<?php
/**
 * The Kitchener Chronicle — front page (design pkg plate 03).
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * Lead story with dek and sketch, two secondaries, a three-up row
 * between hairlines, the green Ontario band, and the rail: Latest with
 * timestamps, the weather (settings-driven), and Most read.
 */

$vus = $hero ? [(int) $hero['id']] : [];
$duo = front_featured_posts($vus, 2);
if (count($duo) < 2) {
    $duo = array_merge($duo, latest_posts(2 - count($duo), array_merge($vus, array_map('intval', array_column($duo, 'id')))));
}
$vus = array_merge($vus, array_map('intval', array_column($duo, 'id')));
$trio = latest_posts(3, $vus);
$vus = array_merge($vus, array_map('intval', array_column($trio, 'id')));

$ontCat = category_by_slug('ontario');
$ontario = $ontCat ? posts_in_category((int) $ontCat['id'], 3, $vus) : [];
$vus = array_merge($vus, array_map('intval', array_column($ontario, 'id')));

$latest = latest_posts(4, $vus);
$mostRead = latest_posts(4, array_merge($vus, array_map('intval', array_column($latest, 'id'))));
$kcClock = fn (array $p) => str_replace(['am', 'pm'], ['a.m.', 'p.m.'], date('g:i a', strtotime((string) $p['published_at'])));
$kcWeather = trim(setting('weather_line'));
?>

<div class="kc-front in">
  <div class="cols">
    <div>
      <?php if ($hero): ?>
      <article class="kc-lead">
        <div class="kc-rulerow"><span class="kc-kicker" style="margin:0"><?= e(pp_desk_label($hero['category_slug'], $hero['category_name']) ?: 'The lead') ?></span></div>
        <h1 style="margin-top:18px"><a href="<?= e(post_href($hero)) ?>"><?= e($hero['title']) ?></a></h1>
        <?php if ($hero['lede']): ?><p class="dek"><?= e($hero['lede']) ?></p><?php endif; ?>
        <p class="kc-meta">
          <?php if ($hero['byline']): ?>By <?= e($hero['byline']) ?> &middot; <?php endif; ?>
          <?= e($kcClock($hero)) ?> &middot; <?= e(read_minutes($hero)) ?> min read
        </p>
        <figure><img src="<?= e(kc_art($hero)) ?>" alt=""></figure>
        <?php if ($hero['image'] && $hero['dateline']): ?><figcaption><?= e($hero['dateline']) ?>. <span>Sketch: Chronicle graphics</span></figcaption><?php endif; ?>
      </article>
      <?php endif; ?>

      <?php if ($duo): ?>
      <div class="kc-duo">
        <?php foreach ($duo as $p): ?>
        <article>
          <figure class="kc-cardfig"><img src="<?= e(kc_art($p)) ?>" alt=""></figure>
          <span class="kc-kicker"><?= e(pp_desk_label($p['category_slug'], $p['category_name'])) ?></span>
          <h2><a href="<?= e(post_href($p)) ?>"><?= e($p['title']) ?></a></h2>
          <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 160)) ?></p><?php endif; ?>
          <?php if ($p['byline']): ?><p class="kc-meta">By <?= e($p['byline']) ?></p><?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($trio): ?>
      <div class="kc-trio">
        <?php foreach ($trio as $p): ?>
        <article>
          <figure class="kc-cardfig kc-cardfig--s"><img src="<?= e(kc_art($p)) ?>" alt=""></figure>
          <span class="kc-kicker"><?= e(pp_desk_label($p['category_slug'], $p['category_name'])) ?></span>
          <h2><a href="<?= e(post_href($p)) ?>"><?= e($p['title']) ?></a></h2>
          <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 80)) ?></p><?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($ontario): ?>
      <section class="kc-ontario" aria-label="Ontario focus">
        <div class="head">
          <span class="kc-kicker kc-kicker--green" style="margin:0">Ontario focus</span>
          <span class="line"></span>
          <span class="tail">From the legislature</span>
        </div>
        <div class="grid">
          <?php foreach ($ontario as $p): ?>
          <article>
            <?php if ($p === $ontario[0]): ?><figure class="kc-cardfig"><img src="<?= e(kc_art($p)) ?>" alt=""></figure><?php endif; ?>
            <h2><a href="<?= e(post_href($p)) ?>"><?= e($p['title']) ?></a></h2>
            <?php if ($p === $ontario[0] && $p['lede']): ?><p><?= e(excerpt($p['lede'], 170)) ?></p><?php endif; ?>
          </article>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if (!$hero && !$duo && !$trio): ?>
      <p class="kc-empty">The newsroom hasn&rsquo;t filed yet. The first edition is on its way.</p>
      <?php endif; ?>
    </div>

    <aside class="kc-rail">
      <?php if ($latest): ?>
      <div class="box">
        <h2>Latest</h2>
        <?php foreach ($latest as $p): ?>
        <div class="row">
          <div class="when"><?= e($kcClock($p)) ?></div>
          <a href="<?= e(post_href($p)) ?>"><?= e($p['title']) ?></a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($kcWeather !== ''): ?>
      <div class="box kc-weather">
        <h2>Weather</h2>
        <?php [$kcDeg, $kcCond] = array_pad(explode('|', $kcWeather, 2), 2, ''); ?>
        <div class="deg">
          <span class="t"><?= e(trim($kcDeg)) ?></span>
          <span class="w"><?= e(trim($kcCond)) ?></span>
        </div>
        <div class="src">Kitchener &middot; Region of Waterloo Airport</div>
      </div>
      <?php endif; ?>

      <?php if ($mostRead): ?>
      <div class="box">
        <h2>Most read</h2>
        <?php foreach ($mostRead as $i => $p): ?>
        <div class="row rank">
          <span class="n"><?= $i + 1 ?></span>
          <a href="<?= e(post_href($p)) ?>"><?= e($p['title']) ?></a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </aside>
  </div>
</div>
