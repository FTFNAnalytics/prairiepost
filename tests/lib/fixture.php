<?php
/**
 * Engine-aware disposable database fixtures for the test suites.
 *
 * Every DB-backed test builds its database through here, so the engine is
 * an explicit, ASSERTED choice — never an accident of a hardcoded config:
 *
 *   PP_TEST_DB=sqlite   (default) a throwaway file database
 *   PP_TEST_DB=pgsql    a uniquely named schema in the server named by
 *                       PP_TEST_PG_HOST/PORT/DB/USER/PASS (or PP_TEST_PG_DSN-style socket dir via HOST=/path)
 *   PP_TEST_DB=mysql    a uniquely named database on the server named by
 *                       PP_TEST_MY_SOCKET (or HOST/PORT) + USER/PASS
 *
 * Each fixture owns a unique identity (schema/database/file name carrying
 * random bytes) and its destroy() removes ONLY that identity. Preparation
 * is the real production path: tools/migrate.php --apply, then optional
 * tools/seed-core.php — no test relies on request-time installation,
 * because request-time installation no longer exists.
 */

function pp_fixture_engine(): string
{
    $e = getenv('PP_TEST_DB') ?: 'sqlite';
    if (!in_array($e, ['sqlite', 'pgsql', 'mysql'], true)) {
        fwrite(STDERR, "PP_TEST_DB must be sqlite, pgsql or mysql (got '$e')\n");
        exit(2);
    }
    return $e;
}

/** Create the empty target + its config file. Returns the fixture handle. */
function pp_fixture_create(string $label): array
{
    $engine = pp_fixture_engine();
    $id = $label . '-' . bin2hex(random_bytes(4));
    $work = sys_get_temp_dir() . '/pp-fx-' . $id;
    mkdir($work, 0700);
    $fx = ['engine' => $engine, 'id' => $id, 'work' => $work, 'config' => $work . '/config.php'];

    if ($engine === 'sqlite') {
        $fx['sqlite_path'] = $work . '/db.sqlite';
        $db = "['driver' => 'sqlite', 'sqlite_path' => '{$fx['sqlite_path']}']";
    } elseif ($engine === 'pgsql') {
        $host = getenv('PP_TEST_PG_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('PP_TEST_PG_PORT') ?: 5432);
        $name = getenv('PP_TEST_PG_DB') ?: 'postgres';
        $user = getenv('PP_TEST_PG_USER') ?: 'postgres';
        $pass = getenv('PP_TEST_PG_PASS') ?: '';
        $schema = 'pp_test_' . str_replace('-', '_', strtolower($id));
        $schema = preg_replace('/[^a-z0-9_]/', '_', $schema);
        $fx += ['pg' => compact('host', 'port', 'name', 'user', 'pass'), 'schema' => $schema];
        $db = var_export(['driver' => 'pgsql', 'pgsql' => [
            'host' => $host, 'port' => $port, 'name' => $name,
            'user' => $user, 'pass' => $pass, 'sslmode' => getenv('PP_TEST_PG_SSL') ?: 'disable',
            'schema' => $schema,
        ]], true);
    } else {
        $dbname = 'pp_test_' . str_replace('-', '_', strtolower($id));
        $dbname = preg_replace('/[^a-z0-9_]/', '_', $dbname);
        $my = [
            'socket' => getenv('PP_TEST_MY_SOCKET') ?: '',
            'host'   => getenv('PP_TEST_MY_HOST') ?: '127.0.0.1',
            'port'   => (int) (getenv('PP_TEST_MY_PORT') ?: 3306),
            'user'   => getenv('PP_TEST_MY_USER') ?: 'root',
            'pass'   => getenv('PP_TEST_MY_PASS') ?: '',
        ];
        $fx += ['my' => $my, 'dbname' => $dbname];
        // The database itself must exist before the app can connect.
        pp_fixture_admin_mysql($my)->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $db = var_export(['driver' => 'mysql', 'mysql' => [
            'socket' => $my['socket'], 'host' => $my['host'], 'port' => $my['port'],
            'name' => $dbname, 'user' => $my['user'], 'pass' => $my['pass'], 'charset' => 'utf8mb4',
        ]], true);
    }

    file_put_contents($fx['config'], "<?php\nreturn ['db' => $db,\n"
        . " 'site_slug' => 'prairiedispatch', 'hub_slug' => 'civismedia',\n"
        . " 'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];\n");
    return $fx;
}

function pp_fixture_admin_mysql(array $my): PDO
{
    $dsn = $my['socket'] !== ''
        ? "mysql:unix_socket={$my['socket']}"
        : "mysql:host={$my['host']};port={$my['port']}";
    return new PDO($dsn, $my['user'], $my['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

/** Run a repo tool (with optional arguments) against the fixture; returns [output, exit]. */
function pp_fixture_tool(array $fx, string $tool, string $extraEnv = ''): array
{
    $root = dirname(__DIR__, 2);
    $parts = explode(' ', $tool);
    $script = array_shift($parts);
    $args = implode(' ', array_map('escapeshellarg', $parts));
    exec('PP_CONFIG=' . escapeshellarg($fx['config']) . ' ' . $extraEnv . ' '
        . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/$script") . ($args !== '' ? " $args" : '') . ' 2>&1', $lines, $exit);
    return [implode("\n", $lines), $exit];
}

/** migrate --apply (+ optional base seed). Dies loudly on failure. */
function pp_fixture_prepare(array $fx, bool $seedCore = true): void
{
    [$out, $exit] = pp_fixture_tool($fx, 'tools/migrate.php --apply');
    if ($exit !== 0) {
        fwrite(STDERR, "fixture prepare failed (migrate):\n$out\n");
        exit(1);
    }
    if ($seedCore) {
        [$out, $exit] = pp_fixture_tool($fx, 'tools/seed-core.php');
        if ($exit !== 0) {
            fwrite(STDERR, "fixture prepare failed (seed-core):\n$out\n");
            exit(1);
        }
    }
}

/**
 * Connect directly to the fixture and ASSERT what it really is — the
 * driver and the server version go to stdout so a suite's evidence names
 * the engine it actually exercised, and a mislabeled run cannot pass.
 */
function pp_fixture_connect(array $fx): PDO
{
    if ($fx['engine'] === 'sqlite') {
        $pdo = new PDO('sqlite:' . $fx['sqlite_path'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    } elseif ($fx['engine'] === 'pgsql') {
        $p = $fx['pg'];
        $pdo = new PDO("pgsql:host={$p['host']};port={$p['port']};dbname={$p['name']}", $p['user'], $p['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('SET search_path TO "' . $fx['schema'] . '"');
    } else {
        $my = $fx['my'];
        $dsn = $my['socket'] !== ''
            ? "mysql:unix_socket={$my['socket']};dbname={$fx['dbname']}"
            : "mysql:host={$my['host']};port={$my['port']};dbname={$fx['dbname']}";
        $pdo = new PDO($dsn, $my['user'], $my['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::MYSQL_ATTR_FOUND_ROWS => true]);
    }
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver !== $fx['engine']) {
        fwrite(STDERR, "ENGINE MISMATCH: fixture says {$fx['engine']}, PDO says $driver\n");
        exit(1);
    }
    $version = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    echo "engine: $driver server " . ($version ?: 'n/a') . " ({$fx['id']})\n";
    return $pdo;
}

/** Remove exactly this fixture's resources — nothing else. */
function pp_fixture_destroy(array $fx): void
{
    try {
        if ($fx['engine'] === 'pgsql') {
            $p = $fx['pg'];
            $pdo = new PDO("pgsql:host={$p['host']};port={$p['port']};dbname={$p['name']}", $p['user'], $p['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('DROP SCHEMA IF EXISTS "' . $fx['schema'] . '" CASCADE');
        } elseif ($fx['engine'] === 'mysql') {
            pp_fixture_admin_mysql($fx['my'])->exec("DROP DATABASE IF EXISTS `{$fx['dbname']}`");
        }
    } catch (Throwable) {
        // best effort — a dropped connection must not fail the suite teardown
    }
    foreach (glob($fx['work'] . '/*') ?: [] as $f) {
        is_file($f) && unlink($f);
    }
    @rmdir($fx['work']);
}
