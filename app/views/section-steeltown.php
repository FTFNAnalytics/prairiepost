<?php
/* Steeltown Standard — section front (brand build; $cat, $posts,
   $page, $pages resolved by section.php). The gold-ruled desk title
   over hairlined story rows. Desk descriptions are shared
   network-wide, so this template does not print them — the desk name
   is the page. */ ?>
<div class="st-wrap st-section">
  <div class="st-ttl" style="margin-top:26px"><h2><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h2></div>
  <?php foreach ($posts as $p): ?>
  <article class="st-row<?= $p['image'] ? '' : ' noart' ?>">
    <div>
      <span class="st-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
      <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      <?php if ($p['lede']): ?><p><?= e($p['lede']) ?></p><?php endif; ?>
      <span class="meta"><?= dateline($p) ?></span>
    </div>
    <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
  </article>
  <?php endforeach; ?>
  <?php if (!$posts): ?>
  <div class="st-empty" style="margin-top:8px">Nothing on this desk yet — the newsroom is on it.</div>
  <?php endif; ?>
  <?php if (($pages ?? 1) > 1 && ($page ?? 1) < $pages): ?>
  <div class="st-more"><a class="st-btn" href="<?= e(url('desk/' . $cat['slug'])) ?>?page=<?= (int) $page + 1 ?>">More <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a></div>
  <?php endif; ?>
</div>
