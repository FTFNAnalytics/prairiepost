<?php
/**
 * The article-editing API end to end, over real HTTP on a disposable
 * database: PATCH /api/ingest/drafts/{ref} and POST /api/ingest/update,
 * the shared engine behind them (app/ingest-edit.php), and the upload
 * limit an agent meets on the way (POST /api/ingest-media).
 *
 * It walks the owner's acceptance workflow, step for step:
 *
 *   0. media: a 1.6 MB PNG — the size nginx's 1 MB default refused on
 *      Kermode — uploads; an oversized one is a JSON 413 naming the limit
 *      this server really enforces;
 *   1. each lane is its own grant, refused before anything is read;
 *   2. DRAFTS: a scoped key finds an EDITOR-created draft through the
 *      reader, uploads a cover, attaches it, replaces it (the old alt text,
 *      caption and credit are not inherited), edits text, tags, sources and
 *      SEO, removes the cover — and reads it back each time as the same
 *      unpublished draft: same id, slug, author, status, no public page;
 *      the publish-on-image flag is reported and never touched;
 *   3. retries: resending a mutation that already landed is 200 unchanged
 *      and writes nothing; a stale version, a missing one, or a status
 *      change is refused and writes nothing;
 *   4. no field publishes, schedules, renames, re-papers or moves a draft;
 *   5. scope: wrong paper, wrong hostname, wrong desk, another paper's key,
 *      a story another paper carries;
 *   6. PUBLISHED: replace the cover, edit text and metadata, move desks —
 *      still published, at the same URL, with the same publication date;
 *      the revisions are recorded and an editor's restore brings back the
 *      old picture with its own alt text and credit;
 *   7. the edit, its revisions and its audit row are one transaction: when
 *      the audit row cannot be written, nothing is (SQLite fixture);
 *   8. the hourly limit.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('ingestedit');
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
require $root . '/app/ingest.php';
require $root . '/app/ingest-edit.php';
$pdo = db();
$engine = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "engine: $engine ({$fx['id']})\n";

/* ---- Fixtures --------------------------------------------------------------- */
foreach ([['Local News', 'local-news'], ['Environment', 'environment'], ['Sports', 'sports']] as [$n, $s]) {
    $pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute([$n, $s]);
}
// Fresh papers of their own: the core seed fills prairiedispatch with demo
// stories, and these assertions are exact.
$pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?,?,?)')->execute(['Paper A', 'paper-a', now()]);
$pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?,?,?)')->execute(['Other Paper', 'other-paper', now()]);
$siteA = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'paper-a'")->fetchColumn();
$siteB = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'other-paper'")->fetchColumn();
$desk = [];
foreach (['local-news', 'environment', 'sports'] as $s) {
    $desk[$s] = (int) $pdo->query("SELECT id FROM categories WHERE slug = '$s'")->fetchColumn();
}
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')->execute(['paper-a.test', 'paper-a', now()]);
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')->execute(['paper-b.test', 'other-paper', now()]);
$editorEmail = 'editor-edit@test.local';
$pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
    ->execute(['Edie Editor', $editorEmail, password_hash('correct horse pp', PASSWORD_DEFAULT), 'editor', 'edie-editor', now()]);
$editorId = (int) pp_last_id('users');

$mint = function (string $name, string $sites, string $desks, int $update, int $edit) use ($pdo): string {
    $t = 'hermes_' . bin2hex(random_bytes(28));
    $pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, draft_only, lookup_scope, update_published, read_scope, edit_drafts, created_at)
        VALUES (?,?,?,?,1,1,?,?,?,?,?)')
        ->execute([$name, hash('sha256', $t), $sites, $desks, 'all', $update, 'all', $edit, now()]);
    return $t;
};
// The owner's configuration: draft-only (never publishes anything new),
// reads everything, edits drafts AND live stories, held to one paper.
$agent    = $mint('edit-agent', 'paper-a', '', 1, 1);
$noEdit   = $mint('edit-no-draft-grant', 'paper-a', '', 1, 0);
$noUpdate = $mint('edit-no-update-grant', 'paper-a', '', 0, 1);
$deskHeld = $mint('edit-desk-held', 'paper-a', 'environment', 1, 1);
$bKey     = $mint('edit-paper-b', 'other-paper', '', 1, 1);

$n = 0;
$mkPost = function (array $sites, string $deskSlug, string $title, string $status, array $over = []) use ($pdo, $editorId, $desk, &$n): int {
    $n++;
    $pdo->prepare('INSERT INTO posts (title, slug, category_id, author_id, byline, dateline, lede, body, meta_description,
                                      image, image_alt, image_caption, image_credit, correction, post_type, origin, status,
                                      awaiting_image, filed_by, published_at, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$title, (string) ($over['slug'] ?? 'edit-fixture-' . $n), $desk[$deskSlug], $editorId, 'Staff Writer', 'PRINCE RUPERT',
            'The original lede ' . $n . '.', '<p>The original body ' . $n . '.</p>', (string) ($over['meta'] ?? ''),
            (string) ($over['image'] ?? ''), (string) ($over['image_alt'] ?? ''), (string) ($over['image_caption'] ?? ''),
            (string) ($over['image_credit'] ?? ''), (string) ($over['correction'] ?? ''), 'story', (string) ($over['origin'] ?? ''),
            $status, (int) ($over['awaiting_image'] ?? 0), (string) ($over['filed_by'] ?? ''),
            $status === 'published' ? '2026-11-20 12:00:00' : null, '2026-11-20 11:00:00', '2026-11-20 12:00:00']);
    $id = (int) pp_last_id('posts');
    foreach ($sites as $sid) {
        $pdo->prepare('INSERT INTO post_sites (post_id, site_id) VALUES (?,?)')->execute([$id, $sid]);
    }
    return $id;
};

$port = 9500 + random_int(0, 80);
// PHP's request limits as production sets them in the release's .user.ini
// (which php -S does not read) — except upload_max_filesize, held BELOW the
// app's 8 MB so section 0 can show a 413 naming PHP's lower limit. Every
// oversized body stays under post_max_size: a body over it makes PHP warn
// at request startup, and on a PHP with Xdebug loaded (the CI runner's)
// that took the whole test server down.
const PP_TEST_POST_MAX = 12 * 1048576;
const PP_TEST_UPLOAD_MAX = 4 * 1048576;
$cmd = 'PP_CONFIG=' . escapeshellarg($config) . ' ' . escapeshellarg(PHP_BINARY)
     . ' -d post_max_size=12M -d upload_max_filesize=4M'
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

function api(string $method, string $path, ?string $token, $body = null, string $host = 'paper-a.test', ?string $ctype = 'application/json'): array
{
    $ch = curl_init('http://127.0.0.1:' . $GLOBALS['port'] . $path);
    $headers = ['Host: ' . $host];
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    if ($body !== null) {
        if ($ctype !== null) {
            $headers[] = 'Content-Type: ' . $ctype;
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) || is_array($body) && $ctype === null ? $body : json_encode($body));
    }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
                            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, json_decode($raw, true), $raw];
}
function fresh(): PDO
{
    global $config, $pdo;
    $cfg = require $config;
    if (($cfg['db']['driver'] ?? '') !== 'sqlite') {
        return $pdo;
    }
    $p = new PDO('sqlite:' . $cfg['db']['sqlite_path']);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $p;
}
function row(int $id): array
{
    $s = fresh()->prepare('SELECT * FROM posts WHERE id = ?');
    $s->execute([$id]);
    $r = $s->fetch() ?: [];
    $s->closeCursor();
    return $r;
}
function scalar(string $sql, array $args = []): int
{
    $s = fresh()->prepare($sql);
    $s->execute($args);
    $r = $s->fetch(PDO::FETCH_NUM);
    $s->closeCursor();
    return $r ? (int) $r[0] : 0;
}
function revisions(int $id): array
{
    $s = fresh()->prepare('SELECT id, title, image, image_alt, image_caption, image_credit, meta_description, saved_by, reason FROM post_revisions WHERE post_id = ? ORDER BY id');
    $s->execute([$id]);
    $r = $s->fetchAll();
    $s->closeCursor();
    return $r;
}
function audits(string $action): array
{
    $s = fresh()->prepare('SELECT site_id, user_name, target, detail FROM audit_log WHERE action = ? ORDER BY id');
    $s->execute([$action]);
    $r = $s->fetchAll();
    $s->closeCursor();
    return $r;
}
function ver(int $id): string
{
    return (string) (pp_ingest_edit_load(fresh(), $id)['version'] ?? '');
}
$refOf = fn (int $id, int $site = 0): string => pp_ingest_article_ref($site ?: $siteA, $id, pp_ingest_ref_key());
$read = fn (string $token, string $query, string $host = 'paper-a.test'): array => api('GET', '/api/ingest/stories?' . $query, $token, null, $host);
$patch = fn (string $token, string $ref, array $body, string $host = 'paper-a.test'): array
    => api('PATCH', '/api/ingest/drafts/' . $ref, $token, $body + ['site' => 'paper-a', 'desk' => 'local-news'], $host);
$update = fn (string $token, array $body, string $host = 'paper-a.test'): array
    => api('POST', '/api/ingest/update', $token, $body + ['site' => 'paper-a', 'desk' => 'local-news'], $host);

/* ---- 0. Media: the real size limit ---------------------------------------------- */
// A 1.6 MB PNG of noise (noise does not compress): the size Kermode's nginx
// refused at its 1 MB default before PHP saw it. The app takes it.
$img = imagecreatetruecolor(740, 740);
mt_srand(7);
for ($y = 0; $y < 740; $y++) {
    for ($x = 0; $x < 740; $x++) {
        imagesetpixel($img, $x, $y, mt_rand(0, 0xFFFFFF));
    }
}
ob_start();
imagepng($img, null, 0);
$bigPng = (string) ob_get_clean();
imagedestroy($img);
ok(strlen($bigPng) > 1_500_000 && strlen($bigPng) < 2_000_000, '0. setup: a ~1.6 MB PNG (' . strlen($bigPng) . ' bytes)');
[$code, $j] = api('POST', '/api/ingest-media?name=big-cover', $agent, $bigPng, 'paper-a.test', 'image/png');
ok($code === 201 && ($j['bytes'] ?? 0) === strlen($bigPng), "0. a 1.6 MB PNG as the raw body: 201 (got $code " . json_encode($j) . ')');
$bigPath = (string) ($j['path'] ?? '');
$storedFiles[] = $bigPath;
$tmp = $work . '/big.png';
file_put_contents($tmp, $bigPng);
[$code, $j] = api('POST', '/api/ingest-media', $agent, ['file' => new CURLFile($tmp, 'image/png', 'big.png')], 'paper-a.test', null);
ok($code === 201 && ($j['bytes'] ?? 0) === strlen($bigPng), "0. the same PNG as multipart: 201 (got $code " . json_encode($j) . ')');
$storedFiles[] = (string) ($j['path'] ?? '');
// Over the limit: a JSON 413 that names the limit this server enforces —
// the smaller of the app's 8 MB and PHP's own.
$rawLimit = min(8 * 1048576, PP_TEST_POST_MAX);
[$code, $j] = api('POST', '/api/ingest-media', $agent, str_repeat("\0", $rawLimit + 1), 'paper-a.test', 'image/png');
ok($code === 413 && ($j['ok'] ?? null) === false && ($j['max_bytes'] ?? 0) === $rawLimit
   && str_contains((string) ($j['error'] ?? ''), 'over the upload limit'),
   "0. a raw body one byte over the app's 8 MB: JSON 413 with max_bytes $rawLimit (got $code " . json_encode($j) . ')');
$multiLimit = min($rawLimit, PP_TEST_UPLOAD_MAX);
file_put_contents($tmp, str_repeat("\0", $multiLimit + 1));
[$code, $j] = api('POST', '/api/ingest-media', $agent, ['file' => new CURLFile($tmp, 'image/png', 'huge.png')], 'paper-a.test', null);
ok($code === 413 && ($j['max_bytes'] ?? 0) === $multiLimit,
   "0. a multipart file over PHP's upload_max_filesize: JSON 413 naming that lower limit, $multiLimit (got $code " . json_encode($j) . ')');
@unlink($tmp);

// Ordinary covers for the workflow, through the same lane.
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$upload = function () use ($agent, $png, &$storedFiles): string {
    [, $j] = api('POST', '/api/ingest-media', $agent, $png, 'paper-a.test', 'image/png');
    $path = is_array($j) ? (string) ($j['path'] ?? '') : '';
    $storedFiles[] = $path;
    return $path;
};
[$coverA, $coverB, $coverC, $oldArt, $flagArt] = [$upload(), $upload(), $upload(), $upload(), $upload()];
ok(!in_array('', [$coverA, $coverB, $coverC, $oldArt, $flagArt], true), '0. setup: five covers uploaded by the draft-only key');

$edDraft = $mkPost([$siteA], 'local-news', 'Council Weighs The Harbour Plan', 'draft', ['slug' => 'council-weighs-the-harbour-plan']);
$flagged = $mkPost([$siteA], 'local-news', 'Waiting On Art', 'draft', ['awaiting_image' => 1, 'origin' => 'hermes', 'filed_by' => 'edit-agent']);
$review  = $mkPost([$siteA], 'local-news', 'Sent For Review', 'in_review');
$sched   = $mkPost([$siteA], 'local-news', 'Scheduled For Monday', 'scheduled');
$live    = $mkPost([$siteA], 'local-news', 'Ferry Schedule Changes This Winter', 'published', ['slug' => 'ferry-schedule-changes-this-winter',
    'image' => $oldArt, 'image_alt' => 'The old ferry at the dock', 'image_caption' => 'The old caption.', 'image_credit' => 'Old Photographer',
    'meta' => 'The old description.', 'correction' => 'An editor\'s correction note.']);
$synd    = $mkPost([$siteA, $siteB], 'local-news', 'Carried By Two Papers', 'draft');
$bDraft  = $mkPost([$siteB], 'local-news', 'Only On Paper B', 'draft');
$pdo->prepare('INSERT INTO tags (name, slug) VALUES (?,?)')->execute(['Ferries', 'ferries']);
$pdo->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (?,?)')->execute([$live, (int) pp_last_id('tags')]);
$pdo->prepare('INSERT INTO story_sources (post_id, url, title, retrieved_at, created_at) VALUES (?,?,?,?,?)')
    ->execute([$live, 'https://bcferries.example/notice/1', 'Service notice', '2026-11-19 08:00:00', now()]);
$draftBefore = row($edDraft);
$liveBefore = row($live);

/* ---- 1. Each lane is its own grant ------------------------------------------------- */
[$code, $j] = $patch($noEdit, $refOf($edDraft), ['image' => $coverA, 'if_version' => ver($edDraft)]);
ok($code === 403 && $j === ['ok' => false, 'error' => 'this token has no draft-edit permission',
    'reason' => 'edit_drafts is granted at minting (--edit-drafts); without it this surface changes nothing'],
   "1. a key without edit_drafts: 403 {ok, error, reason} (got $code " . json_encode($j) . ')');
[$code, $j] = $update($noUpdate, ['ref' => $refOf($live), 'image' => $coverC, 'if_version' => ver($live)]);
ok($code === 403 && ($j['error'] ?? '') === 'this token has no update permission', "1. a key without update_published: 403 (got $code)");
[$code, $j] = api('POST', '/api/ingest/drafts/' . $refOf($edDraft), $agent, ['site' => 'paper-a']);
ok($code === 405 && $j === ['ok' => false, 'error' => 'PATCH one draft edit per call, at /api/ingest/drafts/{ref}'], "1. POST to the draft lane: 405 (got $code)");
ok(row($edDraft) == $draftBefore && row($live) == $liveBefore, '1. none of those wrote anything');

/* ---- 2. The draft workflow: find, cover, replace, read back ------------------------ */
// Find: the editor's draft, through the reader, by a scoped key.
[$code, $j] = $read($agent, 'site=paper-a&status=draft&desk=local-news');
$found = null;
foreach ((array) ($j['stories'] ?? []) as $s) {
    if (($s['title'] ?? '') === 'Council Weighs The Harbour Plan') {
        $found = $s;
    }
}
$ref = (string) ($found['ref'] ?? '');
ok($code === 200 && $found !== null && $found['status'] === 'draft' && $ref === $refOf($edDraft) && !isset($found['url']),
   "2. the scoped key finds the editor-created draft in the reader's list (got $code)");
[$code, $j] = $read($agent, 'site=paper-a&ref=' . $ref);
$v1 = (string) ($j['story']['version'] ?? '');
ok($code === 200 && ($j['story']['image'] ?? null) === '' && $v1 === ($found['version'] ?? '') && $v1 === ver($edDraft),
   '2. read in full: no cover yet; the list, the full read and the engine agree on the version');

// Attach a cover.
[$code, $j] = $patch($agent, $ref, ['image' => $coverA, 'image_alt' => 'Harbour cranes at dusk',
    'image_caption' => 'The harbour from the north shore.', 'image_credit' => 'Photo A', 'if_version' => $v1]);
$v2 = (string) ($j['version'] ?? '');
ok($code === 200 && ($j['ok'] ?? false) === true && ($j['ref'] ?? '') === $ref && ($j['status'] ?? '') === 'draft'
   && ($j['desk'] ?? '') === 'local-news' && ($j['updated'] ?? null) === ['image', 'image_alt', 'image_caption', 'image_credit']
   && ($j['previous_image'] ?? null) === '' && !isset($j['url']) && preg_match('/^v_[0-9a-f]{16}$/', $v2) === 1 && $v2 !== $v1,
   "2. attach a cover: 200 {ok, ref, status: draft, desk, updated, version, previous_image: ''} (got $code " . json_encode($j) . ')');

// Replace it, sending only the new credit: the old alt text and caption go.
[$code, $j] = $patch($agent, $ref, ['image' => $coverB, 'image_credit' => 'Photo B', 'if_version' => $v2]);
$v3 = (string) ($j['version'] ?? '');
ok($code === 200 && ($j['updated'] ?? null) === ['image', 'image_alt', 'image_caption', 'image_credit']
   && ($j['previous_image'] ?? '') === $coverA,
   "2. replace the cover: 200, the previous image reported (got $code " . json_encode($j) . ')');
$r = row($edDraft);
ok($r['image'] === $coverB && $r['image_alt'] === '' && $r['image_caption'] === '' && $r['image_credit'] === 'Photo B',
   '2. a replaced cover inherits none of the old alt text, caption or credit');

// Read it back: the same unpublished draft.
[$code, $j] = $read($agent, 'site=paper-a&ref=' . $ref);
$st = (array) ($j['story'] ?? []);
ok($code === 200 && ($st['ref'] ?? '') === $ref && ($st['status'] ?? '') === 'draft' && ($st['image'] ?? '') === $coverB
   && ($st['image_credit'] ?? '') === 'Photo B' && ($st['version'] ?? '') === $v3 && !isset($st['url']),
   '2. read back: the same ref, still a draft, the new cover, the version the PATCH returned');
foreach (['id', 'slug', 'author_id', 'status', 'published_at', 'awaiting_image', 'byline', 'dateline', 'category_id', 'origin', 'created_at'] as $k) {
    ok((string) $r[$k] === (string) $draftBefore[$k], "2. the draft's $k did not move");
}
[$pcode] = api('GET', '/story/council-weighs-the-harbour-plan', null);
ok($pcode === 404, "2. and it is still not public (got $pcode)");
$revs = revisions($edDraft);
ok(count($revs) === 3 && $revs[0]['reason'] === 'before agent draft edit' && $revs[0]['saved_by'] === '' && $revs[0]['image'] === ''
   && $revs[1]['reason'] === 'agent draft edit' && $revs[1]['saved_by'] === 'hermes:edit-agent' && $revs[1]['image'] === $coverA
   && $revs[1]['image_alt'] === 'Harbour cranes at dusk' && $revs[2]['image'] === $coverB && $revs[2]['image_credit'] === 'Photo B',
   '2. history: the editor\'s state before the agent, then each agent edit, signed (got ' . json_encode($revs) . ')');
$a = audits('ingest-draft-edit');
ok(count($a) === 2 && (int) $a[0]['site_id'] === $siteA && $a[0]['user_name'] === 'hermes:edit-agent'
   && $a[0]['target'] === 'council-weighs-the-harbour-plan'
   && $a[0]['detail'] === 'draft edited: image, image_alt, image_caption, image_credit (image none → ' . $coverA . ')'
   && $a[1]['detail'] === 'draft edited: image, image_alt, image_caption, image_credit (image ' . $coverA . ' → ' . $coverB . ')',
   '2. each edit audited once against the paper, naming the key, the fields and the image change (got ' . json_encode($a) . ')');

// Text, tags, sources and SEO on the draft.
[$code, $j] = $patch($agent, $ref, ['title' => 'Council Weighs The Harbour Plan Tonight', 'lede' => 'A new lede.',
    'body' => '<p>New body.</p><script>alert(1)</script>', 'meta_description' => 'What the harbour plan means.',
    'tags' => ['Harbour', 'harbour', 'City Council'], 'sources' => [['url' => 'https://city.example/agenda', 'title' => 'Agenda']],
    'if_version' => $v3]);
$v4 = (string) ($j['version'] ?? '');
ok($code === 200 && ($j['updated'] ?? null) === ['title', 'lede', 'body', 'meta_description', 'tags', 'sources'],
   "2. text, SEO description, tags and sources: 200 (got $code " . json_encode($j) . ')');
[, $j] = $read($agent, 'site=paper-a&ref=' . $ref);
$st = (array) ($j['story'] ?? []);
ok(($st['title'] ?? '') === 'Council Weighs The Harbour Plan Tonight' && ($st['meta_description'] ?? '') === 'What the harbour plan means.'
   && ($st['tags'] ?? null) === ['City Council', 'Harbour'] && ($st['sources'] ?? null) === [['url' => 'https://city.example/agenda', 'title' => 'Agenda']]
   && !str_contains((string) ($st['body'] ?? ''), '<script') && ($st['status'] ?? '') === 'draft' && ($st['version'] ?? '') === $v4,
   '2. read back: the new text, one tag per name, the sources as sent, the body sanitized, still a draft');
ok(row($edDraft)['slug'] === 'council-weighs-the-harbour-plan', '2. a new headline does not rename the story');

// Remove the cover: its text goes with it.
[$code, $j] = $patch($agent, $ref, ['image' => null, 'if_version' => $v4]);
$v5 = (string) ($j['version'] ?? '');
$r = row($edDraft);
ok($code === 200 && ($j['updated'] ?? null) === ['image', 'image_credit'] && ($j['previous_image'] ?? '') === $coverB
   && $r['image'] === '' && $r['image_credit'] === '',
   "2. image: null removes the cover and its credit (got $code " . json_encode($j) . ')');

/* ---- 3. Retries, stale versions and status changes ----------------------------------- */
$revCount = count(revisions($edDraft));
$auditCount = count(audits('ingest-draft-edit'));
[$code, $j] = $patch($agent, $ref, ['image' => null, 'if_version' => $v4]);   // the same request, resent
ok($code === 200 && ($j['unchanged'] ?? false) === true && ($j['version'] ?? '') === $v5,
   "3. resending a mutation that already landed (its version now stale): 200 unchanged, the current version (got $code " . json_encode($j) . ')');
[$code, $j] = $patch($agent, $ref, ['title' => 'Would Overwrite A Newer Edit', 'if_version' => $v4]);
ok($code === 409 && !isset($j['version']) && ($j['status'] ?? '') === 'draft' && str_contains((string) ($j['error'] ?? ''), 'changed since your version'),
   "3. a real change on a stale version: 409 with the status and no version — re-read first (got $code " . json_encode($j) . ')');
[$code, $j] = $patch($agent, $ref, ['title' => 'No Version At All']);
ok($code === 422 && str_contains((string) ($j['error'] ?? ''), 'if_version is required'), "3. no if_version: 422 (got $code)");
[$code, $j] = $patch($agent, $ref, ['title' => 'Bad Version', 'if_version' => 'v_nothex']);
ok($code === 422 && str_contains((string) ($j['error'] ?? ''), 'if_version is required'), "3. a malformed if_version: 422 (got $code)");
ok(count(revisions($edDraft)) === $revCount && count(audits('ingest-draft-edit')) === $auditCount
   && row($edDraft)['title'] === 'Council Weighs The Harbour Plan Tonight', '3. none of those wrote a row, a revision or an audit line');
// The newsroom moves the story on between the agent's read and its write.
$vBeforeReview = ver($edDraft);
fresh()->prepare("UPDATE posts SET status = 'in_review' WHERE id = ?")->execute([$edDraft]);
[$code, $j] = $patch($agent, $ref, ['title' => 'Edited After Submission', 'if_version' => $vBeforeReview]);
ok($code === 409 && ($j['status'] ?? '') === 'in_review' && row($edDraft)['title'] === 'Council Weighs The Harbour Plan Tonight',
   "3. a draft submitted for review since the read: 409 with its new status, nothing written (got $code " . json_encode($j) . ')');
fresh()->prepare("UPDATE posts SET status = 'draft' WHERE id = ?")->execute([$edDraft]);

/* ---- 4. Nothing here publishes, schedules, renames or moves a draft ------------------- */
$v = ver($edDraft);
foreach ([
    'status' => ['status' => 'published'],
    'published_at' => ['published_at' => '2026-11-23 09:00:00'],
    'slug' => ['slug' => 'a-new-slug'],
    'publish_on_image' => ['publish_on_image' => true],
    'awaiting_image' => ['awaiting_image' => 1],
    'sites' => ['sites' => ['other-paper']],
    'byline' => ['byline' => 'Someone Else'],
] as $field => $extra) {
    [$code, $j] = $patch($agent, $ref, $extra + ['title' => 'Smuggled', 'if_version' => $v]);
    ok($code === 422 && str_starts_with((string) ($j['error'] ?? ''), 'not editable here: ' . $field),
       "4. '$field' is refused: 422 not editable here (got $code " . json_encode($j) . ')');
}
[$code, $j] = $patch($agent, $ref, ['new_desk' => 'sports', 'if_version' => $v]);
ok($code === 422 && str_contains((string) ($j['error'] ?? ''), 'desk is the newsroom'), "4. new_desk on a draft: 422 (got $code)");
[$code, $j] = $patch($agent, $ref, ['ref' => $ref, 'title' => 'X', 'if_version' => $v]);
ok($code === 422 && str_contains((string) ($j['error'] ?? ''), 'ref is in the path'), "4. a ref in the body: 422 (got $code)");
[$code, $j] = $patch($agent, $ref, ['image' => null, 'image_caption' => 'A caption for nothing', 'if_version' => $v]);
ok($code === 422 && str_contains((string) ($j['error'] ?? ''), 'removed cover takes'), "4. removing a cover while captioning it: 422 (got $code)");
[$code, $j] = $patch($agent, $ref, ['image_caption' => 'A caption for nothing', 'if_version' => $v]);
ok($code === 422 && str_contains((string) ($j['error'] ?? ''), 'has no cover'), "4. a caption on a story with no cover: 422 (got $code)");
[$code, $j] = $patch($agent, $ref, ['image' => '/uploads/2026/11/never-uploaded.png', 'if_version' => $v]);
ok($code === 422 && str_contains((string) ($j['error'] ?? ''), 'image must be an /uploads/ path'), "4. an upload that does not exist: 422 (got $code)");
[$code, $j] = $patch($agent, $ref, ['tags' => array_map(fn ($i) => "Tag $i", range(1, 11)), 'if_version' => $v]);
ok($code === 422 && str_contains((string) ($j['error'] ?? ''), 'at most 10 tags'), "4. eleven tags: 422 (got $code)");
[$code, $j] = $patch($agent, $ref, ['sources' => [['url' => 'ftp://x.example/']], 'if_version' => $v]);
ok($code === 422 && str_contains((string) ($j['error'] ?? ''), 'must be http(s)'), "4. a non-http source: 422 (got $code)");
ok(row($edDraft)['status'] === 'draft' && row($edDraft)['published_at'] === null && ver($edDraft) === $v, '4. and the draft is exactly as it was');

// A publish-on-image draft: a cover lands, the flag and the status do not move.
[$code, $j] = $patch($agent, $refOf($flagged), ['image' => $flagArt, 'if_version' => ver($flagged)]);
$r = row($flagged);
ok($code === 200 && ($j['awaiting_image'] ?? false) === true && ($j['status'] ?? '') === 'draft'
   && $r['status'] === 'draft' && (int) $r['awaiting_image'] === 1 && $r['published_at'] === null && $r['image'] === $flagArt,
   "4. a cover on a publish-on-image draft: attached, reported awaiting_image, NOT published, the flag untouched (got $code " . json_encode($j) . ')');

// Wrong status for this lane.
foreach (['in review' => $review, 'scheduled' => $sched, 'published' => $live] as $label => $id) {
    [$code, $j] = $patch($agent, $refOf($id), ['title' => 'Edited Through The Draft Lane', 'if_version' => ver($id)]);
    ok($code === 409 && str_contains((string) ($j['error'] ?? ''), 'not a draft'), "4. a $label story through the draft lane: 409 (got $code)");
}

/* ---- 5. Scope ---------------------------------------------------------------------- */
$oos = ['ok' => false, 'error' => 'out of scope'];
$v = ver($edDraft);
foreach ([
    'paper B\'s key on paper A' => [$bKey, $ref, ['site' => 'paper-a'], 'paper-a.test'],
    'paper A\'s draft asked for on paper B\'s hostname' => [$agent, $ref, ['site' => 'paper-a'], 'paper-b.test'],
    'another paper\'s draft' => [$agent, $refOf($bDraft, $siteB), ['site' => 'other-paper'], 'paper-b.test'],
    'a desk the key does not hold' => [$deskHeld, $ref, ['site' => 'paper-a'], 'paper-a.test'],
] as $label => [$tok, $r, $over, $host]) {
    [$code, $j] = api('PATCH', '/api/ingest/drafts/' . $r, $tok, $over + ['desk' => 'local-news', 'title' => 'Out Of Scope', 'if_version' => $v], $host);
    ok($code === 403 && $j === $oos, "5. $label: the three-word 403 (got $code " . json_encode($j) . ')');
}
[$code, $j] = $patch($agent, $ref, ['desk' => 'environment', 'title' => 'X', 'if_version' => $v]);
ok($code === 404, "5. the right draft under the wrong desk: 404 (got $code)");
[$code, $j] = $patch($agent, 'ar_' . str_repeat('0', 24), ['title' => 'X', 'if_version' => $v]);
ok($code === 404, "5. a reference to nothing: 404 (got $code)");
[$code, $j] = $patch($agent, 'not-a-ref', ['title' => 'X', 'if_version' => $v]);
ok($code === 422 && str_contains((string) ($j['error'] ?? ''), 'ref must be an article reference'), "5. a malformed reference: 422 (got $code)");
[$code, $j] = $patch($agent, $refOf($synd), ['title' => 'X', 'if_version' => ver($synd)]);
ok($code === 409 && str_contains((string) ($j['error'] ?? ''), 'also carried by a paper'), "5. a draft another paper carries: 409 (got $code)");
ok(row($bDraft)['title'] === 'Only On Paper B' && row($synd)['title'] === 'Carried By Two Papers' && ver($edDraft) === $v, '5. none of those wrote anything');

/* ---- 6. A published story: cover, text, metadata, desk — same URL, same date --------- */
$liveRef = $refOf($live);
$liveUrl = 'https://paper-a.test/story/ferry-schedule-changes-this-winter';
[$code, $j] = $read($agent, 'site=paper-a&ref=' . $liveRef);
$lv1 = (string) ($j['story']['version'] ?? '');
[$code, $j] = $update($agent, ['ref' => $liveRef, 'image' => $coverC, 'image_alt' => 'The new ferry leaving the slip',
    'image_caption' => 'The new caption.', 'image_credit' => 'New Photographer', 'if_version' => $lv1]);
$lv2 = (string) ($j['version'] ?? '');
ok($code === 200 && ($j['status'] ?? '') === 'published' && ($j['url'] ?? '') === $liveUrl && ($j['previous_image'] ?? '') === $oldArt
   && ($j['updated'] ?? null) === ['image', 'image_alt', 'image_caption', 'image_credit'],
   "6. replace a live cover: 200, published, same URL, previous image reported (got $code " . json_encode($j) . ')');
[$code, $j] = $update($agent, ['url' => $liveUrl, 'title' => 'Ferry Schedule Changes Again This Winter', 'body' => '<p>Updated sailings.</p>',
    'meta_description' => 'The new winter sailings.', 'image_alt' => 'The new ferry, from the bridge',
    'tags' => ['Ferries', 'Transport'], 'sources' => [['url' => 'https://bcferries.example/notice/1', 'title' => 'Service notice'],
        ['url' => 'https://bcferries.example/notice/2', 'title' => 'Revised notice']], 'if_version' => $lv2]);
$lv3 = (string) ($j['version'] ?? '');
ok($code === 200 && ($j['updated'] ?? null) === ['title', 'body', 'meta_description', 'image_alt', 'tags', 'sources'],
   "6. text and metadata on the live story: 200 (got $code " . json_encode($j) . ')');
$src = fresh()->prepare('SELECT url, retrieved_at FROM story_sources WHERE post_id = ? ORDER BY id');
$src->execute([$live]);
$src = $src->fetchAll();
ok(count($src) === 2 && $src[0]['retrieved_at'] === '2026-11-19 08:00:00' && $src[1]['retrieved_at'] === null,
   '6. a source kept across the replacement keeps its retrieval time');
[$code, $j] = $update($agent, ['ref' => $liveRef, 'new_desk' => 'environment', 'if_version' => $lv3]);
$lv4 = (string) ($j['version'] ?? '');
ok($code === 200 && ($j['updated'] ?? null) === ['desk'] && ($j['desk'] ?? '') === 'environment' && ($j['url'] ?? '') === $liveUrl,
   "6. move the live story to another desk the key holds: 200, same URL (got $code " . json_encode($j) . ')');
$r = row($live);
foreach (['id', 'slug', 'status', 'published_at', 'byline', 'dateline', 'correction', 'author_id', 'lede', 'created_at'] as $k) {
    ok((string) $r[$k] === (string) $liveBefore[$k], "6. the live story's $k did not move");
}
ok((int) $r['category_id'] === $desk['environment'] && $r['title'] === 'Ferry Schedule Changes Again This Winter' && $r['image'] === $coverC,
   '6. the row carries the new desk, headline and cover');
[$pcode, , $page] = api('GET', '/story/ferry-schedule-changes-this-winter', null);
ok($pcode === 200 && str_contains($page, 'Ferry Schedule Changes Again This Winter') && str_contains($page, 'Updated sailings.')
   && str_contains($page, $coverC) && str_contains($page, 'The new ferry, from the bridge'),
   "6. the public page at the same URL serves the new headline, body, cover and alt text (got $pcode)");
[$code, $j] = $read($agent, 'site=paper-a&desk=environment&ref=' . $liveRef);
ok($code === 200 && ($j['story']['desk'] ?? '') === 'environment' && ($j['story']['version'] ?? '') === $lv4
   && ($j['story']['published_at'] ?? '') === '2026-11-20 12:00:00',
   '6. read back on its new desk: same ref, same publication date, the version the update returned');
$revs = revisions($live);
ok(count($revs) === 4 && $revs[0]['reason'] === 'before agent update' && $revs[0]['image'] === $oldArt
   && $revs[0]['image_alt'] === 'The old ferry at the dock' && $revs[0]['image_credit'] === 'Old Photographer'
   && $revs[3]['reason'] === 'agent update' && $revs[3]['saved_by'] === 'hermes:edit-agent',
   '6. the revisions are recorded: the pre-agent state with its alt text and credit, then each agent update');
$a = audits('ingest-update');
ok(count($a) === 3 && str_contains($a[1]['detail'], '(tags were: Ferries)') && str_contains($a[1]['detail'], '(sources were: https://bcferries.example/notice/1)')
   && $a[2]['detail'] === 'published story updated: desk (desk local-news → environment)',
   '6. the audit rows record the previous tags, sources and desk (got ' . json_encode(array_column($a, 'detail')) . ')');
// The retry of the desk move, and the lane's refusals.
[$code, $j] = $update($agent, ['ref' => $liveRef, 'desk' => 'environment', 'new_desk' => 'environment', 'if_version' => $lv3]);
ok($code === 200 && ($j['unchanged'] ?? false) === true, "6. resending the move (on the new desk): 200 unchanged (got $code)");
[$code, $j] = $update($agent, ['ref' => $liveRef, 'desk' => 'environment', 'title' => 'Stale', 'if_version' => $lv3]);
ok($code === 409 && !isset($j['version']) && ($j['status'] ?? '') === 'published', "6. a stale version on the live story: 409 (got $code)");
[$code, $j] = $update($deskHeld, ['ref' => $liveRef, 'desk' => 'environment', 'new_desk' => 'local-news', 'if_version' => $lv4]);
ok($code === 403 && $j === $oos, "6. moving to a desk the key does not hold: 403 (got $code)");
[$code, $j] = $update($agent, ['ref' => $liveRef, 'desk' => 'environment', 'new_desk' => 'no-such-desk', 'if_version' => $lv4]);
ok($code === 422 && str_contains((string) ($j['error'] ?? ''), 'unknown desk'), "6. an unknown desk: 422 (got $code)");
foreach (['status' => 'draft', 'slug' => 'renamed', 'published_at' => '2026-01-01 00:00:00', 'sites' => ['other-paper']] as $field => $value) {
    [$code, $j] = $update($agent, ['ref' => $liveRef, 'desk' => 'environment', $field => $value, 'if_version' => $lv4]);
    ok($code === 422 && str_starts_with((string) ($j['error'] ?? ''), 'not editable here: ' . $field), "6. '$field' on a live story: 422 (got $code)");
}
[$code, $j] = $update($bKey, ['ref' => $liveRef, 'site' => 'paper-a', 'desk' => 'environment', 'title' => 'X', 'if_version' => $lv4]);
ok($code === 403 && $j === $oos, "6. paper B's key on paper A's live story: 403 (got $code)");
[$code, $j] = $update($agent, ['ref' => $refOf($edDraft), 'title' => 'X', 'if_version' => ver($edDraft)]);
ok($code === 409 && ($j['status'] ?? '') === 'draft', "6. a draft through the published lane: 409 with its status (got $code)");
ok(ver($live) === $lv4, '6. and none of those wrote anything');

// An editor restores the pre-agent revision: the old picture with its own alt text and credit.
$jar = $work . '/jar-editor';
$http = function (string $method, string $path, array $fields = []) use ($jar, $port): array {
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => false]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body];
};
[, $loginPage] = $http('GET', '/admin/login.php');
preg_match('/name="csrf" value="([0-9a-f]+)"/', $loginPage, $m);
$csrf = $m[1] ?? '';
[$lc] = $http('POST', '/admin/login.php', ['csrf' => $csrf, 'email' => $editorEmail, 'password' => 'correct horse pp']);
[$rc] = $http('POST', '/admin/revision.php', ['csrf' => $csrf, 'id' => (int) $revs[0]['id'], 'action' => 'restore']);
$r = row($live);
ok($lc === 302 && $rc === 302 && $r['image'] === $oldArt && $r['image_alt'] === 'The old ferry at the dock'
   && $r['image_credit'] === 'Old Photographer' && $r['status'] === 'published',
   "6. an editor's restore brings back the old cover WITH its alt text and credit (got $lc/$rc " . json_encode([$r['image_alt'], $r['image_credit']]) . ')');

/* ---- 7. One transaction: no audit row, no edit ---------------------------------------- */
if ($engine === 'sqlite') {
    $f = fresh();
    $f->exec("CREATE TRIGGER fail_edit_audit BEFORE INSERT ON audit_log WHEN NEW.action = 'ingest-draft-edit'
              BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
    $before = row($edDraft);
    $revCount = count(revisions($edDraft));
    $tagCount = scalar('SELECT COUNT(*) FROM post_tags WHERE post_id = ?', [$edDraft]);
    [$code, $j] = $patch($agent, $ref, ['title' => 'Lost With The Audit', 'tags' => [], 'image' => $coverA, 'if_version' => ver($edDraft)]);
    // SQLite reports a trigger's RAISE(ABORT) as a constraint violation
    // (SQLSTATE 23000), which the engine answers as a collision; any other
    // failure is a 500. Either way the answer says nothing was written —
    // and the rows below prove it.
    ok($code === 409 && $j === ['ok' => false,
        'error' => 'a concurrent change collided with this edit — nothing was written; read the story again and resend'],
       "7. an audit row that cannot be written: refused, nothing written (got $code " . json_encode($j) . ')');
    ok(row($edDraft) == $before && count(revisions($edDraft)) === $revCount
       && scalar('SELECT COUNT(*) FROM post_tags WHERE post_id = ?', [$edDraft]) === $tagCount,
       '7. the story, its tags and its history are exactly as they were');
    $f->exec('DROP TRIGGER fail_edit_audit');
}

/* ---- 8. The hourly limit ------------------------------------------------------------- */
$ins = fresh()->prepare("INSERT INTO audit_log (site_id, user_id, user_name, action, target, detail, ip, created_at) VALUES (?, 0, 'hermes:edit-agent', 'ingest-draft-edit', 'fixture', 'fixture', '', ?)");
for ($i = 0; $i < 60; $i++) {
    $ins->execute([$siteA, now()]);
}
$ins = null;
[$code, $j] = $patch($agent, $ref, ['title' => 'Sixty-First Of The Hour', 'if_version' => ver($edDraft)]);
ok($code === 429 && ($j['error'] ?? '') === 'rate limit: at most 60 draft edits per hour for this token'
   && row($edDraft)['title'] !== 'Sixty-First Of The Hour', "8. the sixty-first draft edit of the hour: 429, nothing written (got $code)");

if ($fails === 0) {
    echo "ingest-edit: all checks pass\n";
    exit(0);
}
exit(1);
