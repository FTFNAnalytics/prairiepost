<?php
/** The Prairie Dispatch — front page. */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

// The hub's public face is a brochure, not a paper — rendered whole,
// before any of the newsroom chrome or front-page queries run.
if (pp_chrome('template') === 'civis') {
    require PP_ROOT . '/app/views/front-civis.php';
    return;
}

$hero = featured_post();
$heroId = $hero ? [(int) $hero['id']] : [];
$featured = front_featured_posts($heroId);
// The band an editor set; when empty, the two latest stories stand in.
$secondary = $featured ?: latest_posts(2, $heroId);
$shownIds = array_merge($heroId, array_map('intval', array_column($secondary, 'id')));
$river = latest_posts(10, $shownIds);
$markets = setting_json('markets');
$weather = setting_json('weather_today');

$GLOBALS['pp_front_page'] = true;

page_header([
    'description' => setting('meta_description'),
    'jsonld' => [
        '@context' => 'https://schema.org',
        '@type'    => 'NewsMediaOrganization',
        'name'     => setting('site_title', 'The Prairie Dispatch'),
        'url'      => site_url(),
        'slogan'   => setting('tagline', 'News to the horizon'),
        'logo'     => site_url() . site_asset('mark.svg'),
    ],
]);
?>

<?php if (pp_chrome('template') === 'echo-v3') {
    require PP_ROOT . '/app/views/front-v3.php';
    page_footer();
    return;
}
if (pp_chrome('template') === 'aurora') {
    require PP_ROOT . '/app/views/front-aurora.php';
    page_footer();
    return;
}
if (pp_chrome('template') === 'chronicle') {
    require PP_ROOT . '/app/views/front-chronicle.php';
    page_footer();
    return;
}
if (pp_chrome('template') === 'pacific') {
    require PP_ROOT . '/app/views/front-pacific.php';
    page_footer();
    return;
}
if (pp_chrome('template') === 'current') {
    require PP_ROOT . '/app/views/front-current.php';
    page_footer();
    return;
}
if (pp_chrome('template') === 'bulletin') {
    require PP_ROOT . '/app/views/front-bulletin.php';
    page_footer();
    return;
}
if (pp_chrome('template') === 'westernwire') {
    require PP_ROOT . '/app/views/front-westernwire.php';
    page_footer();
    return;
}
if (pp_chrome('template') === 'torch') {
    require PP_ROOT . '/app/views/front-torch.php';
    page_footer();
    return;
}
if (pp_chrome('template') === 'standard') {
    require PP_ROOT . '/app/views/front-standard.php';
    page_footer();
    return;
} ?>

<div class="front wrap">
  <?= ad_slot('top') ?>

  <div class="frontgrid">
    <div class="mainwell">
      <?php if ($hero && pp_chrome('hero') === 'overlay' && $hero['image']): ?>
      <article class="lead lead--overlay" style="background-image:url('<?= e($hero['image']) ?>')">
        <div class="ov-inner">
          <span class="ov-kicker">Top story</span>
          <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
          <?php if ($hero['lede']): ?><p class="standfirst"><?= e($hero['lede']) ?></p><?php endif; ?>
          <p class="byline"><?= dateline($hero) ?></p>
        </div>
      </article>
      <?php elseif ($hero): ?>
      <article class="lead">
        <?= story_photo($hero) ?>
        <?= eyebrow($hero) ?>
        <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
        <?php if ($hero['lede']): ?><p class="standfirst"><?= e($hero['lede']) ?></p><?php endif; ?>
        <p class="byline"><?= dateline($hero) ?></p>
      </article>
      <?php else: ?>
      <div class="empty">No stories published yet. The newsroom signs in at <a href="/admin/">/admin/</a> and files the first one.</div>
      <?php endif; ?>

      <?php if ($secondary): ?>
      <div class="duo<?= count($secondary) > 2 ? ' duo--band' : '' ?>">
        <?php foreach ($secondary as $post): ?><?= story_card($post) ?><?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($river): ?>
      <section class="deskblock" aria-label="The latest">
        <div class="deskhead"><h2>The latest</h2></div>
        <div class="pp-horizon"></div>
        <ul class="river">
          <?php foreach ($river as $post): ?>
          <li>
            <time datetime="<?= e($post['published_at']) ?>"><?= e(time_label($post['published_at'])) ?></time>
            <a href="<?= e(url('story/' . $post['slug'])) ?>"><?= e($post['title']) ?></a>
            <?= eyebrow($post) ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>
    </div>

    <aside class="rail">
      <?php if (pp_chrome('trending')): ?>
      <div class="railmod trendmod">
        <h4>Trending now</h4>
        <ol>
          <?php foreach (trending_posts(5) as $tp): ?>
          <li><a href="<?= e(url('story/' . $tp['slug'])) ?>"><?= e($tp['title']) ?></a>
            <span class="when"><?= e(time_label($tp['published_at'])) ?></span></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php endif; ?>

      <?php if ($markets): ?>
      <div class="railmod">
        <h4>Closing prices</h4>
        <table class="prices">
          <caption><?= e(setting('markets_note')) ?></caption>
          <?php foreach ($markets as $row): [$name, $price, $change] = array_pad($row, 3, ''); ?>
          <tr>
            <td><?= e($name) ?></td>
            <td><?= e($price) ?></td>
            <td class="<?= str_starts_with($change, '-') ? 'down' : 'up' ?>"><?= e(str_replace('-', '−', $change)) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <?php endif; ?>

      <?php if ($weather): ?>
      <div class="railmod weatherblock">
        <span class="k">The forecast</span>
        <div class="temp"><?= e($weather['temp'] ?? '') ?></div>
        <span class="pp-meta">High <?= e($weather['hi'] ?? '—') ?> · Low <?= e($weather['lo'] ?? '—') ?></span>
        <p><?= e($weather['line'] ?? '') ?></p>
      </div>
      <?php endif; ?>

      <div class="railmod"><?= signup_block() ?></div>

      <?= ad_slot('rail') ?>
    </aside>
  </div>

  <?php foreach (categories_all() as $cat): ?>
    <?php
    $posts = posts_in_category((int) $cat['id'], 3, $shownIds);
    if (!$posts) {
        continue;
    }
    ?>
  <section class="deskblock" aria-label="<?= e($cat['name']) ?>">
    <div class="deskhead"<?= desk_style(empty($cat['color_is_fill']) ? pp_desk_hex($cat['slug'], $cat['color']) : pp_brand_palette()['ink']) ?>>
      <h2><a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a></h2>
      <a class="more" href="<?= e(url('desk/' . $cat['slug'])) ?>">All <?= e($cat['name']) ?> →</a>
    </div>
    <div class="pp-horizon"></div>
    <div class="deskgrid">
      <?php foreach ($posts as $post): ?><?= story_card($post) ?><?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>
</div>

<?php page_footer(); ?>
