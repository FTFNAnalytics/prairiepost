<?php
/* Le Memphré — article. FOUNDATION build (Nov 2026): Toronto Telegraph's
   article, unbranded ($post resolved). Chip kicker over the bold sans
   headline, ruled byline strip, classic serif body. */
// Bylines in French: the shared dateline() prints "By" and English times.
$meSig = static function (array $p): string {
    $bits = [];
    if (($p['post_type'] ?? '') === 'link' && !empty($p['source_url'])) {
        $src = (string) ($p['source_name'] ?? '');
        if ($src === '') {
            $src = (string) preg_replace('/^www\./', '', (string) parse_url((string) $p['source_url'], PHP_URL_HOST));
        }
        if ($src !== '') {
            $bits[] = $src;
        }
    } else {
        if (!empty($p['dateline'])) {
            $bits[] = mb_strtoupper((string) $p['dateline']);
        }
        if (!empty($p['byline'])) {
            $bits[] = pp_t('By') . ' ' . $p['byline'];
        }
    }
    $ts = !empty($p['published_at']) ? strtotime((string) $p['published_at']) : false;
    if ($ts) {
        $bits[] = pp_date('Y-m-d', $ts) === pp_date('Y-m-d') ? pp_clock($ts) : pp_date_long($ts);
    }
    return implode(' · ', array_map('e', $bits));
};
?>
<div class="me-wrap">
  <article class="me-article">
    <span class="me-chip"><?= e(pp_desk_label((string) $post['category_slug'], (string) ($post['category_name'] ?: 'Actualités'))) ?></span>
    <h1><?= e($post['title']) ?></h1>
    <?php if ($post['lede']): ?><p class="lede"><?= e($post['lede']) ?></p><?php endif; ?>
    <p class="me-byline"><?= $meSig($post) ?> &middot; <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $post['body'])) / 220))) ?> <?= e(pp_t('min read')) ?></p>
    <?= story_photo($post, true) ?>
    <div class="body"><?= sanitize_html((string) $post['body']) ?></div>
  </article>
  <?php $meRel = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
  <?php if ($meRel): ?>
  <div class="me-related">
    <div class="me-ttl"><h2>À lire aussi</h2></div>
    <?php foreach ($meRel as $p): ?>
    <article class="me-row noart">
      <div>
        <span class="me-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
