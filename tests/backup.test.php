<?php
/**
 * The backup job (F07) against a synthetic server layout: complete sets
 * with both config layers, private modes, verifying manifests, unique set
 * ids, truthful failure for every required component, overlap and stale-
 * lock handling, off-site encryption with required/optional semantics,
 * and retention that prunes whole sets and never the last good one.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
if (pp_fixture_engine() === 'mysql') {
    echo "SKIP: the backup job dumps pgsql (production) and sqlite (fixtures); mysql installs use their own dump tooling\n";
    exit(0);
}
$fx = pp_fixture_create('backup');
pp_fixture_prepare($fx);   // a real little database to dump

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

/** Build a synthetic server layout; returns its base dir. */
function layout(array $fx, array $opts = []): string
{
    $b = sys_get_temp_dir() . '/pp-bklay-' . bin2hex(random_bytes(4));
    foreach (['vhosts', 'cron', 'dest', 'rel-aaa111-shared/app', 'rel-bbb222-civismedia/app', 'uploads/2026'] as $d) {
        mkdir("$b/$d", 0755, true);
    }
    file_put_contents("$b/uploads/2026/pic.jpg", 'photo-bytes-' . $b);
    symlink("$b/uploads", "$b/rel-aaa111-shared/uploads");
    if (isset($opts['db'])) {
        // A deliberately broken database target (the dump-failure scenario).
        $dbArr = "['driver' => 'sqlite', 'sqlite_path' => '{$opts['db']}']";
    } elseif ($fx['engine'] === 'pgsql') {
        // The pg engine exercises the REAL pg_dump path against the fixture schema.
        $p = $fx['pg'];
        $dbArr = var_export(['driver' => 'pgsql', 'pgsql' => [
            'host' => $p['host'], 'port' => $p['port'], 'name' => $p['name'],
            'user' => $p['user'], 'pass' => $p['pass'], 'sslmode' => 'disable', 'schema' => $fx['schema'],
        ]], true);
    } else {
        $dbArr = "['driver' => 'sqlite', 'sqlite_path' => '{$fx['sqlite_path']}']";
    }
    $site = "<?php\nreturn ['db' => $dbArr,\n"
        . " 'site_slug' => 'civismedia', 'hub_slug' => 'civismedia', 'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];\n";
    $wrapper = "<?php\n\$c = require __DIR__ . '/app/config.site.php';\nreturn \$c;\n";
    foreach (['rel-aaa111-shared', 'rel-bbb222-civismedia'] as $rel) {
        file_put_contents("$b/$rel/config.php", $wrapper);
        if (empty($opts['omit_site_config'])) {
            file_put_contents("$b/$rel/app/config.site.php", $site);
        }
    }
    file_put_contents("$b/vhosts/kitchenerchronicle", "server {\n  root $b/rel-aaa111-shared; # shared release\n}\n");
    file_put_contents("$b/vhosts/civismedia", "server {\n  root $b/rel-bbb222-civismedia;\n}\n");
    file_put_contents("$b/cron/civis-backup", "17 3 * * * root true\n");
    if (!empty($opts['no_uploads'])) {
        unlink("$b/rel-aaa111-shared/uploads");
        exec('rm -rf ' . escapeshellarg("$b/uploads"));
    }
    return $b;
}
function runBackup(string $b, string $extraEnv = ''): array
{
    global $root;
    exec('PP_BACKUP_DEST=' . escapeshellarg("$b/dest")
        . ' PP_BACKUP_VHOSTS_DIR=' . escapeshellarg("$b/vhosts")
        . ' PP_BACKUP_CRON_DIR=' . escapeshellarg("$b/cron")
        . " $extraEnv bash " . escapeshellarg("$root/tools/backup.sh") . ' 2>&1', $out, $rc);
    return [implode("\n", $out), $rc];
}
function state(string $b): array
{
    return json_decode((string) @file_get_contents("$b/dest/state.json"), true) ?: [];
}
function sets(string $b): array
{
    return array_values(array_filter(scandir("$b/dest/sets") ?: [], fn ($s) => preg_match('/^\d{8}-/', $s)));
}

/* --- The happy path ---------------------------------------------------------- */

$b = layout($fx);
[$out, $rc] = runBackup($b);
ok($rc === 0, "a complete backup succeeds (rc=$rc)");
$st = state($b);
ok(($st['ok'] ?? false) === true && ($st['schema_version'] ?? 0) >= 19, 'state.json says ok with the schema version');
ok(!isset($st['components']['ok']), 'component map is clean');
$set = sets($b)[0] ?? '';
$setDir = "$b/dest/sets/$set";
ok(is_file("$setDir/config/rel-bbb222-civismedia/config.site.php"), 'the UNDERLYING config.site.php is in the set (the F07 gap)');
ok(is_file("$setDir/config/rel-bbb222-civismedia/config.php"), 'alongside the wrapper');
ok(is_file("$setDir/uploads.tar.gz") && is_file("$setDir/db.dump") && is_file("$setDir/manifest.json"), 'db, uploads and manifest present');
$perm = substr(sprintf('%o', fileperms($setDir)), -3);
ok($perm === '700', "the set directory is private from creation (mode $perm)");
$dumpPerm = substr(sprintf('%o', fileperms("$setDir/db.dump")), -3);
ok($dumpPerm === '600', "the dump is never world-readable (mode $dumpPerm)");
$man = json_decode((string) file_get_contents("$setDir/manifest.json"), true);
ok(isset($man['files']['db.dump']['sha256']) && $man['files']['db.dump']['sha256'] === hash_file('sha256', "$setDir/db.dump"),
    'manifest checksums match the artifacts');
ok(!empty($man['excluded_external_dependencies']), 'external recovery dependencies are DECLARED, not implied');
ok(!str_contains((string) file_get_contents("$b/dest/state.json"), $fx['sqlite_path'] ?? '@@'), 'state.json carries no filesystem paths');

// A second run: a NEW set id; both retained (same-day reruns cannot clobber).
[$out2, $rc2] = runBackup($b);
ok($rc2 === 0 && count(sets($b)) === 2, 'a same-day rerun publishes a second, distinct set');

/* --- Truthful failures (each leaves the good sets alone) ---------------------- */

$goodCount = count(sets($b));

// Missing underlying config on a wrapper.
$b2 = layout($fx, ['omit_site_config' => true]);
[$out, $rc] = runBackup($b2);
ok($rc !== 0 && (state($b2)['ok'] ?? true) === false, 'a wrapper without config.site.php FAILS the run');
ok(str_contains(state($b2)['reason'] ?? '', 'config.site.php'), 'and says why');
ok(sets($b2) === [], 'and publishes nothing');

// Database dump failure.
$b3 = layout($fx, ['db' => '/nonexistent/nowhere.sqlite']);
[$out, $rc] = runBackup($b3);
ok($rc !== 0 && (state($b3)['ok'] ?? true) === false, 'a failed database dump FAILS the run');
ok(json_decode((string) file_get_contents("$b3/dest/state.json"), true) !== null, 'the failure state is still valid JSON');

// Missing uploads.
$b4 = layout($fx, ['no_uploads' => true]);
[$out, $rc] = runBackup($b4);
ok($rc !== 0 && str_contains(state($b4)['reason'] ?? '', 'uploads'), 'missing uploads FAILS the run, named');

// Archive failure (a tar stub that refuses uploads — confined to this test).
$b5 = layout($fx);
$stub = sys_get_temp_dir() . '/pp-bkstub-' . bin2hex(random_bytes(3));
mkdir($stub);
file_put_contents("$stub/tar", "#!/bin/sh\nfor a in \"\$@\"; do case \"\$a\" in *uploads*) exit 1;; esac; done\nexec /usr/bin/tar \"\$@\"\n");
chmod("$stub/tar", 0755);
[$out, $rc] = runBackup($b5, 'PATH=' . escapeshellarg("$stub:" . getenv('PATH')));
ok($rc !== 0 && str_contains(state($b5)['reason'] ?? '', 'archive'), 'a failing archive step FAILS the run');

// Retention never removes the last verified set after a failure.
copy("$b/dest/state.json", "$b/dest/state.before-failure.json");
file_put_contents("$b/rel-bbb222-civismedia/app/config.site.php",
    "<?php\nreturn ['db' => ['driver' => 'sqlite', 'sqlite_path' => '/nonexistent/x.sqlite'],\n 'site_slug' => 'civismedia', 'hub_slug' => 'civismedia', 'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];\n");
[$out, $rc] = runBackup($b);
ok($rc !== 0, 'the sabotaged rerun fails');
ok(count(sets($b)) === $goodCount, "and the $goodCount previously verified set(s) are all still there");
ok((state($b)['ok'] ?? true) === false, 'while the state truthfully reports the failure');

/* --- Overlap and stale locks --------------------------------------------------- */

$b6 = layout($fx);
mkdir("$b6/dest/sets", 0700, true);
mkdir("$b6/dest/.lock");
file_put_contents("$b6/dest/.lock/pid", (string) getmypid());   // a LIVE pid
[$out, $rc] = runBackup($b6);
ok($rc !== 0 && str_contains($out, 'in progress'), 'a live concurrent run is refused');
file_put_contents("$b6/dest/.lock/pid", '999999999');            // a dead pid
mkdir("$b6/dest/.staging-stale-run", 0700);
[$out, $rc] = runBackup($b6);
ok($rc === 0, 'a stale lock from a dead run is reclaimed and the backup proceeds');

/* --- Off-site: encrypted, required vs optional --------------------------------- */

$b7 = layout($fx);
$key = "$b7/backup.key";
file_put_contents($key, bin2hex(random_bytes(32)));
chmod($key, 0600);
$off = "$b7/offsite";
mkdir($off);
[$out, $rc] = runBackup($b7, 'PP_BACKUP_KEY_FILE=' . escapeshellarg($key) . ' PP_OFFSITE_CMD=' . escapeshellarg("cp -t $off"));
ok($rc === 0 && (state($b7)['ok'] ?? false) === true, 'off-site transfer succeeds');
$enc = glob("$off/*.tar.enc")[0] ?? '';
ok($enc !== '', 'the transferred artifact is the ENCRYPTED archive');
exec('openssl enc -d -aes-256-cbc -pbkdf2 -pass ' . escapeshellarg("file:$key") . ' -in ' . escapeshellarg($enc) . ' | tar -tf - 2>/dev/null', $members, $drc);
ok($drc === 0 && str_contains(implode("\n", $members), 'manifest.json'), 'and it decrypts with the key file to a full set');

$b8 = layout($fx);
file_put_contents("$b8/k", 'x');
[$out, $rc] = runBackup($b8, 'PP_BACKUP_KEY_FILE=' . escapeshellarg("$b8/k") . ' PP_OFFSITE_CMD=/bin/false PP_OFFSITE_REQUIRED=1');
ok($rc !== 0 && (state($b8)['ok'] ?? true) === false, 'a REQUIRED off-site failure fails the run');
ok(count(sets($b8)) === 1, 'but the local set is preserved, not destroyed');

$b9 = layout($fx);
file_put_contents("$b9/k", 'x');
[$out, $rc] = runBackup($b9, 'PP_BACKUP_KEY_FILE=' . escapeshellarg("$b9/k") . ' PP_OFFSITE_CMD=/bin/false');
$st9 = state($b9);
ok($rc === 0 && ($st9['ok'] ?? false) === true, 'an OPTIONAL off-site failure allows local success');
ok(str_contains($st9['offsite'] ?? '', 'UNVERIFIED'), 'but the state still says off-site recovery is unverified');

/* --- Retention rotates whole sets ---------------------------------------------- */

$b10 = layout($fx);
mkdir("$b10/dest/sets", 0700, true);
for ($i = 1; $i <= 9; $i++) {
    $d = date('Ymd', strtotime("-$i days"));
    $sid = "$d-031700-" . str_pad(dechex($i), 8, '0', STR_PAD_LEFT);
    mkdir("$b10/dest/sets/$sid", 0700, true);
    file_put_contents("$b10/dest/sets/$sid/manifest.json", '{}');
}
[$out, $rc] = runBackup($b10, 'PP_BACKUP_KEEP_DAILY=3 PP_BACKUP_KEEP_WEEKLY=1');
$left = sets($b10);
ok($rc === 0 && count($left) <= 5, 'retention prunes old sets (kept ' . count($left) . ')');
foreach ($left as $s) {
    ok(is_dir("$b10/dest/sets/$s") && is_file("$b10/dest/sets/$s/manifest.json"), "kept set $s is whole");
}

/* --- Cleanup ------------------------------------------------------------------- */
foreach ([$b, $b2, $b3, $b4, $b5, $b6, $b7, $b8, $b9, $b10, $stub] as $d) {
    exec('rm -rf ' . escapeshellarg($d));
}
pp_fixture_destroy($fx);
exit($fails ? 1 : 0);
