<?php
/* The Easterly — front page. Toronto Telegraph's front in the owner's
   brand package (28 Nov 2026). $hero resolved by index.php: the photo
   hero under the North Atlantic scrim (the North Atlantic panel with the
   reversed E. seated on its bottom edge when the lead has no art),
   Featured Stories cards beside the rail — weather, the latest five,
   the subscribe box. Renders the empty state cleanly: the paper carries
   no stories before its inaugural edition. */
$eaSeen = $hero ? [(int) $hero['id']] : [];
$eaGrid = latest_posts(3, $eaSeen);
$eaSeen = array_merge($eaSeen, array_column($eaGrid, 'id'));
$eaMore = latest_posts(6, $eaSeen);
$eaLatest = latest_posts(5, []);
$eaW = array_pad(explode('|', pp_weather_line()), 2, '');
?>
<?php if (!$hero): ?>
<div class="ea-wrap" style="padding-top:24px">
  <div class="ea-empty">The Easterly&#039;s first edition is on the press. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first story.</div>
</div>
<?php elseif ($hero['image']): ?>
<div class="ea-hero">
  <img class="bg" src="<?= e($hero['image']) ?>" alt="">
  <div class="scrim"></div>
  <div class="pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'News'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e(excerpt($hero['lede'], 150)) ?></span><?php endif; ?>
    <a class="ea-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read the Latest &rarr;</a>
  </div>
</div>
<?php else: ?>
<div class="ea-hero ea-hero--noart">
  <img class="wm" src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
  <div class="pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'News'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e($hero['lede']) ?></span><?php endif; ?>
    <a class="ea-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read the Latest &rarr;</a>
  </div>
</div>
<?php endif; ?>

<?php if ($hero): ?>
<div class="ea-wrap">
  <div class="ea-home">
    <div>
      <div class="ea-ttl"><h2>Featured Stories</h2></div>
      <?php if ($eaGrid): ?>
      <div class="ea-grid">
        <?php foreach ($eaGrid as $p): ?>
        <article class="ea-card<?= $p['image'] ? '' : ' noart' ?>">
          <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
          <div class="txt">
            <span class="ea-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) ($p['category_name'] ?: 'News'))) ?></span>
            <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
            <?php if ($p['lede']): ?><span class="meta"><?= e(excerpt($p['lede'], 95)) ?></span><?php endif; ?>
            <span class="meta"><?= dateline($p) ?></span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="ea-empty">More stories are on the way — the newsroom is filing.</div>
      <?php endif; ?>

      <?php if ($eaMore): ?>
      <div class="ea-ttl"><h2>More Headlines</h2></div>
      <?php foreach ($eaMore as $p): ?>
      <article class="ea-row<?= $p['image'] ? '' : ' noart' ?>">
        <div>
          <span class="ea-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
          <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
          <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 130)) ?></p><?php endif; ?>
          <span class="meta"><?= dateline($p) ?></span>
        </div>
        <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
      </article>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <aside class="ea-rail">
      <?php if (pp_weather_line() !== ''): ?>
      <div class="ea-box ea-weather">
        <div class="hd"><h3>Weather</h3></div>
        <div class="now">
          <span class="temp"><?= e(trim($eaW[0])) ?></span>
          <span class="cond"><?= e(trim($eaW[1])) ?></span>
        </div>
        <div class="src"><?= pp_weather_source_html() ?></div>
      </div>
      <?php endif; ?>
      <?php if ($eaLatest): ?>
      <div class="ea-box ea-trend">
        <div class="hd"><h3>Latest</h3></div>
        <ol>
          <?php $eaN = 0; foreach ($eaLatest as $p): $eaN++; ?>
          <li><span class="n"><?= $eaN ?></span><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php endif; ?>
      <div class="ea-subbox">
        <h3><?= e(setting('newsletter_heading', 'The Morning Tide')) ?></h3>
        <p><?= e(setting('newsletter_copy', 'The Easterly\'s front page in your inbox every weekday morning: what the four Atlantic provinces decided, built and argued about.')) ?></p>
        <a class="ea-btn" href="<?= e(url('newsletter/')) ?>"><?= e(pp_t('Subscribe')) ?></a>
      </div>
    </aside>
  </div>
</div>
<?php endif; ?>
