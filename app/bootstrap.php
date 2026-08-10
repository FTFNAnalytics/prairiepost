<?php
/**
 * The Prairie Dispatch — application bootstrap.
 * Every entry point (public page, admin page, cron) requires this file first.
 */

define('PP_ROOT', dirname(__DIR__));
define('PP_SCHEMA_VERSION', 7);

$configFile = PP_ROOT . '/config.php';
$GLOBALS['pp_config'] = is_file($configFile)
    ? require $configFile
    : require PP_ROOT . '/config.example.php';

date_default_timezone_set($GLOBALS['pp_config']['timezone'] ?? 'America/Edmonton');

if (!empty($GLOBALS['pp_config']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('ppsession');
    session_start();
}

require PP_ROOT . '/app/helpers.php';
require PP_ROOT . '/app/db.php';
require PP_ROOT . '/app/models.php';

function pp_config(string $key, $default = null)
{
    return $GLOBALS['pp_config'][$key] ?? $default;
}

/** Lazily connected PDO handle; installs or migrates the schema on first use. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $cfg = $GLOBALS['pp_config']['db'];
    $driver = $cfg['driver'] ?? 'sqlite';

    if ($driver === 'mysql') {
        $m = $cfg['mysql'];
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $m['host'], $m['name'], $m['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, $m['user'], $m['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } elseif ($driver === 'pgsql') {
        $p = $cfg['pgsql'];
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $p['host'],
            (int) ($p['port'] ?? 5432),
            $p['name'] ?? 'postgres',
            $p['sslmode'] ?? 'require'
        );
        $pdo = new PDO($dsn, $p['user'], $p['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Supabase's poolers (PgBouncer) don't hold server-side prepared
            // statements across transactions; emulation keeps every query safe
            // on both the session (5432) and transaction (6543) pooler.
            PDO::ATTR_EMULATE_PREPARES => true,
        ]);
        // The app lives in its own Postgres schema so it can share a database
        // with other applications without table-name collisions. Created on
        // first connect; every unqualified table name resolves here.
        $schema = pp_pg_schema();
        $pdo->exec('CREATE SCHEMA IF NOT EXISTS "' . $schema . '"');
        $pdo->exec('SET search_path TO "' . $schema . '"');
    } else {
        $driver = 'sqlite';
        $path = $cfg['sqlite_path'] ?? PP_ROOT . '/data/prairiedispatch.sqlite';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
    }

    if (!pp_schema_installed($pdo, $driver)) {
        require_once PP_ROOT . '/app/seed.php';
        // First boot is all-or-nothing where the engine allows it (Postgres
        // and SQLite run DDL transactionally; MySQL auto-commits DDL). A
        // crash mid-install/seed leaves nothing behind to clean up.
        $atomic = $driver !== 'mysql';
        if ($atomic) {
            $pdo->beginTransaction();
        }
        try {
            pp_install($pdo, $driver);
            pp_seed($pdo);
            if ($atomic) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($atomic && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    } else {
        pp_migrate($pdo, $driver);
    }

    return $pdo;
}

/**
 * The Postgres schema (namespace) this app owns inside a shared database.
 * Validated to a bare identifier so it can be safely quoted into DDL.
 */
function pp_pg_schema(): string
{
    $schema = (string) ($GLOBALS['pp_config']['db']['pgsql']['schema'] ?? 'prairiedispatch');
    if (!preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $schema)) {
        exit("Config error: db.pgsql.schema must be a plain lowercase identifier (letters, digits, underscores).\n");
    }
    return $schema;
}

/** Insert id of the last row, across drivers. */
function pp_last_id(string $table): int
{
    $pdo = db();
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
        return (int) $pdo->lastInsertId($table . '_id_seq');
    }
    return (int) $pdo->lastInsertId();
}

/** Case-insensitive LIKE operator for the active driver. */
function pp_like(): string
{
    return db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? 'ILIKE' : 'LIKE';
}

/**
 * The site this deployment serves, resolved by config 'site_slug'.
 * Joining an existing shared database creates the site row (and its default
 * settings) on first request — no manual setup step.
 */
function current_site(): array
{
    static $site = null;
    if ($site !== null) {
        return $site;
    }
    // PP_SITE overrides the config for CLI runs (cron on a multi-site host,
    // where HTTP_HOST-based config mapping has no host to look at).
    $slug = slugify((string) (getenv('PP_SITE') ?: pp_config('site_slug', 'prairiedispatch')));
    $stmt = db()->prepare('SELECT * FROM sites WHERE slug = ?');
    $stmt->execute([$slug]);
    $site = $stmt->fetch();
    if (!$site) {
        require_once PP_ROOT . '/app/seed.php';
        $site = pp_create_site(db(), $slug);
    }
    return $site;
}

function current_site_id(): int
{
    return (int) current_site()['id'];
}

/** Read a per-site runtime setting (cached per request). */
function setting(string $key, string $default = ''): string
{
    if (!isset($GLOBALS['pp_setting_cache'])) {
        $cache = [];
        $stmt = db()->prepare('SELECT skey, svalue FROM settings WHERE site_id = ?');
        $stmt->execute([current_site_id()]);
        foreach ($stmt as $row) {
            $cache[$row['skey']] = $row['svalue'];
        }
        $GLOBALS['pp_setting_cache'] = $cache;
    }
    return $GLOBALS['pp_setting_cache'][$key] ?? $default;
}

function setting_json(string $key, array $default = []): array
{
    $raw = setting($key);
    if ($raw === '') {
        return $default;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $default;
}

function set_setting(string $key, string $value, ?int $siteId = null): void
{
    $siteId = $siteId ?? current_site_id();
    $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'mysql'
        ? 'INSERT INTO settings (site_id, skey, svalue) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
        : 'INSERT INTO settings (site_id, skey, svalue) VALUES (?, ?, ?) ON CONFLICT(site_id, skey) DO UPDATE SET svalue = excluded.svalue';
    db()->prepare($sql)->execute([$siteId, $key, $value]);
    if ($siteId === current_site_id() && isset($GLOBALS['pp_setting_cache'])) {
        $GLOBALS['pp_setting_cache'][$key] = $value;
    }
}

/** Canonical absolute base URL, no trailing slash. */
function site_url(): string
{
    $configured = trim((string) pp_config('site_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https://' : 'http://') . $host;
}
