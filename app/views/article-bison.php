<?php
/* The Bison Bulletin — article (brand build; $post resolved). White
   story card on prairie cream; red kicker over a Montserrat headline;
   Open Sans body with red links and the red-rule blockquote from the
   stylesheet. */ ?>
<div class="bsn-wrap bsn-article-wrap">
  <article class="bsn-article">
    <span class="bsn-kick"><?= e(pp_desk_label((string) $post['category_slug'], (string) ($post['category_name'] ?: 'News'))) ?></span>
    <h1><?= e($post['title']) ?></h1>
    <?php if ($post['lede']): ?><p class="lede"><?= e($post['lede']) ?></p><?php endif; ?>
    <p class="bsn-byline"><?= dateline($post) ?> &middot; <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $post['body'])) / 220))) ?> min read</p>
    <?= story_photo($post, true) ?>
    <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
  </article>
  <?php $bsnRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
  <?php if ($bsnRel): ?>
  <div class="bsn-related">
    <div class="bsn-band">More from the Bulletin</div>
    <div class="bsn-rows">
      <?php foreach ($bsnRel as $p): ?>
      <article class="bsn-row noart">
        <div>
          <span class="bsn-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
          <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
