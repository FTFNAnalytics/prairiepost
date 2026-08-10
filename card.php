<?php
/**
 * The Prairie Dispatch — social card generator.
 * /card/{slug}.png renders a 1200×630 Open Graph card for a story: mono
 * kicker, the horizon rule, the headline in condensed caps-height type, and
 * the five-band prairie ground. Cards are cached on disk and re-rendered
 * when the story changes.
 */
require __DIR__ . '/app/bootstrap.php';

const CARD_W = 1200;
const CARD_H = 630;

$fontHead = PP_ROOT . '/assets/fonts/archivo-narrow-700.ttf';
$fontMono = PP_ROOT . '/assets/fonts/plex-mono-600.ttf';

$slug = (string) ($_GET['slug'] ?? '');
$post = $slug !== '' ? post_by_slug($slug) : null;

if (!$post || !is_file($fontHead) || !is_file($fontMono)) {
    // No story (or no fonts on this host): serve the static default card.
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=3600');
    readfile(site_asset_path('og-default.png'));
    exit;
}

/* --- Cache ---------------------------------------------------------------- */
$pal = pp_brand_palette();
$stamp = sha1($post['title'] . '|' . ($post['category_name'] ?? '') . '|' . $post['updated_at']
    . '|' . setting('site_title') . '|' . json_encode($pal) . '|v2');
$cacheDir = PP_ROOT . '/data/cards';
$cacheFile = $cacheDir . '/' . $post['slug'] . '-' . substr($stamp, 0, 10) . '.png';

if (!is_file($cacheFile)) {
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0775, true);
    }
    foreach (glob($cacheDir . '/' . $post['slug'] . '-*.png') ?: [] as $stale) {
        unlink($stale); // one live card per story
    }

    $im = imagecreatetruecolor(CARD_W, CARD_H);
    $col = fn (string $hex) => imagecolorallocate(
        $im,
        (int) hexdec(substr($hex, 1, 2)),
        (int) hexdec(substr($hex, 3, 2)),
        (int) hexdec(substr($hex, 5, 2))
    );
    $cloudbank   = $col($pal['paper']);
    $shelterbelt = $col($pal['ink']);
    $board       = $col($pal['board']);
    $noonsky     = $col($pal['sky']);
    $quarter     = $col($pal['hill']);
    $field       = $col($pal['field']);
    $stubble     = $col($pal['stubble']);

    imagefilledrectangle($im, 0, 0, CARD_W, CARD_H, $cloudbank);

    $pad = 72;

    /** Mono type with tracking — GD has no letterspacing of its own. */
    $track = function ($size, $x, $y, $color, $text, $spacing) use ($im, $fontMono) {
        $cx = $x;
        foreach (mb_str_split(mb_strtoupper($text)) as $ch) {
            imagettftext($im, $size, 0, (int) $cx, (int) $y, $color, $fontMono, $ch);
            $box = imagettfbbox($size, 0, $fontMono, $ch);
            $cx += ($box[2] - $box[0]) + $spacing;
        }
        return $cx;
    };

    // Kicker: desk (in its colour when it may carry text) · paper name.
    $kickerY = $pad + 20;
    $deskName = (string) ($post['category_name'] ?? '');
    $deskHex = (!empty($post['category_color']) && empty($post['category_color_is_fill']))
        ? $post['category_color'] : $pal['ink'];
    $x = $pad;
    if ($deskName !== '') {
        $x = $track(19, $x, $kickerY, $col($deskHex), $deskName, 4.5);
        $x = $track(19, $x + 10, $kickerY, $board, '·', 4.5) + 10;
    }
    $track(19, $x, $kickerY, $shelterbelt, setting('site_title', 'The Prairie Dispatch'), 4.5);

    // The horizon rule: ink, two pixels of paper, hairline.
    $ruleY = $kickerY + 26;
    imagefilledrectangle($im, $pad, $ruleY, CARD_W - $pad, $ruleY + 7, $shelterbelt);
    imagefilledrectangle($im, $pad, $ruleY + 11, CARD_W - $pad, $ruleY + 12, $board);

    // Headline: wrapped, up to four lines, sized down for long titles.
    $title = $post['title'];
    $maxWidth = CARD_W - 2 * $pad;
    $size = mb_strlen($title) > 90 ? 52 : 60;
    $lineHeight = (int) ($size * 1.42);
    $lines = [];
    $line = '';
    foreach (preg_split('/\s+/', trim($title)) as $word) {
        $try = $line === '' ? $word : "$line $word";
        $box = imagettfbbox($size, 0, $fontHead, $try);
        if (($box[2] - $box[0]) > $maxWidth && $line !== '') {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $try;
        }
    }
    if ($line !== '') {
        $lines[] = $line;
    }
    if (count($lines) > 4) {
        $lines = array_slice($lines, 0, 4);
        $lines[3] .= '…';
    }
    $y = $ruleY + 46 + $size;
    foreach ($lines as $l) {
        imagettftext($im, $size, 0, $pad, (int) $y, $shelterbelt, $fontHead, $l);
        $y += $lineHeight;
    }

    // The five-band ground: sky, distant hill, the horizon, field, stubble.
    $bandTop = CARD_H - 168;
    imagefilledrectangle($im, 0, $bandTop, CARD_W, $bandTop + 74, $noonsky);
    imagefilledrectangle($im, 0, $bandTop + 74, CARD_W, $bandTop + 92, $quarter);
    imagefilledrectangle($im, 0, $bandTop + 92, CARD_W, $bandTop + 102, $shelterbelt);
    imagefilledrectangle($im, 0, $bandTop + 102, CARD_W, $bandTop + 142, $field);
    imagefilledrectangle($im, 0, $bandTop + 142, CARD_W, CARD_H, $stubble);

    // Tagline riding just above the sky band.
    $track(15, $pad, $bandTop - 18, $col('#5A6A5C'), setting('tagline', 'News to the horizon'), 4);

    imagepng($im, $cacheFile);
    imagedestroy($im);
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($cacheFile));
readfile($cacheFile);
