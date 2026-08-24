<?php
// The wordmark stacks: "The Sudbury" over "STANDARD". The blackletter S is
// the monogram only — never a word — and it carries its own font.
$sdWords = preg_split('/\s+/', trim($siteTitle));
$sdLast  = count($sdWords) > 1 ? array_pop($sdWords) : '';
$sdFirst = $sdLast !== '' ? implode(' ', $sdWords) : $siteTitle;
$sdMono  = mb_substr(preg_replace('/^The\s+/i', '', $siteTitle), 0, 1);
?>
<div class="sd-util">
  <div class="in">
    <span><?= e(pp_chrome('place') ?: setting('tagline')) ?> &middot; <?= e(date('j F Y')) ?></span>
    <span class="grp">
      <a href="<?= e(url('newsletter/')) ?>">Newsletter</a>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Tips</a><?php endif; ?>
      <a href="/admin/">Sign in</a>
    </span>
  </div>
</div>
<header class="sd-mast">
  <div class="in">
    <a class="lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <span class="sx" aria-hidden="true"><?= e($sdMono) ?></span>
      <span>
        <span class="l1"><?= e($sdFirst) ?></span>
        <span class="l2"><?= e(mb_strtoupper($sdLast)) ?></span>
      </span>
    </a>
    <nav class="sd-nav" aria-label="Sections">
      <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('about')) ?>"<?= $activeDesk === 'about' ? ' aria-current="page"' : '' ?>>About</a>
    </nav>
  </div>
</header>
