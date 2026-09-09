<?php
/**
 * The migration runner — the ONLY thing that installs or changes the
 * application schema (F12). Ordinary requests verify and refuse; this
 * tool, run deliberately by an operator or the release procedure, mutates.
 *
 *   php tools/migrate.php --status      report, change nothing (exit codes below)
 *   php tools/migrate.php --dry-run     list what --apply would do, change nothing
 *   php tools/migrate.php --apply       install fresh, or run pending steps
 *   php tools/migrate.php --apply --resume-partial
 *                                       finish a step interrupted mid-flight,
 *                                       when its catalog markers prove it landed
 *
 * Target selection: the same config.php / PP_CONFIG the application reads.
 * For Postgres, an optional `db.pgsql.maintenance` config block (host/port/
 * user/pass overrides) points at a DIRECT connection — session advisory
 * locks and multi-statement sessions are not safe through a transaction
 * pooler, and the runner verifies its lock is really held by its own
 * backend before touching anything.
 *
 * Exit codes:
 *   0  ready (status) / applied cleanly (apply)
 *   2  usage, connection, or environment error
 *   3  schema behind (status/dry-run: steps pending)
 *   4  schema empty (status/dry-run: fresh install pending)
 *   5  schema partial or inconsistent — operator repair needed
 *   6  schema AHEAD of this code — deploy newer code, never downgrade here
 *   7  another runner holds the migration lock
 *
 * Locking: one runner per logical database/schema. Postgres: a session
 * advisory lock keyed on the schema name, bounded by lock_timeout. SQLite:
 * each step runs inside BEGIN IMMEDIATE with a busy timeout — the file's
 * own write lock serializes runners. MySQL: GET_LOCK named for the
 * database. Scratch databases/schemas with different identities never
 * contend with each other.
 *
 * Journaling: engines with transactional DDL (Postgres, SQLite) commit
 * each step's DDL and its journal row as ONE transaction, so a crash
 * leaves either a fully recorded step or nothing. MySQL auto-commits DDL,
 * so the journal writes 'started' first and 'applied' after; a surviving
 * 'started' row marks the database partial, the application refuses to
 * serve it, and --resume-partial finishes it only when the step's catalog
 * markers are actually present.
 *
 * Never prints credentials. Status and dry-run never write — a missing
 * SQLite file is REPORTED, not created.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

$args = array_slice($argv, 1);
$mode = null;
$resumePartial = false;
foreach ($args as $a) {
    switch ($a) {
        case '--status':
        case '--dry-run':
        case '--apply':
            if ($mode !== null) {
                fwrite(STDERR, "Pick ONE of --status / --dry-run / --apply.\n");
                exit(2);
            }
            $mode = substr($a, 2);
            break;
        case '--resume-partial':
            $resumePartial = true;
            break;
        default:
            fwrite(STDERR, "Unknown option: $a\nUsage: php tools/migrate.php --status | --dry-run | --apply [--resume-partial]\n");
            exit(2);
    }
}
if ($mode === null) {
    fwrite(STDERR, "Usage: php tools/migrate.php --status | --dry-run | --apply [--resume-partial]\n");
    exit(2);
}

$driver = pp_db_driver();

/** Sanitized target identity — never the credentials. */
function mg_target(string $driver): string
{
    $cfg = $GLOBALS['pp_config']['db'] ?? [];
    if ($driver === 'pgsql') {
        $p = $cfg['pgsql'] ?? [];
        return sprintf('pgsql %s:%s db=%s schema=%s', $p['host'] ?? '?', $p['port'] ?? 5432, $p['name'] ?? '?', pp_pg_schema());
    }
    if ($driver === 'mysql') {
        $m = $cfg['mysql'] ?? [];
        return sprintf('mysql %s db=%s', $m['socket'] ?? (($m['host'] ?? '?') . ':' . ($m['port'] ?? 3306)), $m['name'] ?? '?');
    }
    return 'sqlite ' . pp_sqlite_path();
}

echo 'target:  ', mg_target($driver), "\n";
echo 'code:    schema version ', PP_SCHEMA_VERSION, "\n";

/* --- Read-only phase: connect (or report a missing sqlite file) ----------- */

$sqlitePath = pp_sqlite_path();
if ($sqlitePath !== '' && !is_file($sqlitePath)) {
    if ($mode !== 'apply') {
        echo "state:   empty (the database file does not exist; --status/--dry-run never create it)\n";
        echo 'pending: fresh install to version ', PP_SCHEMA_VERSION, "\n";
        exit(4);
    }
} elseif ($sqlitePath === '' || is_file($sqlitePath)) {
    // fallthrough: connect below
}

try {
    // Maintenance overlay (Postgres): a direct endpoint for session locks.
    $overlay = [];
    if ($driver === 'pgsql' && !empty($GLOBALS['pp_config']['db']['pgsql']['maintenance'])
        && is_array($GLOBALS['pp_config']['db']['pgsql']['maintenance'])) {
        $overlay = ['pgsql' => $GLOBALS['pp_config']['db']['pgsql']['maintenance']];
        echo "conn:    maintenance overlay in use (direct endpoint)\n";
    }
    $pdo = ($sqlitePath !== '' && !is_file($sqlitePath)) ? null : pp_db_connect($overlay);
} catch (PDOException $e) {
    fwrite(STDERR, 'connection failed: ' . $e->getMessage() . "\n");
    exit(2);
}

/** Does a table exist in OUR namespace? */
function mg_table_exists(PDO $pdo, string $driver, string $table): bool
{
    if ($driver === 'pgsql') {
        $s = $pdo->prepare('SELECT 1 FROM pg_tables WHERE schemaname = ? AND tablename = ?');
        $s->execute([pp_pg_schema(), $table]);
        return $s->fetch() !== false;
    }
    if ($driver === 'mysql') {
        $s = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $s->execute([$table]);
        return $s->fetch() !== false;
    }
    $s = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
    $s->execute([$table]);
    return $s->fetch() !== false;
}

function mg_column_exists(PDO $pdo, string $driver, string $table, string $column): bool
{
    if (!mg_table_exists($pdo, $driver, $table)) {
        return false;
    }
    if ($driver === 'pgsql') {
        $s = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?');
        $s->execute([pp_pg_schema(), $table, $column]);
        return $s->fetch() !== false;
    }
    if ($driver === 'mysql') {
        $s = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $s->execute([$table, $column]);
        return $s->fetch() !== false;
    }
    foreach ($pdo->query('PRAGMA table_info(' . preg_replace('/\W/', '', $table) . ')') as $row) {
        if (strcasecmp((string) $row['name'], $column) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Catalog markers a step leaves behind, derived from its own source: the
 * tables it creates and the columns it adds. Used to validate that a
 * stored version number reflects steps that actually completed, and to
 * decide whether an interrupted step really landed.
 */
function mg_step_markers(int $version): array
{
    $src = pp_migration_source($version);
    $markers = [];
    // A created table is probed WITH its first column, so a squatter table
    // of the same name but the wrong shape cannot satisfy the marker.
    if (preg_match_all('/CREATE TABLE (\w+)\s*\(\s*(\w+)/', $src, $m)) {
        foreach ($m[1] as $i => $t) {
            $markers[] = [$t, $m[2][$i]];
        }
    }
    if (preg_match_all('/ALTER TABLE (\w+) ADD COLUMN (\w+)/', $src, $m)) {
        foreach ($m[1] as $i => $t) {
            $markers[] = [$t, $m[2][$i]];
        }
    }
    return $markers;
}

function mg_markers_present(PDO $pdo, string $driver, int $version): bool
{
    foreach (mg_step_markers($version) as [$table, $column]) {
        $ok = $column === null
            ? mg_table_exists($pdo, $driver, $table)
            : mg_column_exists($pdo, $driver, $table, $column);
        if (!$ok) {
            return false;
        }
    }
    return true;
}

/**
 * Validate that a database claiming version $v actually looks like one:
 * every marker at or below $v present, every marker table introduced
 * AFTER $v absent. Returns a list of discrepancies (empty = consistent).
 */
function mg_validate_claimed_version(PDO $pdo, string $driver, int $v): array
{
    $problems = [];
    foreach (pp_migrations() as $step => $fn) {
        $markers = mg_step_markers($step);
        if (!$markers) {
            continue;
        }
        if ($step <= $v) {
            foreach ($markers as [$t, $c]) {
                $ok = $c === null ? mg_table_exists($pdo, $driver, $t) : mg_column_exists($pdo, $driver, $t, $c);
                if (!$ok) {
                    $problems[] = "step $step should have left " . ($c === null ? "table $t" : "$t.$c") . ' — missing';
                }
            }
        } else {
            // Only table creations make a reliable absence probe.
            foreach ($markers as [$t, $c]) {
                if ($c === null && mg_table_exists($pdo, $driver, $t)) {
                    $problems[] = "table $t belongs to step $step (> claimed $v) but already exists";
                }
            }
        }
    }
    return $problems;
}

/* --- Status --------------------------------------------------------------- */

$status = $pdo === null
    ? ['state' => 'empty', 'version' => null, 'target' => PP_SCHEMA_VERSION]
    : pp_schema_status($pdo, $driver);
echo 'state:   ', $status['state'],
     ' (stored version ', $status['version'] === null ? 'none' : $status['version'],
     ', target ', $status['target'], ")\n";

$pending = [];
if ($status['state'] === 'behind') {
    foreach (pp_migrations() as $v => $fn) {
        if ($v > (int) $status['version']) {
            $pending[$v] = pp_migration_label($v);
        }
    }
}

if ($mode === 'status' || $mode === 'dry-run') {
    if ($status['state'] === 'ready') {
        if ($pdo !== null && !pp_journal_exists($pdo, $driver)) {
            echo "journal: none — the first --apply will validate the catalog and adopt this database\n";
        }
        echo "Nothing to do.\n";
        exit(0);
    }
    if ($status['state'] === 'empty') {
        echo 'pending: fresh install of the full current schema (version ', PP_SCHEMA_VERSION, "), no seeding\n";
        if ($mode === 'dry-run') {
            echo "dry-run: --apply would create the schema objects and stamp version ", PP_SCHEMA_VERSION, ".\n";
            echo "         Content seeding stays separate: tools/seed-core.php, tools/seed-launch.php.\n";
        }
        exit(4);
    }
    if ($status['state'] === 'ahead') {
        echo "REFUSED: this database is newer than this code. Deploy the newer release; never downgrade a schema from here.\n";
        exit(6);
    }
    if ($status['state'] === 'partial') {
        foreach ($pdo->query("SELECT version, name, started_at FROM schema_migrations WHERE status = 'started'") as $row) {
            echo "partial: step {$row['version']} ({$row['name']}) started {$row['started_at']} and never finished\n";
        }
        echo "Repair: php tools/migrate.php --apply --resume-partial   (finishes only if the step's catalog markers are present;\n";
        echo "otherwise it stops and prints the step for manual completion.)\n";
        exit(5);
    }
    // behind
    foreach ($pending as $v => $label) {
        printf("pending: step %-3d %s  checksum %s\n", $v, $label, substr(pp_migration_checksum($v), 0, 12));
    }
    if ($mode === 'dry-run') {
        echo "dry-run: --apply would take the migration lock for this schema and run the steps above, one\n";
        echo "         at a time, each journaled. Steps that create tables/columns take brief exclusive\n";
        echo "         locks on the tables they touch; no long backfills are defined in this range.\n";
    }
    exit(3);
}

/* --- Apply ---------------------------------------------------------------- */

if ($status['state'] === 'ahead') {
    fwrite(STDERR, "REFUSED: schema is ahead of this code (stored {$status['version']}, code " . PP_SCHEMA_VERSION . ").\n");
    exit(6);
}

// Create-on-apply for a missing sqlite file happens here, deliberately.
if ($pdo === null) {
    try {
        $pdo = pp_db_connect();
    } catch (PDOException $e) {
        fwrite(STDERR, 'connection failed: ' . $e->getMessage() . "\n");
        exit(2);
    }
    $status = pp_schema_status($pdo, $driver);
}

/* Take the one-runner-per-schema lock. */
$lockHeld = false;
$lockWait = max(1, (int) (getenv('PP_MIGRATE_LOCK_TIMEOUT') ?: 20)); // seconds; an ops knob, not a behavior switch
if ($driver === 'pgsql') {
    $pdo->exec("SET lock_timeout = '" . $lockWait . "s'");
    $pdo->exec("SET statement_timeout = '600s'");
    $key = 'pp-migrate:' . pp_pg_schema();
    try {
        $pdo->prepare('SELECT pg_advisory_lock(hashtext(?))')->execute([$key]);
    } catch (PDOException $e) {
        fwrite(STDERR, "Could not take the migration lock within {$lockWait}s — another runner is working this schema.\n");
        exit(7);
    }
    // Prove the lock lives on OUR backend session: on a transaction pooler
    // the lock could land on a different server connection than the next
    // statement uses, which silently voids every guarantee below.
    $mine = (int) $pdo->query("SELECT COUNT(*) FROM pg_locks WHERE locktype = 'advisory' AND pid = pg_backend_pid()")->fetchColumn();
    if ($mine < 1) {
        fwrite(STDERR, "REFUSED: the advisory lock did not stick to this session — this looks like a transaction\n"
            . "pooler endpoint. Point db.pgsql.maintenance at the DIRECT connection (port 5432) and rerun.\n");
        exit(2);
    }
    $lockHeld = true;
    // The application schema itself is maintenance-owned DDL.
    $pdo->exec('CREATE SCHEMA IF NOT EXISTS "' . pp_pg_schema() . '"');
    $pdo->exec('SET search_path TO "' . pp_pg_schema() . '"');
} elseif ($driver === 'mysql') {
    $dbname = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $got = $pdo->query("SELECT GET_LOCK(" . $pdo->quote('pp-migrate:' . $dbname) . ", " . $lockWait . ")")->fetchColumn();
    if ((string) $got !== '1') {
        fwrite(STDERR, "Could not take the migration lock within {$lockWait}s — another runner is working this database.\n");
        exit(7);
    }
    $lockHeld = true;
}
// sqlite: BEGIN IMMEDIATE per step + busy_timeout serialize runners.

$release = function () use ($pdo, $driver, &$lockHeld): void {
    if (!$lockHeld) {
        return;
    }
    try {
        if ($driver === 'pgsql') {
            $pdo->query('SELECT pg_advisory_unlock_all()');
        } elseif ($driver === 'mysql') {
            $pdo->query('SELECT RELEASE_ALL_LOCKS()');
        }
    } catch (Throwable) {
        // the lock dies with the session anyway
    }
    $lockHeld = false;
};

/** One transactional unit: journal + work commit together where DDL allows. */
function mg_txn(PDO $pdo, string $driver, callable $work): void
{
    if ($driver === 'mysql') {          // DDL auto-commits; no wrapping lie
        $work();
        return;
    }
    if ($driver === 'sqlite') {
        $pdo->exec('BEGIN IMMEDIATE');  // the cross-process write lock
    } else {
        $pdo->beginTransaction();
    }
    try {
        $work();
        $driver === 'sqlite' ? $pdo->exec('COMMIT') : $pdo->commit();
    } catch (Throwable $e) {
        try {
            $driver === 'sqlite' ? $pdo->exec('ROLLBACK') : $pdo->rollBack();
        } catch (Throwable) {
        }
        throw $e;
    }
}

$now = fn (): string => date('Y-m-d H:i:s');

try {
    // Re-read state under the lock — another runner may have finished first.
    $status = pp_schema_status($pdo, $driver);

    if ($status['state'] === 'partial') {
        $open = $pdo->query("SELECT version, name FROM schema_migrations WHERE status = 'started' ORDER BY version")->fetchAll();
        if (!$resumePartial) {
            foreach ($open as $row) {
                fwrite(STDERR, "partial: step {$row['version']} ({$row['name']}) is mid-flight.\n");
            }
            fwrite(STDERR, "Refusing to continue past an unfinished step. If the interruption is understood,\n"
                . "rerun with --apply --resume-partial.\n");
            $release();
            exit(5);
        }
        foreach ($open as $row) {
            $v = (int) $row['version'];
            if (mg_markers_present($pdo, $driver, $v)) {
                $pdo->prepare("UPDATE schema_migrations SET status = 'applied', finished_at = ? WHERE version = ?")
                    ->execute([$now(), $v]);
                // The step's own version bump may not have landed; make it true.
                if (pp_schema_version($pdo) < $v) {
                    $pdo->prepare("UPDATE settings SET svalue = ? WHERE site_id = 0 AND skey = 'schema_version'")
                        ->execute([(string) $v]);
                }
                echo "resumed: step $v — its catalog markers are all present; journal completed\n";
            } else {
                fwrite(STDERR, "CANNOT RESUME step $v: its catalog markers are not all present — the step half-landed.\n"
                    . "Complete it by hand from the definition below, then rerun --apply --resume-partial.\n\n"
                    . pp_migration_source($v) . "\n");
                $release();
                exit(5);
            }
        }
        $status = pp_schema_status($pdo, $driver);
    }

    if ($status['state'] === 'empty') {
        echo "installing: fresh schema at version " . PP_SCHEMA_VERSION . "\n";
        mg_txn($pdo, $driver, function () use ($pdo, $driver, $now) {
            // Under the write lock at last: a concurrent runner may have
            // installed between our first look and this transaction.
            if (pp_schema_installed($pdo, $driver)) {
                return;
            }
            pp_install($pdo, $driver);
            $pdo->exec(pp_journal_ddl($driver));
            $pdo->prepare('INSERT INTO schema_migrations (version, name, checksum, status, started_at, finished_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([PP_SCHEMA_VERSION, 'fresh install of the full current schema', '', 'installed', $now(), $now()]);
        });
        echo "installed. Seeding is separate: php tools/seed-core.php, then per-paper tools/seed-launch.php.\n";
        $release();
        exit(0);
    }

    // Adoption: an existing database with no journal enters the history
    // only after its catalog matches what its version number claims.
    if (!pp_journal_exists($pdo, $driver)) {
        $claimed = (int) $status['version'];
        $problems = mg_validate_claimed_version($pdo, $driver, $claimed);
        if ($problems) {
            fwrite(STDERR, "REFUSED to adopt: this database claims version $claimed but its catalog disagrees:\n");
            foreach ($problems as $p) {
                fwrite(STDERR, "  - $p\n");
            }
            fwrite(STDERR, "A version number is not proof the steps ran. Diagnose with the step definitions in\n"
                . "app/db.php (pp_migrations) and repair the catalog by hand before rerunning.\n");
            $release();
            exit(5);
        }
        mg_txn($pdo, $driver, function () use ($pdo, $driver, $claimed, $now) {
            $pdo->exec(pp_journal_ddl($driver));
            $pdo->prepare('INSERT INTO schema_migrations (version, name, checksum, status, started_at, finished_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$claimed, "adopted at version $claimed after catalog validation", '', 'adopted', $now(), $now()]);
        });
        echo "adopted: existing database validated and journaled at version $claimed\n";
    }

    // Pending steps, one at a time.
    $from = (int) pp_schema_version($pdo);
    $ran = 0;
    foreach (pp_migrations() as $v => $step) {
        if ($v <= $from) {
            continue;
        }
        $label = pp_migration_label($v);
        $checksum = pp_migration_checksum($v);
        echo "step $v: $label\n";
        if ($driver === 'mysql') {
            // DDL auto-commits: record intent first, complete after.
            $pdo->prepare('INSERT INTO schema_migrations (version, name, checksum, status, started_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$v, $label, $checksum, 'started', $now()]);
            $step($pdo, $driver);
            $pdo->prepare("UPDATE schema_migrations SET status = 'applied', finished_at = ? WHERE version = ?")
                ->execute([$now(), $v]);
        } else {
            mg_txn($pdo, $driver, function () use ($pdo, $v, $label, $checksum, $step, $driver, $now) {
                if (pp_schema_version($pdo) >= $v) {
                    return; // a concurrent runner applied it while we waited
                }
                $pdo->prepare('INSERT INTO schema_migrations (version, name, checksum, status, started_at) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$v, $label, $checksum, 'started', $now()]);
                $step($pdo, $driver);
                $pdo->prepare("UPDATE schema_migrations SET status = 'applied', finished_at = ? WHERE version = ?")
                    ->execute([$now(), $v]);
            });
        }
        $ran++;
    }
    $final = pp_schema_status($pdo, $driver);
    echo $ran > 0
        ? "done: $ran step(s) applied; schema now version {$final['version']} ({$final['state']})\n"
        : "done: nothing pending; schema version {$final['version']} ({$final['state']})\n";
    $release();
    exit($final['state'] === 'ready' ? 0 : 5);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    fwrite(STDERR, "Nothing past the last committed step was recorded. Rerun --status to see where this\n"
        . "database stands; on engines without transactional DDL an unfinished step will show as partial.\n");
    $release();
    exit(2);
}