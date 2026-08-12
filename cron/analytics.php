<?php
/**
 * The nightly analytics pull (hub only). For every site with a configured
 * GA4 property or Search Console property, pulls the numbers into our own
 * tables — traffic by day with channel mix and top pages, and Search
 * Console rows by query and by page. Search data lags about two days, so
 * a trailing window is re-pulled each night; rows upsert, so re-running
 * is always safe. Both tables prune to sixteen months, Search Console's
 * own retention.
 *
 * Run from the shell:  PP_SITE=civismedia php cron/analytics.php
 * Or over HTTP:        /cron/analytics.php?key=CRON_SECRET
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
    exit("Analytics pulls run on the hub — PP_SITE=civismedia (or the hub host).\n");
}
if (!pp_google_enabled()) {
    exit("Not connected: set google_sa_json in the hub's config.php to the service-account JSON path.\n");
}

$db = db();
$started = microtime(true);

foreach ($db->query('SELECT id, slug, name FROM sites ORDER BY id') as $site) {
    $siteId = (int) $site['id'];
    $property = trim(pp_setting_for_site($siteId, 'ga4_property_id'));
    $gscSite  = trim(pp_setting_for_site($siteId, 'gsc_site_url'));
    if ($property === '' && $gscSite === '') {
        continue;
    }
    $line = $site['slug'] . ':';

    if ($property !== '') {
        [$days, $err] = pp_pull_ga4($siteId, $property);
        $line .= $err ? " GA ERROR ($err)" : " ga $days day(s)";
    }
    if ($gscSite !== '') {
        [$days, $err] = pp_pull_gsc($siteId, $gscSite);
        $line .= $err ? " GSC ERROR ($err)" : " gsc $days day(s)";
    }
    echo ($line === $site['slug'] . ':' ? $line . ' nothing configured' : $line) . "\n";
}

// Sixteen months — Search Console's own retention; keeping more is theatre.
$cutoff = date('Y-m-d', strtotime('-16 months'));
foreach (['site_metrics_daily', 'gsc_daily'] as $t) {
    $stmt = $db->prepare("DELETE FROM $t WHERE day < ?");
    $stmt->execute([$cutoff]);
    if ($stmt->rowCount()) {
        echo 'pruned ' . $stmt->rowCount() . " old row(s) from $t\n";
    }
}
printf("----\ndone in %.1fs.\n", microtime(true) - $started);

/** First pull for a site backfills a month; nights after re-pull three days. */
function pp_ga_window(int $siteId): array
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM site_metrics_daily WHERE site_id = ?');
    $stmt->execute([$siteId]);
    $days = $stmt->fetchColumn() ? 3 : 30;
    return [date('Y-m-d', strtotime("-$days days")), date('Y-m-d', strtotime('-1 day'))];
}

function pp_pull_ga4(int $siteId, string $property): array
{
    [$start, $end] = pp_ga_window($siteId);
    $range = ['dateRanges' => [['startDate' => $start, 'endDate' => $end]]];

    [$daily, $err] = pp_ga4_run_report($property, $range + [
        'dimensions' => [['name' => 'date']],
        'metrics' => [['name' => 'sessions'], ['name' => 'totalUsers'], ['name' => 'screenPageViews'],
                      ['name' => 'engagedSessions'], ['name' => 'userEngagementDuration']],
    ]);
    if ($daily === null) {
        return [0, $err];
    }
    [$channels] = pp_ga4_run_report($property, $range + [
        'dimensions' => [['name' => 'date'], ['name' => 'sessionDefaultChannelGroup']],
        'metrics' => [['name' => 'sessions']],
        'limit' => 5000,
    ]);
    [$pages] = pp_ga4_run_report($property, $range + [
        'dimensions' => [['name' => 'date'], ['name' => 'pagePath']],
        'metrics' => [['name' => 'screenPageViews']],
        'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
        'limit' => 5000,
    ]);

    $mixByDay = [];
    foreach (($channels['rows'] ?? []) as $row) {
        $day = pp_ga_day($row['dimensionValues'][0]['value'] ?? '');
        $mixByDay[$day][(string) ($row['dimensionValues'][1]['value'] ?? '?')] = (int) ($row['metricValues'][0]['value'] ?? 0);
    }
    $pagesByDay = [];
    foreach (($pages['rows'] ?? []) as $row) {
        $day = pp_ga_day($row['dimensionValues'][0]['value'] ?? '');
        if (count($pagesByDay[$day] ?? []) < 10) {
            $pagesByDay[$day][(string) ($row['dimensionValues'][1]['value'] ?? '')] = (int) ($row['metricValues'][0]['value'] ?? 0);
        }
    }

    $n = 0;
    foreach (($daily['rows'] ?? []) as $row) {
        $day = pp_ga_day($row['dimensionValues'][0]['value'] ?? '');
        if ($day === '') {
            continue;
        }
        $m = array_map(fn ($v) => (int) round((float) ($v['value'] ?? 0)), $row['metricValues'] ?? []);
        pp_upsert_metrics($siteId, $day, [
            'sessions' => $m[0] ?? 0, 'users' => $m[1] ?? 0, 'pageviews' => $m[2] ?? 0,
            'engaged_sessions' => $m[3] ?? 0, 'engagement_secs' => $m[4] ?? 0,
            'channels_json' => json_encode($mixByDay[$day] ?? []),
            'top_pages_json' => json_encode($pagesByDay[$day] ?? []),
        ]);
        $n++;
    }
    return [$n, null];
}

/** GA4 dates arrive as YYYYMMDD. */
function pp_ga_day(string $raw): string
{
    return preg_match('/^(\d{4})(\d{2})(\d{2})$/', $raw, $m) ? "$m[1]-$m[2]-$m[3]" : $raw;
}

function pp_upsert_metrics(int $siteId, string $day, array $vals): void
{
    $db = db();
    $stmt = $db->prepare('SELECT id FROM site_metrics_daily WHERE site_id = ? AND day = ?');
    $stmt->execute([$siteId, $day]);
    if ($id = $stmt->fetchColumn()) {
        $set = implode(', ', array_map(fn ($k) => "$k = ?", array_keys($vals)));
        $db->prepare("UPDATE site_metrics_daily SET $set WHERE id = ?")
           ->execute([...array_values($vals), $id]);
    } else {
        $cols = 'site_id, day, ' . implode(', ', array_keys($vals));
        $marks = implode(', ', array_fill(0, count($vals) + 2, '?'));
        $db->prepare("INSERT INTO site_metrics_daily ($cols) VALUES ($marks)")
           ->execute([$siteId, $day, ...array_values($vals)]);
    }
}

function pp_pull_gsc(int $siteId, string $gscSite): array
{
    // Search data lags ~2 days; re-pull a trailing window (a month on the
    // first pull), upserting over what's there.
    $stmt = db()->prepare('SELECT COUNT(*) FROM gsc_daily WHERE site_id = ?');
    $stmt->execute([$siteId]);
    $span = $stmt->fetchColumn() ? 5 : 30;
    $end = date('Y-m-d', strtotime('-2 days'));
    $start = date('Y-m-d', strtotime("$end -" . ($span - 1) . ' days'));

    $daysSeen = [];
    foreach (['query', 'page'] as $dim) {
        [$data, $err] = pp_gsc_query($gscSite, [
            'startDate' => $start, 'endDate' => $end,
            'dimensions' => ['date', $dim],
            'rowLimit' => 5000,
        ]);
        if ($data === null) {
            return [0, $err];
        }
        $perDay = [];
        foreach (($data['rows'] ?? []) as $row) {
            $day = (string) ($row['keys'][0] ?? '');
            $key = mb_substr((string) ($row['keys'][1] ?? ''), 0, 255);
            if ($day === '' || $key === '' || ($perDay[$day] ?? 0) >= 250) {
                continue;
            }
            $perDay[$day] = ($perDay[$day] ?? 0) + 1;
            $daysSeen[$day] = true;
            pp_upsert_gsc($siteId, $day, $dim, $key,
                (int) ($row['clicks'] ?? 0), (int) ($row['impressions'] ?? 0), (float) ($row['position'] ?? 0));
        }
    }
    return [count($daysSeen), null];
}

function pp_upsert_gsc(int $siteId, string $day, string $dim, string $key, int $clicks, int $impressions, float $position): void
{
    $db = db();
    $stmt = $db->prepare('SELECT id FROM gsc_daily WHERE site_id = ? AND day = ? AND dim = ? AND dkey = ?');
    $stmt->execute([$siteId, $day, $dim, $key]);
    if ($id = $stmt->fetchColumn()) {
        $db->prepare('UPDATE gsc_daily SET clicks = ?, impressions = ?, position = ? WHERE id = ?')
           ->execute([$clicks, $impressions, $position, $id]);
    } else {
        $db->prepare('INSERT INTO gsc_daily (site_id, day, dim, dkey, clicks, impressions, position) VALUES (?, ?, ?, ?, ?, ?, ?)')
           ->execute([$siteId, $day, $dim, $key, $clicks, $impressions, $position]);
    }
}
