<?php
/**
 * The Mississauga Monitor — front page (design canvas §01).
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * Navy hero (featured post over its photo, blue section chip), the
 * Featured-story card with a ward-brief list beneath it, then the
 * "Latest GTA stories" card grid and the newsletter card.
 */

$vus = $hero ? [(int) $hero['id']] : [];
// The Featured-story card takes what an editor placed in the featured
// band, and falls back to the newest story when the band is empty.
$featStory = front_featured_posts($vus, 1)[0] ?? latest_posts(1, $vus)[0] ?? null;
if ($featStory) {
    $vus[] = (int) $featStory['id'];
}
$briefs = latest_posts(3, $vus);
$vus = array_merge($vus, array_map('intval', array_column($briefs, 'id')));
$latest = latest_posts(8, $vus);

/** The ward chip: "W7" from a "Ward 7" dateline; "Green" for environment. */
$mmChip = function (array $p): array {
    if (($p['category_slug'] ?? '') === 'environment') {
        return ['Green', 'w--green'];
    }
    if (preg_match('/ward\s*(\d+)/i', (string) ($p['dateline'] ?? ''), $m)) {
        return ['W' . $m[1], ''];
    }
    return [pp_desk_label($p['category_slug'] ?? null, $p['category_name'] ?? '') ?: 'News', ''];
};
?>

<div class="mm-page mm-front">
  <div class="mm-top">
    <?php if ($hero): ?>
    <div class="mm-hero">
      <?php if ($hero['image']): ?><span class="ph"><img src="<?= e($hero['image']) ?>" alt=""></span><?php endif; ?>
      <span class="scrim" aria-hidden="true"></span>
      <div class="txt">
        <?php if ($hero['category_name']): ?><span class="chip"><?= e(pp_desk_label($hero['category_slug'], $hero['category_name'])) ?></span><?php endif; ?>
        <h1><a class="more" style="font:inherit" href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
        <?php if ($hero['lede']): ?><p><?= e(excerpt($hero['lede'], 170)) ?></p><?php endif; ?>
        <a class="more" href="<?= e(url('story/' . $hero['slug'])) ?>">Read more &rarr;</a>
      </div>
    </div>
    <?php endif; ?>

    <div class="mm-feat">
      <?php if ($featStory): ?>
      <div class="mm-card lead">
        <span class="mm-kicker mm-kicker--muted" style="color:var(--mm-org-dk)">Featured story</span>
        <h2><a href="<?= e(url('story/' . $featStory['slug'])) ?>"><?= e($featStory['title']) ?></a></h2>
        <?php if ($featStory['lede']): ?><p><?= e(excerpt($featStory['lede'], 180)) ?></p><?php endif; ?>
        <p class="mm-meta">
          <?php if ($featStory['byline']): ?>By <strong><?= e($featStory['byline']) ?></strong> &middot; <?php endif; ?>
          <?= e(read_minutes($featStory)) ?> min read
        </p>
      </div>
      <?php endif; ?>
      <?php if ($briefs): ?>
      <div class="mm-card mm-briefs">
        <?php foreach ($briefs as $p): ?>
        <?php [$chip, $mod] = $mmChip($p); ?>
        <div class="b">
          <span class="w <?= e($mod) ?>"><?= e($chip) ?></span>
          <a style="color:inherit" href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($latest): ?>
  <section class="mm-latest" aria-label="Latest GTA stories">
    <h2 class="mm-h">Latest GTA stories</h2>
    <div class="grid">
      <?php foreach ($latest as $p): ?>
      <article class="mm-card">
        <span class="mm-kicker"><?= e(pp_desk_label($p['category_slug'] ?? null, $p['category_name'] ?? '')) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 110)) ?></p><?php endif; ?>
        <span class="mm-meta"><?= e(fmt_date($p['published_at'])) ?></span>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!$hero && !$featStory && !$latest): ?>
  <div class="mm-card">
    <h2>Nothing filed yet.</h2>
    <p class="mm-meta">The newsroom signs in at <a href="/admin/">/admin/</a> to publish the first story.</p>
  </div>
  <?php endif; ?>

  <?= ad_slot('top') ?>

  <section class="mm-news" aria-label="Newsletter">
    <h3><?= e(setting('newsletter_heading', 'Newsletter signup')) ?></h3>
    <?php if (isset($_GET['subscribed'])): ?>
    <p>You're on the list.</p>
    <?php else: ?>
    <p><?= e(setting('newsletter_copy', 'Get the latest local news delivered to your inbox.')) ?></p>
    <form method="post" action="<?= e(url('subscribe')) ?>">
      <input type="email" name="email" required placeholder="Enter your email address" aria-label="Email address">
      <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
      <button class="mm-subscribe" type="submit" style="margin-left:0">Subscribe</button>
    </form>
    <?php endif; ?>
  </section>
</div>
