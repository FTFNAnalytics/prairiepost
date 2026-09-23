<?php
/* The Burrard Brief — front page (brand build). $hero resolved by
   index.php. The mockup's shape: lead beside the "Latest" timeline
   rail, then "The Morning Brief" (the numbered five on a mist band),
   the desk blocks with scene-art thumbnails, and an Opinion quote
   card. Renders the empty state cleanly — zero-story launch. */
$bbSeen  = $hero ? [(int) $hero['id']] : [];
$bbRail  = latest_posts(6, $bbSeen);
$bbSeen  = array_merge($bbSeen, array_column($bbRail, 'id'));
$bbFive  = latest_posts(5, []);
$bbOpCat = null;
foreach (categories_all() as $c) {
    if ($c['slug'] === 'opinion') { $bbOpCat = $c; break; }
}
$bbOp = $bbOpCat ? (posts_in_category((int) $bbOpCat['id'], 1)[0] ?? null) : null;
?>
<div class="bb-wrap">
  <?php if (!$hero && !$bbRail): ?>
  <div class="bb-front" style="grid-template-columns:1fr">
    <div class="bb-empty">The first brief is being written. The newsroom signs in at <a href="/admin/">/admin/</a> and files it — briefly.</div>
  </div>
  <?php else: ?>
  <div class="bb-front">
    <div class="bb-lead">
      <?php if ($hero): ?>
      <span class="bb-kick<?= ($hero['category_slug'] ?? '') === 'opinion' ? ' bb-kick--opinion' : '' ?>"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'Top story'))) ?></span>
      <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
      <?php if ($hero['lede']): ?><p class="standfirst"><?= e($hero['lede']) ?></p><?php endif; ?>
      <p class="bb-byline"><?= dateline($hero) ?></p>
      <img src="<?= e(bb_art($hero)) ?>" alt="">
      <?php endif; ?>
    </div>
    <aside class="bb-rail">
      <h2>Latest</h2>
      <?php foreach ($bbRail as $p): ?>
      <div class="item">
        <span class="when"><?= e(date('g:i a', strtotime($p['published_at']))) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <span class="sec"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
      </div>
      <?php endforeach; ?>
      <?php if (!$bbRail): ?><div class="item"><span class="sec">Nothing filed yet today.</span></div><?php endif; ?>
    </aside>
  </div>

  <?php if ($bbFive): ?>
  <section class="bb-brief">
    <h2>The Morning Brief</h2>
    <p class="sub">Five things to know before you head out.</p>
    <ol>
      <?php foreach ($bbFive as $p): ?>
      <li><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li>
      <?php endforeach; ?>
    </ol>
  </section>
  <?php endif; ?>

  <?php if ($bbOp): ?>
  <section class="bb-opinion">
    <span class="bb-kick bb-kick--opinion">Opinion</span>
    <blockquote>&ldquo;<a href="<?= e(url('story/' . $bbOp['slug'])) ?>"><?= e($bbOp['title']) ?></a>&rdquo;</blockquote>
    <span class="who"><?= dateline($bbOp) ?></span>
  </section>
  <?php endif; ?>
  <?php endif; ?>

  <div class="bb-desks">
    <div class="bb-deskband">
      <?php foreach (array_slice(pp_nav_categories(), 0, 3) as $cat): ?>
      <section>
        <div class="bb-deskhead">
          <h2><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h2>
          <a href="<?= e(url('desk/' . $cat['slug'])) ?>">All <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?> &rarr;</a>
        </div>
        <?php $bbPosts = posts_in_category((int) $cat['id'], 3, $bbSeen); ?>
        <?php foreach ($bbPosts as $p): ?>
        <div class="bb-card">
          <img src="<?= e(bb_art($p)) ?>" alt="">
          <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        </div>
        <?php endforeach; ?>
        <?php if (!$bbPosts): ?><div class="bb-card" style="color:var(--bb-muted);font-size:14px;font-style:italic">Nothing on this desk yet.</div><?php endif; ?>
      </section>
      <?php endforeach; ?>
    </div>
  </div>
</div>
