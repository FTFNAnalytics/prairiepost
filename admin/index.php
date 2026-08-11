<?php
/** Newsroom dashboard: the daily news pull, plus the state of the paper. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_login();
$editor = is_editor($user);

$regions = setting_json('regions', ['local' => 'Local']);
$regionKeys = array_keys($regions);
$region = (string) ($_GET['region'] ?? $regionKeys[0]);
if (!isset($regions[$region])) {
    $region = $regionKeys[0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $itemId = (int) ($_POST['item_id'] ?? 0);

    if ($action === 'start_draft' && $itemId) {
        $stmt = db()->prepare('SELECT n.*, s.name AS source_name FROM news_items n JOIN sources s ON s.id = n.source_id WHERE n.id = ?');
        $stmt->execute([$itemId]);
        if ($item = $stmt->fetch()) {
            $title = $item['title'];
            $body = '<p><em>Draft started from the news pull. Source: '
                  . e($item['source_name']) . ' — <a href="' . e($item['url']) . '">' . e($item['url']) . '</a></em></p>'
                  . ($item['summary'] ? '<p>' . e($item['summary']) . '</p>' : '');
            db()->prepare('INSERT INTO posts (title, slug, author_id, byline, lede, body, source_url, origin, status, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$title, unique_post_slug($title), (int) $user['id'], $user['name'], '', $body,
                           $item['url'], 'wire', 'draft', now(), now()]);
            $postId = pp_last_id('posts');
            set_post_sites($postId, [current_site_id()]);
            db()->prepare('UPDATE news_items SET used = 1 WHERE id = ?')->execute([$itemId]);
            redirect('post-edit.php?id=' . $postId);
        }
    }
    if ($action === 'toggle_used' && $itemId) {
        db()->prepare('UPDATE news_items SET used = 1 - used WHERE id = ?')->execute([$itemId]);
        redirect('index.php?region=' . urlencode($region));
    }
}

$counts = pp_counts();
$items = news_items_for_region($region, 40);
if ($editor) {
    $drafts = db()->query("SELECT id, title, updated_at FROM posts WHERE status = 'draft' ORDER BY updated_at DESC LIMIT 6")->fetchAll();
} else {
    $stmt = db()->prepare("SELECT id, title, updated_at FROM posts WHERE status IN ('draft','in_review') AND author_id = ? ORDER BY updated_at DESC LIMIT 6");
    $stmt->execute([(int) $user['id']]);
    $drafts = $stmt->fetchAll();
}
$queue = $editor ? review_queue() : [];
$lastFetch = db()->query('SELECT MAX(last_fetched_at) AS t FROM sources')->fetch()['t'] ?? null;

admin_header('Dashboard', 'dashboard');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle">The morning pull</h1>
  <?php if ($editor): ?><a class="btn btn--ghost" href="sources.php?fetch=all">Fetch all feeds now</a><?php endif; ?>
</div>
<p class="pagesub">
  <?php if ($lastFetch): ?>Wire last fetched <?= e(fmt_date($lastFetch, 'M j, g:i a')) ?>.
  <?php else: ?>The wire hasn't been fetched yet — run it from <a href="sources.php">Sources</a>, or set up the cron job in the README.<?php endif; ?>
  Start a draft from a headline, or tick it off once it's covered.
</p>

<div class="stats">
  <div class="stat"><div class="n"><?= $counts['published'] ?></div><span class="k">Published</span></div>
  <div class="stat"><div class="n"><?= $counts['drafts'] ?></div><span class="k">Drafts</span></div>
  <div class="stat"><div class="n"><?= $counts['in_review'] ?></div><span class="k">In review</span></div>
  <div class="stat"><div class="n"><?= $counts['scheduled'] ?></div><span class="k">Scheduled</span></div>
  <div class="stat"><div class="n"><?= $counts['subscribers'] ?></div><span class="k">Subscribers</span></div>
  <div class="stat"><div class="n"><?= $counts['sources'] ?></div><span class="k">Feeds on</span></div>
</div>

<?php if ($editor && pp_is_hub()): ?>
<?php
// The control room's morning glance: every paper's pulse, and any feed
// that failed its last fetch. Aggregates over the shared tables — the
// data has been there all along, just never in one room.
$published = [];
foreach (db()->query("SELECT ps.site_id, COUNT(*) AS n, MAX(p.published_at) AS t
    FROM posts p JOIN post_sites ps ON ps.post_id = p.id
    WHERE p.status = 'published' GROUP BY ps.site_id") as $row) {
    $published[(int) $row['site_id']] = $row;
}
$subs = [];
foreach (db()->query("SELECT site_id, COUNT(*) AS n FROM subscribers WHERE status = 'active' GROUP BY site_id") as $row) {
    $subs[(int) $row['site_id']] = (int) $row['n'];
}
$editions = [];
foreach (db()->query('SELECT site_id, MAX(edition_date) AS d FROM newsletters GROUP BY site_id') as $row) {
    $editions[(int) $row['site_id']] = (string) $row['d'];
}
$failing = db()->query("SELECT name, region, last_status, last_fetched_at FROM sources
    WHERE enabled = 1 AND last_status LIKE 'error:%' ORDER BY region, name")->fetchAll();
?>
<div class="panel">
  <h2>The network, this morning</h2>
  <table class="tbl">
    <tr><th>Paper</th><th>Published</th><th>Last story</th><th>Subscribers</th><th>Last 6 a.m.</th></tr>
    <?php foreach (pp_paper_sites() as $s): $sid = (int) $s['id']; ?>
    <tr>
      <td><strong><?= e($s['name']) ?></strong> <span class="mono" style="color:#5A6A5C"><?= e($s['slug']) ?></span></td>
      <td class="mono"><?= (int) ($published[$sid]['n'] ?? 0) ?></td>
      <td class="mono"><?= !empty($published[$sid]['t']) ? e(fmt_date($published[$sid]['t'], 'M j, g:i a')) : '—' ?></td>
      <td class="mono"><?= $subs[$sid] ?? 0 ?></td>
      <td class="mono"><?= isset($editions[$sid]) ? e(fmt_date($editions[$sid], 'M j')) : '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($failing): ?>
<div class="panel" style="border-top:4px solid #9C3B22">
  <h2>Wire feeds failing their last fetch</h2>
  <table class="tbl">
    <tr><th>Feed</th><th>Region</th><th>Last status</th><th>Tried</th></tr>
    <?php foreach ($failing as $f): ?>
    <tr>
      <td><?= e($f['name']) ?></td>
      <td class="mono"><?= e($f['region']) ?></td>
      <td class="mono" style="color:#9C3B22"><?= e($f['last_status']) ?></td>
      <td class="mono"><?= e(fmt_date($f['last_fetched_at'], 'M j, g:i a')) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <p class="help">Some publishers block automated readers (CTV, Postmedia) — a permanent error there is expected; pause the source. Anything else usually recovers on the next fetch.</p>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($editor && !pp_is_hub()): ?>
<?php
$fpHero = featured_post();
$fpBand = front_featured_posts($fpHero ? [(int) $fpHero['id']] : []);
$fpLeads = db()->query("SELECT p.id, p.title, c.name AS desk FROM posts p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.placement = 'desk_lead' AND p.status = 'published' ORDER BY c.sort")->fetchAll();
?>
<div class="panel">
  <h2>The front page, as set</h2>
  <table class="tbl">
    <tr><th>Slot</th><th>Story</th></tr>
    <tr><td>Hero</td><td><?php if ($fpHero): ?><a class="rowtitle" href="post-edit.php?id=<?= (int) $fpHero['id'] ?>"><?= e($fpHero['title']) ?></a><?= $fpHero['placement'] !== 'hero' ? ' <span class="chip chip--used">latest story standing in — no hero set</span>' : '' ?><?php else: ?>—<?php endif; ?></td></tr>
    <tr><td>Featured band</td><td><?php if ($fpBand): ?><?php foreach ($fpBand as $i => $fp): ?><?= $i ? ' · ' : '' ?><a class="rowtitle" href="post-edit.php?id=<?= (int) $fp['id'] ?>"><?= e($fp['title']) ?></a><?php endforeach; ?><?php else: ?><span class="chip chip--used">empty — two latest stand in</span><?php endif; ?></td></tr>
    <tr><td>Desk leads</td><td><?php if ($fpLeads): ?><?php foreach ($fpLeads as $i => $fp): ?><?= $i ? ' · ' : '' ?><a class="rowtitle" href="post-edit.php?id=<?= (int) $fp['id'] ?>"><?= e($fp['title']) ?></a> <span class="chip chip--used"><?= e($fp['desk'] ?? '—') ?></span><?php endforeach; ?><?php else: ?>—<?php endif; ?></td></tr>
  </table>
</div>
<?php endif; ?>

<?php if ($editor && $queue): ?>
<div class="panel" style="border-top:4px solid var(--pp-bigsky)">
  <h2>The review queue — waiting on an editor</h2>
  <table class="tbl">
    <tr><th>Story</th><th>Author</th><th>Desk</th><th>Submitted</th><th></th></tr>
    <?php foreach ($queue as $q): ?>
    <tr>
      <td><a class="rowtitle" href="post-edit.php?id=<?= (int) $q['id'] ?>"><?= e($q['title']) ?></a></td>
      <td><?= e($q['author_name'] ?: $q['byline']) ?></td>
      <td><?= e($q['category_name'] ?? '—') ?></td>
      <td class="mono"><?= e(fmt_date($q['updated_at'], 'M j, g:i a')) ?></td>
      <td><a class="btn btn--sky btn--small" href="post-edit.php?id=<?= (int) $q['id'] ?>">Review</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="tabs" role="tablist" aria-label="Regions">
  <?php foreach ($regions as $key => $label): ?>
  <a href="index.php?region=<?= e(urlencode($key)) ?>"<?= $key === $region ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="panel">
  <h2><?= e($regions[$region]) ?> — latest from the wire</h2>
  <?php if (!$items): ?>
  <p>No items fetched for this region yet. Check the feeds under <a href="sources.php">Sources</a>, then fetch — new headlines land here.</p>
  <?php endif; ?>
  <?php foreach ($items as $item): ?>
  <div class="newsitem<?= $item['used'] ? ' is-used' : '' ?>">
    <div class="t">
      <a href="<?= e($item['url']) ?>" target="_blank" rel="noopener"><?= e($item['title']) ?></a>
      <span class="src"><?= e($item['source_name']) ?> · <?= e(fmt_date($item['published_at'] ?: $item['fetched_at'], 'M j, g:i a')) ?></span>
    </div>
    <div class="acts">
      <?php if ($item['used']): ?><span class="chip chip--used">Used</span><?php endif; ?>
      <?php if ($editor): ?>
      <a class="btn btn--ghost btn--small" href="link-post.php?item=<?= (int) $item['id'] ?>">Post link</a>
      <?php endif; ?>
      <form method="post" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="start_draft">
        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
        <button class="btn btn--sky btn--small" type="submit">Start draft</button>
      </form>
      <form method="post" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_used">
        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
        <button class="btn btn--ghost btn--small" type="submit"><?= $item['used'] ? 'Unmark' : 'Mark used' ?></button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="panel">
  <h2><?= $editor ? 'Open drafts' : 'Your stories in progress' ?></h2>
  <?php if (!$drafts): ?>
  <p>No drafts on the spike. Start one from a wire headline above, or <a href="post-edit.php">file a fresh story</a>.</p>
  <?php else: ?>
  <table class="tbl">
    <tr><th>Story</th><th>Last touched</th><th></th></tr>
    <?php foreach ($drafts as $d): ?>
    <tr>
      <td><a class="rowtitle" href="post-edit.php?id=<?= (int) $d['id'] ?>"><?= e($d['title']) ?></a></td>
      <td class="mono"><?= e(fmt_date($d['updated_at'], 'M j, g:i a')) ?></td>
      <td><a class="btn btn--ghost btn--small" href="post-edit.php?id=<?= (int) $d['id'] ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<?php admin_footer(); ?>
