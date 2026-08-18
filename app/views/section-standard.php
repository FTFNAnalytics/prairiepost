<?php
/**
 * The standard section index (chrome.template = "standard").
 * Included by section.php after page_header(); $cat, $posts, $page and
 * $pages are already resolved.
 *
 * A navy band carrying the desk's name and its standing description, then
 * the same white cards on paper grey the front page uses — lead at 16:9,
 * the rest cropped 4:3.
 */

$byline = function (array $p): string {
    $bits = [];
    if (!empty($p['byline'])) {
        $bits[] = 'By ' . $p['byline'];
    }
    if (!empty($p['published_at'])) {
        $bits[] = fmt_date($p['published_at'], 'j F Y');
    }
    $bits[] = read_minutes($p) . ' min read';
    return e(implode(' · ', $bits));
};

$lead = $posts[0] ?? null;
$rest = array_slice($posts, 1);
?>

<div class="sd-band">
  <div class="in">
    <p class="eyebrow">Opinion desk</p>
    <h1><?= e($cat['name']) ?></h1>
    <?php if (!empty($cat['description'])): ?><p><?= e($cat['description']) ?></p><?php endif; ?>
  </div>
</div>

<div class="sd-main">
  <div class="sd-wrap">
    <?php if (!$posts): ?>
    <div class="sd-empty">Nothing filed from <?= e($cat['name']) ?> yet. Try <a href="/">the front page</a> or another section above.</div>
    <?php else: ?>

    <?php if ($lead): ?>
    <a class="sd-card" href="<?= e(url('story/' . $lead['slug'])) ?>" style="margin-bottom:22px">
      <?php if ($lead['image']): ?>
      <span class="ph"><img src="<?= e($lead['image']) ?>" alt="" loading="eager"></span>
      <?php else: ?>
      <span class="noph"><span><?= e($lead['title']) ?></span></span>
      <?php endif; ?>
      <span class="body">
        <span class="sd-kicker sd-kicker--red">Opinion · <?= e($cat['name']) ?></span>
        <h2><?= e($lead['title']) ?></h2>
        <?php if ($lead['lede']): ?><p class="deck"><?= e($lead['lede']) ?></p><?php endif; ?>
        <span class="by"><?= $byline($lead) ?></span>
      </span>
    </a>
    <?php endif; ?>

    <?php if ($rest): ?>
    <div class="sd-river">
      <?php foreach ($rest as $p): ?>
      <a class="sd-card" href="<?= e(url('story/' . $p['slug'])) ?>">
        <?php if ($p['image']): ?>
        <span class="ph"><img src="<?= e($p['image']) ?>" alt="" loading="lazy"></span>
        <?php else: ?>
        <span class="noph"><span><?= e($p['title']) ?></span></span>
        <?php endif; ?>
        <span class="body">
          <span class="sd-kicker">Opinion · <?= e($cat['name']) ?></span>
          <h2><?= e($p['title']) ?></h2>
          <?php if ($p['lede']): ?><p class="deck"><?= e(excerpt($p['lede'], 110)) ?></p><?php endif; ?>
          <span class="by"><?= $byline($p) ?></span>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
    <nav class="sd-pagination" aria-label="Pages">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span aria-current="page"><?= $i ?></span>
        <?php else: ?><a href="<?= e(url('desk/' . $cat['slug']) . '?page=' . $i) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
