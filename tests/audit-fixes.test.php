<?php
/**
 * Regression suite for the six core audit fixes:
 *  1. Site-scoped hero promotion (promoting a hero on paper A never clears paper B's hero).
 *  2. Published/scheduled slug stability (headline edits never silently break live URLs).
 *  3. Admin-only raw HTML embed ads + http(s)/local URL scheme validation on story edits & redirects.
 *  4. /subscribe per-IP sliding-window rate limiting (HTTP 429 past threshold).
 *  5. Per-paper timezone evaluation for daily newsletter send hour and edition date.
 *  6. Admin posts case-insensitive search (pp_like), per-paper default scoping, and author scheduled-delete UI check.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('auditfixes');
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
echo 'engine: ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . ' server '
   . (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . " ({$fx['id']})\n";

// Seed two papers with different timezones (plus default prairiedispatch)
foreach (['kermode-chronicle', 'kitchener-chronicle'] as $slug) {
    pp_fixture_tool($fx, 'tools/seed-launch.php', 'PP_SITE=' . escapeshellarg($slug));
}
$paperAId = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'prairiedispatch'")->fetchColumn();
$paperBId = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'kitchener-chronicle'")->fetchColumn();

$mkUser = function (string $name, string $role) use ($pdo): array {
    $email = strtolower($role) . '-af@test.local';
    $pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
        ->execute([$name, $email, password_hash('correct horse pp', PASSWORD_DEFAULT), $role, slugify($name), now()]);
    return ['id' => pp_last_id('users'), 'email' => $email];
};
$admin  = $mkUser('Ada Admin', 'admin');
$editor = $mkUser('Ed Editor', 'editor');
$author = $mkUser('Al Author', 'author');

$mkPost = function (string $title, string $status, int $authorId, array $siteIds, string $placement = '', string $extra = '') use ($pdo): array {
    $slug = slugify($title) . '-' . bin2hex(random_bytes(3));
    $pub = $status === 'published' ? now() : ($status === 'scheduled' ? date('Y-m-d H:i:s', time() + 86400) : null);
    $pdo->prepare('INSERT INTO posts (title, slug, body, lede, status, placement, author_id, published_at, created_at, updated_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([$title, $slug, '<p>Body text.</p>', 'Lede text.', $status, $placement, $authorId, $pub, now(), now()]);
    $id = pp_last_id('posts');
    set_post_sites($id, $siteIds);
    return ['id' => $id, 'slug' => $slug];
};

$heroA1 = $mkPost('Calgary Budget Hero One', 'published', (int) $editor['id'], [$paperAId], 'hero');
$storyA2 = $mkPost('Prairie Second Candidate', 'published', (int) $editor['id'], [$paperAId], '');
$storyA3 = $mkPost('Prairie Third Candidate', 'published', (int) $editor['id'], [$paperAId], '');
$heroB1 = $mkPost('Kitchener Untouched Hero', 'published', (int) $editor['id'], [$paperBId], 'hero');
$draftA = $mkPost('Original Draft Headline', 'draft', (int) $author['id'], [$paperAId], '');
$schedAuthor = $mkPost('Scheduled Author Piece', 'scheduled', (int) $author['id'], [$paperAId], '');

// Direct DB insert of a malicious wire-link post to test article.php defense-in-depth
$badLinkSlug = 'bad-link-' . bin2hex(random_bytes(3));
$pdo->prepare("INSERT INTO posts (title, slug, body, lede, post_type, source_url, status, author_id, published_at, created_at, updated_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)")
    ->execute(['Bad wire link', $badLinkSlug, '', 'Lede', 'link', 'javascript:alert(1)', 'published', (int) $editor['id'], now(), now(), now()]);
set_post_sites(pp_last_id('posts'), [$paperAId]);

$rlDir = $work . '/sub-rl';
$port = 8500 + random_int(0, 90);
$cmd = 'PP_CONFIG=' . escapeshellarg($config) . ' PP_SUBSCRIBE_RL_DIR=' . escapeshellarg($rlDir) . ' '
     . escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("$work/server.log") . ' 2>&1 & echo $!';
$serverPid = (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && ' . $cmd));
usleep(700000);
register_shutdown_function(function () use ($serverPid, $fx) {
    if ($serverPid) {
        @posix_kill($serverPid, 15);
    }
    pp_fixture_destroy($fx);
});

function req(string $method, string $url, array $fields, string $jar, string $base): array
{
    $ch = curl_init($base . $url);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
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

$base = 'http://127.0.0.1:' . $port;
$login = function (string $email) use ($work, $base): array {
    $jar = $work . '/jar-' . md5($email);
    [, $page] = req('GET', '/admin/login.php', [], $jar, $base);
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
    $csrf = $m[1] ?? '';
    [$code] = req('POST', '/admin/login.php', ['csrf' => $csrf, 'email' => $email, 'password' => 'correct horse pp'], $jar, $base);
    return [$jar, $csrf, $code];
};

[$jarAdmin, $csrfAdmin, $c1] = $login($admin['email']);
[$jarEditor, $csrfEditor, $c2] = $login($editor['email']);
[$jarAuthor, $csrfAuthor, $c3] = $login($author['email']);
ok($c1 === 302 && $c2 === 302 && $c3 === 302, "all three roles signed in ($c1/$c2/$c3)");

$postRow = function (int $id) use ($pdo): array {
    $s = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: [];
};

/* --- 1. Site-scoped hero promotion ---------------------------------------- */

[$code] = req('POST', '/admin/posts.php', ['csrf' => $csrfEditor, 'action' => 'feature', 'id' => $storyA2['id']], $jarEditor, $base);
ok($code === 302, 'feature action redirected');
ok($postRow($storyA2['id'])['placement'] === 'hero', 'story A2 became hero on paper A');
ok($postRow($heroA1['id'])['placement'] === '', 'previous hero A1 cleared on paper A');
ok($postRow($heroB1['id'])['placement'] === 'hero', 'paper B hero stayed intact after paper A posts.php feature');

[$code] = req('POST', '/admin/post-edit.php?id=' . $storyA3['id'], [
    'csrf' => $csrfEditor,
    'title' => 'Prairie Third Candidate',
    'lede' => 'Lede text.',
    'body' => '<p>Body text.</p>',
    'status' => 'published',
    'placement' => 'hero',
    'sites' => [$paperAId],
], $jarEditor, $base);
ok($code === 302, 'post-edit hero save redirected');
ok($postRow($storyA3['id'])['placement'] === 'hero', 'story A3 became hero on paper A');
ok($postRow($storyA2['id'])['placement'] === '', 'previous hero A2 cleared on paper A');
ok($postRow($heroB1['id'])['placement'] === 'hero', 'paper B hero stayed intact after paper A post-edit hero save');

/* --- 2. Published slug stability ------------------------------------------ */

[$code] = req('POST', '/admin/post-edit.php?id=' . $storyA3['id'], [
    'csrf' => $csrfEditor,
    'title' => 'Completely Renamed Published Headline',
    'lede' => 'Lede text.',
    'body' => '<p>Body text.</p>',
    'status' => 'published',
    'placement' => 'hero',
    'sites' => [$paperAId],
], $jarEditor, $base);
ok($code === 302, 'published headline edit saved');
$afterPub = $postRow($storyA3['id']);
ok($afterPub['title'] === 'Completely Renamed Published Headline', 'published headline updated');
ok($afterPub['slug'] === $storyA3['slug'], 'published story slug stayed unchanged (no silent 404)');

[$code] = req('POST', '/admin/post-edit.php?id=' . $draftA['id'], [
    'csrf' => $csrfAuthor,
    'title' => 'Fresh Draft Headline',
    'lede' => 'Lede text.',
    'body' => '<p>Body text.</p>',
    'status' => 'draft',
], $jarAuthor, $base);
ok($code === 302, 'draft headline edit saved');
ok(str_starts_with($postRow($draftA['id'])['slug'], 'fresh-draft-headline'), 'unpublished draft slug still updates from headline');

/* --- 3. Admin-only HTML ads & URL scheme validation ----------------------- */

[$code] = req('POST', '/admin/ads.php', [
    'csrf' => $csrfEditor,
    'action' => 'save',
    'name' => 'Editor XSS Attempt',
    'placement' => 'rail',
    'kind' => 'html',
    'html' => '<script>alert(1)</script>',
], $jarEditor, $base);
$xssCount = (int) $pdo->query("SELECT COUNT(*) FROM ads WHERE name = 'Editor XSS Attempt'")->fetchColumn();
ok($xssCount === 0, 'non-admin editor cannot create raw HTML embed ad');

[$code] = req('POST', '/admin/ads.php', [
    'csrf' => $csrfAdmin,
    'action' => 'save',
    'name' => 'Admin Legit Embed',
    'placement' => 'rail',
    'kind' => 'html',
    'html' => '<div class="sponsor">Ad</div>',
], $jarAdmin, $base);
$htmlAdId = (int) $pdo->query("SELECT id FROM ads WHERE name = 'Admin Legit Embed'")->fetchColumn();
ok($htmlAdId > 0, 'administrator can create raw HTML embed ad');

req('POST', '/admin/ads.php', ['csrf' => $csrfEditor, 'action' => 'delete', 'id' => $htmlAdId], $jarEditor, $base);
$stillThere = (int) $pdo->query("SELECT COUNT(*) FROM ads WHERE id = $htmlAdId")->fetchColumn();
ok($stillThere === 1, 'non-admin editor cannot delete an administrator raw HTML ad');

[, $badSrcBody] = req('POST', '/admin/post-edit.php?id=' . $draftA['id'], [
    'csrf' => $csrfAuthor,
    'title' => 'Fresh Draft Headline',
    'lede' => 'Lede text.',
    'body' => '<p>Body text.</p>',
    'source_url' => 'javascript:alert(1)',
    'status' => 'draft',
], $jarAuthor, $base);
ok(str_contains($badSrcBody, 'http:// or https://'), 'post-edit rejects javascript: source_url');
ok((string) $postRow($draftA['id'])['source_url'] === '', 'javascript: source_url was not persisted');

[$linkCode, , $linkHeaders] = req('GET', '/story/' . $badLinkSlug, [], $work . '/jar-anon', $base);
ok($linkCode === 302 && ($linkHeaders['location'] ?? '') === '/', 'article.php refuses non-http(s) wire link redirect');

/* --- 4. /subscribe per-IP rate limiting ----------------------------------- */

$anonJar = $work . '/jar-sub';
$subCodes = [];
for ($i = 1; $i <= 6; $i++) {
    [$sc] = req('POST', '/subscribe', ['email' => "reader{$i}@example.com", 'website' => ''], $anonJar, $base);
    $subCodes[] = $sc;
}
ok(array_slice($subCodes, 0, 5) === [302, 302, 302, 302, 302], 'first 5 subscribe requests succeed (' . implode(',', $subCodes) . ')');
ok($subCodes[5] === 429, '6th subscribe request from same IP is throttled with 429 (got ' . $subCodes[5] . ')');
$subCount = (int) $pdo->query("SELECT COUNT(*) FROM subscribers WHERE site_id = $paperAId")->fetchColumn();
ok($subCount === 5, "only 5 subscriber rows persisted (got $subCount)");

/* --- 5. Newsletter per-paper timezone scheduling -------------------------- */

// 2026-11-28 11:30:00 UTC = 06:30 EST (Toronto) and 03:30 PST (Vancouver)
$utcInstant = strtotime('2026-11-28 11:30:00 UTC');
$runNewsletterCheck = function (string $slug) use ($root, $config, $utcInstant): string {
    $code = 'define("PP_TEST_BOOT", 1); require ' . var_export("$root/app/bootstrap.php", true) . '; '
          . 'require ' . var_export("$root/app/newsletter.php", true) . '; '
          . 'set_setting("newsletter_enabled", "1"); set_setting("newsletter_send_hour", "6"); '
          . 'echo pp_maybe_send_daily(' . $utcInstant . ');';
    return trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($config)
        . ' PP_SITE=' . escapeshellarg($slug) . ' ' . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1'));
};
$vancouverOut = $runNewsletterCheck('kermode-chronicle');
$torontoOut   = $runNewsletterCheck('kitchener-chronicle');
ok(str_contains($vancouverOut, "before the 6 o'clock send"), "Vancouver paper waits at 03:30 local ($vancouverOut)");
ok(!str_contains($torontoOut, "before the 6 o'clock send"), "Toronto paper passes 6 a.m. gate at 06:30 local ($torontoOut)");

/* --- 6. Admin posts search, site scoping, and author scheduled-delete UI -- */

[$code, $listDefault] = req('GET', '/admin/posts.php', [], $jarEditor, $base);
ok($code === 200, 'admin/posts.php loads');
ok(str_contains($listDefault, 'Calgary Budget Hero One'), 'paper A posts list shows paper A story');
ok(!str_contains($listDefault, 'Kitchener Untouched Hero'), 'paper A posts list hides paper B story by default');

[, $listAll] = req('GET', '/admin/posts.php?scope=all', [], $jarEditor, $base);
ok(str_contains($listAll, 'Kitchener Untouched Hero'), 'scope=all reveals sister paper story when requested');

[, $searchRes] = req('GET', '/admin/posts.php?q=calgary', [], $jarEditor, $base);
ok(str_contains($searchRes, 'Calgary Budget Hero One'), 'admin/posts.php search matches case-insensitively (pp_like)');

[, $authorList] = req('GET', '/admin/posts.php', [], $jarAuthor, $base);
ok(str_contains($authorList, 'Scheduled Author Piece'), 'author sees own scheduled story in list');
ok(!preg_match('/<tr>(?:(?!<\/tr>).)*Scheduled Author Piece(?:(?!<\/tr>).)*value="delete"(?:(?!<\/tr>).)*<\/tr>/s', $authorList), 'author does not see Delete button on scheduled story');
ok((bool) preg_match('/<tr>(?:(?!<\/tr>).)*Fresh Draft Headline(?:(?!<\/tr>).)*value="delete"(?:(?!<\/tr>).)*<\/tr>/s', $authorList), 'author still sees Delete button on own draft');

exit($fails ? 1 : 0);
