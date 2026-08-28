<?php
/* The London Lookout — masthead chrome (site canvas: utility strip, indigo
   band, section nav). In scope from page_header(): $siteTitle, $tagline,
   $activeDesk. The wordmark splits "The London" from "LOOKOUT" per brand §01;
   the weather line is settings-driven and simply absent until the newsroom
   sets it. */
$llWords = preg_split('/\s+/', trim($siteTitle)) ?: [];
$llName  = array_pop($llWords) ?: $siteTitle;      // LOOKOUT
$llThe   = implode(' ', $llWords);                  // The London
$llWeather = trim(setting('weather_line'));
$llMail = trim(setting('contact_email'));
?>
<div class="ll-util">
  <div class="in">
    <div class="grp">
      <span><?= e(date('l, j F Y')) ?></span>
      <?php if ($llWeather !== ''): ?>
      <span class="sep">|</span><span><?= e(str_replace('|', ' · ', $llWeather)) ?></span>
      <?php endif; ?>
      <span class="sep">|</span>
      <a class="digest" href="<?= e(url('newsletter/')) ?>">Get the 7am digest</a>
    </div>
    <div class="grp">
      <?php if ($llMail !== ''): ?><a href="mailto:<?= e($llMail) ?>">Send a tip</a><?php endif; ?>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="ll-mast">
  <div class="in">
    <a class="ll-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="" width="34" height="34">
      <span>
        <?php if ($llThe !== ''): ?><span class="the"><?= e($llThe) ?></span><?php endif; ?>
        <span class="name"><?= e($llName) ?></span>
      </span>
    </a>
    <div class="tools">
      <a class="ll-search" href="<?= e(url('search')) ?>">
        <svg width="14" height="14" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M229.66,218.34l-50.07-50.06a88.11,88.11,0,1,0-11.31,11.31l50.06,50.07a8,8,0,0,0,11.32-11.32ZM40,112a72,72,0,1,1,72,72A72.08,72.08,0,0,1,40,112Z"></path></svg>
        Search the Lookout
      </a>
      <a class="ll-member" href="<?= e(url('newsletter/')) ?>">Become a member</a>
    </div>
  </div>
</header>
<nav class="ll-nav" aria-label="Sections">
  <div class="in">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
    <a class="end" href="<?= e(url('corrections')) ?>">Corrections</a>
  </div>
</nav>
<main id="content">
