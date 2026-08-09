<?php
/** Newsroom dashboard: the daily news pull, plus the state of the paper. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
require_login();

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
            db()->prepare('INSERT INTO posts (title, slug, byline, lede, body, source_url, status, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$title, unique_post_slug($title), current_user()['name'], '', $body,
                           $item['url'], 'draft', now(), now()]);
            $postId = (int) db()->lastInsertId();
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
$drafts = db()->query("SELECT id, title, updated_at FROM posts WHERE status = 'draft' ORDER BY updated_at DESC LIMIT 6")->fetchAll();
$lastFetch = db()->query('SELECT MAX(last_fetched_at) AS t FROM sources')->fetch()['t'] ?? null;

admin_header('Dashboard', 'dashboard');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle">The morning pull</h1>
  <a class="btn btn--ghost" href="sources.php?fetch=all">Fetch all feeds now</a>
</div>
<p class="pagesub">
  <?php if ($lastFetch): ?>Wire last fetched <?= e(fmt_date($lastFetch, 'M j, g:i a')) ?>.
  <?php else: ?>The wire hasn't been fetched yet — run it from <a href="sources.php">Sources</a>, or set up the cron job in the README.<?php endif; ?>
  Start a draft from a headline, or tick it off once it's covered.
</p>

<div class="stats">
  <div class="stat"><div class="n"><?= $counts['published'] ?></div><span class="k">Published</span></div>
  <div class="stat"><div class="n"><?= $counts['drafts'] ?></div><span class="k">Drafts</span></div>
  <div class="stat"><div class="n"><?= $counts['scheduled'] ?></div><span class="k">Scheduled</span></div>
  <div class="stat"><div class="n"><?= $counts['subscribers'] ?></div><span class="k">Subscribers</span></div>
  <div class="stat"><div class="n"><?= $counts['sources'] ?></div><span class="k">Feeds on</span></div>
</div>

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
  <h2>Open drafts</h2>
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
