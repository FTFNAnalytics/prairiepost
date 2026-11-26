<?php
/**
 * Per-paper icons and share metadata.
 *
 * Every public page used to link one SVG favicon and nothing else, and
 * site_asset() answered a missing mark or share card with the flagship's
 * files. Google's favicon crawler requests /favicon.ico (an SVG link is
 * not enough), and a story share then carried the Prairie Dispatch's
 * mark or its landscape. The files under assets/sites/<slug>/icons/ are
 * that paper's own, rasterized from its favicon.svg. /favicon.ico and
 * the PNG sizes are routes, not one file at the release root, because
 * every domain is served from the same tree.
 *
 * A paper with no mark of its own gets a monogram in its ink on its
 * paper colour, drawn here. The flagship is the one site whose brand
 * lives in assets/img/; only its slug may use those files.
 */

/** Sizes written for every paper. 48 is the one Google's crawler requires. */
function pp_icon_sizes(): array
{
    return [16, 32, 48, 180, 192, 512];
}

/**
 * A brand file this paper actually owns, as a root-relative URL.
 * Null when the paper has none — never the network file, except for the
 * flagship, whose brand is that file.
 */
function pp_owned_asset(string $file): ?string
{
    $file = str_replace('\\', '/', $file);
    if ($file === '' || str_contains($file, '..') || str_contains($file, "\0") || str_starts_with($file, '/')) {
        return null;
    }
    $slug = (string) (current_site()['slug'] ?? '');
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        return null;
    }
    $rel = '/assets/sites/' . $slug . '/' . $file;
    if (is_file(PP_ROOT . $rel)) {
        return $rel;
    }
    if ($slug === 'prairiedispatch' && is_file(PP_ROOT . '/assets/img/' . $file)) {
        return '/assets/img/' . $file;
    }
    return null;
}

/** Up to two initials. A leading "The" / "Le" is not a letter in the mark. */
function pp_monogram_letters(string $name): string
{
    $name = trim((string) preg_replace('/\s+/u', ' ', $name));
    $name = (string) preg_replace('/^(the|le|la|les)\s+/iu', '', $name);
    $letters = '';
    foreach (preg_split('/\s+/u', $name) ?: [] as $word) {
        if ($word === '') {
            continue;
        }
        $letters .= mb_strtoupper(mb_substr($word, 0, 1));
        if (mb_strlen($letters) >= 2) {
            break;
        }
    }
    return $letters !== '' ? $letters : 'N';
}

function pp_icon_hex_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return [23, 48, 28];
    }
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/** The paper's ink, then its accent — a real hex, else the network ink. */
function pp_theme_color(): string
{
    $palette = pp_brand_file()['palette'] ?? [];
    foreach (['ink', 'accent', 'hill'] as $key) {
        $value = $palette[$key] ?? null;
        if (is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            return $value;
        }
    }
    $ink = pp_brand_palette()['ink'] ?? '#17301C';
    return is_string($ink) && preg_match('/^#[0-9A-Fa-f]{6}$/', $ink) ? $ink : '#17301C';
}

/** The paper's page colour, for icon mats and the manifest. */
function pp_paper_color(): string
{
    $palette = pp_brand_file()['palette'] ?? [];
    foreach (['paper', 'surface'] as $key) {
        $value = $palette[$key] ?? null;
        if (is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            return $value;
        }
    }
    $paper = pp_brand_palette()['paper'] ?? '#F7F6F3';
    return is_string($paper) && preg_match('/^#[0-9A-Fa-f]{6}$/', $paper) ? $paper : '#F7F6F3';
}

/**
 * A square monogram PNG. Letters are the paper colour on its ink, which
 * is the tile a browser shows at 16px when the paper has no mark.
 */
function pp_png_monogram(int $size, string $letters, string $background, string $foreground): string
{
    $im = imagecreatetruecolor($size, $size);
    [$br, $bg, $bb] = pp_icon_hex_rgb($background);
    [$fr, $fg, $fb] = pp_icon_hex_rgb($foreground);
    $bgCol = imagecolorallocate($im, $br, $bg, $bb);
    $fgCol = imagecolorallocate($im, $fr, $fg, $fb);
    imagefilledrectangle($im, 0, 0, $size - 1, $size - 1, $bgCol);
    $font = PP_ROOT . '/assets/fonts/archivo-narrow-700.ttf';
    if (is_file($font)) {
        $pt = $size * (mb_strlen($letters) > 1 ? 0.40 : 0.56);
        $box = imagettfbbox($pt, 0, $font, $letters);
        $xs = [$box[0], $box[2], $box[4], $box[6]];
        $ys = [$box[1], $box[3], $box[5], $box[7]];
        $x = ($size - (max($xs) - min($xs))) / 2 - min($xs);
        $y = ($size - (max($ys) - min($ys))) / 2 - min($ys);
        imagettftext($im, $pt, 0, (int) round($x), (int) round($y), $fgCol, $font, $letters);
    } else {
        $built = imagecreatetruecolor(imagefontwidth(5) * strlen($letters), imagefontheight(5));
        imagefilledrectangle($built, 0, 0, imagesx($built) - 1, imagesy($built) - 1, $bgCol);
        imagestring($built, 5, 0, 0, $letters, $fgCol);
        $target = (int) ($size * 0.62);
        $dx = (int) (($size - $target) / 2);
        $dy = (int) (($size - $target * imagesy($built) / imagesx($built)) / 2);
        imagecopyresampled($im, $built, $dx, $dy, 0, 0, $target, (int) ($target * imagesy($built) / max(1, imagesx($built))), imagesx($built), imagesy($built));
        imagedestroy($built);
    }
    ob_start();
    imagepng($im, null, 9);
    imagedestroy($im);
    return (string) ob_get_clean();
}

/**
 * ICO whose images are PNG payloads (Vista and every current browser).
 * $pngs is size => png bytes. A size of 256 is stored as 0, per the format.
 */
function pp_pack_ico(array $pngs): string
{
    ksort($pngs);
    $count = count($pngs);
    $header = pack('vvv', 0, 1, $count);
    $offset = 6 + 16 * $count;
    $entries = '';
    $blobs = '';
    foreach ($pngs as $size => $bytes) {
        $dim = (int) $size;
        $wh = $dim >= 256 ? 0 : $dim;
        $entries .= pack('CCCCvvVV', $wh, $wh, 0, 0, 1, 32, strlen($bytes), $offset);
        $offset += strlen($bytes);
        $blobs .= $bytes;
    }
    return $header . $entries . $blobs;
}

/** Where a committed or cached icon file lives for this slug. */
function pp_icon_disk_path(string $slug, string $name): ?string
{
    if (!preg_match('/^[a-z0-9-]+$/', $slug) || !preg_match('/^(favicon\.ico|icon-(16|32|48|180|192|512)\.png)$/', $name)) {
        return null;
    }
    foreach ([
        PP_ROOT . '/assets/sites/' . $slug . '/icons/' . $name,
        PP_ROOT . '/data/icons/' . $slug . '/' . $name,
    ] as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

/**
 * Draw and cache a monogram set when a paper has no committed icons.
 * The cache is under data/, which nginx never serves; the route reads it.
 */
function pp_cache_monogram(string $slug): void
{
    if (pp_icon_disk_path($slug, 'favicon.ico') !== null) {
        return;
    }
    $dir = PP_ROOT . '/data/icons/' . $slug;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return;
    }
    $letters = pp_monogram_letters(setting('site_title', 'News'));
    $pngs = [];
    foreach (pp_icon_sizes() as $size) {
        $bytes = pp_png_monogram($size, $letters, pp_theme_color(), pp_paper_color());
        $pngs[$size] = $bytes;
        @file_put_contents($dir . '/icon-' . $size . '.png', $bytes);
    }
    $ico = pp_pack_ico([16 => $pngs[16], 32 => $pngs[32], 48 => $pngs[48]]);
    @file_put_contents($dir . '/favicon.ico', $ico);
}

/**
 * [content-type, body, status] for one icon route.
 * kind is ico, a pixel size, or manifest.
 */
function pp_icon_response(string $kind): array
{
    $slug = (string) (current_site()['slug'] ?? '');
    if ($kind === 'manifest') {
        $json = json_encode(pp_web_manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return ['application/manifest+json; charset=UTF-8', $json === false ? '{}' : $json, 200];
    }
    $file = $kind === 'ico' ? 'favicon.ico' : (preg_match('/^(16|32|48|180|192|512)$/', $kind) ? 'icon-' . $kind . '.png' : '');
    if ($file === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
        return ['text/plain; charset=UTF-8', 'Not found', 404];
    }
    $path = pp_icon_disk_path($slug, $file);
    if ($path === null) {
        pp_cache_monogram($slug);
        $path = pp_icon_disk_path($slug, $file);
    }
    if ($path === null) {
        // The cache directory could not be written. Draw this one size
        // and answer with it; the next request tries the cache again.
        if ($kind === 'ico') {
            $letters = pp_monogram_letters(setting('site_title', 'News'));
            $pngs = [];
            foreach ([16, 32, 48] as $size) {
                $pngs[$size] = pp_png_monogram($size, $letters, pp_theme_color(), pp_paper_color());
            }
            return ['image/x-icon', pp_pack_ico($pngs), 200];
        }
        $size = (int) $kind;
        return ['image/png', pp_png_monogram($size, pp_monogram_letters(setting('site_title', 'News')), pp_theme_color(), pp_paper_color()), 200];
    }
    $type = $kind === 'ico' ? 'image/x-icon' : 'image/png';
    return [$type, (string) file_get_contents($path), 200];
}

/** The manifest a browser reads for this host. Names come from the paper, not the network. */
function pp_web_manifest(): array
{
    $name = setting('site_title', 'News');
    $lang = str_replace('_', '-', pp_locale());
    return [
        'name'             => $name,
        'short_name'       => $name,
        'icons'            => [
            ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ],
        'start_url'        => '/',
        'display'          => 'browser',
        'background_color' => pp_paper_color(),
        'theme_color'      => pp_theme_color(),
        'lang'             => $lang,
    ];
}

/** Icon links, the touch icon, the manifest and the theme colour. */
function pp_emit_icon_tags(): void
{
    $svg = pp_owned_asset('favicon.svg');
    echo '<link rel="icon" href="/favicon.ico" sizes="48x48">' . "\n";
    echo '<link rel="icon" type="image/png" href="/icon-32.png" sizes="32x32">' . "\n";
    echo '<link rel="icon" type="image/png" href="/icon-48.png" sizes="48x48">' . "\n";
    if ($svg !== null) {
        echo '<link rel="icon" type="image/svg+xml" href="' . e($svg) . '">' . "\n";
    }
    echo '<link rel="apple-touch-icon" href="/apple-touch-icon.png">' . "\n";
    echo '<link rel="manifest" href="/site.webmanifest">' . "\n";
    echo '<meta name="theme-color" content="' . e(pp_theme_color()) . '">' . "\n";
}

/**
 * Open Graph and Twitter tags. $t keys: site_name, title, description,
 * type, url, image, image_width, image_height, image_alt, locale, and
 * article (published_time, modified_time, section, author).
 */
function pp_emit_social_tags(array $t): void
{
    $locale = (string) ($t['locale'] ?? pp_locale());
    echo '<meta property="og:locale" content="' . e($locale) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . e((string) $t['site_name']) . '">' . "\n";
    echo '<meta property="og:title" content="' . e((string) $t['title']) . '">' . "\n";
    $description = (string) ($t['description'] ?? '');
    if ($description !== '') {
        echo '<meta property="og:description" content="' . e($description) . '">' . "\n";
    }
    echo '<meta property="og:type" content="' . e((string) ($t['type'] ?? 'website')) . '">' . "\n";
    echo '<meta property="og:url" content="' . e((string) $t['url']) . '">' . "\n";
    echo '<meta property="og:image" content="' . e((string) $t['image']) . '">' . "\n";
    if (!empty($t['image_width'])) {
        echo '<meta property="og:image:width" content="' . (int) $t['image_width'] . '">' . "\n";
    }
    if (!empty($t['image_height'])) {
        echo '<meta property="og:image:height" content="' . (int) $t['image_height'] . '">' . "\n";
    }
    $alt = (string) ($t['image_alt'] ?? '');
    if ($alt !== '') {
        echo '<meta property="og:image:alt" content="' . e($alt) . '">' . "\n";
    }
    $article = is_array($t['article'] ?? null) ? $t['article'] : [];
    foreach (['published_time', 'modified_time', 'section', 'author'] as $key) {
        $value = trim((string) ($article[$key] ?? ''));
        if ($value !== '') {
            echo '<meta property="article:' . $key . '" content="' . e($value) . '">' . "\n";
        }
    }
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . e((string) $t['title']) . '">' . "\n";
    if ($description !== '') {
        echo '<meta name="twitter:description" content="' . e($description) . '">' . "\n";
    }
    echo '<meta name="twitter:image" content="' . e((string) $t['image']) . '">' . "\n";
    if ($alt !== '') {
        echo '<meta name="twitter:image:alt" content="' . e($alt) . '">' . "\n";
    }
}

/** Absolute URL for a stored image path. Already-absolute URLs are kept. */
function pp_public_image_url(string $src): string
{
    $src = trim($src);
    if (preg_match('#^https?://#i', $src)) {
        return $src;
    }
    if (str_starts_with($src, '//')) {
        return 'https:' . $src;
    }
    if (!str_starts_with($src, '/')) {
        $src = '/' . $src;
    }
    return site_url() . $src;
}

/** JPEG, PNG, GIF or WebP. SVG is not a share image any platform fetches. */
function pp_is_raster_image(string $src): bool
{
    $path = (string) (parse_url($src, PHP_URL_PATH) ?? $src);
    return preg_match('/\.(jpe?g|png|gif|webp)$/i', $path) === 1;
}

/** Pixel size of a local public path, or null when the file is not on disk. */
function pp_local_image_size(string $src): ?array
{
    $path = (string) (parse_url($src, PHP_URL_PATH) ?? '');
    if ($path === '' || !str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, "\0")) {
        return null;
    }
    $full = PP_ROOT . $path;
    if (!is_file($full)) {
        return null;
    }
    $info = @getimagesize($full);
    if ($info === false || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
        return null;
    }
    return [(int) $info[0], (int) $info[1]];
}

/**
 * The paper's own default share image. The flagship's is assets/img;
 * every other paper's is its og-default.png. With neither, the icon
 * stands in — still this paper, never the flagship's landscape.
 *
 * @return array{url:string,width:?int,height:?int,alt:string}
 */
function pp_default_share_image(): array
{
    $rel = pp_owned_asset('og-default.png');
    $alt = setting('site_title', '');
    if ($rel === null) {
        return [
            'url'    => site_url() . '/icon-512.png',
            'width'  => 512,
            'height' => 512,
            'alt'    => $alt,
        ];
    }
    $dim = pp_local_image_size($rel);
    return [
        'url'    => site_url() . $rel,
        'width'  => $dim[0] ?? null,
        'height' => $dim[1] ?? null,
        'alt'    => $alt,
    ];
}

/**
 * What a story shares: its feature image when that is a raster file,
 * otherwise the paper's own default card.
 *
 * @return array{url:string,width:?int,height:?int,alt:string}
 */
function pp_story_share_image(array $post): array
{
    $title = trim((string) ($post['title'] ?? ''));
    $alt = trim((string) ($post['image_alt'] ?? ''));
    if ($alt === '') {
        $alt = trim((string) ($post['image_caption'] ?? ''));
    }
    if ($alt === '') {
        $alt = $title;
    }
    $image = trim((string) ($post['image'] ?? ''));
    if ($image !== '' && pp_is_raster_image($image)) {
        $dim = pp_local_image_size($image);
        return [
            'url'    => pp_public_image_url($image),
            'width'  => $dim[0] ?? null,
            'height' => $dim[1] ?? null,
            'alt'    => $alt,
        ];
    }
    $share = pp_default_share_image();
    $share['alt'] = $alt !== '' ? $alt : $share['alt'];
    return $share;
}

/**
 * The publisher logo in story data: this paper's 512px icon, which is
 * its mark rasterized. A square PNG is what a platform can fetch; the
 * SVG mark is the source, not the thing we hand to a crawler.
 *
 * @return array{url:string,width:int,height:int}
 */
function pp_publisher_logo(): array
{
    return [
        'url'    => site_url() . '/icon-512.png',
        'width'  => 512,
        'height' => 512,
    ];
}

/** ISO-8601 on the network clock, or '' when the stamp is unreadable. */
function pp_iso8601(?string $dt): string
{
    if ($dt === null || $dt === '') {
        return '';
    }
    $ts = strtotime($dt);
    return $ts ? date('c', $ts) : '';
}
