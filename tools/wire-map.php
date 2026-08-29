<?php
/**
 * The wire map — everything a filing agent needs to know before it
 * proposes a story, and nothing it shouldn't.
 *
 *   php tools/wire-map.php
 *
 * Read-only: which sources feed which region bucket and whether each is
 * still answering, which regions each paper subscribes to, how deep the
 * pool is per bucket, what each ingest token is scoped to, and the
 * network's desks. There is no read API for news_items — the endpoint
 * only writes — so this is how the pool becomes visible at all.
 *
 * Token SECRETS are neither stored nor printed anywhere: the database
 * holds only their sha256, and this reads name, scope and last-used.
 */
require dirname(__DIR__) . "/app/bootstrap.php";
$pdo = db();

echo "== SOURCES (" . (int) $pdo->query('SELECT COUNT(*) FROM sources')->fetchColumn() . " total)\n";
$q = $pdo->query('SELECT region, name, url, enabled, last_status, last_fetched_at FROM sources ORDER BY region, name');
$region = null;
foreach ($q as $s) {
    if ($s['region'] !== $region) { $region = $s['region']; echo "\n[$region]\n"; }
    printf("  %-42s %s%s\n", mb_strimwidth($s['name'], 0, 42, '…'),
        $s['enabled'] ? '' : 'DISABLED ',
        trim((string) $s['last_status']) !== '' ? 'last: ' . mb_strimwidth((string) $s['last_status'], 0, 60, '…') : 'ok');
}

echo "\n== PAPERS AND THE REGIONS THEY READ\n";
foreach ($pdo->query('SELECT id, name, slug FROM sites ORDER BY id') as $site) {
    $st = $pdo->prepare('SELECT svalue FROM settings WHERE site_id = ? AND skey = ?');
    $st->execute([(int) $site['id'], 'regions']);
    $raw = (string) ($st->fetchColumn() ?: '');
    $keys = array_keys(json_decode($raw, true) ?: []);
    $st->execute([(int) $site['id'], 'automated_byline']);
    $byline = (string) ($st->fetchColumn() ?: '');
    printf("  %-24s regions: %-52s byline: %s\n", $site['slug'],
        $keys ? implode(',', $keys) : '(none)', $byline !== '' ? $byline : 'NOT SET');
}

echo "\n== POOL DEPTH BY REGION (news_items)\n";
$week = date('Y-m-d H:i:s', time() - 7 * 86400);
$st = $pdo->prepare('SELECT COUNT(*) FROM news_items WHERE region = ? AND fetched_at > ?');
$pool = [];
foreach ($pdo->query('SELECT region, COUNT(*) n, MAX(fetched_at) last FROM news_items GROUP BY region ORDER BY region') as $r) {
    $st->execute([$r['region'], $week]);
    $pool[$r['region']] = true;
    printf("  %-18s %6d total  %5d in 7d  last fetched %s\n",
        $r['region'], (int) $r['n'], (int) $st->fetchColumn(), $r['last']);
}

// A paper can subscribe to a bucket nothing feeds. That reads as "quiet
// wire" and is really "no sources", so name it rather than leave it to
// be inferred from an empty discovery run.
echo "\n== BUCKETS THAT DELIVER NOTHING (subscribed by a paper)\n";
// Two ways a paper reads an empty wire, and they need different fixes.
// DEAD: no enabled source at all — someone has to add a feed.
// SILENT: sources exist but nothing arrived in seven days — the feeds
// are broken, and a bucket whose only source 404s is exactly as empty
// as one with no source. Judging this on delivered items rather than on
// last_status is deliberate: statuses are free text ("error: HTTP 404",
// but also "not a valid RSS or Atom feed"), and what a paper actually
// gets is not a matter of interpretation.
$fed = [];
foreach ($pdo->query('SELECT DISTINCT region FROM sources WHERE enabled = 1') as $r) { $fed[$r['region']] = true; }
$delivered = [];
$dq = $pdo->prepare('SELECT COUNT(*) FROM news_items WHERE region = ? AND fetched_at > ?');
foreach (array_keys($fed) as $bucket) {
    $dq->execute([$bucket, $week]);
    $delivered[$bucket] = (int) $dq->fetchColumn();
}
$readers = [];
foreach ($pdo->query('SELECT id, slug FROM sites ORDER BY id') as $site) {
    $g = $pdo->prepare('SELECT svalue FROM settings WHERE site_id = ? AND skey = ?');
    $g->execute([(int) $site['id'], 'regions']);
    foreach (array_keys(json_decode((string) ($g->fetchColumn() ?: ''), true) ?: []) as $k) {
        $readers[$k][] = $site['slug'];
    }
}
$why = $pdo->prepare('SELECT name, last_status FROM sources WHERE region = ? AND enabled = 1 ORDER BY name');
// If NOTHING has been fetched network-wide, every bucket is silent and the
// per-bucket listing is noise hiding one fact: the fetch cron is not running.
$dq->execute(['', $week]);
$anywhere = (int) $pdo->query('SELECT COUNT(*) FROM news_items')->fetchColumn();
if ($anywhere === 0) {
    echo "  the pool is empty network-wide — no news_items at all.\n";
    echo "  That is the fetch cron, not the buckets. Check /etc/cron.d for the\n";
    echo "  fetch-news jobs before reading anything else here.\n\n";
}
$found = false;
foreach ($readers as $bucket => $slugs) {
    if (!isset($fed[$bucket])) {
        $found = true;
        printf("  DEAD   %-16s no enabled source; read by %s\n", $bucket, implode(', ', $slugs));
    } elseif ($delivered[$bucket] === 0) {
        $found = true;
        printf("  SILENT %-16s sources exist, nothing in 7 days; read by %s\n", $bucket, implode(', ', $slugs));
        $why->execute([$bucket]);
        foreach ($why->fetchAll() as $srow) {
            printf("         %-30s %s\n", mb_strimwidth((string) $srow['name'], 0, 30, '…'),
                trim((string) $srow['last_status']) !== '' ? $srow['last_status'] : '(never fetched)');
        }
    }
}
if (!$found) {
    echo "  none — every subscribed bucket has a source and delivered inside 7 days\n";
}

echo "\n== INGEST TOKENS (scope only; no secret is stored or printed)\n";
foreach ($pdo->query('SELECT name, sites, desks, enabled, last_used_at FROM ingest_agents ORDER BY id') as $a) {
    printf("  %-20s %s  sites=%s  desks=%s  last used %s\n", $a['name'],
        $a['enabled'] ? 'enabled ' : 'REVOKED ', $a['sites'], $a['desks'] !== '' ? $a['desks'] : '(any)',
        $a['last_used_at'] ?: 'never');
}

echo "\n== DESKS (network-wide)\n";
foreach ($pdo->query('SELECT slug, name FROM categories ORDER BY slug') as $c) { echo "  {$c['slug']}  ({$c['name']})\n"; }
