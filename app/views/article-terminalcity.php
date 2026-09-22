<?php /* Terminal City Times — article (foundation build; $post resolved).
         The drop cap comes from the stylesheet, not the markup. */ ?>
<article class="tc-article">
  <span class="tc-kick"><?= e($post['category_name'] ?: 'News') ?></span>
  <h1><?= e($post['title']) ?></h1>
  <?php if ($post['lede']): ?><p class="standfirst"><?= e($post['lede']) ?></p><?php endif; ?>
  <p class="tc-byline"><?= dateline($post) ?></p>
  <?= story_photo($post, true) ?>
  <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
  <?php $tcRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
  <?php if ($tcRel): ?>
  <div class="tc-related">
    <h2>Also in the Times</h2>
    <?php foreach ($tcRel as $p): ?>
    <div class="tc-card"><h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</article>
