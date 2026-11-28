<?php
/* Borealis Bulletin — front page. FOUNDATION build (Nov 2026): Toronto
   Telegraph's front, unbranded. $hero resolved by index.php: the photo
   hero under the primary scrim (the primary panel with the reversed
   roundel when the lead has no art), Featured Stories cards beside the
   rail — weather, the latest five, the subscribe box. Renders the empty
   state cleanly: the paper carries no stories before its brand build. */
$boSeen = $hero ? [(int) $hero['id']] : [];
$boGrid = latest_posts(3, $boSeen);
$boSeen = array_merge($boSeen, array_column($boGrid, 'id'));
$boMore = latest_posts(6, $boSeen);
$boLatest = latest_posts(5, []);
$boW = array_pad(explode('|', pp_weather_line()), 2, '');
?>
<?php if (!$hero): ?>
<div class="bo-wrap" style="padding-top:24px">
  <div class="bo-empty">The Bulletin&#039;s first edition is on the press. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first story.</div>
</div>
<?php elseif ($hero['image']): ?>
<div class="bo-hero">
  <img class="bg" src="<?= e($hero['image']) ?>" alt="">
  <div class="scrim"></div>
  <div class="pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'News'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e(excerpt($hero['lede'], 150)) ?></span><?php endif; ?>
    <a class="bo-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read the Latest &rarr;</a>
  </div>
</div>
<?php else: ?>
<div class="bo-hero bo-hero--noart">
  <img class="wm" src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
  <div class="pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'News'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e($hero['lede']) ?></span><?php endif; ?>
    <a class="bo-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read the Latest &rarr;</a>
  </div>
</div>
<?php endif; ?>

<?php if ($hero): ?>
<div class="bo-wrap">
  <div class="bo-home">
    <div>
      <div class="bo-ttl"><h2>Featured Stories</h2></div>
      <?php if ($boGrid): ?>
      <div class="bo-grid">
        <?php foreach ($boGrid as $p): ?>
        <article class="bo-card<?= $p['image'] ? '' : ' noart' ?>">
          <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
          <div class="txt">
            <span class="bo-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) ($p['category_name'] ?: 'News'))) ?></span>
            <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
            <?php if ($p['lede']): ?><span class="meta"><?= e(excerpt($p['lede'], 95)) ?></span><?php endif; ?>
            <span class="meta"><?= dateline($p) ?></span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="bo-empty">More stories are on the way — the newsroom is filing.</div>
      <?php endif; ?>

      <?php if ($boMore): ?>
      <div class="bo-ttl"><h2>More Headlines</h2></div>
      <?php foreach ($boMore as $p): ?>
      <article class="bo-row<?= $p['image'] ? '' : ' noart' ?>">
        <div>
          <span class="bo-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
          <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
          <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 130)) ?></p><?php endif; ?>
          <span class="meta"><?= dateline($p) ?></span>
        </div>
        <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
      </article>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <aside class="bo-rail">
      <?php if (pp_weather_line() !== ''): ?>
      <div class="bo-box bo-weather">
        <div class="hd"><h3>Weather</h3></div>
        <div class="now">
          <span class="temp"><?= e(trim($boW[0])) ?></span>
          <span class="cond"><?= e(trim($boW[1])) ?></span>
        </div>
        <div class="src"><?= pp_weather_source_html() ?></div>
      </div>
      <?php endif; ?>
      <?php if ($boLatest): ?>
      <div class="bo-box bo-trend">
        <div class="hd"><h3>Latest</h3></div>
        <ol>
          <?php $boN = 0; foreach ($boLatest as $p): $boN++; ?>
          <li><span class="n"><?= $boN ?></span><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php endif; ?>
      <div class="bo-subbox">
        <h3><?= e(setting('newsletter_heading', 'First Light')) ?></h3>
        <p><?= e(setting('newsletter_copy', 'The Bulletin\'s front page in your inbox every weekday morning: what the three territories decided, built and argued about.')) ?></p>
        <a class="bo-btn" href="<?= e(url('newsletter/')) ?>"><?= e(pp_t('Subscribe')) ?></a>
      </div>
    </aside>
  </div>
</div>
<?php endif; ?>
