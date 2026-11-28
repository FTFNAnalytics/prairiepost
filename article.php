<?php
/** The Prairie Dispatch — story page. */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

$slug = (string) ($_GET['slug'] ?? '');
$post = $slug !== '' ? post_by_slug($slug) : null;

if (!$post && $slug !== '') {
    $redir = pp_slug_redirect_target($slug);
    if ($redir && !empty($redir['slug']) && $redir['slug'] !== $slug) {
        header('Location: ' . url('story/' . $redir['slug']), true, 301);
        header('Cache-Control: public, max-age=3600');
        exit;
    }
}

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
// The home-paper canonical: a widely-syndicated story can name one paper
// home, and the other copies point their rel=canonical there so a single
// paper accrues the ranking. Needs the home site's public domain
// (sites.domain); without one this stays safely self-canonical.
if (!empty($post['canonical_site_id']) && (int) $post['canonical_site_id'] !== current_site_id()) {
    $homeDomain = pp_site_domain((int) $post['canonical_site_id']);
    if ($homeDomain !== '') {
        $canonical = 'https://' . $homeDomain . '/story/' . $post['slug'];
    }
}
$tags = tags_for_post((int) $post['id']);

// A wire link has no story page of its own — count the click-through here
// before sending the reader straight to the outlet that reported it.
if (is_link_post($post)) {
    if (!preg_match('/bot|crawl|spider|slurp|preview/i', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
        db()->prepare('UPDATE posts SET views = views + 1 WHERE id = ?')->execute([(int) $post['id']]);
    }
    $target = trim((string) $post['source_url']);
    if (!preg_match('#^https?://#i', $target) || preg_match('/[\r\n\x00-\x1f]/', $target)) {
        redirect('/');
    }
    header('Location: ' . $target, true, 302);
    exit;
}

// Story pages count reads via the uncached /ad?v=<id> footer beacon so GET
// responses remain 100% read-only and absorb traffic spikes from nginx cache.
$GLOBALS['ppViewPostId'] = (int) $post['id'];

$siteTitle = setting('site_title', 'The Prairie Dispatch');
$jsonld = [
    '@context' => 'https://schema.org',
    '@type'    => 'NewsArticle',
    'headline' => $post['title'],
    'description' => $post['meta_description'] ?: excerpt((string) $post['lede'], 155),
    'datePublished' => date('c', strtotime($post['published_at'])),
    'dateModified'  => date('c', strtotime($post['updated_at'] ?: $post['published_at'])),
    'mainEntityOfPage' => $canonical,
    'author' => pp_jsonld_author($post, $siteTitle),
    'publisher' => [
        '@type' => 'NewsMediaOrganization',
        'name'  => $siteTitle,
        'url'   => site_url(),
        'publishingPrinciples' => site_url() . '/standards',
        'correctionsPolicy'    => site_url() . '/corrections',
        'ownershipFundingInfo' => site_url() . '/about',
        'logo'  => [
            '@type'  => 'ImageObject',
            'url'    => ($ppLogo = pp_publisher_logo())['url'],
            'width'  => $ppLogo['width'],
            'height' => $ppLogo['height'],
        ],
    ],
    'inLanguage' => str_replace('_', '-', pp_locale()),
];
// The photograph, when it is a raster a platform will actually fetch.
// An SVG illustration, or no image, falls back to this paper's own card.
$ppShare = pp_story_share_image($post);
$ppImageLd = ['@type' => 'ImageObject', 'url' => $ppShare['url']];
if ($ppShare['width']) {
    $ppImageLd['width'] = $ppShare['width'];
}
if ($ppShare['height']) {
    $ppImageLd['height'] = $ppShare['height'];
}
$jsonld['image'] = [$ppImageLd];
if ($post['dateline']) {
    $jsonld['dateline'] = mb_strtoupper($post['dateline']);
}

// Turtle Island condenses its masthead on an article (screen 2a).
if (pp_chrome('template') === 'turtleisland') {
    $GLOBALS['ppTiMast'] = 'slim';
}

page_header([
    'title'          => $post['title'],
    'description'    => $post['meta_description'] ?: excerpt((string) $post['lede'], 155),
    'canonical'      => $canonical,
    'og_type'        => 'article',
    'og_image'       => $ppShare['url'],
    'og_image_width' => $ppShare['width'],
    'og_image_height'=> $ppShare['height'],
    'og_image_alt'   => $ppShare['alt'],
    'article'        => array_filter([
        'published_time' => pp_iso8601($post['published_at'] ?? ''),
        'modified_time'  => pp_iso8601(($post['updated_at'] ?? '') !== '' ? $post['updated_at'] : ($post['published_at'] ?? '')),
        'section'        => pp_desk_label($post['category_slug'] ?? null, $post['category_name'] ?? ''),
        'author'         => trim((string) ($post['byline'] ?? '')),
    ], static fn ($v) => $v !== ''),
    'jsonld'         => $jsonld,
], (string) $post['category_slug']);
?>

<?php
// Convention dispatch: a paper that ships app/views/article-<template>.php
// takes over the article here; anything else renders the shared markup
// below. Adding a paper is adding files — this chain never grows again.
// The block boundary below deliberately emits one newline: the chain it
// replaced was two php blocks, and every page carries that byte.
$GLOBALS['ppDisclosureRendered'] = false;
$ppView = PP_ROOT . '/app/views/article-' . basename((string) pp_chrome('template')) . '.php'; ?>

<?php if (is_file($ppView)) {
    require $ppView;
    if (empty($GLOBALS['ppDisclosureRendered'])) {
        $ppDisc = pp_article_disclosure($post);
        if ($ppDisc !== '') {
            echo '<div class="wrap" style="margin:18px auto">' . $ppDisc . '</div>';
        }
    }
    page_footer();
    return;
} ?>
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
    <?= pp_article_disclosure($post) ?>
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
