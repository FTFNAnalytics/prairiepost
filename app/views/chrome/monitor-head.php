<?php
/* The Mississauga Monitor — masthead chrome (design pkg §2, §4).
   In scope from page_header(): $siteTitle, $tagline, $activeDesk.
   The breaking bar is settings-driven: it renders only while the
   newsroom has breaking_label set, and links to breaking_url. */
$mmWords = preg_split('/\s+/', trim($siteTitle)) ?: [];
$mmThe   = (strcasecmp($mmWords[0] ?? '', 'The') === 0) ? array_shift($mmWords) : '';
$mmName  = implode(' ', $mmWords) ?: $siteTitle;
$mmBreaking = trim(setting('breaking_label'));
?>
<div class="mm-mast">
  <div class="in">
    <a class="mm-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <span class="m"><img src="<?= e(site_asset('mark.svg')) ?>" alt="" width="44" height="38"></span>
      <span>
        <?php if ($mmThe !== ''): ?><span class="the"><?= e($mmThe) ?></span><?php endif; ?>
        <span class="name"><?= e($mmName) ?></span>
        <span class="tag"><?= e($tagline) ?></span>
      </span>
    </a>
    <nav class="mm-nav" aria-label="Sections">
      <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('search')) ?>">Search</a>
      <a class="mm-subscribe" href="<?= e(url('newsletter/')) ?>">Subscribe</a>
    </nav>
  </div>
</div>
<?php if ($mmBreaking !== ''): ?>
<div class="mm-breaking">
  <div class="in">
    <span class="bang" aria-hidden="true">!</span>
    <span class="lbl">Breaking news</span>
    <?php $mmBUrl = trim(setting('breaking_url')); ?>
    <?php if ($mmBUrl !== ''): ?><a href="<?= e($mmBUrl) ?>"><?= e($mmBreaking) ?></a>
    <?php else: ?><span><?= e($mmBreaking) ?></span><?php endif; ?>
  </div>
</div>
<?php endif; ?>
<main id="content">
