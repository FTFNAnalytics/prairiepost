<div class="ww-strip">
  <div class="wrap">
    <div class="grp">
      <span>Western Canada · <?= e(date('l, j F Y')) ?></span>
    </div>
    <div class="grp">
      <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
      <?php if (setting('contact_email') !== ''): ?>
      <a href="mailto:<?= e(setting('contact_email')) ?>">Submit a tip</a>
      <?php endif; ?>
      <a href="/admin/">Sign in</a>
      <a class="hot" href="<?= e(url('subscribe')) ?>">Subscribe</a>
    </div>
  </div>
</div>
<header class="ww-mast">
  <div class="wrap">
    <a class="brand" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark.svg')) ?>" alt="">
      <span class="wm"><?= e($siteTitle) ?></span>
    </a>
    <div class="wire" aria-hidden="true"><span class="live"></span></div>
    <p class="tagline"><?= e($tagline) ?></p>
  </div>
</header>
<nav class="ww-nav" aria-label="Sections">
  <div class="wrap">
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <?php $wwRegions = setting_json('regions'); if ($wwRegions): ?>
    <span class="div" aria-hidden="true"></span>
    <?php foreach ($wwRegions as $rk => $rl): ?>
    <a class="rg" href="<?= e(url('region/' . $rk)) ?>"><?= e($rl) ?></a>
    <?php endforeach; ?>
    <?php endif; ?>
    <a class="sp" href="<?= e(url('search')) ?>" aria-label="Search the archive">Search</a>
  </div>
</nav>
<?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
<div class="ww-dev">
  <div class="wrap">
    <span class="d">Developing</span>
    <a href="<?= e(setting('breaking_url')) ?>"><?= e(setting('breaking_label')) ?></a>
  </div>
</div>
<?php endif; ?>
