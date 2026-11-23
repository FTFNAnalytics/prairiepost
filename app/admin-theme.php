<?php
/**
 * Each paper's newsroom wears the paper (admin/_layout.php).
 *
 * The admin sheet (assets/css/admin.css) is the CivisMedia control room's
 * design, and its tokens are the hub's cyan and magenta. On a paper's own
 * domain the newsroom takes that paper's identity instead, from the `admin`
 * block of its assets/sites/<slug>/palette.json:
 *
 *   "admin": {
 *     "accent": "#041E42",        buttons, links, focus, a light nav's current page
 *     "spot":   "#AC162C",        the rarer colour: danger, errors, review chips
 *     "strip":  ["#FFFFFF", "#4B5563"],     the dateline rail: background, text
 *     "band":   ["#041E42", "#FFFFFF"],     the masthead row: background, text
 *     "nav":    ["#041E42", "#FFFFFF", "#AC162C", "#FFFFFF"],
 *                                 the nav row: background, text, and optionally
 *                                 the current page's background and text
 *     "mark":   "mark-reversed.svg",        drawn before the paper's name
 *     "logo":   "logo-header.png",          an image wordmark: replaces mark + name
 *     "wordmark": {"font": "…", "weight": 800, "case": "uppercase",
 *                  "spacing": "0.02em", "style": "normal", "color": "#0088B0"},
 *     "fonts":  {"heading": "…", "body": "…"}
 *   }
 *
 * accent and spot are required; anything left out falls back to the admin
 * sheet's own rule. mark and logo name a brand asset of the paper (or of
 * the network default under assets/img/). The 100–900 tint scales the sheet
 * uses are derived here from accent and spot, and the steps it sets text in
 * are darkened until they read at 4.5:1. tests/admin-brand.test.php checks
 * every committed block: valid values, contrast for every pairing it draws,
 * accent and spot far enough apart that the status chips stay distinct, and
 * every font it names declared by a stylesheet the admin loads. The hub has
 * no block, so the control room renders exactly as before.
 */

const PP_ADMIN_GROUND  = '#F3F2F2';   // admin.css --color-bg
const PP_ADMIN_SURFACE = '#EAE9E9';   // admin.css --color-surface (panels)

/* --- Colour arithmetic (sRGB, WCAG 2 contrast) ----------------------------- */

/** '#RRGGBB' → [r, g, b], each 0–255. */
function pp_hex_rgb(string $hex): array
{
    return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
}

/** [r, g, b] → '#RRGGBB' (clamped, rounded). */
function pp_rgb_hex(array $rgb): string
{
    return sprintf('#%02X%02X%02X', ...array_map(fn ($v): int => max(0, min(255, (int) round($v))), $rgb));
}

/** $t of colour $a mixed with (1 − $t) of colour $b. */
function pp_hex_mix(string $a, string $b, float $t): string
{
    $x = pp_hex_rgb($a);
    $y = pp_hex_rgb($b);
    return pp_rgb_hex(array_map(fn (int $i): float => $x[$i] * $t + $y[$i] * (1 - $t), [0, 1, 2]));
}

/** WCAG relative luminance, 0 (black) to 1 (white). */
function pp_luminance(string $hex): float
{
    $c = array_map(function (int $v): float {
        $v /= 255;
        return $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    }, pp_hex_rgb($hex));
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

/** WCAG contrast ratio between two colours, 1 to 21. */
function pp_contrast(string $a, string $b): float
{
    $la = pp_luminance($a);
    $lb = pp_luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/** Darken $hex toward black, as little as it takes to read at $ratio on every colour in $on. */
function pp_darken_to(string $hex, array $on, float $ratio = 4.5): string
{
    for ($share = 100; $share >= 0; $share -= 4) {
        $c = pp_hex_mix($hex, '#000000', $share / 100);
        if (min(array_map(fn (string $o): float => pp_contrast($c, $o), $on)) >= $ratio) {
            return $c;
        }
    }
    return '#000000';
}

/** CIE L*a*b* (D65) of an sRGB colour — for telling two colours apart. */
function pp_lab(string $hex): array
{
    $lin = array_map(function (int $v): float {
        $v /= 255;
        return $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    }, pp_hex_rgb($hex));
    $xyz = [
        (0.4124 * $lin[0] + 0.3576 * $lin[1] + 0.1805 * $lin[2]) / 0.95047,
        (0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2]),
        (0.0193 * $lin[0] + 0.1192 * $lin[1] + 0.9505 * $lin[2]) / 1.08883,
    ];
    $f = array_map(fn (float $t): float => $t > 216 / 24389 ? $t ** (1 / 3) : (24389 / 27 * $t + 16) / 116, $xyz);
    return [116 * $f[1] - 16, 500 * ($f[0] - $f[1]), 200 * ($f[1] - $f[2])];
}

/** CIE76 colour difference: under ~10 two colours read as "the same". */
function pp_delta_e(string $a, string $b): float
{
    [$l1, $a1, $b1] = pp_lab($a);
    [$l2, $a2, $b2] = pp_lab($b);
    return sqrt(($l1 - $l2) ** 2 + ($a1 - $a2) ** 2 + ($b1 - $b2) ** 2);
}

/**
 * The 100–900 scale the admin sheet uses, from one brand colour: tints mix
 * toward white, shades toward black. 700 and 800 carry text (the dateline,
 * panel heads, chip labels), so each is darkened until it reads at 4.5:1 on
 * the page, on the panels and on the 100 tint its chip sits on.
 */
function pp_admin_scale(string $base): array
{
    $s = [
        100 => pp_hex_mix($base, '#FFFFFF', 0.10),
        200 => pp_hex_mix($base, '#FFFFFF', 0.22),
        300 => pp_hex_mix($base, '#FFFFFF', 0.40),
        400 => pp_hex_mix($base, '#FFFFFF', 0.62),
        500 => pp_hex_mix($base, '#FFFFFF', 0.82),
        600 => pp_hex_mix($base, '#000000', 0.88),
        700 => pp_hex_mix($base, '#000000', 0.72),
        800 => pp_hex_mix($base, '#000000', 0.52),
        900 => pp_hex_mix($base, '#000000', 0.32),
    ];
    $on = [PP_ADMIN_GROUND, PP_ADMIN_SURFACE, $s[100]];
    $s[700] = pp_darken_to($s[700], $on);
    $s[800] = pp_darken_to($s[800], $on);
    return $s;
}

/* --- The block in palette.json --------------------------------------------- */

/** Public URL of a paper's brand asset, falling back to the network default; null when neither exists. */
function pp_admin_asset_url(string $slug, string $file): ?string
{
    if ($slug !== '' && is_file(PP_ROOT . "/assets/sites/$slug/$file")) {
        return "/assets/sites/$slug/$file";
    }
    return is_file(PP_ROOT . "/assets/img/$file") ? "/assets/img/$file" : null;
}

/**
 * Validate a paper's `admin` block. Returns ['spec' => validated values or
 * null, 'problems' => what was wrong]. A value that fails validation is
 * dropped, so the sheet's own rule applies to it, and is named in
 * 'problems'; tests/admin-brand.test.php requires that list to be empty for
 * every committed paper. Everything that reaches CSS is a #RRGGBB colour, a
 * font stack of letters, digits, spaces, quotes, commas and hyphens, or one
 * of a few fixed keywords — nothing that can close the <style> element.
 */
function pp_admin_theme_spec(array $brand, string $slug): array
{
    $in = $brand['admin'] ?? null;
    if (!is_array($in)) {
        return ['spec' => null, 'problems' => ['no "admin" block']];
    }
    $hex = fn ($v): bool => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v) === 1;
    $stack = fn ($v): bool => is_string($v) && strlen($v) <= 160 && preg_match('/^[A-Za-z0-9 ,\'"-]+$/', $v) === 1;
    $spec = [];
    $problems = [];

    foreach (array_diff(array_keys($in), ['accent', 'spot', 'strip', 'band', 'nav', 'mark', 'logo', 'wordmark', 'fonts']) as $k) {
        $problems[] = "unknown key \"$k\"";
    }
    foreach (['accent', 'spot'] as $k) {
        if ($hex($in[$k] ?? null)) {
            $spec[$k] = strtoupper($in[$k]);
        } else {
            $problems[] = "$k must be a #RRGGBB colour";
        }
    }
    foreach (['strip' => 2, 'band' => 2, 'nav' => 4] as $k => $max) {
        if (!array_key_exists($k, $in)) {
            continue;
        }
        $v = $in[$k];
        if (is_array($v) && array_is_list($v) && in_array(count($v), $max === 2 ? [2] : [2, 4], true)
            && count(array_filter($v, $hex)) === count($v)) {
            $spec[$k] = array_map('strtoupper', $v);
        } else {
            $problems[] = "$k must be a list of " . ($max === 2 ? '2' : '2 or 4') . ' #RRGGBB colours';
        }
    }
    foreach (['mark', 'logo'] as $k) {
        if (!array_key_exists($k, $in)) {
            continue;
        }
        $f = $in[$k];
        if (is_string($f) && preg_match('/^[a-z0-9][a-z0-9._-]*\.(svg|png)$/i', $f) === 1 && pp_admin_asset_url($slug, $f) !== null) {
            $spec[$k] = $f;
        } else {
            $problems[] = "$k must name an existing .svg or .png brand asset";
        }
    }
    foreach ((array) ($in['fonts'] ?? []) as $k => $v) {
        if (in_array($k, ['heading', 'body'], true) && $stack($v)) {
            $spec['fonts'][$k] = $v;
        } else {
            $problems[] = "fonts.$k must be heading or body, as a plain font stack";
        }
    }
    $wordmarkRules = [
        'font'    => $stack,
        'weight'  => fn ($v): bool => is_int($v) && $v >= 100 && $v <= 900 && $v % 100 === 0,
        'case'    => fn ($v): bool => in_array($v, ['none', 'uppercase'], true),
        'spacing' => fn ($v): bool => is_string($v) && preg_match('/^(normal|-?(0|0?\.[0-9]+|[1-9][0-9]*(\.[0-9]+)?)em)$/', $v) === 1,
        'style'   => fn ($v): bool => in_array($v, ['normal', 'italic'], true),
        'color'   => $hex,
    ];
    foreach ((array) ($in['wordmark'] ?? []) as $k => $v) {
        if (isset($wordmarkRules[$k]) && $wordmarkRules[$k]($v)) {
            $spec['wordmark'][$k] = $k === 'color' ? strtoupper($v) : $v;
        } else {
            $problems[] = "wordmark.$k is not valid";
        }
    }
    return ['spec' => isset($spec['accent'], $spec['spot']) ? $spec : null, 'problems' => $problems];
}

/**
 * Turn a validated block into what the layout draws: the custom properties
 * for the head's :root rule, the body classes, and the masthead's images.
 */
function pp_admin_theme_render(array $spec, string $slug): array
{
    $vars = [];
    foreach (['accent' => '--color-accent', 'spot' => '--color-accent-2'] as $k => $name) {
        $vars[$name] = $spec[$k];
        foreach (pp_admin_scale($spec[$k]) as $step => $hex) {
            $vars["$name-$step"] = $hex;
        }
    }
    foreach (['heading' => '--font-heading', 'body' => '--font-body'] as $k => $name) {
        if (isset($spec['fonts'][$k])) {
            $vars[$name] = $spec['fonts'][$k];
        }
    }
    $classes = ['pp-paper'];
    if (isset($spec['strip'])) {
        [$vars['--nh-strip-bg'], $vars['--nh-strip-fg']] = $spec['strip'];
    }
    if (isset($spec['band'])) {
        [$vars['--nh-band-bg'], $vars['--nh-band-fg']] = $spec['band'];
        if (pp_luminance($spec['band'][0]) < 0.2) {
            $classes[] = 'pp-band-dark';
        }
    }
    if (isset($spec['nav'])) {
        [$bg, $fg] = $spec['nav'];
        $dark = pp_luminance($bg) < 0.2;
        $vars['--nh-nav-bg'] = $bg;
        $vars['--nh-nav-fg'] = $fg;
        // The current page: as given, else the inverse on a dark bar (its
        // own text colour as the pill) or the accent on a light one.
        $vars['--nh-nav-active-bg'] = $spec['nav'][2] ?? ($dark ? $fg : $spec['accent']);
        $vars['--nh-nav-active-fg'] = $spec['nav'][3] ?? ($dark ? $bg : '#FFFFFF');
        // The signed-in name: a step quieter than the links, never below 4.5:1.
        $who = pp_hex_mix($fg, $bg, 0.88);
        $vars['--nh-nav-who'] = pp_contrast($who, $bg) >= 4.5 ? $who : $fg;
        if ($dark) {
            // A dark bar is its own edge; the newspaper head's thick ink rule
            // over it would read as a stray black line.
            $vars['--nh-nav-rule'] = $bg;
            $classes[] = 'pp-nav-dark';
        }
    } else {
        // The sheet's own nav: the accent's palest tint. Its grey for the
        // signed-in name (neutral-600) reads under 4.5:1 on that tint, so a
        // paper's newsroom takes the next grey down.
        $vars['--nh-nav-who'] = '#605D5D';
    }
    $wordmark = ['font' => '--nh-wm-font', 'weight' => '--nh-wm-weight', 'case' => '--nh-wm-case',
                 'spacing' => '--nh-wm-spacing', 'style' => '--nh-wm-style', 'color' => '--nh-wm-color'];
    foreach ($wordmark as $k => $name) {
        if (isset($spec['wordmark'][$k])) {
            $vars[$name] = (string) $spec['wordmark'][$k];
        }
    }
    $css = ':root{' . implode('', array_map(fn ($k, $v) => "$k:$v;", array_keys($vars), $vars)) . '}';
    return [
        'css'     => str_contains($css, '<') ? '' : $css,   // belt and braces: validation admits no '<'
        'classes' => implode(' ', $classes),
        'mark'    => isset($spec['mark']) ? pp_admin_asset_url($slug, $spec['mark']) : null,
        'logo'    => isset($spec['logo']) ? pp_admin_asset_url($slug, $spec['logo']) : null,
        'vars'    => $vars,
    ];
}

/**
 * A content stamp for the admin sheet's URL. No static asset is served with
 * Cache-Control, so browsers keep a heuristic copy, and a roll that changes
 * the sheet must not meet yesterday's: new masthead markup under the old
 * sheet draws a paper's mark at the browser's default 300px.
 */
function pp_admin_sheet_version(): string
{
    static $version = null;
    return $version ??= substr((string) md5_file(PP_ROOT . '/assets/css/admin.css'), 0, 10);
}

/**
 * The current paper's admin theme, or null on the hub (and on a paper with
 * no usable block), where the control room's own sheet applies unchanged.
 */
function pp_admin_theme(): ?array
{
    if (pp_is_hub()) {
        return null;
    }
    $slug = (string) (current_site()['slug'] ?? '');
    $spec = pp_admin_theme_spec(pp_brand_file(), $slug)['spec'];
    return $spec === null ? null : pp_admin_theme_render($spec, $slug);
}
