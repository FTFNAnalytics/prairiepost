<?php
/* Borealis Bulletin — masthead chrome. Toronto Telegraph's masthead in
   the owner's brand package (28 Nov 2026), which shipped this partial:
   the Polar Midnight utility strip with its 60°N+ latitude tag; the white
   masthead with the Polar Compass & Aurora Roundel (mark.svg) beside the
   stacked two-tone wordmark (Borealis in Polar Midnight over Bulletin in
   Boreal Pine, live text) and the tagline; the nav beside it; and the
   Frost Mist corridor bar naming the footprint. In scope from
   page_header(): $siteTitle, $tagline, $activeDesk.

   The wordmark is mixed case in the served HTML ("Borealis",
   "Bulletin") and painted in capitals by CSS, as are the corridor bar
   and the breaking label (lesson 10). The breaking strip is Lichen
   Copper and shows only while pp_breaking_live() holds
   (app/freshness.php). */
?>
<div class="bo-util">
  <div class="in">
    <div>
      <span class="lat">60°N+</span>
      <span class="sep">|</span><span><?= e(pp_date('l, F j, Y')) ?></span>
      <span class="sep">|</span><span><?= e($tagline) ?></span>
    </div>
    <div>
      <a href="<?= e(url('tips')) ?>">News tips</a><span class="sep">|</span>
      <a href="/admin/"><?= e(pp_t('Sign in')) ?></a>
    </div>
  </div>
</div>
<header class="bo-head">
  <div class="in">
    <a class="bo-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark.svg')) ?>" alt="" width="54" height="54">
      <span class="bo-name"><span class="top">Borealis</span><span class="mid">Bulletin</span><span class="sub"><?= e($tagline) ?></span></span>
    </a>
    <nav class="bo-nav" aria-label="<?= e(pp_t('Sections')) ?>">
      <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>><?= e(pp_t('Home')) ?></a>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('search')) ?>"><?= e(pp_t('Search')) ?></a>
      <a class="bo-sub-btn" href="<?= e(url('newsletter/')) ?>"><?= e(pp_t('Subscribe')) ?></a>
    </nav>
  </div>
</header>
<?php if (pp_breaking_live()): ?>
<div class="bo-brk">
  <div class="in">
    <span class="lbl"><?= e(pp_t('Breaking')) ?></span>
    <a href="<?= e(setting('breaking_url')) ?>"><?= e(setting('breaking_label')) ?></a>
  </div>
</div>
<?php endif; ?>
<div class="bo-corridor">
  <div class="in">
    <div class="places">
      <span>Yukon</span><span class="dot">&middot;</span>
      <span>Northwest Territories</span><span class="dot">&middot;</span>
      <span>Nunavut</span><span class="dot">&middot;</span>
      <span>Inuit Nunangat</span><span class="dot">&middot;</span>
      <span>Circumpolar North</span>
    </div>
    <span>867 &amp; Above the 60th Parallel</span>
  </div>
</div>
<main id="content">
