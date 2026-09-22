<?php
/* Cariboo Compass — masthead chrome (foundation build). In scope from
   page_header(): $siteTitle, $tagline, $activeDesk. Stacked uppercase
   lockup beside the compass-rose placeholder mark. */
$ccWords = preg_split('/\s+/', trim($siteTitle)) ?: [];
$ccName  = array_pop($ccWords) ?: $siteTitle;   // Compass
$ccFirst = implode(' ', $ccWords);              // Cariboo
$ccWeather = trim(setting('weather_line'));
$ccMail = trim(setting('contact_email'));
?>
<div class="cc-util">
  <div class="in">
    <div>
      <span><?= e(date('l, j F Y')) ?></span>
      <?php if ($ccWeather !== ''): ?><span class="sep">|</span><span><?= e(str_replace('|', ' · ', $ccWeather)) ?></span><?php endif; ?>
    </div>
    <div>
      <?php if ($ccMail !== ''): ?><a href="mailto:<?= e($ccMail) ?>">Send a tip</a><span class="sep">|</span><?php endif; ?>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Morning Bearing')) ?></a><span class="sep">|</span>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="cc-mast">
  <div class="in">
    <a class="cc-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark.svg')) ?>" alt="" width="46" height="46">
      <span class="name"><?= e($ccFirst) ?><span class="co"><?= e($ccName) ?></span></span>
    </a>
    <span class="cc-tag"><?= e($tagline) ?></span>
  </div>
</header>
<nav class="cc-nav" aria-label="Sections">
  <div class="in">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<main id="content">
