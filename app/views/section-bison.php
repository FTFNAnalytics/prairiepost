<?php
/* The Bison Bulletin — section front (brand build; $cat, $posts, $page,
   $pages resolved by section.php). The red band names the desk, then
   white story rows. Desk descriptions are shared network-wide, so
   this template does not print them — the desk name is the page. */ ?>
<div class="bsn-wrap bsn-section">
  <div class="bsn-band" style="margin-top:22px"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></div>
  <div class="bsn-rows">
    <?php foreach ($posts as $p): ?>
    <article class="bsn-row<?= $p['image'] ? '' : ' noart' ?>">
      <div>
        <span class="bsn-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <?php if ($p['lede']): ?><p><?= e($p['lede']) ?></p><?php endif; ?>
        <span class="meta"><?= dateline($p) ?></span>
      </div>
      <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
  <?php if (!$posts): ?>
  <div class="bsn-empty" style="margin-top:4px">Nothing on this desk yet — the newsroom is on it.</div>
  <?php endif; ?>
  <?php if (($pages ?? 1) > 1 && ($page ?? 1) < $pages): ?>
  <div class="bsn-more"><a class="bsn-btn" href="<?= e(url('desk/' . $cat['slug'])) ?>?page=<?= (int) $page + 1 ?>">More <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a></div>
  <?php endif; ?>
</div>
