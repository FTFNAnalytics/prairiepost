<?php
/**
 * The Prairie Dispatch — the ad counter, both directions.
 * ?imp=1-2-3  the footer beacon reporting which ads a rendered page carried
 *             (impressions are counted here, not at render, so views served
 *             from the nginx microcache still count)
 * ?v=N        the footer beacon counting a published article read on this site
 *             (keeps GET /story/<slug> 100% read-only for nginx microcaching)
 * ?id=N       a click-through: count, then redirect to the advertiser
 */
require __DIR__ . '/app/bootstrap.php';

if (isset($_GET['imp'])) {
    pp_ads_count_impressions(array_map('intval', explode('-', (string) $_GET['imp'])));
    header('Cache-Control: no-store, private');
    http_response_code(204);
    exit;
}

if (isset($_GET['v'])) {
    $postId = (int) $_GET['v'];
    if ($postId > 0 && !preg_match('/bot|crawl|spider|slurp|preview/i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
        db()->prepare("UPDATE posts SET views = views + 1 WHERE id = ? AND status = 'published' "
            . 'AND EXISTS (SELECT 1 FROM post_sites ps WHERE ps.post_id = posts.id AND ps.site_id = ?)')
            ->execute([$postId, current_site_id()]);
    }
    header('Cache-Control: no-store, private');
    http_response_code(204);
    exit;
}

$ad = ad_by_id((int) ($_GET['id'] ?? 0));
$target = $ad['link_url'] ?? '';

if (!$ad || !preg_match('#^https?://#i', $target)) {
    redirect('/');
}

db()->prepare('UPDATE ads SET clicks = clicks + 1 WHERE id = ?')->execute([$ad['id']]);
header('Location: ' . $target, true, 302);
