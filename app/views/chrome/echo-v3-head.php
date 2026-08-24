<?php $mastWords = preg_split('/\s+/', trim(preg_replace('/^The\s+/i', '', $siteTitle))); $mastLast = count($mastWords) > 1 ? array_pop($mastWords) : ''; ?>
<div class="v3-strip">
  <div class="wrap">
    <div class="grp">
      <span><?= e(date('l, F j, Y')) ?></span>
      <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
    </div>
    <div class="grp">
      <?php if (setting('breaking_label') !== '' && setting('breaking_url') !== ''): ?>
      <a class="hot" href="<?= e(setting('breaking_url')) ?>"><?= e(setting('breaking_label')) ?></a>
      <?php endif; ?>
      <a class="hot" href="<?= e(url('subscribe')) ?>">Subscribe</a>
      <a href="<?= e(url('search')) ?>">Search</a>
    </div>
  </div>
</div>
<div class="v3-mast">
  <a class="box" href="/" aria-label="<?= e($siteTitle) ?> — front page">
    <span class="l1"><?= e($mastLast !== '' ? implode(' ', $mastWords) : $siteTitle) ?></span>
    <?php if ($mastLast !== ''): ?><span class="l2" style="display:block"><?= e($mastLast) ?></span><?php endif; ?>
  </a>
</div>
<nav class="v3-nav" aria-label="Desks">
  <div class="wrap">
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<?php $banner = pp_chrome('banner'); if (is_string($banner) && $banner !== '' && ($GLOBALS['pp_front_page'] ?? false)): ?>
<div class="v3-banner" style="background-image:url('<?= e($banner) ?>')"></div>
<?php endif; ?>
