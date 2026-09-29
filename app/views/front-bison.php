<?php
/* The Bison Bulletin — front page (brand build). $hero resolved by
   index.php. Per the package's site mockups: full-width hero with the
   overlay headline and Read More button (red panel with the faint
   bison watermark when the lead has no art), then the red "Latest
   News" band over a white card grid, beside the Categories / Trending
   Now / Subscribe sidebar. Renders the empty state cleanly. */
$bsnSeen = $hero ? [(int) $hero['id']] : [];
$bsnGrid = latest_posts(6, $bsnSeen);
$bsnSeen = array_merge($bsnSeen, array_column($bsnGrid, 'id'));
$bsnTrend = latest_posts(4, $bsnSeen);
?>
<div class="bsn-wrap">
  <?php if (!$hero): ?>
  <div style="padding:24px 0">
    <div class="bsn-empty">The Bulletin's first edition is at the presses. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first story — Manitoba news you can trust.</div>
  </div>
  <?php elseif ($hero['image']): ?>
  <div class="bsn-hero">
    <img src="<?= e($hero['image']) ?>" alt="">
    <div class="ov">
      <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'News'))) ?></span>
      <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
      <span class="meta"><?= dateline($hero) ?></span>
      <a class="bsn-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read More</a>
    </div>
  </div>
  <?php else: ?>
  <div class="bsn-hero bsn-hero--noart">
    <img class="wm" src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
    <div class="pad">
      <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'News'))) ?></span>
      <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
      <?php if ($hero['lede']): ?><span class="meta" style="max-width:560px"><?= e($hero['lede']) ?></span><?php endif; ?>
      <a class="bsn-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read More</a>
    </div>
  </div>
  <?php endif; ?>

  <div class="bsn-home">
    <div>
      <div class="bsn-band" style="margin-top:0">Latest News</div>
      <?php if ($bsnGrid): ?>
      <div class="bsn-grid">
        <?php foreach ($bsnGrid as $p): ?>
        <article class="bsn-card">
          <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
          <div class="pad">
            <span class="bsn-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) ($p['category_name'] ?: 'News'))) ?></span>
            <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
            <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 90)) ?></p><?php endif; ?>
            <span class="meta"><?= dateline($p) ?></span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php elseif ($hero): ?>
      <div class="bsn-empty">More stories arrive as the newsroom files.</div>
      <?php endif; ?>
    </div>
    <aside class="bsn-side">
      <div class="bsn-box">
        <div class="head">Categories</div>
        <div class="body">
          <?php foreach (pp_nav_categories() as $cat): ?>
          <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="bsn-trend">
        <div class="ttl">Trending Now</div>
        <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
        <?php foreach ($bsnTrend as $p): ?>
        <a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a>
        <?php endforeach; ?>
        <?php if (!$bsnTrend): ?><a href="<?= e(url('search')) ?>">The archive opens as stories publish.</a><?php endif; ?>
      </div>
      <div class="bsn-box bsn-sub">
        <div class="ttl">Subscribe</div>
        <p><?= e(setting('newsletter_copy', 'Manitoba\'s news in your inbox.')) ?></p>
        <a class="bsn-btn" href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Morning Bulletin')) ?></a>
      </div>
    </aside>
  </div>
</div>
