<?php /* The Burrard Brief — section front (brand build; $cat, $posts,
         $page, $pages resolved by section.php). */ ?>
<div class="bb-wrap bb-section">
  <header class="bb-sechead">
    <h1><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h1>
    <?php if (!empty($cat['description'])): ?><p><?= e($cat['description']) ?></p><?php endif; ?>
  </header>
  <?php foreach ($posts as $p): ?>
  <div class="bb-row">
    <img src="<?= e(bb_art($p)) ?>" alt="">
    <div>
      <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      <?php if ($p['lede']): ?><p><?= e($p['lede']) ?></p><?php endif; ?>
      <p class="bb-byline"><?= dateline($p) ?></p>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$posts): ?>
  <div class="bb-empty" style="margin-top:16px">Nothing on this desk yet — the newsroom is on it, briefly.</div>
  <?php endif; ?>
  <?php if (($pages ?? 1) > 1 && ($page ?? 1) < $pages): ?>
  <div class="bb-more"><a class="bb-btn" href="<?= e(url('desk/' . $cat['slug'])) ?>?page=<?= (int) $page + 1 ?>">More <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a></div>
  <?php endif; ?>
</div>
