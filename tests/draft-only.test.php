<?php
/**
 * Draft-only ingest tokens (milestone NEWS1), over real HTTP on a
 * disposable database: a token minted draft_only files drafts and
 * nothing else — on a wire-configured desk, with publish_on_image and a
 * valid image, and through /api/ingest-publish — while scope (403) and
 * revocation (401) answer exactly as before and a normal token still
 * publishes on a wire desk, so the change is additive. No row a
 * draft-only token filed ends up published, the attach call it is
 * refused leaves the story untouched, and no call answers 5xx. The CLI
 * flag is exercised end to end: tools/make-agent.php --draft-only
 * mints a token that behaves this way; one minted without it publishes;
 * and a mistyped or misplaced flag stops the tool with a non-zero exit
 * and no row, instead of falling through to a standard token.
 *
 * The audit row each call leaves is bounded to its columns and can never
 * change the answer: a slug longer than audit_log.target (VARCHAR(160),
 * enforced by PostgreSQL and ignored by SQLite — so the status codes in
 * section 7c are the PostgreSQL job's assertion) still answers 201 and
 * 403, and so does a filing made while the audit write fails outright.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('draftonly');
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

/* ---- Fixtures: a wire desk and a plain desk, a second paper, three tokens -- */
$pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute(['Local News', 'local-news']);
$pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute(['Environment', 'environment']);
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')
    ->execute(['paper.test', 'prairiedispatch', now()]);
$pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?,?,?)')
    ->execute(['Other Paper', 'other-paper', now()]);
$siteId = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'prairiedispatch'")->fetchColumn();
// local-news is this paper's live wire: a NORMAL token publishes there at once.
$pdo->prepare('INSERT INTO settings (site_id, skey, svalue) VALUES (?, ?, ?)')
    ->execute([$siteId, 'wire_desks', 'local-news']);

$mint = function (string $name, int $draftOnly, int $enabled) use ($pdo): string {
    $t = 'hermes_' . bin2hex(random_bytes(28));
    $pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, draft_only, created_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([$name, hash('sha256', $t), 'prairiedispatch', '', $enabled, $draftOnly, now()]);
    return $t;
};
$draftToken   = $mint('draftonly-writer', 1, 1);
$normalToken  = $mint('draftonly-normal', 0, 1);
$revokedToken = $mint('draftonly-revoked', 1, 0);

$port = 8800 + random_int(0, 80);
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
function api(int $port, string $method, string $path, ?string $token, $body = null, string $ctype = 'application/json'): array
{
    global $codes;
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    $headers = $token !== null ? ['Authorization: Bearer ' . $token] : [];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
                            CURLOPT_CUSTOMREQUEST => $method]);
    if ($body !== null) {
        $headers[] = 'Content-Type: ' . $ctype;
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $out = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $codes[] = $code;
    return [$code, (array) json_decode($out, true)];
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
function row(string $slug): array|false
{
    $s = fresh()->prepare('SELECT status, awaiting_image, image, published_at, filed_by FROM posts WHERE slug = ?');
    $s->execute([$slug]);
    $r = $s->fetch();
    $s->closeCursor();
    return $r;
}
$file = fn (string $token, array $over) => api($port, 'POST', '/api/ingest', $token, array_merge([
    'site' => 'prairiedispatch', 'desk' => 'environment',
    'lede' => 'A lede long enough to pass the gate.',
    'body' => '<p>Body copy for the draft-only suite.</p>',
], $over));

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

/* ---- 1. Draft-only token on a wire-configured desk ------------------------ */
[$code, $j] = $file($draftToken, ['desk' => 'local-news', 'title' => 'Draft-only token on the wire desk']);
ok($code === 201 && ($j['status'] ?? '') === 'draft',
   "draft-only token on the wire desk files a DRAFT (got $code/" . ($j['status'] ?? '?') . ')');
ok(is_string($j['reason'] ?? null) && ($j['reason'] ?? '') !== '' && str_contains((string) ($j['reason'] ?? ''), 'wire'),
   'the response carries a reason naming the withheld wire-desk publish (got ' . json_encode($j['reason'] ?? null) . ')');
ok(!isset($j['url']) && !isset($j['awaiting_image']), 'no public URL and no queue flag are handed back');
$wireSlug = (string) ($j['slug'] ?? '');
$p = row($wireSlug);
ok($p && $p['status'] === 'draft' && $p['published_at'] === null && (int) $p['awaiting_image'] === 0,
   'the row is a plain draft: not published, not queued');
[$pcode] = api($port, 'GET', '/story/' . $wireSlug, null);
ok($pcode === 404, "the wire-desk draft is publicly invisible (got $pcode)");

/* ---- 2. Draft-only token, publish_on_image + a valid image ----------------- */
[$code, $j] = api($port, 'POST', '/api/ingest-media', $draftToken, $png, 'image/png');
ok($code === 201 && str_starts_with((string) ($j['path'] ?? ''), '/uploads/'), "a draft-only token may still upload art (got $code)");
$art = (string) ($j['path'] ?? '');
$storedFiles[] = $art;

[$code, $j] = $file($draftToken, ['title' => 'Draft-only with ready flag and art', 'publish_on_image' => true, 'image' => $art]);
ok($code === 201 && ($j['status'] ?? '') === 'draft',
   "ready flag + valid image stays a DRAFT for a draft-only token (got $code/" . ($j['status'] ?? '?') . ')');
ok(str_contains((string) ($j['reason'] ?? ''), 'publish_on_image'), 'the reason says the flag was not honoured');
ok(($j['awaiting_image'] ?? false) !== true && !isset($j['url']), 'it is neither reported as awaiting art nor given a URL');
$artSlug = (string) ($j['slug'] ?? '');
$p = row($artSlug);
ok($p && $p['status'] === 'draft' && (int) $p['awaiting_image'] === 0 && $p['image'] === $art && $p['published_at'] === null,
   'the draft keeps the art for the newsroom but is unpublished and unqueued');
[$pcode] = api($port, 'GET', '/story/' . $artSlug, null);
ok($pcode === 404, "the flagged-with-art draft is publicly invisible (got $pcode)");

// The flag WITHOUT art: still never queued, so no other token can publish it later.
[$code, $j] = $file($draftToken, ['title' => 'Draft-only with ready flag, no art', 'publish_on_image' => true]);
ok($code === 201 && ($j['status'] ?? '') === 'draft' && ($j['awaiting_image'] ?? false) !== true,
   "ready flag without art stays an unqueued draft (got $code)");
$p = row((string) ($j['slug'] ?? ''));
ok($p && (int) $p['awaiting_image'] === 0, 'the row is not flagged awaiting_image');
[$code, $q] = api($port, 'GET', '/api/ingest-queue', $normalToken);
ok($code === 200 && ($q['stories'] ?? ['x']) === [], "a draft-only filing never enters the image agent's queue (got $code)");

/* ---- 3. Draft-only token calling /api/ingest-publish ---------------------- */
// A NORMAL token files a story that genuinely waits for art — the one
// state the lane may publish — so the refusal below is about the token.
[$code, $j] = $file($normalToken, ['title' => 'Waiting for art, filed by a normal token', 'publish_on_image' => true]);
ok($code === 201 && ($j['status'] ?? '') === 'draft' && ($j['awaiting_image'] ?? false) === true,
   "a normal token's flagged filing waits in the queue (got $code)");
$waitSlug = (string) ($j['slug'] ?? '');

[$code, $j] = api($port, 'POST', '/api/ingest-publish', $draftToken, ['story' => $waitSlug, 'image' => $art]);
ok($code === 403 && ($j['ok'] ?? true) === false && ($j['status'] ?? '') === 'draft',
   "draft-only token cannot attach-and-publish: 403 with status draft (got $code/" . ($j['status'] ?? '?') . ')');
ok(is_string($j['reason'] ?? null) && ($j['reason'] ?? '') !== '', 'the refusal carries a reason string');
$p = row($waitSlug);
ok($p && $p['status'] === 'draft' && (int) $p['awaiting_image'] === 1 && $p['image'] === '' && $p['published_at'] === null,
   'nothing was written: the story still waits, art unattached');
[$pcode] = api($port, 'GET', '/story/' . $waitSlug, null);
ok($pcode === 404, "the waiting story is still publicly invisible (got $pcode)");
$refused = (int) fresh()->query("SELECT COUNT(*) FROM audit_log WHERE action = 'ingest-publish' AND detail LIKE 'REFUSED%'")->fetchColumn();
ok($refused === 1, "the refusal is audited (got $refused rows)");

// Additive: the SAME story publishes for a normal token — only the credential was refused.
[$code, $j] = api($port, 'POST', '/api/ingest-publish', $normalToken, ['story' => $waitSlug, 'image' => $art]);
ok($code === 200 && ($j['status'] ?? '') === 'published', "the same story publishes for a normal token (got $code)");

/* ---- 4. Out-of-scope paper: 403, unchanged -------------------------------- */
[$code, $j] = $file($draftToken, ['site' => 'other-paper', 'title' => 'Out of scope']);
ok($code === 403 && str_contains((string) ($j['error'] ?? ''), 'scoped'), "a paper outside the token's scope → 403 (got $code)");
ok(row('out-of-scope') === false, 'and nothing was filed there');

/* ---- 5. Revoked draft-only token: 401 on every endpoint, unchanged -------- */
[$code] = $file($revokedToken, ['desk' => 'local-news', 'title' => 'Revoked token filing']);
ok($code === 401, "revoked token → 401 on /api/ingest (got $code)");
[$code] = api($port, 'POST', '/api/ingest-publish', $revokedToken, ['story' => $waitSlug, 'image' => $art]);
ok($code === 401, "revoked token → 401 on /api/ingest-publish (got $code)");
[$code] = api($port, 'POST', '/api/ingest-media', $revokedToken, $png, 'image/png');
ok($code === 401, "revoked token → 401 on /api/ingest-media (got $code)");
ok(row('revoked-token-filing') === false, 'the revoked token filed nothing');

/* ---- 6. Additive: a normal token still publishes on the wire desk --------- */
[$code, $j] = $file($normalToken, ['desk' => 'local-news', 'title' => 'Normal token on the wire desk']);
ok($code === 201 && ($j['status'] ?? '') === 'published' && !isset($j['reason'])
   && ($j['url'] ?? '') === 'https://paper.test/story/' . ($j['slug'] ?? ''),
   "a normal token on the wire desk publishes at once, no reason attached (got $code/" . ($j['status'] ?? '?') . ')');
$normalWireSlug = (string) ($j['slug'] ?? '');
$p = row($normalWireSlug);
ok($p && $p['status'] === 'published' && $p['published_at'] !== null, 'the row is published with a timestamp');
[$pcode] = api($port, 'GET', '/story/' . $normalWireSlug, null);
ok($pcode === 200, "the wire story is publicly live (got $pcode)");

/* ---- 7. The CLI flag, end to end -------------------------------------------- */
[$out, $rc] = pp_fixture_tool($fx, 'tools/make-agent.php create --name cli-draft-only --sites prairiedispatch --draft-only');
ok($rc === 0 && preg_match('/Token:\s+(hermes_[0-9a-f]{56})/', $out, $tm) === 1 && str_contains($out, 'DRAFT-ONLY'),
   "make-agent --draft-only mints a token and says so (rc=$rc)");
$cliToken = $tm[1] ?? '';
$r = fresh()->query("SELECT draft_only, enabled FROM ingest_agents WHERE name = 'cli-draft-only'")->fetch();
ok($r && (int) $r['draft_only'] === 1 && (int) $r['enabled'] === 1, 'the stored row carries draft_only = 1');
[$code, $j] = $file($cliToken, ['desk' => 'local-news', 'title' => 'CLI draft-only token on the wire desk']);
ok($code === 201 && ($j['status'] ?? '') === 'draft' && ($j['reason'] ?? '') !== '',
   "the CLI-minted draft-only token stays a draft on the wire desk (got $code/" . ($j['status'] ?? '?') . ')');
[$out, $rc] = pp_fixture_tool($fx, 'tools/make-agent.php list');
ok($rc === 0 && preg_match('/cli-draft-only\s+active\s+draft-only/', $out) === 1, 'list shows the token as draft-only');

[$out, $rc] = pp_fixture_tool($fx, 'tools/make-agent.php create --name cli-standard --sites prairiedispatch');
ok($rc === 0 && preg_match('/Token:\s+(hermes_[0-9a-f]{56})/', $out, $tm) === 1 && str_contains($out, 'standard'),
   "make-agent without the flag mints a standard token (rc=$rc)");
[$code, $j] = $file($tm[1] ?? '', ['desk' => 'local-news', 'title' => 'CLI standard token on the wire desk']);
ok($code === 201 && ($j['status'] ?? '') === 'published', "the default is unchanged: a standard token publishes on the wire desk (got $code)");

/* ---- 7b. The CLI fails CLOSED on anything it does not recognise ------------- */
// Skipped instead of refused, each of these mints a STANDARD, publish-capable
// token and exits 0 — the failure the review found. Refused, nothing is minted.
function agentCount(string $name): int
{
    $s = fresh()->prepare('SELECT COUNT(*) FROM ingest_agents WHERE name = ?');
    $s->execute([$name]);
    $n = (int) $s->fetchColumn();
    $s->closeCursor();
    return $n;
}
$agentsBefore = (int) fresh()->query('SELECT COUNT(*) FROM ingest_agents')->fetchColumn();
foreach ([
    'misspelt flag (--draft_only)'   => ['cli-typo-a', 'create --name cli-typo-a --sites prairiedispatch --draft_only'],
    'flag given a value'             => ['cli-typo-b', 'create --name cli-typo-b --sites prairiedispatch --draft-only=1'],
    'single-dash flag'               => ['cli-typo-c', 'create --name cli-typo-c --sites prairiedispatch -draft-only'],
    'unknown option'                 => ['cli-typo-d', 'create --name cli-typo-d --sites prairiedispatch --publish'],
    'stray positional word'          => ['cli-typo-e', 'create --name cli-typo-e --sites prairiedispatch draft-only'],
    'flag swallowed as the name'     => ['draft-only', 'create --sites prairiedispatch --name --draft-only'],
    'option given twice'             => ['cli-typo-f', 'create --name cli-typo-f --name cli-typo-g --sites prairiedispatch --draft-only'],
    'misspelt subcommand'            => ['cli-typo-h', 'creat --name cli-typo-h --sites prairiedispatch --draft-only'],
] as $label => [$wouldBe, $args]) {
    [$out, $rc] = pp_fixture_tool($fx, "tools/make-agent.php $args");
    ok($rc !== 0 && !str_contains($out, 'Token:'),
       "make-agent refuses a $label: non-zero exit, no token printed (rc=$rc)");
    ok(agentCount($wouldBe) === 0, "… and creates no row ($label)");
}
// --draft-only where it means nothing is an error, never a silent no-op.
foreach (['list --draft-only', 'revoke --name cli-standard --draft-only', 'enable --name cli-standard --draft-only',
          'list --name cli-standard'] as $args) {
    [$out, $rc] = pp_fixture_tool($fx, "tools/make-agent.php $args");
    ok($rc !== 0, "make-agent refuses `$args` (rc=$rc)");
}
$r = fresh()->query("SELECT enabled, draft_only FROM ingest_agents WHERE name = 'cli-standard'")->fetch();
ok($r && (int) $r['enabled'] === 1 && (int) $r['draft_only'] === 0, 'the refused revoke left the token exactly as it was');
ok((int) fresh()->query('SELECT COUNT(*) FROM ingest_agents')->fetchColumn() === $agentsBefore,
   'no refusal created or removed a token row');
// The documented invocations still work after the refusals.
[$out, $rc] = pp_fixture_tool($fx, 'tools/make-agent.php revoke --name cli-standard');
ok($rc === 0 && str_contains($out, 'revoked'), "a well-formed revoke still works (rc=$rc)");
[$out, $rc] = pp_fixture_tool($fx, 'tools/make-agent.php enable --name=cli-standard');
ok($rc === 0 && str_contains($out, 're-enabled'), "a well-formed enable (the --name=value form) still works (rc=$rc)");

/* ---- 7c. A slug longer than audit_log.target (VARCHAR(160)) ----------------- */
// A slug runs to 180 characters; the audit row's target column holds 160.
// PostgreSQL rejects the overflow — SQLite does not notice — and the row is
// written AFTER the filing has committed, so an unbounded write stored the
// draft and then answered 500, and turned the 403 refusal into a 500. The
// status codes here are what the PostgreSQL job proves; the stored target
// length is asserted on both engines.
function auditRows(string $action, string $targetPrefix): array
{
    $s = fresh()->prepare('SELECT target, detail FROM audit_log WHERE action = ? AND target LIKE ? ORDER BY id');
    $s->execute([$action, $targetPrefix . '%']);
    $rows = $s->fetchAll();
    $s->closeCursor();
    return $rows;
}
$slugA = 'draft-only-long-slug-' . str_repeat('a', 154);   // 21 + 154 = 175 characters
$slugB = 'waiting-long-slug-' . str_repeat('b', 162);      // 18 + 162 = 180 characters
ok(strlen($slugA) === 175 && strlen($slugB) === 180, 'the fixture slugs are 175 and 180 characters');

[$code, $j] = $file($draftToken, ['desk' => 'local-news', 'title' => 'Draft-only filing with a 175-character slug',
                                  'suggested_slug' => $slugA]);
ok($code === 201 && ($j['status'] ?? '') === 'draft' && ($j['slug'] ?? '') === $slugA,
   "a draft-only filing whose slug is 175 characters answers 201 draft, not 500 (got $code)");
$p = row($slugA);
ok($p && $p['status'] === 'draft' && $p['published_at'] === null, 'the long-slug draft is stored, unpublished');
$a = auditRows('ingest', 'draft-only-long-slug-');
ok(count($a) === 1 && strlen((string) $a[0]['target']) === 160 && str_starts_with($slugA, (string) $a[0]['target']),
   'its audit row exists, the target cut to the column width (got ' . (isset($a[0]) ? strlen((string) $a[0]['target']) : 'no row') . ')');
ok(count($a) === 1 && str_contains((string) $a[0]['detail'], 'post #' . (int) ($j['id'] ?? 0)),
   'and the cut row still names the story by its post id');

[$code, $j] = $file($normalToken, ['title' => 'Waiting story with a 180-character slug', 'publish_on_image' => true,
                                   'suggested_slug' => $slugB]);
ok($code === 201 && ($j['awaiting_image'] ?? false) === true && ($j['slug'] ?? '') === $slugB,
   "a normal token's 180-character-slug filing waits for art (got $code)");
[$code, $j] = api($port, 'POST', '/api/ingest-publish', $draftToken, ['story' => $slugB, 'image' => $art]);
ok($code === 403 && ($j['status'] ?? '') === 'draft' && ($j['reason'] ?? '') !== '',
   "the refusal is still a 403 with status draft when the slug is 180 characters (got $code)");
$p = row($slugB);
ok($p && $p['status'] === 'draft' && (int) $p['awaiting_image'] === 1 && $p['image'] === '',
   'the long-slug story is untouched by the refusal');
$a = auditRows('ingest-publish', 'waiting-long-slug-');
ok(count($a) === 1 && str_starts_with((string) $a[0]['detail'], 'REFUSED') && strlen((string) $a[0]['target']) === 160,
   'the long-slug refusal is audited, target cut to 160');
[$code, $j] = api($port, 'POST', '/api/ingest-publish', $normalToken, ['story' => $slugB, 'image' => $art]);
ok($code === 200 && ($j['status'] ?? '') === 'published', "a normal token still publishes the long-slug story (got $code)");
$a = auditRows('ingest-publish', 'waiting-long-slug-');
ok(count($a) === 2 && strlen((string) $a[1]['target']) === 160, 'the long-slug publish is audited too, target cut to 160');

/* ---- 7d. An audit write that FAILS never changes the answer ----------------- */
// Park the table so every audit insert fails outright, on either engine. The
// filing is already stored and the refusal is already decided when the row
// is written, so the answers must stay 201 and 403.
[$code, $j] = $file($normalToken, ['title' => 'Waiting story for the audit-failure check', 'publish_on_image' => true]);
$parkedWaitSlug = (string) ($j['slug'] ?? '');
ok($code === 201 && ($j['awaiting_image'] ?? false) === true, "a second waiting story is filed for this check (got $code)");
$maint = pp_db_connect();
$maint->exec('ALTER TABLE audit_log RENAME TO audit_log_parked');
[$code, $j] = $file($draftToken, ['desk' => 'local-news', 'title' => 'Draft-only filing while the audit table is away']);
$noAuditCode = $code;
$noAuditSlug = (string) ($j['slug'] ?? '');
$noAuditStatus = (string) ($j['status'] ?? '?');
[$code, $j] = api($port, 'POST', '/api/ingest-publish', $draftToken, ['story' => $parkedWaitSlug, 'image' => $art]);
$maint->exec('ALTER TABLE audit_log_parked RENAME TO audit_log');   // restored before any assertion can exit
$maint = null;
ok($noAuditCode === 201 && $noAuditStatus === 'draft',
   "with the audit write failing, a draft-only filing still answers 201 draft (got $noAuditCode/$noAuditStatus)");
ok($code === 403 && ($j['status'] ?? '') === 'draft',
   "with the audit write failing, the refusal still answers 403 with status draft (got $code)");
$p = row($noAuditSlug);
ok($p && $p['status'] === 'draft' && $p['published_at'] === null, 'the filing made while auditing failed is stored as a draft');
$p = row($parkedWaitSlug);
ok($p && $p['status'] === 'draft' && (int) $p['awaiting_image'] === 1 && $p['image'] === '',
   'the story refused while auditing failed is untouched');
ok((int) fresh()->query('SELECT COUNT(*) FROM audit_log')->fetchColumn() > 0, 'the audit table is back in place');

/* ---- 8. Invariants ---------------------------------------------------------- */
$f = fresh();
$pubByDraft = (int) $f->query("SELECT COUNT(*) FROM posts WHERE status = 'published' AND filed_by IN ('draftonly-writer', 'cli-draft-only', 'draftonly-revoked')")->fetchColumn();
ok($pubByDraft === 0, "no row filed by a draft-only token is published (got $pubByDraft)");
$filedByDraft = (int) $f->query("SELECT COUNT(*) FROM posts WHERE filed_by IN ('draftonly-writer', 'cli-draft-only')")->fetchColumn();
// Five from draftonly-writer (wire desk; flag + art; flag, no art; the
// 175-character slug; the filing made while auditing failed) and one from the
// CLI-minted token. The off-scope and revoked attempts filed nothing.
ok($filedByDraft === 6, "the draft-only tokens filed exactly six drafts (got $filedByDraft)");
$pubTotal = (int) $f->query("SELECT COUNT(*) FROM posts WHERE status = 'published' AND (filed_by LIKE 'draftonly-%' OR filed_by LIKE 'cli-%')")->fetchColumn();
ok($pubTotal === 4, "exactly the four normal-token stories are published (got $pubTotal)");
ok(max($codes) < 500, 'no call answered 5xx (max status ' . max($codes) . ')');

if ($fails === 0) {
    echo "draft-only: all checks pass\n";
    exit(0);
}
exit(1);
