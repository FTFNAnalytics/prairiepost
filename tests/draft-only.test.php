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
 * mints a token that behaves this way; one minted without it publishes.
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

/* ---- 8. Invariants ---------------------------------------------------------- */
$f = fresh();
$pubByDraft = (int) $f->query("SELECT COUNT(*) FROM posts WHERE status = 'published' AND filed_by IN ('draftonly-writer', 'cli-draft-only', 'draftonly-revoked')")->fetchColumn();
ok($pubByDraft === 0, "no row filed by a draft-only token is published (got $pubByDraft)");
$filedByDraft = (int) $f->query("SELECT COUNT(*) FROM posts WHERE filed_by IN ('draftonly-writer', 'cli-draft-only')")->fetchColumn();
ok($filedByDraft === 5, "the draft-only tokens filed exactly five drafts (got $filedByDraft)");
$pubTotal = (int) $f->query("SELECT COUNT(*) FROM posts WHERE status = 'published' AND (filed_by LIKE 'draftonly-%' OR filed_by LIKE 'cli-%')")->fetchColumn();
ok($pubTotal === 3, "exactly the three normal-token stories are published (got $pubTotal)");
ok(max($codes) < 500, 'no call answered 5xx (max status ' . max($codes) . ')');

if ($fails === 0) {
    echo "draft-only: all checks pass\n";
    exit(0);
}
exit(1);
