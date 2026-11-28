<?php
/* Borealis Bulletin — article ($post resolved). Toronto Telegraph's
   article in the owner's brand package (28 Nov 2026), which shipped
   this template:
   desk chip kicker over bold Archivo headline, Polar Midnight ruled
   mono byline strip, Source Serif 4 body, Aurora-ruled Frost Mist
   blockquotes, and More from the Bulletin related dispatches. */
$boChip = static function (array $p): string {
    $slug = (string) ($p['category_slug'] ?? '');
    if ($slug === 'opinion') {
        return 'bo-chip bo-chip--opinion';
    }
    if (in_array($slug, ['local-news', 'environment', 'communities'], true)) {
        return 'bo-chip bo-chip--pine';
    }
    return 'bo-chip';
};
?>
<div class="bo-wrap">
  <article class="bo-article">
    <span class="<?= $boChip($post) ?>"><?= e(pp_desk_label((string) $post['category_slug'], (string) ($post['category_name'] ?: 'Territories'))) ?></span>
    <h1><?= e($post['title']) ?></h1>
    <?php if ($post['lede']): ?><p class="lede"><?= e($post['lede']) ?></p><?php endif; ?>
    <p class="bo-byline"><?= dateline($post) ?> &middot; <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $post['body'])) / 220))) ?> <?= e(pp_t('min read')) ?></p>
    <?= story_photo($post, true) ?>
    <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
    <?= pp_article_disclosure($post) ?>
  </article>
  <?php $boRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
  <?php if ($boRel): ?>
  <div class="bo-related">
    <div class="bo-ttl"><span class="star" aria-hidden="true">&#10022;</span><h2>More from the Bulletin</h2></div>
    <?php foreach ($boRel as $p): ?>
    <article class="bo-row noart">
      <div>
        <span class="<?= $boChip($p) ?>"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
