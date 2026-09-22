<?php
/* Terminal City Times — masthead chrome (foundation build). In scope
   from page_header(): $siteTitle, $tagline, $activeDesk. Centred
   heritage masthead between rules; the roundel is the placeholder mark. */
$tcWeather = trim(setting('weather_line'));
$tcMail = trim(setting('contact_email'));
?>
<div class="tc-util">
  <div class="in">
    <div>
      <span><?= e(date('l, j F Y')) ?></span>
      <?php if ($tcWeather !== ''): ?><span class="sep">|</span><span><?= e(str_replace('|', ' · ', $tcWeather)) ?></span><?php endif; ?>
    </div>
    <div>
      <?php if ($tcMail !== ''): ?><a href="mailto:<?= e($tcMail) ?>">Send a tip</a><span class="sep">|</span><?php endif; ?>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Morning Departure')) ?></a><span class="sep">|</span>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="tc-mast">
  <a class="tc-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
    <img src="<?= e(site_asset('mark.svg')) ?>" alt="" width="42" height="42">
    <span class="name"><span class="tct">Terminal City</span> Times</span>
  </a>
  <span class="tc-tag"><?= e($tagline) ?></span>
  <div class="tc-rules"></div>
</header>
<nav class="tc-nav" aria-label="Sections">
  <div class="in">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Front Page</a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<main id="content">
