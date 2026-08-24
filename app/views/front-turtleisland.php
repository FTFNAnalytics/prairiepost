<?php
/**
 * Turtle Island Times — front page (chrome.template = "turtleisland").
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * The brand package's home page, in order: the ink block and spot rail (both
 * chrome, in ui.php), then Featured — one story given a large plate and a
 * scrim — then Latest as a river of tiles, then the morning brief.
 *
 * Broadsheet's rules govern the layout: no cards behind the pictures, no
 * dividers between sections, and whitespace doing the organising. Cyan is
 * reserved for links inside body copy; the spot carries the tags.
 */

/** "By Danielle Paul · 6 min" — a byline, or the desk when there isn't one. */
$meta = function (array $p): string {
    $bits = [];
    if (!empty($p['byline'])) {
        $bits[] = 'By ' . $p['byline'];
    }
    $bits[] = read_minutes($p) . ' min';
    return e(implode(' · ', $bits));
};

/**
 * A story tile: the picture, an ink scrim rising from its foot, the headline
 * on top and one spot tag in the corner. Never a white card. Where a story
 * has no picture the tile keeps the ink block rather than borrowing stock art.
 */
$tile = function (array $p, array $o = []) use ($meta): string {
    $href = e(url('story/' . $p['slug']));
    $desk = $p['category_name'] ?? '';
    $tag = $desk !== '' ? '<span class="ti-tag">' . e(pp_desk_label($p['category_slug'] ?? null, $desk)) . '</span>' : '';
    $eager = !empty($o['eager']) ? 'eager' : 'lazy';
    $out = '<a class="ti-tile" href="' . $href . '">';
    if (!empty($p['image'])) {
        $out .= '<div class="shot"><img src="' . e($p['image']) . '" alt="" loading="' . $eager . '">'
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

$shown = $hero ? [(int) $hero['id']] : [];
$river = latest_posts(9, $shown);
?>

  <?= ad_slot('top') ?>

  <?php if ($hero): ?>
  <p class="ti-sechead">Featured</p>
  <div class="ti-lead">
    <a class="shot" href="<?= e(url('story/' . $hero['slug'])) ?>">
      <?php if ($hero['image']): ?>
      <img src="<?= e($hero['image']) ?>" alt="" loading="eager">
      <?php endif; ?>
    </a>
    <div>
      <?php if (!empty($hero['category_name'])): ?>
      <p class="ti-kicker"><?= e(pp_desk_label($hero['category_slug'] ?? null, $hero['category_name'])) ?></p>
      <?php endif; ?>
      <h1><a style="color:inherit" href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
      <?php if ($hero['lede']): ?><p class="standfirst"><?= e($hero['lede']) ?></p><?php endif; ?>
      <p class="meta"><?= $meta($hero) ?></p>
    </div>
  </div>
  <?php else: ?>
  <div class="ti-empty">
    The newsroom hasn't filed yet. Editors sign in at <a href="/admin/">/admin/</a> to publish the first story.
  </div>
  <?php endif; ?>

  <?php if ($river): ?>
  <p class="ti-sechead">Latest</p>
  <div class="ti-river">
    <?php foreach ($river as $p): ?>
    <?= $tile($p, ['deck' => 110]) ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?= ad_slot('rail') ?>

  <section class="ti-brief">
    <h3><?= e(setting('newsletter_heading', 'The morning brief')) ?></h3>
    <?php if (isset($_GET['subscribed'])): ?>
    <p class="done"><?= isset($_GET['confirm']) ? 'Nearly there — check your inbox for the confirmation link.' : 'You\'re on the list.' ?></p>
    <?php else: ?>
    <p><?= e(setting('newsletter_copy', 'One email, weekday mornings, five minutes.')) ?></p>
    <form method="post" action="<?= e(url('subscribe')) ?>">
      <input type="email" name="email" required placeholder="you@example.ca" aria-label="Email address">
      <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
      <button type="submit">Sign up</button>
    </form>
    <?php endif; ?>
  </section>
