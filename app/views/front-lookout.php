<?php
/**
 * The lookout front page (chrome.template = "lookout").
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * Lead package (hero + the Watch rail) → Ontario in Brief (numbered,
 * text-first) → From the Forks → City Hall & Queen's Park →
 * Forest City Life (fog panel) → the newsletter block. A lookout is a
 * vantage point: the Watch rail is the paper's standing promise to keep
 * eyes on files after they stop trending, and it leads the page.
 * Section heads carry the site's standing descriptions via chrome keys.
 */

$shown = $hero ? [(int) $hero['id']] : [];
$watch = latest_posts(4, $shown);
$shown = array_merge($shown, array_map('intval', array_column($watch, 'id')));

$catBySlug = [];
foreach (categories_all() as $c) {
    $catBySlug[$c['slug']] = $c;
}
$inCat = function (string $slug, int $n, array $skip) use ($catBySlug): array {
    return isset($catBySlug[$slug]) ? posts_in_category((int) $catBySlug[$slug]['id'], $n, $skip) : [];
};

$brief = latest_posts(4, $shown);
$shown = array_merge($shown, array_map('intval', array_column($brief, 'id')));

// From the Forks: the home desk leads; two more desks fill in with art.
$chrome = pp_brand_file()['chrome'] ?? [];
$regionDesk = is_string($chrome['region_desk'] ?? null) && $chrome['region_desk'] !== '' ? $chrome['region_desk'] : 'news';
$forks = $inCat($regionDesk, 1, $shown);
$shown = array_merge($shown, array_map('intval', array_column($forks, 'id')));
foreach (categories_all() as $cat) {
    if (count($forks) >= 3) {
        break;
    }
    if (in_array($cat['slug'], [$regionDesk, 'opinion', 'politics', 'culture'], true)) {
        continue;
    }
    $got = posts_in_category((int) $cat['id'], 1, $shown);
    if ($got && $got[0]['image'] !== '') {   // the forks cards are visual
        $forks[] = $got[0];
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
    return '<span class="lo-eyebrow" style="color:' . e(pp_desk_hex($p['category_slug'], $p['category_color'])) . '">' . e($p['category_name']) . '</span>';
};
?>

<div class="lo-main">
  <section class="lo-lead" aria-label="Lead stories">
    <?php if ($hero): ?>
    <article class="lo-hero">
      <a href="<?= e(url('story/' . $hero['slug'])) ?>">
        <?php if ($hero['image']): ?>
        <div class="art" aria-hidden="true"><img src="<?= e($hero['image']) ?>" alt="" loading="eager"></div>
        <?php endif; ?>
        <?= $eyebrow($hero) ?>
        <h1 class="lo-headline"><?= e($hero['title']) ?></h1>
        <?php if ($hero['lede']): ?><p class="lo-dek"><?= e($hero['lede']) ?></p><?php endif; ?>
        <p class="lo-meta"><?= dateline($hero) ?></p>
      </a>
    </article>
    <?php else: ?>
    <div class="empty">No stories published yet. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first one.</div>
    <?php endif; ?>

    <aside class="lo-watch" aria-label="The Watch">
      <div class="ti"><h2><?= e($secText('watch_head', 'The Watch')) ?></h2><span>Kept open</span></div>
      <p class="sub"><?= e($secText('watch_sub', 'The files this paper keeps open — followed until they resolve, not until they stop trending.')) ?></p>
      <?php $n = 0; foreach ($watch as $p): $n++; ?>
      <a class="it" href="<?= e(url('story/' . $p['slug'])) ?>">
        <span class="no"><?= sprintf('%02d', $n) ?></span>
        <div>
          <?= $eyebrow($p) ?>
          <h3><?= e($p['title']) ?></h3>
          <p class="lo-meta"><?= e(time_label($p['published_at'])) ?></p>
        </div>
      </a>
      <?php endforeach; ?>
    </aside>
  </section>

  <?= ad_slot('top') ?>

  <?php if ($brief): ?>
  <section class="lo-section" aria-label="Ontario in Brief">
    <div class="lo-sechead">
      <h2><?= e($secText('brief_head', 'Ontario in Brief')) ?></h2>
      <p><?= e($secText('brief_sub', 'Fast, consequential updates from across the province — with the context that turns an update into understanding.')) ?></p>
    </div>
    <div class="lo-briefgrid">
      <?php $n = 0; foreach ($brief as $p): $n++; ?>
      <a class="lo-briefcard" href="<?= e(url('story/' . $p['slug'])) ?>">
        <span class="no"><?= sprintf('%02d', $n) ?></span>
        <?= $eyebrow($p) ?>
        <h3><?= e($p['title']) ?></h3>
        <p class="lo-meta"><?= e(time_label($p['published_at'])) ?></p>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($forks): ?>
  <section class="lo-section" aria-label="From the Forks">
    <div class="lo-sechead">
      <h2><?= e($secText('forks_head', 'From the Forks')) ?></h2>
      <p><?= e($secText('forks_sub', 'Home base leads; the reporting map spans every community around it.')) ?></p>
    </div>
    <div class="lo-forks">
      <?php foreach ($forks as $i => $p): ?>
      <a class="lo-scard<?= $i === 0 ? ' feature' : '' ?>" href="<?= e(url('story/' . $p['slug'])) ?>">
        <div class="art"><img src="<?= e($p['image'] ?: site_asset('og-default.png')) ?>" alt="" loading="lazy"></div>
        <div class="cp">
          <?= $eyebrow($p) ?>
          <h3><?= e($p['title']) ?></h3>
          <?php if ($p['lede']): ?><p class="lo-dek"><?= e(excerpt($p['lede'], 120)) ?></p><?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($plead || $prows): ?>
  <section class="lo-section" aria-label="City Hall and Queen's Park">
    <div class="lo-sechead">
      <h2><?= e($secText('politics_head', "City Hall & Queen's Park")) ?></h2>
      <p><?= e($secText('politics_sub', 'Two chambers decide most of what changes here.')) ?></p>
    </div>
    <div class="lo-policy">
      <?php if ($plead): ?>
      <a class="lo-plead" href="<?= e(url('story/' . $plead['slug'])) ?>">
        <div class="art"><img src="<?= e($plead['image'] ?: site_asset('og-default.png')) ?>" alt="" loading="lazy"></div>
        <div class="cp">
          <span class="lo-eyebrow"><?= e($plead['category_name'] ?? 'Politics') ?></span>
          <h3><?= e($plead['title']) ?></h3>
          <?php if ($plead['lede']): ?><p class="lo-dek"><?= e(excerpt($plead['lede'], 130)) ?></p><?php endif; ?>
        </div>
      </a>
      <?php endif; ?>
      <div class="lo-plist">
        <?php foreach ($prows as $p): ?>
        <a class="lo-prow" href="<?= e(url('story/' . $p['slug'])) ?>">
          <?= $eyebrow($p) ?>
          <h3><?= e($p['title']) ?></h3>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($life): ?>
  <section class="lo-section lo-life" aria-label="Life and culture">
    <div class="lo-sechead">
      <h2><?= e($secText('life_head', 'Forest City Life')) ?></h2>
      <p><?= e($secText('life_sub', 'Stages, studios, rinks, patios and the river — the life the city actually lives.')) ?></p>
    </div>
    <div class="lo-lifegrid">
      <?php foreach ($life as $p): ?>
      <a class="lo-lifecard" href="<?= e(url('story/' . $p['slug'])) ?>">
        <div class="art"><img src="<?= e($p['image'] ?: site_asset('og-default.png')) ?>" alt="" loading="lazy"></div>
        <?= $eyebrow($p) ?>
        <h3><?= e($p['title']) ?></h3>
        <?php if ($p['lede']): ?><p class="lo-dek"><?= e(excerpt($p['lede'], 110)) ?></p><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="lo-news" aria-label="Newsletter">
    <div>
      <span class="lo-eyebrow"><?= e(setting('newsletter_heading', 'The Lookout at Six')) ?></span>
      <h2><?= e($secText('news_head', 'See the whole board before nine.')) ?></h2>
      <p><?= e(setting('newsletter_copy')) ?></p>
    </div>
    <?php if (isset($_GET['subscribed'])): ?>
    <p class="done"><?= isset($_GET['confirm']) ? 'Nearly there — check your inbox for the confirmation link.' : "You're on the list." ?></p>
    <?php else: ?>
    <form method="post" action="<?= e(url('subscribe')) ?>">
      <label for="lo-email" style="position:absolute;left:-10000px">Email address</label>
      <input id="lo-email" type="email" name="email" required placeholder="Email address">
      <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
      <button type="submit">Join free</button>
    </form>
    <?php endif; ?>
  </section>
</div>
