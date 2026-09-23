<?php
/* The Cariboo Compass — masthead chrome (brand build, Sep 2026). In
   scope from page_header(): $siteTitle, $tagline, $activeDesk. White
   masthead: the compass-rose mark beside "The Cariboo" in Deep Forest
   Green with "Compass" in gold, the caps subline under it, then the
   deep green nav band with the gold active underline. */
$ccWords = preg_split('/\s+/', trim($siteTitle)) ?: [];
$ccLast  = array_pop($ccWords) ?: $siteTitle;    // Compass
$ccFirst = implode(' ', $ccWords);               // The Cariboo
$ccWeather = trim(setting('weather_line'));
$ccMail = trim(setting('contact_email'));
?>
<div class="cc-util">
  <div class="in">
    <div>
      <span><?= e(date('l, F j, Y')) ?></span>
      <?php if ($ccWeather !== ''): ?><span class="sep">|</span><span><?= e(str_replace('|', ' · ', $ccWeather)) ?></span><?php endif; ?>
    </div>
    <div>
      <?php if ($ccMail !== ''): ?><a href="mailto:<?= e($ccMail) ?>">News tips</a><span class="sep">|</span><?php endif; ?>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Morning Bearing')) ?></a><span class="sep">|</span>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="cc-mast">
  <a class="cc-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
    <img src="<?= e(site_asset('mark.svg')) ?>" alt="" width="52" height="52">
    <span>
      <span class="name"><?= $ccFirst !== '' ? e($ccFirst) . ' ' : '' ?><span class="gold"><?= e($ccLast) ?></span></span>
      <span class="cc-tag"><?= e($tagline) ?></span>
    </span>
  </a>
</header>
<nav class="cc-nav" aria-label="Sections">
  <div class="in">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(url('search')) ?>">Search</a>
  </div>
</nav>
<main id="content">
