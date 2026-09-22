<?php /* Surrey Standard — article (foundation build; $post resolved). */ ?>
<article class="ss-article">
  <span class="ss-kick"><?= e($post['category_name'] ?: 'News') ?></span>
  <h1><?= e($post['title']) ?></h1>
  <?php if ($post['lede']): ?><p class="standfirst"><?= e($post['lede']) ?></p><?php endif; ?>
  <p class="ss-byline"><?= dateline($post) ?></p>
  <?= story_photo($post, true) ?>
  <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
  <?php $ssRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
  <?php if ($ssRel): ?>
  <div class="ss-related">
    <h2>More from the Standard</h2>
    <?php foreach ($ssRel as $p): ?>
    <div class="ss-card"><h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</article>
