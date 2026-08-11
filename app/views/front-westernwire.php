<?php
/**
 * The westernwire front (chrome.template = "westernwire").
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * On the wire now — the lead item and the river, each credited to the
 * newsroom that filed it — beside the rail (The 6 a.m. Wire, Most read,
 * Topics, the labelled ad slot), then Across the four provinces and the
 * Also filed today ledger. Wire links open at the source outlet.
 */

$regions = setting_json('regions');

// Relative timestamps in the aggregator's register: "42 min", "3 h", "Aug 8".
$wwAgo = function (?string $dt): string {
    $ts = $dt ? strtotime($dt) : false;
    if (!$ts) {
        return '';
    }
    $mins = max(1, (int) floor((time() - $ts) / 60));
    if ($mins < 60) {
        return $mins . ' min';
    }
    if ($mins < 60 * 24) {
        return (int) round($mins / 60) . ' h';
    }
    return date('M j', $ts);
};

// "Calgary Herald · 42 min" on a wire link; the byline on an original.
$wwAtt = function (array $p) use ($wwAgo): string {
    if (is_link_post($p)) {
        return '<b>' . e(post_source_label($p)) . '</b> · ' . e($wwAgo($p['published_at']));
    }
    $by = ($p['byline'] ?? '') !== '' ? e($p['byline']) . ' · ' : '';
    return $by . e($wwAgo($p['published_at']));
};

$wwKick = function (array $p) use ($regions): string {
    $html = '<p class="kick">';
    if (!empty($p['category_name'])) {
        $html .= '<a href="' . e(url('desk/' . $p['category_slug'])) . '">' . e($p['category_name']) . '</a>';
    }
    if (!empty($p['region']) && isset($regions[$p['region']])) {
        $html .= '<span class="rg">' . e($regions[$p['region']]) . '</span>';
    }
    return $html . '</p>';
};

$shown = $hero ? [(int) $hero['id']] : [];
$wire = latest_posts(10, $shown);
$shown = array_merge($shown, array_map('intval', array_column($wire, 'id')));

$latest = $hero['published_at'] ?? ($wire[0]['published_at'] ?? null);
$also = latest_posts(12, $shown);
?>

<div class="ww-main">
  <?= ad_slot('top') ?>

  <div class="ww-front">
    <aside class="ww-index" aria-label="Index">
      <?php $topics = top_tags(12); if ($topics): ?>
      <div class="ww-topics">
        <h2 class="ww-railhead">Topics</h2>
        <div class="cloud">
          <?php foreach ($topics as $t): ?>
          <a href="<?= e(url('search') . '?q=' . urlencode($t['name'])) ?>"><?= e($t['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($regions): ?>
      <div class="ww-bylist" aria-label="By province">
        <h2 class="ww-railhead">By province</h2>
        <?php foreach ($regions as $rk => $rl): ?>
        <a href="<?= e(url('region/' . $rk)) ?>"><?= e($rl) ?><span class="n"><?= (int) count_posts_in_region($rk) ?></span></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php $rooms = wire_newsrooms(8); if ($rooms): ?>
      <div class="ww-bylist" aria-label="Newsrooms on the wire">
        <h2 class="ww-railhead">On the wire</h2>
        <?php foreach ($rooms as $r): ?>
        <a href="<?= e(url('search') . '?q=' . urlencode($r['name'])) ?>"><?= e($r['name']) ?><span class="n"><?= (int) $r['n'] ?></span></a>
        <?php endforeach; ?>
        <p class="note">Every headline links to the outlet that reported it.</p>
      </div>
      <?php endif; ?>
    </aside>

    <section class="ww-onwire" aria-label="On the wire now">
      <div class="oh">
        <h2>On the wire now</h2>
        <?php if ($latest): ?><span class="upd">Updated <?= e($wwAgo($latest)) ?> ago</span><?php endif; ?>
      </div>

      <?php if ($hero): ?>
      <article class="ww-item ww-item--lead" style="border-top:0;padding-top:18px">
        <?php if ($hero['image']): ?>
        <a class="ph" href="<?= e(post_href($hero)) ?>"<?= post_link_attr($hero) ?> tabindex="-1" aria-hidden="true"><img src="<?= e($hero['image']) ?>" alt="" loading="eager"></a>
        <?php endif; ?>
        <?= $wwKick($hero) ?>
        <h3><a href="<?= e(post_href($hero)) ?>"<?= post_link_attr($hero) ?>><?= e($hero['title']) ?></a></h3>
        <?php if ($hero['lede']): ?><p><?= e($hero['lede']) ?></p><?php endif; ?>
        <p class="att"><?= $wwAtt($hero) ?></p>
      </article>
      <?php elseif (!$wire): ?>
      <div class="empty">Nothing on the wire yet. The desk signs in at <a href="/admin/">/admin/</a> and posts the first link.</div>
      <?php endif; ?>

      <?php foreach ($wire as $p): ?>
      <article class="ww-item<?= $p['image'] ? ' has-ph' : '' ?>">
        <div class="tx">
          <?= $wwKick($p) ?>
          <h3><a href="<?= e(post_href($p)) ?>"<?= post_link_attr($p) ?>><?= e($p['title']) ?></a></h3>
          <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 170)) ?></p><?php endif; ?>
          <p class="att"><?= $wwAtt($p) ?></p>
        </div>
        <?php if ($p['image']): ?>
        <a class="thumb" href="<?= e(post_href($p)) ?>"<?= post_link_attr($p) ?> tabindex="-1" aria-hidden="true"><img src="<?= e($p['image']) ?>" alt="" loading="lazy"></a>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>
    </section>

    <aside class="ww-rail">
      <div class="ww-signup">
        <span class="k"><?= e(setting('newsletter_heading', 'The 6 a.m. Wire')) ?></span>
        <?php if (isset($_GET['subscribed'])): ?>
        <p><?= isset($_GET['confirm']) ? 'Nearly there — check your inbox for the confirmation link.' : 'You\'re on the list. The next edition lands at 6 a.m.' ?></p>
        <?php else: ?>
        <p><?= e(setting('newsletter_copy', 'Six stories from four provinces, in your inbox before the coffee\'s done.')) ?></p>
        <form method="post" action="<?= e(url('subscribe')) ?>">
          <input type="email" name="email" required placeholder="you@example.com" aria-label="Email address">
          <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
          <button type="submit">Subscribe</button>
        </form>
        <?php endif; ?>
      </div>

      <?php if (pp_chrome('trending')): $most = trending_posts(5); if ($most): ?>
      <div class="ww-most" aria-label="Most read">
        <h2 class="ww-railhead">Most read</h2>
        <ol>
          <?php foreach ($most as $p): ?>
          <li><div>
            <a href="<?= e(post_href($p)) ?>"<?= post_link_attr($p) ?>><?= e($p['title']) ?></a>
            <span class="src"><?= is_link_post($p) ? e(post_source_label($p)) : e(setting('site_title', 'Western Wire')) ?> · <?= e($wwAgo($p['published_at'])) ?></span>
          </div></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php endif; endif; ?>

      <?= ad_slot('rail') ?>
    </aside>
  </div>

  <?php
  $provinceCols = [];
  foreach ($regions as $key => $label) {
      $posts = posts_in_region($key, 4, $hero ? [(int) $hero['id']] : []);
      if ($posts) {
          $provinceCols[$key] = ['label' => $label, 'posts' => $posts];
      }
  }
  ?>
  <?php if ($provinceCols): ?>
  <section class="ww-provinces" aria-label="Across the provinces">
    <h2>Across the four provinces</h2>
    <div class="grid">
      <?php foreach ($provinceCols as $key => $col): ?>
      <div class="col">
        <p class="ph"><a href="<?= e(url('region/' . $key)) ?>"><?= e($col['label']) ?></a></p>
        <?php foreach ($col['posts'] as $p): ?>
        <a class="it" href="<?= e(post_href($p)) ?>"<?= post_link_attr($p) ?>>
          <h3><?= e($p['title']) ?></h3>
          <span class="src"><?= $wwAtt($p) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($also): ?>
  <section class="ww-also" aria-label="Also filed today">
    <h2>Also filed today</h2>
    <div class="grid">
      <?php foreach ($also as $p): ?>
      <a href="<?= e(post_href($p)) ?>"<?= post_link_attr($p) ?>>
        <span><?= e($p['title']) ?></span>
        <span class="src"><?= is_link_post($p) ? e(post_source_label($p)) : e($wwAgo($p['published_at'])) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>
