<?php
/* Le Memphré — masthead chrome. Toronto Telegraph's masthead in the
   owner's brand guidelines (Nov 2026): the lake serpent (mark.svg, traced
   from the logo), the wordmark set in Playfair Display, the logo's
   three-line wave (waves.svg) and the tagline. In scope from
   page_header(): $siteTitle, $tagline, $activeDesk. The breaking strip
   is Teal under ink and shows only while pp_breaking_live() holds
   (app/freshness.php); its label is "Dernière heure" in the served HTML,
   uppercased by CSS. */
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
      <img class="mk" src="<?= e(site_asset('mark.svg')) ?>" alt="" width="98" height="90">
      <span class="me-name"><span class="wm">Le Memphré</span><img class="wv" src="<?= e(site_asset('waves.svg')) ?>" alt="" width="110" height="28"><span class="sub"><?= e($tagline) ?></span></span>
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
<?php if (pp_breaking_live()): ?>
<div class="me-brk">
  <div class="in">
    <span class="lbl"><?= e(pp_t('Breaking')) ?></span>
    <a href="<?= e(setting('breaking_url')) ?>"><?= e(setting('breaking_label')) ?></a>
  </div>
</div>
<?php endif; ?>
<main id="content">
