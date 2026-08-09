<?php
/** The Prairie Post — story page. */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

$slug = (string) ($_GET['slug'] ?? '');
$post = $slug !== '' ? post_by_slug($slug) : null;

if (!$post) {
    http_response_code(404);
    page_header(['title' => 'Story not found']);
    echo '<div class="wrap pagehead"><h1>Not found</h1>';
    echo '<div class="empty">That story isn\'t in the archive — the link may be old, or the piece may have been unpublished. '
       . 'Try the <a href="/">front page</a> or <a href="' . e(url('search')) . '">search the archive</a>.</div></div>';
    page_footer();
    exit;
}

$canonical = site_url() . '/story/' . $post['slug'];
$tags = tags_for_post((int) $post['id']);

$jsonld = [
    '@context' => 'https://schema.org',
    '@type'    => 'NewsArticle',
    'headline' => $post['title'],
    'description' => $post['meta_description'] ?: excerpt((string) $post['lede'], 155),
    'datePublished' => date('c', strtotime($post['published_at'])),
    'dateModified'  => date('c', strtotime($post['updated_at'] ?: $post['published_at'])),
    'mainEntityOfPage' => $canonical,
    'author' => array_filter([
        '@type' => 'Person',
        'name'  => $post['byline'] ?: setting('site_title'),
        'url'   => !empty($post['author_slug']) ? site_url() . '/author/' . $post['author_slug'] : null,
    ]),
    'publisher' => [
        '@type' => 'NewsMediaOrganization',
        'name'  => setting('site_title', 'The Prairie Post'),
        'logo'  => ['@type' => 'ImageObject', 'url' => site_url() . '/assets/img/mark.svg'],
    ],
];
$cardUrl = site_url() . '/card/' . $post['slug'] . '.png';
$jsonld['image'] = [$cardUrl];
if ($post['image']) {
    $jsonld['image'][] = site_url() . $post['image'];
}
if ($post['dateline']) {
    $jsonld['dateline'] = mb_strtoupper($post['dateline']);
}

page_header([
    'title'       => $post['title'],
    'description' => $post['meta_description'] ?: excerpt((string) $post['lede'], 155),
    'canonical'   => $canonical,
    'og_type'     => 'article',
    'og_image'    => $cardUrl,
    'jsonld'      => $jsonld,
], (string) $post['category_slug']);
?>

<article class="article wrap">
  <div class="headwrap">
    <?= eyebrow($post) ?>
    <h1><?= e($post['title']) ?></h1>
    <?php if ($post['lede']): ?><p class="lede"><?= e($post['lede']) ?></p><?php endif; ?>
    <p class="byline dateline"><?php
      $parts = [];
      if ($post['dateline']) {
          $parts[] = e(mb_strtoupper($post['dateline']));
      }
      if ($post['byline']) {
          $parts[] = !empty($post['author_slug'])
              ? 'By <a href="' . e(url('author/' . $post['author_slug'])) . '">' . e($post['byline']) . '</a>'
              : 'By ' . e($post['byline']);
      }
      if ($post['published_at']) {
          $parts[] = e(time_label($post['published_at']));
      }
      echo implode(' · ', $parts);
    ?></p>
  </div>

  <div class="pp-horizon" style="margin-top:18px"></div>

  <?php if ($post['image']): ?>
  <div style="max-width:820px;margin-top:26px">
    <?= story_photo($post, true) ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($post['correction'])): ?>
  <div class="correction" style="max-width:var(--pp-measure);margin-top:26px">
    <span class="k">Correction · <?= e(fmt_date($post['corrected_at'], 'M j, Y')) ?></span>
    <p style="margin:6px 0 0"><?= e((string) $post['correction']) ?></p>
  </div>
  <?php endif; ?>

  <div class="bodycopy">
    <?= sanitize_html((string) $post['body']) ?>
  </div>

  <?= ad_slot('article') ?>

  <?php if ($post['source_url']): ?>
  <p class="pp-meta" style="margin-top:24px">Source material: <a href="<?= e($post['source_url']) ?>" rel="nofollow noopener"><?= e(parse_url($post['source_url'], PHP_URL_HOST) ?: $post['source_url']) ?></a></p>
  <?php endif; ?>

  <?php if ($tags): ?>
  <div class="tagsrow">
    <?php foreach ($tags as $tag): ?>
    <a href="<?= e(url('search') . '?q=' . urlencode($tag['name'])) ?>"><?= e($tag['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php
    $shareUrl = $canonical;
    $shareText = $post['title'];
  ?>
  <div class="sharerow">
    <span class="k">Share the story</span>
    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= e(urlencode($shareUrl)) ?>" target="_blank" rel="noopener">Facebook</a>
    <a href="https://twitter.com/intent/tweet?url=<?= e(urlencode($shareUrl)) ?>&amp;text=<?= e(urlencode($shareText)) ?>" target="_blank" rel="noopener">X</a>
    <a href="https://bsky.app/intent/compose?text=<?= e(urlencode($shareText . ' ' . $shareUrl)) ?>" target="_blank" rel="noopener">Bluesky</a>
    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e(urlencode($shareUrl)) ?>" target="_blank" rel="noopener">LinkedIn</a>
    <a href="mailto:?subject=<?= e(rawurlencode($shareText)) ?>&amp;body=<?= e(rawurlencode($shareUrl)) ?>">Email</a>
    <button type="button" id="copylink" data-url="<?= e($shareUrl) ?>">Copy the link</button>
  </div>
  <script>
  (function () {
    var btn = document.getElementById('copylink');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var done = function () { btn.textContent = 'Copied'; setTimeout(function () { btn.textContent = 'Copy the link'; }, 2000); };
      if (navigator.clipboard) { navigator.clipboard.writeText(btn.dataset.url).then(done); }
      else { window.prompt('Copy the link:', btn.dataset.url); }
    });
  })();
  </script>
</article>

<?php $related = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id']); ?>
<?php if ($related): ?>
<section class="related wrap" aria-label="More from the paper">
  <div class="deskhead"><h2>More from the paper</h2></div>
  <div class="pp-horizon"></div>
  <div class="deskgrid" style="margin-top:20px">
    <?php foreach ($related as $rel): ?><?= story_card($rel) ?><?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php page_footer(); ?>
