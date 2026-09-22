<?php
/* Cariboo Compass — front page (foundation build). $hero resolved by
   index.php: lead left, "Bearings" latest rail right, then desk bands.
   Renders the empty state cleanly — this paper launches with zero
   stories. */
$ccSeen = $hero ? [(int) $hero['id']] : [];
$ccRail = latest_posts(7, $ccSeen);
$ccSeen = array_merge($ccSeen, array_column($ccRail, 'id'));
?>
<div class="cc-wrap">
  <?php if (!$hero && !$ccRail): ?>
  <div class="cc-front" style="grid-template-columns:1fr">
    <div class="cc-empty">The Compass is finding its first bearing. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first story.</div>
  </div>
  <?php else: ?>
  <div class="cc-front">
    <div class="cc-lead">
      <?php if ($hero): ?>
      <span class="cc-kick"><?= e($hero['category_name'] ?: 'Top story') ?></span>
      <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
      <?php if ($hero['lede']): ?><p class="standfirst"><?= e($hero['lede']) ?></p><?php endif; ?>
      <p class="cc-byline"><?= dateline($hero) ?></p>
      <?= story_photo($hero) ?>
      <?php endif; ?>
    </div>
    <aside class="cc-bearings">
      <h2>Bearings</h2>
      <?php foreach ($ccRail as $p): ?>
      <div class="item">
        <span class="cc-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      </div>
      <?php endforeach; ?>
      <?php if (!$ccRail): ?><div class="item" style="color:var(--cc-muted);font-size:14px">Nothing filed yet.</div><?php endif; ?>
    </aside>
  </div>
  <?php endif; ?>

  <div class="cc-desks">
    <div class="cc-deskband">
      <?php foreach (array_slice(pp_nav_categories(), 0, 3) as $cat): ?>
      <section>
        <div class="cc-deskhead">
          <h2><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h2>
          <a href="<?= e(url('desk/' . $cat['slug'])) ?>">All &rarr;</a>
        </div>
        <?php $ccPosts = posts_in_category((int) $cat['id'], 3, $ccSeen); ?>
        <?php foreach ($ccPosts as $p): ?>
        <div class="cc-card"><h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3></div>
        <?php endforeach; ?>
        <?php if (!$ccPosts): ?><div class="cc-card" style="color:var(--cc-muted);font-size:13px">Nothing on this desk yet.</div><?php endif; ?>
      </section>
      <?php endforeach; ?>
    </div>
  </div>
</div>
