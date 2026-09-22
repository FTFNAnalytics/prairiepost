<?php /* The Burrard Brief — article (foundation build; $post resolved). */ ?>
<article class="bb-article">
  <span class="bb-kick"><?= e($post['category_name'] ?: 'News') ?></span>
  <h1><?= e($post['title']) ?></h1>
  <?php if ($post['lede']): ?><p class="standfirst"><?= e($post['lede']) ?></p><?php endif; ?>
  <p class="bb-byline"><?= dateline($post) ?></p>
  <?= story_photo($post, true) ?>
  <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
  <?php $bbRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
  <?php if ($bbRel): ?>
  <div class="bb-related">
    <h2>Also in the Brief</h2>
    <?php foreach ($bbRel as $p): ?>
    <div class="bb-card"><h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</article>
