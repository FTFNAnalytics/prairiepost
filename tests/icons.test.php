<?php
/**
 * Tab icons, touch icons and share cards (PLAN.md 10.8), and the head
 * that publishes them.
 *
 * The four Manitoba papers carry a real icon set built from each paper's
 * own mark, and every page says so. A paper without those files keeps the
 * SVG favicon and is not given a link that 404s. theme-color comes from
 * the palette. The well-known /favicon.ico URL is the paper's own file.
 * Indexing stays off: robots is still noindex.
 *
 * The address check is the production case. The config names the flagship
 * for every tenant, and the rendered Portage and Bison pages must still
 * put their own host on canonical, og:url, og:image and twitter:image.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

/* --- The files ----------------------------------------------------------- */

$papers = [
    'portage-press'      => '#041E42',
    'bison-bulletin'     => '#8B0000',
    'brandon-bulletin'   => '#E6BF2E',
    'red-river-register' => '#7E3517',
];
$networkOg = md5_file("$root/assets/img/og-default.png");
foreach ($papers as $slug => $theme) {
    $dir = "$root/assets/sites/$slug";
    $brand = json_decode((string) file_get_contents("$dir/palette.json"), true);
    $palette = array_map('strtoupper', array_filter(
        (array) ($brand['palette'] ?? []),
        static fn ($v) => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v) === 1
    ));
    ok(strtoupper((string) ($brand['theme'] ?? '')) === $theme, "$slug names theme $theme");
    ok(in_array($theme, $palette, true), "$slug theme is one of its own palette colours");

    $png = @getimagesize("$dir/favicon-32.png");
    ok($png && $png[0] === 32 && $png[1] === 32 && $png['mime'] === 'image/png', "$slug 32px icon is a 32×32 PNG");
    $apple = @getimagesize("$dir/apple-touch-icon.png");
    ok($apple && $apple[0] === 180 && $apple[1] === 180 && $apple['mime'] === 'image/png', "$slug touch icon is 180×180 PNG");
    $og = @getimagesize("$dir/og-default.png");
    ok($og && $og[0] === 1200 && $og[1] === 630 && $og['mime'] === 'image/png', "$slug share card is 1200×630 PNG");
    ok(md5_file("$dir/og-default.png") !== $networkOg, "$slug share card is not the network landscape");

    $ico = (string) file_get_contents("$dir/favicon.ico");
    ok(str_starts_with($ico, "\x00\x00\x01\x00"), "$slug favicon.ico has an ICO header");
    $count = unpack('v', substr($ico, 4, 2))[1] ?? 0;
    ok($count === 3, "$slug favicon.ico carries 16, 32 and 48 ($count images)");
    // Each image is a PNG, which is what current browsers read out of an ICO.
    $pngs = 0;
    for ($i = 0; $i < $count; $i++) {
        $entry = unpack('Cwidth/Cheight/x2/vplanes/vbits/Vbytes/Voffset', substr($ico, 6 + 16 * $i, 16));
        if (substr($ico, (int) $entry['offset'], 8) === "\x89PNG\r\n\x1a\n") {
            $pngs++;
        }
    }
    ok($pngs === 3, "$slug favicon.ico images are PNG ($pngs of $count)");
}

$river = (string) file_get_contents("$root/assets/sites/red-river-register/favicon.svg");
$riverMark = (string) file_get_contents("$root/assets/sites/red-river-register/mark.svg");
ok($river === $riverMark, 'Red River favicon and mark are the same drawing');
ok(preg_match('/<mask\b/i', $river) !== 1, 'Red River icon has no SVG mask (a mask paints blank in a tab)');
ok(preg_match('/<path\b[^>]*\sd="[^"]*\bC\b/', $river) === 1, 'the river meander is a curve in the path itself');
ok(str_contains($river, 'fill="#7E3517"'), 'the icon keeps the clay of the mark');
ok(!str_contains($river, 'fill="#FFFFFF"') && !str_contains($river, 'fill="#ffffff"'), 'the icon is not the white reversed mark');

/* --- What a reader and a crawler get ------------------------------------- */

$fx = pp_fixture_create('icons');
pp_fixture_prepare($fx);
register_shutdown_function(static fn () => pp_fixture_destroy($fx));
$base = (string) file_get_contents($fx['config']);
file_put_contents($fx['config'], str_replace("'site_url' => ''", "'site_url' => 'https://prairiedispatch.ca'", $base));
putenv('PP_CONFIG=' . $fx['config']);
require $root . '/app/bootstrap.php';
require_once $root . '/app/seed.php';
$pdo = db();

$flagship = current_site();
$portage = pp_create_site($pdo, 'portage-press', 'Portage Press');
$bison = pp_create_site($pdo, 'bison-bulletin', 'The Bison Bulletin');
$pdo->prepare('UPDATE sites SET domain = ? WHERE slug = ?')->execute(['portagepress.ca', 'portage-press']);
$pdo->prepare('UPDATE sites SET domain = ? WHERE slug = ?')->execute(['bisonbulletin.ca', 'bison-bulletin']);
$now = date('Y-m-d H:i:s');
$ins = $pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?, ?, ?)');
$ins->execute(['prairiedispatch.ca', 'prairiedispatch', $now]);
$ins->execute(['portagepress.ca', 'portage-press', $now]);
$ins->execute(['bisonbulletin.ca', 'bison-bulletin', $now]);

$published = date('Y-m-d H:i:s', time() - 60);
$pdo->prepare('INSERT INTO posts (title, slug, body, lede, status, published_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)')
    ->execute(['A Portage story', 'portage-icon-check', '<p>The story.</p>', 'The lede.', 'published', $published, $now, $now]);
$postId = pp_seed_last_id($pdo, 'posts');
$pdo->prepare('INSERT INTO post_sites (post_id, site_id) VALUES (?, ?)')->execute([$postId, (int) $portage['id']]);

$port = 8910 + random_int(0, 70);
$pid = (int) trim((string) shell_exec(
    'cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($fx['config']) . ' '
    . escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg($fx['work'] . '/server.log') . ' 2>&1 & echo $!'
));
register_shutdown_function(static function () use ($pid) {
    if ($pid > 0) {
        @posix_kill($pid, 15);
    }
});
$up = false;
for ($i = 0; $i < 20; $i++) {
    $probe = @file_get_contents("http://127.0.0.1:$port/", false, stream_context_create(['http' => ['timeout' => 1, 'header' => "Host: portagepress.ca\r\n"]]));
    if (is_string($probe) && str_contains($probe, '<head>')) {
        $up = true;
        break;
    }
    usleep(150000);
}
ok($up, 'the test server answered');

function req(string $host, string $path, int $port): array
{
    $ch = curl_init("http://127.0.0.1:$port$path");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => ["Host: $host"],
    ]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $split = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$code, substr($raw, 0, $split), substr($raw, $split)];
}
function head_of(string $html): string
{
    $end = stripos($html, '</head>');
    return $end === false ? $html : substr($html, 0, $end);
}

if ($up) {
    [$code, , $body] = req('portagepress.ca', '/', $port);
    $head = head_of($body);
    $own = 'https://portagepress.ca';
    ok($code === 200, "Portage front is 200 ($code)");
    ok(str_contains($head, 'name="robots" content="noindex'), 'indexing is still off');
    ok(str_contains($head, '<link rel="canonical" href="' . $own . '/">'), 'canonical is portagepress.ca');
    ok(str_contains($head, '<meta property="og:url" content="' . $own . '/">'), 'og:url is portagepress.ca');
    ok(str_contains($head, '<meta property="og:image" content="' . $own . '/assets/sites/portage-press/og-default.png">'), 'og:image is the paper\'s own card on its own host');
    ok(str_contains($head, '<meta name="twitter:image" content="' . $own . '/assets/sites/portage-press/og-default.png">'), 'twitter:image matches it');
    ok(str_contains($head, '<link rel="icon" href="/assets/sites/portage-press/favicon.ico" sizes="any">'), 'ICO favicon is linked');
    ok(str_contains($head, '<link rel="icon" type="image/png" sizes="32x32" href="/assets/sites/portage-press/favicon-32.png">'), '32px PNG is linked');
    ok(str_contains($head, '<link rel="icon" type="image/svg+xml" href="/assets/sites/portage-press/favicon.svg">'), 'SVG favicon stays');
    ok(str_contains($head, '<link rel="apple-touch-icon" href="/assets/sites/portage-press/apple-touch-icon.png">'), 'apple touch icon is linked');
    ok(str_contains($head, '<meta name="theme-color" content="#041E42">'), 'theme-color is Portage navy');
    ok(!str_contains($head, 'prairiedispatch.ca'), 'the flagship host is not in the head');
    ok(str_contains($head, '<meta property="og:site_name" content="Portage Press">'), 'the paper\'s name is unchanged');

    [$code, , $story] = req('portagepress.ca', '/story/portage-icon-check', $port);
    $storyHead = head_of($story);
    $card = $own . '/card/portage-icon-check.png';
    ok($code === 200, "the story page is 200 ($code)");
    ok(str_contains($storyHead, '<link rel="canonical" href="' . $own . '/story/portage-icon-check">'), 'a story canonical stays on the paper');
    ok(str_contains($storyHead, '<meta property="og:image" content="' . $card . '">'), 'a story share image is its card');
    ok(str_contains($storyHead, '<meta name="twitter:image" content="' . $card . '">'), 'twitter:image follows the story card');
    ok(str_contains($storyHead, 'name="robots" content="noindex'), 'a story stays noindex');

    [$code, , $bisonBody] = req('bisonbulletin.ca', '/', $port);
    $bisonHead = head_of($bisonBody);
    ok($code === 200 && str_contains($bisonHead, '<meta property="og:image" content="https://bisonbulletin.ca/assets/sites/bison-bulletin/og-default.png">'), 'Bison\'s share image is its own, on its own host');
    ok(str_contains($bisonHead, '<meta name="twitter:image" content="https://bisonbulletin.ca/assets/sites/bison-bulletin/og-default.png">'), 'Bison has twitter:image');
    ok(str_contains($bisonHead, '<meta name="theme-color" content="#8B0000">'), 'Bison theme-color is its red');
    ok(!str_contains($bisonHead, 'prairiedispatch.ca'), 'Bison\'s head does not name the flagship');

    [$code, $headers, $ico] = req('portagepress.ca', '/favicon.ico', $port);
    $portageFile = (string) file_get_contents("$root/assets/sites/portage-press/favicon.ico");
    ok($code === 200 && str_contains(strtolower($headers), 'content-type: image/x-icon') && $ico === $portageFile, 'GET /favicon.ico is Portage\'s icon');
    [$code, , $bisonIco] = req('bisonbulletin.ca', '/favicon.ico', $port);
    $bisonFile = (string) file_get_contents("$root/assets/sites/bison-bulletin/favicon.ico");
    ok($code === 200 && $bisonIco === $bisonFile && $bisonIco !== $portageFile, 'GET /favicon.ico is the host\'s own icon');
    [$code, , $touch] = req('portagepress.ca', '/apple-touch-icon.png', $port);
    ok($code === 200 && $touch === (string) file_get_contents("$root/assets/sites/portage-press/apple-touch-icon.png"), 'GET /apple-touch-icon.png is the paper\'s touch icon');
    [$code, , $pre] = req('portagepress.ca', '/apple-touch-icon-precomposed.png', $port);
    ok($code === 200 && $pre === $touch, 'the precomposed URL serves the same icon');

    [$code, , $flag] = req('prairiedispatch.ca', '/', $port);
    $flagHead = head_of($flag);
    ok($code === 200, "the flagship still renders ($code)");
    ok(!str_contains($flagHead, 'favicon.ico') && !str_contains($flagHead, 'apple-touch-icon'), 'a paper without the new files does not link them');
    ok(str_contains($flagHead, '<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">'), 'it keeps the SVG favicon');
    ok(str_contains($flagHead, '<meta name="theme-color" content="#17301C">'), 'theme-color falls back to the newsroom accent');
    ok(str_contains($flagHead, '<meta name="twitter:image" content="https://prairiedispatch.ca/assets/img/og-default.png">'), 'twitter:image falls back with og:image');
    ok(str_contains($flagHead, 'name="robots" content="noindex'), 'the flagship stays noindex');
    [$code] = req('prairiedispatch.ca', '/favicon.ico', $port);
    ok($code === 404, "a paper with no favicon.ico 404s at /favicon.ico ($code)");
}

if ($fails > 0 && is_file($fx['work'] . '/server.log')) {
    $log = (string) file_get_contents($fx['work'] . '/server.log');
    if ($log !== '') {
        echo "server log:\n" . substr($log, 0, 2000) . "\n";
    }
}

exit($fails > 0 ? 1 : 0);
