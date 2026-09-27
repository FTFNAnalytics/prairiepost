<?php
/**
 * Courier, the uploading agent, end to end against a real dev server on
 * a disposable database: a bundle with a featured PNG lands as a draft
 * with the image attached and gains a receipt; a re-run skips on the
 * receipt; --resend hits the server's dedupe instead of duplicating; a
 * defective bundle fails alone while the batch continues; --dry-run
 * sends nothing. Skips cleanly where python3 is not installed.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$python = trim((string) shell_exec('command -v python3 2>/dev/null'));
if ($python === '') {
    echo "SKIP courier: python3 is not installed here\n";
    exit(0);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('courier');
pp_fixture_prepare($fx);
$work = $fx['work'];
$config = $fx['config'];

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

putenv('PP_CONFIG=' . $config);
define('PP_TEST_BOOT', 1);
require $root . '/app/bootstrap.php';
$pdo = db();

$pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute(['Local News', 'local-news']);
$token = 'hermes_' . bin2hex(random_bytes(28));
$pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, created_at) VALUES (?,?,?,?,1,?)')
    ->execute(['courier-test', hash('sha256', $token), 'prairiedispatch', '', now()]);

$port = 8600 + random_int(0, 80);
$cmd = 'PP_CONFIG=' . escapeshellarg($config) . ' ' . escapeshellarg(PHP_BINARY)
     . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("$work/server.log") . ' 2>&1 & echo $!';
$serverPid = (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && ' . $cmd));
usleep(700000);
$storedFiles = [];
register_shutdown_function(function () use ($serverPid, $fx, &$storedFiles, $root) {
    if ($serverPid) {
        @posix_kill($serverPid, 15);
    }
    foreach ($storedFiles as $f) {
        @unlink($root . $f);
    }
    pp_fixture_destroy($fx);
});

/* ---- Bundles: one good (with image), one defective (no lede) ------------ */
$ready = $work . '/ready';
mkdir("$ready/first-council-story", 0777, true);
mkdir("$ready/broken-bundle", 0777, true);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
file_put_contents("$ready/first-council-story/featured.png", $png);
file_put_contents("$ready/first-council-story/story.md", <<<MD
---
site: prairiedispatch
desk: local-news
title: Courier end-to-end test story
lede: A lede approved by a person before Courier saw it.
dateline: TESTVILLE
tags: courier, test
image_caption: The caption rides along.
image_credit: Test bench
source: https://example.test/record | The record
---
First paragraph of plain text, which Courier escapes & wraps.

Second paragraph after a blank line.
MD);
file_put_contents("$ready/broken-bundle/story.md", "---\nsite: prairiedispatch\ndesk: local-news\ntitle: No lede here\n---\nBody.\n");

$run = function (string $args) use ($python, $root, $token, $port, $work): array {
    $env = 'PP_INGEST_KEY=' . escapeshellarg($token) . ' PP_INGEST_BASE=' . escapeshellarg('http://127.0.0.1:' . $port);
    exec("cd " . escapeshellarg($root) . " && $env " . escapeshellarg($python)
       . " tools/courier.py $args 2>&1", $out, $code);
    return [$code, implode("\n", $out)];
};

/* ---- Dry run validates and sends nothing --------------------------------- */
[$code, $out] = $run('--dry-run ' . escapeshellarg("$ready/first-council-story"));
ok($code === 0 && str_contains($out, 'dry run'), "dry run passes on the good bundle (exit $code)");
ok((int) $pdo->query('SELECT COUNT(*) FROM posts WHERE filed_by = ' . $pdo->quote('courier-test'))->fetchColumn() === 0,
   'dry run filed nothing');

/* ---- The batch: good bundle lands, broken bundle fails alone ------------- */
[$code, $out] = $run('--all ' . escapeshellarg($ready));
ok($code === 1, "the batch exits 1 for exactly one failed bundle (exit $code)");
ok(str_contains($out, 'filed as draft'), 'the good bundle filed as a draft');
ok(str_contains($out, 'broken-bundle: FAILED') && str_contains($out, 'lede'), 'the broken bundle names its defect');
ok(is_file("$ready/first-council-story/receipt.json"), 'the good bundle gained a receipt');
ok(!is_file("$ready/broken-bundle/receipt.json"), 'the broken bundle gained none');
ok(!str_contains($out, substr($token, 10, 20)), 'the key never appears in output');

$receipt = (array) json_decode((string) file_get_contents("$ready/first-council-story/receipt.json"), true);
$row = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
$row->execute([(int) ($receipt['id'] ?? 0)]);
$p = $row->fetch();
// Close the cursor NOW: under WAL an open cursor pins this connection to
// a read snapshot, and the UPDATE below would fail SQLITE_BUSY_SNAPSHOT
// once the dev server has written anything since.
$row->closeCursor();
ok($p && $p['status'] === 'draft', 'the receipt names a draft row');
ok($p && str_starts_with((string) $p['image'], '/uploads/') && is_file($root . $p['image']),
   'the draft carries the uploaded featured image');
ok($p && $p['image_caption'] === 'The caption rides along.', 'the caption rode along');
ok($p && str_contains((string) $p['body'], 'escapes &amp; wraps') && str_contains((string) $p['body'], '<p>'),
   'plain text became escaped paragraphs');
if ($p) {
    $storedFiles[] = (string) $p['image'];
}

/* ---- Idempotence: receipt skip, then server dedupe on --resend ------------ */
[$code, $out] = $run(escapeshellarg("$ready/first-council-story"));
ok($code === 0 && str_contains($out, 'skipped'), "a re-run skips on the receipt (exit $code)");
[$code, $out] = $run('--resend ' . escapeshellarg("$ready/first-council-story"));
ok($code === 0 && str_contains($out, 'dedupe'), "--resend hits the server dedupe, no duplicate (exit $code)");
$n = (int) $pdo->query('SELECT COUNT(*) FROM posts WHERE filed_by = ' . $pdo->quote('courier-test'))->fetchColumn();
ok($n === 1, "exactly one story exists after three runs (got $n)");

/* ---- A revoked key fails plainly ------------------------------------------ */
$pdo->exec('UPDATE ingest_agents SET enabled = 0');
@unlink("$ready/first-council-story/receipt.json");
[$code, $out] = $run(escapeshellarg("$ready/first-council-story"));
ok($code === 1 && str_contains($out, 'revoked'), "a revoked key fails the bundle with the server's reason (exit $code)");

if ($fails === 0) {
    echo "courier: all checks pass\n";
    exit(0);
}
exit(1);
