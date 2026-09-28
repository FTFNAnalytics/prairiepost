<?php
/**
 * The publish-on-image lane, over real HTTP on a disposable database:
 * a flagged filing waits as an invisible draft, the queue shows it to
 * the right token and not to others, attach-and-publish flips it live
 * in one guarded write and returns the public URL, re-publishing and
 * unflagged stories are refused, and a filing that brings both flag
 * and image publishes immediately.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('pubflow');
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
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')
    ->execute(['paper.test', 'prairiedispatch', now()]);
$pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?,?,?)')
    ->execute(['Other Paper', 'other-paper', now()]);

$token = 'hermes_' . bin2hex(random_bytes(28));
$pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, created_at) VALUES (?,?,?,?,1,?)')
    ->execute(['pubflow-writer', hash('sha256', $token), 'prairiedispatch', '', now()]);
$otherToken = 'hermes_' . bin2hex(random_bytes(28));
$pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, created_at) VALUES (?,?,?,?,1,?)')
    ->execute(['pubflow-other', hash('sha256', $otherToken), 'other-paper', '', now()]);

$port = 8700 + random_int(0, 80);
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

function api(int $port, string $method, string $path, ?string $token, $body = null, string $ctype = 'application/json'): array
{
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    $headers = $token !== null ? ['Authorization: Bearer ' . $token] : [];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
                            CURLOPT_CUSTOMREQUEST => $method]);
    if ($body !== null) {
        $headers[] = 'Content-Type: ' . $ctype;
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $out = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, (array) json_decode($out, true)];
}

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

/* ---- Step 1: the text agent files approved copy, flagged ready ------------ */
[$code, $j] = api($port, 'POST', '/api/ingest', $token, [
    'site' => 'prairiedispatch', 'desk' => 'local-news',
    'title' => 'Publish-on-image flow test story',
    'lede' => 'Approved copy that waits only for its art.',
    'body' => '<p>The whole body, already approved by a person.</p>',
    'publish_on_image' => true,
]);
ok($code === 201 && ($j['status'] ?? '') === 'draft' && ($j['awaiting_image'] ?? false) === true,
   "a flagged filing lands as a draft awaiting its image (got $code/" . ($j['status'] ?? '?') . ')');
$slug = (string) ($j['slug'] ?? '');

[$pcode] = api($port, 'GET', '/story/' . $slug, null);
ok($pcode === 404, "the waiting draft is publicly invisible (got $pcode)");

/* ---- Step 2: the image agent reads its queue ------------------------------ */
[$code, $j] = api($port, 'GET', '/api/ingest-queue', $token);
ok($code === 200 && count($j['stories'] ?? []) === 1 && $j['stories'][0]['slug'] === $slug
   && $j['stories'][0]['title'] === 'Publish-on-image flow test story',
   "the queue lists the waiting story with its headline (got $code)");
[$code, $j2] = api($port, 'GET', '/api/ingest-queue', $otherToken);
ok($code === 200 && ($j2['stories'] ?? ['x']) === [], 'a token scoped elsewhere sees an empty queue');
[$code] = api($port, 'GET', '/api/ingest-queue', null);
ok($code === 401, "the queue fails closed without a token (got $code)");

/* ---- Step 3: refusals before the happy path ------------------------------- */
[$code, $j2] = api($port, 'POST', '/api/ingest-publish', $token,
    ['story' => $slug, 'image' => '/uploads/2099/01/never-uploaded.png']);
ok($code === 422, "publishing with an image that was never uploaded → 422 (got $code)");
// Scope refusal must not depend on the image argument being real, so
// check it AFTER a real upload exists (see step 4's cross-check below).

// An ordinary editor draft (no flag) must be untouchable from this lane.
[$code, $j2] = api($port, 'POST', '/api/ingest', $token, [
    'site' => 'prairiedispatch', 'desk' => 'local-news',
    'title' => 'Ordinary unflagged draft', 'lede' => 'Not cleared for auto-publish.',
    'body' => '<p>Body.</p>',
]);
$plainSlug = (string) ($j2['slug'] ?? '');

/* ---- Step 4: upload the art, attach, publish ------------------------------ */
[$code, $j2] = api($port, 'POST', '/api/ingest-media', $token, $png, 'image/png');
ok($code === 201, "the image agent uploads the featured art (got $code)");
$art = (string) ($j2['path'] ?? '');
$storedFiles[] = $art;

[$code, $j2] = api($port, 'POST', '/api/ingest-publish', $otherToken, ['story' => $slug, 'image' => $art]);
ok($code === 403, "another paper's token cannot publish it (got $code)");

[$code, $j2] = api($port, 'POST', '/api/ingest-publish', $token,
    ['story' => $slug, 'image' => $art, 'image_caption' => 'Generated art', 'image_credit' => 'Image desk']);
ok($code === 200 && ($j2['status'] ?? '') === 'published', "attach-and-publish answers published (got $code)");
ok(($j2['url'] ?? '') === 'https://paper.test/story/' . $slug, 'the response carries the public URL (got ' . ($j2['url'] ?? '?') . ')');

$row = $pdo->prepare('SELECT status, awaiting_image, image, image_caption, published_at FROM posts WHERE slug = ?');
$row->execute([$slug]);
$p = $row->fetch();
$row->closeCursor();
ok($p && $p['status'] === 'published' && (int) $p['awaiting_image'] === 0
   && $p['image'] === $art && $p['image_caption'] === 'Generated art' && $p['published_at'] !== null,
   'the row is published with the art, caption and timestamp');

[$pcode] = api($port, 'GET', '/story/' . $slug, null);
ok($pcode === 200, "the story is publicly live after the attach (got $pcode)");

/* ---- Step 5: the lane is closed behind it --------------------------------- */
[$code, $j2] = api($port, 'GET', '/api/ingest-queue', $token);
ok($code === 200 && ($j2['stories'] ?? ['x']) === [], 'the queue is empty again');
[$code, $j2] = api($port, 'POST', '/api/ingest-publish', $token, ['story' => $slug, 'image' => $art]);
ok($code === 409 && str_contains((string) ($j2['error'] ?? ''), 'already published'),
   "a second publish is refused (got $code)");
[$code, $j2] = api($port, 'POST', '/api/ingest-publish', $token, ['story' => $plainSlug, 'image' => $art]);
ok($code === 409 && str_contains((string) ($j2['error'] ?? ''), 'newsroom'),
   "an unflagged draft cannot be published from this lane (got $code)");

/* ---- Step 6: flag + image in ONE filing publishes immediately ------------- */
[$code, $j2] = api($port, 'POST', '/api/ingest', $token, [
    'site' => 'prairiedispatch', 'desk' => 'local-news',
    'title' => 'Flagged filing that brings its own art',
    'lede' => 'Approved copy arriving art-in-hand.',
    'body' => '<p>Body.</p>', 'publish_on_image' => true, 'image' => $art,
]);
ok($code === 201 && ($j2['status'] ?? '') === 'published'
   && ($j2['url'] ?? '') === 'https://paper.test/story/' . ($j2['slug'] ?? ''),
   "flag + image in one filing publishes immediately with its URL (got $code/" . ($j2['status'] ?? '?') . ')');

if ($fails === 0) {
    echo "publish-flow: all checks pass\n";
    exit(0);
}
exit(1);
