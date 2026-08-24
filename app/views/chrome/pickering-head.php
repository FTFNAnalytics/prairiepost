<?php
// The tile lockup leads, because that is what the masthead uses. The name
// splits into "The" over the rest so the two lines set flush beside the tile.
$pkWords = preg_split('/\s+/', trim($siteTitle));
$pkThe   = (strcasecmp($pkWords[0] ?? '', 'The') === 0) ? array_shift($pkWords) : '';
$pkName  = implode(' ', $pkWords);
$pkInit  = mb_strtoupper(mb_substr($pkName !== '' ? $pkName : $siteTitle, 0, 1));
// A section front replaces the tile masthead with the centred nameplate;
// section.php sets these before page_header() runs.
$pkMode  = $GLOBALS['ppPkMode'] ?? 'full';
?>
<div class="pk-page">
  <div class="pk-util">
    <span><?= e(setting('tagline')) ?> &middot; <?= e(date('l, F j, Y')) ?></span>
    <span class="grp">
      <a href="<?= e(url('newsletter/')) ?>">Newsletter</a>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Contact</a><?php endif; ?>
      <a href="/admin/">Sign in</a>
    </span>
  </div>
  <header class="pk-mast">
    <a class="pk-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <span class="pk-tile" aria-hidden="true"><?= e($pkInit) ?></span>
      <span>
        <?php if ($pkThe !== ''): ?><span class="sub" style="display:block"><?= e($pkThe) ?></span><?php endif; ?>
        <span class="pk-name"><?= e($pkName !== '' ? $pkName : $siteTitle) ?></span>
      </span>
    </a>
    <a class="pk-btn" href="<?= e(url('subscribe')) ?>">Subscribe</a>
  </header>
  <nav class="pk-nav" aria-label="Sections">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(url('contact')) ?>"<?= $activeDesk === 'contact' ? ' aria-current="page"' : '' ?>>Contact</a>
  </nav>
