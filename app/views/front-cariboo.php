<?php
/* The Cariboo Compass — front page (brand build). $hero resolved by
   index.php. The mockup's shape: TOP STORY hero (photo overlay when
   the story has art, text lead otherwise), the Latest rail, then desk
   blocks as white cards. Renders the empty state cleanly — zero-story
   launch. */
$ccSeen = $hero ? [(int) $hero['id']] : [];
$ccRail = latest_posts(6, $ccSeen);
$ccSeen = array_merge($ccSeen, array_column($ccRail, 'id'));
$ccKick = fn (array $p) => 'cc-kick' . ((($p['category_slug'] ?? '') === 'environment') ? ' cc-kick--gold' : '');
?>
<div class="cc-wrap">
  <?php if (!$hero && !$ccRail): ?>
  <div class="cc-front" style="grid-template-columns:1fr">
    <div class="cc-empty">The Compass is finding its first bearing. The newsroom signs in at <a href="/admin/" style="color:var(--cc-lake)">/admin/</a> and files the first story.</div>
  </div>
  <?php else: ?>
  <div class="cc-front">
    <div>
      <?php if ($hero && $hero['image']): ?>
      <div class="cc-hero">
        <img src="<?= e($hero['image']) ?>" alt="">
        <div class="ov">
          <span><span class="cc-kick cc-kick--gold">Top Story</span></span>
          <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
          <span class="meta"><?= dateline($hero) ?> · <?= e(pp_desk_label((string) $hero['category_slug'], (string) $hero['category_name'])) ?></span>
        </div>
      </div>
      <?php elseif ($hero): ?>
      <div class="cc-lead">
        <span class="<?= e($ccKick($hero)) ?>"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'Top story'))) ?></span>
        <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
        <?php if ($hero['lede']): ?><p class="standfirst"><?= e($hero['lede']) ?></p><?php endif; ?>
        <p class="cc-byline"><?= dateline($hero) ?></p>
      </div>
      <?php endif; ?>
    </div>
    <aside class="cc-rail">
      <h2>Latest</h2>
      <?php foreach ($ccRail as $p): ?>
      <div class="item">
        <span class="<?= e($ccKick($p)) ?>" style="font-size:10px;padding:2px 8px"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <span class="meta"><?= e(date('M j', strtotime($p['published_at']))) ?> · <?= e(max(1, (int) ceil(str_word_count(strip_tags((string) $p['body'])) / 220))) ?> min read</span>
      </div>
      <?php endforeach; ?>
      <?php if (!$ccRail): ?><div class="item"><span class="meta">Nothing filed yet today.</span></div><?php endif; ?>
    </aside>
  </div>
  <?php endif; ?>

  <div class="cc-desks">
    <div class="cc-deskband">
      <?php foreach (array_slice(pp_nav_categories(), 0, 3) as $cat): ?>
      <section>
        <div class="cc-deskhead">
          <h2><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h2>
          <a href="<?= e(url('desk/' . $cat['slug'])) ?>">View all &rarr;</a>
        </div>
        <?php $ccPosts = posts_in_category((int) $cat['id'], 3, $ccSeen); ?>
        <?php foreach ($ccPosts as $p): ?>
        <div class="cc-card"><h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3></div>
        <?php endforeach; ?>
        <?php if (!$ccPosts): ?><div class="cc-card" style="color:var(--cc-muted);font-size:14px">Nothing on this desk yet.</div><?php endif; ?>
      </section>
      <?php endforeach; ?>
    </div>
  </div>
</div>
