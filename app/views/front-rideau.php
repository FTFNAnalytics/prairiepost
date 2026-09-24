<?php
/* The Rideau Review — front page (brand build). $hero resolved by
   index.php. The prototype's shape: two-column Globe-gravity front —
   leads with art on the left, the "More top stories" rail on the
   right (lock mark for the rail tick), then the "Across the region"
   three-card desk band, then the crimson promo well (the one place
   solid crimson is allowed). Renders the empty state cleanly —
   zero-story launch. */
$rrSeen = $hero ? [(int) $hero['id']] : [];
$rrSecond = latest_posts(1, $rrSeen);
$rrSecond = $rrSecond ? $rrSecond[0] : null;
if ($rrSecond) { $rrSeen[] = (int) $rrSecond['id']; }
$rrList = latest_posts(2, $rrSeen);
$rrSeen = array_merge($rrSeen, array_column($rrList, 'id'));
$rrRail = latest_posts(4, $rrSeen);
$rrSeen = array_merge($rrSeen, array_column($rrRail, 'id'));
$rrMins = fn (array $p) => max(1, (int) ceil(str_word_count(strip_tags((string) $p['body'])) / 220));
?>
<div class="rr-wrap">
  <?php if (!$hero): ?>
  <div style="padding:28px 0 12px">
    <div class="rr-empty">The Review is between editions. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first story — council, the bridges, the budgets. The capital, closely read.</div>
  </div>
  <?php else: ?>
  <div class="rr-home">
    <div>
      <?php foreach (array_filter([$hero, $rrSecond]) as $p): ?>
      <article class="rr-lead<?= $p['image'] ? '' : ' noart' ?>">
        <div>
          <span class="rr-kicker"><?= e(pp_desk_label((string) $p['category_slug'], (string) ($p['category_name'] ?: 'News'))) ?></span>
          <h2><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h2>
          <?php if ($p['lede']): ?><p class="rr-dek"><?= e($p['lede']) ?></p><?php endif; ?>
          <div class="rr-meta"><span><?= dateline($p) ?></span><span><?= e($rrMins($p)) ?> min read</span></div>
        </div>
        <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
      </article>
      <?php endforeach; ?>
      <?php foreach ($rrList as $p): ?>
      <article class="rr-story">
        <span class="rr-kicker"><?= e(pp_desk_label((string) $p['category_slug'], (string) ($p['category_name'] ?: 'News'))) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <div class="rr-meta"><span><?= dateline($p) ?></span><span><?= e($rrMins($p)) ?> min read</span></div>
      </article>
      <?php endforeach; ?>
    </div>
    <aside>
      <h2 class="rr-railhead">More top stories</h2>
      <?php foreach ($rrRail as $p): ?>
      <article class="rr-railitem<?= $p['image'] ? '' : ' noart' ?>">
        <div>
          <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
          <div class="rr-meta"><span><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span><span><?= e($rrMins($p)) ?> min</span></div>
        </div>
        <?php if ($p['image']): ?><img src="<?= e($p['image']) ?>" alt=""><?php endif; ?>
      </article>
      <?php endforeach; ?>
      <?php if (!$rrRail): ?><p style="color:var(--rr-muted);font-size:14px">Nothing further filed yet.</p><?php endif; ?>
    </aside>
  </div>
  <?php endif; ?>

  <section class="rr-pack">
    <h2 class="rr-packtitle">Across the region</h2>
    <div class="rr-three">
      <?php foreach (array_slice(pp_nav_categories(), 0, 3) as $cat): ?>
      <?php $rrTop = posts_in_category((int) $cat['id'], 1, $rrSeen); $rrTop = $rrTop ? $rrTop[0] : null; ?>
      <div class="rr-card">
        <?php if ($rrTop && $rrTop['image']): ?><a href="<?= e(url('story/' . $rrTop['slug'])) ?>"><img src="<?= e($rrTop['image']) ?>" alt=""></a><?php endif; ?>
        <span class="rr-kicker"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></span>
        <?php if ($rrTop): ?>
        <h3><a href="<?= e(url('story/' . $rrTop['slug'])) ?>"><?= e($rrTop['title']) ?></a></h3>
        <?php if ($rrTop['lede']): ?><p><?= e($rrTop['lede']) ?></p><?php endif; ?>
        <?php else: ?>
        <h3><a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?> opens its notebook shortly</a></h3>
        <p>Nothing on this desk yet — the newsroom is on it.</p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="rr-promo">
    <div class="rr-promo-left">
      <h2>The capital,<br>closely read</h2>
      <p>Independent journalism for Ottawa, Gatineau and Eastern Ontario.</p>
    </div>
    <div class="rr-promo-right">
      <div class="tag">Across the river. Down the canal.</div>
      <div class="place">Ottawa &middot; Gatineau &middot; Eastern Ontario</div>
      <p><a href="<?= e(url('newsletter/')) ?>" class="rr-btn-sub"><?= e(setting('newsletter_heading', 'The Evening Briefing')) ?></a></p>
    </div>
  </section>
</div>
