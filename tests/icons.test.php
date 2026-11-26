<?php
/**
 * Every paper serves its own icon set and its own share card
 * (PLAN.md 10.8), and a story page's social tags name that paper.
 *
 * What was wrong: the only icon was an SVG link, so /favicon.ico 404'd
 * and a crawler drew no favicon; site_asset() filled a missing mark or
 * share card with the flagship's files, so Kelowna and Kermode named the
 * Prairie Dispatch as publisher and fourteen papers shared its landscape.
 * A story's og:image was a generated text card, with no locale, no
 * article times and no Twitter title or image.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';

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

function ico_widths(string $bin): array
{
    if (strlen($bin) < 6 || substr($bin, 0, 4) !== "\x00\x00\x01\x00") {
        return [];
    }
    $n = unpack('v', substr($bin, 4, 2))[1];
    $widths = [];
    for ($i = 0; $i < $n; $i++) {
        $w = ord($bin[6 + 16 * $i]);
        $widths[] = $w === 0 ? 256 : $w;
    }
    return $widths;
}

/* --- The committed files, one set per paper -------------------------------- */

$networkCard = hash_file('sha256', "$root/assets/img/og-default.png");
$flagIcon = hash_file('sha256', "$root/assets/sites/prairiedispatch/icons/icon-512.png");
$sites = glob("$root/assets/sites/*", GLOB_ONLYDIR) ?: [];
ok(count($sites) >= 25, 'the network has its papers on disk (' . count($sites) . ')');

foreach ($sites as $dir) {
    $slug = basename($dir);
    $ico = "$dir/icons/favicon.ico";
    ok(is_file($ico), "$slug has a favicon.ico");
    if (is_file($ico)) {
        $widths = ico_widths((string) file_get_contents($ico));
        ok(in_array(48, $widths, true), "$slug favicon.ico carries a 48px image (" . implode(',', $widths) . ')');
        ok(substr((string) file_get_contents($ico), 0, 4) === "\x00\x00\x01\x00", "$slug favicon.ico is an ICO");
    }
    foreach ([32 => 'icon-32.png', 48 => 'icon-48.png', 180 => 'icon-180.png', 192 => 'icon-192.png', 512 => 'icon-512.png'] as $size => $name) {
        $path = "$dir/icons/$name";
        $info = is_file($path) ? @getimagesize($path) : false;
        ok($info !== false && $info[0] === $size && $info[1] === $size, "$slug $name is a {$size}px square");
    }
    if ($slug !== 'prairiedispatch') {
        ok(hash_file('sha256', "$dir/icons/icon-512.png") !== $flagIcon, "$slug's icon is not the flagship's mark");
        $card = "$dir/og-default.png";
        $info = is_file($card) ? @getimagesize($card) : false;
        ok($info !== false && $info[0] === 1200 && $info[1] === 630, "$slug has its own 1200×630 share card");
        if (is_file($card)) {
            ok(hash_file('sha256', $card) !== $networkCard, "$slug's share card is not the flagship landscape");
        }
    }
}
ok(is_file("$root/assets/img/og-default.png"), 'the flagship keeps its designed landscape');

// Red River's meander used to be an SVG mask, which paints blank as a tab
// icon. The path itself is the cut, in the mark's clay, not the white reverse.
$river = (string) file_get_contents("$root/assets/sites/red-river-register/favicon.svg");
ok($river === (string) file_get_contents("$root/assets/sites/red-river-register/mark.svg"), 'Red River favicon and mark are the same drawing');
ok(preg_match('/<mask\b/i', $river) !== 1, 'Red River icon has no SVG mask');
ok(str_contains($river, 'fill="#7E3517"'), 'Red River icon keeps the clay mark');
foreach ([
    'portage-press' => '#041E42',
    'bison-bulletin' => '#8B0000',
    'brandon-bulletin' => '#E6BF2E',
    'red-river-register' => '#7E3517',
] as $slug => $theme) {
    $brand = json_decode((string) file_get_contents("$root/assets/sites/$slug/palette.json"), true);
    ok(strtoupper((string) ($brand['theme'] ?? '')) === $theme, "$slug names theme-color $theme");
}

/* --- A monogram, for a paper that has no mark ------------------------------ */

define('PP_ROOT', $root);
require $root . '/app/icons.php';
$mono = pp_png_monogram(48, 'KC', '#0C3448', '#FBFAF5');
$info = getimagesizefromstring($mono);
ok($info !== false && $info[0] === 48 && $info[1] === 48, 'a monogram is a 48px PNG');
ok(pp_monogram_letters('The Kelowna Current') === 'KC', 'a leading The is not a letter in the mark');
ok(pp_monogram_letters('Le Bleuet Blanc') === 'BB', 'a leading Le is not either');
$packed = pp_pack_ico([
    16 => pp_png_monogram(16, 'KC', '#0C3448', '#FBFAF5'),
    32 => pp_png_monogram(32, 'KC', '#0C3448', '#FBFAF5'),
    48 => $mono,
]);
ok(ico_widths($packed) === [16, 32, 48], 'a packed monogram ICO carries 16, 32 and 48');

/* --- What a reader and a crawler get -------------------------------------- */

$fx = pp_fixture_create('icons');
pp_fixture_prepare($fx);
register_shutdown_function(function () use ($fx, $root) {
    @unlink("$root/uploads/icons-test-feature.png");
    pp_fixture_destroy($fx);
});
$pdo = pp_fixture_connect($fx);

foreach (['kelowna-current', 'bleuet-blanc', 'surrey-standard', 'prairiedispatch'] as $slug) {
    [$out, $exit] = pp_fixture_tool($fx, 'tools/seed-launch.php', 'PP_SITE=' . escapeshellarg($slug));
    ok($exit === 0, "seed $slug ($out)");
}

// A config shaped like production's: papers launched without a config arm
// would otherwise print the flagship's address.
$base = (string) file_get_contents($fx['config']);
$flagship = $fx['work'] . '/config-flagship.php';
file_put_contents($flagship, str_replace("'site_url' => ''", "'site_url' => 'https://prairiedispatch.ca'", $base));

$render = function (string $host, string $script, array $get = [], string $uri = '/') use ($fx, $root, $flagship): string {
    $driver = "{$fx['work']}/render-icons.php";
    file_put_contents($driver, "<?php\nchdir(\$argv[1]);\n\$_SERVER['REQUEST_URI'] = \$argv[5];\n\$_SERVER['REQUEST_METHOD'] = 'GET';\n"
        . "\$_SERVER['HTTPS'] = 'on';\n\$_SERVER['HTTP_HOST'] = \$argv[2];\n\$_SERVER['HTTP_USER_AGENT'] = 'pp-icons-test';\n"
        . "\$_GET = json_decode(\$argv[4], true);\nrequire \$argv[1] . '/' . \$argv[3];\n");
    exec('PP_CONFIG=' . escapeshellarg($flagship) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($driver) . ' '
        . escapeshellarg($root) . ' ' . escapeshellarg($host) . ' ' . escapeshellarg($script) . ' '
        . escapeshellarg(json_encode($get)) . ' ' . escapeshellarg($uri) . ' 2>&1', $out, $code);
    return $code === 0 ? implode("\n", $out) : "EXIT $code: " . implode("\n", $out);
};

$ask = function (string $host, string $code) use ($root, $flagship): string {
    $php = '$_SERVER["HTTP_HOST"] = ' . var_export($host, true) . '; $_SERVER["HTTPS"] = "on"; $_SERVER["REQUEST_METHOD"] = "GET"; '
        . 'require ' . var_export("$root/app/bootstrap.php", true) . '; ' . $code;
    return (string) shell_exec('cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($flagship) . ' '
        . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($php) . ' 2>&1');
};

$own = 'https://kelownacurrent.ca';
$front = $render('kelownacurrent.ca', 'index.php', [], '/');
ok(str_contains($front, '<link rel="canonical" href="' . $own . '/">'), 'Kelowna\'s front is canonical on its own host');
ok(str_contains($front, '<meta property="og:site_name" content="Kelowna Current">'), 'og:site_name is the paper');
ok(str_contains($front, '<meta property="og:locale" content="en_CA">'), 'og:locale is en_CA');
ok(str_contains($front, '<meta property="og:image" content="' . $own . '/assets/sites/kelowna-current/og-default.png">'), 'the front shares Kelowna\'s own card');
ok(str_contains($front, '<meta name="twitter:card" content="summary_large_image">'), 'twitter card is a large image');
ok(str_contains($front, '<meta name="twitter:image" content="' . $own . '/assets/sites/kelowna-current/og-default.png">'), 'twitter:image is that card');
ok(str_contains($front, '<link rel="icon" href="/favicon.ico" sizes="48x48">'), 'the front links /favicon.ico');
ok(str_contains($front, '<link rel="icon" type="image/png" href="/icon-48.png" sizes="48x48">'), 'and a 48px PNG');
ok(str_contains($front, '<link rel="apple-touch-icon" href="/apple-touch-icon.png">'), 'and an apple touch icon');
ok(str_contains($front, '<link rel="manifest" href="/site.webmanifest">'), 'and a manifest');
ok(str_contains($front, 'rel="icon" type="image/svg+xml" href="/assets/sites/kelowna-current/favicon.svg"'), 'the SVG is Kelowna\'s, not the network\'s');
ok(str_contains($front, '"logo":{"@type":"ImageObject","url":"' . str_replace('/', '\/', $own . '/icon-512.png') . '"'), 'publisher logo is the paper\'s icon');
ok(!str_contains($front, 'assets/img/mark.svg') && !str_contains($front, 'assets/img/og-default.png'), 'Kelowna\'s front does not carry the flagship\'s art');
ok(!str_contains($front, 'prairiedispatch.ca') && !str_contains($front, 'Civis Media'), 'and does not name the flagship or the hub');

$desk = (string) $pdo->query("SELECT c.slug FROM categories c
    JOIN posts p ON p.category_id = c.id
    JOIN post_sites ps ON ps.post_id = p.id
    JOIN sites s ON s.id = ps.site_id
    WHERE s.slug = 'kelowna-current' AND p.status = 'published' AND p.post_type <> 'link' LIMIT 1")->fetchColumn();
if (ok($desk !== '', 'Kelowna has a desk to open')) {
    $section = $render('kelownacurrent.ca', 'section.php', ['slug' => $desk], '/desk/' . $desk);
    ok(str_contains($section, '<link rel="canonical" href="' . $own . '/desk/' . $desk . '">'), 'a desk page is canonical on Kelowna');
    ok(str_contains($section, '<meta property="og:type" content="website">'), 'a desk is a website, not an article');
    ok(str_contains($section, '<meta property="og:image" content="' . $own . '/assets/sites/kelowna-current/og-default.png">'), 'a desk shares Kelowna\'s card');
    ok(str_contains($section, '<link rel="icon" href="/favicon.ico"'), 'a desk links the favicon');
    ok(!str_contains($section, 'assets/img/og-default.png'), 'a desk does not share the flagship landscape');
}

$row = $pdo->query("SELECT p.id, p.slug, p.title, p.byline, p.published_at FROM posts p
    JOIN post_sites ps ON ps.post_id = p.id JOIN sites s ON s.id = ps.site_id
    WHERE s.slug = 'kelowna-current' AND p.status = 'published' AND p.post_type <> 'link' ORDER BY p.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (ok(is_array($row) && $row !== [], 'Kelowna has a published story')) {
    if (!is_dir("$root/uploads")) {
        mkdir("$root/uploads", 0775, true);
    }
    $im = imagecreatetruecolor(40, 20);
    imagefilledrectangle($im, 0, 0, 39, 19, imagecolorallocate($im, 12, 52, 72));
    imagepng($im, "$root/uploads/icons-test-feature.png");
    imagedestroy($im);
    $pdo->prepare('UPDATE posts SET image = ?, image_alt = ? WHERE id = ?')
        ->execute(['/uploads/icons-test-feature.png', 'Okanagan volunteers', (int) $row['id']]);

    $story = $render('kelownacurrent.ca', 'article.php', ['slug' => $row['slug']], '/story/' . $row['slug']);
    $url = $own . '/story/' . $row['slug'];
    $feature = $own . '/uploads/icons-test-feature.png';
    ok(str_contains($story, '<link rel="canonical" href="' . $url . '">'), 'the story canonical is on Kelowna');
    ok(str_contains($story, '<meta property="og:url" content="' . $url . '">'), 'og:url matches it');
    ok(str_contains($story, '<meta property="og:type" content="article">'), 'og:type is article');
    ok(str_contains($story, '<meta property="og:site_name" content="Kelowna Current">'), 'the story names Kelowna');
    ok(str_contains($story, '<meta property="og:title" content="' . htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') . '">'), 'og:title is the headline');
    ok(str_contains($story, '<meta name="description" content="'), 'the story has a meta description');
    ok(str_contains($story, '<meta property="og:image" content="' . $feature . '">'), 'og:image is the feature image, absolute, on this host');
    ok(str_contains($story, '<meta property="og:image:width" content="40">'), 'and its width');
    ok(str_contains($story, '<meta property="og:image:height" content="20">'), 'and its height');
    ok(str_contains($story, '<meta property="og:image:alt" content="Okanagan volunteers">'), 'and its alt');
    ok(str_contains($story, '<meta name="twitter:image" content="' . $feature . '">'), 'twitter:image is the same photograph');
    ok(str_contains($story, '<meta name="twitter:title" content="'), 'twitter:title is set');
    ok(preg_match('/<meta property="article:published_time" content="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}">/', $story) === 1, 'article:published_time is an ISO stamp');
    ok(preg_match('/<meta property="article:modified_time" content="\d{4}-\d{2}-\d{2}T/', $story) === 1, 'article:modified_time is set');
    ok(str_contains($story, '<meta property="article:section" content="'), 'article:section is set');
    if (trim((string) $row['byline']) !== '') {
        ok(str_contains($story, '<meta property="article:author" content="' . htmlspecialchars($row['byline'], ENT_QUOTES, 'UTF-8') . '">'), 'article:author is the byline');
    }
    ok(str_contains($story, '"@type":"NewsArticle"'), 'NewsArticle data is present');
    ok(str_contains($story, '"publisher":{"@type":"NewsMediaOrganization","name":"Kelowna Current"'), 'the publisher is Kelowna Current');
    ok(str_contains($story, '"logo":{"@type":"ImageObject","url":"' . str_replace('/', '\/', $own . '/icon-512.png') . '"'), 'the publisher logo is Kelowna\'s icon');
    ok(!str_contains($story, '/card/' . $row['slug'] . '.png'), 'the share image is not the generated text card');
    ok(!str_contains($story, 'assets/img/mark.svg') && !str_contains($story, 'prairiedispatch.ca') && !str_contains($story, 'Civis'), 'the story does not leak the flagship or the hub');

    // An SVG illustration is not a share image. The paper's card stands in.
    $svg = '/assets/sites/kelowna-current/favicon.svg';
    $pdo->prepare('UPDATE posts SET image = ?, image_alt = ? WHERE id = ?')->execute([$svg, '', (int) $row['id']]);
    $fallback = $render('kelownacurrent.ca', 'article.php', ['slug' => $row['slug']], '/story/' . $row['slug']);
    ok(str_contains($fallback, '<meta property="og:image" content="' . $own . '/assets/sites/kelowna-current/og-default.png">'), 'an SVG feature falls back to the paper\'s card');
    ok(str_contains($fallback, '<meta property="og:image:width" content="1200">'), 'that card publishes its width');
}

$bleu = $render('bleuetblanc.ca', 'index.php', [], '/');
ok(str_contains($bleu, '<html lang="fr">'), 'Bleuet Blanc is French');
ok(str_contains($bleu, '<meta property="og:locale" content="fr_CA">'), 'its og:locale is fr_CA');
ok(str_contains($bleu, '"inLanguage":"fr-CA"'), 'its structured data says fr-CA');
ok(str_contains($bleu, '<meta property="og:site_name" content="Le Bleuet Blanc">'), 'and names itself');
ok(!str_contains($bleu, 'assets/img/og-default.png'), 'its share image is not the flagship landscape');

$surrey = $render('surreystandard.ca', 'index.php', [], '/');
ok(str_contains($surrey, '<link rel="canonical" href="https://surreystandard.ca/">'), 'Surrey\'s canonical is its own host, not the flagship');
ok(str_contains($surrey, '<meta property="og:url" content="https://surreystandard.ca/">'), 'Surrey\'s og:url is too');
ok(str_contains($surrey, '<meta property="og:image" content="https://surreystandard.ca/assets/sites/surrey-standard/og-default.png">'), 'Surrey shares its own card');
ok(str_contains($surrey, '<meta property="og:site_name" content="The Surrey Standard">'), 'Surrey names itself');
ok(!str_contains($surrey, 'prairiedispatch.ca') && !str_contains($surrey, 'assets/img/'), 'Surrey\'s front carries none of the flagship');

$ico = $ask('kelownacurrent.ca', '$r = pp_icon_response("ico"); echo $r[2], " ", $r[0], " ", bin2hex(substr($r[1], 0, 4)), " ", strlen($r[1]);');
ok(str_contains($ico, '200 image/x-icon 00000100'), 'Kelowna\'s /favicon.ico is an ICO (' . trim($ico) . ')');
$man = $ask('kelownacurrent.ca', '$r = pp_icon_response("manifest"); echo $r[1];');
ok(str_contains($man, '"name":"Kelowna Current"'), 'the manifest names Kelowna Current');
ok(str_contains($man, '"src":"/icon-192.png"'), 'and points at the 192px icon');
ok(!str_contains($man, 'Prairie') && !str_contains($man, 'Civis'), 'the manifest does not name another paper');

$surreyIco = $ask('surreystandard.ca', '$r = pp_icon_response("ico"); echo md5($r[1]);');
$kelownaIco = $ask('kelownacurrent.ca', '$r = pp_icon_response("ico"); echo md5($r[1]);');
ok(trim($surreyIco) !== '' && trim($surreyIco) !== trim($kelownaIco), 'two papers do not serve the same favicon.ico');

exit($fails ? 1 : 0);
