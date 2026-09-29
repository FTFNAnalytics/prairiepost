<?php
/* Portage Press — article (brand build; $post resolved). Red category
   label over the navy Inter headline; dark-gray body; red links; the
   Playfair pull-quote treatment (centered, navy, short red rule)
   comes from the stylesheet's blockquote rules. */ ?>
<div class="ptg-wrap">
  <article class="ptg-article">
    <span class="ptg-kick"><?= e(pp_desk_label((string) $post['category_slug'], (string) ($post['category_name'] ?: 'News'))) ?></span>
    <h1><?= e($post['title']) ?></h1>
    <?php if ($post['lede']): ?><p class="lede"><?= e($post['lede']) ?></p><?php endif; ?>
    <p class="ptg-byline"><?= dateline($post) ?> &middot; <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $post['body'])) / 220))) ?> min read</p>
    <?= story_photo($post, true) ?>
    <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
  </article>
  <?php $ptgRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
  <?php if ($ptgRel): ?>
  <div class="ptg-related">
    <div class="ptg-secttl"><h2>More from the Press</h2></div>
    <?php foreach ($ptgRel as $p): ?>
    <article class="ptg-row noart">
      <div>
        <span class="ptg-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
