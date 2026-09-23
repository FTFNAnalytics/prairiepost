<?php /* The Burrard Brief — article (brand build; $post resolved). Teal
         pull quotes; scene art stands in when a story has no photo. */ ?>
<article class="bb-article">
  <span class="bb-kick<?= ($post['category_slug'] ?? '') === 'opinion' ? ' bb-kick--opinion' : '' ?>"><?= e(pp_desk_label((string) $post['category_slug'], (string) ($post['category_name'] ?: 'News'))) ?></span>
  <h1><?= e($post['title']) ?></h1>
  <?php if ($post['lede']): ?><p class="standfirst"><?= e($post['lede']) ?></p><?php endif; ?>
  <p class="bb-byline"><?= dateline($post) ?> · <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $post['body'])) / 220))) ?> min read</p>
  <?php if ($post['image']): ?><?= story_photo($post, true) ?><?php else: ?><img src="<?= e(bb_art($post)) ?>" alt=""><?php endif; ?>
  <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
  <?php $bbRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
  <?php if ($bbRel): ?>
  <div class="bb-related">
    <h2>Related, briefly</h2>
    <?php foreach ($bbRel as $p): ?>
    <div class="bb-card">
      <img src="<?= e(bb_art($p)) ?>" alt="">
      <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</article>
