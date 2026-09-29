<?php
/* The Red River Register — front page (brand build; the Rideau chassis
   as the Register's own identity). $hero resolved by index.php:
   two-column Globe-gravity front — leads with art left, the "More top
   stories" rail right (the mark as the rail tick), the "Across the
   valley" desk band, then the clay promo well (the one place solid
   clay is allowed). Renders the empty state cleanly. */
$rgSeen = $hero ? [(int) $hero['id']] : [];
$rgSecond = latest_posts(1, $rgSeen);
$rgSecond = $rgSecond ? $rgSecond[0] : null;
if ($rgSecond) { $rgSeen[] = (int) $rgSecond['id']; }
$rgList = latest_posts(2, $rgSeen);
$rgSeen = array_merge($rgSeen, array_column($rgList, 'id'));
$rgRail = latest_posts(4, $rgSeen);
$rgSeen = array_merge($rgSeen, array_column($rgRail, 'id'));
$rgMins = fn (array $p) => max(1, (int) ceil(str_word_count(strip_tags((string) $p['body'])) / 220));
?>
<div class="rg-wrap">
  <?php if (!$hero): ?>
  <div style="padding:28px 0 12px">
    <div class="rg-empty">The Register is between entries. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first story — the valley, on the record.</div>
  </div>
  <?php else: ?>
  <div class="rg-home">
    <div>
      <?php foreach (array_filter([$hero, $rgSecond]) as $p): ?>
      <article class="rg-lead<?= $p['image'] ? '' : ' noart' ?>">
        <div>
          <span class="rg-kicker"><?= e(pp_desk_label((string) $p['category_slug'], (string) ($p['category_name'] ?: 'News'))) ?></span>
          <h2><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h2>
          <?php if ($p['lede']): ?><p class="rg-dek"><?= e($p['lede']) ?></p><?php endif; ?>
          <div class="rg-meta"><span><?= dateline($p) ?></span><span><?= e($rgMins($p)) ?> min read</span></div>
        </div>
        <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
      </article>
      <?php endforeach; ?>
      <?php foreach ($rgList as $p): ?>
      <article class="rg-story">
        <span class="rg-kicker"><?= e(pp_desk_label((string) $p['category_slug'], (string) ($p['category_name'] ?: 'News'))) ?></span>
        <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <div class="rg-meta"><span><?= dateline($p) ?></span><span><?= e($rgMins($p)) ?> min read</span></div>
      </article>
      <?php endforeach; ?>
    </div>
    <aside>
      <h2 class="rg-railhead">More top stories</h2>
      <?php foreach ($rgRail as $p): ?>
      <article class="rg-railitem<?= $p['image'] ? '' : ' noart' ?>">
        <div>
          <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
          <div class="rg-meta"><span><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span><span><?= e($rgMins($p)) ?> min</span></div>
        </div>
        <?php if ($p['image']): ?><img src="<?= e($p['image']) ?>" alt=""><?php endif; ?>
      </article>
      <?php endforeach; ?>
      <?php if (!$rgRail): ?><p style="color:var(--rg-muted);font-size:14px">Nothing further filed yet.</p><?php endif; ?>
    </aside>
  </div>
  <?php endif; ?>

  <section class="rg-pack">
    <h2 class="rg-packtitle">Across the valley</h2>
    <div class="rg-three">
      <?php foreach (array_slice(pp_nav_categories(), 0, 3) as $cat): ?>
      <?php $rgTop = posts_in_category((int) $cat['id'], 1, $rgSeen); $rgTop = $rgTop ? $rgTop[0] : null; ?>
      <div class="rg-card">
        <?php if ($rgTop && $rgTop['image']): ?><a href="<?= e(url('story/' . $rgTop['slug'])) ?>"><img src="<?= e($rgTop['image']) ?>" alt=""></a><?php endif; ?>
        <span class="rg-kicker"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></span>
        <?php if ($rgTop): ?>
        <h3><a href="<?= e(url('story/' . $rgTop['slug'])) ?>"><?= e($rgTop['title']) ?></a></h3>
        <?php if ($rgTop['lede']): ?><p><?= e($rgTop['lede']) ?></p><?php endif; ?>
        <?php else: ?>
        <h3><a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?> opens its notebook shortly</a></h3>
        <p>Nothing on this desk yet — the newsroom is on it.</p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="rg-promo">
    <div class="rg-promo-left">
      <h2>The valley,<br>on the record</h2>
      <p>Independent journalism for the Red River Valley.</p>
    </div>
    <div class="rg-promo-right">
      <div class="tag">Beyond the Perimeter. Along the river.</div>
      <div class="place">The Red River Valley &middot; Manitoba</div>
      <p><a href="<?= e(url('newsletter/')) ?>" class="rg-btn-sub"><?= e(setting('newsletter_heading', 'The Morning Record')) ?></a></p>
    </div>
  </section>
</div>
