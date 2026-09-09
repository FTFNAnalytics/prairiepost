<?php
/**
 * First-administrator provisioning: a public visitor can no longer claim an
 * unprovisioned installation; tools/setup-admin.php is the only door, it is
 * atomic under simultaneous runs, refuses once anyone exists, and the
 * account it creates signs in normally. Disposable database throughout.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('setup');
pp_fixture_prepare($fx);            // migrate --apply + seed-core, explicitly
$work = $fx['work'];
$config = $fx['config'];
$dbfile = $fx['sqlite_path'] ?? '';

putenv('PP_CONFIG=' . $config);
require $root . '/app/bootstrap.php';
$pdo = db();

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}
$userCount = fn (): int => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

/* --- The public form no longer founds the paper ----------------------------- */

$port = 8800 + random_int(0, 90);
$pid = (int) trim((string) shell_exec(
    'cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($config) . ' '
    . escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("$work/server.log") . ' 2>&1 & echo $!'
));
usleep(700000);
register_shutdown_function(function () use ($pid, $fx) {
    if ($pid) {
        @posix_kill($pid, 15);
    }
    pp_fixture_destroy($fx);
});

$jar = "$work/jar";
$get = function (string $path) use ($port, $jar): array {
    $ch = curl_init("http://127.0.0.1:$port$path");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body];
};
$post = function (string $path, array $fields) use ($port, $jar): array {
    $ch = curl_init("http://127.0.0.1:$port$path");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body];
};

ok($userCount() === 0, 'fresh install has zero accounts');
[$code, $body] = $get('/admin/login.php');
ok($code === 200 && str_contains($body, 'setup-admin'), 'login page points at the server-side tool');
ok(!str_contains($body, 'name="name"'), 'no founding-account form is offered');
preg_match('/name="csrf" value="([0-9a-f]+)"/', $body, $m);
[$code2, $body2] = $post('/admin/login.php', [
    'csrf' => $m[1] ?? '', 'name' => 'Eve Visitor', 'email' => 'eve@evil.test', 'password' => 'take the whole paper',
]);
ok($userCount() === 0, 'a visitor POSTing the old founding fields creates NOTHING');
ok($code2 !== 302, 'and is not signed in');

/* --- The CLI tool ------------------------------------------------------------ */

$env = 'PP_CONFIG=' . escapeshellarg($config) . ' ';
$tool = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/tools/setup-admin.php");

exec("printf '%s' 'short' | $env$tool 'Jo Founder' jo@paper.test 2>&1", $o1, $e1);
ok($e1 !== 0 && $userCount() === 0, 'a too-short passphrase is refused');

exec("printf '%s' 'a fine long passphrase' | $env$tool 'Jo Founder' jo@paper.test 2>&1", $o2, $e2);
ok($e2 === 0 && $userCount() === 1, 'the CLI founds exactly one account');
$u = $pdo->query('SELECT role, email FROM users')->fetch();
ok($u['role'] === 'admin' && $u['email'] === 'jo@paper.test', 'the account is the administrator asked for');

exec("printf '%s' 'another passphrase here' | $env$tool 'Second Try' two@paper.test 2>&1", $o3, $e3);
ok($e3 !== 0 && $userCount() === 1, 'a second run refuses — the installation is provisioned');
ok(str_contains(implode(' ', $o3), 'reset-password'), 'and points at the existing recovery path');

// The founded account signs in through the normal form.
[$code, $body] = $get('/admin/login.php');
preg_match('/name="csrf" value="([0-9a-f]+)"/', $body, $m);
[$code] = $post('/admin/login.php', ['csrf' => $m[1] ?? '', 'email' => 'jo@paper.test', 'password' => 'a fine long passphrase']);
ok($code === 302, 'the founded administrator signs in normally');

// Existing recovery tooling still runs (list mode, read-only).
exec($env . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/tools/reset-password.php") . ' list 2>&1', $o4, $e4);
ok($e4 === 0, 'tools/reset-password.php still works for existing accounts');

/* --- Simultaneous provisioning: exactly one founder --------------------------- */

// A second fixture on the SAME engine, prepared but unseeded, so both
// racers contend only on the users table (on Postgres this exercises the
// LOCK TABLE strategy across two genuinely independent processes).
$fx2 = pp_fixture_create('setuprace');
pp_fixture_prepare($fx2, false);
register_shutdown_function(fn () => pp_fixture_destroy($fx2));
$cfg2 = $fx2['config'];

$procs = [];
foreach ([['Racer One', 'one@race.test'], ['Racer Two', 'two@race.test']] as $i => [$n, $eaddr]) {
    $procs[$i] = proc_open(
        "printf '%s' 'racing passphrase $i' | PP_CONFIG=" . escapeshellarg($cfg2) . ' '
        . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/tools/setup-admin.php") . ' '
        . escapeshellarg($n) . ' ' . escapeshellarg($eaddr) . ' 2>&1',
        [1 => ['pipe', 'w']], $pipes[$i]
    );
}
$codes = [];
foreach ($procs as $i => $p) {
    stream_get_contents($pipes[$i][1]);
    $codes[] = proc_close($p);
}
$pdo2 = pp_fixture_connect($fx2);
$n = (int) $pdo2->query('SELECT COUNT(*) FROM users')->fetchColumn();
ok($n === 1, "simultaneous provisioning founds exactly one account (got $n)");
sort($codes);
ok($codes[0] === 0 && $codes[1] !== 0, 'one racer succeeds, the other is told no');

exit($fails ? 1 : 0);
