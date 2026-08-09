<?php
/** The Prairie Post — feed aggregation, shared by cron and the sources admin. */

/** Fetch one source; returns [added_count, error|null]. Updates source status. */
function pp_fetch_source(PDO $db, array $source): array
{
    [$body, $err] = http_get($source['url']);
    if ($err !== null) {
        $db->prepare('UPDATE sources SET last_fetched_at = ?, last_status = ? WHERE id = ?')
           ->execute([now(), 'error: ' . $err, $source['id']]);
        return [0, $err];
    }

    [$items, $parseErr] = parse_feed($body);
    if ($parseErr !== null) {
        $db->prepare('UPDATE sources SET last_fetched_at = ?, last_status = ? WHERE id = ?')
           ->execute([now(), 'error: ' . $parseErr, $source['id']]);
        return [0, $parseErr];
    }

    $added = 0;
    $insert = $db->prepare('INSERT INTO news_items (source_id, region, title, url, url_hash, summary, published_at, fetched_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $exists = $db->prepare('SELECT id FROM news_items WHERE url_hash = ?');

    foreach (array_slice($items, 0, 30) as $item) {
        $hash = sha1($item['url']);
        $exists->execute([$hash]);
        if ($exists->fetch()) {
            continue;
        }
        $insert->execute([
            $source['id'], $source['region'],
            mb_substr($item['title'], 0, 500), mb_substr($item['url'], 0, 600),
            $hash, $item['summary'], $item['published_at'], now(),
        ]);
        $added++;
    }

    $db->prepare('UPDATE sources SET last_fetched_at = ?, last_status = ? WHERE id = ?')
       ->execute([now(), 'ok: ' . count($items) . ' items, ' . $added . ' new', $source['id']]);
    return [$added, null];
}

/** Fetch every enabled source. Returns a per-source report. */
function pp_fetch_all(PDO $db): array
{
    $report = [];
    $sources = $db->query('SELECT * FROM sources WHERE enabled = 1 ORDER BY region, name')->fetchAll();
    foreach ($sources as $source) {
        [$added, $err] = pp_fetch_source($db, $source);
        $report[] = ['name' => $source['name'], 'added' => $added, 'error' => $err];
    }
    return $report;
}

/** Drop unused wire items older than 14 days so the pull stays current. */
function pp_prune_news(PDO $db): int
{
    $stmt = $db->prepare('DELETE FROM news_items WHERE used = 0 AND fetched_at < ?');
    $stmt->execute([date('Y-m-d H:i:s', strtotime('-14 days'))]);
    return $stmt->rowCount();
}

/** Flip scheduled posts live once their time arrives. */
function pp_publish_due(PDO $db): int
{
    $stmt = $db->prepare("UPDATE posts SET status = 'published' WHERE status = 'scheduled' AND published_at <= ?");
    $stmt->execute([now()]);
    return $stmt->rowCount();
}
