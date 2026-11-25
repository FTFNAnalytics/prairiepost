<div class="ga-strip">
  <div class="wrap">
    <div class="grp">
      <span><?= e(date('l, F j, Y')) ?></span>
      <?= pp_weather_link(e(pp_weather_text())) ?>
    </div>
    <div class="grp">
      <?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
      <a class="hot" href="<?= e(setting('breaking_url')) ?>"><?= e(setting('breaking_label')) ?></a>
      <?php endif; ?>
      <a href="<?= e(url('tips')) ?>">Send a tip</a>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
    </div>
  </div>
</div>
<header class="ga-head">
  <div class="wrap">
    <a class="logo" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('logo-header.png')) ?>" alt="<?= e($siteTitle) ?>">
    </a>
    <div class="acts">
      <a class="lnk" href="<?= e(url('search')) ?>">Search</a>
      <a class="ga-btn ga-btn--ghost" href="<?= e(url('tips')) ?>">Send a tip</a>
      <a class="ga-btn ga-btn--solid" href="<?= e(url('subscribe')) ?>">Get the newsletter</a>
    </div>
  </div>
</header>
<nav class="ga-nav" aria-label="Desks">
  <div class="wrap">
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
