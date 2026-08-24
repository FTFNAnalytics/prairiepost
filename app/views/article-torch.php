<?php
/**
 * The torch article page (chrome.template = "torch").
 * Included by article.php after page_header(); $post, $tags, $canonical
 * are already resolved.
 *
 * Text sits in a seven-column measure offset one column from the left, so
 * the page keeps the asymmetry of the home page. The hero figure runs three
 * columns wider than the text — the page's one wide element — and a sticky
 * three-column rail carries related headlines from the same desk.
 */

$rail = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id'], 5);
$deskName = pp_desk_label($post['category_slug'] ?? null, $post['category_name'] ?? '');
$initials = '';
foreach (preg_split('/\s+/', trim((string) $post['byline'])) as $word) {
    if ($word !== '') {
        $initials .= mb_strtoupper(mb_substr($word, 0, 1));
    }
}
$initials = mb_substr($initials, 0, 2) ?: 'TT';
?>

<div class="tt-container tt-article">
  <div class="tt-grid">
    <div style="grid-column: 2 / span 7">
      <div class="flagrow">
        <?php if (setting('breaking_label') !== '' && setting('breaking_url') !== '' && $post['slug'] === basename((string) setting('breaking_url'))): ?>
        <span class="tt-flag">Breaking</span>
        <?php endif; ?>
        <?php if ($k = torch_kicker($post)): ?><span class="kicker"><?= $k ?></span><?php endif; ?>
      </div>
      <h1><?= e($post['title']) ?></h1>
      <?php if ($post['lede']): ?><p class="standfirst"><?= e($post['lede']) ?></p><?php endif; ?>

      <div class="byline">
        <span class="por"><span><?= e($initials) ?></span></span>
        <span class="who">
          <b><?php if (!empty($post['author_slug'])): ?><a style="color:inherit;text-decoration:none" href="<?= e(url('author/' . $post['author_slug'])) ?>"><?= e($post['byline']) ?></a><?php else: ?><?= e($post['byline'] ?: setting('site_title')) ?><?php endif; ?></b>
          <span><?= e(fmt_date($post['published_at'], 'j F Y')) ?><?= $post['dateline'] ? ' · ' . e($post['dateline']) : '' ?></span>
        </span>
      </div>
    </div>

    <?php if ($post['image']): ?>
    <figure class="hero" style="grid-column: 2 / span 10">
      <img src="<?= e($post['image']) ?>" alt="<?= e($post['image_caption'] ?: $post['title']) ?>">
      <?php if ($post['image_caption'] !== '' || $post['image_credit'] !== ''): ?>
      <figcaption><?= e($post['image_caption']) ?><?php if ($post['image_credit'] !== ''): ?> <i><?= e($post['image_credit']) ?></i><?php endif; ?></figcaption>
      <?php endif; ?>
    </figure>
    <?php endif; ?>

    <div style="grid-column: 2 / span 7">
      <?php if (!empty($post['correction'])): ?>
      <div class="correction">
        <span class="k">Correction · <?= e(fmt_date($post['corrected_at'], 'j M Y')) ?></span>
        <p><?= e((string) $post['correction']) ?></p>
      </div>
      <?php endif; ?>

      <div class="bodycopy"><?= sanitize_html((string) $post['body']) ?></div>
<?= pp_provenance_box($post) ?>

      <?= ad_slot('article') ?>

      <?php if ($post['source_url']): ?>
      <p class="t-meta" style="margin-top:22px">Source material: <a style="color:var(--inlet-blue)" href="<?= e($post['source_url']) ?>" rel="nofollow noopener"><?= e(parse_url($post['source_url'], PHP_URL_HOST) ?: $post['source_url']) ?></a></p>
      <?php endif; ?>

      <?php if ($tags): ?>
      <div class="tagsrow">
        <?php foreach ($tags as $tag): ?>
        <a href="<?= e(url('search') . '?q=' . urlencode($tag['name'])) ?>"><?= e($tag['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="sharerow">
        <span class="k">Share</span>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= e(urlencode($canonical)) ?>" target="_blank" rel="noopener">Facebook</a>
        <a href="https://twitter.com/intent/tweet?url=<?= e(urlencode($canonical)) ?>&amp;text=<?= e(urlencode($post['title'])) ?>" target="_blank" rel="noopener">X</a>
        <a href="https://bsky.app/intent/compose?text=<?= e(urlencode($post['title'] . ' ' . $canonical)) ?>" target="_blank" rel="noopener">Bluesky</a>
        <a href="mailto:?subject=<?= e(rawurlencode($post['title'])) ?>&amp;body=<?= e(rawurlencode($canonical)) ?>">Email</a>
        <button type="button" id="copylink" data-url="<?= e($canonical) ?>">Copy the link</button>
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
    </div>

    <?php if ($rail): ?>
    <aside style="grid-column: 10 / span 3">
      <div class="tt-rail">
        <h2>More in <?= e($deskName ?: 'the Torch') ?></h2>
        <?php foreach ($rail as $r): ?>
        <a href="<?= e(url('story/' . $r['slug'])) ?>">
          <?= e($r['title']) ?>
          <?php if (!empty($r['published_at'])): ?><span><?= e(fmt_date($r['published_at'], 'j M Y')) ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </aside>
    <?php endif; ?>
  </div>
</div>
