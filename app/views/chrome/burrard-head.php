<?php
/* The Burrard Brief — masthead chrome (foundation build). In scope from
   page_header(): $siteTitle, $tagline, $activeDesk. The lockup splits
   "The Burrard" from "Brief"; the brand package replaces the mark only. */
$bbWords = preg_split('/\s+/', trim($siteTitle)) ?: [];
$bbName  = array_pop($bbWords) ?: $siteTitle;   // Brief
$bbThe   = implode(' ', $bbWords);              // The Burrard
$bbWeather = trim(setting('weather_line'));
$bbMail = trim(setting('contact_email'));
?>
<div class="bb-util">
  <div class="in">
    <div>
      <span class="bb-mono"><?= e(date('D, j M Y')) ?></span>
      <?php if ($bbWeather !== ''): ?><span class="sep">|</span><span><?= e(str_replace('|', ' · ', $bbWeather)) ?></span><?php endif; ?>
    </div>
    <div>
      <?php if ($bbMail !== ''): ?><a href="mailto:<?= e($bbMail) ?>">Send a tip</a><span class="sep">|</span><?php endif; ?>
      <a href="<?= e(url('newsletter/')) ?>">Get the brief by email</a><span class="sep">|</span>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="bb-mast">
  <div class="in">
    <a class="bb-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark.svg')) ?>" alt="" width="40" height="40">
      <span>
        <?php if ($bbThe !== ''): ?><span class="the"><?= e($bbThe) ?></span><?php endif; ?>
        <span class="name"><em><?= e($bbName) ?></em></span>
      </span>
    </a>
    <span class="bb-tag"><?= e($tagline) ?></span>
  </div>
</header>
<nav class="bb-nav" aria-label="Sections">
  <div class="in">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<main id="content">
