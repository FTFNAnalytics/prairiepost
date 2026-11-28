<?php
/* Borealis Bulletin — section front ($cat, $posts, $page, $pages
   resolved by section.php), as the owner's brand package (28 Nov 2026)
   shipped it. Polaris-ornamented desk title over
   hairlined story rows. Desk descriptions are shared network-wide, so
   this template deliberately prints the desk name only. */
$boChip = static function (array $p): string {
    $slug = (string) ($p['category_slug'] ?? '');
    if ($slug === 'opinion') {
        return 'bo-chip bo-chip--opinion';
    }
    if (in_array($slug, ['local-news', 'environment', 'communities'], true)) {
        return 'bo-chip bo-chip--pine';
    }
    return 'bo-chip';
};
?>
<div class="bo-wrap bo-section">
  <div class="bo-ttl" style="margin-top:26px"><span class="star" aria-hidden="true">&#10022;</span><h2><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h2></div>
  <?php foreach ($posts as $p): ?>
  <article class="bo-row<?= $p['image'] ? '' : ' noart' ?>">
    <div>
      <span class="<?= $boChip($p) ?>"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
      <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      <?php if ($p['lede']): ?><p><?= e($p['lede']) ?></p><?php endif; ?>
      <span class="meta"><?= dateline($p) ?></span>
    </div>
    <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
  </article>
  <?php endforeach; ?>
  <?php if (!$posts): ?>
  <div class="bo-empty" style="margin-top:8px">Nothing filed on this northern desk yet — the newsroom is on it.</div>
  <?php endif; ?>
  <?php if (($pages ?? 1) > 1 && ($page ?? 1) < $pages): ?>
  <div class="bo-more"><a class="bo-btn" href="<?= e(url('desk/' . $cat['slug'])) ?>?page=<?= (int) $page + 1 ?>">More <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a></div>
  <?php endif; ?>
</div>
