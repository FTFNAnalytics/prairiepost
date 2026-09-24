<?php
/* The Rideau Review — masthead chrome (brand build, Sep 2026). In scope
   from page_header(): $siteTitle, $tagline, $activeDesk. Warm paper
   ground; the lock-and-leaf mark BEFORE the nameplate (no trailing
   maple — brand rule); outlined subscribe, never the solid brick; the
   active desk carries the crimson underline; ONE crimson rule under
   the nav. */
$rrWeather = trim(setting('weather_line'));
$rrMail = trim(setting('contact_email'));
?>
<div class="rr-util">
  <div class="in">
    <div>
      <span><?= e(date('l, F j, Y')) ?></span>
      <?php if ($rrWeather !== ''): ?><span class="sep">|</span><span><?= e(str_replace('|', ' · ', $rrWeather)) ?></span><?php endif; ?>
    </div>
    <div>
      <?php if ($rrMail !== ''): ?><a href="mailto:<?= e($rrMail) ?>">News tips</a><span class="sep">|</span><?php endif; ?>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="rr-header">
  <div class="rr-wrap rr-top">
    <div class="rr-tools"><span>Ottawa&nbsp;&middot;&nbsp;Gatineau&nbsp;&middot;&nbsp;Eastern&nbsp;Ontario</span></div>
    <div>
      <a class="rr-nameplate" href="/" aria-label="<?= e($siteTitle) ?> — front page"><img class="lock-mark" src="<?= e(site_asset('mark.svg')) ?>" alt=""> <?= e($siteTitle) ?></a>
      <span class="rr-tag"><?= e($tagline) ?></span>
    </div>
    <div class="rr-tools right">
      <a class="rr-btn-sub" href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Evening Briefing')) ?></a>
    </div>
  </div>
  <nav class="rr-wrap rr-nav" aria-label="Sections">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(url('search')) ?>">Search</a>
  </nav>
  <hr class="rr-rule">
</header>
<main id="content">
