<?php
/* Borealis Bulletin — article. FOUNDATION build (Nov 2026): Toronto Telegraph's
   article, unbranded ($post resolved). Chip kicker over the bold sans
   headline, ruled byline strip, classic serif body. */
?>
<div class="bo-wrap">
  <article class="bo-article">
    <span class="bo-chip"><?= e(pp_desk_label((string) $post['category_slug'], (string) ($post['category_name'] ?: 'News'))) ?></span>
    <h1><?= e($post['title']) ?></h1>
    <?php if ($post['lede']): ?><p class="lede"><?= e($post['lede']) ?></p><?php endif; ?>
    <p class="bo-byline"><?= dateline($post) ?> &middot; <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $post['body'])) / 220))) ?> <?= e(pp_t('min read')) ?></p>
    <?= story_photo($post, true) ?>
    <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
  </article>
  <?php $boRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
  <?php if ($boRel): ?>
  <div class="bo-related">
    <div class="bo-ttl"><h2>More from the Bulletin</h2></div>
    <?php foreach ($boRel as $p): ?>
    <article class="bo-row noart">
      <div>
        <span class="bo-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
