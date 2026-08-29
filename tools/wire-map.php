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
echo "\n== DEAD BUCKETS (subscribed by a paper, fed by nothing)\n";
$fed = [];
foreach ($pdo->query('SELECT DISTINCT region FROM sources WHERE enabled = 1') as $r) { $fed[$r['region']] = true; }
$dead = [];
foreach ($pdo->query('SELECT id, slug FROM sites ORDER BY id') as $site) {
    $g = $pdo->prepare('SELECT svalue FROM settings WHERE site_id = ? AND skey = ?');
    $g->execute([(int) $site['id'], 'regions']);
    foreach (array_keys(json_decode((string) ($g->fetchColumn() ?: ''), true) ?: []) as $k) {
        if (!isset($fed[$k])) { $dead[$k][] = $site['slug']; }
    }
}
if (!$dead) {
    echo "  none — every subscribed bucket has at least one enabled source\n";
}
foreach ($dead as $bucket => $slugs) {
    printf("  %-18s no enabled source; read by %s\n", $bucket, implode(', ', $slugs));
}

echo "\n== INGEST TOKENS (scope only; no secret is stored or printed)\n";
foreach ($pdo->query('SELECT name, sites, desks, enabled, last_used_at FROM ingest_agents ORDER BY id') as $a) {
    printf("  %-20s %s  sites=%s  desks=%s  last used %s\n", $a['name'],
        $a['enabled'] ? 'enabled ' : 'REVOKED ', $a['sites'], $a['desks'] !== '' ? $a['desks'] : '(any)',
        $a['last_used_at'] ?: 'never');
}

echo "\n== DESKS (network-wide)\n";
foreach ($pdo->query('SELECT slug, name FROM categories ORDER BY slug') as $c) { echo "  {$c['slug']}  ({$c['name']})\n"; }
