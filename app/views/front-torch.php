<?php
/**
 * The torch front page (chrome.template = "torch").
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * Zones 2–8 of the design package, in the fixed order. Zone 1 (the gradient
 * nav) and zone 9 (the footer) live in ui.php because every page carries them.
 *
 *   2 hero masthead   full bleed, lockup A over the photograph
 *   3 banner card     pulled up 46px — the one element that overlaps another
 *   4 lead row        8 + 4, feature card beside a photo card
 *   5 photo strip     4 + 4 + 4, no body copy: the page's visual breath
 *   6 two-up feature  indigo card, plain photograph, community card
 *   7 briefs          four white cards under a section heading
 *   8 newsletter      indigo gradient card
 *
 * Row order is fixed. Editors change what fills each slot, never the
 * sequence — the rhythm of dark, photographic, dark, white is what makes
 * the page scan.
 */

$img = fn (array $p) => (string) ($p['image'] ?? '');
$photos = fn (array $set) => array_values(array_filter($set, fn ($p) => $p['image'] !== ''));

/** Kicker → headline → deck → meta. Any part may drop; the order never changes. */
$meta = function (array $p): string {
    $bits = [];
    if (!empty($p['byline'])) {
        $bits[] = 'By ' . $p['byline'];
    }
    if (!empty($p['published_at'])) {
        $bits[] = fmt_date($p['published_at'], 'j F Y');
    }
    return $bits ? e(implode(' · ', $bits)) : '';
};

$shown = $hero ? [(int) $hero['id']] : [];

// Zone 4 · the photo card beside the feature.
$leadPhoto = null;
foreach (latest_posts(8, $shown) as $p) {
    if ($p['image'] !== '') {
        $leadPhoto = $p;
        $shown[] = (int) $p['id'];
        break;
    }
}

// Zone 5 · three illustrated stories, no decks.
$strip = [];
foreach (latest_posts(14, $shown) as $p) {
    if (count($strip) >= 3) {
        break;
    }
    if ($p['image'] === '') {
        continue;
    }
    $strip[] = $p;
    $shown[] = (int) $p['id'];
}

// Zone 6 · indigo card, its photograph, and a community story.
$catBySlug = [];
foreach (categories_all() as $c) {
    $catBySlug[$c['slug']] = $c;
}
$fromDesk = function (string $slug, int $n, array $skip) use ($catBySlug): array {
    return isset($catBySlug[$slug]) ? posts_in_category((int) $catBySlug[$slug]['id'], $n, $skip) : [];
};

$twoLead = null;
foreach (latest_posts(10, $shown) as $p) {
    if (($p['category_slug'] ?? '') === 'community') {
        continue;
    }
    $twoLead = $p;
    $shown[] = (int) $p['id'];
    break;
}
$community = $fromDesk('community', 1, $shown);
$community = $community[0] ?? null;
if ($community) {
    $shown[] = (int) $community['id'];
}

// Zone 7 · four briefs. Every brief carries a 3:2 thumbnail, so illustrated
// stories are taken first; unillustrated ones fill any remaining slot.
$briefs = [];
$pool = latest_posts(16, $shown);
foreach ($pool as $p) {
    if (count($briefs) < 4 && $p['image'] !== '') {
        $briefs[] = $p;
        $shown[] = (int) $p['id'];
    }
}
foreach ($pool as $p) {
    if (count($briefs) < 4 && !in_array((int) $p['id'], $shown, true)) {
        $briefs[] = $p;
        $shown[] = (int) $p['id'];
    }
}

$heroPhoto = pp_chrome('hero_photo') ?: site_asset('og-default.png');
$bannerPhoto = pp_chrome('banner_photo') ?: $heroPhoto;

$ttWords = preg_split('/\s+/', trim(setting('site_title', 'Tri Cities Torch')));
$ttLast  = count($ttWords) > 1 ? array_pop($ttWords) : '';
$ttFirst = $ttLast !== '' ? implode(' ', $ttWords) : setting('site_title');
?>

<!-- 2 · hero masthead -->
<div class="tt-hero">
  <img src="<?= e($heroPhoto) ?>" alt="" loading="eager">
  <span class="veil" aria-hidden="true"></span>
  <a class="lock" href="/" aria-label="<?= e(setting('site_title')) ?> — front page">
    <img src="<?= e(site_asset('mark.svg')) ?>" alt="">
    <span class="wm"><b><?= e($ttFirst) ?></b><span><?= e(mb_strtoupper($ttLast)) ?></span></span>
  </a>
</div>

<div class="tt-container tt-main">
  <!-- 3 · banner card, straddling the hero edge -->
  <a class="tt-banner" href="/" aria-label="<?= e(setting('site_title')) ?>">
    <img src="<?= e($bannerPhoto) ?>" alt="">
    <span class="wash" aria-hidden="true"></span>
    <span class="lock">
      <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
      <span class="wm"><b><?= e($ttFirst) ?></b><span><?= e(mb_strtoupper($ttLast)) ?></span></span>
    </span>
  </a>

  <?= ad_slot('top') ?>

  <?php if (!$hero): ?>
  <div class="tt-empty">No stories published yet. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first one.</div>
  <?php else: ?>

  <!-- 4 · lead row, 8 + 4 -->
  <div class="tt-lead">
    <a class="tt-card tt-card--feature" href="<?= e(url('story/' . $hero['slug'])) ?>">
      <span class="pad">
        <?php if ($k = torch_kicker($hero)): ?><span class="kicker"><?= $k ?></span><?php endif; ?>
        <h2><?= e($hero['title']) ?></h2>
        <?php if ($hero['lede']): ?><span class="deck"><?= e($hero['lede']) ?></span><?php endif; ?>
        <?php if ($m = $meta($hero)): ?><span class="meta"><?= $m ?></span><?php endif; ?>
      </span>
    </a>
    <?php if ($leadPhoto): ?>
    <a class="tt-card tt-card--photo" href="<?= e(url('story/' . $leadPhoto['slug'])) ?>">
      <img src="<?= e($leadPhoto['image']) ?>" alt="" loading="eager">
      <span class="scrim" aria-hidden="true"></span>
      <span class="pad">
        <?php if ($k = torch_kicker($leadPhoto)): ?><span class="kicker"><?= $k ?></span><?php endif; ?>
        <h3><?= e($leadPhoto['title']) ?></h3>
      </span>
    </a>
    <?php endif; ?>
  </div>

  <!-- 5 · photo strip -->
  <?php if ($strip): ?>
  <div class="tt-strip">
    <?php foreach ($strip as $p): ?>
    <a class="tt-card tt-card--photo" href="<?= e(url('story/' . $p['slug'])) ?>">
      <img src="<?= e($p['image']) ?>" alt="" loading="lazy">
      <span class="scrim" aria-hidden="true"></span>
      <span class="pad">
        <?php if ($k = torch_kicker($p)): ?><span class="kicker"><?= $k ?></span><?php endif; ?>
        <h3 class="sm"><?= e($p['title']) ?></h3>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- 6 · two-up feature: indigo, a photograph, green -->
  <?php if ($twoLead || $community): ?>
  <div class="tt-twoup">
    <?php if ($twoLead): ?>
    <a class="tt-card tt-card--feature" href="<?= e(url('story/' . $twoLead['slug'])) ?>">
      <span class="pad">
        <?php if ($k = torch_kicker($twoLead)): ?><span class="kicker"><?= $k ?></span><?php endif; ?>
        <h2 style="font-size:24px"><?= e($twoLead['title']) ?></h2>
        <?php if ($twoLead['lede']): ?><span class="deck"><?= e(excerpt($twoLead['lede'], 120)) ?></span><?php endif; ?>
      </span>
    </a>
    <?php // The photograph belongs to the card on its left and links to the same story. ?>
    <a class="tt-photo" href="<?= e(url('story/' . $twoLead['slug'])) ?>" tabindex="-1" aria-hidden="true">
      <img src="<?= e($twoLead['image'] ?: $heroPhoto) ?>" alt="" loading="lazy">
    </a>
    <?php endif; ?>
    <?php if ($community): ?>
    <a class="tt-card tt-card--community" href="<?= e(url('story/' . $community['slug'])) ?>">
      <span class="pad">
        <?php if ($k = torch_kicker($community)): ?><span class="kicker"><?= $k ?></span><?php endif; ?>
        <h3><?= e($community['title']) ?></h3>
        <?php if ($community['lede']): ?><span class="deck"><?= e(excerpt($community['lede'], 110)) ?></span><?php endif; ?>
      </span>
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- 7 · briefs -->
  <?php if ($briefs): ?>
  <div class="tt-sechead">
    <h2>Around the Tri-Cities</h2>
    <a class="tt-link" href="<?= e(url('search')) ?>">More briefs <span class="ar">→</span></a>
  </div>
  <div class="tt-briefs">
    <?php foreach ($briefs as $p): ?>
    <a class="tt-card tt-card--brief" href="<?= e(url('story/' . $p['slug'])) ?>">
      <?php if ($p['image']): ?>
      <span class="ph"><img src="<?= e($p['image']) ?>" alt="" loading="lazy"></span>
      <?php endif; ?>
      <span class="pad">
        <?php if ($k = torch_kicker($p)): ?><span class="kicker"><?= $k ?></span><?php endif; ?>
        <h3><?= e($p['title']) ?></h3>
        <?php if (!empty($p['published_at'])): ?><span class="meta"><?= e(fmt_date($p['published_at'], 'j M')) ?></span><?php endif; ?>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <!-- 8 · newsletter -->
  <div class="tt-news">
    <div>
      <h2><?= e(setting('newsletter_heading', 'The Torch, every weekday at 6am')) ?></h2>
      <p><?= e(setting('newsletter_copy')) ?></p>
    </div>
    <?php if (isset($_GET['subscribed'])): ?>
    <p class="done"><?= isset($_GET['confirm']) ? 'Nearly there — check your inbox for the confirmation link.' : 'You\'re on the list. The next edition lands at 6am.' ?></p>
    <?php else: ?>
    <form method="post" action="<?= e(url('subscribe')) ?>">
      <input type="email" name="email" required placeholder="you@example.com" aria-label="Email address">
      <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
      <button class="tt-btn" type="submit">Sign up</button>
    </form>
    <?php endif; ?>
  </div>

  <?= ad_slot('rail') ?>
</div>
