<?php
/**
 * The agent ingest surface, over real HTTP on a disposable database:
 * bearer auth failing closed on /api/ingest-media, image upload in both
 * body forms with the byte-sniff refusing non-images, the uploaded path
 * riding into a story filing as its featured image (drafts only), scope
 * enforcement, and the hub's API-keys page minting a key that works and
 * revoking one that then stops.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('ingestapi');
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

/* ---- Fixtures: a hub site row, a desk, one known agent key, one admin --- */
$pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?,?,?)')
    ->execute(['Civis Media', 'civismedia', now()]);
$pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')
    ->execute(['Local News', 'local-news']);

$knownToken = 'hermes_' . bin2hex(random_bytes(28));
$pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, created_at) VALUES (?,?,?,?,1,?)')
    ->execute(['test-uploader', hash('sha256', $knownToken), 'prairiedispatch', '', now()]);
$revokedToken = 'hermes_' . bin2hex(random_bytes(28));
$pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, created_at) VALUES (?,?,?,?,0,?)')
    ->execute(['test-revoked', hash('sha256', $revokedToken), 'prairiedispatch', '', now()]);

$adminEmail = 'admin-keys@test.local';
$pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
    ->execute(['Kay Admin', $adminEmail, password_hash('correct horse pp', PASSWORD_DEFAULT), 'admin', 'kay-admin', now()]);

/* ---- Two dev servers: the paper, and the hub (same database) ------------ */
$port = 8500 + random_int(0, 80);
$hubPort = $port + 1;
$spawn = function (int $p, string $extraEnv) use ($config, $root, $work): int {
    $cmd = $extraEnv . ' PP_CONFIG=' . escapeshellarg($config) . ' ' . escapeshellarg(PHP_BINARY)
         . ' -S 127.0.0.1:' . $p . ' router.php >' . escapeshellarg("$work/server-$p.log") . ' 2>&1 & echo $!';
    return (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && ' . $cmd));
};
$paperPid = $spawn($port, '');
$hubPid = $spawn($hubPort, 'PP_SITE=civismedia');
usleep(800000);
$storedFiles = [];
register_shutdown_function(function () use ($paperPid, $hubPid, $fx, &$storedFiles, $root) {
    foreach ([$paperPid, $hubPid] as $pid) {
        if ($pid) {
            @posix_kill($pid, 15);
        }
    }
    foreach ($storedFiles as $f) {
        @unlink($root . $f);
    }
    pp_fixture_destroy($fx);
});

function api(int $port, string $path, ?string $token, array $opts = []): array
{
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    $headers = $token !== null ? ['Authorization: Bearer ' . $token] : [];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POST => true]);
    if (isset($opts['json'])) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['json']));
    } elseif (isset($opts['raw'])) {
        $headers[] = 'Content-Type: ' . ($opts['ctype'] ?? 'application/octet-stream');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['raw']);
    } elseif (isset($opts['multipart'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['multipart']);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, (array) json_decode($body, true), $body];
}

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

/* ---- /api/ingest-media: auth fails closed -------------------------------- */
[$code] = api($port, '/api/ingest-media', null, ['raw' => $png, 'ctype' => 'image/png']);
ok($code === 401, "no token → 401 (got $code)");
[$code] = api($port, '/api/ingest-media', $revokedToken, ['raw' => $png, 'ctype' => 'image/png']);
ok($code === 401, "revoked token → 401 (got $code)");
[$code] = api($port, '/api/ingest-media', 'hermes_' . str_repeat('ab', 28), ['raw' => $png, 'ctype' => 'image/png']);
ok($code === 401, "unknown token → 401 (got $code)");

/* ---- upload, both body forms --------------------------------------------- */
[$code, $j] = api($port, '/api/ingest-media?name=front%20graphic', $knownToken, ['raw' => $png, 'ctype' => 'image/png']);
ok($code === 201 && ($j['ok'] ?? false) === true, "raw-body PNG uploads (got $code)");
$rawPath = (string) ($j['path'] ?? '');
ok(str_starts_with($rawPath, '/uploads/') && str_ends_with($rawPath, '.png'), 'the returned path is an /uploads png');
ok(str_contains($rawPath, 'front-graphic'), 'the name seed reaches the stored basename');
ok(is_file($root . $rawPath), 'the raw-body file exists where the path says');
$storedFiles[] = $rawPath;

$tmp = $work . '/multi.png';
file_put_contents($tmp, $png);
[$code, $j] = api($port, '/api/ingest-media', $knownToken,
    ['multipart' => ['file' => new CURLFile($tmp, 'image/png', 'hero shot.png'), 'name' => 'hero-shot']]);
ok($code === 201 && ($j['ok'] ?? false) === true, "multipart PNG uploads (got $code)");
$multiPath = (string) ($j['path'] ?? '');
ok(is_file($root . $multiPath), 'the multipart file exists where the path says');
$storedFiles[] = $multiPath;

/* ---- non-images are refused, whatever they claim -------------------------- */
[$code, $j] = api($port, '/api/ingest-media', $knownToken, ['raw' => '<?php echo "nope";', 'ctype' => 'image/png']);
ok($code === 422, "non-image bytes → 422 (got $code)");

/* ---- the story rides the uploaded path ------------------------------------ */
$story = fn (array $over) => api($port, '/api/ingest', $knownToken, ['json' => array_merge([
    'site' => 'prairiedispatch', 'desk' => 'local-news',
    'title' => 'Ingest API test story ' . bin2hex(random_bytes(3)),
    'lede' => 'A lede long enough to pass.',
    'body' => '<p>Body copy for the ingest test.</p>',
], $over)]);

[$code, $j] = $story(['image' => $rawPath, 'image_caption' => 'The caption', 'image_credit' => 'The credit']);
ok($code === 201 && ($j['status'] ?? '') === 'draft', "story with uploaded image files as a DRAFT (got $code/" . ($j['status'] ?? '?') . ')');
$row = $pdo->prepare('SELECT image, image_caption, status FROM posts WHERE id = ?');
$row->execute([(int) ($j['id'] ?? 0)]);
$p = $row->fetch();
ok($p && $p['image'] === $rawPath && $p['image_caption'] === 'The caption' && $p['status'] === 'draft',
   'the draft row carries the uploaded path and caption');

[$code, $j] = $story(['image' => '/uploads/2099/01/does-not-exist.png']);
ok($code === 422, "a nonexistent /uploads path → 422 (got $code)");
[$code, $j] = $story(['image' => '/uploads/2026/09/../../config.php']);
ok($code === 422, "a traversal-shaped path is refused (got $code)");
[$code, $j] = $story(['site' => 'civismedia']);
ok($code === 403, "a site outside the key's scope → 403 (got $code)");

/* ---- the hub API-keys page: mint, use, revoke ----------------------------- */
function web(int $port, string $method, string $url, array $fields, string $jar): array
{
    $ch = curl_init('http://127.0.0.1:' . $port . $url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body];
}

$jar = $work . '/jar-admin';
[, $page] = web($hubPort, 'GET', '/admin/login.php', [], $jar);
preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
$csrf = $m[1] ?? '';
[$code] = web($hubPort, 'POST', '/admin/login.php', ['csrf' => $csrf, 'email' => $adminEmail, 'password' => 'correct horse pp'], $jar);
ok($code === 302, "the admin signs in on the hub (got $code)");

[$code, $page] = web($hubPort, 'GET', '/admin/api-keys.php', [], $jar);
ok($code === 200 && str_contains($page, 'Mint a key'), "the hub shows the API-keys page to an admin (got $code)");
preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
$csrf = $m[1] ?? $csrf;

[$code, $page] = web($hubPort, 'POST', '/admin/api-keys.php',
    ['csrf' => $csrf, 'action' => 'create', 'name' => 'ui-minted', 'sites' => ['prairiedispatch'], 'desks' => ''], $jar);
ok($code === 200 && preg_match('/hermes_[0-9a-f]{56}/', $page, $tm) === 1,
   "minting shows the raw key exactly once (got $code)");
$minted = $tm[0] ?? '';
// A fresh connection for reads after the servers write: a long-lived
// sqlite handle can serve a stale snapshot of rows another process
// committed after it opened.
$freshDb = function () use ($config, $pdo): PDO {
    $cfg = require $config;
    if (($cfg['db']['driver'] ?? '') !== 'sqlite') {
        return $pdo;   // read-committed engines see other processes' commits
    }
    $pdo2 = new PDO('sqlite:' . $cfg['db']['sqlite_path']);
    $pdo2->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo2;
};
$sel = $freshDb()->prepare('SELECT * FROM ingest_agents WHERE name = ?');
$sel->execute(['ui-minted']);
$mintedRow = $sel->fetch();
ok($mintedRow && $mintedRow['token_hash'] === hash('sha256', $minted) && $mintedRow['sites'] === 'prairiedispatch',
   'the stored row is the hash of the shown key, scoped as picked');

[$code, $j] = api($port, '/api/ingest-media', $minted, ['raw' => $png, 'ctype' => 'image/png']);
ok($code === 201, "the UI-minted key uploads on the paper (got $code)");
if (isset($j['path'])) {
    $storedFiles[] = (string) $j['path'];
}

[$code, $page] = web($hubPort, 'GET', '/admin/api-keys.php', [], $jar);
preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
$csrf = $m[1] ?? $csrf;
[$code] = web($hubPort, 'POST', '/admin/api-keys.php',
    ['csrf' => $csrf, 'action' => 'revoke', 'id' => (int) $mintedRow['id']], $jar);
ok($code === 302, "revoking answers with a redirect (got $code)");
[$code] = api($port, '/api/ingest-media', $minted, ['raw' => $png, 'ctype' => 'image/png']);
ok($code === 401, "the revoked UI key answers 401 on its next request (got $code)");

/* ---- the page does not exist off the hub, or below admin ------------------ */
$jarPaper = $work . '/jar-admin-paper';
[, $page] = web($port, 'GET', '/admin/login.php', [], $jarPaper);
preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
[$code] = web($port, 'POST', '/admin/login.php', ['csrf' => $m[1] ?? '', 'email' => $adminEmail, 'password' => 'correct horse pp'], $jarPaper);
[$code, $page] = web($port, 'GET', '/admin/api-keys.php', [], $jarPaper);
ok($code === 404, "a paper newsroom has no API-keys page (got $code)");
[$code] = web($hubPort, 'GET', '/admin/api-keys.php', [], $work . '/jar-nobody');
ok($code !== 200, "signed out, the hub page refuses (got $code)");

if ($fails === 0) {
    echo "ingest-api: all checks pass\n";
    exit(0);
}
exit(1);
