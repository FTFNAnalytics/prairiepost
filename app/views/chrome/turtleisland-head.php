<?php
// The ink block. The turtle sits over the nameplate and the caps cut into its
// shell, so the two read as one shape — the mark is positioned over the type,
// not stacked above it, and the keyline in the stylesheet is what holds white
// letters against a white mark.
//
// On an article or a section front the block condenses; a section front puts
// the desk's name where the nameplate goes. $ppTiMast is set by those
// templates before page_header() runs.
$tiMode = $GLOBALS['ppTiMast'] ?? 'full';
$tiPlate = $GLOBALS['ppTiPlate'] ?? $siteTitle;
?>
<div class="ti-field">
  <div class="ti-col">
    <header class="ti-mast<?= $tiMode === 'slim' ? ' ti-mast--slim' : '' ?><?= $tiMode === 'section' ? ' ti-mast--section' : '' ?>">
      <div class="ti-util">
        <span><?= e(setting('tagline')) ?></span>
        <span class="grp">
          <a href="<?= e(url('subscribe')) ?>">Subscribe</a>
          <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Contact</a><?php endif; ?>
          <a href="/admin/">Sign in</a>
        </span>
      </div>
      <div class="ti-name">
        <?php if ($tiMode !== 'section'): ?>
        <span class="shell" aria-hidden="true"><img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="" width="140" height="124"></span>
        <?php endif; ?>
        <?php if ($tiMode === 'full'): ?>
        <h1 class="wm"><a style="color:inherit" href="/"><?= e($tiPlate) ?></a></h1>
        <?php else: ?>
        <p class="wm"><a style="color:inherit" href="<?= e($tiMode === 'section' ? '/' : '/') ?>"><?= e($tiPlate) ?></a></p>
        <?php endif; ?>
      </div>
      <div class="ti-rail">
        <nav aria-label="Sections">
          <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
          <?php foreach (pp_nav_categories() as $cat): ?>
          <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
          <?php endforeach; ?>
          <a href="<?= e(url('about')) ?>"<?= $activeDesk === 'about' ? ' aria-current="page"' : '' ?>>About</a>
        </nav>
        <form method="get" action="<?= e(url('search')) ?>" role="search">
          <input type="search" name="q" placeholder="Search" aria-label="Search the archive">
          <button type="submit" aria-label="Search">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="7" cy="7" r="4.6"/><path d="M10.4 10.4 14 14"/></svg>
          </button>
        </form>
      </div>
    </header>
    <div class="ti-pad">
