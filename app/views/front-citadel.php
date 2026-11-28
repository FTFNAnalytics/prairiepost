<?php
/* Citadel Courier — front page. FOUNDATION build (Nov 2026): Toronto
   Telegraph's front, unbranded. $hero resolved by index.php: the photo
   hero under the primary scrim (the primary panel with the reversed
   roundel when the lead has no art), Featured Stories cards beside the
   rail — weather, the latest five, the subscribe box. Renders the empty
   state cleanly: the paper carries no stories before its brand build. */
$ccSeen = $hero ? [(int) $hero['id']] : [];
$ccGrid = latest_posts(3, $ccSeen);
$ccSeen = array_merge($ccSeen, array_column($ccGrid, 'id'));
$ccMore = latest_posts(6, $ccSeen);
$ccLatest = latest_posts(5, []);
$ccW = array_pad(explode('|', pp_weather_line()), 2, '');
?>
<?php if (!$hero): ?>
<div class="cc-wrap" style="padding-top:24px">
  <div class="cc-empty">The Courier&#039;s first edition is on the press. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first story.</div>
</div>
<?php elseif ($hero['image']): ?>
<div class="cc-hero">
  <img class="bg" src="<?= e($hero['image']) ?>" alt="">
  <div class="scrim"></div>
  <div class="pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'News'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e(excerpt($hero['lede'], 150)) ?></span><?php endif; ?>
    <a class="cc-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read the Latest &rarr;</a>
  </div>
</div>
<?php else: ?>
<div class="cc-hero cc-hero--noart">
  <img class="wm" src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
  <div class="pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'News'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e($hero['lede']) ?></span><?php endif; ?>
    <a class="cc-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read the Latest &rarr;</a>
  </div>
</div>
<?php endif; ?>

<?php if ($hero): ?>
<div class="cc-wrap">
  <div class="cc-home">
    <div>
      <div class="cc-ttl"><h2>Featured Stories</h2></div>
      <?php if ($ccGrid): ?>
      <div class="cc-grid">
        <?php foreach ($ccGrid as $p): ?>
        <article class="cc-card<?= $p['image'] ? '' : ' noart' ?>">
          <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
          <div class="txt">
            <span class="cc-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) ($p['category_name'] ?: 'News'))) ?></span>
            <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
            <?php if ($p['lede']): ?><span class="meta"><?= e(excerpt($p['lede'], 95)) ?></span><?php endif; ?>
            <span class="meta"><?= dateline($p) ?></span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="cc-empty">More stories are on the way — the newsroom is filing.</div>
      <?php endif; ?>

      <?php if ($ccMore): ?>
      <div class="cc-ttl"><h2>More Headlines</h2></div>
      <?php foreach ($ccMore as $p): ?>
      <article class="cc-row<?= $p['image'] ? '' : ' noart' ?>">
        <div>
          <span class="cc-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
          <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
          <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 130)) ?></p><?php endif; ?>
          <span class="meta"><?= dateline($p) ?></span>
        </div>
        <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
      </article>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <aside class="cc-rail">
      <?php if (pp_weather_line() !== ''): ?>
      <div class="cc-box cc-weather">
        <div class="hd"><h3>Weather</h3></div>
        <div class="now">
          <span class="temp"><?= e(trim($ccW[0])) ?></span>
          <span class="cond"><?= e(trim($ccW[1])) ?></span>
        </div>
        <div class="src"><?= pp_weather_source_html() ?></div>
      </div>
      <?php endif; ?>
      <?php if ($ccLatest): ?>
      <div class="cc-box cc-trend">
        <div class="hd"><h3>Latest</h3></div>
        <ol>
          <?php $ccN = 0; foreach ($ccLatest as $p): $ccN++; ?>
          <li><span class="n"><?= $ccN ?></span><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php endif; ?>
      <div class="cc-subbox">
        <h3><?= e(setting('newsletter_heading', 'The Noon Gun')) ?></h3>
        <p><?= e(setting('newsletter_copy', 'The Courier\'s front page in your inbox at midday, when the Citadel fires its gun: what Halifax decided, built and argued about this morning.')) ?></p>
        <a class="cc-btn" href="<?= e(url('newsletter/')) ?>"><?= e(pp_t('Subscribe')) ?></a>
      </div>
    </aside>
  </div>
</div>
<?php endif; ?>
