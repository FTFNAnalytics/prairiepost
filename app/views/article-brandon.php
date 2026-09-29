<?php
/* The Brandon Bulletin — article (brand build; $post resolved). Deep
   gold kicker over a bold sans headline; Inter body; the light-wheat
   gold-ruled blockquote and deep-gold links come from the
   stylesheet. */ ?>
<div class="bdn-wrap">
  <article class="bdn-article">
    <span class="bdn-kick"><?= e(pp_desk_label((string) $post['category_slug'], (string) ($post['category_name'] ?: 'Local News'))) ?></span>
    <h1><?= e($post['title']) ?></h1>
    <?php if ($post['lede']): ?><p class="lede"><?= e($post['lede']) ?></p><?php endif; ?>
    <p class="bdn-byline"><?= dateline($post) ?> &middot; <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $post['body'])) / 220))) ?> min read</p>
    <?= story_photo($post, true) ?>
    <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
  </article>
  <?php $bdnRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
  <?php if ($bdnRel): ?>
  <div class="bdn-related">
    <div class="bdn-ttl"><h2>More from the Bulletin</h2><span class="star">✦</span></div>
    <?php foreach ($bdnRel as $p): ?>
    <article class="bdn-row noart">
      <div>
        <span class="bdn-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
