<?php
/**
 * Analytics & Search Console — the network's numbers in one place, pulled
 * nightly into our own tables, and the story-gap report: heuristics over
 * search data, the wire, and the monitoring desk that point at stories
 * worth writing. Suggestions land beside the numbers — never auto-filed.
 *
 * Hub only, editors and up. Configuration (property ids) lives in each
 * site's Settings; the service-account JSON lives in config.php.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_editor();
if (!pp_is_hub()) {
    http_response_code(404);
    exit('Not found.');
}

$sites = db()->query('SELECT id, slug, name FROM sites ORDER BY name')->fetchAll();
$bySlug = [];
foreach ($sites as $s) {
    $bySlug[$s['slug']] = $s;
}
$view = isset($bySlug[(string) ($_GET['site'] ?? '')]) ? $bySlug[(string) $_GET['site']] : null;

$d = fn (int $daysAgo) => date('Y-m-d', strtotime("-$daysAgo days"));
$since28 = $d(28);
$since7  = $d(7);
$prior7  = $d(14);

/* --- AI pitches from a site's gaps → the ideas docket --------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pitches' && $view) {
    csrf_check();
    set_time_limit(300);
    $siteId = (int) $view['id'];
    $gapText = (string) ($_POST['gaps'] ?? '');
    if (trim($gapText) === '' || !pp_ai_enabled()) {
        flash_set(pp_ai_enabled() ? 'No gap data to pitch from yet.' : "The research desk isn't connected — the numbers work without it, pitches don't.", true);
        redirect('analytics.php?site=' . urlencode($view['slug']));
    }
    $regions = array_keys((array) json_decode(pp_setting_for_site($siteId, 'regions', '{}'), true));
    $system = "You are the story-ideas desk of a Canadian community-news network. From one paper's search-gap report, propose three to five story pitches that would close the gaps. Use ONLY the provided data — never invent facts; a pitch names what to report, not what the story will find. Each pitch: working title, the angle in one or two sentences, and why now (rationale, tied to the numbers). region comes from the provided keys; site_slug is always \"{$view['slug']}\".";
    $userMsg = "PAPER\n{$view['name']} ({$view['slug']})\nRegion keys: " . implode(', ', $regions ?: ['(none)'])
             . "\n\nGAP REPORT\n$gapText";
    $res = pp_ai_message($system, [['role' => 'user', 'content' => $userMsg]],
                         ['schema' => pp_ai_ideas_schema(), 'max_tokens' => 8000]);
    if (!$res['ok']) {
        flash_set($res['error'], true);
        redirect('analytics.php?site=' . urlencode($view['slug']));
    }
    $decoded = json_decode($res['text'], true);
    $n = 0;
    foreach (array_slice((array) ($decoded['ideas'] ?? []), 0, 5) as $idea) {
        $title = trim((string) ($idea['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        db()->prepare('INSERT INTO story_ideas (site_id, title, angle, rationale, region, origin, status, created_by, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$siteId, mb_substr($title, 0, 255), trim((string) ($idea['angle'] ?? '')),
                       trim((string) ($idea['rationale'] ?? '')),
                       mb_substr(slugify((string) ($idea['region'] ?? '')), 0, 40),
                       'ai', 'open', $user['name'], now()]);
        $n++;
    }
    flash_set($n ? "$n pitch(es) filed to the ideas docket on the monitoring desk." : 'The model returned no usable pitches.', !$n);
    redirect('analytics.php?site=' . urlencode($view['slug']));
}

$fmtN = fn ($n) => number_format((int) $n);
$bar = function (int $v, int $max, string $color = 'var(--color-accent)') {
    $pct = $max > 0 ? max(2, (int) round($v / $max * 100)) : 0;
    return '<span style="display:inline-block;height:9px;width:' . $pct . 'px;max-width:100px;background:' . $color . ';vertical-align:middle"></span>';
};

admin_header('Analytics', 'analytics');
flash_show();

/* ======================= Network rollup ======================= */
if (!$view):
    $rows = [];
    foreach ($sites as $s) {
        $sid = (int) $s['id'];
        $stmt = db()->prepare('SELECT COALESCE(SUM(sessions),0) s, COALESCE(SUM(users),0) u, COALESCE(SUM(pageviews),0) p FROM site_metrics_daily WHERE site_id = ? AND day >= ?');
        $stmt->execute([$sid, $since28]);
        $m28 = $stmt->fetch();
        $stmt = db()->prepare('SELECT COALESCE(SUM(sessions),0) s FROM site_metrics_daily WHERE site_id = ? AND day >= ?');
        $stmt->execute([$sid, $since7]);
        $last7 = (int) $stmt->fetch()['s'];
        $stmt = db()->prepare('SELECT COALESCE(SUM(sessions),0) s FROM site_metrics_daily WHERE site_id = ? AND day >= ? AND day < ?');
        $stmt->execute([$sid, $prior7, $since7]);
        $prev7 = (int) $stmt->fetch()['s'];
        $stmt = db()->prepare('SELECT COALESCE(SUM(clicks),0) c, COALESCE(SUM(impressions),0) i FROM gsc_daily WHERE site_id = ? AND dim = ? AND day >= ?');
        $stmt->execute([$sid, 'query', $since28]);
        $g = $stmt->fetch();
        $rows[] = [
            'site' => $s,
            'ga'  => trim(pp_setting_for_site($sid, 'ga4_property_id')) !== '',
            'gsc' => trim(pp_setting_for_site($sid, 'gsc_site_url')) !== '',
            's28' => (int) $m28['s'], 'u28' => (int) $m28['u'], 'p28' => (int) $m28['p'],
            'l7' => $last7, 'w' => $prev7 > 0 ? round(($last7 - $prev7) / $prev7 * 100) : null,
            'clicks' => (int) $g['c'], 'imps' => (int) $g['i'],
        ];
    }
    $maxS = max(1, ...array_column($rows, 's28'));
?>
<h1 class="pagetitle">The network's numbers</h1>
<p class="pagesub">Pulled nightly from Google Analytics and Search Console into our own tables — last 28 days below, week-over-week beside it. Open a paper for the daily detail, the acquisition mix, top queries, and the story-gap report.</p>

<?php if (!pp_google_enabled()): ?>
<div class="panel">
  <h2>Not connected yet</h2>
  <p>The nightly pull needs one Google service account: set <code>google_sa_json</code> in the hub's <code>config.php</code> to the JSON key's path (outside the web root), then add the account's email as <strong>Viewer</strong> on each GA4 property and <strong>Restricted</strong> on each Search Console property, and fill <span class="mono">ga4_property_id</span> / <span class="mono">gsc_site_url</span> in each paper's Settings. The runbook's Phase 5 section walks it end to end.</p>
</div>
<?php elseif (pp_google_sa()): ?>
<div class="panel">
  <h2>Connected</h2>
  <p>Service account: <span class="mono"><?= e((string) pp_google_sa()['client_email']) ?></span> — add this email as Viewer (GA4) and Restricted (Search Console) on each property, and fill the two ids in each paper's Settings. The pull runs nightly; numbers appear the morning after.</p>
</div>
<?php endif; ?>

<div class="panel">
  <h2>Last 28 days</h2>
  <table class="tbl">
    <tr><th>Paper</th><th>Sessions</th><th></th><th>Users</th><th>Pageviews</th><th>WoW</th><th>Search clicks</th><th>Impressions</th><th>Configured</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><strong><?= e($r['site']['name']) ?></strong></td>
      <td class="mono"><?= $fmtN($r['s28']) ?></td>
      <td><?= $bar($r['s28'], $maxS) ?></td>
      <td class="mono"><?= $fmtN($r['u28']) ?></td>
      <td class="mono"><?= $fmtN($r['p28']) ?></td>
      <td class="mono"><?= $r['w'] === null ? '—' : (($r['w'] >= 0 ? '+' : '') . $r['w'] . '%') ?></td>
      <td class="mono"><?= $fmtN($r['clicks']) ?></td>
      <td class="mono"><?= $fmtN($r['imps']) ?></td>
      <td class="mono"><?= $r['ga'] ? 'GA' : '·' ?> <?= $r['gsc'] ? 'GSC' : '·' ?></td>
      <td><a class="btn btn--ghost btn--small" href="analytics.php?site=<?= e($r['site']['slug']) ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <p class="help">A paper with dots under <em>Configured</em> isn't pulling yet — its Settings need <span class="mono">ga4_property_id</span> and <span class="mono">gsc_site_url</span>.</p>
</div>

<?php
/* ======================= One paper ======================= */
else:
    $sid = (int) $view['id'];
    $stmt = db()->prepare('SELECT * FROM site_metrics_daily WHERE site_id = ? AND day >= ? ORDER BY day DESC');
    $stmt->execute([$sid, $since28]);
    $daily = $stmt->fetchAll();
    $tot = ['s' => 0, 'u' => 0, 'p' => 0, 'es' => 0, 'secs' => 0];
    $mix = [];
    $topPages = [];
    foreach ($daily as $row) {
        $tot['s'] += $row['sessions'];
        $tot['u'] += $row['users'];
        $tot['p'] += $row['pageviews'];
        $tot['es'] += $row['engaged_sessions'];
        $tot['secs'] += $row['engagement_secs'];
        foreach ((array) json_decode((string) $row['channels_json'], true) as $ch => $n) {
            $mix[$ch] = ($mix[$ch] ?? 0) + (int) $n;
        }
        if (strtotime($row['day']) >= strtotime($since7)) {
            foreach ((array) json_decode((string) $row['top_pages_json'], true) as $path => $n) {
                $topPages[$path] = ($topPages[$path] ?? 0) + (int) $n;
            }
        }
    }
    arsort($mix);
    arsort($topPages);
    $topPages = array_slice($topPages, 0, 12, true);

    // Titles for /story/<slug> paths, from our own posts.
    $titles = [];
    $slugs = [];
    foreach (array_keys($topPages) as $path) {
        if (preg_match('#^/story/([a-z0-9-]+)#', $path, $m)) {
            $slugs[$path] = $m[1];
        }
    }
    if ($slugs) {
        $marks = implode(',', array_fill(0, count($slugs), '?'));
        $stmt = db()->prepare("SELECT slug, title FROM posts WHERE slug IN ($marks)");
        $stmt->execute(array_values($slugs));
        foreach ($stmt as $row) {
            $titles[$row['slug']] = $row['title'];
        }
    }

    // Search: top queries and pages, 28 days aggregated.
    $stmt = db()->prepare("SELECT dkey, SUM(clicks) c, SUM(impressions) i, SUM(position * impressions) / NULLIF(SUM(impressions),0) pos
                           FROM gsc_daily WHERE site_id = ? AND dim = 'query' AND day >= ?
                           GROUP BY dkey ORDER BY c DESC, i DESC LIMIT 20");
    $stmt->execute([$sid, $since28]);
    $topQueries = $stmt->fetchAll();
    $stmt = db()->prepare("SELECT dkey, SUM(clicks) c, SUM(impressions) i
                           FROM gsc_daily WHERE site_id = ? AND dim = 'page' AND day >= ?
                           GROUP BY dkey ORDER BY c DESC LIMIT 10");
    $stmt->execute([$sid, $since28]);
    $topGscPages = $stmt->fetchAll();

    /* --- The story-gap report: heuristics first, no AI required ----------- */
    // 1 · Almost ranking: page-two queries with real impressions.
    $stmt = db()->prepare("SELECT dkey, SUM(clicks) c, SUM(impressions) i, SUM(position * impressions) / NULLIF(SUM(impressions),0) pos
                           FROM gsc_daily WHERE site_id = ? AND dim = 'query' AND day >= ?
                           GROUP BY dkey HAVING SUM(impressions) >= 30
                              AND (SUM(position * impressions) / NULLIF(SUM(impressions),0)) BETWEEN 8 AND 25
                           ORDER BY i DESC LIMIT 15");
    $stmt->execute([$sid, $since28]);
    $almost = $stmt->fetchAll();

    // 2 · Rising: impressions at least doubled week over week.
    $rising = [];
    $stmt = db()->prepare("SELECT dkey, SUM(CASE WHEN day >= ? THEN impressions ELSE 0 END) recent,
                                  SUM(CASE WHEN day < ? THEN impressions ELSE 0 END) prior
                           FROM gsc_daily WHERE site_id = ? AND dim = 'query' AND day >= ?
                           GROUP BY dkey HAVING SUM(CASE WHEN day >= ? THEN impressions ELSE 0 END) >= 20");
    $stmt->execute([$since7, $since7, $sid, $prior7, $since7]);
    foreach ($stmt as $row) {
        $recent = (int) $row['recent'];
        $prior = (int) $row['prior'];
        if ($recent >= max(20, 2 * max(1, $prior))) {
            $rising[] = ['q' => $row['dkey'], 'recent' => $recent, 'prior' => $prior];
        }
    }
    usort($rising, fn ($a, $b) => $b['recent'] <=> $a['recent']);
    $rising = array_slice($rising, 0, 10);

    // 3 · Uncovered demand: searched-for, but no published story matches.
    $uncovered = [];
    $op = pp_like();
    $probe = db()->prepare("SELECT 1 FROM posts p JOIN post_sites ps ON ps.post_id = p.id
                            WHERE ps.site_id = ? AND p.status = 'published' AND p.title $op ? LIMIT 1");
    $probeTag = db()->prepare("SELECT 1 FROM tags t JOIN post_tags pt ON pt.tag_id = t.id
                               JOIN post_sites ps ON ps.post_id = pt.post_id
                               WHERE ps.site_id = ? AND t.name $op ? LIMIT 1");
    foreach (array_slice($topQueries, 0, 60) as $q) {
        if (count($uncovered) >= 10) {
            break;
        }
        $needle = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $q['dkey']) . '%';
        $probe->execute([$sid, $needle]);
        if ($probe->fetch()) {
            continue;
        }
        $probeTag->execute([$sid, $needle]);
        if ($probeTag->fetch()) {
            continue;
        }
        $uncovered[] = $q;
    }

    // 4 · Wire heat: unused wire items in this paper's regions, last 7 days.
    $siteRegions = array_keys((array) json_decode(pp_setting_for_site($sid, 'regions', '{}'), true));
    $wireHeat = [];
    if ($siteRegions) {
        $marks = implode(',', array_fill(0, count($siteRegions), '?'));
        $stmt = db()->prepare("SELECT n.title, n.url, s.name AS source_name, n.region FROM news_items n
                               JOIN sources s ON s.id = n.source_id
                               WHERE n.used = 0 AND n.region IN ($marks) AND n.fetched_at >= ?
                               ORDER BY n.fetched_at DESC LIMIT 8");
        $stmt->execute([...$siteRegions, date('Y-m-d H:i:s', strtotime('-7 days'))]);
        $wireHeat = $stmt->fetchAll();
    }

    // 5 · Monitoring heat: item clusters in this paper's regions, 14 days.
    $monHeat = [];
    if ($siteRegions) {
        $marks = implode(',', array_fill(0, count($siteRegions), '?'));
        $stmt = db()->prepare("SELECT region, level, COUNT(*) n FROM monitor_items
                               WHERE region IN ($marks) AND fetched_at >= ?
                               GROUP BY region, level HAVING COUNT(*) >= 2 ORDER BY n DESC LIMIT 6");
        $stmt->execute([...$siteRegions, date('Y-m-d H:i:s', strtotime('-14 days'))]);
        $monHeat = $stmt->fetchAll();
    }

    // The gap report as plain text — what the optional AI pitches read.
    $gapText = "ALMOST RANKING (page-two queries, 28d)\n";
    foreach ($almost as $a) {
        $gapText .= "- \"{$a['dkey']}\" · " . (int) $a['i'] . ' impressions · position ' . round((float) $a['pos'], 1) . "\n";
    }
    $gapText .= "\nRISING QUERIES (7d vs prior 7d)\n";
    foreach ($rising as $r) {
        $gapText .= "- \"{$r['q']}\" · {$r['prior']} → {$r['recent']} impressions\n";
    }
    $gapText .= "\nUNCOVERED DEMAND (searched, no matching story)\n";
    foreach ($uncovered as $u) {
        $gapText .= "- \"{$u['dkey']}\" · " . (int) $u['i'] . " impressions\n";
    }
    $maxQ = max(1, ...array_map(fn ($q) => (int) $q['c'], $topQueries ?: [['c' => 1]]));
?>
<div class="headrow">
  <h1 class="pagetitle"><?= e($view['name']) ?></h1>
  <div><a class="btn btn--ghost" href="analytics.php">← All papers</a></div>
</div>
<p class="pagesub">Last 28 days, pulled nightly. The story-gap report below points at coverage the numbers say is missing — suggestions, never auto-filed stories.</p>

<div class="panel">
  <h2>Traffic</h2>
  <div class="stats">
    <div class="crow"><span>Sessions</span><b><?= $fmtN($tot['s']) ?></b></div>
    <div class="crow"><span>Users</span><b><?= $fmtN($tot['u']) ?></b></div>
    <div class="crow"><span>Pageviews</span><b><?= $fmtN($tot['p']) ?></b></div>
    <div class="crow"><span>Engaged share</span><b><?= $tot['s'] ? round($tot['es'] / $tot['s'] * 100) . '%' : '—' ?></b></div>
    <div class="crow"><span>Avg engagement</span><b><?= $tot['s'] ? gmdate('i:s', (int) ($tot['secs'] / $tot['s'])) : '—' ?></b></div>
  </div>
  <?php if ($daily): $maxD = max(1, ...array_column($daily, 'sessions')); ?>
  <table class="tbl" style="margin-top:12px">
    <tr><th>Day</th><th>Sessions</th><th></th><th>Users</th><th>Pageviews</th></tr>
    <?php foreach (array_slice($daily, 0, 14) as $row): ?>
    <tr>
      <td class="mono"><?= e(fmt_date($row['day'], 'D, M j')) ?></td>
      <td class="mono"><?= $fmtN($row['sessions']) ?></td>
      <td><?= $bar((int) $row['sessions'], $maxD) ?></td>
      <td class="mono"><?= $fmtN($row['users']) ?></td>
      <td class="mono"><?= $fmtN($row['pageviews']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <p>No pulled days yet — the nightly cron fills this once <span class="mono">ga4_property_id</span> is set in this paper's Settings.</p>
  <?php endif; ?>
</div>

<div class="panel">
  <h2>Acquisition · 28 days</h2>
  <?php if ($mix): $maxC = max(1, ...array_values($mix)); ?>
  <table class="tbl">
    <tr><th>Channel</th><th>Sessions</th><th></th><th>Share</th></tr>
    <?php foreach ($mix as $ch => $n): ?>
    <tr><td><?= e($ch) ?></td><td class="mono"><?= $fmtN($n) ?></td><td><?= $bar($n, $maxC) ?></td><td class="mono"><?= $tot['s'] ? round($n / $tot['s'] * 100) : 0 ?>%</td></tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?><p>No channel data yet.</p><?php endif; ?>
</div>

<div class="panel">
  <h2>Top stories · 7 days (GA pageviews · our own read counter beside it)</h2>
  <?php if ($topPages): ?>
  <table class="tbl">
    <tr><th>Page</th><th>GA views</th><th>Site reads</th></tr>
    <?php foreach ($topPages as $path => $n): $slug = $slugs[$path] ?? null; ?>
    <tr>
      <td><?= $slug && isset($titles[$slug]) ? e($titles[$slug]) : '<span class="mono">' . e($path) . '</span>' ?></td>
      <td class="mono"><?= $fmtN($n) ?></td>
      <td class="mono"><?php
        if ($slug && isset($titles[$slug])) {
            $stmt = db()->prepare('SELECT views FROM posts WHERE slug = ?');
            $stmt->execute([$slug]);
            echo $fmtN((int) $stmt->fetchColumn());
        } else {
            echo '—';
        }
      ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?><p>No page data yet.</p><?php endif; ?>
</div>

<div class="panel">
  <h2>Search · 28 days</h2>
  <?php if ($topQueries): ?>
  <table class="tbl">
    <tr><th>Query</th><th>Clicks</th><th></th><th>Impressions</th><th>CTR</th><th>Position</th></tr>
    <?php foreach ($topQueries as $q): ?>
    <tr>
      <td><?= e($q['dkey']) ?></td>
      <td class="mono"><?= $fmtN($q['c']) ?></td>
      <td><?= $bar((int) $q['c'], $maxQ) ?></td>
      <td class="mono"><?= $fmtN($q['i']) ?></td>
      <td class="mono"><?= $q['i'] ? round($q['c'] / $q['i'] * 100, 1) : 0 ?>%</td>
      <td class="mono"><?= round((float) $q['pos'], 1) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if ($topGscPages): ?>
  <p class="help" style="margin-top:10px">Top landing pages from search:
    <?php foreach (array_slice($topGscPages, 0, 5) as $p): ?><span class="mono"><?= e(parse_url((string) $p['dkey'], PHP_URL_PATH) ?: $p['dkey']) ?></span> (<?= $fmtN($p['c']) ?>) · <?php endforeach; ?>
  </p>
  <?php endif; ?>
  <?php else: ?><p>No search data yet — it appears the morning after <span class="mono">gsc_site_url</span> is set in this paper's Settings.</p><?php endif; ?>
</div>

<div class="panel" style="border-top:3px solid var(--color-accent)">
  <h2>The story-gap report</h2>
  <p class="pagesub" style="margin-bottom:12px">Where the numbers say coverage is missing. Heuristics, not verdicts — a journalist judges every one.</p>

  <h3 style="margin-top:8px">Almost ranking · a stronger or fresher story wins this page-one slot</h3>
  <?php if ($almost): ?>
  <table class="tbl">
    <tr><th>Query</th><th>Impressions</th><th>Clicks</th><th>Position</th></tr>
    <?php foreach ($almost as $a): ?>
    <tr><td><?= e($a['dkey']) ?></td><td class="mono"><?= $fmtN($a['i']) ?></td><td class="mono"><?= $fmtN($a['c']) ?></td><td class="mono"><?= round((float) $a['pos'], 1) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?><p>Nothing sitting on page two right now.</p><?php endif; ?>

  <h3 style="margin-top:16px">Rising queries · impressions at least doubled week over week</h3>
  <?php if ($rising): ?>
  <table class="tbl">
    <tr><th>Query</th><th>Prior 7d</th><th>Last 7d</th></tr>
    <?php foreach ($rising as $r): ?>
    <tr><td><?= e($r['q']) ?></td><td class="mono"><?= $fmtN($r['prior']) ?></td><td class="mono"><b><?= $fmtN($r['recent']) ?></b></td></tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?><p>No sharp risers this week.</p><?php endif; ?>

  <h3 style="margin-top:16px">Uncovered demand · searched for, no matching published story</h3>
  <?php if ($uncovered): ?>
  <table class="tbl">
    <tr><th>Query</th><th>Impressions</th><th>Clicks</th></tr>
    <?php foreach ($uncovered as $u): ?>
    <tr><td><?= e($u['dkey']) ?></td><td class="mono"><?= $fmtN($u['i']) ?></td><td class="mono"><?= $fmtN($u['c']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <p class="help">Matched against titles and tags on this paper — phrase match, so read with judgment.</p>
  <?php else: ?><p>Every high-impression query has a matching story.</p><?php endif; ?>

  <h3 style="margin-top:16px">Wire heat · unused wire items in this paper's regions, 7 days</h3>
  <?php if ($wireHeat): ?>
  <?php foreach ($wireHeat as $w): ?>
  <div class="newsitem"><div class="t">
    <a href="<?= e($w['url']) ?>" target="_blank" rel="noopener"><?= e($w['title']) ?></a>
    <span class="src"><?= e($w['source_name']) ?> · <?= e($w['region']) ?></span>
  </div></div>
  <?php endforeach; ?>
  <?php else: ?><p>The wire in this paper's regions is either quiet or already worked.</p><?php endif; ?>

  <h3 style="margin-top:16px">Monitoring heat · what officialdom is publishing in these regions, 14 days</h3>
  <?php if ($monHeat): ?>
  <p><?php foreach ($monHeat as $m): ?><a class="btn btn--ghost btn--small" href="monitor.php?region=<?= e($m['region']) ?>&amp;level=<?= e($m['level']) ?>"><?= e($m['region']) ?> · <?= e($m['level']) ?> · <?= (int) $m['n'] ?> item(s)</a> <?php endforeach; ?></p>
  <?php else: ?><p>No clusters on the monitoring desk for these regions.</p><?php endif; ?>

  <?php if ($almost || $rising || $uncovered): ?>
  <form method="post" style="margin-top:16px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="pitches">
    <input type="hidden" name="gaps" value="<?= e($gapText) ?>">
    <button class="btn" type="submit"<?= pp_ai_enabled() ? '' : ' disabled' ?>>Draft pitches from these gaps</button>
    <span class="help"><?= pp_ai_enabled() ? 'Three to five pitches, filed to the ideas docket — a journalist claims and writes every one.' : 'Lights up when the research desk is connected.' ?></span>
  </form>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php admin_footer(); ?>
