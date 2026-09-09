<?php
/**
 * Runtime vs maintenance privileges on real PostgreSQL: a non-owner,
 * non-superuser runtime role with schema USAGE + table DML + sequence
 * access serves the full application, cannot run DDL, cannot mutate the
 * migration journal, and cannot run the migration runner; the runner
 * under an unprivileged role fails cleanly. PostgreSQL-only by nature —
 * on other engines this suite reports an explicit SKIP.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
if (pp_fixture_engine() !== 'pgsql') {
    echo "SKIP: privileges are a PostgreSQL concern — run with PP_TEST_DB=pgsql (the per-engine gate does)\n";
    exit(0);
}

$fx = pp_fixture_create('privs');
pp_fixture_prepare($fx);   // owner role installs and seeds

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

$p = $fx['pg'];
$schema = $fx['schema'];
$admin = new PDO("pgsql:host={$p['host']};port={$p['port']};dbname={$p['name']}", $p['user'], $p['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo 'engine: pgsql server ', (string) $admin->getAttribute(PDO::ATTR_SERVER_VERSION), "\n";

// The runtime role: exactly what serving traffic needs, nothing that
// changes the schema. (This is the documented production grant set.)
$role = 'pp_rt_' . bin2hex(random_bytes(3));
$admin->exec("CREATE ROLE \"$role\" LOGIN PASSWORD 'runtime-test-only'");
$admin->exec("GRANT CONNECT ON DATABASE \"{$p['name']}\" TO \"$role\"");
$admin->exec("GRANT USAGE ON SCHEMA \"$schema\" TO \"$role\"");
$admin->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA \"$schema\" TO \"$role\"");
$admin->exec("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA \"$schema\" TO \"$role\"");
// The journal is read-only for runtime: revoke its DML again.
$admin->exec("REVOKE INSERT, UPDATE, DELETE ON \"$schema\".schema_migrations FROM \"$role\"");
register_shutdown_function(function () use ($admin, $role, $fx) {
    try {
        $admin->exec("DROP OWNED BY \"$role\"");
        $admin->exec("DROP ROLE \"$role\"");
    } catch (Throwable) {
    }
    pp_fixture_destroy($fx);
});

// A config that connects as the runtime role.
$rtConfig = $fx['work'] . '/runtime-config.php';
file_put_contents($rtConfig, "<?php\nreturn ['db' => ['driver' => 'pgsql', 'pgsql' => [\n"
    . " 'host' => '{$p['host']}', 'port' => {$p['port']}, 'name' => '{$p['name']}',\n"
    . " 'user' => '$role', 'pass' => 'runtime-test-only', 'sslmode' => 'disable', 'schema' => '$schema']],\n"
    . " 'site_slug' => 'prairiedispatch', 'hub_slug' => 'civismedia', 'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];\n");

/* --- The runtime role serves the application --------------------------------- */

$port = 9000 + random_int(0, 90);
$pid = (int) trim((string) shell_exec(
    'cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($rtConfig) . ' '
    . escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg($fx['work'] . '/rt.log') . ' 2>&1 & echo $!'
));
usleep(700000);
register_shutdown_function(function () use ($pid) {
    if ($pid) {
        @posix_kill($pid, 15);
    }
});
$get = function (string $path) use ($port): array {
    $ch = curl_init("http://127.0.0.1:$port$path");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $b = (string) curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$c, $b];
};
[$code, $body] = $get('/');
ok($code === 200 && str_contains($body, 'Prairie Dispatch'), "runtime role renders the front page (got $code)");
[$code] = $get('/feed/');
ok($code === 200, 'and the feed');
[$code] = $get('/search?q=council');
ok($code === 200, 'and search');
// A write path: subscribe posts into the database as the runtime role.
$ch = curl_init("http://127.0.0.1:$port/subscribe");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['email' => 'runtime-role@test.local', 'website' => '']), CURLOPT_TIMEOUT => 15]);
curl_exec($ch);
curl_close($ch);
$n = (int) $admin->query("SELECT COUNT(*) FROM \"$schema\".subscribers WHERE email = 'runtime-role@test.local'")->fetchColumn();
ok($n === 1, 'runtime role performs ordinary DML (a subscription landed)');

/* --- …and can change nothing structural -------------------------------------- */

$rt = new PDO("pgsql:host={$p['host']};port={$p['port']};dbname={$p['name']}", $role, 'runtime-test-only',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$rt->exec("SET search_path TO \"$schema\"");
$denied = fn (string $sql): bool => (function () use ($rt, $sql) {
    try {
        $rt->exec($sql);
        return false;
    } catch (PDOException) {
        return true;
    }
})();
ok($denied('CREATE TABLE priv_probe (id INT)'), 'runtime role cannot CREATE TABLE');
ok($denied('ALTER TABLE posts ADD COLUMN priv_probe INT'), 'runtime role cannot ALTER TABLE');
ok($denied('DROP TABLE posts'), 'runtime role cannot DROP TABLE');
ok($denied("CREATE SCHEMA priv_probe_schema"), 'runtime role cannot create schemas');
ok($denied("INSERT INTO schema_migrations (version, name, checksum, status, started_at) VALUES (98, 'x', '', 'applied', now())"),
    'runtime role cannot write the migration journal');
$v = (int) $rt->query("SELECT COUNT(*) FROM schema_migrations")->fetchColumn();
ok($v >= 1, 'but can READ the journal (the readiness gate needs it)');

/* --- The migration runner under insufficient privileges ----------------------- */

exec('PP_CONFIG=' . escapeshellarg($rtConfig) . ' ' . escapeshellarg(PHP_BINARY) . ' '
    . escapeshellarg("$root/tools/migrate.php") . ' --status 2>&1', $o1, $rc1);
ok($rc1 === 0, 'the runner\'s read-only status works under the runtime role');
// Force pending work, then try to apply as the runtime role.
$admin->exec("UPDATE \"$schema\".settings SET svalue = '18' WHERE site_id = 0 AND skey = 'schema_version'");
$admin->exec("DELETE FROM \"$schema\".schema_migrations");
$admin->exec("DROP TABLE \"$schema\".media_orders");
exec('PP_CONFIG=' . escapeshellarg($rtConfig) . ' ' . escapeshellarg(PHP_BINARY) . ' '
    . escapeshellarg("$root/tools/migrate.php") . ' --apply 2>&1', $o2, $rc2);
$out2 = implode("\n", $o2);
ok($rc2 !== 0, "apply under the runtime role fails (rc=$rc2)");
ok(!preg_match('/runtime-test-only/', $out2), 'and never echoes credentials');
$still = (int) $admin->query("SELECT COUNT(*) FROM pg_tables WHERE schemaname = '$schema' AND tablename = 'media_orders'")->fetchColumn();
ok($still === 0, 'and wrote nothing structural');

exit($fails ? 1 : 0);
