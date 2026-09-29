<?php
/* Steeltown Standard — masthead chrome (brand build, Sep 2026). In
   scope from page_header(): $siteTitle, $tagline, $activeDesk. Per the
   owner's package: white bar carrying the chain-ring mark beside the
   stacked wordmark (condensed navy STEELTOWN over the letterspaced
   serif STANDARD), uppercase navy nav with the gold active underline,
   and the gold Subscribe button, over a black utility strip. */
$stMail = trim(setting('contact_email'));
?>
<div class="st-util">
  <div class="in">
    <div>
      <span><?= e(date('l, F j, Y')) ?></span>
      <span class="sep">|</span><span><?= e($tagline) ?></span>
    </div>
    <div>
      <?php if ($stMail !== ''): ?><a href="mailto:<?= e($stMail) ?>">News tips</a><span class="sep">|</span><?php endif; ?>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="st-head">
  <div class="in">
    <a class="st-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark.svg')) ?>" alt="">
      <span class="st-name"><span class="top">Steeltown</span><span class="sub">Standard</span></span>
    </a>
    <nav class="st-nav" aria-label="Sections">
      <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('search')) ?>">Search</a>
      <a class="st-sub-btn" href="<?= e(url('newsletter/')) ?>">Subscribe</a>
    </nav>
  </div>
</header>
<main id="content">
