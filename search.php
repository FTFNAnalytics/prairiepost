<?php
/** The Prairie Dispatch — archive search. */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

$q = trim((string) ($_GET['q'] ?? ''));
$perPage = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));

$posts = [];
$total = 0;
$pages = 1;
if ($q !== '') {
    $total = count_search_posts($q);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $posts = search_posts($q, $perPage, ($page - 1) * $perPage);
}

page_header(['title' => $q !== '' ? 'Search: ' . $q : 'Search the archive', 'keep_query' => true]);
?>

<div class="pagehead wrap">
  <span class="pp-meta" style="color:#5A6A5C">The archive</span>
  <h1>Search</h1>
  <form class="searchform" method="get" action="<?= e(url('search')) ?>" role="search">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="A town, a name, a crop…" aria-label="Search the archive">
    <button class="btn btn--ink" type="submit">Search the archive</button>
  </form>
  <?php if ($q !== ''): ?>
  <p class="desc"><?= (int) $total ?> <?= $total === 1 ? 'story' : 'stories' ?> matching “<?= e($q) ?>”.</p>
  <?php endif; ?>
  <div class="pp-horizon"></div>
</div>

<div class="archive wrap">
  <?php if ($q !== '' && !$posts): ?>
  <div class="empty">Nothing in the archive matches “<?= e($q) ?>”. Try a town name, a surname, or a shorter word — the archive searches headlines, ledes and story text.</div>
  <?php elseif ($posts): ?>
  <div class="deskgrid">
    <?php foreach ($posts as $post): ?><?= story_card($post) ?><?php endforeach; ?>
  </div>
  <?php if ($pages > 1): ?>
  <nav class="pagination" aria-label="Pages">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?><span aria-current="page"><?= $i ?></span>
      <?php else: ?><a href="<?= e(url('search') . '?q=' . urlencode($q) . '&page=' . $i) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php page_footer(); ?>
