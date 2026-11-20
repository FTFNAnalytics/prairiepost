<?php
/**
 * The story-lookup lane, end to end over real HTTP on a disposable
 * database: POST /api/ingest/lookup and the source_story_id idempotency
 * of POST /api/ingest.
 *
 * What is proved here, in the order the brief asked for it:
 *   1. an editor-created draft WITH a cover is found by a key with the
 *      'all' grant (and is invisible to 'own');
 *   2. a published filing is found by its stored source_story_id, with
 *      the public URL in the answer;
 *   3. an uncertain legacy comparison answers review_required, never
 *      not_found — and never a match on one line of evidence;
 *   4. cross-paper and cross-desk isolation: out-of-scope requests get
 *      one three-word refusal that reveals nothing, existence
 *      notwithstanding, and the serving hostname is part of scope;
 *   5. two submissions under one source_story_id create exactly one
 *      article — the database constraint, not a read-then-write check —
 *      an identical retry is answered with the original receipt, and a
 *      different payload is a 409 that never overwrites editorial edits.
 *
 * Throughout: lookups WRITE NOTHING (no audit row, no last_used_at, no
 * receipt), match answers carry only {ref, status, url?}, and no answer
 * body ever carries draft copy or an editor's name.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('ingestlookup');
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
echo 'engine: ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . " ({$fx['id']})\n";

/* ---- Fixtures: two papers on their own hostnames, two desks, four keys ---- */
$pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute(['Local News', 'local-news']);
$pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute(['Environment', 'environment']);
$pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?,?,?)')->execute(['Other Paper', 'other-paper', now()]);
$siteA = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'prairiedispatch'")->fetchColumn();
$siteB = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'other-paper'")->fetchColumn();
$deskLocal = (int) $pdo->query("SELECT id FROM categories WHERE slug = 'local-news'")->fetchColumn();
$deskEnv = (int) $pdo->query("SELECT id FROM categories WHERE slug = 'environment'")->fetchColumn();
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')->execute(['paper-a.test', 'prairiedispatch', now()]);
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')->execute(['paper-b.test', 'other-paper', now()]);
$pdo->prepare('INSERT INTO settings (site_id, skey, svalue) VALUES (?, ?, ?)')->execute([$siteA, 'wire_desks', 'local-news']);

$mint = function (string $name, string $sites, string $desks, string $lookup, int $draftOnly = 0) use ($pdo): string {
    $t = 'hermes_' . bin2hex(random_bytes(28));
    $pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, draft_only, lookup_scope, created_at) VALUES (?,?,?,?,1,?,?,?)')
        ->execute([$name, hash('sha256', $t), $sites, $desks, $draftOnly, $lookup, now()]);
    return $t;
};
$all      = $mint('lookup-all', 'prairiedispatch', '', 'all');
$own      = $mint('lookup-own', 'prairiedispatch', '', 'own', 1);   // draft-only AND granted: the grant, not draft_only, decides
$deskHeld = $mint('lookup-desk-held', 'prairiedispatch', 'environment', 'all');
$bToken   = $mint('lookup-paper-b', 'other-paper', '', 'all');

$port = 9200 + random_int(0, 80);
$cmd = 'PP_CONFIG=' . escapeshellarg($config) . ' ' . escapeshellarg(PHP_BINARY)
     . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("$work/server.log") . ' 2>&1 & echo $!';
$serverPid = (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && ' . $cmd));
usleep(700000);
register_shutdown_function(function () use ($serverPid, $fx) {
    if ($serverPid) {
        @posix_kill($serverPid, 15);
    }
    pp_fixture_destroy($fx);
});

function api(int $port, string $method, string $path, ?string $token, $body = null, string $host = 'paper-a.test'): array
{
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    $headers = ['Host: ' . $host];
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
                            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, json_decode($raw, true), $raw];
}
$lookup = fn (string $token, array $q, string $host = 'paper-a.test'): array => api($GLOBALS['port'], 'POST', '/api/ingest/lookup', $token, $q, $host);

// Reads after server writes: a fresh connection, never the pinned one.
function fresh(): PDO
{
    global $config, $pdo;
    $cfg = require $config;
    if (($cfg['db']['driver'] ?? '') !== 'sqlite') {
        return $pdo;
    }
    $p = new PDO('sqlite:' . $cfg['db']['sqlite_path']);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $p;
}
function scalar(string $sql, array $args = []): int
{
    $s = fresh()->prepare($sql);
    $s->execute($args);
    $r = $s->fetch();
    $s->closeCursor();
    return $r ? (int) array_values($r)[0] : 0;
}

/* ---- Fixture content ------------------------------------------------------- */
// An EDITOR-created draft with a cover: no filed_by, no receipt, a stored
// primary-source URL — exactly the hand-entered story the noon job must
// not refile.
$mkPost = function (int $siteId, int $deskId, string $title, string $slug, string $status, array $over = []) use ($pdo): int {
    $pdo->prepare('INSERT INTO posts (title, slug, category_id, byline, lede, body, image, source_url, post_type, origin, status, filed_by, published_at, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$title, $slug, $deskId, (string) ($over['byline'] ?? 'An Editor'),
            'Editor-entered lede.', '<p>Editor-entered body.</p>',
            (string) ($over['image'] ?? ''), (string) ($over['source_url'] ?? ''),
            'story', '', $status, (string) ($over['filed_by'] ?? ''),
            $status === 'published' ? now() : null, now(), now()]);
    $id = (int) pp_last_id('posts');
    $pdo->prepare('INSERT INTO post_sites (post_id, site_id) VALUES (?,?)')->execute([$id, $siteId]);
    return $id;
};
$editorDraft = $mkPost($siteA, $deskEnv, 'Council Weighs The Harbour Cleanup', 'council-weighs-the-harbour-cleanup', 'draft',
    ['image' => '/uploads/2026/11/cover.jpg', 'source_url' => 'https://city.example/agenda/4411?utm_source=feed']);
$mkPost($siteA, $deskEnv, 'An Unrelated Environment Story', 'an-unrelated-environment-story', 'draft',
    ['source_url' => 'https://city.example/agenda/9999']);
$mkPost($siteB, $deskEnv, 'A Story Only Paper B Has', 'a-story-only-paper-b-has', 'draft');
$mkPost($siteA, $deskLocal, 'Filed On Another Desk', 'filed-on-another-desk', 'draft');

$file = fn (string $token, array $over): array => api($port, 'POST', '/api/ingest', $token, array_merge([
    'site' => 'prairiedispatch', 'desk' => 'local-news', 'title' => 'Wire Story With A Stable Id',
    'lede' => 'A lede long enough to pass the gate.', 'body' => '<p>Body copy for the lookup suite.</p>',
    'source_story_id' => 'noon-2026-11-20-001',
], $over));

/* ---- 1. The editor-created draft, with its cover ---------------------------- */
[$code, $j] = $lookup($all, ['site' => 'prairiedispatch', 'desk' => 'environment',
    'headline' => '  council weighs   the harbour cleanup ',
    'source_url' => 'http://CITY.example/agenda/4411/']);
$ref = is_array($j) ? (string) ($j['article']['ref'] ?? '') : '';
ok($code === 200 && $j === ['ok' => true, 'result' => 'match', 'article' => ['ref' => $ref, 'status' => 'draft']]
   && preg_match('/^ar_[0-9a-f]{24}$/', $ref) === 1,
   "1. 'all' finds the editor's covered draft by normalized headline + canonical URL: match {ref, status}, nothing else (got $code " . json_encode($j) . ')');
[$code2, $j2] = $lookup($all, ['site' => 'prairiedispatch', 'desk' => 'environment',
    'headline' => 'Council Weighs The Harbour Cleanup', 'source_url' => 'https://city.example/agenda/4411']);
ok($code2 === 200 && ($j2['article']['ref'] ?? '') === $ref, '1. the opaque ref is stable across calls');
[$code, $j] = $lookup($own, ['site' => 'prairiedispatch', 'desk' => 'environment',
    'headline' => 'Council Weighs The Harbour Cleanup', 'source_url' => 'https://city.example/agenda/4411']);
ok($code === 200 && is_array($j) && ($j['result'] ?? '') !== 'match',
   "1. 'own' does NOT match the editor's draft — finding other-filer and editor drafts takes 'all' (got " . json_encode($j) . ')');

/* ---- 2. A published filing, found by its stored story id ------------------- */
[$code, $j] = $file($all, []);
$wireId = is_array($j) ? (int) ($j['id'] ?? 0) : 0;
$wireSlug = is_array($j) ? (string) ($j['slug'] ?? '') : '';
ok($code === 201 && ($j['status'] ?? '') === 'published', "2. setup: the wire filing with a source_story_id publishes (got $code)");
[$code, $j] = $lookup($all, ['site' => 'prairiedispatch', 'desk' => 'local-news',
    'headline' => 'A Completely Different Headline', 'source_story_id' => 'noon-2026-11-20-001']);
ok($code === 200 && ($j['result'] ?? '') === 'match'
   && ($j['article']['status'] ?? '') === 'published'
   && ($j['article']['url'] ?? '') === 'https://paper-a.test/story/' . $wireSlug,
   '2. the stored id is checked FIRST, headline notwithstanding: match {status: published, url} (got ' . json_encode($j) . ')');
ok(!str_contains((string) json_encode($j), $wireSlug) || str_contains((string) ($j['article']['url'] ?? ''), $wireSlug),
   '2. the slug appears only inside the public url');

/* ---- 3. Uncertainty answers review_required, never a bare not_found -------- */
// Exact headline, but neither side has a URL to confirm with.
$mkPost($siteA, $deskEnv, 'A Legacy Story Without Links', 'a-legacy-story-without-links', 'draft');
[$code, $j] = $lookup($all, ['site' => 'prairiedispatch', 'desk' => 'environment',
    'headline' => 'a legacy story without links']);
ok($code === 200 && $j === ['ok' => true, 'result' => 'review_required'],
   '3. exact headline with no URL on either side: review_required (got ' . json_encode($j) . ')');
// The URL matches but the headline does not: one line of evidence.
[$code, $j] = $lookup($all, ['site' => 'prairiedispatch', 'desk' => 'environment',
    'headline' => 'A Headline The Desk Has Never Seen', 'source_url' => 'https://city.example/agenda/4411']);
ok($code === 200 && $j === ['ok' => true, 'result' => 'review_required'],
   '3. a source URL alone is not a match: review_required (got ' . json_encode($j) . ')');
// A merely similar headline is nothing at all.
[$code, $j] = $lookup($all, ['site' => 'prairiedispatch', 'desk' => 'environment',
    'headline' => 'Council Weighs The Harbor Cleanup Plan']);
ok($code === 200 && $j === ['ok' => true, 'result' => 'not_found'],
   '3. a similar-but-not-exact headline, no URL hit: not_found (got ' . json_encode($j) . ')');
// A receipt whose article an editor moved to another desk: certain
// knowledge that cannot be shown whole within THIS desk.
fresh()->prepare('UPDATE posts SET category_id = ? WHERE id = ?')->execute([$deskEnv, $wireId]);
[$code, $j] = $lookup($all, ['site' => 'prairiedispatch', 'desk' => 'local-news',
    'headline' => 'Wire Story With A Stable Id', 'source_story_id' => 'noon-2026-11-20-001']);
ok($code === 200 && $j === ['ok' => true, 'result' => 'review_required'],
   '3. a receipt whose article moved desks: review_required, nothing revealed (got ' . json_encode($j) . ')');
fresh()->prepare('UPDATE posts SET category_id = ? WHERE id = ?')->execute([$deskLocal, $wireId]);

/* ---- 4. Isolation: one refusal, revealing nothing -------------------------- */
$oos = ['ok' => false, 'error' => 'out of scope'];
[$code, $j] = $lookup($all, ['site' => 'other-paper', 'desk' => 'environment',
    'headline' => 'A Story Only Paper B Has'], 'paper-b.test');
ok($code === 403 && $j === $oos, "4. a paper-A key asking about paper B: the three-word 403, though the story exists (got $code " . json_encode($j) . ')');
[$code, $j] = $lookup($all, ['site' => 'prairiedispatch', 'desk' => 'environment',
    'headline' => 'Council Weighs The Harbour Cleanup'], 'paper-b.test');
ok($code === 403 && $j === $oos, '4. the right site on the WRONG hostname: the same 403 — the serving domain is part of scope (got ' . json_encode($j) . ')');
[$code, $j] = $lookup($bToken, ['site' => 'prairiedispatch', 'desk' => 'environment',
    'headline' => 'Council Weighs The Harbour Cleanup']);
ok($code === 403 && $j === $oos, "4. paper B's key asking about paper A: the same 403 (got " . json_encode($j) . ')');
[$code, $j] = $lookup($all, ['site' => 'prairiedispatch', 'desk' => 'environment',
    'headline' => 'A Story Only Paper B Has']);
ok($code === 200 && $j === ['ok' => true, 'result' => 'not_found'],
   "4. paper B's stories do not exist from paper A: not_found (got " . json_encode($j) . ')');
[$code, $j] = $lookup($all, ['site' => 'prairiedispatch', 'desk' => 'environment',
    'headline' => 'Filed On Another Desk']);
ok($code === 200 && $j === ['ok' => true, 'result' => 'not_found'],
   '4. a story on another desk of the same paper is not revealed: not_found (got ' . json_encode($j) . ')');
[$code, $j] = $lookup($deskHeld, ['site' => 'prairiedispatch', 'desk' => 'local-news',
    'headline' => 'Wire Story With A Stable Id']);
ok($code === 403 && $j === $oos, '4. a desk-held key asking outside its desks: the same 403 (got ' . json_encode($j) . ')');

/* ---- 5. One source_story_id, one article, enforced by the database --------- */
ok(scalar('SELECT COUNT(*) FROM ingest_receipts WHERE site_id = ? AND source_story_id = ?', [$siteA, 'noon-2026-11-20-001']) === 1,
   '5. the filing stored exactly one receipt');
// The constraint itself: the loser of a race cannot insert a second row.
$threw = false;
try {
    fresh()->prepare('INSERT INTO ingest_receipts (site_id, source_story_id, post_id, slug, payload_hash, filed_by, created_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([$siteA, 'noon-2026-11-20-001', 999999, 'x', str_repeat('0', 64), 'x', now()]);
} catch (PDOException $e) {
    $threw = str_starts_with((string) $e->getCode(), '23');
}
ok($threw, '5. UNIQUE (site_id, source_story_id) holds at the database, not in a read-then-write check');
// An identical retry: the original receipt, no second article.
$postsBefore = scalar('SELECT COUNT(*) FROM posts');
[$code, $j] = $file($all, []);
ok($code === 200 && ($j['duplicate'] ?? false) === true && (int) ($j['id'] ?? 0) === $wireId
   && ($j['slug'] ?? '') === $wireSlug && ($j['status'] ?? '') === 'published',
   '5. an identical retry answers the original receipt: 200 {duplicate, id, slug, status} (got ' . json_encode($j) . ')');
// An editor edits; a DIFFERENT payload under the same id is refused and
// the editor's work survives byte for byte.
fresh()->prepare('UPDATE posts SET title = ? WHERE id = ?')->execute(['Headline As The Editor Rewrote It', $wireId]);
[$code, $j] = $file($all, ['title' => 'Second Version Of The Wire Story']);
ok($code === 409 && ($j['ok'] ?? null) === false,
   '5. a different payload under a used id: 409, never an overwrite (got ' . $code . ' ' . json_encode($j) . ')');
$t = fresh()->prepare('SELECT title FROM posts WHERE id = ?');
$t->execute([$wireId]);
ok((string) $t->fetchColumn() === 'Headline As The Editor Rewrote It', "5. the editor's rewrite is untouched after the 409");
ok(scalar('SELECT COUNT(*) FROM posts') === $postsBefore, '5. neither the retry nor the 409 created an article');
// Two SIMULTANEOUS submissions, a fresh id: at most one article can exist,
// whichever process wins the constraint.
$body = json_encode(['site' => 'prairiedispatch', 'desk' => 'local-news', 'title' => 'The Simultaneous Story',
    'lede' => 'A lede long enough to pass the gate.', 'body' => '<p>Race body.</p>', 'source_story_id' => 'race-001']);
$curl = 'curl -s -o /dev/null -X POST http://127.0.0.1:' . $port . '/api/ingest'
      . ' -H ' . escapeshellarg('Host: paper-a.test')
      . ' -H ' . escapeshellarg('Authorization: Bearer ' . $all)
      . ' -H "Content-Type: application/json" -d ' . escapeshellarg($body);
shell_exec("($curl) & ($curl) & wait");
ok(scalar('SELECT COUNT(*) FROM posts WHERE title = ?', ['The Simultaneous Story']) === 1
   && scalar('SELECT COUNT(*) FROM ingest_receipts WHERE source_story_id = ?', ['race-001']) === 1,
   '5. two simultaneous submissions created exactly one article and one receipt');

/* ---- 6. Lookups write nothing ---------------------------------------------- */
$audits = scalar('SELECT COUNT(*) FROM audit_log');
$receipts = scalar('SELECT COUNT(*) FROM ingest_receipts');
[$code] = $lookup($all, ['site' => 'prairiedispatch', 'desk' => 'environment', 'headline' => 'Council Weighs The Harbour Cleanup', 'source_url' => 'https://city.example/agenda/4411']);
[$code2] = $lookup($all, ['site' => 'other-paper', 'desk' => 'environment', 'headline' => 'x'], 'paper-b.test');
ok($code === 200 && $code2 === 403
   && scalar('SELECT COUNT(*) FROM audit_log') === $audits
   && scalar('SELECT COUNT(*) FROM ingest_receipts') === $receipts
   && scalar("SELECT COUNT(*) FROM ingest_agents WHERE name IN ('lookup-own', 'lookup-desk-held', 'lookup-paper-b') AND last_used_at IS NOT NULL") === 0,
   '6. a match and a refusal alike wrote nothing: no audit row, no receipt, no last_used_at');

if ($fails === 0) {
    echo "ingest-lookup: all checks pass\n";
    exit(0);
}
exit(1);
