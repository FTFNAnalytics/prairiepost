<?php
/* The Brandon Bulletin — front page (brand build). $hero resolved by
   index.php. Per the package's site mockup: full-bleed photo hero with
   the white headline and gold Read More (light-wheat panel when the
   lead has no art), then the ornamented "Latest News" title over
   gold-ruled cards, beside the Weather box, the black Trending
   Stories rail and the Subscribe box. Renders the empty state
   cleanly. */
$bdnSeen = $hero ? [(int) $hero['id']] : [];
$bdnGrid = latest_posts(6, $bdnSeen);
$bdnSeen = array_merge($bdnSeen, array_column($bdnGrid, 'id'));
$bdnTrend = latest_posts(5, []);
$bdnW = array_pad(explode('|', (string) setting('weather_line')), 2, '');
?>
<?php if (!$hero): ?>
<div class="bdn-wrap" style="padding-top:24px">
  <div class="bdn-empty">The Bulletin's first edition is being set. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first story — news with heart, rooted in place.</div>
</div>
<?php elseif ($hero['image']): ?>
<div class="bdn-hero">
  <img src="<?= e($hero['image']) ?>" alt="">
  <div class="ov">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'Local News'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta" style="max-width:560px"><?= e(excerpt($hero['lede'], 140)) ?></span><?php endif; ?>
    <a class="bdn-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read More</a>
  </div>
</div>
<?php else: ?>
<div class="bdn-hero bdn-hero--noart">
  <div class="bdn-wrap pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'Local News'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e($hero['lede']) ?></span><?php endif; ?>
    <a class="bdn-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read More</a>
  </div>
</div>
<?php endif; ?>

<?php if ($hero): ?>
<div class="bdn-wrap">
  <div class="bdn-home">
    <div>
      <div class="bdn-ttl"><h2>Latest News</h2><span class="star">✦</span></div>
      <?php if ($bdnGrid): ?>
      <div class="bdn-grid">
        <?php foreach ($bdnGrid as $p): ?>
        <article class="bdn-card">
          <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
          <span class="bdn-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) ($p['category_name'] ?: 'Local News'))) ?></span>
          <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
          <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 90)) ?></p><?php endif; ?>
          <span class="meta"><?= dateline($p) ?></span>
        </article>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="bdn-empty">More stories arrive as the newsroom files.</div>
      <?php endif; ?>
    </div>
    <aside class="bdn-side">
      <?php if ($bdnW[0] !== ''): ?>
      <div class="bdn-weather">
        <div>
          <div class="ttl">Weather</div>
          <div class="cond"><?= e(trim($bdnW[1]) !== '' ? trim($bdnW[1]) . ', ' : '') ?><?= e(trim($bdnW[0])) ?></div>
        </div>
        <span class="sun" aria-hidden="true">☀</span>
      </div>
      <?php endif; ?>
      <div class="bdn-trend">
        <div class="head">Trending Stories</div>
        <div class="body">
          <?php foreach ($bdnTrend as $p): ?>
          <a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a>
          <?php endforeach; ?>
          <?php if (!$bdnTrend): ?><a href="<?= e(url('search')) ?>">The archive opens as stories publish.</a><?php endif; ?>
        </div>
      </div>
      <div class="bdn-subbox">
        <div class="ttl">Subscribe</div>
        <p><?= e(setting('newsletter_copy', 'Southwest Manitoba\'s news in your inbox.')) ?></p>
        <a class="bdn-btn" href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Wheat City Brief')) ?></a>
      </div>
    </aside>
  </div>
</div>
<?php endif; ?>
