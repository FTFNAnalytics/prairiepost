<?php
/* The Red River Register — article (brand build; $post resolved). Clay
   section label over a Baskerville headline; the lede at 20px; clay
   in-copy links and the clay-rule blockquote come from the
   stylesheet. */ ?>
<article class="rg-article">
  <div class="rg-seclabel"><?= e(pp_desk_label((string) $post['category_slug'], (string) ($post['category_name'] ?: 'News'))) ?></div>
  <h1><?= e($post['title']) ?></h1>
  <?php if ($post['lede']): ?><p class="rg-lede"><?= e($post['lede']) ?></p><?php endif; ?>
  <p class="rg-byline"><?= dateline($post) ?> &middot; <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $post['body'])) / 220))) ?> min read</p>
  <?= story_photo($post, true) ?>
  <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
</article>
<?php $rgRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
<?php if ($rgRel): ?>
<div class="rg-related">
  <h2 class="rg-railhead">More from the Register</h2>
  <?php foreach ($rgRel as $p): ?>
  <article class="rg-railitem noart">
    <div>
      <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      <div class="rg-meta"><span><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span></div>
    </div>
  </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>
