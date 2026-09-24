<?php
/* The Rideau Review — article (brand build; $post resolved). Crimson
   section label over a Baskerville headline; the lede at 20px; crimson
   in-copy links and the crimson-rule blockquote come from the
   stylesheet. */ ?>
<article class="rr-article">
  <div class="rr-seclabel"><?= e(pp_desk_label((string) $post['category_slug'], (string) ($post['category_name'] ?: 'News'))) ?></div>
  <h1><?= e($post['title']) ?></h1>
  <?php if ($post['lede']): ?><p class="rr-lede"><?= e($post['lede']) ?></p><?php endif; ?>
  <p class="rr-byline"><?= dateline($post) ?> &middot; <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $post['body'])) / 220))) ?> min read</p>
  <?= story_photo($post, true) ?>
  <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
</article>
<?php $rrRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
<?php if ($rrRel): ?>
<div class="rr-related">
  <h2 class="rr-railhead">More from the Review</h2>
  <?php foreach ($rrRel as $p): ?>
  <article class="rr-railitem noart">
    <div>
      <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      <div class="rr-meta"><span><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span></div>
    </div>
  </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>
