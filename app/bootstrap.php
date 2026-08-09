<?php
/**
 * The Prairie Post — application bootstrap.
 * Every entry point (public page, admin page, cron) requires this file first.
 */

define('PP_ROOT', dirname(__DIR__));

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

/** Lazily connected PDO handle; installs the schema on first run. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $cfg = $GLOBALS['pp_config']['db'];
    if (($cfg['driver'] ?? 'sqlite') === 'mysql') {
        $m = $cfg['mysql'];
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $m['host'], $m['name'], $m['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, $m['user'], $m['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $driver = 'mysql';
    } else {
        $path = $cfg['sqlite_path'] ?? PP_ROOT . '/data/prairiepost.sqlite';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $driver = 'sqlite';
    }

    if (!pp_schema_installed($pdo, $driver)) {
        require_once PP_ROOT . '/app/seed.php';
        pp_install($pdo, $driver);
        pp_seed($pdo);
    }

    return $pdo;
}

/** Read a runtime setting (cached per request). */
function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT skey, svalue FROM settings') as $row) {
            $cache[$row['skey']] = $row['svalue'];
        }
    }
    return $cache[$key] ?? $default;
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

function set_setting(string $key, string $value): void
{
    $sql = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? 'INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
        : 'INSERT INTO settings (skey, svalue) VALUES (?, ?) ON CONFLICT(skey) DO UPDATE SET svalue = excluded.svalue';
    db()->prepare($sql)->execute([$key, $value]);
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
