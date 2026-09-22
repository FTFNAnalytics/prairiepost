<?php
/* Surrey Standard — masthead chrome (foundation build). In scope from
   page_header(): $siteTitle, $tagline, $activeDesk. Centred serif
   masthead over a double rule; "Standard" carries the crest red. */
$ssWords = preg_split('/\s+/', trim($siteTitle)) ?: [];
$ssName  = array_pop($ssWords) ?: $siteTitle;   // Standard
$ssCity  = implode(' ', $ssWords);              // Surrey
$ssWeather = trim(setting('weather_line'));
$ssMail = trim(setting('contact_email'));
?>
<div class="ss-util">
  <div class="in">
    <div>
      <span><?= e(date('l, j F Y')) ?></span>
      <?php if ($ssWeather !== ''): ?><span class="sep">|</span><span><?= e(str_replace('|', ' · ', $ssWeather)) ?></span><?php endif; ?>
    </div>
    <div>
      <?php if ($ssMail !== ''): ?><a href="mailto:<?= e($ssMail) ?>">Send a tip</a><span class="sep">|</span><?php endif; ?>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Morning Standard')) ?></a><span class="sep">|</span>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="ss-mast">
  <a class="ss-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
    <img src="<?= e(site_asset('mark.svg')) ?>" alt="" width="44" height="44">
    <span class="name"><?= $ssCity !== '' ? e($ssCity) . ' ' : '' ?><span class="std"><?= e($ssName) ?></span></span>
  </a>
  <span class="ss-tag"><?= e($tagline) ?></span>
</header>
<nav class="ss-nav" aria-label="Sections">
  <div class="in">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Front Page</a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<main id="content">
