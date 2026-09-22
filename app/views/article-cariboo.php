<?php /* Cariboo Compass — article (foundation build; $post resolved). */ ?>
<article class="cc-article">
  <span class="cc-kick"><?= e($post['category_name'] ?: 'News') ?></span>
  <h1><?= e($post['title']) ?></h1>
  <?php if ($post['lede']): ?><p class="standfirst"><?= e($post['lede']) ?></p><?php endif; ?>
  <p class="cc-byline"><?= dateline($post) ?></p>
  <?= story_photo($post, true) ?>
  <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
  <?php $ccRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
  <?php if ($ccRel): ?>
  <div class="cc-related">
    <h2>More bearings</h2>
    <?php foreach ($ccRel as $p): ?>
    <div class="cc-card"><h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</article>
