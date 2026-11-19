<?php
/**
 * A correction to evidence never touches the editor's work (milestone
 * NEWS4), over real HTTP on a disposable database.
 *
 * The evidence exchange holds one draft-only ingest token. When the
 * evidence behind an item it filed changes, it does not rewrite that
 * item: it files a second draft, a note that names the item ("Correction
 * to newsroom item N") and says what changed. This suite files an item
 * the way the exchange does, lets an editor rework it through a signed-in
 * session (headline, copy, a note to the author, tags, an autosave), and
 * then sends everything the exchange can send afterwards:
 *
 *   - the correction note;
 *   - the same note again (a timed-out request repeated);
 *   - the original filing again (a replay of the first revision);
 *   - an attach-and-publish call against the editor's item;
 *   - a second note and a withdrawal notice once the item is published.
 *
 * After each one the item is read back whole — its row, its revision
 * history, its sources, its tags, the papers it runs on — and must be
 * exactly what the editor left. The notes land as drafts, once each, and
 * are never public. The editor keeps working on the item afterwards, and
 * records the correction on the published story from the edit page: that
 * is the only way a correction reaches a reader.
 *
 * One column is left out of the comparison: posts.views, which a reader's
 * visit raises and which is not editorial work.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('corrnote');
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

/* ---- Fixtures: one desk, one paper, the exchange's token, one editor ------ */
$pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute(['Energy', 'energy']);
$deskId = pp_last_id('categories');
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')
    ->execute(['paper.test', 'prairiedispatch', now()]);
$siteId = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'prairiedispatch'")->fetchColumn();
// The desk is this paper's live wire, so a token that could publish would:
// what keeps every filing below a draft is the token, not the desk.
$pdo->prepare('INSERT INTO settings (site_id, skey, svalue) VALUES (?, ?, ?)')
    ->execute([$siteId, 'wire_desks', 'energy']);

$token = 'hermes_' . bin2hex(random_bytes(28));
$pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, draft_only, created_at) VALUES (?,?,?,?,?,?,?)')
    ->execute(['evidence-desk', hash('sha256', $token), 'prairiedispatch', 'energy', 1, 1, now()]);

$pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
    ->execute(['Ed Editor', 'editor-corrnote@test.local', password_hash('correct horse pp', PASSWORD_DEFAULT), 'editor', 'ed-editor-corrnote', now()]);

$port = 8900 + random_int(0, 80);
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

$codes = [];   // every HTTP status this suite saw — none may be 5xx

/** The exchange's side: one JSON call with its bearer token. */
function api(int $port, string $method, string $path, ?string $token, $body = null, string $ctype = 'application/json'): array
{
    global $codes;
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
    $codes[] = $code;
    return [$code, (array) json_decode($out, true)];
}

/** The newsroom's side: a browser session with a cookie jar. */
function web(int $port, string $method, string $path, array $fields, string $jar): array
{
    global $codes;
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $codes[] = $code;
    return [$code, $body];
}

// Reads after the server writes go through a fresh connection: under WAL
// an open cursor pins the long-lived handle to a read snapshot.
function fresh(): PDO
{
    global $config, $pdo;
    $cfg = require $config;
    if (($cfg['db']['driver'] ?? '') !== 'sqlite') {
        return $pdo;   // read-committed engines see other processes' commits
    }
    $p = new PDO('sqlite:' . $cfg['db']['sqlite_path']);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $p;
}

/**
 * Everything the newsroom holds for one item: the row (less the reader
 * counter), its revision history, its sources, its tags and its papers.
 */
function state(int $id): array
{
    $p = fresh();
    $all = function (string $sql) use ($p, $id): array {
        $s = $p->prepare($sql);
        $s->execute([$id]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        $s->closeCursor();
        return $rows;
    };
    $post = $all('SELECT * FROM posts WHERE id = ?');
    foreach ($post as &$r) {
        unset($r['views']);
    }
    unset($r);
    return [
        'post' => $post,
        'revisions' => $all('SELECT * FROM post_revisions WHERE post_id = ? ORDER BY id'),
        'sources' => $all('SELECT * FROM story_sources WHERE post_id = ? ORDER BY id'),
        'tags' => $all('SELECT t.slug FROM post_tags pt JOIN tags t ON t.id = pt.tag_id WHERE pt.post_id = ? ORDER BY t.slug'),
        'papers' => $all('SELECT site_id FROM post_sites WHERE post_id = ? ORDER BY site_id'),
    ];
}
function posts_total(): int
{
    $s = fresh()->query('SELECT COUNT(*) FROM posts');
    $n = (int) $s->fetchColumn();
    $s->closeCursor();
    return $n;
}

/* ---- What the exchange sends: the keys of its desk filing, nothing else --- */
$source = ['url' => 'https://example.org/orders/12-2026.pdf', 'title' => 'Order 12/2026', 'retrieved_at' => '2026-11-18T08:00:00Z'];
$original = [
    'site' => 'prairiedispatch', 'desk' => 'energy',
    'title' => 'Order 12/2026: the test regulator approves the test line',
    'lede' => 'Filed from evidence revision 1. Every attribution below is unreviewed.',
    'body' => '<h2>What the record says</h2><p>ORIGINALBODY The order approves the line, effective 2026-12-01.</p>'
            . '<ul><li>Revision: 1 (first publication)</li></ul>',
    'tags' => 'evidence, energy',
    'external_id' => 'evidence:energy:order-12-2026:r1',
    'sources' => [$source],
];
$note = fn (int $item, string $kind, int $revision): array => [
    'site' => 'prairiedispatch', 'desk' => 'energy',
    'title' => ($kind === 'withdrawal' ? 'Withdrawal notice for' : 'Correction to') . " newsroom item $item: Order 12/2026 (evidence revision $revision)",
    'lede' => $kind === 'withdrawal'
        ? "The source withdrew the decision behind newsroom item $item. This notice belongs to that item and is not a new story."
        : "The evidence behind newsroom item $item changed from revision 1 to revision $revision. This note belongs to that item and is not a new story.",
    'body' => "<p>NOTEBODY Filed by the evidence exchange as a draft note. It must not run as a new story. "
            . "The original item, its notes and its drafts are untouched.</p>"
            . "<ul><li>Newsroom item: $item</li><li>Filed from evidence revision: 1</li><li>This evidence revision: $revision</li></ul>"
            . '<h2>What changed</h2><ul><li>Effective from: 2026-12-01 became 2027-01-01</li></ul>',
    'tags' => 'evidence, energy',
    'external_id' => "evidence:energy:order-12-2026:r$revision",
    'sources' => [$source],
];

/* ---- 1. The exchange files the item --------------------------------------- */
[$code, $j] = api($port, 'POST', '/api/ingest', $token, $original);
$item = (int) ($j['id'] ?? 0);
ok($code === 201 && $item > 0 && ($j['status'] ?? '') === 'draft',
    "the item is filed as a draft (got $code, status " . ($j['status'] ?? '?') . ')');
ok(str_contains((string) ($j['reason'] ?? ''), 'draft_only'), 'and the response says the token files drafts only');
$filedSlug = (string) ($j['slug'] ?? '');

/* ---- 2. An editor reworks it through the admin ---------------------------- */
$jar = $work . '/jar-editor';
[, $page] = web($port, 'GET', '/admin/login.php', [], $jar);
preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
$csrf = $m[1] ?? '';
[$code] = web($port, 'POST', '/admin/login.php', ['csrf' => $csrf, 'email' => 'editor-corrnote@test.local', 'password' => 'correct horse pp'], $jar);
ok($code === 302, "the editor signs in (got $code)");

// The edit form as the editor's browser posts it. `sites` is the paper the
// story already runs on, which the form shows ticked.
$save = fn (array $over) => web($port, 'POST', "/admin/post-edit.php?id=$item", array_merge([
    'csrf' => $csrf,
    'title' => 'EDITORHEADLINE Regulator clears the line',
    'lede' => 'EDITORLEDE The editor rewrote this.',
    'body' => '<p>EDITORBODY The editor rewrote the copy and checked the order.</p>',
    'status' => 'draft',
    'category_id' => $deskId,
    'byline' => 'Newsroom',
    'tags' => 'energy, editor-pick',
    'sites' => [$siteId],
    'review_note' => 'EDITORNOTE Check the effective date against the order before this runs.',
    'correction' => '',
], $over), $jar);

[$code] = $save([]);
ok($code === 302, "the editor's save is accepted (got $code)");
[$code, $body] = web($port, 'POST', '/admin/autosave.php', [
    'csrf' => $csrf, 'id' => $item,
    'title' => 'EDITORHEADLINE Regulator clears the line',
    'lede' => 'EDITORLEDE The editor rewrote this.',
    'body' => '<p>EDITORBODY The editor rewrote the copy and checked the order.</p><p>EDITORAUTOSAVE A paragraph still being written.</p>',
], $jar);
ok($code === 200 && str_contains($body, 'saved'), "the editor's autosave is accepted (got $code)");

$edited = state($item);
$row = $edited['post'][0] ?? [];
$editorSlug = (string) ($row['slug'] ?? '');
ok(str_contains((string) ($row['title'] ?? ''), 'EDITORHEADLINE')
    && str_contains((string) ($row['body'] ?? ''), 'EDITORAUTOSAVE')
    && !str_contains((string) ($row['body'] ?? ''), 'ORIGINALBODY')
    && str_contains((string) ($row['review_note'] ?? ''), 'EDITORNOTE')
    && ($row['status'] ?? '') === 'draft',
    "the item now holds the editor's headline, copy, autosave and note");
ok($editorSlug !== '' && $editorSlug !== $filedSlug, "the editor's headline gave the item a new address");
ok(count($edited['revisions']) >= 1 && $edited['revisions'][0]['saved_by'] === 'Ed Editor',
    "the editor's save is in the revision history");
ok(array_column($edited['tags'], 'slug') === ['editor-pick', 'energy'], "the item carries the editor's tags");
ok(count($edited['sources']) === 1 && array_column($edited['papers'], 'site_id') == [$siteId],
    'it still cites its source and runs on its paper');
$totalBefore = posts_total();

/* ---- 3. The evidence changes: the exchange files a correction note -------- */
[$code, $j] = api($port, 'POST', '/api/ingest', $token, $note($item, 'correction', 2));
$noteId = (int) ($j['id'] ?? 0);
$noteSlug = (string) ($j['slug'] ?? '');
ok($code === 201 && $noteId > 0 && $noteId !== $item, "the correction is filed as its own item (got $code, id $noteId, original $item)");
ok(($j['status'] ?? '') === 'draft' && !isset($j['url']), 'as a draft, with no public address');
ok(state($item) === $edited, "the editor's item is exactly as the editor left it");
ok(posts_total() === $totalBefore + 1, 'exactly one item was added');

$noteState = state($noteId);
$n = $noteState['post'][0] ?? [];
ok(str_contains((string) ($n['title'] ?? ''), "Correction to newsroom item $item"), 'the note names the item it belongs to');
ok(str_contains((string) ($n['body'] ?? ''), 'must not run as a new story')
    && str_contains((string) ($n['body'] ?? ''), 'What changed')
    && str_contains((string) ($n['body'] ?? ''), "Newsroom item: $item"),
    'the note keeps its warning, its list of changes and the item number');
ok(($n['status'] ?? '') === 'draft' && empty($n['published_at']) && ($n['filed_by'] ?? '') === 'evidence-desk'
    && (int) ($n['category_id'] ?? 0) === $deskId,
    'the note is an unpublished draft on the same desk, filed by the exchange');
ok(count($noteState['sources']) === 1, 'the note cites the source');

/* ---- 4. The same note again: one note, not two ---------------------------- */
[$code, $j] = api($port, 'POST', '/api/ingest', $token, $note($item, 'correction', 2));
ok($code === 200 && ($j['duplicate'] ?? false) === true && (int) ($j['id'] ?? 0) === $noteId,
    "a repeated correction is confirmed, not filed again (got $code)");
ok(posts_total() === $totalBefore + 1, 'no second note exists');
ok(state($item) === $edited, "the editor's item is unchanged by the repeat");

/* ---- 5. The original filing again: the editor's copy is not overwritten --- */
[$code, $j] = api($port, 'POST', '/api/ingest', $token, $original);
ok($code === 200 && ($j['duplicate'] ?? false) === true && (int) ($j['id'] ?? 0) === $item,
    "a replay of the first filing is confirmed against the editor's item (got $code)");
ok(($j['slug'] ?? '') === $editorSlug, "and answers with the item's current address");
ok(state($item) === $edited, "the editor's headline and copy are not replaced by the replay");
ok(posts_total() === $totalBefore + 1, 'the replay filed nothing');

/* ---- 6. The token cannot publish the editor's item ------------------------ */
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
[$code, $j] = api($port, 'POST', '/api/ingest-media', $token, $png, 'image/png');
$art = (string) ($j['path'] ?? '');
if ($art !== '') {
    $storedFiles[] = $art;
}
ok($code === 201 && $art !== '', "the token can store an image (got $code)");
[$code, $j] = api($port, 'POST', '/api/ingest-publish', $token, ['story' => (string) $item, 'image' => $art]);
// 409, not the draft-only 403: the item was never filed as publish-on-image,
// so the lane is closed to every token before the capability is even asked.
ok($code === 409 && str_contains((string) ($j['error'] ?? ''), 'only its newsroom can publish it'),
    "attach-and-publish against the editor's item is refused: only its newsroom can publish it (got $code)");
ok(state($item) === $edited, 'and leaves the item as the editor left it: no image, not published');

/* ---- 7. The editor carries on: the history is all still there ------------- */
[$code] = $save(['body' => '<p>EDITORBODY The editor rewrote the copy and checked the order.</p><p>EDITORSECOND The effective date is now confirmed.</p>']);
ok($code === 302, "the editor's next save is accepted (got $code)");
$later = state($item);
ok(array_slice($later['revisions'], 0, count($edited['revisions'])) === $edited['revisions']
    && count($later['revisions']) === count($edited['revisions']) + 1,
    'every earlier revision is still in the history, and the new save was added');
ok(str_contains((string) $later['post'][0]['body'], 'EDITORSECOND')
    && str_contains((string) $later['post'][0]['review_note'], 'EDITORNOTE'),
    "the item holds the editor's new copy and still holds the note to the author");

/* ---- 8. Published: a correction still cannot reach a reader by itself ----- */
[$code] = $save(['status' => 'published',
    'body' => '<p>EDITORBODY The editor rewrote the copy and checked the order.</p><p>EDITORSECOND The effective date is now confirmed.</p>']);
ok($code === 302, "the editor publishes the item (got $code)");
[$code, $live] = web($port, 'GET', '/story/' . $editorSlug, [], $work . '/jar-reader');
ok($code === 200 && str_contains($live, 'EDITORSECOND'), "the story is live with the editor's copy (got $code)");
$published = state($item);
ok(($published['post'][0]['status'] ?? '') === 'published' && !empty($published['post'][0]['published_at']), 'the row is published');
$totalPublished = posts_total();

[$code, $j] = api($port, 'POST', '/api/ingest', $token, $note($item, 'correction', 3));
$note3 = (int) ($j['id'] ?? 0);
$note3Slug = (string) ($j['slug'] ?? '');
ok($code === 201 && ($j['status'] ?? '') === 'draft' && $note3 !== $item && $note3 !== $noteId,
    "a later correction is filed as another draft note (got $code)");
[$code, $j] = api($port, 'POST', '/api/ingest', $token, $note($item, 'withdrawal', 4));
$note4 = (int) ($j['id'] ?? 0);
ok($code === 201 && ($j['status'] ?? '') === 'draft', "a withdrawal notice is filed as a draft too (got $code)");
ok(state($item) === $published, 'the published story is unchanged: same copy, same publish time, no correction recorded');
ok(empty($published['post'][0]['correction']) && empty($published['post'][0]['corrected_at']),
    'nothing the exchange sent wrote a correction onto the story');
ok(posts_total() === $totalPublished + 2, 'exactly the two notes were added');

[$code, $live] = web($port, 'GET', '/story/' . $editorSlug, [], $work . '/jar-reader');
ok($code === 200 && str_contains($live, 'EDITORSECOND')
    && !str_contains($live, 'NOTEBODY') && !str_contains($live, 'newsroom item'),
    "readers still get the editor's story, with nothing from a note in it");
foreach ([$noteSlug, $note3Slug] as $slug) {
    [$code, $page] = web($port, 'GET', '/story/' . $slug, [], $work . '/jar-reader');
    ok($code === 404 && !str_contains($page, 'NOTEBODY'), "a note has no public page (got $code)");
}
[$code, $front] = web($port, 'GET', '/', [], $work . '/jar-reader');
ok($code === 200 && !str_contains($front, 'newsroom item') && !str_contains($front, 'Withdrawal notice'),
    'and no note appears on the front page');

/* ---- 9. The editor records the correction: that is how a reader sees one -- */
[$code] = $save(['status' => 'published',
    'body' => '<p>EDITORBODY The editor rewrote the copy and checked the order.</p><p>EDITORSECOND The effective date is now 2027-01-01.</p>',
    'correction' => 'EDITORCORRECTION An earlier version gave the effective date as 2026-12-01.']);
ok($code === 302, "the editor's correction is accepted (got $code)");
$corrected = state($item);
ok(str_contains((string) $corrected['post'][0]['correction'], 'EDITORCORRECTION') && !empty($corrected['post'][0]['corrected_at'])
    && ($corrected['post'][0]['status'] ?? '') === 'published',
    'the story now carries the correction, with its time, and is still published');
ok(array_slice($corrected['revisions'], 0, count($published['revisions'])) === $published['revisions'],
    "the story's history from before the correction is intact");
[$code, $live] = web($port, 'GET', '/story/' . $editorSlug, [], $work . '/jar-reader');
ok($code === 200 && str_contains($live, 'EDITORCORRECTION'), 'and readers see the correction on the story');

$f = fresh();
$s = $f->prepare("SELECT COUNT(*) FROM posts WHERE filed_by = 'evidence-desk' AND status <> 'draft' AND id <> ?");
$s->execute([$item]);
ok((int) $s->fetchColumn() === 0, 'every note the exchange filed is still a draft');
$s->closeCursor();
$s = $f->prepare("SELECT COUNT(*) FROM posts WHERE filed_by = 'evidence-desk'");
$s->execute();
ok((int) $s->fetchColumn() === 4, 'the exchange filed four items in all: the original and three notes');
$s->closeCursor();
ok($note4 > 0 && max($codes) < 500, 'no call answered 5xx (max status ' . max($codes) . ')');

if ($fails === 0) {
    echo "correction-note: all checks pass\n";
    exit(0);
}
exit(1);
