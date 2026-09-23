<?php /* The Cariboo Compass — article (brand build; $post resolved).
         Gold serif pull quotes; Lake Blue in-copy links come from the
         stylesheet. */ ?>
<article class="cc-article">
  <span class="cc-kick<?= ($post['category_slug'] ?? '') === 'environment' ? ' cc-kick--gold' : '' ?>"><?= e(pp_desk_label((string) $post['category_slug'], (string) ($post['category_name'] ?: 'News'))) ?></span>
  <h1><?= e($post['title']) ?></h1>
  <?php if ($post['lede']): ?><p class="standfirst"><?= e($post['lede']) ?></p><?php endif; ?>
  <p class="cc-byline"><?= dateline($post) ?> · <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $post['body'])) / 220))) ?> min read</p>
  <?= story_photo($post, true) ?>
  <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
  <?php $ccRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
  <?php if ($ccRel): ?>
  <div class="cc-related">
    <h2>More from the Compass</h2>
    <?php foreach ($ccRel as $p): ?>
    <div class="cc-card"><h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</article>
