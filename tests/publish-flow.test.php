<?php
/**
 * The publish-on-image lane, over real HTTP on a disposable database:
 * a flagged filing waits as an invisible draft, the queue shows it to
 * the right token and not to others, attach-and-publish flips it live
 * in one guarded write and returns the public URL, re-publishing and
 * unflagged stories are refused, and a filing that brings both flag
 * and image publishes immediately.
 *
 * Then the newsroom's side, through a signed-in editor: publishing or
 * scheduling a flagged story by hand consumes the flag, so a story the
 * editor later moves back to draft is not queued again, and an attach
 * from a worklist read while it waited is refused instead of
 * republishing it. A draft save leaves the story waiting, review holds
 * it out of the lane until it returns to draft, and a refused save
 * changes nothing.
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
echo 'engine: ', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), ' server ',
     (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION), "\n";

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

/** The newsroom's side: a browser session with a cookie jar. */
function web(int $port, string $method, string $path, array $fields, string $jar): array
{
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
                            CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
                            CURLOPT_FOLLOWLOCATION => false]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body];
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

/* ---- Step 7: an editor publishes a waiting story by hand ------------------ */
// Publishing by hand is a person deciding, so it spends the flag. When the
// editor later moves the story back to draft, it must not return to the
// image agent's worklist, and an attach call made from a worklist read while
// the story waited must be refused, not republish over the unpublish.
$deskId = (int) $pdo->query("SELECT id FROM categories WHERE slug = 'local-news'")->fetchColumn();
$siteId = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'prairiedispatch'")->fetchColumn();
$pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
    ->execute(['Ed Editor', 'editor-pubflow@test.local', password_hash('correct horse pp', PASSWORD_DEFAULT),
               'editor', 'ed-editor-pubflow', now()]);
$jar = $work . '/jar-editor';
[, $page] = web($port, 'GET', '/admin/login.php', [], $jar);
preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
$csrf = $m[1] ?? '';
[$code] = web($port, 'POST', '/admin/login.php',
    ['csrf' => $csrf, 'email' => 'editor-pubflow@test.local', 'password' => 'correct horse pp'], $jar);
ok($code === 302, "an editor signs in (got $code)");

// The row as the server left it, read through a closed cursor so the
// long-lived handle never pins an old snapshot.
$story = function (int $id) use ($pdo): array {
    $s = $pdo->prepare('SELECT slug, status, awaiting_image, image FROM posts WHERE id = ?');
    $s->execute([$id]);
    $r = $s->fetch() ?: [];
    $s->closeCursor();
    return $r;
};
// The ids on the image agent's worklist, or null when the queue did not
// answer: a broken queue must never pass for one without the story in it.
$queue = function () use ($port, $token): ?array {
    [$code, $q] = api($port, 'GET', '/api/ingest-queue', $token);
    return $code === 200 && ($q['ok'] ?? false) === true
        ? array_map('intval', array_column($q['stories'] ?? [], 'id')) : null;
};
// A ready filing with no art yet: [id, slug], or [0, ''] if it did not land so.
$fileWaiting = function (string $title) use ($port, $token): array {
    [$code, $j] = api($port, 'POST', '/api/ingest', $token, [
        'site' => 'prairiedispatch', 'desk' => 'local-news', 'title' => $title,
        'lede' => 'Approved copy waiting for its art.', 'body' => '<p>Approved body.</p>',
        'publish_on_image' => true,
    ]);
    return $code === 201 && ($j['awaiting_image'] ?? false) === true
        ? [(int) ($j['id'] ?? 0), (string) ($j['slug'] ?? '')] : [0, ''];
};
// The edit form as the editor's browser posts it: the story's own headline
// (so its address stays put), its desk, and the paper it runs on, ticked.
$save = fn (int $id, string $title, array $over) => web($port, 'POST', "/admin/post-edit.php?id=$id", array_merge([
    'csrf' => $csrf, 'title' => $title,
    'lede' => 'Approved copy waiting for its art.', 'body' => '<p>Approved body.</p>',
    'status' => 'draft', 'published_at' => '', 'category_id' => $deskId,
    'byline' => 'Automated report', 'tags' => '', 'sites' => [$siteId],
], $over), $jar);

$t1 = 'Waiting story the editor publishes by hand';
[$s1, $slug1] = $fileWaiting($t1);
ok($s1 > 0 && in_array($s1, $queue() ?? [], true), 'a ready filing without art is on the worklist');

[$code] = $save($s1, $t1, ['status' => 'published']);
$p = $story($s1);
ok($code === 302 && ($p['status'] ?? '') === 'published' && ($p['slug'] ?? '') === $slug1,
   "the editor publishes it by hand, at the address it was filed under (got $code)");
ok((int) ($p['awaiting_image'] ?? -1) === 0, 'publishing by hand consumes the publish-on-image flag');
[$pcode] = api($port, 'GET', '/story/' . $slug1, null);
ok($pcode === 200, "the story is live without its art (got $pcode)");

[$code] = $save($s1, $t1, ['status' => 'draft']);
$p = $story($s1);
ok($code === 302 && ($p['status'] ?? '') === 'draft' && (int) ($p['awaiting_image'] ?? -1) === 0,
   "the editor moves it back to draft, and the flag stays spent (got $code)");
$ids = $queue();
ok($ids !== null && !in_array($s1, $ids, true), 'the unpublished story is not back on the worklist');

[$code, $j2] = api($port, 'POST', '/api/ingest-publish', $token, ['story' => $slug1, 'image' => $art]);
ok($code === 409 && str_contains((string) ($j2['error'] ?? ''), 'only its newsroom can publish it'),
   "an attach from the stale worklist is refused (got $code)");
$p = $story($s1);
ok(($p['status'] ?? '') === 'draft' && ($p['image'] ?? 'x') === '',
   "and the story stays the editor's draft, without the art");
[$pcode] = api($port, 'GET', '/story/' . $slug1, null);
ok($pcode === 404, "it is not public (got $pcode)");

/* ---- Step 8: a draft save keeps it waiting; scheduling spends the flag ---- */
// An edit saved as a draft is not a decision to publish, so the story keeps
// waiting for its art; sent to review, it is held out of the lane until it
// returns to draft. A save the server refuses changes nothing.
// Scheduling is approval for publication just as publishing is, so it spends
// the flag too: pulled back to draft before it runs, the story stays out of
// the lane.
$t2 = 'Waiting story the editor schedules';
[$s2, $slug2] = $fileWaiting($t2);
[$code] = $save($s2, $t2, ['lede' => 'The editor tightened the lede while the art is made.']);
$p = $story($s2);
ok($code === 302 && ($p['status'] ?? '') === 'draft' && (int) ($p['awaiting_image'] ?? -1) === 1
   && in_array($s2, $queue() ?? [], true),
   "an editor's draft save leaves the story waiting on the worklist (got $code)");

[$code] = $save($s2, $t2, ['status' => 'in_review']);
$ids = $queue();
[$acode] = api($port, 'POST', '/api/ingest-publish', $token, ['story' => $slug2, 'image' => $art]);
ok($code === 302 && $ids !== null && !in_array($s2, $ids, true) && $acode === 409
   && ($story($s2)['status'] ?? '') === 'in_review',
   "sent to review, it is held out of the lane: off the worklist, attach refused (got $code/$acode)");
[$code] = $save($s2, $t2, ['status' => 'draft']);
$p = $story($s2);
ok($code === 302 && (int) ($p['awaiting_image'] ?? -1) === 1 && in_array($s2, $queue() ?? [], true),
   "returned to draft, it is waiting on the worklist again (got $code)");

[$code, $page] = $save($s2, $t2, ['status' => 'scheduled', 'published_at' => date('Y-m-d\TH:i', time() - 2 * 86400)]);
$p = $story($s2);
ok($code === 200 && str_contains($page, 'needs a publish time in the future'), "a schedule in the past is refused (got $code)");
ok(($p['status'] ?? '') === 'draft' && (int) ($p['awaiting_image'] ?? -1) === 1 && in_array($s2, $queue() ?? [], true),
   'and the refused save changed nothing: still a waiting draft on the worklist');

[$code] = $save($s2, $t2, ['status' => 'scheduled', 'published_at' => date('Y-m-d\TH:i', time() + 7 * 86400)]);
$p = $story($s2);
ok($code === 302 && ($p['status'] ?? '') === 'scheduled' && (int) ($p['awaiting_image'] ?? -1) === 0,
   "scheduling by hand consumes the flag too (got $code)");

[$code] = $save($s2, $t2, ['status' => 'draft']);
$ids = $queue();
ok($code === 302 && ($story($s2)['status'] ?? '') === 'draft' && $ids !== null && !in_array($s2, $ids, true),
   "pulled back to draft before it ran, it is not back on the worklist (got $code)");
[$code, $j2] = api($port, 'POST', '/api/ingest-publish', $token, ['story' => $slug2, 'image' => $art]);
ok($code === 409 && str_contains((string) ($j2['error'] ?? ''), 'only its newsroom can publish it')
   && ($story($s2)['status'] ?? '') === 'draft',
   "and an attach is refused, leaving it a draft (got $code)");

if ($fails === 0) {
    echo "publish-flow: all checks pass\n";
    exit(0);
}
exit(1);
