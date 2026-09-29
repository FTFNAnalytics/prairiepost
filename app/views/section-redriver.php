<?php
/* The Red River Register — section front (brand build; $cat, $posts,
   $page, $pages resolved by section.php): a light 48px Baskerville
   title over a hairline, then rows. Desk descriptions are shared
   network-wide, so this template does not print them — the desk name
   is the page. */ ?>
<div class="rg-wrap rg-section">
  <h1><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h1>
  <hr class="rg-sectionrule">
  <?php foreach ($posts as $p): ?>
  <article class="rg-row<?= $p['image'] ? '' : ' noart' ?>">
    <div>
      <span class="rg-kicker"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
      <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      <?php if ($p['lede']): ?><p><?= e($p['lede']) ?></p><?php endif; ?>
      <div class="rg-meta"><span><?= dateline($p) ?></span></div>
    </div>
    <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
  </article>
  <?php endforeach; ?>
  <?php if (!$posts): ?>
  <div class="rg-empty" style="margin-top:16px">Nothing on this desk yet — the newsroom is on it.</div>
  <?php endif; ?>
  <?php if (($pages ?? 1) > 1 && ($page ?? 1) < $pages): ?>
  <div class="rg-more"><a class="rg-btn-sub" href="<?= e(url('desk/' . $cat['slug'])) ?>?page=<?= (int) $page + 1 ?>">More <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a></div>
  <?php endif; ?>
</div>
