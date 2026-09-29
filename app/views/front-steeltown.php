<?php /* The Steeltown Standard — front page (scaffold stub; $hero is already resolved). */ ?>
<div class="wrap">
  <p class="scaffold-note">front-steeltown.php — replace with the design build.</p>
  <?php if ($hero): ?><h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1><?php endif; ?>
  <?php foreach (latest_posts(10, $hero ? [(int) $hero['id']] : []) as $p): ?>
  <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
  <?php endforeach; ?>
</div>
