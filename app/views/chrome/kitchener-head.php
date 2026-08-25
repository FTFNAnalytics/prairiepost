<?php
/* The Kitchener Chronicle — masthead chrome (design pkg plates 01 & 03).
   In scope from page_header(): $siteTitle, $tagline, $activeDesk.
   The dateline carries the volume the paper has run since 1909 and the
   day-of-year issue number — derived, never stored. */
$kcWords = preg_split('/\s+/', trim($siteTitle)) ?: [];
$kcThe   = (strcasecmp($kcWords[0] ?? '', 'The') === 0) ? array_shift($kcWords) : '';
$kcName  = implode(' ', $kcWords) ?: $siteTitle;
$kcRoman = function (int $n): string {
    $out = '';
    foreach ([1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC',
              50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'] as $v => $r) {
        while ($n >= $v) { $out .= $r; $n -= $v; }
    }
    return $out;
};
/* Every card carries art: the story's own image, or a desk-mapped
   sketch from the paper's illustration set — rotated by post id so a
   desk's cards don't all repeat one drawing. Fallbacks exist because
   desks are shared and stories arrive (from Hermes, from the newsroom)
   without photography. */
if (!function_exists('kc_art')) {
    function kc_art(array $p): string
    {
        if (!empty($p['image'])) {
            return (string) $p['image'];
        }
        $set = [
            'local-news' => ['rowhouses.svg', 'iontrain.svg', 'tower.svg'],
            'politics'   => ['tower.svg', 'legislature.svg'],
            'ontario'    => ['legislature.svg', 'river.svg'],
            'business'   => ['corridor.svg', 'rowhouses.svg'],
            'sports'     => ['arena.svg'],
            'culture'    => ['market.svg', 'arena.svg'],
        ];
        $pick = $set[$p['category_slug'] ?? ''] ?? ['skyline.svg', 'tower.svg', 'river.svg'];
        return site_asset('img/' . $pick[((int) ($p['id'] ?? 0)) % count($pick)]);
    }
}
?>
<div class="kc-topbar"></div>
<header class="kc-mast">
  <div class="in">
    <img class="kc-sky" src="<?= e(site_asset('img/skyline-ghost.svg')) ?>" alt="" aria-hidden="true">
    <div class="kc-dateline">
      <span><?= e(date('l, F j, Y')) ?></span>
      <span class="vol">Vol. <?= e($kcRoman((int) date('Y') - 1909)) ?> &middot; No. <?= e((string) ((int) date('z') + 1)) ?></span>
    </div>
    <a class="kc-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <?php if ($kcThe !== ''): ?><span class="the"><?= e($kcThe) ?></span><?php endif; ?>
      <span class="name"><?= e($kcName) ?></span>
      <span class="places"><span><?= e($tagline) ?></span></span>
    </a>
  </div>
</header>
<div class="kc-rule"></div>
<nav class="kc-nav" aria-label="Sections">
  <div class="in">
    <div class="links">
      <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>>Home</a>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="tools">
      <a class="find" href="<?= e(url('search')) ?>">Search the Chronicle</a>
      <a class="kc-subscribe" href="<?= e(url('newsletter/')) ?>">Subscribe</a>
    </div>
  </div>
</nav>
<main id="content">
