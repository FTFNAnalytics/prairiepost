<?php
/* Toronto Telegraph — article (brand build; $post resolved). Navy
   chip kicker over the bold sans headline, navy-ruled byline strip,
   classic serif body; the cambridge-ruled blockquote comes from the
   stylesheet. */ ?>
<div class="tg-wrap">
  <article class="tg-article">
    <span class="tg-chip"><?= e(pp_desk_label((string) $post['category_slug'], (string) ($post['category_name'] ?: 'City'))) ?></span>
    <h1><?= e($post['title']) ?></h1>
    <?php if ($post['lede']): ?><p class="lede"><?= e($post['lede']) ?></p><?php endif; ?>
    <p class="tg-byline"><?= dateline($post) ?> &middot; <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $post['body'])) / 220))) ?> min read</p>
    <?= story_photo($post, true) ?>
    <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
  </article>
  <?php $tgRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
  <?php if ($tgRel): ?>
  <div class="tg-related">
    <div class="tg-ttl"><h2>More from the Telegraph</h2></div>
    <?php foreach ($tgRel as $p): ?>
    <article class="tg-row noart">
      <div>
        <span class="tg-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
