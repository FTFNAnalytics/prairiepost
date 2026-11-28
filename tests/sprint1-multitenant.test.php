<?php
/**
 * Sprint 1 regression suite (Schema v27):
 * 1. Per-site placement (post_sites.placement) across syndicated stories.
 * 2. Tenant-scoped RBAC (site_memberships + users.is_network_admin + can_publish).
 * 3. Automatic 301 redirects (slug_redirects) on intentional live slug changes.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('sprint1');
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

// Two papers on one shared DB, routed by Host header via `domains`.
$paperAId = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'prairiedispatch'")->fetchColumn();
$pdo->prepare("INSERT INTO sites (name, slug, domain, created_at) VALUES ('Sister Gazette', 'sister-gazette', 'sister.test', ?)")
    ->execute([now()]);
$paperBId = pp_last_id('sites');
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?, ?, ?)')->execute(['paper-a.test', 'prairiedispatch', now()]);
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?, ?, ?)')->execute(['paper-b.test', 'sister-gazette', now()]);
$pdo->prepare("INSERT INTO settings (site_id, skey, svalue) VALUES (?, 'site_title', 'Sister Gazette')")->execute([$paperBId]);

$mkUser = function (string $name, string $role, int $netAdmin = 0) use ($pdo): array {
    $email = strtolower(preg_replace('/[^a-z0-9]/i', '', $name)) . '@test.local';
    $pdo->prepare('INSERT INTO users (name, email, pass_hash, role, is_network_admin, slug, created_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([$name, $email, password_hash('correct horse pp', PASSWORD_DEFAULT), $role, $netAdmin, slugify($name), now()]);
    return ['id' => pp_last_id('users'), 'email' => $email];
};

$netAdmin = $mkUser('Net Admin', 'admin', 1);

$mkPost = function (string $title, string $slug, string $status, int $authorId, array $sitePlacements) use ($pdo): int {
    $pub = $status === 'published' ? now() : null;
    $pdo->prepare('INSERT INTO posts (title, slug, body, lede, status, author_id, published_at, created_at, updated_at)
                   VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$title, $slug, '<p>Story body text.</p>', 'Story lede.', $status, $authorId, $pub, now(), now()]);
    $id = pp_last_id('posts');
    set_post_sites($id, array_keys($sitePlacements), $sitePlacements);
    pp_sync_legacy_post_placement($id);
    pp_post_snapshot($id, 'create', 'fixture');
    return $id;
};

$heroA   = $mkPost('Paper A Initial Hero', 'paper-a-initial-hero', 'published', $netAdmin['id'], [$paperAId => 'hero']);
$heroB   = $mkPost('Paper B Initial Hero', 'paper-b-initial-hero', 'published', $netAdmin['id'], [$paperBId => 'hero']);
$shared  = $mkPost('Syndicated Budget Story', 'syndicated-budget-story', 'published', $netAdmin['id'], [
    $paperAId => '',
    $paperBId => '',
]);
$onlyB   = $mkPost('Paper B Exclusive', 'paper-b-exclusive', 'draft', $netAdmin['id'], [$paperBId => '']);
$onlyA   = $mkPost('Paper A Local Draft', 'paper-a-local-draft', 'draft', $netAdmin['id'], [$paperAId => '']);

// Start dev server.
$port = 8500 + random_int(0, 80);
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

$base = 'http://127.0.0.1:' . $port;

function s1_http(string $method, string $url, array $fields, string $jar, string $base, string $host = 'paper-a.test'): array
{
    $ch = curl_init($base . $url);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Host: ' . $host],
        CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$headers): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($line);
        },
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body, $headers];
}

$login = function (string $email, string $host = 'paper-a.test') use ($work, $base): array {
    $jar = $work . '/jar-' . md5($email . '|' . $host);
    [, $page] = s1_http('GET', '/admin/login.php', [], $jar, $base, $host);
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
    $csrf = $m[1] ?? '';
    [$code] = s1_http('POST', '/admin/login.php', ['csrf' => $csrf, 'email' => $email, 'password' => 'correct horse pp'], $jar, $base, $host);
    return [$jar, $csrf, $code];
};

$psMap = function (int $postId) use ($pdo): array {
    $s = $pdo->prepare('SELECT site_id, placement FROM post_sites WHERE post_id = ?');
    $s->execute([$postId]);
    $out = [];
    foreach ($s as $r) {
        $out[(int) $r['site_id']] = (string) $r['placement'];
    }
    return $out;
};

[$jarNet, $csrfNet, $cNet] = $login($netAdmin['email'], 'paper-a.test');
ok($cNet === 302, "network admin signs in (got $cNet)");

/* =========================================================================
 * 1. Per-site placement (post_sites.placement)
 * ========================================================================= */

// Set $shared as 'hero' on Paper A and 'featured' on Paper B in a single edit.
[$code] = s1_http('POST', "/admin/post-edit.php?id=$shared", [
    'csrf' => $csrfNet,
    'title' => 'Syndicated Budget Story',
    'lede' => 'Story lede.',
    'body' => '<p>Story body text.</p>',
    'status' => 'published',
    'placement' => 'hero',
    'sites' => [$paperAId, $paperBId],
    'site_placement' => [$paperBId => 'featured'],
], $jarNet, $base, 'paper-a.test');
ok($code === 302, "per-site placement save succeeded (got $code)");

$sharedPl = $psMap($shared);
ok(($sharedPl[$paperAId] ?? null) === 'hero', 'syndicated story is hero on Paper A');
ok(($sharedPl[$paperBId] ?? null) === 'featured', 'same syndicated story is featured on Paper B');
ok(($psMap($heroA)[$paperAId] ?? null) === '', 'Paper A previous hero was cleared');
ok(($psMap($heroB)[$paperBId] ?? null) === 'hero', 'Paper B hero stayed completely untouched');

// Verify via Paper A's posts.php "Make hero" button on $heroA:
// restores $heroA on Paper A without touching $shared's featured slot on Paper B.
[$code] = s1_http('POST', '/admin/posts.php', [
    'csrf' => $csrfNet,
    'action' => 'feature',
    'id' => $heroA,
], $jarNet, $base, 'paper-a.test');
ok($code === 302, 'posts.php Make hero succeeded');
ok(($psMap($heroA)[$paperAId] ?? null) === 'hero', 'Paper A hero restored');
ok(($psMap($shared)[$paperAId] ?? null) === '', 'syndicated story hero cleared on Paper A');
ok(($psMap($shared)[$paperBId] ?? null) === 'featured', 'syndicated story featured placement preserved on Paper B');

// Verify public front_featured_posts & featured_post honor ps.placement per site.
$featuredA = featured_post();
ok($featuredA !== null && (int) $featuredA['id'] === $heroA, 'Paper A featured_post() returns Paper A hero');
$frontFeatA = front_featured_posts([$heroA]);
ok(!in_array($shared, array_map('intval', array_column($frontFeatA, 'id')), true), 'syndicated story is NOT in Paper A featured band');

/* =========================================================================
 * 2. Tenant-scoped RBAC (site_memberships + is_network_admin + can_publish)
 * ========================================================================= */

// Create three tenant-scoped users via admin/users.php:
// a) Paper A editor WITH publish permission
[$code] = s1_http('POST', '/admin/users.php', [
    'csrf' => $csrfNet,
    'action' => 'save',
    'id' => 0,
    'name' => 'Paper A Editor',
    'email' => 'editor-a@test.local',
    'role' => 'author',
    'password' => 'correct horse pp',
    'memberships' => [
        $paperAId => ['role' => 'editor', 'can_publish' => '1'],
    ],
], $jarNet, $base, 'paper-a.test');
ok($code === 302, 'created Paper A editor via admin/users.php');

// b) Paper A copy editor WITHOUT publish permission
[$code] = s1_http('POST', '/admin/users.php', [
    'csrf' => $csrfNet,
    'action' => 'save',
    'id' => 0,
    'name' => 'Paper A Copy Desk',
    'email' => 'copydesk-a@test.local',
    'role' => 'author',
    'password' => 'correct horse pp',
    'memberships' => [
        $paperAId => ['role' => 'editor'],
    ],
], $jarNet, $base, 'paper-a.test');
ok($code === 302, 'created Paper A copy-editor (no publish) via admin/users.php');

// c) Paper A local admin (NOT network admin)
[$code] = s1_http('POST', '/admin/users.php', [
    'csrf' => $csrfNet,
    'action' => 'save',
    'id' => 0,
    'name' => 'Paper A Local Admin',
    'email' => 'admin-a@test.local',
    'role' => 'author',
    'password' => 'correct horse pp',
    'memberships' => [
        $paperAId => ['role' => 'admin', 'can_publish' => '1'],
    ],
], $jarNet, $base, 'paper-a.test');
ok($code === 302, 'created Paper A local admin via admin/users.php');

// Test Paper A Editor on Paper A vs Paper B vs Users admin
[$jarEdA, $csrfEdA, $cEdA] = $login('editor-a@test.local', 'paper-a.test');
ok($cEdA === 302, 'Paper A editor signs in on Paper A');

[$code, $body] = s1_http('GET', '/admin/posts.php', [], $jarEdA, $base, 'paper-a.test');
ok($code === 200 && str_contains($body, 'Paper A Local Draft') && !str_contains($body, 'Paper B Exclusive'),
    'Paper A editor sees Paper A stories and not Paper B exclusives');

[, , $cEdB] = $login('editor-a@test.local', 'paper-b.test');
ok($cEdB === 403, "Paper A editor is refused sign-in on Paper B (got $cEdB)");

[$code] = s1_http('GET', "/admin/post-edit.php?id=$onlyB", [], $jarEdA, $base, 'paper-a.test');
ok($code === 403, "Paper A editor cannot open Paper B exclusive story by ID (got $code)");

[$code] = s1_http('GET', '/admin/users.php', [], $jarEdA, $base, 'paper-a.test');
ok($code === 403, "Paper A editor cannot access network accounts page (got $code)");

// Paper A editor tries to forge syndication onto Paper B when publishing $onlyA
[$code] = s1_http('POST', "/admin/post-edit.php?id=$onlyA", [
    'csrf' => $csrfEdA,
    'title' => 'Paper A Local Story Live',
    'lede' => 'Lede.',
    'body' => '<p>Body.</p>',
    'status' => 'published',
    'sites' => [$paperAId, $paperBId],
], $jarEdA, $base, 'paper-a.test');
ok($code === 302, 'Paper A editor publishes Paper A story');
$onlyASites = array_keys($psMap($onlyA));
ok($onlyASites === [$paperAId], 'forged syndication onto Paper B was stripped for Paper A editor');
$onlyARow = $pdo->query("SELECT status FROM posts WHERE id = $onlyA")->fetch();
ok(($onlyARow['status'] ?? '') === 'published', 'Paper A editor with can_publish=1 published the story');

// Test Paper A Copy Desk (editor with can_publish=0)
[$jarCopy, $csrfCopy, $cCopy] = $login('copydesk-a@test.local', 'paper-a.test');
ok($cCopy === 302, 'Paper A copy-editor signs in');
$copyDraft = $mkPost('Copy Desk Target', 'copy-desk-target', 'draft', $netAdmin['id'], [$paperAId => '']);
[$code] = s1_http('POST', "/admin/post-edit.php?id=$copyDraft", [
    'csrf' => $csrfCopy,
    'title' => 'Copy Desk Edited',
    'lede' => 'Lede.',
    'body' => '<p>Cleaned copy.</p>',
    'status' => 'published', // must be downgraded because can_publish=0
], $jarCopy, $base, 'paper-a.test');
ok($code === 302, 'copy-editor can edit another author draft on Paper A');
$copyRow = $pdo->query("SELECT title, status FROM posts WHERE id = $copyDraft")->fetch();
ok(($copyRow['title'] ?? '') === 'Copy Desk Edited' && ($copyRow['status'] ?? '') === 'draft',
    'copy-editor with can_publish=0 saved edits but could not publish');

// Test Paper A Local Admin (site admin on Paper A, NOT network admin)
[$jarAdmA, , $cAdmA] = $login('admin-a@test.local', 'paper-a.test');
ok($cAdmA === 302, 'Paper A local admin signs in');
[$code] = s1_http('GET', '/admin/settings.php', [], $jarAdmA, $base, 'paper-a.test');
ok($code === 200, "Paper A local admin accesses Paper A settings (got $code)");
[$code] = s1_http('GET', '/admin/users.php', [], $jarAdmA, $base, 'paper-a.test');
ok($code === 403, "Paper A local admin is refused network-wide users.php (got $code)");
[, , $cAdmB] = $login('admin-a@test.local', 'paper-b.test');
ok($cAdmB === 403, "Paper A local admin is refused sign-in on Paper B (got $cAdmB)");

// Reassign Paper A local admin to Paper B only: their existing Paper A session is immediately refused with 403!
$admAId = (int) $pdo->query("SELECT id FROM users WHERE email = 'admin-a@test.local'")->fetchColumn();
pp_set_user_memberships($admAId, [$paperBId => ['role' => 'admin', 'can_publish' => 1]]);
[$code] = s1_http('GET', '/admin/settings.php', [], $jarAdmA, $base, 'paper-a.test');
ok($code === 403, "active session immediately blocked (403) when paper membership is moved away (got $code)");

/* =========================================================================
 * 3. Automatic 301 Slug Redirects (slug_redirects)
 * ========================================================================= */

$redirPost = $mkPost('Original City Budget', 'original-city-budget', 'published', $netAdmin['id'], [$paperAId => '']);

// Headline edit without changing slug field keeps original slug and writes zero redirects.
[$code] = s1_http('POST', "/admin/post-edit.php?id=$redirPost", [
    'csrf' => $csrfNet,
    'title' => 'Updated City Budget Headline',
    'slug' => 'original-city-budget',
    'lede' => 'Lede.',
    'body' => '<p>Body.</p>',
    'status' => 'published',
    'sites' => [$paperAId],
], $jarNet, $base, 'paper-a.test');
ok($code === 302, 'headline-only edit on published story succeeded');
ok((string) $pdo->query("SELECT slug FROM posts WHERE id = $redirPost")->fetchColumn() === 'original-city-budget',
    'headline edit without slug change preserved live slug');
ok((int) $pdo->query("SELECT COUNT(*) FROM slug_redirects WHERE post_id = $redirPost")->fetchColumn() === 0,
    'no redirect row created when slug did not change');

// Editor intentionally changes slug -> new URL serves 200 and old URL returns 301!
[$code] = s1_http('POST', "/admin/post-edit.php?id=$redirPost", [
    'csrf' => $csrfNet,
    'title' => 'Updated City Budget Headline',
    'slug' => 'revised-city-budget-2026',
    'lede' => 'Lede.',
    'body' => '<p>Body.</p>',
    'status' => 'published',
    'sites' => [$paperAId],
], $jarNet, $base, 'paper-a.test');
ok($code === 302, 'intentional slug change on published story succeeded');
ok((string) $pdo->query("SELECT slug FROM posts WHERE id = $redirPost")->fetchColumn() === 'revised-city-budget-2026',
    'post slug updated to requested value');

[$codeNew] = s1_http('GET', '/story/revised-city-budget-2026', [], $work . '/jar-anon', $base, 'paper-a.test');
ok($codeNew === 200, "new slug serves HTTP 200 (got $codeNew)");

[$codeOld, , $hdrOld] = s1_http('GET', '/story/original-city-budget', [], $work . '/jar-anon', $base, 'paper-a.test');
ok($codeOld === 301 && ($hdrOld['location'] ?? '') === '/story/revised-city-budget-2026',
    "old slug returns 301 to /story/revised-city-budget-2026 (got $codeOld, loc=" . ($hdrOld['location'] ?? '') . ')');

// Old slug does NOT redirect on Paper B (story only runs on Paper A).
[$codeOldB] = s1_http('GET', '/story/original-city-budget', [], $work . '/jar-anon', $base, 'paper-b.test');
ok($codeOldB === 404, "old slug is 404 on Paper B where story does not run (got $codeOldB)");

// Rename back (A -> B -> A): zero redirect loops!
[$code] = s1_http('POST', "/admin/post-edit.php?id=$redirPost", [
    'csrf' => $csrfNet,
    'title' => 'Updated City Budget Headline',
    'slug' => 'original-city-budget',
    'lede' => 'Lede.',
    'body' => '<p>Body.</p>',
    'status' => 'published',
    'sites' => [$paperAId],
], $jarNet, $base, 'paper-a.test');
ok($code === 302, 'renaming slug back succeeded');

[$codeBack] = s1_http('GET', '/story/original-city-budget', [], $work . '/jar-anon', $base, 'paper-a.test');
[$codeFwd, , $hdrFwd] = s1_http('GET', '/story/revised-city-budget-2026', [], $work . '/jar-anon', $base, 'paper-a.test');
ok($codeBack === 200, "reverted slug serves 200 without redirect loop (got $codeBack)");
ok($codeFwd === 301 && ($hdrFwd['location'] ?? '') === '/story/original-city-budget',
    "intermediate slug now 301-redirects back to original-city-budget (got $codeFwd)");

// Unpublishing suppresses the redirect (never leaks draft slugs).
$pdo->exec("UPDATE posts SET status = 'draft' WHERE id = $redirPost");
[$codeDraftRedir] = s1_http('GET', '/story/revised-city-budget-2026', [], $work . '/jar-anon', $base, 'paper-a.test');
ok($codeDraftRedir === 404, "unpublished story redirect returns 404 (got $codeDraftRedir)");

// Deleting the story removes its slug_redirects rows.
[$code] = s1_http('POST', '/admin/posts.php', [
    'csrf' => $csrfNet,
    'action' => 'delete',
    'id' => $redirPost,
], $jarNet, $base, 'paper-a.test');
ok($code === 302 && (int) $pdo->query("SELECT COUNT(*) FROM slug_redirects WHERE post_id = $redirPost")->fetchColumn() === 0,
    'deleting a story removes its slug_redirects rows');

exit($fails ? 1 : 0);
