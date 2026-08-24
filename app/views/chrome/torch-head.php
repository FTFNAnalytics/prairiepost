<?php
// The wordmark stacks "Tri Cities" over "TORCH"; split the title once here.
$ttWords = preg_split('/\s+/', trim($siteTitle));
$ttLast  = count($ttWords) > 1 ? array_pop($ttWords) : '';
$ttFirst = $ttLast !== '' ? implode(' ', $ttWords) : $siteTitle;
// Article and section pages carry the header lockup from the first pixel;
// the home page fades it in once the masthead has scrolled away.
$ttFront = (bool) ($GLOBALS['pp_front_page'] ?? false);
?>
<nav class="tt-nav<?= $ttFront ? '' : ' always-lockup' ?>" id="ttnav" aria-label="Sections">
  <div class="in">
    <a class="lockup" href="/" aria-label="<?= e($siteTitle) ?> — front page">
      <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
      <span class="wm"><b><?= e($ttFirst) ?></b><span><?= e(mb_strtoupper($ttLast)) ?></span></span>
    </a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<?php if ($ttFront): ?>
<script>
(function () {
  var nav = document.getElementById('ttnav');
  if (!nav) return;
  var on = false;
  var check = function () {
    var should = window.scrollY > 220;
    if (should !== on) { on = should; nav.classList.toggle('is-stuck', should); }
  };
  window.addEventListener('scroll', check, { passive: true });
  check();
})();
</script>
<?php endif; ?>
