<div class="pf-strip">
  <div class="wrap">
    <div class="grp">
      <span><?= e(date('l, F j, Y')) ?><?= setting('weather_line') !== '' ? ' · ' . e(setting('weather_line')) : '' ?></span>
    </div>
    <div class="grp">
      <a class="hide-s" href="<?= e(url('newsletter/')) ?>">Newsletters</a>
      <a class="hide-s" href="/admin/">Sign in</a>
      <a class="pf-btn pf-btn--inlet" href="<?= e(url('subscribe')) ?>">Subscribe</a>
    </div>
  </div>
</div>
<header class="pf-plate">
  <div class="inner">
    <a href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <span class="the">The</span>
      <span class="name">
        <img class="mark" src="<?= e(site_asset('mark.svg')) ?>" alt="">
        <span><?= e(preg_replace('/^The\s+/i', '', $siteTitle)) ?></span>
      </span>
    </a>
  </div>
</header>
<nav class="pf-nav" aria-label="Desks">
  <div class="wrap">
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <a class="sp" href="<?= e(url('search')) ?>">Search</a>
  </div>
</nav>
<?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
<div class="pf-breaking">
  <div class="wrap">
    <span class="b">Breaking</span>
    <a href="<?= e(setting('breaking_url')) ?>"><?= e(setting('breaking_label')) ?></a>
  </div>
</div>
<?php endif; ?>
