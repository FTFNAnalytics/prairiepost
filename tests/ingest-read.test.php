<?php
/**
 * GET /api/ingest/stories — an agent reading a paper's stories — end to
 * end over real HTTP on a disposable database.
 *
 *   1. the grant is separate and fail-closed;
 *   2. scope: the key's papers, the serving hostname, the key's desks, and
 *      the statuses the grant covers — one revealing-nothing 403; a story
 *      the key may not read answers 404 like one that does not exist;
 *   3. 'published' sees live stories only; 'all' sees every status,
 *      editors' drafts included; another paper's stories never appear;
 *   4. the list item and the full story carry exactly the documented
 *      fields — no author accounts, editor names, review notes or draft
 *      slugs anywhere in the bytes;
 *   5. pagination, the status filter and updated_since;
 *   6. the loop the read exists for: read a live story, edit it, send the
 *      version back to /api/ingest/update — and a stale version is
 *      refused;
 *   7. reads write nothing.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('ingestread');
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
$pdo = db();
echo 'engine: ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . " ({$fx['id']})\n";

/* ---- Fixtures --------------------------------------------------------------- */
$pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute(['Local News', 'local-news']);
$pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute(['Environment', 'environment']);
// Paper A is a fresh site of its own: the fixture's core seed fills
// prairiedispatch with demo stories, and these assertions are exact.
$pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?,?,?)')->execute(['Paper A', 'paper-a', now()]);
$pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?,?,?)')->execute(['Other Paper', 'other-paper', now()]);
$siteA = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'paper-a'")->fetchColumn();
$siteB = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'other-paper'")->fetchColumn();
$deskLocal = (int) $pdo->query("SELECT id FROM categories WHERE slug = 'local-news'")->fetchColumn();
$deskEnv = (int) $pdo->query("SELECT id FROM categories WHERE slug = 'environment'")->fetchColumn();
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')->execute(['paper-a.test', 'paper-a', now()]);
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')->execute(['paper-b.test', 'other-paper', now()]);
$pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
    ->execute(['Edwina Secretname', 'edwina@test.local', password_hash('x', PASSWORD_DEFAULT), 'editor', 'edwina', now()]);
$editorId = (int) pp_last_id('users');

$mint = function (string $name, string $sites, string $desks, string $read, int $update = 0) use ($pdo): string {
    $t = 'hermes_' . bin2hex(random_bytes(28));
    $pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, draft_only, lookup_scope, update_published, read_scope, created_at) VALUES (?,?,?,?,1,1,?,?,?,?)')
        ->execute([$name, hash('sha256', $t), $sites, $desks, 'all', $update, $read, now()]);
    return $t;
};
$all      = $mint('read-all', 'paper-a', '', 'all', 1);   // reads everything AND may update live stories
$pubOnly  = $mint('read-published', 'paper-a', '', 'published');
$noGrant  = $mint('read-none', 'paper-a', '', '');
$deskHeld = $mint('read-desk-held', 'paper-a', 'environment', 'all');
$bKey     = $mint('read-paper-b', 'other-paper', '', 'all');

$n = 0;
$mkPost = function (array $sites, int $deskId, string $title, string $status, array $over = []) use ($pdo, $editorId, &$n): int {
    $n++;
    $pdo->prepare('INSERT INTO posts (title, slug, category_id, author_id, byline, lede, body, image, image_caption, image_credit,
                                      review_note, post_type, origin, status, filed_by, published_at, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$title, (string) ($over['slug'] ?? 'read-fixture-' . $n), $deskId, $editorId, 'Staff', 'Lede ' . $n, '<p>Body ' . $n . '</p>',
            (string) ($over['image'] ?? ''), '', '', 'PRIVATE REVIEW NOTE ' . $n, 'story', '', $status, 'secret-filer-name',
            $status === 'published' ? '2026-11-20 12:00:00' : null, '2026-11-20 11:00:00', (string) ($over['updated_at'] ?? '2026-11-20 12:00:00')]);
    $id = (int) pp_last_id('posts');
    foreach ($sites as $sid) {
        $pdo->prepare('INSERT INTO post_sites (post_id, site_id) VALUES (?,?)')->execute([$id, $sid]);
    }
    return $id;
};
$live      = $mkPost([$siteA], $deskLocal, 'A Live Story', 'published', ['slug' => 'a-live-story', 'image' => '/uploads/2026/11/live.jpg']);
$draft     = $mkPost([$siteA], $deskLocal, 'An Editor Draft', 'draft', ['slug' => 'secret-draft-slug']);
$review    = $mkPost([$siteA], $deskLocal, 'In Review Copy', 'in_review');
$scheduled = $mkPost([$siteA], $deskEnv, 'Scheduled For Tomorrow', 'scheduled');
$envLive   = $mkPost([$siteA], $deskEnv, 'Live On Environment', 'published', ['slug' => 'live-on-environment', 'updated_at' => '2026-11-22 09:00:00']);
$bStory    = $mkPost([$siteB], $deskLocal, 'Only On Paper B', 'published', ['slug' => 'only-on-paper-b']);
$pdo->prepare('INSERT INTO tags (name, slug) VALUES (?,?)')->execute(['Council', 'council']);
$pdo->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (?,?)')->execute([$live, (int) pp_last_id('tags')]);
$pdo->prepare('INSERT INTO story_sources (post_id, url, title, created_at) VALUES (?,?,?,?)')->execute([$live, 'https://city.example/release/9', 'Release', now()]);

$port = 9400 + random_int(0, 80);
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
$read = fn (string $token, string $query, string $host = 'paper-a.test'): array => api($GLOBALS['port'], 'GET', '/api/ingest/stories?' . $query, $token, null, $host);
$titlesOf = fn ($j): array => is_array($j) ? array_column((array) ($j['stories'] ?? []), 'title') : [];

function scalar(string $sql, array $args = []): int
{
    global $config, $pdo;
    $cfg = require $config;
    $p = ($cfg['db']['driver'] ?? '') === 'sqlite' ? new PDO('sqlite:' . $cfg['db']['sqlite_path']) : $pdo;
    $s = $p->prepare($sql);
    $s->execute($args);
    $r = $s->fetch(PDO::FETCH_NUM);
    $s->closeCursor();
    return $r ? (int) $r[0] : 0;
}
$auditBefore = scalar('SELECT COUNT(*) FROM audit_log');

/* ---- 1. No grant -------------------------------------------------------------- */
[$code, $j] = $read($noGrant, 'site=paper-a');
ok($code === 403 && $j === ['ok' => false, 'error' => 'this token has no read permission',
    'reason' => 'story reading is granted at minting (read_scope published|all); without it this surface answers nothing'],
   "1. a key without the grant: 403 (got $code " . json_encode($j) . ')');

/* ---- 2. Scope -------------------------------------------------------------------- */
$oos = ['ok' => false, 'error' => 'out of scope'];
foreach ([
    'another paper' => [$all, 'site=other-paper', 'paper-b.test'],
    'the right paper on the wrong hostname' => [$all, 'site=paper-a', 'paper-b.test'],
    'paper B\'s key on paper A' => [$bKey, 'site=paper-a', 'paper-a.test'],
    'a desk the key does not hold' => [$deskHeld, 'site=paper-a&desk=local-news', 'paper-a.test'],
    'drafts on a published-only grant' => [$pubOnly, 'site=paper-a&status=draft', 'paper-a.test'],
    'unpublished on a published-only grant' => [$pubOnly, 'site=paper-a&status=unpublished', 'paper-a.test'],
] as $label => [$tok, $query, $host]) {
    [$code, $j] = $read($tok, $query, $host);
    ok($code === 403 && $j === $oos, "2. $label: the three-word 403 (got $code " . json_encode($j) . ')');
}
$draftRef = pp_ingest_article_ref($siteA, $draft, pp_ingest_ref_key());
[$code, $j] = $read($pubOnly, 'site=paper-a&ref=' . $draftRef);
ok($code === 404 && ($j['ok'] ?? null) === false, "2. a published-only key reading a draft by its ref: 404, as if absent (got $code)");
[$code, $j] = $read($all, 'site=paper-a&url=' . rawurlencode('https://paper-b.test/story/only-on-paper-b'));
ok($code === 403 && $j === $oos, "2. a story URL on another paper's host: the same 403 (got $code)");

/* ---- 3. Who sees what -------------------------------------------------------------- */
[$code, $j] = $read($pubOnly, 'site=paper-a');
$t = $titlesOf($j);
sort($t);
ok($code === 200 && $t === ['A Live Story', 'Live On Environment'], "3. 'published' sees exactly the live stories (got $code " . json_encode($t) . ')');
[$code, $j] = $read($all, 'site=paper-a');
$t = $titlesOf($j);
sort($t);
ok($code === 200 && $t === ['A Live Story', 'An Editor Draft', 'In Review Copy', 'Live On Environment', 'Scheduled For Tomorrow'],
   "3. 'all' sees every status on its paper, the editor's draft included, and nothing of paper B (got " . json_encode($t) . ')');
[$code, $j] = $read($deskHeld, 'site=paper-a');
$t = $titlesOf($j);
sort($t);
ok($code === 200 && $t === ['Live On Environment', 'Scheduled For Tomorrow'], "3. a desk-held key without ?desk sees only its desks (got " . json_encode($t) . ')');

/* ---- 4. Exactly the documented fields; nothing about the newsroom ------------------ */
[$code, $j, $raw] = $read($all, 'site=paper-a');
$keysOk = true;
foreach ((array) ($j['stories'] ?? []) as $item) {
    $want = $item['status'] === 'published'
        ? ['ref', 'status', 'desk', 'title', 'url', 'published_at', 'updated_at', 'image', 'version']
        : ['ref', 'status', 'desk', 'title', 'published_at', 'updated_at', 'image', 'version'];
    $keysOk = $keysOk && array_keys($item) === $want && preg_match('/^ar_[0-9a-f]{24}$/', $item['ref']) && preg_match('/^v_[0-9a-f]{16}$/', $item['version']);
}
ok($keysOk, '4. each list item has exactly {ref, status, desk, title, url (published only), published_at, updated_at, image, version}');
foreach (['Edwina', 'PRIVATE REVIEW NOTE', 'secret-filer-name', 'secret-draft-slug', '"id"', 'author'] as $leak) {
    ok(!str_contains($raw, $leak), "4. the list bytes carry no '$leak'");
}
$liveRef = pp_ingest_article_ref($siteA, $live, pp_ingest_ref_key());
[$code, $j, $raw] = $read($all, 'site=paper-a&ref=' . $liveRef);
$st = (array) ($j['story'] ?? []);
ok($code === 200 && array_keys($st) === ['ref', 'status', 'desk', 'title', 'lede', 'body', 'byline', 'dateline', 'image',
    'image_caption', 'image_credit', 'correction', 'tags', 'sources', 'url', 'published_at', 'updated_at', 'version']
   && $st['body'] === '<p>Body 1</p>' && $st['tags'] === ['Council'] && $st['sources'] === ['https://city.example/release/9']
   && $st['url'] === 'https://paper-a.test/story/a-live-story',
   "4. one story in full: the documented fields, body, tags, sources, public url (got $code " . json_encode(array_keys($st)) . ')');
foreach (['Edwina', 'PRIVATE REVIEW NOTE', 'secret-filer-name'] as $leak) {
    ok(!str_contains($raw, $leak), "4. the full story carries no '$leak'");
}
[$code, $j, $raw] = $read($all, 'site=paper-a&ref=' . $draftRef);
ok($code === 200 && ($j['story']['status'] ?? '') === 'draft' && !isset($j['story']['url']) && !str_contains($raw, 'secret-draft-slug'),
   "4. an 'all' key reads the editor's draft in full — with no url and no draft slug (got $code)");
[$code, $j] = $read($all, 'site=paper-a&url=' . rawurlencode('https://paper-a.test/story/a-live-story'));
ok($code === 200 && ($j['story']['ref'] ?? '') === $liveRef, '4. the same story by its public URL');

/* ---- 5. Pagination and filters ------------------------------------------------------ */
[$code, $j] = $read($all, 'site=paper-a&limit=2');
$page1 = $titlesOf($j);
$next = (string) ($j['next'] ?? '');
ok($code === 200 && count($page1) === 2 && preg_match('/^c_\d+$/', $next), "5. limit=2 gives two stories and a next cursor (got " . json_encode($j) . ')');
$seen = $page1;
while ($next !== '') {
    [, $j] = $read($all, 'site=paper-a&limit=2&cursor=' . $next);
    $seen = [...$seen, ...$titlesOf($j)];
    $next = (string) ($j['next'] ?? '');
}
ok(count($seen) === 5 && count(array_unique($seen)) === 5, '5. walking the cursor visits all five stories, each once (got ' . json_encode($seen) . ')');
[, $j] = $read($all, 'site=paper-a&status=unpublished');
$t = $titlesOf($j);
sort($t);
ok($t === ['An Editor Draft', 'In Review Copy', 'Scheduled For Tomorrow'], '5. status=unpublished: draft, review and scheduled (got ' . json_encode($t) . ')');
[, $j] = $read($all, 'site=paper-a&updated_since=' . rawurlencode('2026-11-21 00:00:00'));
ok($titlesOf($j) === ['Live On Environment'], '5. updated_since keeps only the story touched since (got ' . json_encode($titlesOf($j)) . ')');
foreach (['status=bogus' => 'status must be one of', 'limit=0' => 'limit must be 1 to 100', 'cursor=nope' => 'cursor must be',
          'updated_since=not-a-date' => 'updated_since must be', 'ref=ar_bad' => 'ref must be'] as $query => $needle) {
    [$code, $j] = $read($all, 'site=paper-a&' . $query);
    ok($code === 422 && str_contains((string) ($j['error'] ?? ''), $needle), "5. $query: 422 (got $code)");
}

/* ---- 6. Read, edit, update with the version — and a stale version refused ----------- */
[, $j] = $read($all, 'site=paper-a&ref=' . $liveRef);
$v1 = (string) ($j['story']['version'] ?? '');
[$code, $u] = api($port, 'POST', '/api/ingest/update', $all, ['site' => 'paper-a', 'desk' => 'local-news', 'ref' => $liveRef,
    'body' => '<p>Body 1, corrected by the agent after reading it.</p>', 'if_version' => $v1]);
ok($code === 200 && ($u['updated'] ?? null) === ['body'], "6. the version from the read lets the update through (got $code " . json_encode($u) . ')');
[$code, $u] = api($port, 'POST', '/api/ingest/update', $all, ['site' => 'paper-a', 'desk' => 'local-news', 'ref' => $liveRef,
    'title' => 'Would Clobber The Newer Body', 'if_version' => $v1]);
ok($code === 409, "6. the same, now-stale version is refused: 409 (got $code)");
[, $j] = $read($all, 'site=paper-a&ref=' . $liveRef);
ok(($j['story']['body'] ?? '') === '<p>Body 1, corrected by the agent after reading it.</p>' && ($j['story']['title'] ?? '') === 'A Live Story'
   && ($j['story']['version'] ?? '') !== $v1,
   '6. a fresh read shows the agent\'s body, the untouched title, and a new version');

/* ---- 7. Reads write nothing -------------------------------------------------------- */
ok(scalar("SELECT COUNT(*) FROM audit_log WHERE action <> 'ingest-update'") === $auditBefore
   && scalar("SELECT COUNT(*) FROM ingest_agents WHERE name IN ('read-published', 'read-none', 'read-desk-held', 'read-paper-b') AND last_used_at IS NOT NULL") === 0,
   '7. every read above wrote nothing: no audit row, no last_used_at');

if ($fails === 0) {
    echo "ingest-read: all checks pass\n";
    exit(0);
}
exit(1);
