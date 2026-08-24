<?php
/**
 * Turtle Island Times — section front (chrome.template = "turtleisland").
 * Included by section.php after page_header(); $cat, $posts, $page and $pages
 * are already resolved.
 *
 * Screen 2b of the package: the section's name replaces the nameplate in the
 * ink block (section.php sets $ppTiPlate before the header renders), the
 * desk's standing description sits under it, then one lead and a river.
 */

$meta = function (array $p): string {
    $bits = [];
    if (!empty($p['byline'])) {
        $bits[] = 'By ' . $p['byline'];
    }
    $bits[] = read_minutes($p) . ' min';
    return e(implode(' · ', $bits));
};

$tile = function (array $p, array $o = []) use ($meta): string {
    $href = e(url('story/' . $p['slug']));
    $kick = $o['kicker'] ?? '';
    $tag = $kick !== '' ? '<span class="ti-tag">' . e($kick) . '</span>' : '';
    $out = '<a class="ti-tile" href="' . $href . '">';
    if (!empty($p['image'])) {
        $out .= '<div class="shot"><img src="' . e($p['image']) . '" alt="" loading="lazy">'
              . '<span class="scrim"><h3>' . e($p['title']) . '</h3></span></div>' . $tag;
    } else {
        $out .= '<div class="shot shot--none"><h3>' . e($p['title']) . '</h3></div>' . $tag;
    }
    if (!empty($o['deck']) && !empty($p['lede'])) {
        $out .= '<p class="dek">' . e(excerpt($p['lede'], (int) $o['deck'])) . '</p>';
    }
    $out .= '<p class="meta">' . $meta($p) . '</p></a>';
    return $out;
};

$lead = $posts[0] ?? null;
$rest = array_slice($posts, 1);
?>

  <?php if (!empty($cat['description'])): ?>
  <p class="ti-secblurb"><?= e($cat['description']) ?></p>
  <?php endif; ?>

  <?php if (!$posts): ?>
  <div class="ti-empty">
    Nothing filed to <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?> yet. Try <a href="/">the front page</a> or another section above.
  </div>
  <?php else: ?>

  <?php if ($lead): ?>
  <div class="ti-lead">
    <a class="shot" href="<?= e(url('story/' . $lead['slug'])) ?>">
      <?php if ($lead['image']): ?>
      <img src="<?= e($lead['image']) ?>" alt="" loading="eager">
      <?php endif; ?>
    </a>
    <div>
      <h1><a style="color:inherit" href="<?= e(url('story/' . $lead['slug'])) ?>"><?= e($lead['title']) ?></a></h1>
      <?php if ($lead['lede']): ?><p class="standfirst"><?= e($lead['lede']) ?></p><?php endif; ?>
      <p class="meta"><?= $meta($lead) ?></p>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($rest): ?>
  <div class="ti-river">
    <?php foreach ($rest as $p): ?>
    <?= $tile($p, ['deck' => 110]) ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($pages > 1): ?>
  <nav class="ti-pag" aria-label="Pages">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?><span aria-current="page"><?= $i ?></span>
      <?php else: ?><a href="<?= e(url('desk/' . $cat['slug']) . '?page=' . $i) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
