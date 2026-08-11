<?php
/** Newsroom dashboard: the daily news pull, plus the state of the paper.
 *  Laid out like a proof of the day's front page (Broadsheet direction 1b):
 *  the morning pull as a numbered index on the left, the paper's numbers,
 *  the review queue and the front page in the right rail. On the hub, the
 *  network panels run full-width above the grid. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_login();
$editor = is_editor($user);
$hub = pp_is_hub();

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
            set_post_sites($postId, $hub ? [] : [current_site_id()]);
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

if ($editor && !$hub) {
    $fpHero = featured_post();
    $fpBand = front_featured_posts($fpHero ? [(int) $fpHero['id']] : []);
    $fpLeads = db()->query("SELECT p.id, p.title, c.name AS desk FROM posts p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.placement = 'desk_lead' AND p.status = 'published' ORDER BY c.sort")->fetchAll();
}

$actions = ($editor ? '<a class="btn btn--ghost" href="sources.php?fetch=all">Fetch all feeds</a>' : '')
         . '<a class="btn" href="post-edit.php">New story</a>';
admin_header('Dashboard', 'dashboard', $actions);
flash_show();
?>

<?php if ($editor && $hub): ?>
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
      <td><strong><?= e($s['name']) ?></strong> <span class="mono"><?= e($s['slug']) ?></span></td>
      <td class="mono"><?= (int) ($published[$sid]['n'] ?? 0) ?></td>
      <td class="mono"><?= !empty($published[$sid]['t']) ? e(fmt_date($published[$sid]['t'], 'M j, g:i a')) : '—' ?></td>
      <td class="mono"><?= $subs[$sid] ?? 0 ?></td>
      <td class="mono"><?= isset($editions[$sid]) ? e(fmt_date($editions[$sid], 'M j')) : '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($failing): ?>
<div class="panel" style="border-top:3px solid var(--color-accent-2)">
  <h2>Wire feeds failing their last fetch</h2>
  <table class="tbl">
    <tr><th>Feed</th><th>Region</th><th>Last status</th><th>Tried</th></tr>
    <?php foreach ($failing as $f): ?>
    <tr>
      <td><?= e($f['name']) ?></td>
      <td class="mono"><?= e($f['region']) ?></td>
      <td class="mono" style="color:var(--color-accent-2-700)"><?= e($f['last_status']) ?></td>
      <td class="mono"><?= e(fmt_date($f['last_fetched_at'], 'M j, g:i a')) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <p class="help">Some publishers block automated readers (CTV, Postmedia) — a permanent error there is expected; pause the source. Anything else usually recovers on the next fetch.</p>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="dashgrid">
  <section>
    <h2>The morning pull</h2>
    <p class="pagesub" style="margin-bottom:0">Start a draft from a headline, or tick it off once it's covered.</p>

    <div class="tabs" role="tablist" aria-label="Regions">
      <?php foreach ($regions as $key => $label): ?>
      <a href="index.php?region=<?= e(urlencode($key)) ?>"<?= $key === $region ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (!$items): ?>
    <p class="help" style="margin-top:18px">No items fetched for this region yet.
      <?= $editor ? 'Check the feeds under <a href="sources.php">Sources</a>, then fetch — new headlines land here.' : 'New headlines land here once the wire is fetched.' ?></p>
    <?php endif; ?>

    <div class="wirelist">
      <?php foreach ($items as $i => $item): ?>
      <article class="wireitem<?= $item['used'] ? ' is-used' : '' ?>">
        <div class="num"><?= $i + 1 ?></div>
        <div>
          <a class="hl" href="<?= e($item['url']) ?>" target="_blank" rel="noopener"><?= e($item['title']) ?></a>
          <div class="meta"><?= e($item['source_name']) ?> · <?= e(fmt_date($item['published_at'] ?: $item['fetched_at'], 'M j, g:i a')) ?><?= $item['used'] ? ' · used' : '' ?></div>
          <div class="acts">
            <?php if (!$item['used']): ?>
            <form method="post" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="start_draft">
              <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
              <button class="btn btn--small" type="submit">Start draft</button>
            </form>
            <?php if ($editor): ?>
            <a class="btn btn--outline btn--small" href="link-post.php?item=<?= (int) $item['id'] ?>">Post link</a>
            <?php endif; ?>
            <?php endif; ?>
            <form method="post" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_used">
              <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
              <button class="btn btn--danger btn--small" type="submit"><?= $item['used'] ? 'Unmark' : 'Mark used' ?></button>
            </form>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <aside class="rail">
    <div class="panel">
      <h2><?= $hub ? 'The network today' : 'The paper today' ?></h2>
      <div class="crow"><span>Published</span><b><?= $counts['published'] ?></b></div>
      <div class="crow"><span>Drafts</span><b><?= $counts['drafts'] ?></b></div>
      <div class="crow"><span>In review</span><b class="hot"><?= $counts['in_review'] ?></b></div>
      <div class="crow"><span>Scheduled</span><b><?= $counts['scheduled'] ?></b></div>
      <?php if ($hub): ?>
      <div class="crow"><span>Papers</span><b><?= count(pp_paper_sites()) ?></b></div>
      <?php else: ?>
      <div class="crow"><span>Subscribers</span><b><?= $counts['subscribers'] ?></b></div>
      <?php endif; ?>
      <div class="crow"><span>Feeds on</span><b><?= $counts['sources'] ?></b></div>
    </div>

    <?php if ($editor && $queue): ?>
    <div class="panel">
      <h2>Review queue</h2>
      <?php foreach ($queue as $q): ?>
      <div class="qitem">
        <a class="t" href="post-edit.php?id=<?= (int) $q['id'] ?>"><?= e($q['title']) ?></a>
        <div class="m"><?= e($q['author_name'] ?: $q['byline']) ?> · <?= e($q['category_name'] ?? '—') ?> ·
          <a class="btn btn--outline btn--small" style="font-size:12px;padding:1px 8px" href="post-edit.php?id=<?= (int) $q['id'] ?>">Review</a></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($editor && !$hub): ?>
    <div class="panel">
      <h2>The front page, as set</h2>
      <div class="slot"><span class="chip chip--published">Hero</span>
        <span><?php if ($fpHero): ?><a class="rowtitle" href="post-edit.php?id=<?= (int) $fpHero['id'] ?>"><?= e($fpHero['title']) ?></a><?= $fpHero['placement'] !== 'hero' ? ' <span class="mono">(latest standing in)</span>' : '' ?><?php else: ?>—<?php endif; ?></span></div>
      <div class="slot"><span class="chip chip--published">Featured</span>
        <span><?php if ($fpBand): foreach ($fpBand as $i => $fp): ?><?= $i ? ' · ' : '' ?><a class="rowtitle" href="post-edit.php?id=<?= (int) $fp['id'] ?>"><?= e($fp['title']) ?></a><?php endforeach; else: ?><span class="mono">empty — two latest stand in</span><?php endif; ?></span></div>
      <div class="slot"><span class="chip chip--published">Desk leads</span>
        <span><?php if ($fpLeads): foreach ($fpLeads as $i => $fp): ?><?= $i ? ' · ' : '' ?><a class="rowtitle" href="post-edit.php?id=<?= (int) $fp['id'] ?>"><?= e($fp['title']) ?></a> <span class="mono"><?= e($fp['desk'] ?? '—') ?></span><?php endforeach; else: ?>—<?php endif; ?></span></div>
    </div>
    <?php endif; ?>
  </aside>
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
