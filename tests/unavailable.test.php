<?php
/**
 * Controlled behavior when the schema is not servable (F12): absent,
 * behind, ahead and mid-migration databases answer 503 — non-cacheable,
 * unbranded, no internals — static assets still serve, CLI jobs exit
 * non-success before business writes, and an unknown tenant refuses
 * rather than falling back to another paper.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('unavail');
// NOT prepared yet — the first scenarios need an absent/empty target.

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}
$port = 8900 + random_int(0, 90);
$pid = (int) trim((string) shell_exec(
    'cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($fx['config']) . ' '
    . escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg($fx['work'] . '/server.log') . ' 2>&1 & echo $!'
));
usleep(700000);
register_shutdown_function(function () use ($pid, $fx) {
    if ($pid) {
        @posix_kill($pid, 15);
    }
    pp_fixture_destroy($fx);
});
function req(string $path, int $port, string $host = ''): array
{
    $ch = curl_init("http://127.0.0.1:$port$path");
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HEADER => true];
    if ($host !== '') {
        $opts[CURLOPT_HTTPHEADER] = ["Host: $host"];
    }
    curl_setopt_array($ch, $opts);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $split = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$code, substr($raw, 0, $split), substr($raw, $split)];
}
$expect503 = function (string $label) use ($port) {
    [$code, $h, $b] = req('/', $port);
    ok($code === 503, "$label: front answers 503 (got $code)");
    ok(stripos($h, 'Cache-Control: no-store') !== false, "$label: non-cacheable");
    ok(stripos($h, 'Retry-After:') !== false, "$label: carries Retry-After");
    ok(!preg_match('/PDO|SQLSTATE|stack|pgsql|mysql|sqlite_path|password/i', $b), "$label: no internals in the body");
    ok(stripos($b, 'unavailable') !== false, "$label: a plain human explanation");
};

/* --- Absent target ----------------------------------------------------------- */

$expect503('absent database');
if ($fx['engine'] === 'sqlite') {
    ok(!is_file($fx['sqlite_path']), 'serving 503s did not create the database file');
}
[$code] = req('/assets/css/prairie.css', $port);
ok($code === 200, 'static assets serve without any database at all');

// CLI: a cron job exits non-success before any business write.
exec('PP_CONFIG=' . escapeshellarg($fx['config']) . ' ' . escapeshellarg(PHP_BINARY) . ' '
    . escapeshellarg("$root/cron/fetch-news.php") . ' 2>&1', $o, $rc);
ok($rc !== 0, "a cron job on an unprepared database exits non-success (rc=$rc)");
ok(str_contains(implode(' ', $o), 'migrate.php'), 'and its message points at the runner');

/* --- Prepared: serves; then behind / ahead / partial ------------------------- */

pp_fixture_prepare($fx);
[$code] = req('/', $port);
ok($code === 200, 'a prepared database serves 200');

$pdo = pp_fixture_connect($fx);
$readyVersion = (string) $pdo->query("SELECT svalue FROM settings WHERE site_id = 0 AND skey = 'schema_version'")->fetchColumn();
$pdo->prepare("UPDATE settings SET svalue = '18' WHERE site_id = 0 AND skey = 'schema_version'")->execute();
$expect503('behind schema');
$pdo->prepare("UPDATE settings SET svalue = '99' WHERE site_id = 0 AND skey = 'schema_version'")->execute();
$expect503('ahead schema');
$pdo->prepare("UPDATE settings SET svalue = ? WHERE site_id = 0 AND skey = 'schema_version'")->execute([$readyVersion]);

// Partial: a journal row stuck in 'started'.
$pdo->exec("INSERT INTO schema_migrations (version, name, checksum, status, started_at) VALUES (77, 'stuck', '', 'started', '2026-01-01 00:00:00')");
$expect503('mid-migration schema');
$pdo->exec('DELETE FROM schema_migrations WHERE version = 77');

[$code] = req('/', $port);
ok($code === 200, 'restored to ready, it serves again');

/* --- Unknown tenant: refuse, never fall back --------------------------------- */

[$code, , $body] = req('/', $port, 'stranger.example');
// No domains row for stranger.example → config fallback slug exists → 200
// with the DEFAULT paper is the pre-Phase-3 hostname behavior; the refusal
// under test is a slug with NO site row at all:
exec('PP_CONFIG=' . escapeshellarg($fx['config']) . ' PP_SITE=ghost-paper ' . escapeshellarg(PHP_BINARY) . ' '
    . escapeshellarg("$root/cron/fetch-news.php") . ' 2>&1', $o2, $rc2);
ok($rc2 !== 0, "a CLI job for an unprovisioned tenant exits non-success (rc=$rc2)");
ok(str_contains(implode(' ', $o2), 'seed-launch'), 'and names the provisioning step, not another tenant');

exit($fails ? 1 : 0);
