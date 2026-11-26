<?php
/**
 * The search-engine switch on a paper's Settings page (admin/settings.php,
 * app/indexing.php).
 *
 * - Which papers launched with demonstration copy: exactly the fifteen
 *   packs whose runbooks call their stories demonstration content, and the
 *   founding paper's core-seed stories. The slugs the check looks for are
 *   the ones the seeder gave those stories, and tools/demo-inventory.php
 *   counts the same ones.
 * - An administrator opens an inaugural-edition paper with one button: its
 *   setting turns on, the audit trail says who, and its pages stop carrying
 *   noindex. It hides the paper again the same way.
 * - Opening is refused while demonstration stories are live, and allowed
 *   once they are drafts. It is also refused while the paper's pages name a
 *   host that isn't the paper's. The page shows why, and disables the button.
 *   Once a paper is open, a demonstration story put back up is flagged.
 * - Only an administrator, only with the form's token. The main settings
 *   form no longer touches the switch.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('ixswitch');
pp_fixture_prepare($fx);
$work = $fx['work'];

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

putenv('PP_CONFIG=' . $fx['config']);
define('PP_TEST_BOOT', 1);
require $root . '/app/bootstrap.php';
require $root . '/app/indexing.php';
$pdo = db();
echo 'engine: ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . " ({$fx['id']})\n";

/* ---- 1. Which papers launched with demonstration copy ------------------------- */

$flagged = [];
foreach (glob("$root/assets/sites/*/launch.php") as $file) {
    $pack = (static fn (string $f) => require $f)($file);
    if (!empty($pack['demonstration'])) {
        $flagged[] = basename(dirname($file));
    }
}
sort($flagged);
ok($flagged === ['bleuet-blanc', 'brampton-bulletin', 'edmonton-echo', 'grande-prairie-gazette', 'kelowna-current', 'kermode-chronicle',
                 'kitchener-chronicle', 'london-lookout', 'mississauga-monitor', 'pacific-post', 'pickering-post', 'sudbury-standard',
                 'tri-cities-torch', 'turtle-island-times', 'westernwire'],
   'exactly the fifteen packs of demonstration copy say so (got ' . implode(', ', $flagged) . ')');

foreach (['toronto-telegraph', 'kitchener-chronicle'] as $slug) {
    [$out, $exit] = pp_fixture_tool($fx, 'tools/seed-launch.php', 'PP_SITE=' . escapeshellarg($slug));
    ok($exit === 0, "seed $slug");
}
$site = fn (string $slug): int => (int) $pdo->query('SELECT id FROM sites WHERE slug = ' . $pdo->quote($slug))->fetchColumn();
$toronto = $site('toronto-telegraph');
$kitchener = $site('kitchener-chronicle');
$founding = $site('prairiedispatch');

/** How many of these slugs are published on this paper. */
$liveOn = function (int $siteId, array $slugs): int {
    if (!$slugs) {
        return 0;
    }
    $c = pp_db_connect();
    $st = $c->prepare('SELECT COUNT(*) FROM posts p JOIN post_sites ps ON ps.post_id = p.id WHERE ps.site_id = ? AND p.status = ? AND p.slug IN ('
        . implode(',', array_fill(0, count($slugs), '?')) . ')');
    $st->execute(array_merge([$siteId, 'published'], $slugs));
    return (int) $st->fetchColumn();
};
ob_start();
$kSlugs = pp_demonstration_slugs('kitchener-chronicle');
$pSlugs = pp_demonstration_slugs('prairiedispatch');
$tSlugs = pp_demonstration_slugs('toronto-telegraph');
ob_end_clean();
ok(count($kSlugs) === 19 && $liveOn($kitchener, $kSlugs) === 19,
   "Kitchener's nineteen demonstration stories are found under the slugs the seeder gave them (got " . count($kSlugs) . ')');
ok(count($pSlugs) === 12 && $liveOn($founding, $pSlugs) === 12,
   "the founding paper's twelve core-seed stories are found too (got " . count($pSlugs) . ')');
ok($tSlugs === [], 'an inaugural edition is not demonstration copy: Toronto has none');
[$inv, $exit] = pp_fixture_tool($fx, 'tools/demo-inventory.php');
ok($exit === 0 && preg_match('/^kitchener-chronicle\s+declares 19 demonstration stories: 19 on the paper \(19 live\)/m', $inv) === 1
   && preg_match('/^prairiedispatch\s+declares 12 demonstration stories: 12 on the paper \(12 live\)/m', $inv) === 1
   && preg_match('/^toronto-telegraph .*declares/m', $inv) === 0 && preg_match('/^toronto-telegraph\s+6 post\(s\) mapped/m', $inv) === 1,
   "tools/demo-inventory.php counts the same stories, and no inaugural edition (exit $exit)");

/* ---- The newsroom over HTTP -------------------------------------------------- */

foreach ([['Ada Admin', 'ada@test.local', 'admin'], ['Ed Editor', 'ed@test.local', 'editor']] as [$name, $email, $role]) {
    $pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
        ->execute([$name, $email, password_hash('correct horse ix', PASSWORD_DEFAULT), $role, slugify($name) . '-ix', now()]);
}
$stagingConfig = "$work/config-staging.php";
file_put_contents($stagingConfig, str_replace("'site_url' => ''", "'site_url' => 'https://staging.example.org'", (string) file_get_contents($fx['config'])));

$servers = [];
$serve = function (string $config) use ($root, $work, &$servers): int {
    $port = 8950 + random_int(0, 300);
    $servers[] = (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($config) . ' '
        . escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("$work/server-$port.log") . ' 2>&1 & echo $!'));
    usleep(700000);
    return $port;
};
register_shutdown_function(function () use (&$servers, $fx) {
    foreach ($servers as $pid) {
        if ($pid) {
            @posix_kill($pid, 15);
        }
    }
    pp_fixture_destroy($fx);
});

/** One request: [status, body, Location]. */
function web(int $port, string $host, string $method, string $path, array $fields = [], ?string $jar = null): array
{
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false,
                            CURLOPT_HTTPHEADER => ['Host: ' . $host], CURLOPT_HEADER => true]);
    if ($jar !== null) {
        curl_setopt_array($ch, [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headLen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    preg_match('/^Location:\s*(\S+)/mi', substr($raw, 0, $headLen), $m);
    return [$code, substr($raw, $headLen), $m[1] ?? ''];
}
$signIn = function (int $port, string $host, string $email, string $jar): int {
    [, $page] = web($port, $host, 'GET', '/admin/login.php', [], $jar);
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
    return web($port, $host, 'POST', '/admin/login.php', ['csrf' => $m[1] ?? '', 'email' => $email, 'password' => 'correct horse ix'], $jar)[0];
};
/** The settings page and its form token. */
$settings = function (int $port, string $host, string $jar): array {
    [$code, $page] = web($port, $host, 'GET', '/admin/settings.php', [], $jar);
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
    return [$code, $page, $m[1] ?? ''];
};
$switch = function (int $siteId): string {
    $c = pp_db_connect();
    $st = $c->prepare("SELECT svalue FROM settings WHERE site_id = ? AND skey = 'indexing_enabled'");
    $st->execute([$siteId]);
    return (string) $st->fetchColumn();
};
$audits = function (int $siteId, string $action): array {
    $c = pp_db_connect();
    $st = $c->prepare('SELECT user_name, detail FROM audit_log WHERE site_id = ? AND action = ? ORDER BY id');
    $st->execute([$siteId, $action]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
};
$noindex = '<meta name="robots" content="noindex, nofollow">';
$openButton = '/<button class="btn" type="submit"( disabled)? onclick="[^"]*">Open to search engines<\/button>/';

/* ---- 2. Opening and hiding an inaugural-edition paper ------------------------- */

$port = $serve($fx['config']);
$adaT = "$work/jar-ada-toronto";
ok($signIn($port, 'torontotelegraph.ca', 'ada@test.local', $adaT) === 302, 'the administrator signs in on the Telegraph');
[$code, $page, $csrf] = $settings($port, 'torontotelegraph.ca', $adaT);
ok($code === 200 && str_contains($page, 'id="search-engines"') && str_contains($page, 'chip--draft">Hidden'),
   "Settings opens with the Search engines panel, showing the paper hidden (got $code)");
ok(preg_match($openButton, $page, $b) === 1 && ($b[1] ?? '') === '', 'its Open button is live: nothing stands in the way');
ok(!str_contains($page, 'name="indexing_enabled"') && !str_contains($page, 'name="indexing_marker"'), 'the old checkbox is gone from the form');
ok(str_contains(web($port, 'torontotelegraph.ca', 'GET', '/')[1], $noindex), 'before: the front asks search engines to stay away');

[$code, , $loc] = web($port, 'torontotelegraph.ca', 'POST', '/admin/settings.php', ['csrf' => $csrf, 'action' => 'indexing_on'], $adaT);
$opened = $audits($toronto, 'indexing.on');
ok($code === 302 && str_ends_with($loc, 'settings.php') && $switch($toronto) === '1', "Open: the Telegraph's switch is on (got $code → $loc)");
ok(count($opened) === 1 && $opened[0]['user_name'] === 'Ada Admin' && str_contains((string) $opened[0]['detail'], 'torontotelegraph.ca'),
   'the audit trail says who opened it, and at which address');
ok(!str_contains(web($port, 'torontotelegraph.ca', 'GET', '/')[1], $noindex), 'after: the front no longer asks search engines to stay away');
ok($switch($kitchener) !== '1', "a sister paper's switch is untouched");
[$code, $page] = $settings($port, 'torontotelegraph.ca', $adaT);
ok(str_contains($page, 'chip--ok">Open') && str_contains($page, 'Hide from search engines') && str_contains($page, 'by Ada Admin'),
   'the panel now shows it open, who opened it, and the Hide button');

[$code] = web($port, 'torontotelegraph.ca', 'POST', '/admin/settings.php', ['csrf' => $csrf, 'action' => 'indexing_off'], $adaT);
ok($code === 302 && $switch($toronto) === '0' && count($audits($toronto, 'indexing.off')) === 1, "Hide: the switch is off and audited (got $code)");
ok(str_contains(web($port, 'torontotelegraph.ca', 'GET', '/')[1], $noindex), 'and the front asks search engines to stay away again');

/* ---- 3. Who may press it ---------------------------------------------------- */

$edT = "$work/jar-ed-toronto";
ok($signIn($port, 'torontotelegraph.ca', 'ed@test.local', $edT) === 302, 'an editor signs in');
[$code] = web($port, 'torontotelegraph.ca', 'GET', '/admin/settings.php', [], $edT);
[$post] = web($port, 'torontotelegraph.ca', 'POST', '/admin/settings.php', ['csrf' => $csrf, 'action' => 'indexing_on'], $edT);
ok($code === 403 && $post === 403 && $switch($toronto) === '0', "an editor can neither open Settings nor press the button (got $code, $post)");
[$code] = web($port, 'torontotelegraph.ca', 'POST', '/admin/settings.php', ['action' => 'indexing_on'], $adaT);
ok($code === 403 && $switch($toronto) === '0', "without the form's token, nothing changes (got $code)");

// The main form never touches the switch, whatever it carries.
web($port, 'torontotelegraph.ca', 'POST', '/admin/settings.php', ['csrf' => $csrf, 'action' => 'indexing_on'], $adaT);
[$code] = web($port, 'torontotelegraph.ca', 'POST', '/admin/settings.php',
    ['csrf' => $csrf, 'site_title' => 'Toronto Telegraph', 'indexing_marker' => '1'], $adaT);
ok($code === 302 && $switch($toronto) === '1', "saving the main form leaves an open paper open (got $code)");
web($port, 'torontotelegraph.ca', 'POST', '/admin/settings.php', ['csrf' => $csrf, 'action' => 'indexing_off'], $adaT);

/* ---- 4. Refused while demonstration stories are live ---------------------------- */

$adaK = "$work/jar-ada-kitchener";
ok($signIn($port, 'kitchenerchronicle.com', 'ada@test.local', $adaK) === 302, 'the administrator signs in on the Chronicle');
[$code, $page, $csrfK] = $settings($port, 'kitchenerchronicle.com', $adaK);
ok($code === 200 && str_contains($page, 'Not ready yet') && str_contains($page, '19 of its demonstration launch stories are still live')
   && preg_match($openButton, $page, $b) === 1 && ($b[1] ?? '') === ' disabled',
   "Settings says why, names the count, and disables the button (got $code)");
ok(str_contains($page, 'The demonstration stories still live (19)'), 'and lists them, each linked to its editor');
[$code, , $loc] = web($port, 'kitchenerchronicle.com', 'POST', '/admin/settings.php', ['csrf' => $csrfK, 'action' => 'indexing_on'], $adaK);
ok($code === 302 && $switch($kitchener) !== '1' && $audits($kitchener, 'indexing.on') === [],
   "pressing it anyway is refused on the server: the switch stays off, nothing is audited (got $code)");
$page = web($port, 'kitchenerchronicle.com', 'GET', '/admin/settings.php', [], $adaK)[1];
ok(str_contains($page, 'Not opened to search engines.'), 'and the refusal is explained');

// The newsroom drafts them; then it opens.
$c = pp_db_connect();
$c->prepare('UPDATE posts SET status = ? WHERE slug IN (' . implode(',', array_fill(0, count($kSlugs), '?')) . ')')
  ->execute(array_merge(['draft'], $kSlugs));
$c = null;
[, $page, $csrfK] = $settings($port, 'kitchenerchronicle.com', $adaK);
ok(preg_match($openButton, $page, $b) === 1 && ($b[1] ?? '') === '', 'with them drafted, the button is live');
web($port, 'kitchenerchronicle.com', 'POST', '/admin/settings.php', ['csrf' => $csrfK, 'action' => 'indexing_on'], $adaK);
ok($switch($kitchener) === '1', 'and the Chronicle opens');

// One goes back up after it opened: the panel says so, beside the Hide button.
$c = pp_db_connect();
$c->prepare('UPDATE posts SET status = ? WHERE slug = ?')->execute(['published', $kSlugs[0]]);
$c = null;
$page = web($port, 'kitchenerchronicle.com', 'GET', '/admin/settings.php', [], $adaK)[1];
ok(str_contains($page, 'chip--ok">Open') && str_contains($page, 'Search engines can see this now:')
   && str_contains($page, '1 of its demonstration launch stories is still live') && str_contains($page, 'Hide from search engines'),
   'a demonstration story put back up after opening is flagged on the open panel');

/* ---- 5. Refused while the pages name a host that isn't the paper's ------------- */

$staging = $serve($stagingConfig);
$adaS = "$work/jar-ada-staging";
ok($signIn($staging, 'torontotelegraph.ca', 'ada@test.local', $adaS) === 302, 'the administrator signs in where the config names another host');
[$code, $page, $csrfS] = $settings($staging, 'torontotelegraph.ca', $adaS);
ok(str_contains($page, 'Its pages name staging.example.org as their address') && preg_match($openButton, $page, $b) === 1 && ($b[1] ?? '') === ' disabled',
   "Settings names the foreign address and disables the button (got $code)");
[$code] = web($staging, 'torontotelegraph.ca', 'POST', '/admin/settings.php', ['csrf' => $csrfS, 'action' => 'indexing_on'], $adaS);
ok($code === 302 && $switch($toronto) === '0', "pressing it anyway is refused on the server (got $code)");

exit($fails ? 1 : 0);
