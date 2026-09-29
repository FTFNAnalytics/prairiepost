<?php /* The Steeltown Standard — section front (scaffold stub; $cat and $posts are resolved). */ ?>
<div class="wrap">
  <h1><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h1>
  <?php foreach ($posts as $p): ?>
  <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
  <?php endforeach; ?>
</div>
