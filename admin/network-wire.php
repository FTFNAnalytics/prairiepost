<?php
/**
 * The network newswire — the whole shared wire pool in one room.
 * Every region every paper watches, side by side: start a draft, post a
 * link, or tick a headline off for the entire network. Hub only.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_editor();
if (!pp_is_hub()) {
    redirect('index.php');
}

/*
 * Region tabs span the network: every region any feed files under, labelled
 * with whichever paper's wording named it first (region keys are shared;
 * labels are per-site settings).
 */
$labelMap = [];
foreach (db()->query("SELECT svalue FROM settings WHERE skey = 'regions'") as $row) {
    $decoded = json_decode((string) $row['svalue'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $k => $v) {
            $labelMap[$k] = $labelMap[$k] ?? (string) $v;
        }
    }
}
$regionKeys = db()->query('SELECT DISTINCT region FROM sources ORDER BY region')->fetchAll(PDO::FETCH_COLUMN);
$regions = [];
foreach ($regionKeys as $k) {
    $regions[$k] = $labelMap[$k] ?? ucwords(str_replace('-', ' ', $k));
}
if (!$regions) {
    $regions = ['local' => 'Local'];
}
$region = (string) ($_GET['region'] ?? array_key_first($regions));
if (!isset($regions[$region])) {
    $region = array_key_first($regions);
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
            $body = '<p><em>Draft started from the network wire. Source: '
                  . e($item['source_name']) . ' — <a href="' . e($item['url']) . '">' . e($item['url']) . '</a></em></p>'
                  . ($item['summary'] ? '<p>' . e($item['summary']) . '</p>' : '');
            db()->prepare('INSERT INTO posts (title, slug, author_id, byline, lede, body, source_url, origin, status, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$title, unique_post_slug($title), (int) $user['id'], $user['name'], '', $body,
                           $item['url'], 'wire', 'draft', now(), now()]);
            $postId = pp_last_id('posts');
            // No mapping yet: the editor picks papers in Runs on, or in bulk
            // from the network desk — until then it runs nowhere.
            db()->prepare('UPDATE news_items SET used = 1 WHERE id = ?')->execute([$itemId]);
            redirect('post-edit.php?id=' . $postId);
        }
    }
    if ($action === 'toggle_used' && $itemId) {
        db()->prepare('UPDATE news_items SET used = 1 - used WHERE id = ?')->execute([$itemId]);
        redirect('network-wire.php?region=' . urlencode($region));
    }
}

$items = news_items_for_region($region, 60);
$lastFetch = db()->query('SELECT MAX(last_fetched_at) AS t FROM sources')->fetch()['t'] ?? null;
$srcStmt = db()->prepare('SELECT COUNT(*) AS n FROM sources WHERE region = ? AND enabled = 1');
$srcStmt->execute([$region]);
$sourceCount = (int) $srcStmt->fetch()['n'];

admin_header('Newswire', 'netwire');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle">The network newswire</h1>
  <a class="btn btn--ghost" href="sources.php?fetch=all">Fetch all feeds now</a>
</div>
<p class="pagesub">
  The whole network's wire pool — every region every paper watches, in one room.
  <?php if ($lastFetch): ?>Last fetched <?= e(fmt_date($lastFetch, 'M j, g:i a')) ?>.<?php endif; ?>
  A draft started here runs nowhere until it's assigned to papers.
</p>

<div class="tabs" role="tablist" aria-label="Regions">
  <?php foreach ($regions as $key => $label): ?>
  <a href="network-wire.php?region=<?= e(urlencode($key)) ?>"<?= $key === $region ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="panel">
  <h2><?= e($regions[$region]) ?> · <?= $sourceCount ?> feed<?= $sourceCount === 1 ? '' : 's' ?> on</h2>
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
      <a class="btn btn--ghost btn--small" href="link-post.php?item=<?= (int) $item['id'] ?>">Post link</a>
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

<?php admin_footer(); ?>
