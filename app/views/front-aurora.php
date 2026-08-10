<?php
/**
 * The aurora front page (chrome.template = "aurora").
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * Full-bleed photo hero → Top stories list → one tile per remaining desk
 * ("Across the <region>") → rail (Most read, ad, This week events) →
 * the newsletter band. The hero backdrop is the featured story's image,
 * falling back to the site's aurora-hero brand asset.
 */

$shown = $hero ? [(int) $hero['id']] : [];
$top = latest_posts(3, $shown);
$shown = array_merge($shown, array_map('intval', array_column($top, 'id')));

// One latest story per desk, for desks not already on the page.
$tiles = [];
foreach (categories_all() as $cat) {
    $got = posts_in_category((int) $cat['id'], 1, $shown);
    if ($got) {
        $tiles[] = $got[0];
        $shown[] = (int) $got[0]['id'];
    }
    if (count($tiles) === 3) {
        break;
    }
}

$events = setting_json('events_items');
$chrome = pp_brand_file()['chrome'] ?? [];
$crosshead = is_string($chrome['crosshead'] ?? null) && $chrome['crosshead'] !== '' ? $chrome['crosshead'] : 'Across the region';
$eventsLabel = is_string($chrome['events_label'] ?? null) && $chrome['events_label'] !== '' ? $chrome['events_label'] : 'This week';
$heroBg = $hero && $hero['image'] ? $hero['image'] : site_asset('aurora-hero.png');
?>

<?php if ($hero): ?>
<section class="ga-hero" style="background-image:url('<?= e($heroBg) ?>')">
  <div class="inner">
    <?php if (!empty($hero['category_name'])): ?>
    <a class="kick" href="<?= e(url('desk/' . $hero['category_slug'])) ?>"><?= e($hero['category_name']) ?></a>
    <?php endif; ?>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><p class="dek"><?= e($hero['lede']) ?></p><?php endif; ?>
    <p class="by"><?= dateline($hero) ?></p>
  </div>
</section>
<?php endif; ?>

<div class="ga-front">
  <div class="ga-main">
    <?php if (!$hero && !$top): ?>
    <div class="empty">No stories published yet. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first one.</div>
    <?php endif; ?>

    <?php if ($top): ?>
    <section aria-label="Top stories">
      <div class="ga-h2row">
        <h2>Top stories</h2>
        <a class="more" href="<?= e(url('search')) ?>">View all →</a>
      </div>
      <?php foreach ($top as $p): ?>
      <article class="ga-item">
        <a class="th" href="<?= e(url('story/' . $p['slug'])) ?>" tabindex="-1" aria-hidden="true">
          <img src="<?= e($p['image'] ?: site_asset('og-default.png')) ?>" alt="" loading="lazy">
        </a>
        <div>
          <?php if (!empty($p['category_name'])): ?>
          <a class="ga-kick" href="<?= e(url('desk/' . $p['category_slug'])) ?>" style="color:<?= e(pp_desk_hex($p['category_slug'], $p['category_color'])) ?>"><?= e($p['category_name']) ?></a>
          <?php endif; ?>
          <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
          <?php if ($p['lede']): ?><p class="dek"><?= e(excerpt($p['lede'], 120)) ?></p><?php endif; ?>
          <p class="meta"><?= e(time_label($p['published_at'])) ?><?= $p['byline'] !== '' ? ' · ' . e($p['byline']) : '' ?></p>
        </div>
      </article>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?= ad_slot('top') ?>

    <?php if ($tiles): ?>
    <section class="ga-block" aria-label="Across the region">
      <div class="ga-h2row">
        <h2><?= e($crosshead) ?></h2>
      </div>
      <div class="ga-grid3">
        <?php foreach ($tiles as $p): ?>
        <article class="ga-tile">
          <a class="th" href="<?= e(url('story/' . $p['slug'])) ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= e($p['image'] ?: site_asset('og-default.png')) ?>" alt="" loading="lazy">
          </a>
          <?php if (!empty($p['category_name'])): ?>
          <a class="ga-kick" href="<?= e(url('desk/' . $p['category_slug'])) ?>" style="color:<?= e(pp_desk_hex($p['category_slug'], $p['category_color'])) ?>"><?= e($p['category_name']) ?></a>
          <?php endif; ?>
          <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
          <p class="meta"><?= e(time_label($p['published_at'])) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>

  <aside class="ga-rail">
    <?php if (pp_chrome('trending')): ?>
    <div class="ga-railmod">
      <p class="lab">Most read</p>
      <ol class="ga-mostread">
        <?php foreach (trending_posts(5) as $tp): ?>
        <li><a href="<?= e(url('story/' . $tp['slug'])) ?>"><?= e($tp['title']) ?></a></li>
        <?php endforeach; ?>
      </ol>
    </div>
    <?php endif; ?>

    <?= ad_slot('rail') ?>

    <?php if ($events): ?>
    <div class="ga-railmod">
      <p class="lab"><?= e($eventsLabel) ?></p>
      <?php foreach ($events as $row):
          [$label, $href, $when, $venue] = array_pad((array) $row, 4, '');
          $parts = $when !== '' ? preg_split('/\s+/', trim($when), 2) : [];
      ?>
      <div class="ga-ev">
        <?php if ($parts): ?>
        <div class="d"><span class="m"><?= e($parts[0]) ?></span><span class="n"><?= e($parts[1] ?? '') ?></span></div>
        <?php else: ?>
        <div class="d"><span class="n">·</span></div>
        <?php endif; ?>
        <div>
          <p class="t"><?php if ($href !== '' && $href !== '#'): ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?php else: ?><?= e($label) ?><?php endif; ?></p>
          <?php if ($venue !== ''): ?><p class="v"><?= e($venue) ?></p><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </aside>
</div>

<section class="ga-nlband" style="background-image:url('<?= e(site_asset('wisp-band.png')) ?>')" aria-label="Newsletter">
  <div class="inner">
    <div>
      <p class="lab">Newsletter</p>
      <h3><?= e(setting('newsletter_heading', 'The 6 a.m.')) ?></h3>
      <p><?= e(setting('newsletter_copy')) ?></p>
    </div>
    <div>
      <?php if (isset($_GET['subscribed'])): ?>
      <p class="done"><?= isset($_GET['confirm']) ? 'Nearly there — check your inbox for the confirmation link.' : "You're on the list. The next edition lands at 6 a.m." ?></p>
      <?php else: ?>
      <form method="post" action="<?= e(url('subscribe')) ?>">
        <input type="email" name="email" required placeholder="Your email address" aria-label="Email address">
        <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button class="ga-btn ga-btn--solid" type="submit">Subscribe</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</section>
