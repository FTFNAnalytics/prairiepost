<?php
/* Surrey Standard — front page (foundation build). $hero resolved by
   index.php: lead left, latest rail right, then desk bands. Renders the
   empty state cleanly — this paper launches with zero stories. */
$ssSeen = $hero ? [(int) $hero['id']] : [];
$ssRail = latest_posts(7, $ssSeen);
$ssSeen = array_merge($ssSeen, array_column($ssRail, 'id'));
?>
<div class="ss-wrap">
  <?php if (!$hero && !$ssRail): ?>
  <div class="ss-front" style="grid-template-columns:1fr">
    <div class="ss-empty">The first edition is being set. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first story.</div>
  </div>
  <?php else: ?>
  <div class="ss-front">
    <div class="ss-lead">
      <?php if ($hero): ?>
      <span class="ss-kick"><?= e($hero['category_name'] ?: 'Top story') ?></span>
      <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
      <?php if ($hero['lede']): ?><p class="standfirst"><?= e($hero['lede']) ?></p><?php endif; ?>
      <p class="ss-byline"><?= dateline($hero) ?></p>
      <?= story_photo($hero) ?>
      <?php endif; ?>
    </div>
    <aside class="ss-rail">
      <h2>The Latest</h2>
      <?php foreach ($ssRail as $p): ?>
      <div class="item">
        <span class="ss-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      </div>
      <?php endforeach; ?>
      <?php if (!$ssRail): ?><div class="item" style="color:var(--ss-muted);font-size:14px">Nothing filed yet.</div><?php endif; ?>
    </aside>
  </div>
  <?php endif; ?>

  <div class="ss-desks">
    <div class="ss-deskband">
      <?php foreach (array_slice(pp_nav_categories(), 0, 3) as $cat): ?>
      <section>
        <div class="ss-deskhead">
          <h2><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h2>
          <a href="<?= e(url('desk/' . $cat['slug'])) ?>">All &rarr;</a>
        </div>
        <?php $ssPosts = posts_in_category((int) $cat['id'], 3, $ssSeen); ?>
        <?php foreach ($ssPosts as $p): ?>
        <div class="ss-card"><h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3></div>
        <?php endforeach; ?>
        <?php if (!$ssPosts): ?><div class="ss-card" style="color:var(--ss-muted);font-size:13px">Nothing on this desk yet.</div><?php endif; ?>
      </section>
      <?php endforeach; ?>
    </div>
  </div>
</div>
