<?php
/**
 * Each paper prints its own address (PLAN.md 10.0): site_url() in
 * app/bootstrap.php, pp_site_canonical_host() in app/models.php.
 *
 * Production's tenant config gives every tenant without an arm of its own
 * the flagship's address. The papers launched since tenant resolution moved
 * into the database have no arm, so thirteen of them printed
 * prairiedispatch.ca in every canonical, share link, sitemap and feed. Here
 * a config shaped like that one (site_url is the flagship's for everyone)
 * must still give each paper its own address:
 * - a configured URL that names another paper yields this paper's own
 *   canonical host: sites.domain, else its shortest hostname;
 * - a configured URL that names this paper, or no paper, is kept as is;
 * - an empty site_url is still the request's own host;
 * - the rendered front, a story, the sitemap and the feed name the paper's
 *   host and never the flagship's.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('siteurl');
pp_fixture_prepare($fx);
register_shutdown_function(fn () => pp_fixture_destroy($fx));
$pdo = pp_fixture_connect($fx);

$fails = 0;
function ok(bool $cond, string $label): bool
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
    return $cond;
}

/* --- Every pack lists its canonical host first ------------------------------ */

// pp_site_canonical_host() picks a paper's shortest hostname; that is only
// the canonical one while every pack lists it first and lists nothing
// shorter after it.
foreach (glob("$root/assets/sites/*/launch.php") as $file) {
    $slug = basename(dirname($file));
    $src = (string) file_get_contents($file);
    if (!preg_match("/'domains'\s*=>\s*\[([^\]]*)\]/", $src, $m)) {
        continue;
    }
    preg_match_all("/'([a-z0-9.-]+)'/", $m[1], $hosts);
    $hosts = $hosts[1];
    $shortest = $hosts;
    usort($shortest, fn ($a, $b) => [strlen($a), $a] <=> [strlen($b), $b]);
    ok($hosts !== [] && $shortest[0] === $hosts[0], "$slug: its first listed hostname ({$hosts[0]}) is its shortest, so it is the one printed");
}

/* --- The fixture ------------------------------------------------------------ */

foreach (['prairiedispatch', 'toronto-telegraph', 'kitchener-chronicle', 'grande-prairie-gazette', 'civismedia'] as $slug) {
    [$out, $exit] = pp_fixture_tool($fx, 'tools/seed-launch.php', 'PP_SITE=' . escapeshellarg($slug));
    ok($exit === 0, "seed $slug ($out)");
}

// A config shaped like production's: one site_url, the flagship's, for every
// tenant without an arm of its own. And two more, for the cases a paper's
// own arm can take.
$base = (string) file_get_contents($fx['config']);
$withUrl = function (string $url) use ($base, $fx): string {
    $path = $fx['work'] . '/config-' . md5($url) . '.php';
    file_put_contents($path, str_replace("'site_url' => ''", "'site_url' => " . var_export($url, true), $base));
    return $path;
};
$flagship = $withUrl('https://prairiedispatch.ca');
$ownArm   = $withUrl('https://www.torontotelegraph.ca');
$stranger = $withUrl('https://staging.example.org');

/** site_url() in a fresh process, as a request to $host (or as CLI). */
$ask = function (string $config, ?string $host, string $env = '', string $code = 'echo site_url();') use ($root): string {
    $php = ($host !== null ? '$_SERVER["HTTP_HOST"] = ' . var_export($host, true) . '; $_SERVER["HTTPS"] = "on"; ' : '')
         . 'require ' . var_export("$root/app/bootstrap.php", true) . '; ' . $code;
    return trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($config) . ' ' . $env . ' '
        . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($php) . ' 2>&1'));
};

/* --- What site_url() says --------------------------------------------------- */

foreach ([
    'torontotelegraph.ca'         => 'https://torontotelegraph.ca',
    'www.torontotelegraph.ca'     => 'https://torontotelegraph.ca',
    'kitchenerchronicle.com'      => 'https://kitchenerchronicle.com',
    'www.grandeprairiegazette.ca' => 'https://www.grandeprairiegazette.ca',
    'civismedia.ca'               => 'https://civismedia.ca',
] as $host => $want) {
    $got = $ask($flagship, $host);
    ok($got === $want, "configured as the flagship, a request to $host prints $want (got $got)");
}
$got = $ask($flagship, 'prairiedispatch.ca');
ok($got === 'https://prairiedispatch.ca', "the flagship keeps its own configured address (got $got)");

$got = $ask($flagship, null, 'PP_SITE=toronto-telegraph');
ok($got === 'https://torontotelegraph.ca', "a CLI run for the Telegraph (cron, the newsletter) prints its own address (got $got)");

$got = $ask($ownArm, 'torontotelegraph.ca');
ok($got === 'https://www.torontotelegraph.ca', "a configured URL naming the paper itself is kept exactly (got $got)");

$got = $ask($stranger, 'torontotelegraph.ca');
ok($got === 'https://staging.example.org', "a configured URL naming no paper is kept exactly (got $got)");

$got = $ask($fx['config'], 'www.torontotelegraph.ca');
ok($got === 'https://www.torontotelegraph.ca', "an empty site_url is still the request's own host (got $got)");

// sites.domain, where it is set, is the paper's canonical host.
$pdo->prepare('UPDATE sites SET domain = ? WHERE slug = ?')->execute(['www.kitchenerchronicle.com', 'kitchener-chronicle']);
$got = $ask($flagship, 'kitchenerchronicle.com');
ok($got === 'https://www.kitchenerchronicle.com', "sites.domain outranks the shortest hostname (got $got)");
$pdo->prepare('UPDATE sites SET domain = ? WHERE slug = ?')->execute(['', 'kitchener-chronicle']);

// A paper with no record of its own host has nothing better than the config.
$pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?, ?, ?)')->execute(['Hostless', 'siteurl-hostless', date('Y-m-d H:i:s')]);
$got = $ask($flagship, null, 'PP_SITE=siteurl-hostless');
ok($got === 'https://prairiedispatch.ca', "a paper with no hostname of its own keeps the configured address (got $got)");

/* --- What a reader and a crawler get ------------------------------------------ */

$render = function (string $host, string $script, array $get = []) use ($fx, $root, $flagship): string {
    $driver = "{$fx['work']}/render-siteurl.php";
    file_put_contents($driver, "<?php\nchdir(\$argv[1]);\n\$_SERVER['REQUEST_URI'] = '/';\n\$_SERVER['REQUEST_METHOD'] = 'GET';\n"
        . "\$_SERVER['HTTPS'] = 'on';\n\$_SERVER['HTTP_HOST'] = \$argv[2];\n\$_GET = json_decode(\$argv[4], true);\n"
        . "require \$argv[1] . '/' . \$argv[3];\n");
    exec('PP_CONFIG=' . escapeshellarg($flagship) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($driver) . ' '
        . escapeshellarg($root) . ' ' . escapeshellarg($host) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg(json_encode($get)) . ' 2>&1', $out, $code);
    return $code === 0 ? implode("\n", $out) : "EXIT $code: " . implode("\n", $out);
};
$own = 'https://torontotelegraph.ca';

$front = $render('torontotelegraph.ca', 'index.php');
ok(str_contains($front, '<link rel="canonical" href="' . $own . '/">'), "the Telegraph's front names itself canonical");
ok(preg_match('#<meta property="og:url" content="' . preg_quote($own, '#') . '/"#', $front) === 1, 'its og:url is its own');
ok(preg_match('#<meta property="og:image" content="' . preg_quote($own, '#') . '/#', $front) === 1, 'its share image is served from its own host');
ok(!str_contains($front, 'href="https://prairiedispatch.ca/feed/"'), 'its feed link is its own');

$slug = (string) $pdo->query("SELECT p.slug FROM posts p JOIN post_sites ps ON ps.post_id = p.id JOIN sites s ON s.id = ps.site_id
    WHERE s.slug = 'toronto-telegraph' AND p.status = 'published' ORDER BY p.id LIMIT 1")->fetchColumn();
if (ok($slug !== '', 'the Telegraph has a published story to open')) {
    $story = $render('torontotelegraph.ca', 'article.php', ['slug' => $slug]);
    $url = "$own/story/$slug";
    ok(str_contains($story, '<link rel="canonical" href="' . $url . '">'), "a story's canonical is on its own host ($url)");
    ok(str_contains($story, '<meta property="og:url" content="' . $url . '">'), "a story's og:url is too");
    ok(str_contains($story, '"mainEntityOfPage":"' . str_replace('/', '\/', $url) . '"'), "and its NewsArticle data");
    ok(str_contains($story, '<meta property="og:image" content="' . "$own/card/$slug.png" . '">'), 'its share card is drawn by its own host');
    $public = trim($ask($flagship, 'torontotelegraph.ca', '',
        'echo pp_post_public_url(db()->query("SELECT * FROM posts WHERE slug = ' . "'" . $slug . "'" . '")->fetch());'));
    ok($public === $url, "the URL the agents' publish lane returns agrees (got $public)");
}

$sitemap = $render('torontotelegraph.ca', 'sitemap.php');
preg_match_all('#<loc>([^<]+)</loc>#', $sitemap, $locs);
ok(count($locs[1]) > 1 && array_filter($locs[1], fn ($u) => !str_starts_with($u, "$own/")) === [],
   'every sitemap URL is on its own host (' . count($locs[1]) . ' URLs)');

// The channel's link and every item's guid name the paper. (An item's
// <link> may rightly name another outlet: a wire item links its source.)
$feed = $render('torontotelegraph.ca', 'feed.php');
preg_match('#<channel>\s*<title>[^<]*</title>\s*<link>([^<]+)</link>#', $feed, $channel);
preg_match_all('#<guid isPermaLink="true">([^<]+)</guid>#', $feed, $guids);
$named = array_merge([$channel[1] ?? ''], $guids[1]);
ok(count($guids[1]) > 0 && array_filter($named, fn ($u) => !str_starts_with($u, "$own/")) === [],
   'the feed names its own host, for itself and for every item (' . count($guids[1]) . ' items)');

exit($fails ? 1 : 0);
