<?php
/* The Burrard Brief — front page (foundation build). $hero resolved by
   index.php. Layout: the lead story beside "The Brief" (a numbered run
   of the latest filings), then three-column desk bands. Renders the
   empty state cleanly — this paper launches with zero stories. */
$bbSeen  = $hero ? [(int) $hero['id']] : [];
$bbBrief = latest_posts(8, $bbSeen);
$bbSeen  = array_merge($bbSeen, array_column($bbBrief, 'id'));
?>
<div class="bb-wrap">
  <?php if (!$hero && !$bbBrief): ?>
  <div class="bb-front" style="grid-template-columns:1fr">
    <div class="bb-empty">The first brief is being written. The newsroom signs in at <a href="/admin/">/admin/</a> and files it.</div>
  </div>
  <?php else: ?>
  <div class="bb-front">
    <div class="bb-lead">
      <?php if ($hero): ?>
      <span class="bb-kick"><?= e($hero['category_name'] ?: 'Top story') ?></span>
      <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
      <?php if ($hero['lede']): ?><p class="standfirst"><?= e($hero['lede']) ?></p><?php endif; ?>
      <p class="bb-byline"><?= dateline($hero) ?></p>
      <?= story_photo($hero) ?>
      <?php endif; ?>
    </div>
    <aside class="bb-brief">
      <h2>The Brief</h2>
      <ol>
        <?php foreach ($bbBrief as $p): ?>
        <li>
          <span>
            <a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a>
            <span class="when bb-mono"><?= e(date('H:i', strtotime($p['published_at']))) ?> · <?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
          </span>
        </li>
        <?php endforeach; ?>
        <?php if (!$bbBrief): ?><li><span>Nothing filed yet today.</span></li><?php endif; ?>
      </ol>
    </aside>
  </div>
  <?php endif; ?>

  <div class="bb-desks">
    <div class="bb-deskband">
      <?php foreach (array_slice(pp_nav_categories(), 0, 3) as $cat): ?>
      <section>
        <div class="bb-deskhead">
          <h2><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h2>
          <a href="<?= e(url('desk/' . $cat['slug'])) ?>">All &rarr;</a>
        </div>
        <?php $bbPosts = posts_in_category((int) $cat['id'], 3, $bbSeen); ?>
        <?php foreach ($bbPosts as $p): ?>
        <div class="bb-card"><h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3></div>
        <?php endforeach; ?>
        <?php if (!$bbPosts): ?><div class="bb-card" style="color:var(--bb-muted);font-size:13px">Nothing on this desk yet.</div><?php endif; ?>
      </section>
      <?php endforeach; ?>
    </div>
  </div>
</div>
