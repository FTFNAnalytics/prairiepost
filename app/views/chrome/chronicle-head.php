<div class="kc-strip">
  <div class="wrap">
    <div class="grp">
      <span><?= e(date('l, j F Y')) ?><?= setting('weather_line') !== '' ? ' · ' . e(setting('weather_line')) : '' ?></span>
    </div>
    <div class="grp">
      <?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
      <a href="<?= e(setting('breaking_url')) ?>"><strong><?= e(setting('breaking_label')) ?></strong></a>
      <?php endif; ?>
      <?php if (setting('contact_email') !== ''): ?>
      <a href="mailto:<?= e(setting('contact_email')) ?>">Tips line</a>
      <?php endif; ?>
      <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="kc-head">
  <div class="wrap">
    <a class="brand" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('bear-crest.png')) ?>" alt="">
      <span>
        <span class="t1"><?= e($siteTitle) ?></span>
        <span class="t2"><?= e($tagline) ?></span>
      </span>
    </a>
    <div class="acts">
      <a class="kc-btn kc-btn--ghost" href="<?= e(url('corrections')) ?>">Corrections</a>
      <a class="kc-btn kc-btn--mint" href="<?= e(url('subscribe')) ?>">Subscribe</a>
    </div>
  </div>
</header>
<nav class="kc-nav" aria-label="Desks">
  <div class="wrap">
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <a class="sp" href="<?= e(url('search')) ?>">Search</a>
  </div>
</nav>
