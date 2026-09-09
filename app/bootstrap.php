<?php
/**
 * The Prairie Dispatch — application bootstrap.
 * Every entry point (public page, admin page, cron) requires this file first.
 */

define('PP_ROOT', dirname(__DIR__));
define('PP_SCHEMA_VERSION', 20);

// PP_CONFIG lets the committed harness (tools/seed-all.sh, baseline.sh)
// point a CLI run at a throwaway config without touching the checkout's
// own config.php. CLI and the dev server only — FPM never honours it.
$configFile = PP_ROOT . '/config.php';
if (in_array(PHP_SAPI, ['cli', 'cli-server'], true) && getenv('PP_CONFIG') !== false && is_file((string) getenv('PP_CONFIG'))) {
    $configFile = (string) getenv('PP_CONFIG');
}
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

/*
 * Sessions start lazily: on admin pages, on POSTs, or when a session cookie
 * already exists. Anonymous public GETs never open one — no public form or
 * page reads $_SESSION (contact and subscribe use honeypots and IP rate
 * limits) — which keeps those responses cookie-free and lets the nginx
 * microcache absorb traffic spikes.
 */
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $ppNeedsSession = isset($_COOKIE['ppsession'])
        || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        || str_starts_with((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/');
    if ($ppNeedsSession) {
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
}

require PP_ROOT . '/app/helpers.php';
require PP_ROOT . '/app/security.php';
require PP_ROOT . '/app/transport.php';
require PP_ROOT . '/app/i18n.php';
require PP_ROOT . '/app/db.php';
require PP_ROOT . '/app/models.php';
require PP_ROOT . '/app/media.php';
require PP_ROOT . '/app/ai.php';
require PP_ROOT . '/app/google.php';

function pp_config(string $key, $default = null)
{
    return $GLOBALS['pp_config'][$key] ?? $default;
}

/** The configured database driver name (sqlite when unset or unknown). */
function pp_db_driver(): string
{
    $driver = $GLOBALS['pp_config']['db']['driver'] ?? 'sqlite';
    return in_array($driver, ['mysql', 'pgsql', 'sqlite'], true) ? $driver : 'sqlite';
}

/**
 * Connect to the configured database — and do NOTHING else. No schema
 * creation, no installation, no seeding, no migration: connecting is not
 * permission to change anything (F12). Maintenance tools use this handle
 * directly; ordinary code goes through db(), which adds the readiness gate.
 *
 * $overlay merges over the configured connection settings — the migration
 * runner uses it to reach a direct (non-pooled) maintenance endpoint.
 */
function pp_db_connect(array $overlay = []): PDO
{
    $cfg = array_replace_recursive($GLOBALS['pp_config']['db'] ?? [], $overlay);
    $driver = pp_db_driver();

    if ($driver === 'mysql') {
        $m = $cfg['mysql'];
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $m['host'], $m['name'], $m['charset'] ?? 'utf8mb4');
        if (!empty($m['port'])) {
            $dsn .= ';port=' . (int) $m['port'];
        }
        if (!empty($m['socket'])) {
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $m['socket'], $m['name'], $m['charset'] ?? 'utf8mb4');
        }
        return new PDO($dsn, $m['user'], $m['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Legacy driver, native changed-rows rowCount(): an identical
            // re-save through pp_guarded_post_update() misreports as a
            // conflict here. Known defect, deferred outside this build —
            // production runs Postgres (see docs/build/phase-02-handoff.md,
            // "Scope correction — Supabase retained").
        ]);
    }
    if ($driver === 'pgsql') {
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
        // The app lives in its own Postgres schema. Setting the search path
        // is a session property, not DDL — the schema itself is created by
        // the migration runner, never by a request.
        $pdo->exec('SET search_path TO "' . pp_pg_schema() . '"');
        return $pdo;
    }
    $path = $cfg['sqlite_path'] ?? PP_ROOT . '/data/prairiedispatch.sqlite';
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // busy_timeout FIRST: the WAL switch itself takes the write lock, and
    // two processes opening a brand-new file together would otherwise race
    // it with no patience at all.
    $pdo->exec('PRAGMA busy_timeout = 10000');
    $pdo->exec('PRAGMA foreign_keys = ON');
    try {
        $pdo->exec('PRAGMA journal_mode = WAL');
    } catch (PDOException) {
        // The mode is a persistent property of the FILE: if a concurrent
        // writer holds the lock right now, whoever set it first already
        // made it WAL, and this connection works fine either way.
    }
    return $pdo;
}

/** For sqlite, the configured database file path (empty for other drivers). */
function pp_sqlite_path(): string
{
    if (pp_db_driver() !== 'sqlite') {
        return '';
    }
    return (string) ($GLOBALS['pp_config']['db']['sqlite_path'] ?? PP_ROOT . '/data/prairiedispatch.sqlite');
}

/**
 * Refuse to serve: the schema this process needs is not in a state it can
 * safely use. Web requests get a controlled, non-cacheable 503 with no
 * branding (the broken schema cannot be read for settings) and no
 * internals; CLI jobs stop non-zero before any business write. The
 * operator detail goes to the error log, never to the client.
 */
function pp_db_unavailable(string $publicReason, string $operatorDetail): never
{
    error_log('prairiepost unavailable: ' . $operatorDetail
        . ' — inspect with `php tools/migrate.php --status`, prepare with `php tools/migrate.php --apply`.');
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "UNAVAILABLE: $operatorDetail\n"
            . "Inspect: php tools/migrate.php --status   Prepare: php tools/migrate.php --apply\n");
        exit(2);
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    header('Retry-After: 120');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex, nofollow"><title>Temporarily unavailable</title></head>'
       . '<body style="font-family:system-ui,sans-serif;max-width:36em;margin:15vh auto;padding:0 1em">'
       . '<h1>Temporarily unavailable</h1>'
       . '<p>' . htmlspecialchars($publicReason, ENT_QUOTES) . ' Please try again in a few minutes.</p>'
       . '</body></html>';
    exit;
}

/**
 * Lazily connected PDO handle for ORDINARY work — web, admin, cron, CLI
 * business jobs. Connects, then verifies once per process that the schema
 * is present and at exactly the version this code was built for; anything
 * else (absent, behind, ahead, or mid-migration) refuses to serve rather
 * than installing, migrating, or guessing. Schema changes happen only
 * through tools/migrate.php.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    // A missing SQLite file is reported without creating it — merely asking
    // for a handle must not manufacture an empty database.
    $sqlitePath = pp_sqlite_path();
    if ($sqlitePath !== '' && !is_file($sqlitePath)) {
        pp_db_unavailable('This site is being set up.', "database file does not exist: $sqlitePath");
    }
    try {
        $candidate = pp_db_connect();
    } catch (PDOException $e) {
        pp_db_unavailable('The site cannot reach its database.', 'connection failed: ' . $e->getMessage());
    }
    $status = pp_schema_status($candidate, pp_db_driver());
    if ($status['state'] !== 'ready') {
        pp_db_unavailable('This site is being updated.', sprintf(
            'schema %s (stored version %s, this code needs %d)',
            $status['state'], $status['version'] === null ? 'unknown' : (string) $status['version'], PP_SCHEMA_VERSION
        ));
    }
    $pdo = $candidate;
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
 * The site slug the domains table maps this request's hostname to, or null
 * when no row claims it. Rows are written only by the seeder from launch
 * packs; this is the authoritative tenant lookup, with the config selector
 * as the fallback for hostnames that predate their row. The try/catch is
 * load-bearing: on a database that hasn't run migration 15 yet (the first
 * request after an upgrade resolves the tenant before it migrates), the
 * query fails and resolution falls back to the config exactly as before.
 */
function pp_domain_site_slug(): ?string
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    $host = preg_replace('/:\d+$/', '', $host);
    if ($host === '' || !preg_match('/^[a-z0-9.-]+$/', $host)) {
        return null;
    }
    // No try/catch here any more: db() has already verified the schema is
    // at this code's version, so `domains` exists. Swallowing an error at
    // this point would resolve the WRONG tenant on a broken schema — the
    // failure must surface, not fall through to the config default.
    $stmt = db()->prepare('SELECT site_slug FROM domains WHERE hostname = ?');
    $stmt->execute([$host]);
    $slug = $stmt->fetchColumn();
    return $slug !== false && $slug !== '' ? (string) $slug : null;
}

/**
 * The site this deployment serves. Resolution order: the PP_SITE
 * environment override, then the domains table on the request hostname,
 * then the config 'site_slug'. A slug with no site row REFUSES to serve —
 * an ordinary request never creates a site (that was request-time
 * seeding); papers join the network through tools/seed-launch.php, which
 * provisions the row explicitly.
 */
function current_site(): array
{
    $site = pp_current_site_or_null();
    if ($site === null) {
        $slug = pp_current_site_slug();
        pp_db_unavailable('This site is not provisioned here.',
            "no site row for tenant slug '$slug' — provision it with PP_SITE=$slug php tools/seed-launch.php");
    }
    return $site;
}

/** The tenant slug this process resolves to, before any row lookup. */
function pp_current_site_slug(): string
{
    // PP_SITE overrides everything for CLI runs (cron on a multi-site host,
    // where HTTP_HOST-based mapping has no host to look at).
    return slugify((string) (getenv('PP_SITE') ?: pp_domain_site_slug() ?: pp_config('site_slug', 'prairiedispatch')));
}

/**
 * The current site row, or null when the resolved tenant has no row —
 * for callers that legitimately act OUTSIDE any one paper (the audit
 * log's global entries, provisioning tools on a not-yet-seeded install).
 * Ordinary page/tool flow uses current_site(), which refuses instead.
 */
function pp_current_site_or_null(): ?array
{
    static $site = null;
    static $looked = false;
    if ($looked) {
        return $site;
    }
    $stmt = db()->prepare('SELECT * FROM sites WHERE slug = ?');
    $stmt->execute([pp_current_site_slug()]);
    $row = $stmt->fetch();
    $site = $row === false ? null : $row;
    $looked = true;
    return $site;
}

function current_site_id(): int
{
    return (int) current_site()['id'];
}

/**
 * Whether this request is serving the network's master control room —
 * the Civis Media hub. True when the current site's slug matches the
 * config 'hub_slug'. The hub is an ordinary site row; hub-ness only
 * decides which admin pages exist here.
 */
function pp_is_hub(): bool
{
    $hub = slugify((string) pp_config('hub_slug', ''));
    return $hub !== '' && (current_site()['slug'] ?? '') === $hub;
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
