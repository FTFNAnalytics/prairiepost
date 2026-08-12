<?php
/**
 * The monitoring desk's hourly pull (hub only). Polls the desk's own feed
 * list — kept apart from the newspaper wire, so releases never land in the
 * morning pull — then prunes what nobody touched. The external scraping
 * agent needs no cron here at all; it POSTs to /api/monitor on its own
 * schedule.
 *
 * Run from the shell:  PP_SITE=civismedia php cron/monitor.php
 * Or over HTTP:        /cron/monitor.php?key=CRON_SECRET
 */
require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    $key = (string) ($_GET['key'] ?? '');
    $secret = setting('cron_secret');
    if ($secret === '' || !hash_equals($secret, $key)) {
        http_response_code(403);
        exit("Missing or wrong key. The cron URL, secret included, is shown in Settings.\n");
    }
    header('Content-Type: text/plain; charset=utf-8');
}
if (!pp_is_hub()) {
    exit("The monitoring desk lives on the hub — run with PP_SITE=civismedia (or on the hub host).\n");
}

$db = db();
$started = microtime(true);
$added = 0;

foreach ($db->query('SELECT * FROM monitor_feeds WHERE enabled = 1 ORDER BY id') as $feed) {
    [$new, $err] = pp_monitor_poll_feed($feed);
    echo ($err ? 'ERROR ' . $feed['name'] . ': ' . $err : 'ok    ' . $feed['name'] . ': ' . $new . ' new') . "\n";
    $added += $new;
}

// Retention: items nobody flagged, claimed or used prune after the window;
// the touched ones keep — they're the paper trail behind published stories.
$days = max(30, (int) (setting('monitor_retention_days') ?: 180));
$cutoff = date('Y-m-d H:i:s', strtotime("-$days days"));
$stmt = $db->prepare("DELETE FROM monitor_items WHERE status IN ('new', 'dismissed') AND fetched_at < ?");
$stmt->execute([$cutoff]);
$pruned = $stmt->rowCount();

printf("----\n%d new item(s), %d pruned (untouched after %d days). %.1fs.\n",
    $added, $pruned, $days, microtime(true) - $started);
