<?php
/* Terminal City Times — front page (foundation build). $hero resolved
   by index.php: broadsheet lead beside the Departure Board (latest),
   then desk bands under a double rule. Renders the empty state cleanly
   — this paper launches with zero stories. */
$tcSeen = $hero ? [(int) $hero['id']] : [];
$tcRail = latest_posts(7, $tcSeen);
$tcSeen = array_merge($tcSeen, array_column($tcRail, 'id'));
?>
<div class="tc-wrap">
  <?php if (!$hero && !$tcRail): ?>
  <div class="tc-front" style="grid-template-columns:1fr">
    <div class="tc-empty">The first edition is on the press. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first story.</div>
  </div>
  <?php else: ?>
  <div class="tc-front">
    <div class="tc-lead">
      <?php if ($hero): ?>
      <span class="tc-kick"><?= e($hero['category_name'] ?: 'Top story') ?></span>
      <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
      <?php if ($hero['lede']): ?><p class="standfirst"><?= e($hero['lede']) ?></p><?php endif; ?>
      <p class="tc-byline"><?= dateline($hero) ?></p>
      <?= story_photo($hero) ?>
      <?php endif; ?>
    </div>
    <aside class="tc-board">
      <h2>The Departure Board</h2>
      <?php foreach ($tcRail as $p): ?>
      <div class="item">
        <span class="tc-kick"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      </div>
      <?php endforeach; ?>
      <?php if (!$tcRail): ?><div class="item" style="color:var(--tc-muted);font-size:14px">Nothing filed yet.</div><?php endif; ?>
    </aside>
  </div>
  <?php endif; ?>

  <div class="tc-desks">
    <div class="tc-deskband">
      <?php foreach (array_slice(pp_nav_categories(), 0, 3) as $cat): ?>
      <section>
        <div class="tc-deskhead">
          <h2><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h2>
          <a href="<?= e(url('desk/' . $cat['slug'])) ?>">All &rarr;</a>
        </div>
        <?php $tcPosts = posts_in_category((int) $cat['id'], 3, $tcSeen); ?>
        <?php foreach ($tcPosts as $p): ?>
        <div class="tc-card"><h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3></div>
        <?php endforeach; ?>
        <?php if (!$tcPosts): ?><div class="tc-card" style="color:var(--tc-muted);font-size:13px">Nothing on this desk yet.</div><?php endif; ?>
      </section>
      <?php endforeach; ?>
    </div>
  </div>
</div>
