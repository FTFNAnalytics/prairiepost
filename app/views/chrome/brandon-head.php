<?php
/* The Brandon Bulletin — masthead chrome (brand build, Sep 2026). In
   scope from page_header(): $siteTitle, $tagline, $activeDesk. Per the
   owner's package: white header carrying the wheat-over-book badge
   beside the stacked wordmark (serif BRANDON over the letterspaced
   gold BULLETIN), nav right with the gold active underline, over a
   thin utility strip. */
?>
<div class="bdn-util">
  <div class="in">
    <div>
      <span><?= e(pp_date('l, F j, Y')) ?></span>
      <span class="sep">|</span><span><?= e($tagline) ?></span>
    </div>
    <div>
      <a href="<?= e(url('tips')) ?>">News tips</a><span class="sep">|</span>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="bdn-head">
  <div class="in">
    <a class="bdn-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark.svg')) ?>" alt="">
      <span class="bdn-name"><span class="top">BRANDON</span><span class="sub">Bulletin</span></span>
    </a>
    <nav class="bdn-nav" aria-label="Sections">
      <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('search')) ?>">Search</a>
    </nav>
  </div>
</header>
<main id="content">
