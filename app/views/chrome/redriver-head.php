<?php
/* The Red River Register — masthead chrome (brand build, Sep 2026; the
   Rideau chassis as the Register's own identity). In scope from
   page_header(): $siteTitle, $tagline, $activeDesk. Warm paper ground;
   the ledger-and-meander mark BEFORE the nameplate; outlined
   newsletter button, never a solid brick; the active desk carries the
   clay underline; ONE clay rule under the nav. */
$rgWeather = trim(setting('weather_line'));
$rgMail = trim(setting('contact_email'));
?>
<div class="rg-util">
  <div class="in">
    <div>
      <span><?= e(date('l, F j, Y')) ?></span>
      <?php if ($rgWeather !== ''): ?><span class="sep">|</span><span><?= e(str_replace('|', ' · ', $rgWeather)) ?></span><?php endif; ?>
    </div>
    <div>
      <?php if ($rgMail !== ''): ?><a href="mailto:<?= e($rgMail) ?>">News tips</a><span class="sep">|</span><?php endif; ?>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="rg-header">
  <div class="rg-wrap rg-top">
    <div class="rg-tools"><span>Selkirk&nbsp;&middot;&nbsp;Steinbach&nbsp;&middot;&nbsp;The&nbsp;Valley</span></div>
    <div>
      <a class="rg-nameplate" href="/" aria-label="<?= e($siteTitle) ?> — front page"><img class="lock-mark" src="<?= e(site_asset('mark.svg')) ?>" alt=""> <?= e($siteTitle) ?></a>
      <span class="rg-tag"><?= e($tagline) ?></span>
    </div>
    <div class="rg-tools right">
      <a class="rg-btn-sub" href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Morning Record')) ?></a>
    </div>
  </div>
  <nav class="rg-wrap rg-nav" aria-label="Sections">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(url('search')) ?>">Search</a>
  </nav>
  <hr class="rg-rule">
</header>
<main id="content">
