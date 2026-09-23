<?php /* The Cariboo Compass — section front (brand build; $cat, $posts,
         $page, $pages resolved by section.php). */ ?>
<div class="cc-wrap cc-section">
  <header class="cc-sechead">
    <h1><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h1>
    <?php if (!empty($cat['description'])): ?><p><?= e($cat['description']) ?></p><?php endif; ?>
  </header>
  <?php foreach ($posts as $p): ?>
  <div class="cc-row">
    <span class="cc-kick<?= ($p['category_slug'] ?? '') === 'environment' ? ' cc-kick--gold' : '' ?>" style="font-size:10px;padding:2px 8px"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
    <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
    <?php if ($p['lede']): ?><p><?= e($p['lede']) ?></p><?php endif; ?>
    <p class="cc-byline"><?= dateline($p) ?></p>
  </div>
  <?php endforeach; ?>
  <?php if (!$posts): ?>
  <div class="cc-empty" style="margin-top:16px">Nothing on this desk yet — the newsroom is on it.</div>
  <?php endif; ?>
  <?php if (($pages ?? 1) > 1 && ($page ?? 1) < $pages): ?>
  <div class="cc-more"><a class="cc-btn" href="<?= e(url('desk/' . $cat['slug'])) ?>?page=<?= (int) $page + 1 ?>">More <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a></div>
  <?php endif; ?>
</div>
