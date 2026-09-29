<?php
/* Portage Press — front page (brand build). $hero resolved by
   index.php. Per the package's site mockup: photo hero with the red
   chip and bottom-left overlay headline (navy P-pin panel when the
   lead has no art), a three-card row with red category labels, and
   the rail — navy Weather panel, MOST READ numbered list, and the
   blue "Stay in the know." subscribe panel. Renders the empty state
   cleanly. */
$ptgSeen = $hero ? [(int) $hero['id']] : [];
$ptgGrid = latest_posts(6, $ptgSeen);
$ptgSeen = array_merge($ptgSeen, array_column($ptgGrid, 'id'));
$ptgMost = latest_posts(5, []);
$ptgW = array_pad(explode('|', (string) setting('weather_line')), 2, '');
?>
<div class="ptg-wrap">
  <div class="ptg-home">
    <div>
      <?php if (!$hero): ?>
      <div class="ptg-empty">The first edition of the Press is being set. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first story — local news, because Winnipeg matters.</div>
      <?php elseif ($hero['image']): ?>
      <div class="ptg-hero">
        <img src="<?= e($hero['image']) ?>" alt="">
        <div class="ov">
          <span class="ptg-chip"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'News'))) ?></span>
          <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
          <span class="meta"><?= dateline($hero) ?> &middot; <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $hero['body'])) / 220))) ?> min read</span>
        </div>
      </div>
      <?php else: ?>
      <div class="ptg-hero ptg-hero--noart">
        <img class="wm" src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
        <div class="pad">
          <span class="ptg-chip"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'News'))) ?></span>
          <h1 style="color:#fff"><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
          <?php if ($hero['lede']): ?><span class="meta" style="color:#C9D4E4;max-width:560px"><?= e($hero['lede']) ?></span><?php endif; ?>
          <a class="ptg-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read More</a>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($ptgGrid): ?>
      <div class="ptg-grid">
        <?php foreach ($ptgGrid as $p): ?>
        <article class="ptg-card">
          <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
          <span class="ptg-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) ($p['category_name'] ?: 'News'))) ?></span>
          <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
          <span class="meta"><?= dateline($p) ?> &middot; <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $p['body'])) / 220))) ?> min read</span>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <aside class="ptg-side">
      <?php if (trim($ptgW[0]) !== ''): ?>
      <div class="ptg-weather">
        <div class="row">
          <span class="temp"><?= e(trim($ptgW[0])) ?></span>
          <span class="place">Winnipeg, MB<?php if (trim($ptgW[1]) !== ''): ?><br><?= e(trim($ptgW[1])) ?><?php endif; ?></span>
        </div>
        <div class="cond">Updated each morning by the newsroom.</div>
      </div>
      <?php endif; ?>
      <div class="ptg-mostread">
        <span class="ttl">Most Read</span>
        <?php foreach ($ptgMost as $i => $p): ?>
        <a href="<?= e(url('story/' . $p['slug'])) ?>"><span class="n"><?= $i + 1 ?></span><?= e($p['title']) ?></a>
        <?php endforeach; ?>
        <?php if (!$ptgMost): ?><a href="<?= e(url('search')) ?>"><span class="n">1</span>The archive opens as stories publish.</a><?php endif; ?>
      </div>
      <div class="ptg-sub">
        <div class="ttl">Stay in the know.</div>
        <p><?= e(setting('newsletter_copy', 'Get the latest Winnipeg news delivered to your inbox.')) ?></p>
        <a class="ptg-btn" href="<?= e(url('newsletter/')) ?>">Subscribe</a>
      </div>
    </aside>
  </div>
</div>
