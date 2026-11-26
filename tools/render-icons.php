<?php
/**
 * Rasterize each paper's favicon and, where it has none, its default
 * share card.
 *
 *     php tools/render-icons.php            # every paper
 *     php tools/render-icons.php kelowna-current
 *
 * Icons are written to assets/sites/<slug>/icons/ from that paper's
 * favicon.svg (the flagship's is assets/img/favicon.svg). A share card
 * is written only when og-default.png is missing, so a designed card is
 * never replaced. Needs rsvg-convert for a real mark; without it, or
 * when the mark is only a monogram, the letters are drawn with GD.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}

$root = dirname(__DIR__);
define('PP_ROOT', $root);
require $root . '/app/icons.php';

$only = $argv[1] ?? '';
$font = $root . '/assets/fonts/archivo-narrow-700.ttf';
$rsvg = trim((string) shell_exec('command -v rsvg-convert'));

/** @return array{0:string,1:string,2:string} ink, paper, accent */
function brand_colors(string $paletteFile): array
{
    $ink = '#17301C';
    $paper = '#F7F6F3';
    $accent = '#9C3B22';
    if (!is_file($paletteFile)) {
        return [$ink, $paper, $accent];
    }
    $data = json_decode((string) file_get_contents($paletteFile), true);
    $p = is_array($data) ? ($data['palette'] ?? []) : [];
    $hex = static function ($value, string $fallback): string {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? $value : $fallback;
    };
    $ink = $hex($p['ink'] ?? null, $hex($p['accent'] ?? null, $ink));
    $paper = $hex($p['paper'] ?? null, $hex($p['surface'] ?? null, $paper));
    $accent = $hex($p['accent'] ?? null, $hex($p['red'] ?? null, $hex($p['hill'] ?? null, $ink)));
    return [$ink, $paper, $accent];
}

function pack_name(string $launchFile, string $slug): array
{
    $name = $slug === 'prairiedispatch' ? 'The Prairie Dispatch' : $slug;
    $tagline = '';
    if (is_file($launchFile)) {
        $pack = include $launchFile;
        if (is_array($pack)) {
            $settings = $pack['settings'] ?? [];
            if (!empty($settings['site_title'])) {
                $name = (string) $settings['site_title'];
            }
            $tagline = trim((string) ($settings['tagline'] ?? ''));
        }
    }
    return [$name, $tagline];
}

/** Source SVG for the icon. The small favicon, then the mark. */
function source_svg(string $root, string $slug): ?string
{
    foreach (['favicon.svg', 'mark.svg', 'logo-primary.svg'] as $file) {
        $path = "$root/assets/sites/$slug/$file";
        if (is_file($path)) {
            return $path;
        }
    }
    if ($slug === 'prairiedispatch') {
        foreach (['favicon.svg', 'mark.svg'] as $file) {
            $path = "$root/assets/img/$file";
            if (is_file($path)) {
                return $path;
            }
        }
    }
    return null;
}

/**
 * Fit a rendered PNG into a square.
 * A mark that already paints its background (opaque corners) is
 * letterboxed in that colour. A mark on a transparent ground sits on
 * the paper colour when it is dark and on the ink when it is light,
 * so a pale monogram does not disappear.
 */
function square_png(string $srcPath, int $size, string $paper, string $ink): string
{
    $src = imagecreatefrompng($srcPath);
    if ($src === false) {
        return pp_png_monogram($size, 'N', $ink, $paper);
    }
    imagealphablending($src, false);
    imagesavealpha($src, true);
    $sw = imagesx($src);
    $sh = imagesy($src);
    $corner = imagecolorat($src, 0, 0);
    $cornerAlpha = ($corner >> 24) & 0x7F;
    if ($cornerAlpha < 8) {
        $bg = [($corner >> 16) & 0xFF, ($corner >> 8) & 0xFF, $corner & 0xFF];
    } else {
        $lum = 0.0;
        $n = 0;
        $step = max(1, (int) ($sw / 24));
        for ($y = 0; $y < $sh; $y += $step) {
            for ($x = 0; $x < $sw; $x += $step) {
                $c = imagecolorat($src, $x, $y);
                if ((($c >> 24) & 0x7F) > 16) {
                    continue;
                }
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $lum += (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
                $n++;
            }
        }
        $avg = $n > 0 ? $lum / $n : 0.0;
        $bg = pp_icon_hex_rgb($avg > 0.72 ? $ink : $paper);
    }
    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, true);
    $fill = imagecolorallocate($canvas, $bg[0], $bg[1], $bg[2]);
    imagefilledrectangle($canvas, 0, 0, $size - 1, $size - 1, $fill);
    $dx = (int) (($size - $sw) / 2);
    $dy = (int) (($size - $sh) / 2);
    imagecopy($canvas, $src, $dx, $dy, 0, 0, $sw, $sh);
    imagedestroy($src);
    ob_start();
    imagepng($canvas, null, 9);
    imagedestroy($canvas);
    return (string) ob_get_clean();
}

/** True when an SVG's text did not paint (a missing face leaves a flat tile). */
function looks_blank(string $pngPath): bool
{
    $im = imagecreatefrompng($pngPath);
    if ($im === false) {
        return true;
    }
    $w = imagesx($im);
    $h = imagesy($im);
    $samples = [];
    for ($i = 0; $i < 16; $i++) {
        $x = (int) ($w * (0.3 + 0.4 * (($i % 4) / 3)));
        $y = (int) ($h * (0.3 + 0.4 * ((int) ($i / 4) / 3)));
        $c = imagecolorat($im, min($w - 1, $x), min($h - 1, $y));
        $samples[] = (($c >> 16) & 0xFF) . ',' . (($c >> 8) & 0xFF) . ',' . ($c & 0xFF);
    }
    imagedestroy($im);
    return count(array_unique($samples)) < 2;
}

function raster_svg(string $rsvg, string $svg, int $size, string $work): ?string
{
    if ($rsvg === '' || !is_file($svg)) {
        return null;
    }
    $out = $work . '/src-' . $size . '.png';
    $cmd = escapeshellarg($rsvg) . ' -w ' . $size . ' -h ' . $size . ' -a '
        . escapeshellarg($svg) . ' -o ' . escapeshellarg($out);
    exec($cmd, $unused, $code);
    return ($code === 0 && is_file($out)) ? $out : null;
}

/**
 * A 1200×630 card: the paper's icon, its name, its tagline. Flat colour
 * compresses well and does not pretend to be a photograph.
 */
function share_card(string $iconPng, string $name, string $tagline, string $ink, string $paper, string $accent, string $font): string
{
    $w = 1200;
    $h = 630;
    $im = imagecreatetruecolor($w, $h);
    [$ir, $ig, $ib] = pp_icon_hex_rgb($ink);
    [$pr, $pg, $pb] = pp_icon_hex_rgb($paper);
    [$ar, $ag, $ab] = pp_icon_hex_rgb($accent);
    $inkC = imagecolorallocate($im, $ir, $ig, $ib);
    $paperC = imagecolorallocate($im, $pr, $pg, $pb);
    $accentC = imagecolorallocate($im, $ar, $ag, $ab);
    imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $paperC);
    imagefilledrectangle($im, 0, $h - 28, $w - 1, $h - 1, $inkC);
    imagefilledrectangle($im, 0, $h - 36, $w - 1, $h - 32, $accentC);

    $icon = imagecreatefromstring($iconPng);
    if ($icon !== false) {
        $side = 280;
        imagecopyresampled($im, $icon, 88, (int) (($h - 36 - $side) / 2), 0, 0, $side, $side, imagesx($icon), imagesy($icon));
        imagedestroy($icon);
    }

    $textX = 420;
    $maxW = $w - $textX - 72;
    $draw = static function (int $pt, int $x, int $y, int $color, string $text) use ($im, $font): void {
        if (is_file($font)) {
            imagettftext($im, $pt, 0, $x, $y, $color, $font, $text);
        }
    };
    $widthOf = static function (int $pt, string $text) use ($font): int {
        if (!is_file($font)) {
            return strlen($text) * 16;
        }
        $box = imagettfbbox($pt, 0, $font, $text);
        return (int) abs($box[2] - $box[0]);
    };
    $wrap = static function (string $text, int $pt) use ($widthOf, $maxW): array {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $try = $line === '' ? $word : "$line $word";
            if ($widthOf($pt, $try) > $maxW && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        return $lines;
    };

    $pt = 72;
    $lines = $wrap($name, $pt);
    while ($pt > 40 && count($lines) > 3) {
        $pt -= 6;
        $lines = $wrap($name, $pt);
    }
    $lineH = (int) ($pt * 1.15);
    $block = count($lines) * $lineH;
    $tagPt = 26;
    $tagLines = $tagline !== '' ? array_slice($wrap($tagline, $tagPt), 0, 2) : [];
    $tagBlock = count($tagLines) * 36;
    $y = (int) (($h - 36) / 2 - ($block + ($tagBlock > 0 ? $tagBlock + 28 : 0)) / 2 + $pt);
    foreach ($lines as $line) {
        $draw($pt, $textX, $y, $inkC, $line);
        $y += $lineH;
    }
    if ($tagLines !== []) {
        $y += 8;
        foreach ($tagLines as $line) {
            $draw($tagPt, $textX, $y, $accentC, $line);
            $y += 36;
        }
    }
    ob_start();
    imagepng($im, null, 9);
    imagedestroy($im);
    return (string) ob_get_clean();
}

$slugs = [];
foreach (glob($root . '/assets/sites/*', GLOB_ONLYDIR) ?: [] as $dir) {
    $slug = basename($dir);
    if ($only === '' || $only === $slug) {
        $slugs[] = $slug;
    }
}
sort($slugs);
if ($slugs === []) {
    exit("No site directory matched.\n");
}

$work = sys_get_temp_dir() . '/pp-icons-' . getmypid();
mkdir($work, 0700);

foreach ($slugs as $slug) {
    [$ink, $paper, $accent] = brand_colors("$root/assets/sites/$slug/palette.json");
    [$name, $tagline] = pack_name("$root/assets/sites/$slug/launch.php", $slug);
    $letters = pp_monogram_letters($name);
    $svg = source_svg($root, $slug);
    $dir = "$root/assets/sites/$slug/icons";
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pngs = [];
    foreach (pp_icon_sizes() as $size) {
        $rendered = $svg ? raster_svg($rsvg, $svg, $size, $work) : null;
        $useMonogram = $rendered === null;
        if ($rendered !== null && is_file($svg) && str_contains((string) file_get_contents($svg), '<text') && looks_blank($rendered)) {
            $useMonogram = true;
        }
        if ($useMonogram) {
            $pngs[$size] = pp_png_monogram($size, $letters, $ink, $paper);
        } else {
            $pngs[$size] = square_png($rendered, $size, $paper, $ink);
        }
        file_put_contents("$dir/icon-$size.png", $pngs[$size]);
    }
    $ico = pp_pack_ico([16 => $pngs[16], 32 => $pngs[32], 48 => $pngs[48]]);
    file_put_contents("$dir/favicon.ico", $ico);

    $share = "$root/assets/sites/$slug/og-default.png";
    $shareNote = 'kept';
    // The flagship's card is the designed landscape in assets/img.
    if ($slug !== 'prairiedispatch' && !is_file($share)) {
        file_put_contents($share, share_card($pngs[512], $name, $tagline, $ink, $paper, $accent, $font));
        $shareNote = 'wrote og-default.png';
    }
    $how = isset($useMonogram) && $useMonogram ? 'monogram' : 'mark';
    echo "$slug  icons ($how)  $shareNote\n";
}

foreach (glob($work . '/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($work);
echo 'done ' . count($slugs) . " papers\n";
