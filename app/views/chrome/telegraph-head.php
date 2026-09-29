<?php
/* Toronto Telegraph — masthead chrome (brand build, Sep 2026). In
   scope from page_header(): $siteTitle, $tagline, $activeDesk. Per
   the owner's package: white bar carrying the maple-leaf-and-
   telegraph-key roundel beside the stacked two-tone wordmark
   (TORONTO navy over TELEGRAPH cambridge, the tagline letterspaced
   beneath), modern sans nav with the cambridge active underline and
   the navy Subscribe button, over an oxford utility strip. */
$tgMail = trim(setting('contact_email'));
?>
<div class="tg-util">
  <div class="in">
    <div>
      <span><?= e(date('l, F j, Y')) ?></span>
      <span class="sep">|</span><span><?= e($tagline) ?></span>
    </div>
    <div>
      <?php if ($tgMail !== ''): ?><a href="mailto:<?= e($tgMail) ?>">News tips</a><span class="sep">|</span><?php endif; ?>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="tg-head">
  <div class="in">
    <a class="tg-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark.svg')) ?>" alt="">
      <span class="tg-name"><span class="top">Toronto</span><span class="mid">Telegraph</span><span class="sub"><?= e($tagline) ?></span></span>
    </a>
    <nav class="tg-nav" aria-label="Sections">
      <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('search')) ?>">Search</a>
      <a class="tg-sub-btn" href="<?= e(url('newsletter/')) ?>">Subscribe</a>
    </nav>
  </div>
</header>
<main id="content">
