<?php $mastWords = preg_split('/\s+/', trim($siteTitle), 2); ?>
<div class="cu-util">
  <div class="wrap">
    <div class="grp">
      <strong><?= e(date('l, F j')) ?></strong>
      <?php if (setting('weather_line') !== ''): ?><span><?= e(setting('weather_line')) ?></span><?php endif; ?>
    </div>
    <div class="grp">
      <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
      <a href="<?= e(url('subscribe')) ?>">Support local journalism</a>
      <strong><a href="/admin/" style="color:#fff;text-decoration:none">Sign in</a></strong>
    </div>
  </div>
</div>
<header class="cu-mast">
  <div class="wrap">
    <div class="side"><?= nl2br(e(pp_chrome('mast_left') ?: '')) ?></div>
    <a class="lockup" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <span class="l1"><?= e(strtoupper($mastWords[0])) ?></span>
      <span class="l2"><?= e($mastWords[1] ?? '') ?></span>
      <svg class="wave" viewBox="0 0 300 14" aria-hidden="true"><path d="M4 8c48-5 96-5 146 0s96 5 146 0" fill="none" stroke="#2A8C86" stroke-width="3" stroke-linecap="round"/></svg>
      <span class="l3"><?= e($tagline) ?></span>
    </a>
    <div class="side"><?= nl2br(e(pp_chrome('mast_right') ?: '')) ?></div>
  </div>
</header>
<nav class="cu-nav" aria-label="Desks">
  <div class="wrap">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Latest</a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <a class="sp" href="<?= e(url('search')) ?>" aria-label="Search the archive">Search</a>
  </div>
</nav>
<?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
<div class="cu-strip<?= pp_chrome('strip_tone') === 'teal' ? ' cu-strip--teal' : '' ?>">
  <div class="wrap">
    <span class="lab">The Current</span>
    <span class="cp"><a href="<?= e(setting('breaking_url')) ?>"><?= e(setting('breaking_label')) ?></a></span>
    <a class="go" href="<?= e(setting('breaking_url')) ?>">Read more</a>
  </div>
</div>
<?php endif; ?>
