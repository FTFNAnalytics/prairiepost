<?php
/* Les Chutes — masthead chrome. Toronto Telegraph's masthead in the
   owner's brand package (28 Nov 2026): the waterfall (mark.svg, traced
   from the package's "logo principal") and the wordmark set in
   Newsreader over the tagline, as the package's homepage mock-up sets
   them. In scope from page_header(): $siteTitle, $tagline, $activeDesk.
   The breaking strip is the package's gold and shows only while
   pp_breaking_live() holds (app/freshness.php); its label is
   "Dernière heure" in the served HTML, uppercased by CSS. */
?>
<div class="ch-util">
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
<header class="ch-head">
  <div class="in">
    <a class="ch-lock" href="/" aria-label="<?= e($siteTitle) ?> — page d’accueil">
      <img class="mk" src="<?= e(site_asset('mark.svg')) ?>" alt="" width="77" height="60">
      <span class="ch-name"><span class="wm">Les Chutes</span><span class="sub"><?= e($tagline) ?></span></span>
    </a>
    <nav class="ch-nav" aria-label="<?= e(pp_t('Sections')) ?>">
      <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>><?= e(pp_t('Home')) ?></a>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('search')) ?>"><?= e(pp_t('Search')) ?></a>
      <a class="ch-sub-btn" href="<?= e(url('newsletter/')) ?>"><?= e(pp_t('Subscribe')) ?></a>
    </nav>
  </div>
</header>
<?php if (pp_breaking_live()): ?>
<div class="ch-brk">
  <div class="in">
    <span class="lbl"><?= e(pp_t('Breaking')) ?></span>
    <a href="<?= e(setting('breaking_url')) ?>"><?= e(setting('breaking_label')) ?></a>
  </div>
</div>
<?php endif; ?>
<main id="content">
