<?php
/** The Prairie Post — public author page. */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

$slug = (string) ($_GET['slug'] ?? '');
$author = $slug !== '' ? user_by_slug($slug) : null;

if (!$author) {
    http_response_code(404);
    page_header(['title' => 'Author not found']);
    echo '<div class="wrap pagehead"><h1>Not found</h1>';
    echo '<div class="empty">No author page at that address. Bylines on any story link to the right place, or start at the <a href="/">front page</a>.</div></div>';
    page_footer();
    exit;
}

$perPage = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = count_posts_by_author((int) $author['id']);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$posts = posts_by_author((int) $author['id'], $perPage, ($page - 1) * $perPage);

page_header([
    'title'       => $author['name'],
    'description' => excerpt((string) ($author['bio'] ?: $author['name'] . ' writes for ' . setting('site_title')), 155),
    'canonical'   => site_url() . '/author/' . $author['slug'],
    'og_image'    => $author['photo'] ? site_url() . $author['photo'] : null,
    'jsonld'      => [
        '@context' => 'https://schema.org',
        '@type'    => 'Person',
        'name'     => $author['name'],
        'jobTitle' => $author['title'] ?: 'Reporter',
        'url'      => site_url() . '/author/' . $author['slug'],
        'worksFor' => ['@type' => 'NewsMediaOrganization', 'name' => setting('site_title', 'The Prairie Post')],
    ],
]);
?>

<div class="pagehead wrap">
  <span class="pp-meta" style="color:#5A6A5C">From the newsroom</span>
  <div style="display:flex;gap:26px;align-items:flex-end;flex-wrap:wrap;margin-top:6px">
    <?php if ($author['photo']): ?>
    <img src="<?= e($author['photo']) ?>" alt="<?= e($author['name']) ?>" width="120" height="120" style="width:120px;height:120px;object-fit:cover;background:var(--pp-highsky)">
    <?php endif; ?>
    <div>
      <h1 style="--desk:#17301C"><?= e($author['name']) ?></h1>
      <?php if ($author['title']): ?><p class="pp-meta" style="margin:6px 0 0"><?= e($author['title']) ?></p><?php endif; ?>
    </div>
  </div>
  <?php if ($author['bio']): ?><p class="desc"><?= e((string) $author['bio']) ?></p><?php endif; ?>
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
      <?php else: ?><a href="<?= e(url('author/' . $author['slug']) . '?page=' . $i) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
  <?php else: ?>
  <div class="empty">No stories filed under this byline on this site yet. Try the <a href="/">front page</a>.</div>
  <?php endif; ?>
</div>

<?php page_footer(); ?>
