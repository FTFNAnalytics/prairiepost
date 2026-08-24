<?php
/** The Prairie Dispatch — desk archive. */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

$slug = (string) ($_GET['slug'] ?? '');
$cat = $slug !== '' ? category_by_slug($slug) : null;

if (!$cat) {
    http_response_code(404);
    page_header(['title' => 'Desk not found']);
    echo '<div class="wrap pagehead"><h1>No such desk</h1>';
    echo '<div class="empty">That desk doesn\'t exist. The desks are listed in the navigation above, or start at the <a href="/">front page</a>.</div></div>';
    page_footer();
    exit;
}

$perPage = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = count_posts_in_category((int) $cat['id']);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$posts = posts_in_category((int) $cat['id'], $perPage, [], ($page - 1) * $perPage);

$deskColor = empty($cat['color_is_fill']) ? pp_desk_hex($cat['slug'], $cat['color']) : pp_brand_palette()['ink'];

// On Turtle Island the desk's name replaces the nameplate in the ink block
// (screen 2b), so both are set before the header renders.
if (pp_chrome('template') === 'turtleisland') {
    $GLOBALS['ppTiMast'] = 'section';
    $GLOBALS['ppTiPlate'] = pp_desk_label($cat['slug'], $cat['name']);
}

page_header([
    'title'       => $cat['name'],
    'description' => (string) $cat['description'],
    'canonical'   => site_url() . '/desk/' . $cat['slug'],
], $cat['slug']);
?>

<?php if (pp_chrome('template') === 'torch') {
    require PP_ROOT . '/app/views/section-torch.php';
    page_footer();
    return;
} ?>

<?php if (pp_chrome('template') === 'turtleisland') {
    require PP_ROOT . '/app/views/section-turtleisland.php';
    page_footer();
    return;
}
if (pp_chrome('template') === 'pickering') {
    require PP_ROOT . '/app/views/section-pickering.php';
    page_footer();
    return;
}
if (pp_chrome('template') === 'bleuetblanc') {
    require PP_ROOT . '/app/views/section-bleuetblanc.php';
    page_footer();
    return;
}
if (pp_chrome('template') === 'standard') {
    require PP_ROOT . '/app/views/section-standard.php';
    page_footer();
    return;
} ?>
<div class="pagehead wrap"<?= desk_style($deskColor) ?>>
  <span class="pp-meta" style="color:#5A6A5C">Desk</span>
  <h1><?= e($cat['name']) ?></h1>
  <?php if ($cat['description']): ?><p class="desc"><?= e($cat['description']) ?></p><?php endif; ?>
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
      <?php else: ?><a href="<?= e(url('desk/' . $cat['slug']) . '?page=' . $i) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>

  <?php else: ?>
  <div class="empty">No stories filed from the <?= e($cat['name']) ?> desk yet. Try <a href="/">the front page</a> or another desk above.</div>
  <?php endif; ?>
</div>

<?php page_footer(); ?>
