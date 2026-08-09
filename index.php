<?php
/** The Prairie Post — front page. */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

$hero = featured_post();
$heroId = $hero ? [(int) $hero['id']] : [];
$featured = front_featured_posts($heroId);
// The band an editor set; when empty, the two latest stories stand in.
$secondary = $featured ?: latest_posts(2, $heroId);
$shownIds = array_merge($heroId, array_map('intval', array_column($secondary, 'id')));
$river = latest_posts(10, $shownIds);
$markets = setting_json('markets');
$weather = setting_json('weather_today');

page_header([
    'description' => setting('meta_description'),
    'jsonld' => [
        '@context' => 'https://schema.org',
        '@type'    => 'NewsMediaOrganization',
        'name'     => setting('site_title', 'The Prairie Post'),
        'url'      => site_url(),
        'slogan'   => setting('tagline', 'News to the horizon'),
        'logo'     => site_url() . '/assets/img/mark.svg',
    ],
]);
?>

<div class="front wrap">
  <?= ad_slot('top') ?>

  <div class="frontgrid">
    <div class="mainwell">
      <?php if ($hero): ?>
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
    <div class="deskhead"<?= desk_style(empty($cat['color_is_fill']) ? $cat['color'] : '#17301C') ?>>
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
