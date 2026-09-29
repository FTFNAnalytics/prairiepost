<?php
/* Portage Press — masthead chrome (brand build, Sep 2026). In scope
   from page_header(): $siteTitle, $tagline, $activeDesk. Per the
   owner's package: the Polar Night Blue band carrying the P-pin, the
   two-tone wordmark with the maple, the letterspaced tagline subline,
   and the white nav with the red active underline — over a light
   utility strip. */
$ptgWeather = trim(setting('weather_line'));
$ptgMail = trim(setting('contact_email'));
$ptgWords = preg_split('/\s+/', trim($siteTitle)) ?: [$siteTitle];
$ptgLast = array_pop($ptgWords);
$ptgFirst = implode(' ', $ptgWords);
?>
<div class="ptg-util">
  <div class="in">
    <div>
      <span><?= e(date('l, F j, Y')) ?></span>
      <?php if ($ptgWeather !== ''): ?><span class="sep">|</span><span><?= e(str_replace('|', ' · ', $ptgWeather)) ?> in Winnipeg</span><?php endif; ?>
    </div>
    <div>
      <?php if ($ptgMail !== ''): ?><a href="mailto:<?= e($ptgMail) ?>">News tips</a><span class="sep">|</span><?php endif; ?>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="ptg-bar">
  <div class="in">
    <a class="ptg-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img class="pin" src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
      <span class="ptg-name">
        <span class="top"><?= $ptgFirst !== '' ? e($ptgFirst) . ' ' : '' ?><span class="red"><?= e($ptgLast) ?></span> <img src="<?= e(site_asset('maple.svg')) ?>" alt=""></span>
        <span class="sub"><?= e($tagline) ?></span>
      </span>
    </a>
    <nav class="ptg-nav" aria-label="Sections">
      <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('search')) ?>">Search</a>
    </nav>
  </div>
</header>
<main id="content">
