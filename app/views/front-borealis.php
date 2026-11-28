<?php
/* Borealis Bulletin — front page. $hero resolved by index.php.
   Toronto Telegraph's front in the owner's brand package (28 Nov 2026),
   which shipped this template: Polar Midnight scrim photo
   hero with bold uppercase Archivo headline and Aurora Emerald "Read
   the Dispatch" CTA (Polar Midnight panel with faint reversed Polar
   Compass Roundel watermark when the lead has no art), then Featured
   Dispatches cards with dark chips beside the rail — Northern Weather
   (Yellowknife ECCC), Trending North of 60°, and the Polar Midnight
   60th Parallel Brief subscribe box. Renders the empty state cleanly.
   The weather box's latitude tag is Yellowknife's, 62°27′N: it moves
   with the weather city in palette.json. */
$boSeen = $hero ? [(int) $hero['id']] : [];
$boGrid = latest_posts(3, $boSeen);
$boSeen = array_merge($boSeen, array_column($boGrid, 'id'));
$boMore = latest_posts(6, $boSeen);
$boTrend = latest_posts(5, []);
$boW = array_pad(explode('|', pp_weather_line()), 2, '');
$boChipClass = static function (array $p): string {
    $slug = (string) ($p['category_slug'] ?? '');
    if ($slug === 'opinion') {
        return 'bo-chip bo-chip--opinion';
    }
    if (in_array($slug, ['local-news', 'environment', 'communities'], true)) {
        return 'bo-chip bo-chip--pine';
    }
    return 'bo-chip';
};
?>
<?php if (!$hero): ?>
<div class="bo-wrap" style="padding-top:24px">
  <div class="bo-empty">Borealis Bulletin's first edition is being set. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first dispatch — above the 60th, on the record.</div>
</div>
<?php elseif ($hero['image']): ?>
<div class="bo-hero">
  <img class="bg" src="<?= e($hero['image']) ?>" alt="">
  <div class="scrim"></div>
  <div class="pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'Territories'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e(excerpt($hero['lede'], 155)) ?></span><?php endif; ?>
    <a class="bo-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read the Dispatch &rarr;</a>
  </div>
</div>
<?php else: ?>
<div class="bo-hero bo-hero--noart">
  <img class="wm" src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
  <div class="pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'Territories'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e($hero['lede']) ?></span><?php endif; ?>
    <a class="bo-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Read the Dispatch &rarr;</a>
  </div>
</div>
<?php endif; ?>

<?php if ($hero): ?>
<div class="bo-wrap">
  <div class="bo-home">
    <div>
      <div class="bo-ttl"><span class="star" aria-hidden="true">&#10022;</span><h2>Featured Dispatches</h2></div>
      <?php if ($boGrid): ?>
      <div class="bo-grid">
        <?php foreach ($boGrid as $p): ?>
        <article class="bo-card<?= $p['image'] ? '' : ' noart' ?>">
          <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
          <div class="txt">
            <span class="<?= $boChipClass($p) ?>"><?= e(pp_desk_label((string) $p['category_slug'], (string) ($p['category_name'] ?: 'Territories'))) ?></span>
            <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
            <?php if ($p['lede']): ?><span class="meta"><?= e(excerpt($p['lede'], 95)) ?></span><?php endif; ?>
            <span class="meta--date"><?= dateline($p) ?></span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="bo-empty">More dispatches arrive as the northern newsroom files.</div>
      <?php endif; ?>

      <?php if ($boMore): ?>
      <div class="bo-ttl"><span class="star" aria-hidden="true">&#10022;</span><h2>Across the North</h2></div>
      <?php foreach ($boMore as $p): ?>
      <article class="bo-row<?= $p['image'] ? '' : ' noart' ?>">
        <div>
          <span class="<?= $boChipClass($p) ?>"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
          <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
          <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 135)) ?></p><?php endif; ?>
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
        <div class="hd">
          <h3>Northern Weather</h3>
          <span class="lat">62&deg;27&prime;N</span>
        </div>
        <div class="now">
          <span class="temp"><?= e(trim($boW[0])) ?></span>
          <span class="cond"><?= e(trim($boW[1])) ?></span>
        </div>
        <div class="src"><?= pp_weather_source_html() ?></div>
      </div>
      <?php endif; ?>
      <?php if ($boTrend): ?>
      <div class="bo-box bo-trend">
        <div class="hd">
          <h3>North of 60&deg;</h3>
          <span class="lat">LATEST</span>
        </div>
        <ol>
          <?php $boN = 0; foreach ($boTrend as $p): $boN++; ?>
          <li><span class="n"><?= $boN ?></span><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php endif; ?>
      <div class="bo-subbox">
        <div class="kicker">Weekday Morning Briefing</div>
        <h3><?= e(setting('newsletter_heading', 'The 60th Parallel Brief')) ?></h3>
        <p><?= e(setting('newsletter_copy', 'Dispatches from Yukon, NWT, Nunavut and the Arctic every weekday morning.')) ?></p>
        <a class="bo-btn" href="<?= e(url('newsletter/')) ?>">Subscribe</a>
      </div>
    </aside>
  </div>
</div>
<?php endif; ?>
