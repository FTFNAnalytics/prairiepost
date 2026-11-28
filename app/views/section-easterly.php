<?php
/* The Easterly — section front. FOUNDATION build (Nov 2026): Toronto
   Telegraph's desk page, unbranded ($cat, $posts, $page, $pages
   resolved by section.php). The ruled desk title over hairlined rows;
   desk descriptions are shared network-wide, so the name is the page. */
?>
<div class="ea-wrap ea-section">
  <div class="ea-ttl" style="margin-top:26px"><h2><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h2></div>
  <?php foreach ($posts as $p): ?>
  <article class="ea-row<?= $p['image'] ? '' : ' noart' ?>">
    <div>
      <span class="ea-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
      <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      <?php if ($p['lede']): ?><p><?= e($p['lede']) ?></p><?php endif; ?>
      <span class="meta"><?= dateline($p) ?></span>
    </div>
    <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
  </article>
  <?php endforeach; ?>
  <?php if (!$posts): ?>
  <div class="ea-empty" style="margin-top:8px">Nothing on this desk yet — the newsroom is on it.</div>
  <?php endif; ?>
  <?php if (($pages ?? 1) > 1 && ($page ?? 1) < $pages): ?>
  <div class="ea-more"><a class="ea-btn" href="<?= e(url('desk/' . $cat['slug'])) ?>?page=<?= (int) $page + 1 ?>">More <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a></div>
  <?php endif; ?>
</div>
