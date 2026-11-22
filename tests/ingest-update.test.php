<?php
/**
 * POST /api/ingest/update — an agent changing a PUBLISHED story — end to
 * end over real HTTP on a disposable database.
 *
 * The lane is the one exception to "a published story changes only at an
 * editor's desk", so this file proves the exception stays exactly as
 * narrow as docs/api-ingest.md promises:
 *
 *   1. the grant is separate and fail-closed: no grant, no read, no write;
 *   2. scope is the key's papers, the serving hostname and the key's
 *      desks, with one revealing-nothing 403; the story must sit on the
 *      requested desk; a story another paper also carries is refused;
 *   3. only PUBLISHED stories: an editor's draft is untouched (409);
 *   4. headline + body change live, under the SAME URL, and nothing the
 *      lane does not own moves — status, dates, byline, desk, slug,
 *      correction; the body is sanitized like any editor save;
 *   5. an image swap by the lookup's ref replaces caption and credit,
 *      reports the previous image, and a new picture never inherits the
 *      old one's caption or credit;
 *   6. history: the state before the first agent change is snapshotted,
 *      every change after it is too, and an EDITOR'S RESTORE puts back the
 *      old picture with its own credit (schema 24's post_revisions column);
 *   7. if_version refuses to overwrite a story changed since the agent's
 *      look; an identical request writes nothing at all;
 *   8. payload rules, and the hourly limit.
 *
 * A draft-only key holds the grant throughout: that is the owner's real
 * configuration, and it shows the grant is independent of publishing.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('ingestupdate');
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
require $root . '/app/ingest.php';
$pdo = db();
echo 'engine: ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . " ({$fx['id']})\n";

/* ---- Fixtures --------------------------------------------------------------- */
$pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute(['Local News', 'local-news']);
$pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute(['Environment', 'environment']);
$pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?,?,?)')->execute(['Other Paper', 'other-paper', now()]);
$siteA = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'prairiedispatch'")->fetchColumn();
$siteB = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'other-paper'")->fetchColumn();
$deskLocal = (int) $pdo->query("SELECT id FROM categories WHERE slug = 'local-news'")->fetchColumn();
$deskEnv = (int) $pdo->query("SELECT id FROM categories WHERE slug = 'environment'")->fetchColumn();
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')->execute(['paper-a.test', 'prairiedispatch', now()]);
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')->execute(['paper-b.test', 'other-paper', now()]);

$mint = function (string $name, string $sites, string $desks, int $update, int $draftOnly = 1, string $lookup = 'all') use ($pdo): string {
    $t = 'hermes_' . bin2hex(random_bytes(28));
    $pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, draft_only, lookup_scope, update_published, created_at) VALUES (?,?,?,?,1,?,?,?,?)')
        ->execute([$name, hash('sha256', $t), $sites, $desks, $draftOnly, $lookup, $update, now()]);
    return $t;
};
$agent    = $mint('upd-agent', 'prairiedispatch', '', 1);          // draft-only WITH the grant
$noGrant  = $mint('upd-no-grant', 'prairiedispatch', '', 0);
$deskHeld = $mint('upd-desk-held', 'prairiedispatch', 'environment', 1);
$bKey     = $mint('upd-paper-b', 'other-paper', '', 1);

$editorEmail = 'editor-upd@test.local';
$pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
    ->execute(['Ed Editor', $editorEmail, password_hash('correct horse pp', PASSWORD_DEFAULT), 'editor', 'ed-editor-upd', now()]);

$mkPost = function (array $sites, int $deskId, string $title, string $slug, string $status, array $over = []) use ($pdo): int {
    $pdo->prepare('INSERT INTO posts (title, slug, category_id, byline, lede, body, image, image_caption, image_credit, source_url,
                                      correction, post_type, origin, status, filed_by, published_at, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$title, $slug, $deskId, 'Automated report', 'The original lede.', '<p>The original body.</p>',
            (string) ($over['image'] ?? ''), (string) ($over['image_caption'] ?? ''), (string) ($over['image_credit'] ?? ''),
            (string) ($over['source_url'] ?? ''), (string) ($over['correction'] ?? ''),
            'story', 'hermes', $status, 'upd-agent',
            $status === 'published' ? '2026-11-20 12:00:00' : null, '2026-11-20 11:00:00', '2026-11-20 12:00:00']);
    $id = (int) pp_last_id('posts');
    foreach ($sites as $sid) {
        $pdo->prepare('INSERT INTO post_sites (post_id, site_id) VALUES (?,?)')->execute([$id, $sid]);
    }
    return $id;
};

$port = 9300 + random_int(0, 80);
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

function api(int $port, string $method, string $path, ?string $token, $body = null, string $host = 'paper-a.test', string $ctype = 'application/json'): array
{
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    $headers = ['Host: ' . $host];
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    if ($body !== null) {
        $headers[] = 'Content-Type: ' . $ctype;
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
                            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, json_decode($raw, true), $raw];
}
$update = fn (string $token, array $body, string $host = 'paper-a.test'): array => api($GLOBALS['port'], 'POST', '/api/ingest/update', $token, $body, $host);

function fresh(): PDO
{
    global $config, $pdo;
    $cfg = require $config;
    if (($cfg['db']['driver'] ?? '') !== 'sqlite') {
        return $pdo;
    }
    $p = new PDO('sqlite:' . $cfg['db']['sqlite_path']);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $p;
}
function row(int $id): array
{
    $s = fresh()->prepare('SELECT * FROM posts WHERE id = ?');
    $s->execute([$id]);
    $r = $s->fetch() ?: [];
    $s->closeCursor();
    return $r;
}
function scalar(string $sql, array $args = []): int
{
    $s = fresh()->prepare($sql);
    $s->execute($args);
    $r = $s->fetch();
    $s->closeCursor();
    return $r ? (int) array_values($r)[0] : 0;
}
function revisions(int $id): array
{
    $s = fresh()->prepare('SELECT id, title, image, image_caption, image_credit, saved_by, reason FROM post_revisions WHERE post_id = ? ORDER BY id');
    $s->execute([$id]);
    $r = $s->fetchAll();
    $s->closeCursor();
    return $r;
}

// Two real uploads, through the lane an agent would use.
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$upload = function () use ($port, $agent, $png, &$storedFiles): string {
    [$code, $j] = api($port, 'POST', '/api/ingest-media', $agent, $png, 'paper-a.test', 'image/png');
    $path = is_array($j) ? (string) ($j['path'] ?? '') : '';
    if ($path !== '') {
        $storedFiles[] = $path;
    }
    return $path;
};
$oldArt = $upload();
$newArt = $upload();
$thirdArt = $upload();
ok($oldArt !== '' && $newArt !== '' && $thirdArt !== '', 'setup: the draft-only key uploads three images');

$pub   = $mkPost([$siteA], $deskLocal, 'Original Headline On The Wire', 'original-headline-on-the-wire', 'published',
    ['image' => $oldArt, 'image_caption' => 'The old caption.', 'image_credit' => 'Old Photographer',
     'source_url' => 'https://city.example/release/1', 'correction' => 'An editor\'s correction note.']);
$draft = $mkPost([$siteA], $deskLocal, 'An Editor Draft', 'an-editor-draft', 'draft');
$synd  = $mkPost([$siteA, $siteB], $deskLocal, 'Carried By Two Papers', 'carried-by-two-papers', 'published');
$bOnly = $mkPost([$siteB], $deskLocal, 'Only On Paper B', 'only-on-paper-b', 'published');
$before = row($pub);

/* ---- 1. No grant: refused before anything is read ---------------------------- */
[$code, $j] = $update($noGrant, ['site' => 'prairiedispatch', 'desk' => 'local-news',
    'url' => 'https://paper-a.test/story/original-headline-on-the-wire', 'title' => 'Should Not Land']);
ok($code === 403 && $j === ['ok' => false, 'error' => 'this token has no update permission',
    'reason' => 'update_published is granted at minting (--update-published); without it this surface changes nothing'],
   "1. a key without the grant: 403 {ok, error, reason} (got $code " . json_encode($j) . ')');

/* ---- 2. Scope: one refusal, revealing nothing -------------------------------- */
$oos = ['ok' => false, 'error' => 'out of scope'];
$pubUrl = 'https://paper-a.test/story/original-headline-on-the-wire';
$cases = [
    'another paper\'s site' => [$agent, ['site' => 'other-paper', 'desk' => 'local-news', 'url' => 'https://paper-b.test/story/only-on-paper-b', 'title' => 'X'], 'paper-b.test'],
    'the right site on the wrong hostname' => [$agent, ['site' => 'prairiedispatch', 'desk' => 'local-news', 'url' => $pubUrl, 'title' => 'X'], 'paper-b.test'],
    'a story URL on another paper\'s host' => [$agent, ['site' => 'prairiedispatch', 'desk' => 'local-news', 'url' => 'https://paper-b.test/story/only-on-paper-b', 'title' => 'X'], 'paper-a.test'],
    'a desk outside a desk-held key' => [$deskHeld, ['site' => 'prairiedispatch', 'desk' => 'local-news', 'url' => $pubUrl, 'title' => 'X'], 'paper-a.test'],
    'paper B\'s key on paper A' => [$bKey, ['site' => 'prairiedispatch', 'desk' => 'local-news', 'url' => $pubUrl, 'title' => 'X'], 'paper-a.test'],
];
foreach ($cases as $label => [$tok, $body, $host]) {
    [$code, $j] = $update($tok, $body, $host);
    ok($code === 403 && $j === $oos, "2. $label: the three-word 403 (got $code " . json_encode($j) . ')');
}
[$code, $j] = $update($agent, ['site' => 'prairiedispatch', 'desk' => 'environment', 'url' => $pubUrl, 'title' => 'X']);
ok($code === 404 && ($j['ok'] ?? null) === false, "2. the right story under the WRONG desk: 404, it is not on that desk (got $code)");
[$code, $j] = $update($agent, ['site' => 'prairiedispatch', 'desk' => 'local-news',
    'url' => 'https://paper-a.test/story/carried-by-two-papers', 'title' => 'X']);
ok($code === 409 && str_contains((string) ($j['error'] ?? ''), 'also carried by a paper'),
   "2. a story another paper also carries: 409, it is that paper's page too (got $code " . json_encode($j) . ')');
ok(row($synd)['title'] === 'Carried By Two Papers' && row($bOnly)['title'] === 'Only On Paper B', '2. none of those wrote anything');

/* ---- 3. Only published stories --------------------------------------------- */
[$code, $j] = $update($agent, ['site' => 'prairiedispatch', 'desk' => 'local-news',
    'url' => 'https://paper-a.test/story/an-editor-draft', 'title' => 'Rewritten By An Agent']);
ok($code === 409 && str_contains((string) ($j['error'] ?? ''), 'not published'), "3. an editor's draft: 409 not published (got $code)");
ok(row($draft)['title'] === 'An Editor Draft', "3. the editor's draft is untouched");

/* ---- 4. Headline + body change live, nothing else moves ---------------------- */
$auditBefore = scalar("SELECT COUNT(*) FROM audit_log WHERE action = 'ingest-update'");
[$code, $j] = $update($agent, ['site' => 'prairiedispatch', 'desk' => 'local-news', 'url' => $pubUrl,
    'title' => 'Updated Headline From The Desk Agent',
    'body' => '<p>The updated body.</p><script>alert(1)</script>']);
ok($code === 200 && ($j['ok'] ?? false) === true && ($j['updated'] ?? null) === ['title', 'body']
   && ($j['status'] ?? '') === 'published' && ($j['url'] ?? '') === $pubUrl
   && preg_match('/^ar_[0-9a-f]{24}$/', (string) ($j['ref'] ?? '')) === 1
   && preg_match('/^v_[0-9a-f]{16}$/', (string) ($j['version'] ?? '')) === 1 && !isset($j['previous_image']),
   "4. headline + body: 200 {ok, ref, status, url, updated: [title, body], version} (got $code " . json_encode($j) . ')');
$after = row($pub);
ok($after['title'] === 'Updated Headline From The Desk Agent' && str_contains((string) $after['body'], 'The updated body.')
   && !str_contains((string) $after['body'], '<script'), '4. the live row has the new headline and the SANITIZED body');
foreach (['slug', 'status', 'published_at', 'byline', 'category_id', 'correction', 'lede', 'image', 'image_caption', 'image_credit', 'meta_description'] as $k) {
    ok((string) $after[$k] === (string) $before[$k], "4. $k did not move");
}
ok($after['updated_at'] !== $before['updated_at'], '4. updated_at records the change');
$revs = revisions($pub);
ok(count($revs) === 2 && $revs[0]['reason'] === 'before agent update' && $revs[0]['title'] === 'Original Headline On The Wire'
   && $revs[0]['image'] === $oldArt && $revs[0]['image_credit'] === 'Old Photographer'
   && $revs[1]['reason'] === 'agent update' && $revs[1]['saved_by'] === 'hermes:upd-agent'
   && $revs[1]['title'] === 'Updated Headline From The Desk Agent',
   '4. history holds the pre-update story (with its credit) and the agent\'s version, signed (got ' . json_encode($revs) . ')');
$a = fresh()->prepare("SELECT site_id, user_name, target, detail FROM audit_log WHERE action = 'ingest-update' ORDER BY id DESC LIMIT 1");
$a->execute();
$a = $a->fetch();
ok(scalar("SELECT COUNT(*) FROM audit_log WHERE action = 'ingest-update'") === $auditBefore + 1
   && $a && (int) $a['site_id'] === $siteA && $a['user_name'] === 'hermes:upd-agent'
   && $a['target'] === 'original-headline-on-the-wire' && $a['detail'] === 'published story updated: title, body',
   '4. audited once against the paper, naming the key and the fields (got ' . json_encode($a) . ')');
[$pcode, , $page] = api($port, 'GET', '/story/original-headline-on-the-wire', null);
ok($pcode === 200 && str_contains($page, 'Updated Headline From The Desk Agent') && str_contains($page, 'The updated body.'),
   "4. the public page serves the new headline and body at the same URL (got $pcode)");

/* ---- 5. Image swap by the lookup's ref ---------------------------------------- */
[$code, $j] = api($port, 'POST', '/api/ingest/lookup', $agent, ['site' => 'prairiedispatch', 'desk' => 'local-news',
    'headline' => 'Updated Headline From The Desk Agent', 'source_url' => 'https://city.example/release/1']);
$ref = is_array($j) ? (string) ($j['article']['ref'] ?? '') : '';
ok($code === 200 && ($j['result'] ?? '') === 'match' && $ref !== '', "5. setup: lookup finds the story and hands out its ref (got $code " . json_encode($j) . ')');
[$code, $j] = $update($agent, ['site' => 'prairiedispatch', 'desk' => 'local-news', 'ref' => $ref,
    'image' => $newArt, 'image_caption' => 'The new caption.', 'image_credit' => 'New Photographer']);
ok($code === 200 && ($j['updated'] ?? null) === ['image', 'image_caption', 'image_credit']
   && ($j['previous_image'] ?? '') === $oldArt && ($j['ref'] ?? '') === $ref,
   "5. image swap by ref: 200, all three image fields, the previous image reported (got $code " . json_encode($j) . ')');
$r = row($pub);
ok($r['image'] === $newArt && $r['image_caption'] === 'The new caption.' && $r['image_credit'] === 'New Photographer'
   && $r['title'] === 'Updated Headline From The Desk Agent', '5. the row carries the new picture, caption and credit; the headline stayed');
$version = (string) ($j['version'] ?? '');
// A third picture, sent alone: the new picture never inherits the old
// caption or credit.
[$code, $j] = $update($agent, ['site' => 'prairiedispatch', 'desk' => 'local-news', 'ref' => $ref, 'image' => $thirdArt]);
$r = row($pub);
ok($code === 200 && $r['image'] === $thirdArt && $r['image_caption'] === '' && $r['image_credit'] === '',
   "5. a picture sent without caption or credit clears both — they described a different photo (got $code " . json_encode($j) . ')');
$latest = (string) ($j['version'] ?? '');

/* ---- 6. An editor's restore puts back the old picture WITH its credit ----------- */
$jar = $work . '/jar-editor';
$http = function (string $method, string $path, array $fields = []) use ($jar, $port): array {
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => false]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body];
};
[, $loginPage] = $http('GET', '/admin/login.php');
preg_match('/name="csrf" value="([0-9a-f]+)"/', $loginPage, $m);
$csrf = $m[1] ?? '';
[$lc] = $http('POST', '/admin/login.php', ['csrf' => $csrf, 'email' => $editorEmail, 'password' => 'correct horse pp']);
ok($lc === 302, "6. setup: the editor signs in (got $lc)");
$baseline = revisions($pub)[0];
[$rc] = $http('POST', '/admin/revision.php', ['csrf' => $csrf, 'id' => (int) $baseline['id'], 'action' => 'restore']);
$r = row($pub);
ok($rc === 302 && $r['image'] === $oldArt && $r['image_caption'] === 'The old caption.' && $r['image_credit'] === 'Old Photographer'
   && $r['title'] === 'Original Headline On The Wire' && $r['status'] === 'published',
   "6. the editor restores the pre-agent revision: old headline, old picture, old caption AND old credit (got $rc " . json_encode([$r['image'], $r['image_credit']]) . ')');

/* ---- 7. if_version, and a request that changes nothing --------------------------- */
[$code, $j] = $update($agent, ['site' => 'prairiedispatch', 'desk' => 'local-news', 'ref' => $ref,
    'title' => 'Would Overwrite The Editor', 'if_version' => $latest]);
ok($code === 409 && preg_match('/^v_[0-9a-f]{16}$/', (string) ($j['version'] ?? '')) === 1,
   "7. if_version from before the editor's restore: 409 with the current version (got $code " . json_encode($j) . ')');
ok(row($pub)['title'] === 'Original Headline On The Wire', '7. and the restored headline is untouched');
$current = (string) ($j['version'] ?? '');
$revCount = count(revisions($pub));
$auditCount = scalar("SELECT COUNT(*) FROM audit_log WHERE action = 'ingest-update'");
[$code, $j] = $update($agent, ['site' => 'prairiedispatch', 'desk' => 'local-news', 'ref' => $ref,
    'title' => 'Original Headline On The Wire', 'if_version' => $current]);
ok($code === 200 && ($j['unchanged'] ?? false) === true && ($j['version'] ?? '') === $current
   && count(revisions($pub)) === $revCount && scalar("SELECT COUNT(*) FROM audit_log WHERE action = 'ingest-update'") === $auditCount,
   "7. the current version and an identical title: 200 unchanged, no history row, no audit row (got $code " . json_encode($j) . ')');

/* ---- 8. Payload rules and the hourly limit ---------------------------------------- */
$bad = [
    'neither ref nor url' => [['title' => 'X'], 'send exactly one of ref'],
    'both ref and url' => [['ref' => $ref, 'url' => $pubUrl, 'title' => 'X'], 'send exactly one of ref'],
    'a malformed ref' => [['ref' => 'ar_nothex', 'title' => 'X'], 'ref must be an article reference'],
    'nothing to change' => [['ref' => $ref], 'nothing to update'],
    'an empty headline' => [['ref' => $ref, 'title' => '   '], 'title cannot be empty'],
    'an image that is not an upload path' => [['ref' => $ref, 'image' => 'https://elsewhere.example/x.png'], 'image must be an /uploads/ path'],
    'an upload path that does not exist' => [['ref' => $ref, 'image' => '/uploads/2026/11/never-uploaded.png'], 'image must be an /uploads/ path'],
];
foreach ($bad as $label => [$extra, $needle]) {
    [$code, $j] = $update($agent, array_merge(['site' => 'prairiedispatch', 'desk' => 'local-news'], $extra));
    ok($code === 422 && str_contains((string) ($j['error'] ?? ''), $needle), "8. $label: 422 (got $code " . json_encode($j) . ')');
}
$ins = fresh()->prepare("INSERT INTO audit_log (site_id, user_id, user_name, action, target, detail, ip, created_at) VALUES (?, 0, 'hermes:upd-agent', 'ingest-update', 'fixture', 'fixture', '', ?)");
for ($i = 0; $i < 60; $i++) {
    $ins->execute([$siteA, now()]);
}
[$code, $j] = $update($agent, ['site' => 'prairiedispatch', 'desk' => 'local-news', 'ref' => $ref, 'title' => 'Sixty-First Of The Hour']);
ok($code === 429 && ($j['error'] ?? '') === 'rate limit: at most 60 updates per hour for this token' && row($pub)['title'] === 'Original Headline On The Wire',
   "8. the sixty-first update of the hour: 429, nothing written (got $code)");

if ($fails === 0) {
    echo "ingest-update: all checks pass\n";
    exit(0);
}
exit(1);
