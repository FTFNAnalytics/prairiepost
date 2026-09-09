<?php
/**
 * The migration runner (F12), on the actual configured engine: fresh
 * installs, adoption of journal-less databases, inconsistent-claim
 * refusal, ahead/behind classification, populated upgrades that preserve
 * data, GENUINE step failure (a conflicting object, no injection
 * switches), the repair path, lock contention, simultaneous runners, and
 * non-interference between separate scratch targets.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$engine = pp_fixture_engine();

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}
function mig(array $fx, string $args, string $env = ''): array
{
    return pp_fixture_tool($fx, "tools/migrate.php $args", $env);
}

/* --- Status/dry-run never mutate; apply installs; rerun no-ops ------------- */

$fx = pp_fixture_create('mig');
register_shutdown_function(fn () => pp_fixture_destroy($fx));

if ($engine === 'sqlite') {
    [$out, $rc] = mig($fx, '--status');
    ok($rc === 4 && !is_file($fx['sqlite_path']), "status on a missing file reports empty (rc=$rc) and creates nothing");
    [$out, $rc] = mig($fx, '--dry-run');
    ok($rc === 4 && !is_file($fx['sqlite_path']), 'dry-run creates nothing either');
}

[$out, $rc] = mig($fx, '--apply');
ok($rc === 0 && str_contains($out, 'installing: fresh schema'), "fresh apply installs (rc=$rc)");
[$out, $rc] = mig($fx, '--status');
ok($rc === 0 && str_contains($out, 'ready'), 'status reads ready after install');
[$out, $rc] = mig($fx, '--apply');
ok($rc === 0 && str_contains($out, 'nothing pending'), 'a second apply is a no-op');

$pdo = pp_fixture_connect($fx);
$targetVersion = (int) $pdo->query("SELECT svalue FROM settings WHERE site_id = 0 AND skey = 'schema_version'")->fetchColumn();
$j = $pdo->query('SELECT version, status FROM schema_migrations ORDER BY version')->fetchAll();
ok(count($j) === 1 && $j[0]['status'] === 'installed', 'journal holds exactly one installed baseline');

/* --- Sanitized output ------------------------------------------------------- */

ok(!preg_match('/pass|password|secret/i', $out), 'runner output never mentions credentials');

/* --- Adoption and refusal --------------------------------------------------- */

// A journal-less database (anything built before this phase): drop the
// journal, keep the catalog — apply must validate and adopt.
$pdo->exec('DROP TABLE schema_migrations');
[$out, $rc] = mig($fx, '--apply');
ok($rc === 0 && str_contains($out, 'adopted'), 'a journal-less current database is validated and adopted');

// An inconsistent claim: version says current, but a step's objects are
// missing. Adoption must refuse, not fabricate history.
$pdo->exec('DROP TABLE schema_migrations');
$pdo->exec('DROP TABLE media_orders');
[$out, $rc] = mig($fx, '--apply');
ok($rc === 5 && str_contains($out, 'REFUSED to adopt'), "an inconsistent version claim refuses adoption (rc=$rc)");

/* --- Behind: a populated older database upgrades and keeps its data --------- */

// Make this database an honest v18: media_orders is already gone; the v20
// indexes must go too, or re-running step 20 would collide with them.
foreach (['idx_post_tags_tag', 'idx_news_items_source', 'idx_audit_log_site'] as $ix) {
    try { $pdo->exec("DROP INDEX $ix" . ($engine === 'mysql' ? ' ON ' . ['idx_post_tags_tag' => 'post_tags', 'idx_news_items_source' => 'news_items', 'idx_audit_log_site' => 'audit_log'][$ix] : '')); } catch (Throwable) {}
}
$pdo->prepare("UPDATE settings SET svalue = '18' WHERE site_id = 0 AND skey = 'schema_version'")->execute();
$pdo->prepare('INSERT INTO posts (title, slug, body, status, author_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?)')
    ->execute(['Upgrade survivor', 'upgrade-survivor-' . bin2hex(random_bytes(3)), '<p>x</p>', 'published', 1, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);

[$out, $rc] = mig($fx, '--status');
ok($rc === 3 && str_contains($out, 'behind'), 'an older database classifies as behind');
[$out, $rc] = mig($fx, '--dry-run');
ok($rc === 3 && str_contains($out, 'step 19') && str_contains($out, 'checksum'), 'dry-run lists the pending step with its checksum');

[$out, $rc] = mig($fx, '--apply');
ok($rc === 0 && str_contains($out, 'step 19'), 'apply runs the pending step');
$pdo2 = pp_fixture_connect($fx);
ok((int) $pdo2->query("SELECT COUNT(*) FROM posts WHERE title = 'Upgrade survivor'")->fetchColumn() === 1,
    'populated data survives the upgrade');
$jr = $pdo2->query("SELECT version, status FROM schema_migrations ORDER BY version")->fetchAll();
ok(end($jr)['status'] === 'applied', 'the final step is journaled applied');
[$out, $rc] = mig($fx, '--status');
ok($rc === 0, 'and the upgraded database is ready');

/* --- Ahead ------------------------------------------------------------------- */

$pdo2->prepare("UPDATE settings SET svalue = '99' WHERE site_id = 0 AND skey = 'schema_version'")->execute();
[$out, $rc] = mig($fx, '--status');
ok($rc === 6, 'a newer-than-code schema classifies as ahead');
[$out, $rc] = mig($fx, '--apply');
ok($rc === 6 && (int) $pdo2->query('SELECT COUNT(*) FROM posts')->fetchColumn() > 0, 'apply refuses to touch an ahead schema');
$pdo2->prepare("UPDATE settings SET svalue = ? WHERE site_id = 0 AND skey = 'schema_version'")->execute([(string) $targetVersion]);

/* --- GENUINE step failure: a conflicting object, then repair ----------------- */

$fx2 = pp_fixture_create('migfail');
register_shutdown_function(fn () => pp_fixture_destroy($fx2));
[$out, $rc] = mig($fx2, '--apply');
ok($rc === 0, 'second fixture installs');
$p2 = pp_fixture_connect($fx2);
// Roll it back to v18 honestly, then squat the name step 19 wants with a
// WRONG-shape table — the step fails for real, no fault switches.
$p2->exec('DROP TABLE media_orders');
foreach (['idx_post_tags_tag' => 'post_tags', 'idx_news_items_source' => 'news_items', 'idx_audit_log_site' => 'audit_log'] as $ix => $tbl) {
    try { $p2->exec("DROP INDEX $ix" . ($engine === 'mysql' ? " ON $tbl" : '')); } catch (Throwable) {}
}
$p2->prepare("UPDATE settings SET svalue = '18' WHERE site_id = 0 AND skey = 'schema_version'")->execute();
$p2->exec('DELETE FROM schema_migrations');
$p2->exec('INSERT INTO schema_migrations (version, name, checksum, status, started_at, finished_at) '
    . "VALUES (18, 'test baseline', '', 'adopted', '2026-01-01 00:00:00', '2026-01-01 00:00:00')");
$p2->exec('CREATE TABLE media_orders (wrong_shape INTEGER)');

[$out, $rc] = mig($fx2, '--apply');
ok($rc !== 0, "the conflicting object makes step 19 fail for real (rc=$rc)");
$p3 = pp_fixture_connect($fx2);

if ($engine === 'mysql') {
    // DDL auto-commits: the 'started' row survives → partial, refused,
    // and resumable only once the step's REAL objects exist.
    [$out, $rc] = mig($fx2, '--status');
    ok($rc === 5 && str_contains($out, 'partial'), 'MySQL: the interrupted step classifies as partial');
    [$out, $rc] = mig($fx2, '--apply');
    ok($rc === 5, 'MySQL: apply refuses to march past the unfinished step');
    [$out, $rc] = mig($fx2, '--apply --resume-partial');
    ok($rc === 5 && str_contains($out, 'CANNOT RESUME'),
        'MySQL: resume refuses while the markers are absent (the squatter table has the wrong shape)');
    ok(str_contains($out, 'CREATE TABLE media_orders'), 'and prints the exact step definition for the repair');
    // The precise repair: remove the squatter and complete the step by
    // hand from the printed definition — here, by running the step body.
    $p3->exec('DROP TABLE media_orders');
    [$out, $rc] = pp_fixture_tool($fx2, 'tests/fixtures/run-step.php 19');
    ok($rc === 0, "manual completion of the step body succeeds ($out)");
    [$out, $rc] = mig($fx2, '--apply --resume-partial');
    ok($rc === 0, 'resume then completes the journal from the detected state');
} else {
    // Transactional DDL: the failed step rolled back whole — no partial
    // state, version still 18, and removing the obstacle lets a plain
    // rerun finish.
    $st = $p3->query("SELECT COUNT(*) FROM schema_migrations WHERE status = 'started'")->fetchColumn();
    ok((int) $st === 0, 'transactional engines leave no half-recorded step');
    [$out, $rc] = mig($fx2, '--status');
    ok($rc === 3, 'still classified behind, not partial');
    $p3->exec('DROP TABLE media_orders');
    [$out, $rc] = mig($fx2, '--apply');
    ok($rc === 0, 'with the obstacle removed, a plain rerun completes');
}
[$out, $rc] = mig($fx2, '--status');
ok($rc === 0 && str_contains($out, 'ready'), 'the repaired database ends ready');

/* --- Simultaneous runners on ONE target: exactly one installs ---------------- */

$fx3 = pp_fixture_create('migrace');
register_shutdown_function(fn () => pp_fixture_destroy($fx3));
$cmd = 'PP_CONFIG=' . escapeshellarg($fx3['config']) . ' ' . escapeshellarg(PHP_BINARY)
     . ' ' . escapeshellarg("$root/tools/migrate.php") . ' --apply 2>&1';
$procs = [];
$pipes = [];
for ($i = 0; $i < 2; $i++) {
    $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w']], $pipes[$i]);
}
$codes = [];
foreach ($procs as $i => $p) {
    stream_get_contents($pipes[$i][1]);
    $codes[] = proc_close($p);
}
ok($codes[0] === 0 && $codes[1] === 0, 'both simultaneous runners exit clean (one installs, one finds it done)');
$p4 = pp_fixture_connect($fx3);
ok((int) $p4->query("SELECT COUNT(*) FROM schema_migrations WHERE status = 'installed'")->fetchColumn() === 1,
    'exactly one installed baseline exists');
[$out, $rc] = mig($fx3, '--status');
ok($rc === 0, 'the raced target ends ready');

/* --- Lock contention and separate-target independence ------------------------ */

if ($engine === 'pgsql') {
    // A foreign session holds THIS schema's lock: the runner must give up
    // within its bounded wait, exit 7, and change nothing.
    $p = $fx['pg'];
    $holder = new PDO("pgsql:host={$p['host']};port={$p['port']};dbname={$p['name']}", $p['user'], $p['pass']);
    $holder->prepare('SELECT pg_advisory_lock(hashtext(?))')->execute(['pp-migrate:' . $fx['schema']]);
    [$out, $rc] = mig($fx, '--apply', 'PP_MIGRATE_LOCK_TIMEOUT=2');
    ok($rc === 7, "a held advisory lock bounds out with exit 7 (rc=$rc)");
    $holder->query('SELECT pg_advisory_unlock_all()');
    // Two DIFFERENT schemas migrate at once without contention.
    $fa = pp_fixture_create('miga');
    $fb = pp_fixture_create('migb');
    register_shutdown_function(function () use ($fa, $fb) { pp_fixture_destroy($fa); pp_fixture_destroy($fb); });
    $pa = proc_open('PP_CONFIG=' . escapeshellarg($fa['config']) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/tools/migrate.php") . ' --apply 2>&1', [1 => ['pipe', 'w']], $pi1);
    $pb = proc_open('PP_CONFIG=' . escapeshellarg($fb['config']) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/tools/migrate.php") . ' --apply 2>&1', [1 => ['pipe', 'w']], $pi2);
    stream_get_contents($pi1[1]);
    stream_get_contents($pi2[1]);
    ok(proc_close($pa) === 0 && proc_close($pb) === 0, 'separate scratch schemas migrate concurrently without blocking');
}
if ($engine === 'mysql') {
    $holder = pp_fixture_admin_mysql($fx['my']);
    $holder->exec("USE `{$fx['dbname']}`");
    $holder->query("SELECT GET_LOCK('pp-migrate:{$fx['dbname']}', 0)");
    [$out, $rc] = mig($fx, '--apply', 'PP_MIGRATE_LOCK_TIMEOUT=2');
    ok($rc === 7, "a held named lock bounds out with exit 7 (rc=$rc)");
    $holder->query('SELECT RELEASE_ALL_LOCKS()');
}

exit($fails ? 1 : 0);
