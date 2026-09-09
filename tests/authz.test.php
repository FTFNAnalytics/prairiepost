<?php
/**
 * Editorial write authorization (F02), over real HTTP against the dev
 * server on a disposable database: the role/action/state matrix for edit,
 * autosave, revision restore and delete; CSRF; public invisibility of
 * drafts; and the publish races — an author's stale write must never land
 * on a story an editor published or scheduled in the meantime.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('authz');
pp_fixture_prepare($fx);            // migrate --apply + seed-core, explicitly
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

// ---- Fixtures: three roles, one story per state, one revision each ------
putenv('PP_CONFIG=' . $config);
define('PP_TEST_BOOT', 1);
require $root . '/app/bootstrap.php';
$pdo = db(); // readiness-gated handle onto the prepared fixture

$mkUser = function (string $name, string $role) use ($pdo): array {
    $email = strtolower($role) . '-' . strtolower(str_replace(' ', '', $name)) . '@test.local';
    $pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
        ->execute([$name, $email, password_hash('correct horse pp', PASSWORD_DEFAULT), $role, slugify($name . '-' . $role), now()]);
    return ['id' => pp_last_id('users'), 'email' => $email];
};
$admin   = $mkUser('Ada Admin', 'admin');
$editor  = $mkUser('Ed Editor', 'editor');
$author1 = $mkUser('Al Author', 'author');
$author2 = $mkUser('Bo Author', 'author');

$mkPost = function (string $status, int $authorId, string $marker) use ($pdo): array {
    $slug = 'authz-' . $status . '-' . bin2hex(random_bytes(3));
    $pub = $status === 'published' ? now() : ($status === 'scheduled' ? date('Y-m-d H:i:s', time() + 86400) : null);
    $pdo->prepare('INSERT INTO posts (title, slug, body, lede, status, author_id, published_at, created_at, updated_at)
                   VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute(["Original $status title", $slug, "<p>$marker original body</p>", 'Original lede.', $status, $authorId, $pub, now(), now()]);
    $id = pp_last_id('posts');
    pp_post_snapshot($id, 'create', 'fixture');
    return ['id' => $id, 'slug' => $slug];
};
$pDraft  = $mkPost('draft', (int) $author1['id'], 'DRAFTBODY');
$pReview = $mkPost('in_review', (int) $author1['id'], 'REVIEWBODY');
$pPub    = $mkPost('published', (int) $author1['id'], 'PUBBODY');
$pSched  = $mkPost('scheduled', (int) $author1['id'], 'SCHEDBODY');
$pOther  = $mkPost('draft', (int) $author2['id'], 'OTHERBODY');
$pDel    = $mkPost('draft', (int) $author1['id'], 'DELBODY');
$pDelPub = $mkPost('published', (int) $author1['id'], 'DELPUBBODY');

$revOf = function (int $postId) use ($pdo): int {
    $s = $pdo->prepare('SELECT id FROM post_revisions WHERE post_id = ? ORDER BY id LIMIT 1');
    $s->execute([$postId]);
    return (int) $s->fetchColumn();
};
$row = function (int $postId) use ($pdo): array {
    $s = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
    $s->execute([$postId]);
    return $s->fetch() ?: [];
};

// ---- The dev server ------------------------------------------------------
$port = 8400 + random_int(0, 90);
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

function http(string $method, string $url, array $fields, string $jar, string $base): array
{
    $ch = curl_init($base . $url);
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
    return [$code, $body];
}

$base = 'http://127.0.0.1:' . $port;
$login = function (string $email) use ($work, $base): array {
    $jar = $work . '/jar-' . md5($email);
    [, $page] = http('GET', '/admin/login.php', [], $jar, $base);
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
    $csrf = $m[1] ?? '';
    [$code] = http('POST', '/admin/login.php', ['csrf' => $csrf, 'email' => $email, 'password' => 'correct horse pp'], $jar, $base);
    return [$jar, $csrf, $code];
};

[$jarAdmin, $csrfAdmin, $c1] = $login($admin['email']);
[$jarEditor, $csrfEditor, $c2] = $login($editor['email']);
[$jarA1, $csrfA1, $c3] = $login($author1['email']);
ok($c1 === 302 && $c2 === 302 && $c3 === 302, "all three roles sign in (got $c1/$c2/$c3)");

$edit = fn (string $jar, string $csrf, int $id, array $over = []) => http('POST', "/admin/post-edit.php?id=$id",
    array_merge(['csrf' => $csrf, 'title' => 'EDITED title', 'body' => '<p>EDITED body</p>', 'lede' => 'x', 'status' => 'draft'], $over),
    $jar, $base);

/* --- Matrix: author on their own stories --------------------------------- */

[$code] = $edit($jarA1, $csrfA1, $pDraft['id']);
ok($code === 302 && $row($pDraft['id'])['title'] === 'EDITED title', 'author edits own draft');

[$code] = $edit($jarA1, $csrfA1, $pReview['id'], ['status' => 'in_review']);
ok($code === 302 && $row($pReview['id'])['title'] === 'EDITED title', 'author edits own in-review story');

[$code] = $edit($jarA1, $csrfA1, $pDraft['id'], ['status' => 'published', 'title' => 'SNEAK publish']);
$r = $row($pDraft['id']);
ok(!in_array($r['status'], ['published', 'scheduled'], true), 'author cannot publish through the status field');

[$code, $body] = $edit($jarA1, $csrfA1, $pPub['id'], ['title' => 'AUTHOR rewrite of live story']);
$r = $row($pPub['id']);
ok($r['title'] === "Original published title" && $r['status'] === 'published' && str_contains($r['body'], 'PUBBODY'),
    'author edit of own PUBLISHED story changes nothing');
ok($code !== 302, 'and is refused, not silently accepted');

[$code] = $edit($jarA1, $csrfA1, $pSched['id'], ['title' => 'AUTHOR rewrite of scheduled']);
$r = $row($pSched['id']);
ok($r['title'] === "Original scheduled title" && $r['status'] === 'scheduled', 'author edit of own SCHEDULED story changes nothing');

[$code] = $edit($jarA1, $csrfA1, $pOther['id'], ['title' => 'CROSS-AUTHOR edit']);
$r = $row($pOther['id']);
ok($code === 403 && str_contains($r['body'], 'OTHERBODY'), "author cannot edit another author's story");

/* --- Autosave ------------------------------------------------------------- */

[, $body] = http('POST', '/admin/autosave.php', ['csrf' => $csrfA1, 'id' => $pPub['id'], 'title' => 'AUTOSAVE onto live', 'body' => '<p>autosave</p>'], $jarA1, $base);
$r = $row($pPub['id']);
ok(str_contains($body, 'not saved') && str_contains($r['body'], 'PUBBODY'), 'author autosave onto own published story lands nowhere');

[, $body] = http('POST', '/admin/autosave.php', ['csrf' => $csrfA1, 'id' => $pOther['id'], 'title' => 'x', 'body' => '<p>y</p>'], $jarA1, $base);
ok(str_contains($body, 'not saved') && str_contains($row($pOther['id'])['body'], 'OTHERBODY'), "author autosave onto another author's story lands nowhere");

[, $body] = http('POST', '/admin/autosave.php', ['csrf' => $csrfA1, 'id' => $pDraft['id'], 'title' => 'Autosaved draft', 'body' => '<p>autosaved</p>'], $jarA1, $base);
ok(str_contains($body, 'saved') && !str_contains($body, 'not saved') && $row($pDraft['id'])['title'] === 'Autosaved draft',
    'author autosave onto own draft works');

/* --- Revision restore (the original F02 bypass) --------------------------- */

[$code] = http('POST', '/admin/revision.php', ['csrf' => $csrfA1, 'id' => $revOf($pPub['id']), 'action' => 'restore'], $jarA1, $base);
$r = $row($pPub['id']);
ok($code === 403, 'author restore onto own PUBLISHED story is refused outright');
ok(str_contains($r['body'], 'PUBBODY') && $r['status'] === 'published', 'and the live story is untouched');

[$code] = http('POST', '/admin/revision.php', ['csrf' => $csrfA1, 'id' => $revOf($pSched['id']), 'action' => 'restore'], $jarA1, $base);
ok($code === 403 && $row($pSched['id'])['status'] === 'scheduled', 'author restore onto own SCHEDULED story is refused');

[$code] = http('POST', '/admin/revision.php', ['csrf' => $csrfA1, 'id' => $revOf($pDraft['id']), 'action' => 'restore'], $jarA1, $base);
ok($code === 302 && $row($pDraft['id'])['title'] === 'Original draft title', 'author restore of own draft works');

[$code] = http('POST', '/admin/revision.php', ['csrf' => $csrfEditor, 'id' => $revOf($pPub['id']), 'action' => 'restore'], $jarEditor, $base);
ok($code === 302, 'editor restore of a published story remains authorized');

/* --- Editor and admin keep their workflow --------------------------------- */

[$code] = $edit($jarEditor, $csrfEditor, $pPub['id'], ['title' => 'EDITOR retitle', 'status' => 'published']);
$r = $row($pPub['id']);
ok($code === 302 && $r['title'] === 'EDITOR retitle' && $r['status'] === 'published', 'editor edits a published story');

[$code] = $edit($jarAdmin, $csrfAdmin, $pSched['id'], ['title' => 'ADMIN retitle', 'status' => 'scheduled', 'published_at' => date('Y-m-d H:i', time() + 86400)]);
ok($code === 302 && $row($pSched['id'])['title'] === 'ADMIN retitle', 'admin edits a scheduled story');

/* --- Delete ---------------------------------------------------------------- */

http('POST', '/admin/posts.php', ['csrf' => $csrfA1, 'action' => 'delete', 'id' => $pDelPub['id']], $jarA1, $base);
ok($row($pDelPub['id']) !== [], 'author cannot delete own published story');
http('POST', '/admin/posts.php', ['csrf' => $csrfA1, 'action' => 'delete', 'id' => $pDel['id']], $jarA1, $base);
ok($row($pDel['id']) === [], 'author deletes own draft');

/* --- CSRF ------------------------------------------------------------------ */

[$code] = $edit($jarA1, 'deadbeef' . str_repeat('0', 32), $pDraft['id'], ['title' => 'CSRF-FORGED']);
ok($code === 403 && $row($pDraft['id'])['title'] !== 'CSRF-FORGED', 'a forged CSRF token changes nothing');

/* --- Drafts stay private ---------------------------------------------------- */

[$code, $body] = http('GET', '/story/' . $pDraft['slug'], [], $work . '/jar-anon', $base);
ok($code === 404 || !str_contains($body, 'Autosaved draft'), 'a draft is not served publicly');

/* --- The races -------------------------------------------------------------- */

// Author's browser has the draft open; an editor publishes it; the author's
// stale save then arrives. The persisted-state check and the conditional
// UPDATE both stand between it and the live story.
$pRace = $mkPost('draft', (int) $author1['id'], 'RACEBODY');
$pdo->prepare("UPDATE posts SET status = 'published', published_at = ? WHERE id = ?")->execute([now(), $pRace['id']]);
[$code] = $edit($jarA1, $csrfA1, $pRace['id'], ['title' => 'STALE author save']);
$r = $row($pRace['id']);
ok($code !== 302 && $r['title'] !== 'STALE author save' && $r['status'] === 'published',
    'race: stale author save after editor publish lands nowhere');

$pRace2 = $mkPost('draft', (int) $author1['id'], 'RACE2BODY');
$pdo->prepare("UPDATE posts SET status = 'published', published_at = ? WHERE id = ?")->execute([now(), $pRace2['id']]);
[, $body] = http('POST', '/admin/autosave.php', ['csrf' => $csrfA1, 'id' => $pRace2['id'], 'title' => 'STALE autosave', 'body' => '<p>stale</p>'], $jarA1, $base);
ok(str_contains($body, 'not saved') && str_contains($row($pRace2['id'])['body'], 'RACE2BODY'),
    'race: stale autosave after editor publish lands nowhere');

// The atomic guard itself, across two live database connections: the
// second connection publishes between the first connection's read and its
// conditional write — zero rows may match.
$pRace3 = $mkPost('draft', (int) $author1['id'], 'RACE3BODY');
$pdo2 = pp_fixture_connect($fx);   // a second, independent connection to the same engine
$pdo2->exec("UPDATE posts SET status = 'scheduled', published_at = '2030-01-01 06:00:00' WHERE id = " . (int) $pRace3['id']);
$wrote = pp_guarded_post_update((int) $pRace3['id'], ['title' => 'GUARD should refuse'], ['draft', 'in_review']);
ok($wrote === false && $row($pRace3['id'])['title'] !== 'GUARD should refuse',
    'guarded update refuses once another connection moved the state');
$wrote = pp_guarded_post_update((int) $pRace3['id'], ['title' => 'GUARD editor path'], null);
ok($wrote === true && $row($pRace3['id'])['title'] === 'GUARD editor path',
    'unconditional (editor) path still writes');

exit($fails ? 1 : 0);
