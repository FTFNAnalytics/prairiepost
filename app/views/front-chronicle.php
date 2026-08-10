<?php
/**
 * The chronicle front page (chrome.template = "chronicle").
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * Full-bleed photo hero → second lead + Latest rail + Field Notes →
 * three-up cards → the Coast Report band (newsletter) → Opinion and
 * Communities columns. The hero backdrop is the featured story's image,
 * falling back to the site's og-default brand card.
 */

$shown = $hero ? [(int) $hero['id']] : [];
$lead2 = latest_posts(1, $shown);
$lead2 = $lead2 ? $lead2[0] : null;
if ($lead2) {
    $shown[] = (int) $lead2['id'];
}
$latest = latest_posts(5, $hero ? [(int) $hero['id']] : []);
$cards = latest_posts(3, $shown);
$shown = array_merge($shown, array_map('intval', array_column($cards, 'id')));

$catBySlug = [];
foreach (categories_all() as $c) {
    $catBySlug[$c['slug']] = $c;
}
$opinion = isset($catBySlug['opinion']) ? posts_in_category((int) $catBySlug['opinion']['id'], 2, $shown) : [];
$communities = isset($catBySlug['communities']) ? posts_in_category((int) $catBySlug['communities']['id'], 4, $shown) : [];

$chrome = pp_brand_file()['chrome'] ?? [];
$notesLabel = is_string($chrome['field_notes_label'] ?? null) && $chrome['field_notes_label'] !== '' ? $chrome['field_notes_label'] : 'Field notes';
$notesLink = is_string($chrome['field_notes_link'] ?? null) && $chrome['field_notes_link'] !== '' ? $chrome['field_notes_link'] : 'Read more →';
$notesText = setting('field_notes_text');
$notesUrl = setting('field_notes_url');
$heroBg = $hero && $hero['image'] ? $hero['image'] : site_asset('og-default.png');

$kick = function (array $p): string {
    if (empty($p['category_name'])) {
        return '';
    }
    return '<a class="kc-kick kc-caps" href="' . e(url('desk/' . $p['category_slug'])) . '">' . e($p['category_name']) . '</a>';
};
?>

<?php if ($hero): ?>
<section class="kc-hero" style="background-image:url('<?= e($heroBg) ?>')">
  <div class="inner">
    <?php if (!empty($hero['category_name'])): ?>
    <a class="kick kc-caps" href="<?= e(url('desk/' . $hero['category_slug'])) ?>"><?= e($hero['category_name']) ?></a>
    <?php endif; ?>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><p class="dek"><?= e($hero['lede']) ?></p><?php endif; ?>
    <p class="by"><?= dateline($hero) ?></p>
  </div>
</section>
<?php endif; ?>

<div class="kc-front">
  <div class="kc-main">
    <?php if (!$hero && !$lead2): ?>
    <div class="empty">No stories published yet. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first one.</div>
    <?php endif; ?>

    <?php if ($lead2): ?>
    <article class="kc-lead2">
      <?php if ($lead2['image']): ?>
      <a class="ph" href="<?= e(url('story/' . $lead2['slug'])) ?>" tabindex="-1" aria-hidden="true">
        <img src="<?= e($lead2['image']) ?>" alt="" loading="lazy">
      </a>
      <?php endif; ?>
      <?= $kick($lead2) ?>
      <h2><a href="<?= e(url('story/' . $lead2['slug'])) ?>"><?= e($lead2['title']) ?></a></h2>
      <?php if ($lead2['lede']): ?><p class="dek"><?= e($lead2['lede']) ?></p><?php endif; ?>
      <p class="kc-by"><?= dateline($lead2) ?></p>
    </article>
    <?php endif; ?>

    <?= ad_slot('top') ?>
  </div>

  <aside class="kc-rail">
    <div class="kc-railhead">
      <span class="kc-caps">Latest</span>
      <span class="upd">Updated <?= e(date('H:i')) ?></span>
    </div>
    <ul class="kc-latest">
      <?php foreach ($latest as $p): ?>
      <li>
        <span class="m"><?= e(time_label($p['published_at'])) ?><?php if (!empty($p['category_name'])): ?> · <b><?= e($p['category_name']) ?></b><?php endif; ?></span>
        <a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($notesText !== ''): ?>
    <div class="kc-notes">
      <div class="h">
        <img src="<?= e(site_asset('bear-crest.png')) ?>" alt="">
        <span class="kc-caps"><?= e($notesLabel) ?></span>
      </div>
      <p><?= e($notesText) ?></p>
      <?php if ($notesUrl !== ''): ?><a href="<?= e($notesUrl) ?>"><?= e($notesLink) ?></a><?php endif; ?>
    </div>
    <?php endif; ?>

    <?= ad_slot('rail') ?>
  </aside>
</div>

<?php if ($cards): ?>
<div class="kc-cards">
  <?php foreach ($cards as $p): ?>
  <article class="kc-card">
    <a class="ph" href="<?= e(url('story/' . $p['slug'])) ?>" tabindex="-1" aria-hidden="true">
      <img src="<?= e($p['image'] ?: site_asset('og-default.png')) ?>" alt="" loading="lazy">
    </a>
    <?= $kick($p) ?>
    <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
    <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 120)) ?></p><?php endif; ?>
    <p class="kc-by"><?= $p['byline'] !== '' ? 'By ' . e($p['byline']) : e(time_label($p['published_at'])) ?></p>
  </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<section class="kc-band" aria-label="Newsletter">
  <div class="inner">
    <div>
      <h3><?= e(setting('newsletter_heading', 'The 6 a.m.')) ?></h3>
      <p class="note"><?= e(setting('newsletter_copy')) ?></p>
    </div>
    <div>
      <?php if (isset($_GET['subscribed'])): ?>
      <p class="done"><?= isset($_GET['confirm']) ? 'Nearly there — check your inbox for the confirmation link.' : "You're on the list. The next letter is on its way." ?></p>
      <?php else: ?>
      <form method="post" action="<?= e(url('subscribe')) ?>">
        <input type="email" name="email" required placeholder="Your email address" aria-label="Email address">
        <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button class="kc-btn kc-btn--pine" type="submit">Subscribe</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($opinion || $communities): ?>
<div class="kc-double">
  <section aria-label="Opinion">
    <div class="kc-colhead kc-caps">Opinion</div>
    <div class="kc-op">
      <?php foreach ($opinion as $p): ?>
      <article>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <?php if ($p['lede']): ?><p class="dek"><?= e(excerpt($p['lede'], 110)) ?></p><?php endif; ?>
        <p class="kc-by"><?= e($p['byline']) ?></p>
      </article>
      <?php endforeach; ?>
      <?php if (!$opinion): ?><p class="kc-by" style="padding-top:14px">The opinion desk files soon.</p><?php endif; ?>
    </div>
  </section>
  <section aria-label="Communities">
    <div class="kc-colhead kc-caps">Communities</div>
    <ul class="kc-comm">
      <?php foreach ($communities as $p): ?>
      <li><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li>
      <?php endforeach; ?>
      <?php if (!$communities): ?><li class="kc-by" style="border:0">Dispatches from the towns, as they arrive.</li><?php endif; ?>
    </ul>
  </section>
</div>
<?php endif; ?>
