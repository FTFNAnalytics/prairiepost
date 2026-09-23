<?php
/* The Burrard Brief — masthead chrome (brand build, Sep 2026). In
   scope from page_header(): $siteTitle, $tagline, $activeDesk. The
   horizontal lockup: the mountains-and-inlet mark, an italic teal
   "The", the wordmark in Deep Burrard Navy; tagline and Subscribe on
   the right; nav under a navy rule.

   bb_art(): the brand package's Vancouver scene photography, mapped by
   desk, as the fallback for photo-less rows (the kc_art pattern) —
   template-level, no database writes. Defined here because this
   partial loads before every burrard view. */
if (!function_exists('bb_art')) {
    function bb_art(array $post): string
    {
        if (!empty($post['image'])) {
            return (string) $post['image'];
        }
        $map = [
            'local-news'  => ['street.png', 'skyline.png', 'aerial.png'],
            'housing'     => ['houses.png', 'towers.png', 'garden.png'],
            'city-hall'   => ['legislature.png', 'mural.png'],
            'transit'     => ['skytrain.png', 'street.png'],
            'environment' => ['forest.png', 'shore.png', 'rain.png'],
            'opinion'     => ['harbour.png', 'mural.png'],
        ];
        $set = $map[(string) ($post['category_slug'] ?? '')] ?? ['harbour.png', 'port.png', 'skyline.png'];
        return site_asset('img/' . $set[((int) ($post['id'] ?? 0)) % count($set)]);
    }
}
$bbWords = preg_split('/\s+/', trim($siteTitle)) ?: [];
$bbThe   = (strcasecmp($bbWords[0] ?? '', 'the') === 0) ? array_shift($bbWords) : '';
$bbName  = implode(' ', $bbWords) ?: $siteTitle;
$bbWeather = trim(setting('weather_line'));
$bbMail = trim(setting('contact_email'));
?>
<div class="bb-util">
  <div class="in">
    <div>
      <span><?= e(date('l, F j, Y')) ?></span>
      <?php if ($bbWeather !== ''): ?><span class="sep">|</span><span>Vancouver <?= e(str_replace('|', ' · ', $bbWeather)) ?></span><?php endif; ?>
    </div>
    <div>
      <?php if ($bbMail !== ''): ?><a href="mailto:<?= e($bbMail) ?>">News tips</a><span class="sep">|</span><?php endif; ?>
      <a href="/admin/">Sign in</a>
    </div>
  </div>
</div>
<header class="bb-mast">
  <div class="in">
    <a class="bb-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark.svg')) ?>" alt="" width="62" height="39">
      <span>
        <?php if ($bbThe !== ''): ?><span class="the"><?= e($bbThe) ?></span><?php endif; ?>
        <span class="name"><?= e($bbName) ?></span>
      </span>
    </a>
    <span class="bb-mastright">
      <span class="bb-tag"><?= e($tagline) ?></span>
      <a class="bb-btn" href="<?= e(url('newsletter/')) ?>">Subscribe</a>
    </span>
  </div>
</header>
<nav class="bb-nav" aria-label="Sections">
  <div class="in">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(url('search')) ?>">Search</a>
  </div>
</nav>
<main id="content">
