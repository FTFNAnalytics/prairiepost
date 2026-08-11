<?php
/** Region archive — the aggregator's by-province pages (/region/{key}). */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

$regions = setting_json('regions');
$key = (string) ($_GET['slug'] ?? '');

if ($key === '' || !isset($regions[$key])) {
    http_response_code(404);
    page_header(['title' => 'Region not found']);
    echo '<div class="wrap pagehead"><h1>No such region</h1>';
    echo '<div class="empty">That region isn\'t one of ours. Start at the <a href="/">front page</a>.</div></div>';
    page_footer();
    exit;
}

$label = (string) $regions[$key];
$perPage = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = count_posts_in_region($key);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$posts = posts_in_region($key, $perPage, [], ($page - 1) * $perPage);

page_header([
    'title'       => $label,
    'description' => 'The latest from ' . $label . ' — every headline links to the newsroom that reported it.',
    'canonical'   => site_url() . '/region/' . $key,
]);
?>

<div class="pagehead wrap">
  <span class="pp-meta" style="color:#5A6A5C">Region</span>
  <h1><?= e($label) ?></h1>
  <div class="pp-horizon"></div>
</div>

<div class="archive wrap">
  <?php if ($posts): ?>
  <div class="deskgrid">
    <?php foreach ($posts as $post): ?><?= story_card($post) ?><?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
  <nav class="pagination" aria-label="Pages">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?><span aria-current="page"><?= $i ?></span>
      <?php else: ?><a href="<?= e(url('region/' . $key) . '?page=' . $i) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>

  <?php else: ?>
  <div class="empty">Nothing filed from <?= e($label) ?> yet. Try <a href="/">the front page</a>.</div>
  <?php endif; ?>
</div>

<?php page_footer(); ?>
