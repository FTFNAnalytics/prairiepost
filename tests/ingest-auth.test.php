<?php
/**
 * Every ingest endpoint against every kind of credential, over real HTTP on
 * a disposable database.
 *
 * Four endpoints answer the ingest bearer token. Each must fail closed for
 * a token that is missing, unknown or revoked, and each must give a
 * draft-only token its documented answer (docs/api-ingest.md):
 * /api/ingest-media stores the upload, /api/ingest files a draft whatever
 * the desk or the flag asked for, /api/ingest-queue refuses, and
 * /api/ingest-publish refuses and leaves the story alone.
 *
 * tests/draft-only.test.php proves those rules as a list of cases. This
 * file proves them as a list of ENDPOINTS, because a list of cases is what
 * let one through: when draft-only tokens were added, the first commit
 * (952f285) reached three endpoints and the queue went on answering a
 * draft-only token until a later commit of the same pull request (0de31aa).
 * Section 1 reads the routes out of router.php and .htaccess and the
 * ingest*.php files out of the tree, and fails unless each one has a row in
 * $endpoints — and a row is not complete without saying what a draft-only
 * token gets. A fifth endpoint cannot arrive without that being written
 * down here.
 *
 * Refusals are compared as whole bodies, keys in order: these answers are
 * what agent authors code against, so a refactor must not move a word.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('ingestauth');
pp_fixture_prepare($fx);
$work = $fx['work'];
$config = $fx['config'];

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

putenv('PP_CONFIG=' . $config);
define('PP_TEST_BOOT', 1);
require $root . '/app/bootstrap.php';
$pdo = db();

/* ---- Fixtures: a wire desk and a plain desk, a second paper, six tokens ---- */
$pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute(['Local News', 'local-news']);
$pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute(['Environment', 'environment']);
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')
    ->execute(['paper.test', 'prairiedispatch', now()]);
$pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?,?,?)')
    ->execute(['Other Paper', 'other-paper', now()]);
$siteId = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'prairiedispatch'")->fetchColumn();
// local-news is this paper's live wire: a standard token publishes there at once.
$pdo->prepare('INSERT INTO settings (site_id, skey, svalue) VALUES (?, ?, ?)')
    ->execute([$siteId, 'wire_desks', 'local-news']);

$mint = function (string $name, string $sites, string $desks, int $enabled, int $draftOnly) use ($pdo): string {
    $t = 'hermes_' . bin2hex(random_bytes(28));
    $pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, draft_only, created_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([$name, hash('sha256', $t), $sites, $desks, $enabled, $draftOnly, now()]);
    return $t;
};
$standard         = $mint('authtest-standard', 'prairiedispatch', '', 1, 0);
$draftOnly        = $mint('authtest-draft-only', 'prairiedispatch', '', 1, 1);
$revoked          = $mint('authtest-revoked', 'prairiedispatch', '', 0, 0);
$revokedDraftOnly = $mint('authtest-revoked-draft-only', 'prairiedispatch', '', 0, 1);
$elsewhere        = $mint('authtest-elsewhere', 'other-paper', '', 1, 0);
$deskBound        = $mint('authtest-desk-bound', 'prairiedispatch', 'environment', 1, 0);
$neverIssued      = 'hermes_' . bin2hex(random_bytes(28));   // well-formed, in no row

$port = 9100 + random_int(0, 80);
$cmd = 'PP_CONFIG=' . escapeshellarg($config) . ' ' . escapeshellarg(PHP_BINARY)
     . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("$work/server.log") . ' 2>&1 & echo $!';
$serverPid = (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && ' . $cmd));
usleep(700000);
$storedFiles = [];
register_shutdown_function(function () use ($serverPid, $fx, &$storedFiles, $root) {
    if ($serverPid) {
        @posix_kill($serverPid, 15);
    }
    foreach ($storedFiles as $f) {
        @unlink($root . $f);
    }
    pp_fixture_destroy($fx);
});

$codes = [];   // every HTTP status this suite saw — none may be 5xx
/**
 * One request. $authorization is the whole header value, or null to send
 * none. Returns [status, decoded body (null when it is not JSON), raw body,
 * content type].
 */
function api(int $port, string $method, string $path, ?string $authorization, $body = null, string $ctype = 'application/json'): array
{
    global $codes;
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    $headers = $authorization !== null ? ['Authorization: ' . $authorization] : [];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
                            CURLOPT_CUSTOMREQUEST => $method]);
    if ($body !== null) {
        $headers[] = 'Content-Type: ' . $ctype;
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    $codes[] = $code;
    return [$code, json_decode($raw, true), $raw, $type];
}

// Reads after the server writes go through a fresh connection: under WAL
// an open cursor pins the long-lived handle to a read snapshot.
function fresh(): PDO
{
    global $config, $pdo;
    $cfg = require $config;
    if (($cfg['db']['driver'] ?? '') !== 'sqlite') {
        return $pdo;   // read-committed engines see other processes' commits
    }
    $p = new PDO('sqlite:' . $cfg['db']['sqlite_path']);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $p;
}
function one(string $sql, array $args = []): array|false
{
    $s = fresh()->prepare($sql);
    $s->execute($args);
    $r = $s->fetch();
    $s->closeCursor();
    return $r;
}
function scalar(string $sql, array $args = []): int
{
    $r = one($sql, $args);
    return $r ? (int) array_values($r)[0] : 0;
}
function post(string $slug): array|false
{
    return one('SELECT id, status, awaiting_image, image, published_at, filed_by FROM posts WHERE slug = ?', [$slug]);
}
function uploadsOnDisk(string $root): int
{
    return count(glob($root . '/uploads/*/*/*') ?: []);
}

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$json = 'application/json; charset=utf-8';

/* ---- 1. The endpoints: the tree's list and this file's list are the same --- */
// Each row is the request a standard token is answered 2xx for, so that in
// every case below the credential is the only thing wrong with the call.
// $art and $waitSlug are filled in by the setup calls that follow.
$art = '';
$waitSlug = '';
$waitId = 0;
$filing = fn (array $over): array => array_merge([
    'site' => 'prairiedispatch', 'desk' => 'environment',
    'lede' => 'A lede long enough to pass the gate.',
    'body' => '<p>Body copy for the ingest-auth suite.</p>',
], $over);
// The filing that asks for everything a token can ask for: the wire desk,
// the ready flag, and the art that would let the flag publish at once.
$askForEverything = fn (string $title): array => $filing([
    'desk' => 'local-news', 'title' => $title, 'publish_on_image' => true, 'image' => $GLOBALS['art'],
]);

$endpoints = [
    '/api/ingest-media' => [
        'file' => 'ingest-media.php', 'method' => 'POST', 'other_method' => 'GET',
        'method_error' => 'POST one image per call',
        'send' => fn (?string $authorization) => api($port, 'POST', '/api/ingest-media', $authorization, $png, 'image/png'),
    ],
    '/api/ingest' => [
        'file' => 'ingest.php', 'method' => 'POST', 'other_method' => 'GET',
        'method_error' => 'POST one JSON story per call',
        'send' => fn (?string $authorization) => api($port, 'POST', '/api/ingest', $authorization, $askForEverything('Refused filing')),
    ],
    '/api/ingest-queue' => [
        'file' => 'ingest-queue.php', 'method' => 'GET', 'other_method' => 'POST',
        'method_error' => 'GET, with the bearer token',
        'send' => fn (?string $authorization) => api($port, 'GET', '/api/ingest-queue', $authorization),
    ],
    '/api/ingest-publish' => [
        'file' => 'ingest-publish.php', 'method' => 'POST', 'other_method' => 'GET',
        'method_error' => 'POST one JSON attach-and-publish per call',
        'send' => fn (?string $authorization) => api($port, 'POST', '/api/ingest-publish', $authorization,
            ['story' => $GLOBALS['waitSlug'], 'image' => $GLOBALS['art']]),
    ],
];

// What the tree routes. router.php and .htaccess are two of the three homes
// a route has (the third is each paper's nginx block, which is not in a
// checkout to read).
$routed = [];
preg_match_all("#'(/api/ingest[a-z-]*)/?'\s*=>\s*'/([a-z-]+\.php)'#", (string) file_get_contents($root . '/router.php'), $mm, PREG_SET_ORDER);
foreach ($mm as $m) {
    $routed[$m[1]] = $m[2];
}
$rewritten = [];
preg_match_all('#^RewriteRule \^api/(ingest[a-z-]*)/\?\$ ([a-z-]+\.php) #m', (string) file_get_contents($root . '/.htaccess'), $mm, PREG_SET_ORDER);
foreach ($mm as $m) {
    $rewritten['/api/' . $m[1]] = $m[2];
}
$onDisk = array_map('basename', glob($root . '/ingest*.php') ?: []);
$known = array_map(fn (array $e): string => $e['file'], $endpoints);
ksort($routed);
ksort($rewritten);
ksort($known);
sort($onDisk);
$knownFiles = array_values($known);
sort($knownFiles);

ok(count($routed) >= 4, 'router.php was read: it routes at least the four ingest endpoints (found ' . count($routed) . ')');
ok($routed === $known,
   'every ingest route in router.php has a row in this file, and nothing else does (router: '
   . json_encode($routed) . ')');
ok($rewritten === $routed, '.htaccess rewrites exactly the routes router.php maps (.htaccess: ' . json_encode($rewritten) . ')');
ok($onDisk === $knownFiles,
   'every ingest*.php file in the web root is one of those endpoints (on disk: ' . implode(', ', $onDisk) . ')');

/* ---- Setup, by a standard token: the art, and a story that waits for it ---- */
[$code, $j] = $endpoints['/api/ingest-media']['send']("Bearer $standard");
ok($code === 201 && is_array($j) && str_starts_with((string) ($j['path'] ?? ''), '/uploads/'), "setup: a standard token uploads the art (got $code)");
$art = (string) ($j['path'] ?? '');
$storedFiles[] = $art;
[$code, $j] = api($port, 'POST', '/api/ingest', "Bearer $standard",
    $filing(['title' => 'Waiting for art on the first paper', 'publish_on_image' => true]));
ok($code === 201 && is_array($j) && ($j['awaiting_image'] ?? false) === true, "setup: a standard token's flagged filing waits for art (got $code)");
$waitSlug = (string) ($j['slug'] ?? '');
$waitId = (int) ($j['id'] ?? 0);
[$code, $j] = api($port, 'POST', '/api/ingest', "Bearer $elsewhere",
    $filing(['site' => 'other-paper', 'title' => 'Waiting for art on the other paper', 'publish_on_image' => true]));
ok($code === 201 && is_array($j) && ($j['awaiting_image'] ?? false) === true, "setup: the other paper has a waiting story of its own (got $code)");
$otherWaitSlug = (string) ($j['slug'] ?? '');
[$code, $j] = api($port, 'POST', '/api/ingest', "Bearer $standard", $filing(['title' => 'An ordinary newsroom draft']));
ok($code === 201 && is_array($j) && ($j['status'] ?? '') === 'draft' && !isset($j['awaiting_image']), "setup: an unflagged draft exists (got $code)");
$plainSlug = (string) ($j['slug'] ?? '');

/* ---- 2. The method is checked before the token, on every endpoint ---------- */
foreach ($endpoints as $route => $e) {
    [$code, $j, , $type] = api($port, $e['other_method'], $route, null);
    ok($code === 405 && $j === ['ok' => false, 'error' => $e['method_error']] && $type === $json,
       "$route answers {$e['other_method']} with 405 and its own message, token or no token (got $code " . json_encode($j) . ')');
}

/* ---- 3. No agent: 401 on every endpoint, and nothing happens -------------- */
// The first three never reach the token table; the last three do, and a
// revoked token must be indistinguishable from one that was never issued.
$noAgent = [
    'no Authorization header'                 => [null, 'missing bearer token'],
    'a scheme other than Bearer'              => ['Basic ' . base64_encode('agent:' . $standard), 'missing bearer token'],
    'a bearer value too short to be a token'  => ['Bearer hermes_tooshort', 'missing bearer token'],
    'a token that was never issued'           => ["Bearer $neverIssued", 'unknown or revoked token'],
    'a revoked token'                         => ["Bearer $revoked", 'unknown or revoked token'],
    'a revoked draft-only token'              => ["Bearer $revokedDraftOnly", 'unknown or revoked token'],
];
$postsBefore = scalar('SELECT COUNT(*) FROM posts');
$auditBefore = scalar('SELECT COUNT(*) FROM audit_log');
$filesBefore = uploadsOnDisk($root);
foreach ($endpoints as $route => $e) {
    $bodies = [];
    foreach ($noAgent as $label => [$authorization, $error]) {
        [$code, $j, $raw, $type] = $e['send']($authorization);
        ok($code === 401 && $j === ['ok' => false, 'error' => $error] && $type === $json,
           "$route, $label: 401 \"$error\" (got $code " . json_encode($j) . ')');
        $bodies[$label] = $raw;
    }
    ok($bodies['a revoked token'] === $bodies['a token that was never issued']
       && $bodies['a revoked draft-only token'] === $bodies['a token that was never issued'],
       "$route answers a revoked token byte for byte as it answers one that never existed");
}
ok(scalar('SELECT COUNT(*) FROM posts') === $postsBefore && post('refused-filing') === false, 'the 401s filed nothing');
ok(scalar('SELECT COUNT(*) FROM audit_log') === $auditBefore, 'the 401s wrote no audit row');
ok(uploadsOnDisk($root) === $filesBefore, 'the 401s stored no upload');
$p = post($waitSlug);
ok($p && $p['status'] === 'draft' && (int) $p['awaiting_image'] === 1 && $p['image'] === '' && $p['published_at'] === null,
   'the 401s left the waiting story waiting, art unattached');
ok(scalar("SELECT COUNT(*) FROM ingest_agents WHERE name IN ('authtest-revoked', 'authtest-revoked-draft-only') AND last_used_at IS NOT NULL") === 0,
   'a revoked token is not recorded as used');

/* ---- 4. A draft-only token: the documented answer on every endpoint -------- */
// One entry per endpoint, checked against the table above: an endpoint with
// no entry here fails the suite, which is the case this file exists for.
$draftOnlyAnswer = [
    // Uploads are open to it: a file under /uploads publishes nothing.
    '/api/ingest-media' => function () use (&$endpoints, &$storedFiles, $draftOnly, $png, $root, $json): void {
        [$code, $j, , $type] = $endpoints['/api/ingest-media']['send']("Bearer $draftOnly");
        $path = is_array($j) ? (string) ($j['path'] ?? '') : '';
        ok($code === 201 && $j === ['ok' => true, 'path' => $path, 'bytes' => strlen($png)] && $type === $json
           && preg_match('#^/uploads/\d{4}/\d{2}/agent-image-[0-9a-f]{6}\.png$#', $path) === 1,
           "/api/ingest-media stores a draft-only token's upload: 201 {ok, path, bytes} (got $code " . json_encode($j) . ')');
        ok($path !== '' && is_file($root . $path), 'the upload is on disk where the path says');
        $storedFiles[] = $path;
        $a = one("SELECT site_id, user_id, user_name, detail, ip FROM audit_log WHERE action = 'ingest-media' AND target = ?", [$path]);
        ok($a && (int) $a['site_id'] === 0 && (int) $a['user_id'] === 0 && $a['user_name'] === 'hermes:authtest-draft-only'
           && $a['detail'] === strlen($png) . ' bytes' && $a['ip'] === '127.0.0.1',
           'the upload is audited: the token named, the path as target, the size as detail (got ' . json_encode($a) . ')');
    },
    // It files, but neither publish instruction is honoured.
    '/api/ingest' => function () use ($port, $draftOnly, $askForEverything, &$art, $json): void {
        [$code, $j, , $type] = api($port, 'POST', '/api/ingest', "Bearer $draftOnly", $askForEverything('Draft-only token asks for everything'));
        $id = is_array($j) ? (int) ($j['id'] ?? 0) : 0;
        ok($code === 201 && $type === $json && $id > 0 && $j === [
            'ok' => true, 'id' => $id, 'slug' => 'draft-only-token-asks-for-everything', 'status' => 'draft',
            'reason' => 'draft_only token: this credential files drafts for the newsroom to publish'
                      . ' (wire-desk auto-publish withheld; publish_on_image not honoured)',
            'image' => $art,
        ], "/api/ingest files a draft-only token's wire-desk, ready-flagged, illustrated story as a draft with the reason; no url, no awaiting_image (got $code " . json_encode($j) . ')');
        $p = post('draft-only-token-asks-for-everything');
        ok($p && $p['status'] === 'draft' && (int) $p['awaiting_image'] === 0 && $p['published_at'] === null
           && $p['image'] === $art && $p['filed_by'] === 'authtest-draft-only',
           'the row is a plain draft: not published, not queued, the art kept for the newsroom');
        [$pcode] = api($port, 'GET', '/story/draft-only-token-asks-for-everything', null);
        ok($pcode === 404, "and it is publicly invisible (got $pcode)");
    },
    // The queue is closed to it: a refusal, never an empty list.
    '/api/ingest-queue' => function () use (&$endpoints, $draftOnly, $json): void {
        [$code, $j, $raw, $type] = $endpoints['/api/ingest-queue']['send']("Bearer $draftOnly");
        ok($code === 403 && $type === $json && $j === [
            'ok' => false,
            'error' => 'this token is draft-only — the publish-on-image queue is closed to it',
            'reason' => 'draft_only token: it cannot publish, so it cannot read the stories waiting to be published',
        ], "/api/ingest-queue refuses a draft-only token: 403 {ok, error, reason} (got $code " . json_encode($j) . ')');
        // The whole-body comparison above already shows there is no `stories`
        // key; this says the same of the raw bytes.
        ok(!str_contains($raw, 'Waiting for art'), 'the refusal leaks no headline');
    },
    // Attaching the art is the act that publishes, so it is refused — but
    // only once the request is known to name a waiting story in its scope.
    '/api/ingest-publish' => function () use (&$endpoints, $port, $draftOnly, &$art, &$waitSlug, &$waitId, $plainSlug, $otherWaitSlug, $siteId, $json): void {
        $attach = fn (array $body) => api($port, 'POST', '/api/ingest-publish', "Bearer $draftOnly", $body);
        [$code, $j] = $attach(['story' => $waitSlug, 'image' => 'not-an-upload-path']);
        ok($code === 422 && $j === ['ok' => false, 'error' => 'image must be an /uploads/ path returned by /api/ingest-media'],
           "a draft-only token's malformed attach answers 422 first, as for any token (got $code)");
        [$code, $j] = $attach(['story' => 'no-such-story', 'image' => $art]);
        ok($code === 404 && $j === ['ok' => false, 'error' => 'no such story'],
           "its attach to an unknown story answers 404 first (got $code)");
        [$code, $j] = $attach(['story' => $otherWaitSlug, 'image' => $art]);
        ok($code === 403 && $j === ['ok' => false, 'error' => 'this token is not scoped to that story\'s site'],
           "its attach to another paper's story answers the scope 403 first, with no reason (got $code " . json_encode($j) . ')');
        [$code, $j] = $attach(['story' => $plainSlug, 'image' => $art]);
        ok($code === 409 && $j === ['ok' => false, 'error' => 'that story was not filed as publish-on-image — only its newsroom can publish it'],
           "its attach to a story that is not waiting answers 409 first (got $code)");
        ok(scalar("SELECT COUNT(*) FROM audit_log WHERE action = 'ingest-publish'") === 0, 'none of those four wrote an audit row');

        [$code, $j, , $type] = $endpoints['/api/ingest-publish']['send']("Bearer $draftOnly");
        ok($code === 403 && $type === $json && $j === [
            'ok' => false,
            'error' => 'this token is draft-only — it cannot publish; the story stays a draft for its newsroom',
            'id' => $waitId,
            'slug' => $waitSlug,
            'status' => 'draft',
            'reason' => 'draft_only token: attach-and-publish refused, nothing written',
        ], "/api/ingest-publish refuses a draft-only token on a waiting story: 403 {ok, error, id, slug, status, reason} (got $code " . json_encode($j) . ')');
        $p = post($waitSlug);
        ok($p && $p['status'] === 'draft' && (int) $p['awaiting_image'] === 1 && $p['image'] === '' && $p['published_at'] === null,
           'nothing was written: the story still waits, art unattached');
        $a = one("SELECT site_id, user_name, target, detail FROM audit_log WHERE action = 'ingest-publish'");
        ok(scalar("SELECT COUNT(*) FROM audit_log WHERE action = 'ingest-publish'") === 1
           && $a && (int) $a['site_id'] === $siteId && $a['user_name'] === 'hermes:authtest-draft-only' && $a['target'] === $waitSlug
           && $a['detail'] === 'REFUSED: draft-only token attempted attach-and-publish; story left as a draft, art not attached',
           'the refusal is audited once, against the paper, naming the token and the story (got ' . json_encode($a) . ')');
    },
];
foreach ($endpoints as $route => $e) {
    ok(isset($draftOnlyAnswer[$route]), "$route has a draft-only answer written down in this file");
    if (isset($draftOnlyAnswer[$route])) {
        $draftOnlyAnswer[$route]();
    }
}
ok(array_diff_key($draftOnlyAnswer, $endpoints) === [], 'no draft-only answer is written for an endpoint that does not exist');

/* ---- 5. Scope: a token reaches only its own papers and desks -------------- */
[$code, $j] = api($port, 'POST', '/api/ingest', "Bearer $elsewhere", $filing(['title' => 'Filed across the fence']));
ok($code === 403 && $j === ['ok' => false, 'error' => 'this token is not scoped to that site'] && post('filed-across-the-fence') === false,
   "another paper's token cannot file here: 403, nothing filed (got $code)");
[$code, $j] = api($port, 'POST', '/api/ingest-publish', "Bearer $elsewhere", ['story' => $waitSlug, 'image' => $art]);
ok($code === 403 && $j === ['ok' => false, 'error' => 'this token is not scoped to that story\'s site'],
   "another paper's token cannot publish this paper's waiting story: 403 (got $code)");
[$code, $j] = $endpoints['/api/ingest-queue']['send']("Bearer $elsewhere");
ok($code === 200 && is_array($j) && count($j['stories'] ?? []) === 1 && ($j['stories'][0]['slug'] ?? '') === $otherWaitSlug,
   "another paper's token reads its own paper's queue and not this one's (got $code, " . count($j['stories'] ?? []) . ' stories)');
[$code, $j] = api($port, 'POST', '/api/ingest', "Bearer $deskBound", $filing(['desk' => 'local-news', 'title' => 'Off the desk']));
ok($code === 403 && $j === ['ok' => false, 'error' => 'this token is not scoped to that desk'] && post('off-the-desk') === false,
   "a desk-bound token cannot file to another desk: 403, nothing filed (got $code)");
[$code, $j] = api($port, 'POST', '/api/ingest', "Bearer $deskBound", $filing(['title' => 'On the desk']));
$p = post('on-the-desk');
ok($code === 201 && is_array($j) && ($j['status'] ?? '') === 'draft' && $p && $p['filed_by'] === 'authtest-desk-bound',
   "a desk-bound token files to its own desk, under its own name (got $code)");

/* ---- 6. A standard token: the same four requests succeed ------------------- */
// So every refusal above was about the credential, not the request.
[$code, $j, , $type] = $endpoints['/api/ingest-media']['send']("Bearer $standard");
$path = is_array($j) ? (string) ($j['path'] ?? '') : '';
ok($code === 201 && $j === ['ok' => true, 'path' => $path, 'bytes' => strlen($png)] && $type === $json && is_file($root . $path),
   "a standard token uploads: 201 {ok, path, bytes} (got $code)");
$storedFiles[] = $path;

[$code, $j, , $type] = api($port, 'POST', '/api/ingest', "Bearer $standard", $askForEverything('Standard token asks for everything'));
$id = is_array($j) ? (int) ($j['id'] ?? 0) : 0;
ok($code === 201 && $type === $json && $id > 0 && $j === [
    'ok' => true, 'id' => $id, 'slug' => 'standard-token-asks-for-everything', 'status' => 'published',
    'url' => 'https://paper.test/story/standard-token-asks-for-everything', 'image' => $art,
], "a standard token's identical filing is published at once, with its url and no reason (got $code " . json_encode($j) . ')');
$p = post('standard-token-asks-for-everything');
ok($p && $p['status'] === 'published' && $p['published_at'] !== null && $p['filed_by'] === 'authtest-standard', 'the row is published, under the token\'s name');

[$code, $j, , $type] = $endpoints['/api/ingest-queue']['send']("Bearer $standard");
ok($code === 200 && $type === $json && is_array($j) && array_keys($j) === ['ok', 'stories'] && $j['ok'] === true
   && count($j['stories']) === 1 && $j['stories'][0]['slug'] === $waitSlug
   && array_keys($j['stories'][0]) === ['id', 'slug', 'site', 'desk', 'title', 'lede', 'dateline', 'filed_by', 'filed_at'],
   "a standard token reads the queue: this paper's one waiting story, every documented field (got $code)");

[$code, $j, , $type] = $endpoints['/api/ingest-publish']['send']("Bearer $standard");
ok($code === 200 && $type === $json && $j === [
    'ok' => true, 'id' => $waitId, 'slug' => $waitSlug, 'status' => 'published',
    'url' => 'https://paper.test/story/' . $waitSlug, 'image' => $art,
], "a standard token publishes the story the draft-only token was refused (got $code " . json_encode($j) . ')');
$p = post($waitSlug);
ok($p && $p['status'] === 'published' && (int) $p['awaiting_image'] === 0 && $p['image'] === $art && $p['published_at'] !== null,
   'the row is published with the art attached');

/* ---- 7. Invariants ---------------------------------------------------------- */
ok(scalar("SELECT COUNT(*) FROM posts WHERE filed_by IN ('authtest-revoked', 'authtest-revoked-draft-only')") === 0,
   'no revoked token filed anything');
ok(scalar("SELECT COUNT(*) FROM posts WHERE status = 'published' AND filed_by = 'authtest-draft-only'") === 0,
   'nothing the draft-only token filed is published');
ok(scalar("SELECT COUNT(*) FROM ingest_agents WHERE name IN ('authtest-standard', 'authtest-draft-only', 'authtest-elsewhere', 'authtest-desk-bound') AND last_used_at IS NOT NULL") === 4,
   'each token that stored something is recorded as used');
ok(max($codes) < 500, 'no call answered 5xx (max status ' . max($codes) . ')');

if ($fails === 0) {
    echo "ingest-auth: all checks pass\n";
    exit(0);
}
exit(1);
