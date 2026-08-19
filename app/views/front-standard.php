<?php
/**
 * The standard front page (chrome.template = "standard").
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * Section 06 of the brand package, in order: a photograph under a navy 55%
 * overlay carrying the desk's standing title, then a 1.55fr / 1fr grid —
 * the lead card beside Latest and The Weekly Standard — and a river of the
 * rest below. Content sits on white, the page sits on paper grey, the
 * chrome is navy.
 *
 * Press Red is rationed: the lead's kicker carries it, and nothing else on
 * the page does.
 */

/** "Opinion · Council" — the piece's nature, then its desk. */
$kicker = function (array $p): string {
    $desk = $p['category_name'] ?? '';
    return $desk !== '' ? 'Opinion · ' . $desk : 'Opinion';
};

/** "By the Editorial Board · 12 min read" */
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

$shown = $hero ? [(int) $hero['id']] : [];
$latest = latest_posts(3, $shown);
$shown = array_merge($shown, array_map('intval', array_column($latest, 'id')));
$river = latest_posts(6, $shown);
?>

<div class="sd-hero">
  <?php $hp = pp_chrome('hero_photo'); if ($hp): ?><img src="<?= e($hp) ?>" alt="" loading="eager"><?php endif; ?>
  <span class="ov" aria-hidden="true"></span>
  <div class="in">
    <p class="eyebrow"><?= e(pp_chrome('hero_eyebrow') ?: 'From the desk') ?></p>
    <h1><?= e(pp_chrome('hero_title') ?: 'Latest from ' . setting('site_title')) ?></h1>
  </div>
</div>

<div class="sd-main">
  <div class="sd-wrap">
    <?= ad_slot('top') ?>

    <div class="sd-grid">
      <div>
        <?php if ($hero): ?>
        <?= standard_card($hero, [
            'kicker' => pp_chrome('lead_kicker') ?: 'The Argument',
            'red' => true, 'deck' => 200, 'meta' => $byline($hero), 'eager' => true,
        ]) ?>
        <?php else: ?>
        <div class="sd-empty">Nothing filed yet. The desk signs in at <a href="/admin/">/admin/</a> and files the first argument.</div>
        <?php endif; ?>
      </div>

      <aside class="sd-rail">
        <?php if ($latest): ?>
        <div class="sd-panel">
          <p class="hd">Latest</p>
          <div class="items">
            <?php foreach ($latest as $p): ?>
            <a class="it" href="<?= e(url('story/' . $p['slug'])) ?>">
              <h3><?= e($p['title']) ?></h3>
              <span class="when"><?= e(time_label($p['published_at'])) ?></span>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="sd-news">
          <h2><?= e(setting('newsletter_heading', 'The Weekly Standard')) ?></h2>
          <?php if (isset($_GET['subscribed'])): ?>
          <p class="done"><?= isset($_GET['confirm']) ? 'Nearly there — check your inbox for the confirmation link.' : 'You\'re on the list.' ?></p>
          <?php else: ?>
          <p><?= e(setting('newsletter_copy', 'One argument, every Thursday morning.')) ?></p>
          <form method="post" action="<?= e(url('subscribe')) ?>">
            <input type="email" name="email" required placeholder="you@example.ca" aria-label="Email address">
            <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
            <button type="submit">Join</button>
          </form>
          <?php endif; ?>
        </div>

        <?= ad_slot('rail') ?>
      </aside>
    </div>

    <?php if ($river): ?>
    <section class="sd-sec" aria-label="More from the desk">
      <div class="sd-sechead">
        <h2>More from the desk</h2>
        <a href="<?= e(url('search')) ?>">The archive</a>
      </div>
      <div class="sd-river">
        <?php foreach ($river as $p): ?>
        <?= standard_card($p, ['kicker' => $kicker($p), 'deck' => 120, 'meta' => $byline($p)]) ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</div>
