<?php
/* Le Memphré — masthead chrome. FOUNDATION build (Nov 2026): Toronto
   Telegraph's masthead, unbranded. In scope from page_header():
   $siteTitle, $tagline, $activeDesk. The placeholder roundel and the
   two-line wordmark wait for the brand package. */
?>
<div class="me-util">
  <div class="in">
    <div>
      <span><?= e(pp_date_full()) ?></span>
      <span class="sep">|</span><span><?= e($tagline) ?></span>
    </div>
    <div>
      <a href="<?= e(url('tips')) ?>"><?= e(pp_t('Send a tip')) ?></a><span class="sep">|</span>
      <a href="/admin/"><?= e(pp_t('Sign in')) ?></a>
    </div>
  </div>
</div>
<header class="me-head">
  <div class="in">
    <a class="me-lock" href="/" aria-label="<?= e($siteTitle) ?> — page d’accueil">
      <img src="<?= e(site_asset('mark.svg')) ?>" alt="">
      <span class="me-name"><span class="top">Le</span><span class="mid">Memphré</span><span class="sub"><?= e($tagline) ?></span></span>
    </a>
    <nav class="me-nav" aria-label="<?= e(pp_t('Sections')) ?>">
      <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>><?= e(pp_t('Home')) ?></a>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('search')) ?>"><?= e(pp_t('Search')) ?></a>
      <a class="me-sub-btn" href="<?= e(url('newsletter/')) ?>"><?= e(pp_t('Subscribe')) ?></a>
    </nav>
  </div>
</header>
<main id="content">
