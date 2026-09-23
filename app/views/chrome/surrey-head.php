<?php
/* The Surrey Standard — masthead chrome (brand build, Sep 2026). In
   scope from page_header(): $siteTitle, $tagline, $activeDesk. A light
   utility strip over the navy masthead band: reversed leaf-S monogram,
   Playfair lockup with an italic "The", lime tagline, nav inside the
   band. */
$ssWords = preg_split('/\s+/', trim($siteTitle)) ?: [];
$ssThe   = (strcasecmp($ssWords[0] ?? '', 'the') === 0) ? array_shift($ssWords) : '';
$ssName  = implode(' ', $ssWords) ?: $siteTitle;
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
      <?php if ($ssMail !== ''): ?><a href="mailto:<?= e($ssMail) ?>">News tips</a><span class="sep">|</span><?php endif; ?>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'Stay in the Know')) ?></a><span class="sep">|</span>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="ss-mast">
  <div class="top">
    <a class="ss-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="" width="38" height="48">
      <span class="name"><?php if ($ssThe !== ''): ?><span class="the"><?= e($ssThe) ?></span><?php endif; ?><?= e($ssName) ?></span>
    </a>
    <span class="ss-tag"><?= e($tagline) ?></span>
  </div>
  <nav class="ss-nav" aria-label="Sections">
    <div class="in">
      <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Front Page</a>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('search')) ?>">Search</a>
    </div>
  </nav>
</header>
<main id="content">
