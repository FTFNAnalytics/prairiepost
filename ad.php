<?php
/**
 * The Prairie Dispatch — the ad counter, both directions.
 * ?imp=1-2-3  the footer beacon reporting which ads a rendered page carried
 *             (impressions are counted here, not at render, so views served
 *             from the nginx microcache still count)
 * ?id=N       a click-through: count, then redirect to the advertiser
 */
require __DIR__ . '/app/bootstrap.php';

if (isset($_GET['imp'])) {
    pp_ads_count_impressions(array_map('intval', explode('-', (string) $_GET['imp'])));
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
