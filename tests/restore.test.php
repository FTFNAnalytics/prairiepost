<?php
/**
 * The full isolated restore (F07, step 9.2): a synthetic multi-paper
 * installation is backed up (encrypted, off-site simulated), the source
 * is DESTROYED, and recovery proceeds from the transferred encrypted copy
 * plus declared inputs only — into a fresh database and a fresh
 * filesystem — then the restored application is served and verified
 * functionally AND for its Phase 1 security behavior. Corrupt, incomplete
 * and member-hostile sets are proven to refuse.
 *
 * With PP_TEST_DB=pgsql (the evidence run) the database round-trips
 * through pg_dump/pg_restore into a freshly created PostgreSQL database.
 * On sqlite the same flow runs with the file-based dump.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$t0 = microtime(true);
$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$engine = pp_fixture_engine();
if ($engine === 'mysql') {
    echo "SKIP: the backup/restore path supports the production engines (pgsql) and fixtures (sqlite); run with PP_TEST_DB=pgsql for the evidence run\n";
    exit(0);
}

$fx = pp_fixture_create('restore');
pp_fixture_prepare($fx);
pp_fixture_tool($fx, 'tools/seed-launch.php', 'PP_SITE=civismedia');
pp_fixture_tool($fx, 'tools/seed-launch.php', 'PP_SITE=pickering-post');

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

/* --- Populate the synthetic installation -------------------------------------- */

$pdo = pp_fixture_connect($fx);
exec("printf '%s' 'restore drill passphrase' | PP_CONFIG=" . escapeshellarg($fx['config']) . ' '
    . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/tools/setup-admin.php") . " 'Drill Admin' drill-admin@test.local 2>&1", $o, $rc);
ok($rc === 0, 'founding admin provisioned');
$pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
    ->execute(['Drill Author', 'drill-author@test.local', password_hash('author pass phrase', PASSWORD_DEFAULT), 'author', 'drill-author', date('Y-m-d H:i:s')]);

$siteIds = $pdo->query('SELECT id, slug FROM sites ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
$bySlug = array_flip($siteIds);
// A day in the past: the app compares published_at in ITS configured
// timezone (America/Toronto); writing "now" from a UTC test process left
// stories four hours in the future and invisible.
$now = date('Y-m-d H:i:s', time() - 86400);
$mkpost = function (string $title, string $slug, string $status, ?string $pub, array $sites) use ($pdo, $now): int {
    $pdo->prepare('INSERT INTO posts (title, slug, body, lede, status, author_id, published_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$title, $slug, "<p>Body of $title.</p>", 'Lede.', $status, 2, $pub, $now, $now]);
    $id = (int) $pdo->query($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
        ? "SELECT currval('posts_id_seq')" : 'SELECT last_insert_rowid()')->fetchColumn();
    foreach ($sites as $sid) {
        $pdo->prepare('INSERT INTO post_sites (post_id, site_id) VALUES (?, ?)')->execute([$id, $sid]);
    }
    return $id;
};
$synd  = $mkpost('Syndicated drill story', 'drill-syndicated', 'published', $now, array_values($bySlug));
$draft = $mkpost('Drill draft story', 'drill-draft', 'draft', null, [$bySlug['prairiedispatch']]);
$sched = $mkpost('Drill scheduled story', 'drill-scheduled', 'scheduled', date('Y-m-d H:i:s', time() + 2 * 86400), [$bySlug['prairiedispatch']]);
// The hostile fixture, stored raw the way pre-Phase-1 saves did.
$pdo->prepare('INSERT INTO posts (title, slug, body, lede, status, author_id, published_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
    ->execute(['Audit marker </script><script>document.title=123456789</script>', 'drill-xss',
        '<p><a href="jav&#x61;script:document.title=\'AUDIT_HTML_EXECUTED\'">Audit link</a></p>', 'Lede.', 'published', 2, $now, $now, $now]);
$xss = (int) $pdo->query($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
    ? "SELECT currval('posts_id_seq')" : 'SELECT last_insert_rowid()')->fetchColumn();
$pdo->prepare('INSERT INTO post_sites (post_id, site_id) VALUES (?, ?)')->execute([$xss, $bySlug['prairiedispatch']]);
// Sanitizer cleanup: rewrites the hostile body and snapshots the original.
pp_fixture_tool($fx, 'tools/sanitize-content.php --apply');
// Per-site settings + indexing: enabled for pickering ONLY (disposable fixture).
exec('PP_CONFIG=' . escapeshellarg($fx['config']) . ' ' . escapeshellarg(PHP_BINARY) . ' -r '
    . escapeshellarg("require '$root/app/bootstrap.php';"
        . " set_setting('indexing_enabled', '1', " . (int) $bySlug['pickering-post'] . ');'
        . " set_setting('tagline', 'Drill distinct tagline', " . (int) $bySlug['prairiedispatch'] . ');') . ' 2>&1', $seto, $setrc);
ok($setrc === 0, 'fixture settings written (' . implode(' ', $seto) . ')');
$pdo->prepare("INSERT INTO subscribers (email, status, site_id, created_at) VALUES ('drill-sub@test.local', 'active', ?, ?)")->execute([$bySlug['prairiedispatch'], $now]);
$pdo->prepare("INSERT INTO media_clients (name, token_hash, scopes, enabled, created_at) VALUES ('drill-client', ?, 'request', 1, ?)")->execute([hash('sha256', 'x'), $now]);

/* --- The server layout + a real release artifact ------------------------------- */

$b = sys_get_temp_dir() . '/pp-restore-' . bin2hex(random_bytes(4));
foreach (['vhosts', 'cron', 'dest', 'offsite', 'rel-drill-shared/app', 'uploads/2026'] as $d) {
    mkdir("$b/$d", 0755, true);
}
$photo = random_bytes(2048);
file_put_contents("$b/uploads/2026/drill-photo.jpg", $photo);
$photoHash = hash('sha256', $photo);
symlink("$b/uploads", "$b/rel-drill-shared/uploads");
copy($fx['config'], "$b/rel-drill-shared/app/config.site.php");
file_put_contents("$b/rel-drill-shared/config.php", "<?php\n\$c = require __DIR__ . '/app/config.site.php';\nreturn \$c;\n");
file_put_contents("$b/vhosts/civismedia", "server {\n  root $b/rel-drill-shared;\n}\n");
file_put_contents("$b/cron/drill", "17 3 * * * root true\n");
// Fix the hub-name lookup: config.site.php must claim civismedia for the
// backup job's driver read — it reads db config only, slug is irrelevant;
// but PP_BACKUP_HUB_NAME matches the vhost basename above.

$key = "$b/backup.key";
file_put_contents($key, bin2hex(random_bytes(32)));
chmod($key, 0600);

exec('PP_BACKUP_DEST=' . escapeshellarg("$b/dest")
    . ' PP_BACKUP_VHOSTS_DIR=' . escapeshellarg("$b/vhosts")
    . ' PP_BACKUP_CRON_DIR=' . escapeshellarg("$b/cron")
    . ' PP_BACKUP_ROOT_PREFIX=' . escapeshellarg("$b/rel-")
    . ' PP_BACKUP_KEY_FILE=' . escapeshellarg($key)
    . ' PP_OFFSITE_CMD=' . escapeshellarg("cp -t $b/offsite")
    . ' bash ' . escapeshellarg("$root/tools/backup.sh") . ' 2>&1', $bo, $brc);
ok($brc === 0, "backup of the populated installation succeeds (rc=$brc)\n" . ($brc ? implode("\n", $bo) : ''));
$encFiles = glob("$b/offsite/*.tar.enc");
ok(count($encFiles) === 1, 'exactly one encrypted archive reached the off-site stand-in');

// The release artifact by SHA — the declared code-recovery dependency.
$sha = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD'));
$rw = "$b/recovered-release";
mkdir($rw);
exec('git -C ' . escapeshellarg($root) . ' archive ' . escapeshellarg($sha) . ' | tar -x -C ' . escapeshellarg($rw), $ga, $garc);
ok($garc === 0, 'the release artifact is recoverable by SHA (git archive)');
ok(is_file("$rw/vendor/ezyang/htmlpurifier/library/HTMLPurifier.php"), 'the PINNED SANITIZER ships inside the release artifact');

/* --- DESTROY the source --------------------------------------------------------- */

if ($engine === 'pgsql') {
    $p = $fx['pg'];
    $admin = new PDO("pgsql:host={$p['host']};port={$p['port']};dbname={$p['name']}", $p['user'], $p['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo = null;
    $admin->exec('DROP SCHEMA "' . $fx['schema'] . '" CASCADE');
} else {
    $pdo = null;
    unlink($fx['sqlite_path']);
}
exec('rm -rf ' . escapeshellarg("$b/uploads") . ' ' . escapeshellarg("$b/rel-drill-shared") . ' ' . escapeshellarg("$b/dest/sets"));
ok(!is_dir("$b/dest/sets"), 'the ORIGINAL local sets are gone too — recovery uses the off-site copy alone');

/* --- Recover: decrypt, restore into fresh space --------------------------------- */

$rec = "$b/recovered";
mkdir($rec, 0700);
exec('openssl enc -d -aes-256-cbc -pbkdf2 -pass ' . escapeshellarg("file:$key") . ' -in ' . escapeshellarg($encFiles[0])
    . ' | tar -x -C ' . escapeshellarg($rec), $do, $drc);
ok($drc === 0, 'the encrypted off-site copy decrypts with the documented key');
$setDir = glob("$rec/*")[0] ?? '';
ok($setDir !== '' && is_file("$setDir/manifest.json"), 'a full set came out of the archive');

$drillEnv = 'PP_RESTORE_SET=' . escapeshellarg($setDir)
    . ' PP_RESTORE_TARGET_DIR=' . escapeshellarg("$b/target")
    . ' PP_RESTORE_RESULTS=' . escapeshellarg("$b/results.json");
$freshDb = '';
if ($engine === 'pgsql') {
    $p = $fx['pg'];
    $freshDb = 'pp_restore_' . bin2hex(random_bytes(3));
    $admin->exec("CREATE DATABASE $freshDb");
    $drillEnv .= ' PP_RESTORE_PG_HOST=' . escapeshellarg($p['host'])
        . ' PP_RESTORE_PG_PORT=' . escapeshellarg((string) $p['port'])
        . ' PP_RESTORE_PG_DB=' . escapeshellarg($freshDb)
        . ' PP_RESTORE_PG_USER=' . escapeshellarg($p['user']);
}
exec("$drillEnv bash " . escapeshellarg("$root/tools/restore-drill.sh") . ' 2>&1', $dro, $drrc);
ok($drrc === 0, "the restore drill succeeds (rc=$drrc)\n" . ($drrc ? implode("\n", $dro) : ''));
$results = json_decode((string) @file_get_contents("$b/results.json"), true) ?: [];
ok(($results['ok'] ?? false) === true, 'machine-readable results say ok');
ok(($results['checks']['count_posts'] ?? '') !== '' && !str_contains((string) $results['checks']['count_posts'], '!='),
    'post counts verified exactly against the manifest');

// Recovered original config preserved as EVIDENCE, never loaded.
ok(is_file("$b/target/config/rel-drill-shared/config.php") && is_file("$b/target/config/rel-drill-shared/config.site.php"),
    'both original config layers recovered and preserved separately');

/* --- Serve the restored application from the recovered release ------------------- */

// Documented test mapping: the restored code connects ONLY to the restored
// database — the original schema no longer exists, and this config names
// the fresh target explicitly. No mail, no jobs, no external providers.
$rcfg = "$b/restored-config.php";
if ($engine === 'pgsql') {
    $p = $fx['pg'];
    file_put_contents($rcfg, "<?php\nreturn ['db' => ['driver' => 'pgsql', 'pgsql' => [\n"
        . " 'host' => '{$p['host']}', 'port' => {$p['port']}, 'name' => '$freshDb',\n"
        . " 'user' => '{$p['user']}', 'pass' => '', 'sslmode' => 'disable', 'schema' => '{$fx['schema']}']],\n"
        . " 'site_slug' => 'prairiedispatch', 'hub_slug' => 'civismedia', 'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];\n");
} else {
    file_put_contents($rcfg, "<?php\nreturn ['db' => ['driver' => 'sqlite', 'sqlite_path' => '$b/target/db.dump'],\n"
        . " 'site_slug' => 'prairiedispatch', 'hub_slug' => 'civismedia', 'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];\n");
}
exec('rm -rf ' . escapeshellarg("$rw/uploads"));
symlink("$b/target/uploads", "$rw/uploads");

$restoredUpload = glob("$b/target/uploads/2026/*.jpg")[0] ?? '';
ok($restoredUpload !== '' && hash_file('sha256', $restoredUpload) === $photoHash, 'the restored upload matches its original hash');

$port = 9200 + random_int(0, 90);
$pid = (int) trim((string) shell_exec(
    'cd ' . escapeshellarg($rw) . ' && PP_CONFIG=' . escapeshellarg($rcfg) . ' '
    . escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("$b/server.log") . ' 2>&1 & echo $!'
));
usleep(800000);
register_shutdown_function(function () use ($pid, $b, $fx, $engine, $freshDb) {
    if ($pid) {
        @posix_kill($pid, 15);
    }
    if ($engine === 'pgsql' && $freshDb !== '') {
        try {
            $p = $fx['pg'];
            (new PDO("pgsql:host={$p['host']};port={$p['port']};dbname={$p['name']}", $p['user'], $p['pass']))
                ->exec("DROP DATABASE IF EXISTS $freshDb WITH (FORCE)");
        } catch (Throwable) {
        }
    }
    if (!getenv('PP_RESTORE_KEEP')) {
        exec('rm -rf ' . escapeshellarg($b));
        pp_fixture_destroy($fx);
    } else {
        fwrite(STDERR, "kept: $b\n");
    }
});
function rq(string $path, int $port, string $host = '', array $post = [], string $jar = ''): array
{
    $ch = curl_init("http://127.0.0.1:$port$path");
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HEADER => true];
    if ($host !== '') {
        $opts[CURLOPT_HTTPHEADER] = ["Host: $host"];
    }
    if ($post) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    if ($jar !== '') {
        $opts[CURLOPT_COOKIEJAR] = $jar;
        $opts[CURLOPT_COOKIEFILE] = $jar;
    }
    curl_setopt_array($ch, $opts);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $split = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$code, substr($raw, 0, $split), substr($raw, $split)];
}

[$code, , $body] = rq('/', $port);
ok($code === 200 && str_contains($body, 'Prairie Dispatch'), "the restored front page serves (got $code)");
ok(str_contains($body, 'name="robots" content="noindex'), 'the default paper is still noindex after restore');
ok(str_contains($body, 'Drill distinct tagline') || str_contains($body, 'name="robots"'), 'per-site settings survived');
[$code, , $body] = rq('/', $port, 'pickeringpost.ca');
ok($code === 200 && str_contains($body, 'Pickering'), 'the second paper resolves by its restored domain mapping');
ok(!str_contains($body, 'name="robots" content="noindex'), "pickering's explicit indexing enablement survived — and only its");

[$code, , $body] = rq('/story/drill-syndicated', $port);
ok($code === 200 && str_contains($body, 'Body of Syndicated drill story'), 'a syndicated article body restored intact');
[$code] = rq('/story/drill-draft', $port);
ok($code === 404, 'drafts remain unavailable publicly after restore');
[$code, , $body] = rq('/story/drill-xss', $port);
ok($code === 200 && !str_contains($body, 'AUDIT_HTML_EXECUTED') && !str_contains($body, '123456789</script>'),
    'the stored hostile fixture is still inert in the restored application');

// Login with the synthetic admin.
$jar = "$b/jar";
[, , $login] = rq('/admin/login.php', $port, '', [], $jar);
preg_match('/name="csrf" value="([0-9a-f]+)"/', $login, $m);
[$code] = rq('/admin/login.php', $port, '', ['csrf' => $m[1] ?? '', 'email' => 'drill-admin@test.local', 'password' => 'restore drill passphrase'], $jar);
ok($code === 302, "the recovered admin signs in with the known passphrase (got $code)");

// Recovered users PREVENT a second founding.
exec("printf '%s' 'a different passphrase!' | PP_CONFIG=" . escapeshellarg($rcfg) . ' '
    . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$rw/tools/setup-admin.php") . " 'Eve After' eve@after.test 2>&1", $so, $src);
ok($src !== 0, 'a founding-admin attempt on the restored installation is refused');

// Author restrictions still hold on restored data (the atomic guard).
$probe = 'PP_CONFIG=' . escapeshellarg($rcfg) . ' ' . escapeshellarg(PHP_BINARY) . ' -r '
    . escapeshellarg('require "' . $rw . '/app/bootstrap.php";
        var_export(pp_guarded_post_update(' . $synd . ', ["title" => "STALE OVERWRITE"], ["draft", "in_review"]));
        echo "|"; $s = db()->prepare("SELECT title, status FROM posts WHERE id = ?"); $s->execute([' . $synd . ']); $r = $s->fetch();
        echo $r["title"], "|", $r["status"];');
$out = (string) shell_exec($probe . ' 2>&1');
ok(str_starts_with($out, 'false|Syndicated drill story|published'), "author-state guard holds on restored data ($out)");

// Scheduled content, revisions (incl. the sanitizer snapshot), journal.
$check = 'PP_CONFIG=' . escapeshellarg($rcfg) . ' ' . escapeshellarg(PHP_BINARY) . ' -r '
    . escapeshellarg('require "' . $rw . '/app/bootstrap.php";
        $p = db();
        echo "sched=", $p->query("SELECT COUNT(*) FROM posts WHERE status = \'scheduled\'")->fetchColumn();
        echo " sanrev=", $p->query("SELECT COUNT(*) FROM post_revisions WHERE reason = \'sanitize\'")->fetchColumn();
        echo " journal=", $p->query("SELECT COUNT(*) FROM schema_migrations")->fetchColumn();
        echo " mediaclients=", $p->query("SELECT COUNT(*) FROM media_clients")->fetchColumn();');
$out = (string) shell_exec($check . ' 2>&1');
ok(preg_match('/sched=1 sanrev=[1-9]\d* journal=[1-9]\d* mediaclients=1/', $out) === 1,
    "scheduled post, sanitize revision, migration journal and media rows all restored ($out)");

/* --- Corrupt / incomplete / hostile sets refuse ---------------------------------- */

$c1 = "$b/set-incomplete";
exec('cp -rp ' . escapeshellarg($setDir) . ' ' . escapeshellarg($c1));
unlink("$c1/uploads.tar.gz");
exec('PP_RESTORE_SET=' . escapeshellarg($c1) . ' PP_RESTORE_TARGET_DIR=' . escapeshellarg("$b/t1") . ' bash ' . escapeshellarg("$root/tools/restore-drill.sh") . ' 2>&1', $o1, $r1);
ok($r1 !== 0 && !is_dir("$b/t1"), 'an INCOMPLETE set (missing uploads) is refused before anything is written');

$c2 = "$b/set-corrupt";
exec('cp -rp ' . escapeshellarg($setDir) . ' ' . escapeshellarg($c2));
$fh = fopen("$c2/db.dump", 'r+');
fseek($fh, 64);
fwrite($fh, 'CORRUPTED');
fclose($fh);
exec('PP_RESTORE_SET=' . escapeshellarg($c2) . ' PP_RESTORE_TARGET_DIR=' . escapeshellarg("$b/t2") . ' bash ' . escapeshellarg("$root/tools/restore-drill.sh") . ' 2>&1', $o2, $r2);
ok($r2 !== 0 && str_contains(implode(' ', $o2), 'checksum'), 'a CORRUPT dump is refused by checksum, not discovered mid-restore');

$c3 = "$b/set-hostile";
exec('cp -rp ' . escapeshellarg($setDir) . ' ' . escapeshellarg($c3));
$evilSrc = "$b/evil";
mkdir($evilSrc);
file_put_contents("$evilSrc/x", 'x');
exec('tar -C ' . escapeshellarg($evilSrc) . ' -czf ' . escapeshellarg("$c3/uploads.tar.gz") . ' --transform "s|^x|../../escaped-file|" x');
// Regenerate the manifest hashes so ONLY member validation stands between
// the hostile archive and extraction.
$man = json_decode((string) file_get_contents("$c3/manifest.json"), true);
$man['files']['uploads.tar.gz'] = ['bytes' => filesize("$c3/uploads.tar.gz"), 'sha256' => hash_file('sha256', "$c3/uploads.tar.gz"), 'mode' => '0600'];
file_put_contents("$c3/manifest.json", json_encode($man));
exec('PP_RESTORE_SET=' . escapeshellarg($c3) . ' PP_RESTORE_TARGET_DIR=' . escapeshellarg("$b/t3") . ' bash ' . escapeshellarg("$root/tools/restore-drill.sh") . ' 2>&1', $o3, $r3);
ok($r3 !== 0 && str_contains(implode(' ', $o3), 'refused'), 'a path-traversal archive member is refused before extraction');
ok(!file_exists("$b/escaped-file") && !file_exists(dirname($b) . '/escaped-file'), 'and nothing escaped');

// A non-empty target refuses (harmless synthetic stand-in for "production").
mkdir("$b/t4");
file_put_contents("$b/t4/existing-data", 'precious');
exec('PP_RESTORE_SET=' . escapeshellarg($setDir) . ' PP_RESTORE_TARGET_DIR=' . escapeshellarg("$b/t4") . ' bash ' . escapeshellarg("$root/tools/restore-drill.sh") . ' 2>&1', $o4, $r4);
ok($r4 !== 0 && file_get_contents("$b/t4/existing-data") === 'precious', 'an existing non-empty target is refused untouched');

printf("elapsed: %.1fs for this synthetic fixture (NOT a production recovery estimate)\n", microtime(true) - $t0);
exit($fails ? 1 : 0);
