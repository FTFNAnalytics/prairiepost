<?php
/**
 * The stored-content cleanup tool (tools/sanitize-content.php), end to end
 * on a disposable file database: dry run reports without writing, apply
 * rewrites with a recoverable history snapshot, and a second run finds
 * nothing left to do. Exercises the F01 historical-content path.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('cleanup');
pp_fixture_prepare($fx);            // migrate --apply + seed-core, explicitly
$work = $fx['work'];
$config = $fx['config'];

$root = dirname(__DIR__);
$php = escapeshellarg(PHP_BINARY);
$env = 'PP_CONFIG=' . escapeshellarg($config) . ' ';
$run = function (string $cmd) use ($env, $php, $root): array {
    exec($env . $php . ' ' . $cmd . ' 2>&1', $lines, $exit);
    return [implode("\n", $lines), $exit];
};

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

// Boot the app once (the fixture is already prepared), then plant
// a deliberately dirty historical body the way pre-fix saves stored it.
[$out, $exit] = $run('-r ' . escapeshellarg(
    'require ' . var_export($root . '/app/bootstrap.php', true) . ';
     $dirty = \'<p>Real prose stays.</p><p><a href="jav&#x61;script:document.title=1">bad link</a></p><img src=x onerror=alert(1)>\';
     db()->prepare("INSERT INTO posts (title, slug, body, status, author_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?)")
         ->execute(["Dirty fixture", "dirty-fixture-" . bin2hex(random_bytes(3)), $dirty, "published", 1, now(), now()]);
     echo "planted:", pp_last_id("posts");'
));
ok($exit === 0 && str_contains($out, 'planted:'), "fixture planted ($out)");
preg_match('/planted:(\d+)/', $out, $m);
$dirtyId = (int) ($m[1] ?? 0);
ok($dirtyId > 0, 'fixture id known');

// Dry run: reports the dirty story, writes nothing.
[$dry] = $run(escapeshellarg($root . '/tools/sanitize-content.php'));
ok(str_contains($dry, 'DRY RUN'), 'dry run announces itself');
ok(str_contains($dry, "#$dirtyId"), 'dry run reports the dirty story');
[$check] = $run('-r ' . escapeshellarg(
    'require ' . var_export($root . '/app/bootstrap.php', true) . ';
     $s = db()->prepare("SELECT body FROM posts WHERE id = ?"); $s->execute([' . $dirtyId . ']);
     echo str_contains((string) $s->fetchColumn(), "onerror") ? "STILL-DIRTY" : "CLEANED";'
));
ok(str_contains($check, 'STILL-DIRTY'), 'dry run wrote nothing');

// Apply: rewrites, snapshots the original, and the body comes out clean.
[$applyOut] = $run(escapeshellarg($root . '/tools/sanitize-content.php') . ' --apply --batch=50');
ok(str_contains($applyOut, "#$dirtyId"), 'apply run rewrote the dirty story');
[$after] = $run('-r ' . escapeshellarg(
    'require ' . var_export($root . '/app/bootstrap.php', true) . ';
     $s = db()->prepare("SELECT body FROM posts WHERE id = ?"); $s->execute([' . $dirtyId . ']);
     $body = (string) $s->fetchColumn();
     $r = db()->prepare("SELECT COUNT(*) FROM post_revisions WHERE post_id = ? AND reason = ?"); $r->execute([' . $dirtyId . ', "sanitize"]);
     $revs = (int) $r->fetchColumn();
     $o = db()->prepare("SELECT body FROM post_revisions WHERE post_id = ? AND reason = ? ORDER BY id LIMIT 1"); $o->execute([' . $dirtyId . ', "sanitize"]);
     $orig = (string) $o->fetchColumn();
     echo (stripos($body, "onerror") === false && stripos($body, "javascript") === false ? "CLEAN " : "DIRTY "),
          (str_contains($body, "Real prose stays.") ? "PROSE-KEPT " : "PROSE-LOST "),
          "revs=$revs ",
          (str_contains($orig, "onerror") ? "ORIGINAL-RECOVERABLE" : "ORIGINAL-MISSING");'
));
ok(str_contains($after, 'CLEAN'), 'body is clean after apply');
ok(str_contains($after, 'PROSE-KEPT'), 'legitimate prose survived the rewrite');
ok(str_contains($after, 'revs=1'), 'exactly one history snapshot per rewrite');
ok(str_contains($after, 'ORIGINAL-RECOVERABLE'), 'the pre-rewrite original is recoverable from history');

// Second apply run: idempotent — the fixture does not appear again.
[$again] = $run(escapeshellarg($root . '/tools/sanitize-content.php'));
ok(!str_contains($again, "#$dirtyId"), 'second run finds the story already clean');

// Cleanup the disposable workspace.
pp_fixture_destroy($fx);

exit($fails ? 1 : 0);
