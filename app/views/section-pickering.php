<?php
/**
 * The Pickering Post — section front (chrome.template = "pickering").
 * Included by section.php after page_header(); $cat, $posts, $page and
 * $pages are already resolved.
 *
 * The package's fourth nameplate direction: caps, centred, the desk set
 * between two hairlines. It is the one symmetrical thing in the system, and
 * nothing below it centres — the standing description and every story
 * beneath run flush left.
 */

$by = function (array $p): string {
    $bits = [];
    if (!empty($p['byline'])) {
        $bits[] = 'By ' . $p['byline'];
    }
    $bits[] = read_minutes($p) . ' min read';
    return e(implode(' · ', $bits));
};

$lead = $posts[0] ?? null;
$rest = array_slice($posts, 1);
?>

<div class="pk-sectop">
  <span class="rule" aria-hidden="true"></span>
  <h1><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h1>
  <span class="rule" aria-hidden="true"></span>
  <?php if (!empty($cat['description'])): ?>
  <p><?= e($cat['description']) ?></p>
  <?php endif; ?>
</div>

<div class="pk-main">
  <div class="pk-well">
    <?php if (!$posts): ?>
    <div class="pk-empty">
      Nothing filed to <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?> yet.
      Try <a href="/">the front page</a> or another section above.
    </div>
    <?php else: ?>

    <?php if ($lead): ?>
    <article class="pk-item" style="margin:0 0 42px">
      <?php if ($lead['image']): ?>
      <a class="shot" href="<?= e(url('story/' . $lead['slug'])) ?>" tabindex="-1" aria-hidden="true"
         style="aspect-ratio:16/9;margin-bottom:20px">
        <img src="<?= e($lead['image']) ?>" alt="" loading="eager">
      </a>
      <?php endif; ?>
      <h3 style="font-size:30px"><a style="color:inherit" href="<?= e(url('story/' . $lead['slug'])) ?>"><?= e($lead['title']) ?></a></h3>
      <?php if ($lead['lede']): ?><p style="font-size:18px;line-height:1.6;margin-top:10px"><?= e($lead['lede']) ?></p><?php endif; ?>
      <p class="by"><?= $by($lead) ?></p>
    </article>
    <?php endif; ?>

    <?php if ($rest): ?>
    <div class="pk-grid">
      <?php foreach ($rest as $p): ?>
      <article class="pk-item">
        <?php if ($p['image']): ?>
        <a class="shot" href="<?= e(url('story/' . $p['slug'])) ?>" tabindex="-1" aria-hidden="true">
          <img src="<?= e($p['image']) ?>" alt="" loading="lazy">
        </a>
        <?php endif; ?>
        <h3><a style="color:inherit" href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 150)) ?></p><?php endif; ?>
        <p class="by"><?= $by($p) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
    <nav class="pk-pag" aria-label="Pages">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span aria-current="page"><?= $i ?></span>
        <?php else: ?><a href="<?= e(url('desk/' . $cat['slug']) . '?page=' . $i) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </nav>
    <?php endif; ?>
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
      <p class="done">You're on the list.</p>
      <?php else: ?>
      <p><?= e(setting('newsletter_copy', 'Six stories from Pickering in your inbox by seven.')) ?></p>
      <form method="post" action="<?= e(url('subscribe')) ?>">
        <input type="email" name="email" required placeholder="you@example.ca" aria-label="Email address">
        <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button type="submit">Sign up</button>
      </form>
      <?php endif; ?>
    </div>

    <?= ad_slot('rail') ?>
  </aside>
</div>
