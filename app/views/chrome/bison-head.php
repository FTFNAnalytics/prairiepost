<?php
/* The Bison Bulletin — masthead chrome (brand build, Sep 2026). In
   scope from page_header(): $siteTitle, $tagline, $activeDesk. Per the
   owner's package: the solid bison-red bar carrying the reversed
   bison mark, the Montserrat nameplate and the white nav (active desk
   underlined white), over a thin white utility strip. */
$bsnWeather = trim(setting('weather_line'));
$bsnMail = trim(setting('contact_email'));
?>
<div class="bsn-util">
  <div class="in">
    <div>
      <span><?= e(date('l, F j, Y')) ?></span>
      <?php if ($bsnWeather !== ''): ?><span class="sep">|</span><span><?= e(str_replace('|', ' · ', $bsnWeather)) ?></span><?php endif; ?>
      <span class="sep">|</span><span><?= e($tagline) ?></span>
    </div>
    <div>
      <?php if ($bsnMail !== ''): ?><a href="mailto:<?= e($bsnMail) ?>">News tips</a><span class="sep">|</span><?php endif; ?>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="bsn-bar">
  <div class="in">
    <a class="bsn-mark" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
      <span class="bsn-name"><?= e($siteTitle) ?></span>
    </a>
    <nav class="bsn-nav" aria-label="Sections">
      <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('search')) ?>">Search</a>
    </nav>
  </div>
</header>
<main id="content">
