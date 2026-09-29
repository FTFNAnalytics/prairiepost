<?php
/* Steeltown Standard — article (brand build; $post resolved). Gold
   kicker over the condensed uppercase headline, gold-ruled byline
   strip, serif body; the camel navy-ruled blockquote comes from the
   stylesheet. */ ?>
<div class="st-wrap">
  <article class="st-article">
    <span class="st-kick"><?= e(pp_desk_label((string) $post['category_slug'], (string) ($post['category_name'] ?: 'Local News'))) ?></span>
    <h1><?= e($post['title']) ?></h1>
    <?php if ($post['lede']): ?><p class="lede"><?= e($post['lede']) ?></p><?php endif; ?>
    <p class="st-byline"><?= dateline($post) ?> &middot; <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $post['body'])) / 220))) ?> min read</p>
    <?= story_photo($post, true) ?>
    <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
  </article>
  <?php $stRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
  <?php if ($stRel): ?>
  <div class="st-related">
    <div class="st-ttl"><h2>More from the Standard</h2></div>
    <?php foreach ($stRel as $p): ?>
    <article class="st-row noart">
      <div>
        <span class="st-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
