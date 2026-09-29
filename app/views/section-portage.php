<?php
/* Portage Press — section front (brand build; $cat, $posts, $page,
   $pages resolved by section.php). Navy title with the short red
   rule, then hairlined rows. Desk descriptions are shared
   network-wide, so this template does not print them — the desk name
   is the page. */ ?>
<div class="ptg-wrap ptg-section">
  <div class="ptg-secttl"><h2><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h2></div>
  <?php foreach ($posts as $p): ?>
  <article class="ptg-row<?= $p['image'] ? '' : ' noart' ?>">
    <div>
      <span class="ptg-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
      <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      <?php if ($p['lede']): ?><p><?= e($p['lede']) ?></p><?php endif; ?>
      <span class="meta"><?= dateline($p) ?></span>
    </div>
    <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
  </article>
  <?php endforeach; ?>
  <?php if (!$posts): ?>
  <div class="ptg-empty" style="margin-top:14px">Nothing on this desk yet — the newsroom is on it.</div>
  <?php endif; ?>
  <?php if (($pages ?? 1) > 1 && ($page ?? 1) < $pages): ?>
  <div class="ptg-more"><a class="ptg-btn ptg-btn--outline" href="<?= e(url('desk/' . $cat['slug'])) ?>?page=<?= (int) $page + 1 ?>">More <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a></div>
  <?php endif; ?>
</div>
