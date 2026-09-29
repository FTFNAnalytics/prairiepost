<?php /* The Red River Register — article (scaffold stub; $post is already resolved). */ ?>
<article class="wrap">
  <h1><?= e($post['title']) ?></h1>
  <?php if ($post['lede']): ?><p><em><?= e($post['lede']) ?></em></p><?php endif; ?>
  <?= sanitize_html((string) $post['body']) ?>
</article>
