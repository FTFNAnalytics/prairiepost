<?php
/**
 * The Prairie Post — daily automation.
 * Fetches every enabled wire feed, de-duplicates by URL, prunes unused items
 * older than 14 days, and publishes scheduled stories whose time has come.
 *
 * Run from the shell:  php cron/fetch-news.php
 * Or over HTTP:        /cron/fetch-news.php?key=CRON_SECRET   (secret in Settings)
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/fetch.php';

if (PHP_SAPI !== 'cli') {
    $key = (string) ($_GET['key'] ?? '');
    $secret = setting('cron_secret');
    if ($secret === '' || !hash_equals($secret, $key)) {
        http_response_code(403);
        exit("Missing or wrong key. The cron URL, secret included, is shown in Settings.\n");
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$db = db();
$started = microtime(true);

$report = pp_fetch_all($db);
$added = 0;
foreach ($report as $r) {
    $line = $r['error'] ? 'ERROR ' . $r['name'] . ': ' . $r['error'] : 'ok    ' . $r['name'] . ': ' . $r['added'] . ' new';
    echo $line . "\n";
    $added += $r['added'];
}

$pruned = pp_prune_news($db);
$publishedNow = pp_publish_due($db);

printf(
    "----\n%d new item(s), %d stale item(s) pruned, %d scheduled story(ies) published. %.1fs.\n",
    $added,
    $pruned,
    $publishedNow,
    microtime(true) - $started
);
