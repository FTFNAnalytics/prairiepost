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
    <?= standard_card($lead, [
        'kicker' => 'Opinion · ' . $cat['name'], 'red' => true, 'deck' => 200,
        'meta' => $byline($lead), 'eager' => true, 'class' => 'sd-card--wide',
    ]) ?>
    <?php endif; ?>

    <?php if ($rest): ?>
    <div class="sd-river">
      <?php foreach ($rest as $p): ?>
      <?= standard_card($p, ['kicker' => 'Opinion · ' . $cat['name'], 'deck' => 110, 'meta' => $byline($p)]) ?>
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
