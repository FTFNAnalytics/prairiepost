<?php
/**
 * Broadsheet-dark front page (chrome.template = "echo-v3").
 * Included by index.php with $hero, $river, $weather already loaded.
 * Left rail: latest titles, sections, traffic (setting), events (setting).
 * Centre: lead story + quick-news list. Right: trending + weather card.
 */

$quick = latest_posts(5, $hero ? [(int) $hero['id']] : []);
$latestTitles = array_slice($quick, 0, 5);
$traffic = pp_fresh_rows('traffic_items');
$events = pp_events_upcoming(setting_json('events_items'));
$sections = pp_brand_file()['sections'] ?? [];
?>

<div class="v3-front">

  <div class="v3-leftrail">
    <div class="v3-box">
      <div class="bh">Latest news</div>
      <div class="bl">
        <?php foreach ($latestTitles as $p): ?>
        <a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e(excerpt($p['title'], 42)) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($sections): ?>
    <div class="v3-box">
      <div class="bh">Sections</div>
      <div class="bl">
        <?php foreach ($sections as $s): if (empty($s['label']) || empty($s['href'])) continue; ?>
        <a href="<?= e($s['href']) ?>"><?= e($s['label']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($traffic): ?>
    <div class="v3-box">
      <div class="bh">Traffic</div>
      <div class="bl">
        <?php foreach ($traffic as $t): [$label, $href] = array_pad((array) $t, 2, ''); ?>
        <a href="<?= e($href ?: '#') ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($events): ?>
    <div class="v3-box">
      <div class="bh">Events</div>
      <div class="bl">
        <?php foreach ($events as $t): [$label, $href] = array_pad((array) $t, 2, ''); ?>
        <a href="<?= e($href ?: '#') ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="v3-well">
    <div class="v3-wellhead">
      <h2 class="qn">Quick News</h2>
      <form class="v3-search" method="get" action="<?= e(url('search')) ?>" role="search">
        <input type="text" name="q" placeholder="Search the <?= e(preg_replace('/^The\s+/i', '', setting('site_title'))) ?>" aria-label="Search the archive">
        <button type="submit" aria-label="Search">
          <svg width="16" height="16" viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="6" fill="none" stroke="#fff" stroke-width="2"></circle><path d="M13 13l5 5" stroke="#fff" stroke-width="2" stroke-linecap="round"></path></svg>
        </button>
      </form>
    </div>

    <?php if ($hero): ?>
    <a class="v3-lead" href="<?= e(url('story/' . $hero['slug'])) ?>" aria-hidden="true" tabindex="-1">
      <?php if ($hero['image']): ?><img src="<?= e($hero['image']) ?>" alt="" loading="eager"><?php endif; ?>
      <span class="badge">Top story</span>
    </a>
    <h3 class="hed"><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h3>
    <?php if ($hero['lede']): ?><p class="dek"><?= e($hero['lede']) ?></p><?php endif; ?>
    <div class="leadmeta">
      <a class="go" href="<?= e(url('story/' . $hero['slug'])) ?>">Read the story</a>
      <span class="m"><?= e($hero['category_name'] ?? '') ?><?= $hero['category_name'] ? ' · ' : '' ?><?= e(time_label($hero['published_at'])) ?></span>
    </div>
    <?php else: ?>
    <div class="empty">No stories published yet. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first one.</div>
    <?php endif; ?>

    <div class="v3-list">
      <?php foreach ($quick as $p): ?>
      <div class="v3-item">
        <a class="th" href="<?= e(url('story/' . $p['slug'])) ?>" tabindex="-1" aria-hidden="true">
          <img src="<?= e($p['image'] ?: site_asset('og-default.png')) ?>" alt="" loading="lazy">
        </a>
        <div>
          <a class="t" href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a>
          <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 120)) ?></p><?php endif; ?>
          <div class="m"><?= e($p['category_name'] ?? '') ?><?= $p['category_name'] ? ' · ' : '' ?><?= e(time_label($p['published_at'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?= ad_slot('top') ?>
  </div>

  <div class="v3-rightrail">
    <div class="v3-card v3-trend">
      <div class="ch">Trending now</div>
      <?php $n = 0; foreach (trending_posts(5) as $tp): $n++; ?>
      <div class="row">
        <span class="n"><?= $n ?></span>
        <div>
          <a href="<?= e(url('story/' . $tp['slug'])) ?>"><?= e($tp['title']) ?></a>
          <div class="when"><?= e(time_label($tp['published_at'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($weather): ?>
    <div class="v3-card v3-wx">
      <div class="ch">
        <span><?= e(preg_replace('/^The\s+/i', '', setting('site_title'))) ?> weather</span>
        <a href="<?= e($weather['url'] ?? '') ?>" rel="external">Full forecast →</a>
      </div>
      <div class="now">
        <div style="flex:1">
          <div class="temp"><?= e($weather['temp'] ?? '') ?></div>
          <div class="cond"><?= e($weather['line'] ?? '') ?></div>
        </div>
      </div>
      <div class="facts">
        <div><div class="k">High</div><div class="v"><?= e($weather['hi'] ?? '—') ?></div></div>
        <div><div class="k">Low</div><div class="v"><?= e($weather['lo'] ?? '—') ?></div></div>
        <div><div class="k"><?= e($weather['fact_label'] ?? 'Humidity') ?></div><div class="v"><?= e($weather['fact'] ?? '—') ?></div></div>
      </div>
      <div class="src pp-meta"><?= $weather['source'] ?? '' ?></div>
    </div>
    <?php endif; ?>

    <div class="v3-card"><?= signup_block() ?></div>

    <?= ad_slot('rail') ?>
  </div>
</div>
