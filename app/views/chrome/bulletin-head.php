<div class="bb-util">
  <div class="wrap">
    <div class="grp">
      <span><?= e(date('l, F j, Y')) ?><?= setting('weather_line') !== '' ? ' · ' . e(setting('weather_line')) : '' ?></span>
    </div>
    <div class="grp">
      <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
      <a href="/admin/">Sign in</a>
      <a class="bb-btn" href="<?= e(url('subscribe')) ?>">Subscribe</a>
    </div>
  </div>
</div>
<header class="bb-mast">
  <div class="wrap">
    <a class="brand" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark.svg')) ?>" alt="">
      <span class="plate"><?= e(preg_replace('/^The\s+/i', '', $siteTitle)) ?></span>
    </a>
    <span></span>
    <div class="vol">
      <?= e(pp_chrome('mast_note') ?: 'Independent · reader-funded') ?><br>
      <?= e($tagline) ?>
    </div>
  </div>
</header>
<nav class="bb-nav" aria-label="Desks">
  <div class="wrap">
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a<?= $cat['slug'] === 'opinion' ? ' class="op"' : '' ?> href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
    <a class="live" href="<?= e(setting('breaking_url')) ?>">
      <span class="dot"></span><span class="l">Live</span>
      <span class="t"><?= e(setting('breaking_label')) ?></span>
    </a>
    <?php else: ?>
    <a class="live" href="<?= e(url('search')) ?>" style="text-decoration:none"><span class="t" style="font-weight:400;color:var(--bb-muted)">Search</span></a>
    <?php endif; ?>
  </div>
</nav>
