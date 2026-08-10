<?php
/**
 * The current front page (chrome.template = "current").
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * Lead package (hero + briefing rail) → B.C. in Brief (numbered,
 * text-first) → Across the Regions → Politics & Public Life →
 * Okanagan Life (mist panel) → the Morning Current newsletter block.
 * Section heads carry the guide's standing descriptions via chrome keys.
 */

$shown = $hero ? [(int) $hero['id']] : [];
$brief = latest_posts(4, $shown);
$shown = array_merge($shown, array_map('intval', array_column($brief, 'id')));

$catBySlug = [];
foreach (categories_all() as $c) {
    $catBySlug[$c['slug']] = $c;
}
$inCat = function (string $slug, int $n, array $skip) use ($catBySlug): array {
    return isset($catBySlug[$slug]) ? posts_in_category((int) $catBySlug[$slug]['id'], $n, $skip) : [];
};

$bcbrief = latest_posts(4, $shown);
$shown = array_merge($shown, array_map('intval', array_column($bcbrief, 'id')));

// Across the Regions: the region desk leads; two more desks fill in.
$chrome = pp_brand_file()['chrome'] ?? [];
$regionDesk = is_string($chrome['region_desk'] ?? null) && $chrome['region_desk'] !== '' ? $chrome['region_desk'] : 'news';
$regions = $inCat($regionDesk, 1, $shown);
$shown = array_merge($shown, array_map('intval', array_column($regions, 'id')));
foreach (categories_all() as $cat) {
    if (count($regions) >= 3) {
        break;
    }
    if (in_array($cat['slug'], [$regionDesk, 'opinion', 'politics', 'culture'], true)) {
        continue;
    }
    $got = posts_in_category((int) $cat['id'], 1, $shown);
    if ($got && $got[0]['image'] !== '') {   // the region cards are visual
        $regions[] = $got[0];
        $shown[] = (int) $got[0]['id'];
    }
}

$plead = $inCat('politics', 1, $shown);
$plead = $plead ? $plead[0] : null;
if ($plead) {
    $shown[] = (int) $plead['id'];
}
$prows = array_merge($inCat('politics', 3, $shown), $inCat('opinion', 1, $shown));
$shown = array_merge($shown, array_map('intval', array_column($prows, 'id')));

$life = $inCat('culture', 3, $shown);

$secText = function (string $key, string $fallback) use ($chrome): string {
    return is_string($chrome[$key] ?? null) && $chrome[$key] !== '' ? $chrome[$key] : $fallback;
};

// Cards are whole-card links, so the eyebrow must not nest another anchor.
$eyebrow = function (array $p): string {
    if (empty($p['category_name'])) {
        return '';
    }
    return '<span class="cu-eyebrow" style="color:' . e(pp_desk_hex($p['category_slug'], $p['category_color'])) . '">' . e($p['category_name']) . '</span>';
};
?>

<div class="cu-main">
  <section class="cu-lead" aria-label="Lead stories">
    <?php if ($hero): ?>
    <article class="cu-hero">
      <a href="<?= e(url('story/' . $hero['slug'])) ?>">
        <?php if ($hero['image']): ?>
        <div class="art" aria-hidden="true"><img src="<?= e($hero['image']) ?>" alt="" loading="eager"></div>
        <?php endif; ?>
        <?= $eyebrow($hero) ?>
        <h1 class="cu-headline"><?= e($hero['title']) ?></h1>
        <?php if ($hero['lede']): ?><p class="cu-dek"><?= e($hero['lede']) ?></p><?php endif; ?>
        <p class="cu-meta"><?= dateline($hero) ?></p>
      </a>
    </article>
    <?php else: ?>
    <div class="empty">No stories published yet. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first one.</div>
    <?php endif; ?>

    <aside class="cu-brief" aria-label="Today's briefing">
      <div class="ti"><h2>Today's briefing</h2><span>5 min read</span></div>
      <?php foreach ($brief as $p): ?>
      <a class="it" href="<?= e(url('story/' . $p['slug'])) ?>">
        <div>
          <?= $eyebrow($p) ?>
          <h3><?= e($p['title']) ?></h3>
        </div>
        <div class="th"><img src="<?= e($p['image'] ?: site_asset('og-default.png')) ?>" alt="" loading="lazy"></div>
      </a>
      <?php endforeach; ?>
    </aside>
  </section>

  <?= ad_slot('top') ?>

  <?php if ($bcbrief): ?>
  <section class="cu-section" aria-label="B.C. in Brief">
    <div class="cu-sechead">
      <h2><?= e($secText('brief_head', 'B.C. in Brief')) ?></h2>
      <p><?= e($secText('brief_sub', 'Fast, consequential updates from every region of the province — with the context that turns an update into understanding.')) ?></p>
    </div>
    <div class="cu-briefgrid">
      <?php $n = 0; foreach ($bcbrief as $p): $n++; ?>
      <a class="cu-briefcard" href="<?= e(url('story/' . $p['slug'])) ?>">
        <span class="no"><?= sprintf('%02d', $n) ?></span>
        <?= $eyebrow($p) ?>
        <h3><?= e($p['title']) ?></h3>
        <p class="cu-meta"><?= e(time_label($p['published_at'])) ?></p>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($regions): ?>
  <section class="cu-section" aria-label="Across the Regions">
    <div class="cu-sechead">
      <h2><?= e($secText('regions_head', 'Across the Regions')) ?></h2>
      <p><?= e($secText('regions_sub', 'Home base leads; the reporting map spans every community around it.')) ?></p>
    </div>
    <div class="cu-regions">
      <?php foreach ($regions as $i => $p): ?>
      <a class="cu-scard<?= $i === 0 ? ' feature' : '' ?>" href="<?= e(url('story/' . $p['slug'])) ?>">
        <div class="art"><img src="<?= e($p['image'] ?: site_asset('og-default.png')) ?>" alt="" loading="lazy"></div>
        <div class="cp">
          <?= $eyebrow($p) ?>
          <h3><?= e($p['title']) ?></h3>
          <?php if ($p['lede']): ?><p class="cu-dek"><?= e(excerpt($p['lede'], 120)) ?></p><?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($plead || $prows): ?>
  <section class="cu-section" aria-label="Politics and public life">
    <div class="cu-sechead">
      <h2><?= e($secText('politics_head', 'Politics & Public Life')) ?></h2>
      <p><?= e($secText('politics_sub', 'Institutions, decisions, money and power — reported for readers who want to know how the province actually works.')) ?></p>
    </div>
    <div class="cu-policy">
      <?php if ($plead): ?>
      <a class="cu-plead" href="<?= e(url('story/' . $plead['slug'])) ?>">
        <div class="art"><img src="<?= e($plead['image'] ?: site_asset('og-default.png')) ?>" alt="" loading="lazy"></div>
        <div class="cp">
          <span class="cu-eyebrow"><?= e($plead['category_name'] ?? 'Politics') ?></span>
          <h3><?= e($plead['title']) ?></h3>
          <?php if ($plead['lede']): ?><p class="cu-dek"><?= e(excerpt($plead['lede'], 130)) ?></p><?php endif; ?>
        </div>
      </a>
      <?php endif; ?>
      <div class="cu-plist">
        <?php foreach ($prows as $p): ?>
        <a class="cu-prow" href="<?= e(url('story/' . $p['slug'])) ?>">
          <?= $eyebrow($p) ?>
          <h3><?= e($p['title']) ?></h3>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($life): ?>
  <section class="cu-section cu-life" aria-label="Life and culture">
    <div class="cu-sechead">
      <h2><?= e($secText('life_head', 'Okanagan Life')) ?></h2>
      <p><?= e($secText('life_sub', 'Food, wine, art, water, outdoors and the people shaping the cultural life of the valley.')) ?></p>
    </div>
    <div class="cu-lifegrid">
      <?php foreach ($life as $p): ?>
      <a class="cu-lifecard" href="<?= e(url('story/' . $p['slug'])) ?>">
        <div class="art"><img src="<?= e($p['image'] ?: site_asset('og-default.png')) ?>" alt="" loading="lazy"></div>
        <?= $eyebrow($p) ?>
        <h3><?= e($p['title']) ?></h3>
        <?php if ($p['lede']): ?><p class="cu-dek"><?= e(excerpt($p['lede'], 110)) ?></p><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="cu-news" aria-label="Newsletter">
    <div>
      <span class="cu-eyebrow"><?= e(setting('newsletter_heading', 'The 6 a.m.')) ?></span>
      <h2><?= e($secText('news_head', 'Start with the province in focus.')) ?></h2>
      <p><?= e(setting('newsletter_copy')) ?></p>
    </div>
    <?php if (isset($_GET['subscribed'])): ?>
    <p class="done"><?= isset($_GET['confirm']) ? 'Nearly there — check your inbox for the confirmation link.' : "You're on the list." ?></p>
    <?php else: ?>
    <form method="post" action="<?= e(url('subscribe')) ?>">
      <label for="cu-email" style="position:absolute;left:-10000px">Email address</label>
      <input id="cu-email" type="email" name="email" required placeholder="Email address">
      <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
      <button type="submit">Join free</button>
    </form>
    <?php endif; ?>
  </section>
</div>
