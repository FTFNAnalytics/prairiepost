<?php
/**
 * The pacific front page (chrome.template = "pacific").
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * Lead story + rail (Opinion, Trending now, ad, newsletter card) →
 * two-up secondary stories → the "Across ..." band (one story per desk).
 * One photograph carries the screen; rules, not boxes, divide it.
 */

$shown = $hero ? [(int) $hero['id']] : [];
$duo = latest_posts(2, $shown);
$shown = array_merge($shown, array_map('intval', array_column($duo, 'id')));

$catBySlug = [];
foreach (categories_all() as $c) {
    $catBySlug[$c['slug']] = $c;
}
$opinion = isset($catBySlug['opinion']) ? posts_in_category((int) $catBySlug['opinion']['id'], 3, $shown) : [];
$shown = array_merge($shown, array_map('intval', array_column($opinion, 'id')));

// One latest story per desk for the band, skipping what's already up.
$band = [];
foreach (categories_all() as $cat) {
    if ($cat['slug'] === 'opinion') {
        continue;
    }
    $got = posts_in_category((int) $cat['id'], 1, $shown);
    if ($got) {
        $band[] = $got[0];
        $shown[] = (int) $got[0]['id'];
    }
    if (count($band) === 6) {
        break;
    }
}

// Trending now: the tags carried by the most-read recent stories.
$chips = db()->query(
    'SELECT t.name, t.slug, SUM(p.views) AS w FROM tags t
     JOIN post_tags pt ON pt.tag_id = t.id
     JOIN posts p ON p.id = pt.post_id ' . pp_site_join() . "
     WHERE p.status = 'published'
     GROUP BY t.id, t.name, t.slug ORDER BY w DESC LIMIT 7"
)->fetchAll();

$chrome = pp_brand_file()['chrome'] ?? [];
$bandHead = is_string($chrome['crosshead'] ?? null) && $chrome['crosshead'] !== '' ? $chrome['crosshead'] : 'Across the region';

$kick = function (array $p): string {
    if (empty($p['category_name'])) {
        return '';
    }
    return '<a class="pf-kick pf-caps" href="' . e(url('desk/' . $p['category_slug'])) . '" style="color:' . e(pp_desk_hex($p['category_slug'], $p['category_color'])) . '">' . e($p['category_name']) . '</a>';
};
?>

<div class="pf-front">
  <div class="pf-main">
    <?php if ($hero): ?>
    <article class="pf-lead">
      <?php if ($hero['image']): ?>
      <a class="ph" href="<?= e(url('story/' . $hero['slug'])) ?>" tabindex="-1" aria-hidden="true">
        <img src="<?= e($hero['image']) ?>" alt="" loading="eager">
      </a>
      <?php endif; ?>
      <?= $kick($hero) ?>
      <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
      <?php if ($hero['lede']): ?><p class="dek"><?= e($hero['lede']) ?></p><?php endif; ?>
      <p class="pf-by"><?= dateline($hero) ?></p>
    </article>
    <?php else: ?>
    <div class="empty">No stories published yet. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first one.</div>
    <?php endif; ?>

    <?php if ($duo): ?>
    <div class="pf-duo">
      <?php foreach ($duo as $p): ?>
      <article>
        <?php if ($p['image']): ?>
        <a class="ph" href="<?= e(url('story/' . $p['slug'])) ?>" tabindex="-1" aria-hidden="true">
          <img src="<?= e($p['image']) ?>" alt="" loading="lazy">
        </a>
        <?php endif; ?>
        <?= $kick($p) ?>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 130)) ?></p><?php endif; ?>
        <p class="pf-by"><?= e(time_label($p['published_at'])) ?><?= $p['byline'] !== '' ? ' · ' . e($p['byline']) : '' ?></p>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?= ad_slot('top') ?>
  </div>

  <aside class="pf-rail">
    <?php if ($opinion): ?>
    <div class="pf-railmod">
      <div class="rule"></div>
      <h4>Opinion</h4>
      <div class="pf-oplist">
        <?php foreach ($opinion as $p): ?>
        <article>
          <h5><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h5>
          <p class="a"><?= e($p['byline']) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($chips && pp_chrome('trending')): ?>
    <div class="pf-railmod">
      <div class="rule"></div>
      <h4>Trending now</h4>
      <div class="pf-chips">
        <?php foreach ($chips as $t): ?>
        <a href="<?= e(url('search') . '?q=' . rawurlencode($t['name'])) ?>"><?= e($t['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?= ad_slot('rail') ?>

    <div class="pf-railmod pf-nlcard">
      <div class="rule rule--inlet"></div>
      <h4><?= e(setting('newsletter_heading', 'The 6 a.m.')) ?></h4>
      <p><?= e(setting('newsletter_copy')) ?></p>
      <?php if (isset($_GET['subscribed'])): ?>
      <p class="done"><?= isset($_GET['confirm']) ? 'Nearly there — check your inbox for the confirmation link.' : "You're on the list." ?></p>
      <?php else: ?>
      <form method="post" action="<?= e(url('subscribe')) ?>">
        <input type="email" name="email" required placeholder="you@example.com" aria-label="Email address">
        <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button class="pf-btn pf-btn--inlet" type="submit">Sign up</button>
      </form>
      <?php endif; ?>
    </div>
  </aside>
</div>

<?php if ($band): ?>
<section class="pf-band" aria-label="<?= e($bandHead) ?>">
  <div class="pf-bandhead">
    <h2><?= e($bandHead) ?></h2>
    <a href="<?= e(url('search')) ?>">All the news</a>
  </div>
  <div class="pf-bandgrid">
    <?php foreach ($band as $p): ?>
    <article class="pf-cell">
      <a class="ph" href="<?= e(url('story/' . $p['slug'])) ?>" tabindex="-1" aria-hidden="true">
        <img src="<?= e($p['image'] ?: site_asset('og-default.png')) ?>" alt="" loading="lazy">
      </a>
      <?= $kick($p) ?>
      <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      <p class="meta"><?= e(time_label($p['published_at'])) ?></p>
    </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
