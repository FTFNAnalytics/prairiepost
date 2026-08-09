<?php
/** The Prairie Dispatch — ad click-through: count, then redirect to the advertiser. */
require __DIR__ . '/app/bootstrap.php';

$ad = ad_by_id((int) ($_GET['id'] ?? 0));
$target = $ad['link_url'] ?? '';

if (!$ad || !preg_match('#^https?://#i', $target)) {
    redirect('/');
}

db()->prepare('UPDATE ads SET clicks = clicks + 1 WHERE id = ?')->execute([$ad['id']]);
header('Location: ' . $target, true, 302);
