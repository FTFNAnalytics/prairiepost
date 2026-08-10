<?php
/**
 * The bulletin front page (chrome.template = "bulletin").
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * Lead package + The Brief numbered rail → three-up story row (the
 * third cell alternates to Sports/Opinion) → Around the GTA (stories
 * from the chrome-named band desk, kickered by dateline) → the
 * newsletter close. Newsprint structure: rules, never boxes.
 */

$shown = $hero ? [(int) $hero['id']] : [];
$brief = latest_posts(5, $shown);
$shown = array_merge($shown, array_map('intval', array_column($brief, 'id')));

$catBySlug = [];
foreach (categories_all() as $c) {
    $catBySlug[$c['slug']] = $c;
}
$inCat = function (string $slug, int $n, array $skip) use ($catBySlug): array {
    return isset($catBySlug[$slug]) ? posts_in_category((int) $catBySlug[$slug]['id'], $n, $skip) : [];
};

$chrome = pp_brand_file()['chrome'] ?? [];
$bandDesk = is_string($chrome['gta_desk'] ?? null) && $chrome['gta_desk'] !== '' ? $chrome['gta_desk'] : '';
$bandHead = is_string($chrome['gta_head'] ?? null) && $chrome['gta_head'] !== '' ? $chrome['gta_head'] : 'Around the region';

// Three-up: the next illustrated stories, skipping the band desk.
$row3 = [];
foreach (latest_posts(12, $shown) as $p) {
    if (count($row3) >= 3) {
        break;
    }
    if ($p['category_slug'] === $bandDesk || $p['image'] === '') {
        continue;
    }
    $row3[] = $p;
    $shown[] = (int) $p['id'];
}

// Second row: sports, opinion, and the labelled ad slot.
$sports = $inCat('sports', 1, $shown);
$shown = array_merge($shown, array_map('intval', array_column($sports, 'id')));
$opinion = $inCat('opinion', 1, $shown);
$shown = array_merge($shown, array_map('intval', array_column($opinion, 'id')));

$gta = $bandDesk !== '' ? $inCat($bandDesk, 4, $shown) : [];

$kick = function (array $p): string {
    if (empty($p['category_name'])) {
        return '';
    }
    return '<span class="bb-kicker">' . e($p['category_name']) . '</span>';
};
?>

<div class="bb-main">
  <?= ad_slot('top') ?>

  <section class="bb-lead" aria-label="Lead stories">
    <?php if ($hero): ?>
    <article class="bb-hero">
      <a href="<?= e(url('story/' . $hero['slug'])) ?>">
        <?php if ($hero['image']): ?>
        <div class="ph"><img src="<?= e($hero['image']) ?>" alt="" loading="eager"></div>
        <?php if ($hero['image_caption'] !== ''): ?><p class="cap"><?= e($hero['image_caption']) ?><?= $hero['image_credit'] !== '' ? ' — ' . e($hero['image_credit']) : '' ?></p><?php endif; ?>
        <?php endif; ?>
        <?= $kick($hero) ?>
        <h1><?= e($hero['title']) ?></h1>
        <?php if ($hero['lede']): ?><p class="dek"><?= e($hero['lede']) ?></p><?php endif; ?>
        <p class="bb-by"><b><?= e($hero['byline']) ?></b> · <?= e(time_label($hero['published_at'])) ?></p>
      </a>
    </article>
    <?php else: ?>
    <div class="empty">No stories published yet. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first one.</div>
    <?php endif; ?>

    <aside class="bb-brief bb-rulepair" aria-label="The Brief">
      <div class="bh">
        <span class="t"><?= e(setting('newsletter_heading', 'The Brief')) ?></span>
        <span class="s"><?= e(setting('newsletter_copy') !== '' ? explode('—', setting('newsletter_copy'))[0] : 'Five things, every weekday') ?></span>
      </div>
      <?php $n = 0; foreach ($brief as $p): $n++; ?>
      <a class="it" href="<?= e(url('story/' . $p['slug'])) ?>">
        <span class="no"><?= sprintf('%02d', $n) ?></span>
        <div>
          <h3><?= e($p['title']) ?></h3>
          <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 90)) ?></p><?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </aside>
  </section>

  <?php if ($row3): ?>
  <section class="bb-row3" aria-label="Top stories">
    <?php foreach ($row3 as $p): ?>
    <a class="bb-cell" href="<?= e(url('story/' . $p['slug'])) ?>">
      <div class="ph"><img src="<?= e($p['image']) ?>" alt="" loading="lazy"></div>
      <?= $kick($p) ?>
      <h3><?= e($p['title']) ?></h3>
      <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 130)) ?></p><?php endif; ?>
      <p class="bb-by"><?= e($p['byline']) ?></p>
    </a>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php if ($sports || $opinion): ?>
  <section class="bb-row3" aria-label="Sports and opinion">
    <?php foreach ($sports as $p): ?>
    <a class="bb-cell" href="<?= e(url('story/' . $p['slug'])) ?>">
      <?= $kick($p) ?>
      <h3><?= e($p['title']) ?></h3>
      <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 120)) ?></p><?php endif; ?>
      <p class="bb-by"><?= e($p['byline']) ?></p>
    </a>
    <?php endforeach; ?>
    <?php foreach ($opinion as $p): ?>
    <a class="bb-cell op" href="<?= e(url('story/' . $p['slug'])) ?>">
      <?= $kick($p) ?>
      <h3><?= e($p['title']) ?></h3>
      <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 120)) ?></p><?php endif; ?>
      <p class="bb-by">Column · <?= e($p['byline']) ?></p>
    </a>
    <?php endforeach; ?>
    <div><?= ad_slot('rail') ?></div>
  </section>
  <?php endif; ?>

  <?php if ($gta): ?>
  <section class="bb-gta" aria-label="<?= e($bandHead) ?>">
    <p class="gh"><?= e($bandHead) ?></p>
    <div class="grid">
      <?php foreach ($gta as $p): ?>
      <a href="<?= e(url('story/' . $p['slug'])) ?>">
        <span class="city"><?= e($p['dateline'] !== '' ? $p['dateline'] : ($p['category_name'] ?? '')) ?></span>
        <h3><?= e($p['title']) ?></h3>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="bb-news" aria-label="Newsletter">
    <div class="inner">
      <div>
        <span class="bb-kicker"><?= e(setting('newsletter_heading', 'The Brief')) ?></span>
        <h2>Start the day with the city read in.</h2>
        <p><?= e(setting('newsletter_copy')) ?></p>
      </div>
      <div>
        <?php if (isset($_GET['subscribed'])): ?>
        <p class="done"><?= isset($_GET['confirm']) ? 'Nearly there — check your inbox for the confirmation link.' : "You're on the list." ?></p>
        <?php else: ?>
        <form method="post" action="<?= e(url('subscribe')) ?>">
          <input type="email" name="email" required placeholder="you@example.com" aria-label="Email address">
          <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
          <button class="bb-btn" type="submit">Sign up free</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>
