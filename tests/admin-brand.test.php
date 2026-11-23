<?php
/**
 * Each paper's newsroom wears the paper; the control room stays itself.
 *
 *   1. Every paper directory carries an "admin" block that validates in
 *      full (app/admin-theme.php drops nothing), and every colour pairing
 *      the admin draws from it reads: white button text on the accent and
 *      the accent as links on page and panel; the dateline rail; the
 *      masthead's name (large, 3:1) and its Newsroom label (4.5:1); the
 *      nav, its current page and the signed-in name; the derived text
 *      steps on page, panel and chip; and accent and spot far enough apart
 *      that a published chip never reads as a review one.
 *   2. Every font a block names is declared by an @font-face rule in a
 *      stylesheet the admin actually loads (admin.css and the fonts.css it
 *      imports) — rules parsed with comments stripped, never grepped
 *      (CLAUDE.md lesson 9) — and every stack ends in a generic family.
 *   3. Rendered for real, one process per tenant over a disposable
 *      database: each paper's admin head carries its own accent, body
 *      classes, name or logo and label, and every image it draws exists;
 *      the hub renders the control room exactly as before — no theme rule,
 *      no body class, the CivisMedia mark.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

define('PP_ROOT', $root);
require $root . '/app/admin-theme.php';

/* --- 1. Every block validates, and every pairing reads -------------------- */

$papers = [];
foreach (glob("$root/assets/sites/*", GLOB_ONLYDIR) as $dir) {
    $slug = basename($dir);
    if ($slug === 'civismedia') {
        continue;   // the hub: no block, the control room's own sheet
    }
    $brand = json_decode((string) file_get_contents("$dir/palette.json"), true);
    ['spec' => $spec, 'problems' => $problems] = pp_admin_theme_spec(is_array($brand) ? $brand : [], $slug);
    ok($problems === [] && $spec !== null, "$slug: admin block validates in full" . ($problems ? ' — ' . implode('; ', $problems) : ''));
    if ($spec !== null) {
        $papers[$slug] = $spec;
    }
}
ok(count($papers) >= 27, 'every paper directory is themed (' . count($papers) . ')');
$hubBrand = json_decode((string) file_get_contents("$root/assets/sites/civismedia/palette.json"), true);
ok(!isset($hubBrand['admin']), 'the hub carries no admin block, so the control room is untouched');

$G = PP_ADMIN_GROUND;
$S = PP_ADMIN_SURFACE;
$reads = function (string $slug, string $what, string $fg, string $bg, float $min): void {
    $r = pp_contrast($fg, $bg);
    ok($r >= $min, sprintf('%s: %s reads at %.2f:1 (needs %.1f) — %s on %s', $slug, $what, $r, $min, $fg, $bg));
};
foreach ($papers as $slug => $spec) {
    $v = pp_admin_theme_render($spec, $slug)['vars'];
    $a = $spec['accent'];
    $reads($slug, 'white text on the accent (buttons)', '#FFFFFF', $a, 4.5);
    $reads($slug, 'the accent as a link on the page', $a, $G, 4.5);
    $reads($slug, 'the accent as a link on a panel', $a, $S, 4.5);
    foreach (['--color-accent', '--color-accent-2'] as $scale) {
        foreach ([700, 800] as $step) {
            foreach ([$G, $S, $v["$scale-100"]] as $on) {
                $reads($slug, "$scale-$step text", $v["$scale-$step"], $on, 4.5);
            }
        }
    }
    [$sb, $sf] = $spec['strip'] ?? [$G, $v['--color-accent-700']];
    $reads($slug, 'the dateline rail', $sf, $sb, 4.5);
    [$bb, $bf] = $spec['band'] ?? [$G, '#201E1D'];
    if (!isset($spec['logo'])) {
        $reads($slug, 'the paper\'s name in the masthead (large type)', $spec['wordmark']['color'] ?? $bf, $bb, 3.0);
    }
    $reads($slug, 'the Newsroom label (at its .85 opacity)', pp_hex_mix($bf, $bb, 0.85), $bb, 4.5);
    $navBg = $v['--nh-nav-bg'] ?? $v['--color-accent-100'];
    $reads($slug, 'the nav links', $v['--nh-nav-fg'] ?? '#201E1D', $navBg, 4.5);
    $activeBg = $v['--nh-nav-active-bg'] ?? $a;
    $reads($slug, 'the current page in the nav', $v['--nh-nav-active-fg'] ?? $G, $activeBg, 4.5);
    ok(pp_delta_e($activeBg, $navBg) >= 20, "$slug: the current page's pill stands out from its bar (ΔE " . round(pp_delta_e($activeBg, $navBg)) . ')');
    $reads($slug, 'the signed-in name', $v['--nh-nav-who'], $navBg, 4.5);
    if (pp_luminance($bb) < 0.2) {
        $reads($slug, 'a masthead button, drawn in the dark band\'s inverse', $bb, $bf, 4.5);
    }
    // Status chips: published and scheduled take the accent, in review and
    // errors the spot. Two colours of one family would make them the same.
    ok(pp_delta_e($a, $spec['spot']) >= 30, "$slug: accent and spot are different colours (ΔE " . round(pp_delta_e($a, $spec['spot'])) . ')');
    ok(pp_delta_e($v['--color-accent-800'], $v['--color-accent-2-800']) >= 20,
        "$slug: a published chip's label and a review chip's label differ (ΔE " . round(pp_delta_e($v['--color-accent-800'], $v['--color-accent-2-800'])) . ')');
}

/* --- 2. Every named font is declared where the admin can load it ---------- */

$declared = [];
$sheets = ["$root/assets/css/admin.css"];
$css = '';
while ($sheet = array_shift($sheets)) {
    $text = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($sheet));
    $css .= $text;
    if (preg_match_all('#@import\s+url\(\s*[\'"]?(/assets/css/[a-z0-9.-]+\.css)#i', $text, $m)) {
        foreach ($m[1] as $import) {
            $sheets[] = $root . $import;
        }
    }
}
if (preg_match_all('/@font-face\s*\{([^}]*)\}/i', $css, $faces)) {
    foreach ($faces[1] as $body) {
        if (preg_match('/font-family\s*:\s*[\'"]?([^;\'"]+)/i', $body, $fm) && preg_match('/src\s*:\s*url\(/i', $body)) {
            $declared[strtolower(trim($fm[1]))] = true;
        }
    }
}
ok(isset($declared['source serif 4'], $declared['playfair display']), 'the admin sheets declare faces (parsed: ' . count($declared) . ' families)');
foreach ($papers as $slug => $spec) {
    $stacks = array_filter([
        'heading' => $spec['fonts']['heading'] ?? null,
        'body' => $spec['fonts']['body'] ?? null,
        'wordmark' => $spec['wordmark']['font'] ?? null,
    ]);
    foreach ($stacks as $role => $stack) {
        $families = array_map(fn ($f) => strtolower(trim($f, " '\"")), explode(',', $stack));
        ok(isset($declared[$families[0]]), "$slug: the $role font '{$families[0]}' is declared by a sheet the admin loads");
        ok(in_array(end($families), ['serif', 'sans-serif', 'monospace'], true), "$slug: the $role stack ends in a generic family");
    }
}

/* --- 3. Rendered for real: each paper's head, and the hub unchanged ------- */

require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('adminbrand');
pp_fixture_prepare($fx);
register_shutdown_function(fn () => pp_fixture_destroy($fx));
$pdo = pp_fixture_connect($fx);

// Provision every tenant as a site row with its own title, the way its
// launch pack would (the founding site is seed-core's).
$titles = [];
foreach (glob("$root/assets/sites/*", GLOB_ONLYDIR) as $dir) {
    $slug = basename($dir);
    $pack = is_file("$dir/launch.php") ? require "$dir/launch.php" : [];
    $titles[$slug] = (string) ($pack['settings']['site_title'] ?? '');
    $exists = $pdo->prepare('SELECT id FROM sites WHERE slug = ?');
    $exists->execute([$slug]);
    $id = $exists->fetchColumn();
    if ($id === false) {
        $pdo->prepare('INSERT INTO sites (name, slug, domain, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$titles[$slug] ?: $slug, $slug, '', date('Y-m-d H:i:s')]);
        $exists->execute([$slug]);
        $id = $exists->fetchColumn();
        if ($titles[$slug] !== '') {
            $pdo->prepare('INSERT INTO settings (site_id, skey, svalue) VALUES (?, ?, ?)')->execute([(int) $id, 'site_title', $titles[$slug]]);
        }
    }
}

$driver = $fx['work'] . '/render-admin-head.php';
file_put_contents($driver, "<?php\nrequire \$argv[1] . '/app/bootstrap.php';\nrequire \$argv[1] . '/admin/_layout.php';\n"
    . "admin_header('Stories', 'posts');\nadmin_footer();\n");
$render = function (string $slug) use ($fx, $driver, $root): string {
    exec('PP_CONFIG=' . escapeshellarg($fx['config']) . ' PP_SITE=' . escapeshellarg($slug) . ' '
        . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($driver) . ' ' . escapeshellarg($root) . ' 2>&1', $out, $code);
    return $code === 0 ? implode("\n", $out) : "EXIT $code: " . implode("\n", $out);
};

foreach ($papers as $slug => $spec) {
    $html = $render($slug);
    if (!ok_page($html, $slug)) {
        continue;
    }
    ok(str_contains($html, '<style>:root{--color-accent:' . $spec['accent'] . ';'), "$slug: the head carries its own accent");
    ok(preg_match('#<style>([^<]*)</style>#', $html, $st) === 1, "$slug: the theme rule is one plain style element");
    ok(preg_match('/<body class="pp-paper[^"]*">/', $html) === 1, "$slug: the body is marked as a paper's newsroom");
    ok(str_contains($html, 'nh-mark nh-mark--paper') && !str_contains($html, 'CivisMedia <span>'), "$slug: its own masthead, not CivisMedia's");
    ok(str_contains($html, '<span class="nh-mark__label">Newsroom</span>'), "$slug: the masthead says Newsroom");
    if (isset($spec['logo'])) {
        ok(preg_match('#<img class="nh-mark__logo" src="([^"]+)" alt="[^"]+">#', $html, $img) === 1 && is_file($root . $img[1]),
            "$slug: its image wordmark is drawn, with alt text, from a file that exists");
    } else {
        $name = htmlspecialchars($titles[$slug] !== '' ? $titles[$slug] : 'The Prairie Dispatch', ENT_QUOTES, 'UTF-8');
        ok(str_contains($html, '<span class="nh-mark__name">' . $name . '</span>'), "$slug: the masthead names the paper ($name)");
    }
    if (isset($spec['mark'])) {
        ok(preg_match('#<img class="nh-mark__img" src="([^"]+)" alt="">#', $html, $img) === 1 && is_file($root . $img[1]),
            "$slug: its mark is drawn from a file that exists");
    }
}

function ok_page(string $html, string $slug): bool
{
    $good = str_contains($html, '</html>') && !str_contains($html, 'EXIT ') && !preg_match('/(Warning|Notice|Deprecated|Fatal error):/', $html);
    ok($good, "$slug: the admin head renders cleanly" . ($good ? '' : ":\n    " . substr($html, 0, 600)));
    return $good;
}

$hub = $render('civismedia');
if (ok_page($hub, 'civismedia')) {
    ok(!str_contains($hub, '<style>'), 'the hub: no theme rule');
    $stamp = substr((string) md5_file("$root/assets/css/admin.css"), 0, 10);
    ok(str_contains($hub, "<link rel=\"stylesheet\" href=\"/assets/css/admin.css?v=$stamp\">\n</head>\n<body>\n"),
        'the hub: the sheet is stamped with its own content hash, nothing follows it in the head, and the body has no class');
    ok(str_contains($hub, '<a class="nh-mark" href="index.php">CivisMedia <span>Control room</span></a>'), 'the hub: the CivisMedia control-room mark');
}

exit($fails ? 1 : 0);
