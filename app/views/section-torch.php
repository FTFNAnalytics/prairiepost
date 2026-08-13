<?php
/**
 * The torch section index (chrome.template = "torch").
 * Included by section.php after page_header(); $cat, $posts, $page and
 * $pages are already resolved.
 *
 * One template serves every section. A short gradient band replaces the
 * photographic masthead — its fill is the section's own colour — and the
 * cards below run a fixed 8 + 4 lead followed by a three-column river.
 */

$tones = pp_chrome('band_tone');
$tone = is_array($tones) ? ($tones[$cat['slug']] ?? 'feature') : 'feature';
$bandClass = in_array($tone, ['coast', 'community', 'inlet'], true) ? ' tt-band--' . $tone : '';
$deskName = pp_desk_label($cat['slug'], $cat['name']);

// The lead: one photo card, then two stacked white cards of equal height.
$lead = $posts[0] ?? null;
$stack = array_slice($posts, 1, 2);
$river = array_slice($posts, 3);

$meta = function (array $p): string {
    $bits = [];
    if (!empty($p['byline'])) {
        $bits[] = $p['byline'];
    }
    if (!empty($p['published_at'])) {
        $bits[] = fmt_date($p['published_at'], 'j M Y');
    }
    return $bits ? e(implode(' · ', $bits)) : '';
};
?>

<div class="tt-band<?= $bandClass ?>">
  <div class="in">
    <h1><?= e($deskName) ?></h1>
    <?php if (!empty($cat['description'])): ?><p><?= e($cat['description']) ?></p><?php endif; ?>
  </div>
</div>

<div class="tt-container tt-main">
  <?php if (!$posts): ?>
  <div class="tt-empty">No stories filed from the <?= e($deskName) ?> desk yet. Try <a href="/">the front page</a> or another section above.</div>
  <?php else: ?>

  <div class="tt-sectionlead">
    <?php if ($lead): ?>
    <?php if ($lead['image']): ?>
    <a class="tt-card tt-card--photo" href="<?= e(url('story/' . $lead['slug'])) ?>" style="min-height:250px">
      <img src="<?= e($lead['image']) ?>" alt="" loading="eager">
      <span class="scrim" aria-hidden="true"></span>
      <span class="pad">
        <?php if ($k = torch_kicker($lead)): ?><span class="kicker"><?= $k ?></span><?php endif; ?>
        <h3 style="font-size:30px;line-height:1.15;letter-spacing:-.015em"><?= e($lead['title']) ?></h3>
        <?php if ($lead['lede']): ?><span class="deck" style="color:rgba(255,255,255,.85)"><?= e(excerpt($lead['lede'], 130)) ?></span><?php endif; ?>
      </span>
    </a>
    <?php else: ?>
    <a class="tt-card tt-card--feature" href="<?= e(url('story/' . $lead['slug'])) ?>" style="min-height:250px">
      <span class="pad">
        <?php if ($k = torch_kicker($lead)): ?><span class="kicker"><?= $k ?></span><?php endif; ?>
        <h2 style="font-size:30px"><?= e($lead['title']) ?></h2>
        <?php if ($lead['lede']): ?><span class="deck"><?= e(excerpt($lead['lede'], 140)) ?></span><?php endif; ?>
      </span>
    </a>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($stack): ?>
    <div class="stack">
      <?php foreach ($stack as $p): ?>
      <a class="tt-card tt-card--standard" href="<?= e(url('story/' . $p['slug'])) ?>">
        <span class="pad">
          <?php if ($k = torch_kicker($p)): ?><span class="kicker"><?= $k ?></span><?php endif; ?>
          <h3 style="font-size:18px"><?= e($p['title']) ?></h3>
          <?php if ($m = $meta($p)): ?><span class="meta"><?= $m ?></span><?php endif; ?>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($river): ?>
  <div class="tt-river">
    <?php foreach ($river as $p): ?>
    <a class="tt-card tt-card--standard" href="<?= e(url('story/' . $p['slug'])) ?>">
      <?php if ($p['image']): ?>
      <span class="ph"><img src="<?= e($p['image']) ?>" alt="" loading="lazy"></span>
      <?php endif; ?>
      <span class="pad">
        <?php if ($k = torch_kicker($p)): ?><span class="kicker"><?= $k ?></span><?php endif; ?>
        <h3><?= e($p['title']) ?></h3>
        <?php if ($p['lede']): ?><span class="deck"><?= e(excerpt($p['lede'], 110)) ?></span><?php endif; ?>
        <?php if ($m = $meta($p)): ?><span class="meta"><?= $m ?></span><?php endif; ?>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($pages > 1): ?>
  <nav class="tt-pagination" aria-label="Pages">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?><span aria-current="page"><?= $i ?></span>
      <?php else: ?><a href="<?= e(url('desk/' . $cat['slug']) . '?page=' . $i) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>
