<?php
/**
 * The London Lookout — front page (site canvas §isHome).
 * Included by index.php after page_header(); $hero is already resolved.
 *
 * Lead story with the council tracker beside it, the "open files" band
 * (the paper's accountability signature), three desk columns with the
 * Events chips, then Opinion/Sports and the membership panel. The two
 * trackers and the membership figures are settings-driven, so the
 * newsroom edits them without a deploy; each panel simply disappears
 * when its setting is empty.
 */

$vus = $hero ? [(int) $hero['id']] : [];
$rail = latest_posts(3, $vus);
$vus = array_merge($vus, array_map('intval', array_column($rail, 'id')));

/** Desk columns: the canvas runs Local News / Events / Business, then
    falls back to any other nav desk with stories (Opinion and Sports have
    their own pair below and never fill a column). */
$llWanted = ['local-news', 'events', 'business'];
$llBySlug = array_column(pp_nav_categories(), null, 'slug');
foreach ($llBySlug as $slug => $cat) {
    if (!in_array($slug, $llWanted, true) && !in_array($slug, ['opinion', 'sports'], true)) {
        $llWanted[] = $slug;
    }
}
$llCols = [];
foreach ($llWanted as $slug) {
    if (count($llCols) >= 3 || !isset($llBySlug[$slug])) {
        continue;
    }
    $posts = posts_in_category((int) $llBySlug[$slug]['id'], 4, $vus);
    if ($posts) {
        $llCols[] = ['cat' => $llBySlug[$slug], 'posts' => $posts];
        $vus = array_merge($vus, array_map('intval', array_column($posts, 'id')));
    }
}
$llOpinion = ($c = category_by_slug('opinion')) ? posts_in_category((int) $c['id'], 2, $vus) : [];
$vus = array_merge($vus, array_map('intval', array_column($llOpinion, 'id')));
$llSports = ($c = category_by_slug('sports')) ? posts_in_category((int) $c['id'], 3, $vus) : [];

$llArt = fn (array $p) => $p['image'] ?: site_asset('img/skyline-night.svg');
$llJson = function (string $key): array {
    $raw = json_decode((string) setting($key), true);
    return is_array($raw) ? $raw : [];
};
$llTracker = $llJson('council_tracker');
$llFiles = $llJson('open_files');
$llMembers = (int) setting('member_count');
$llGoal = max(1, (int) setting('member_goal'));
?>

<div class="ll-front ll-wrap">
  <div class="ll-top">
    <div>
      <?php if ($hero): ?>
      <article class="ll-lead">
        <figure><img src="<?= e($llArt($hero)) ?>" alt=""></figure>
        <div class="tagrow">
          <span class="ll-kick"><?= e(pp_desk_label($hero['category_slug'], $hero['category_name']) ?: 'The lead') ?></span>
          <span class="dash"></span>
          <?php if ($hero['dateline']): ?><span class="note"><?= e($hero['dateline']) ?></span><?php endif; ?>
        </div>
        <h1><a href="<?= e(post_href($hero)) ?>" style="color:inherit"><?= e($hero['title']) ?></a></h1>
        <?php if ($hero['lede']): ?><p class="stand"><?= e($hero['lede']) ?></p><?php endif; ?>
        <p class="ll-meta">
          <?php if ($hero['byline']): ?><span class="who">By <?= e($hero['byline']) ?></span><span>·</span><?php endif; ?>
          <span><?= e(read_minutes($hero)) ?> min read</span>
        </p>
      </article>
      <?php else: ?>
      <p class="ll-empty">The newsroom hasn&rsquo;t filed yet. The first edition is on its way.</p>
      <?php endif; ?>
    </div>

    <aside class="ll-rail">
      <?php if ($llTracker): ?>
      <section class="ll-tracker" aria-label="Council tracker">
        <div class="head">
          <span class="ll-kick">Council tracker</span>
          <?php if (setting('council_meeting') !== ''): ?><span class="when"><?= e(setting('council_meeting')) ?></span><?php endif; ?>
        </div>
        <?php foreach ($llTracker as $i => $row): ?>
        <?php if ($i): ?><div class="ll-hair"></div><?php endif; ?>
        <div class="row">
          <span class="state<?= stripos((string) ($row['state'] ?? ''), 'camera') !== false ? ' state--quiet' : '' ?>"><?= e((string) ($row['state'] ?? '')) ?></span>
          <span class="item"><?= e((string) ($row['item'] ?? '')) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if ($cc = category_by_slug('city-hall')): ?>
        <a class="more" href="<?= e(url('desk/' . $cc['slug'])) ?>">Every vote, this term &rarr;</a>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <?php if ($rail): ?>
      <div class="ll-rule"></div>
      <div class="stack">
        <?php foreach ($rail as $i => $p): ?>
        <?php if ($i): ?><div class="ll-hair"></div><?php endif; ?>
        <article>
          <span class="ll-kick ll-kick--n"><?= e(pp_desk_label($p['category_slug'], $p['category_name'])) ?></span>
          <h3><a href="<?= e(post_href($p)) ?>" style="color:inherit"><?= e($p['title']) ?></a></h3>
          <span class="ll-meta"><?php if ($p['byline']): ?>By <?= e($p['byline']) ?> · <?php endif; ?><?= e(read_minutes($p)) ?> min</span>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </aside>
  </div>

  <?php if ($llFiles): ?>
  <div class="ll-rule" style="margin:38px 0"></div>
  <section class="ll-files" aria-label="Open files">
    <div class="head">
      <div>
        <span class="ll-kick">The Lookout is watching</span>
        <h2>Open files we are still chasing</h2>
      </div>
      <span class="note">Every accountability story stays on this list until it is answered or closed.</span>
    </div>
    <div class="grid">
      <?php foreach (array_slice($llFiles, 0, 3) as $f): ?>
      <article class="card">
        <div class="top">
          <span class="ll-tag<?= !empty($f['closed']) ? ' ll-tag--out' : '' ?>"><?= e((string) ($f['tag'] ?? '')) ?></span>
          <span class="status"><?= e((string) ($f['status'] ?? '')) ?></span>
        </div>
        <h3><?= e((string) ($f['title'] ?? '')) ?></h3>
        <p><?= e((string) ($f['note'] ?? '')) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($llCols): ?>
  <div class="ll-rule" style="margin:38px 0"></div>
  <div class="ll-cols">
    <?php foreach ($llCols as $col): ?>
    <?php $isEvents = $col['cat']['slug'] === 'events'; $lead = $col['posts'][0]; ?>
    <section class="ll-col">
      <div class="head">
        <h2><?= e(pp_desk_label($col['cat']['slug'], $col['cat']['name'])) ?></h2>
        <a href="<?= e(url('desk/' . $col['cat']['slug'])) ?>"><?= $isEvents ? 'Calendar' : 'All' ?></a>
      </div>
      <?php if ($isEvents): ?>
        <?php foreach ($col['posts'] as $p): ?>
        <?php
        // An events story is published now and describes a date to come, so
        // the chip reads the event date from the head of the dateline.
        $llWhen = explode('·', (string) $p['dateline'])[0] ?? '';
        $ts = strtotime(trim($llWhen)) ?: strtotime((string) $p['published_at']);
        $llRest = trim((string) preg_replace('/^[^·]*·\s*/u', '', (string) $p['dateline']));
        ?>
        <article class="ll-ev">
          <span class="chip"><span class="d"><?= e(date('D', $ts)) ?></span><span class="n"><?= e(date('d', $ts)) ?></span></span>
          <span>
            <h3><a href="<?= e(post_href($p)) ?>" style="color:inherit"><?= e($p['title']) ?></a></h3>
            <?php if ($llRest !== ''): ?><span class="where"><?= e($llRest) ?></span><?php endif; ?>
          </span>
        </article>
        <?php endforeach; ?>
      <?php else: ?>
        <figure><img src="<?= e($llArt($lead)) ?>" alt=""></figure>
        <article class="top">
          <h3><a href="<?= e(post_href($lead)) ?>" style="color:inherit"><?= e($lead['title']) ?></a></h3>
          <?php if ($lead['lede']): ?><p><?= e(excerpt($lead['lede'], 140)) ?></p><?php endif; ?>
        </article>
        <?php foreach (array_slice($col['posts'], 1) as $p): ?>
        <article class="row" style="margin-top:16px">
          <h3><a href="<?= e(post_href($p)) ?>" style="color:inherit"><?= e($p['title']) ?></a></h3>
        </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($llOpinion || $llSports): ?>
  <div class="ll-rule" style="margin:38px 0"></div>
  <div class="ll-cols" style="grid-template-columns:1fr 1fr">
    <?php if ($llOpinion): ?>
    <section class="ll-col">
      <div class="head"><h2>Opinion</h2><a href="<?= e(url('desk/opinion')) ?>">All</a></div>
      <?php foreach ($llOpinion as $i => $p): ?>
      <article class="row"<?= $i ? '' : ' style="border-top:0"' ?>>
        <span class="ll-kick ll-kick--n"><?= e($p['dateline'] ?: 'Column') ?></span>
        <h3 style="margin-top:7px"><a href="<?= e(post_href($p)) ?>" style="color:inherit"><?= e($p['title']) ?></a></h3>
        <?php if ($p['byline']): ?><span class="ll-meta"><?= e($p['byline']) ?></span><?php endif; ?>
      </article>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>
    <?php if ($llSports): ?>
    <section class="ll-col">
      <div class="head"><h2>Sports</h2><a href="<?= e(url('desk/sports')) ?>">All</a></div>
      <?php foreach ($llSports as $i => $p): ?>
      <article class="row"<?= $i ? '' : ' style="border-top:0"' ?>>
        <h3><a href="<?= e(post_href($p)) ?>" style="color:inherit"><?= e($p['title']) ?></a></h3>
      </article>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (setting('member_pitch') !== ''): ?>
  <section class="ll-member-band" aria-label="Membership">
    <div>
      <span class="ll-kick">Reader funded</span>
      <h2><?= e(setting('member_pitch')) ?></h2>
      <?php if (setting('member_copy') !== ''): ?><p><?= e(setting('member_copy')) ?></p><?php endif; ?>
      <div class="cta">
        <a class="ll-member" href="<?= e(url('newsletter/')) ?>">Become a member</a>
      </div>
    </div>
    <?php if ($llMembers > 0): ?>
    <div>
      <div class="nums"><span><?= e(number_format($llMembers)) ?> members</span><span>Goal <?= e(number_format($llGoal)) ?></span></div>
      <div class="bar"><span style="width:<?= (int) min(100, round($llMembers / $llGoal * 100)) ?>%"></span></div>
      <?php if (setting('member_goal_note') !== ''): ?><div class="foot"><?= e(setting('member_goal_note')) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>
