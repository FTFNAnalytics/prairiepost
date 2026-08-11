<?php
/**
 * The westernwire front (chrome.template = "westernwire").
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * The brand's homepage: a gold Sections panel, the Spruce Topics panel and
 * gold-numbered Trending on the left rail; On the wire now in the middle —
 * the lead as a dark image card flagged Developing, two flagged side cards,
 * then the credited river; The 6 a.m. Wire, the province index and the
 * newsroom roster on the right; Across the four provinces as image cards
 * with their headline lists; the Also filed today ledger. Wire links open
 * at the source outlet.
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

// A dark image card: section flag(s), white serif headline, credited in caps.
$wwCard = function (array $p, string $extra = '', string $leadFlag = '') use ($wwAtt): string {
    $html  = '<a class="ww-card' . ($extra !== '' ? ' ' . $extra : '') . '" href="' . e(post_href($p)) . '"' . post_link_attr($p) . '>';
    if (!empty($p['image'])) {
        $html .= '<img src="' . e($p['image']) . '" alt="" loading="' . ($leadFlag !== '' ? 'eager' : 'lazy') . '">';
    }
    $html .= '<span class="shade" aria-hidden="true"></span><span class="in">';
    $html .= '<span class="flags">';
    if ($leadFlag !== '') {
        $html .= '<span class="flag flag--gold">' . e($leadFlag) . '</span>';
    }
    if (!empty($p['category_name'])) {
        $html .= '<span class="flag">' . e($p['category_name']) . '</span>';
    }
    $html .= '</span>';
    $html .= '<span class="hl">' . e($p['title']) . '</span>';
    $html .= '<span class="att">' . $wwAtt($p) . '</span>';
    $html .= '</span></a>';
    return $html;
};

$shown = $hero ? [(int) $hero['id']] : [];

// The two flagged side cards: the freshest illustrated items after the lead.
$side = [];
foreach (latest_posts(12, $shown) as $p) {
    if (count($side) >= 2) {
        break;
    }
    if ($p['image'] === '') {
        continue;
    }
    $side[] = $p;
    $shown[] = (int) $p['id'];
}

$wire = latest_posts(9, $shown);
$shown = array_merge($shown, array_map('intval', array_column($wire, 'id')));

$latest = $hero['published_at'] ?? ($wire[0]['published_at'] ?? null);
$also = latest_posts(12, $shown);
?>

<div class="ww-main">
  <?= ad_slot('top') ?>

  <div class="ww-front">
    <aside class="ww-index" aria-label="Index">
      <div class="ww-secs">
        <h2>Sections</h2>
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>

      <?php $topics = top_tags(10); if ($topics): ?>
      <div class="ww-topics">
        <h2>Topics</h2>
        <div class="cloud">
          <?php foreach ($topics as $t): ?>
          <a href="<?= e(url('search') . '?q=' . urlencode($t['name'])) ?>"><?= e($t['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (pp_chrome('trending')): $most = trending_posts(5); if ($most): ?>
      <div class="ww-trend" aria-label="Trending">
        <h2 class="ww-railhead">Trending</h2>
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
    </aside>

    <section class="ww-onwire" aria-label="On the wire now">
      <div class="oh">
        <h2>On the wire now</h2>
        <?php if ($latest): ?><span class="upd">Updated <?= e($wwAgo($latest)) ?> ago</span><?php endif; ?>
      </div>

      <?php if ($hero): ?>
      <div class="ww-leadgrid">
        <?= $wwCard($hero, 'ww-card--lead', $hero['placement'] === 'hero' ? 'Developing' : '') ?>
        <?php if ($side): ?>
        <div class="stack">
          <?php foreach ($side as $p): ?><?= $wwCard($p) ?><?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
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

      <?= ad_slot('rail') ?>
    </aside>
  </div>

  <?php
  $provinceCols = [];
  foreach ($regions as $key => $label) {
      $posts = posts_in_region($key, 4, $hero ? [(int) $hero['id']] : []);
      if (!$posts) {
          continue;
      }
      // Lead the column with its freshest illustrated item where one exists.
      foreach ($posts as $i => $p) {
          if ($i > 0 && $p['image'] !== '') {
              array_splice($posts, $i, 1);
              array_unshift($posts, $p);
              break;
          }
      }
      $provinceCols[$key] = ['label' => $label, 'posts' => $posts];
  }
  ?>
  <?php if ($provinceCols): ?>
  <section class="ww-provinces" aria-label="Across the provinces">
    <h2>Across the four provinces</h2>
    <div class="grid">
      <?php foreach ($provinceCols as $key => $col): ?>
      <div class="col">
        <p class="ph"><a href="<?= e(url('region/' . $key)) ?>"><?= e($col['label']) ?></a></p>
        <?php foreach ($col['posts'] as $i => $p): ?>
        <?php if ($i === 0 && $p['image'] !== ''): ?>
        <?= $wwCard($p, 'ww-card--prov') ?>
        <?php else: ?>
        <a class="it" href="<?= e(post_href($p)) ?>"<?= post_link_attr($p) ?>>
          <h3><?= e($p['title']) ?></h3>
          <span class="src"><?= $wwAtt($p) ?></span>
        </a>
        <?php endif; ?>
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
