<?php
/**
 * The Pickering Post — front page (chrome.template = "pickering").
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * The package's reference layout, part for part: utility strip and masthead
 * and navy band (all chrome, in ui.php), then a hero photograph carrying the
 * lead, Top Stories and Local Events beside a filled rail, Community
 * Spotlight, and the navy footer.
 *
 * Desks share one cyan tint, so no section owns a colour of its own. Opinion
 * is outlined to mark it as comment, obituaries stay neutral, and magenta is
 * held back for the story that is still moving.
 */

/** The desk slug above a headline, in the one tint every desk shares. */
$slug = function (array $p): string {
    $name = $p['category_name'] ?? '';
    if ($name === '') {
        return '';
    }
    $s = $p['category_slug'] ?? '';
    $class = 'pk-slug';
    if ($s === 'opinion') {
        $class .= ' pk-slug--opinion';
    } elseif ($s === 'obituaries') {
        $class .= ' pk-slug--obit';
    } elseif ($s === 'breaking') {
        $class .= ' pk-slug--breaking';
    }
    return '<span class="' . $class . '">' . e(pp_desk_label($s ?: null, $name)) . '</span>';
};

/** "By R. Sandhu · 8 min read" */
$by = function (array $p): string {
    $bits = [];
    if (!empty($p['byline'])) {
        $bits[] = 'By ' . $p['byline'];
    }
    $bits[] = read_minutes($p) . ' min read';
    return e(implode(' · ', $bits));
};

/** Posts from a desk by slug — empty when the desk doesn't exist yet. */
$fromDesk = function (string $slug, int $limit, array $exclude): array {
    $cat = category_by_slug($slug);
    return $cat ? posts_in_category((int) $cat['id'], $limit, $exclude) : [];
};

$shown  = $hero ? [(int) $hero['id']] : [];
$top    = latest_posts(4, $shown);
$shown  = array_merge($shown, array_map('intval', array_column($top, 'id')));
$events = $fromDesk('events', 3, $shown);
$shown  = array_merge($shown, array_map('intval', array_column($events, 'id')));
$spot   = $fromDesk('community', 1, $shown)[0] ?? null;
if ($spot) {
    $shown[] = (int) $spot['id'];
}
$latest = latest_posts(5, $shown);
?>

<?php if ($hero): ?>
<div class="pk-hero">
  <?php if ($hero['image']): ?><img src="<?= e($hero['image']) ?>" alt="" loading="eager"><?php endif; ?>
  <span class="wash" aria-hidden="true"></span>
  <div class="in">
    <?php if (!empty($hero['category_name'])): ?>
    <p class="slug"><?= e(pp_desk_label($hero['category_slug'] ?? null, $hero['category_name'])) ?> &middot; Lead story</p>
    <?php endif; ?>
    <h1><?= e($hero['title']) ?></h1>
    <?php if ($hero['lede']): ?><p class="standfirst"><?= e($hero['lede']) ?></p><?php endif; ?>
    <a class="more" href="<?= e(url('story/' . $hero['slug'])) ?>">Read more</a>
  </div>
</div>
<?php endif; ?>

<div class="pk-main">
  <div class="pk-well">
    <?= ad_slot('top') ?>

    <?php if (!$hero && !$top): ?>
    <div class="pk-empty">
      The newsroom hasn't filed yet. Editors sign in at <a href="/admin/">/admin/</a> to publish the first story.
    </div>
    <?php endif; ?>

    <?php if ($top): ?>
    <div class="pk-sechead">
      <h2>Top Stories</h2>
      <a href="<?= e(url('search')) ?>">More top stories</a>
    </div>
    <div class="pk-grid">
      <?php foreach ($top as $p): ?>
      <article class="pk-item">
        <?php if ($p['image']): ?>
        <a class="shot" href="<?= e(url('story/' . $p['slug'])) ?>" tabindex="-1" aria-hidden="true">
          <img src="<?= e($p['image']) ?>" alt="" loading="lazy">
        </a>
        <?php endif; ?>
        <?= $slug($p) ?>
        <h3><a style="color:inherit" href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 150)) ?></p><?php endif; ?>
        <p class="by"><?= $by($p) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($events): ?>
    <div class="pk-events">
      <div class="pk-sechead">
        <h2>Local Events</h2>
        <a href="<?= e(url('desk/events')) ?>">This week</a>
      </div>
      <?php foreach ($events as $p): ?>
      <div class="ev">
        <h3><a style="color:inherit" href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 160)) ?></p><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($spot): ?>
    <section class="pk-spot" aria-label="Community Spotlight">
      <div class="pk-sechead"><h2>Community Spotlight</h2></div>
      <div class="in">
        <?php if ($spot['image']): ?>
        <a class="shot" href="<?= e(url('story/' . $spot['slug'])) ?>" tabindex="-1" aria-hidden="true">
          <img src="<?= e($spot['image']) ?>" alt="" loading="lazy">
        </a>
        <?php endif; ?>
        <div class="txt">
          <?= $slug($spot) ?>
          <h3><a style="color:inherit" href="<?= e(url('story/' . $spot['slug'])) ?>"><?= e($spot['title']) ?></a></h3>
          <?php if ($spot['lede']): ?><p><?= e($spot['lede']) ?></p><?php endif; ?>
          <p class="by" style="font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:var(--quiet)"><?= $by($spot) ?></p>
        </div>
      </div>
    </section>
    <?php endif; ?>
  </div>

  <aside class="pk-rail">
    <div class="pk-mod">
      <div class="bd">
        <form class="pk-search" method="get" action="<?= e(url('search')) ?>" role="search">
          <input type="search" name="q" placeholder="Search the archive" aria-label="Search the archive">
          <button class="pk-btn" type="submit">Go</button>
        </form>
      </div>
    </div>

    <div class="pk-sub">
      <h3><?= e(setting('newsletter_heading', 'The morning email')) ?></h3>
      <?php if (isset($_GET['subscribed'])): ?>
      <p class="done"><?= isset($_GET['confirm']) ? 'Nearly there — check your inbox for the confirmation link.' : 'You\'re on the list.' ?></p>
      <?php else: ?>
      <p><?= e(setting('newsletter_copy', 'Six stories from Pickering in your inbox by seven. Free, and short enough for the GO train.')) ?></p>
      <form method="post" action="<?= e(url('subscribe')) ?>">
        <input type="email" name="email" required placeholder="you@example.ca" aria-label="Email address">
        <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button type="submit">Sign up</button>
      </form>
      <?php endif; ?>
    </div>

    <?php if ($latest): ?>
    <div class="pk-mod pk-latest">
      <p class="hd">Latest</p>
      <div class="bd">
        <?php foreach ($latest as $p): ?>
        <a class="it" href="<?= e(url('story/' . $p['slug'])) ?>">
          <span class="when"><?= e(fmt_date($p['published_at'], 'H:i')) ?></span>
          <span class="ttl"><?= e($p['title']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?= ad_slot('rail') ?>
  </aside>
</div>
