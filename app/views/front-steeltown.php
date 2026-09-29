<?php
/* Steeltown Standard — front page (brand build). $hero resolved by
   index.php. Per the package's site mockup: navy photo hero with the
   white condensed headline and gold Read Full Story button (navy
   panel with the faint chain-ring watermark when the lead has no
   art), the gold-ruled "Latest News" title over the card grid, more
   headlines below, and the camel subscribe band. Renders the empty
   state cleanly. */
$stSeen = $hero ? [(int) $hero['id']] : [];
$stGrid = latest_posts(6, $stSeen);
$stSeen = array_merge($stSeen, array_column($stGrid, 'id'));
$stMore = latest_posts(6, $stSeen);
?>
<?php if (!$hero): ?>
<div class="st-wrap" style="padding-top:24px">
  <div class="st-empty">The Standard's first edition is being set. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first story — local news, Steeltown strong.</div>
</div>
<?php elseif ($hero['image']): ?>
<div class="st-hero">
  <img class="bg" src="<?= e($hero['image']) ?>" alt="">
  <div class="scrim"></div>
  <div class="pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'Local News'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e(excerpt($hero['lede'], 150)) ?></span><?php endif; ?>
    <a class="st-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read Full Story</a>
  </div>
</div>
<?php else: ?>
<div class="st-hero st-hero--noart">
  <img class="wm" src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
  <div class="pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'Local News'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e($hero['lede']) ?></span><?php endif; ?>
    <a class="st-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read Full Story</a>
  </div>
</div>
<?php endif; ?>

<?php if ($hero): ?>
<div class="st-wrap">
  <div class="st-ttl"><h2>Latest News</h2></div>
  <?php if ($stGrid): ?>
  <div class="st-grid">
    <?php foreach ($stGrid as $p): ?>
    <article class="st-card<?= $p['image'] ? '' : ' noart' ?>">
      <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
      <div class="txt">
        <span class="st-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) ($p['category_name'] ?: 'Local News'))) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <?php if ($p['lede']): ?><span class="meta"><?= e(excerpt($p['lede'], 90)) ?></span><?php endif; ?>
        <span class="meta"><?= dateline($p) ?></span>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="st-empty">More stories are on the way — the newsroom is filing.</div>
  <?php endif; ?>

  <?php if ($stMore): ?>
  <div class="st-ttl"><h2>More Headlines</h2></div>
  <?php foreach ($stMore as $p): ?>
  <article class="st-row<?= $p['image'] ? '' : ' noart' ?>">
    <div>
      <span class="st-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
      <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 130)) ?></p><?php endif; ?>
      <span class="meta"><?= dateline($p) ?></span>
    </div>
    <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
  </article>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="st-band">
  <div class="in">
    <div>
      <h3><?= e(setting('newsletter_heading', 'The Morning Pour')) ?></h3>
      <p><?= e(setting('newsletter_copy', 'Hamilton\'s news every weekday morning.')) ?></p>
    </div>
    <a class="st-btn" href="<?= e(url('newsletter/')) ?>">Subscribe</a>
  </div>
</div>
<?php endif; ?>
