<?php
/* The Rideau Review — section front (brand build; $cat, $posts, $page,
   $pages resolved by section.php). The prototype's shape: a light
   48px Baskerville title over a hairline, then rows. Desk
   descriptions are shared network-wide, so this template does not
   print them — the desk name is the page. */ ?>
<div class="rr-wrap rr-section">
  <h1><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h1>
  <hr class="rr-sectionrule">
  <?php foreach ($posts as $p): ?>
  <article class="rr-row<?= $p['image'] ? '' : ' noart' ?>">
    <div>
      <span class="rr-kicker"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
      <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      <?php if ($p['lede']): ?><p><?= e($p['lede']) ?></p><?php endif; ?>
      <div class="rr-meta"><span><?= dateline($p) ?></span></div>
    </div>
    <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
  </article>
  <?php endforeach; ?>
  <?php if (!$posts): ?>
  <div class="rr-empty" style="margin-top:16px">Nothing on this desk yet — the newsroom is on it.</div>
  <?php endif; ?>
  <?php if (($pages ?? 1) > 1 && ($page ?? 1) < $pages): ?>
  <div class="rr-more"><a class="rr-btn-sub" href="<?= e(url('desk/' . $cat['slug'])) ?>?page=<?= (int) $page + 1 ?>">More <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a></div>
  <?php endif; ?>
</div>
