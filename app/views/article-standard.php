<?php
/**
 * The standard article page (chrome.template = "standard").
 * Included by article.php after page_header(); $post, $tags and $canonical
 * are already resolved.
 *
 * Section 07 of the brand package: a 660px measure on white, the kicker in
 * Press Red, a Playfair headline, a one-sentence standfirst, and a byline
 * row that carries the reading time. The pull quote breaks the measure as a
 * full-bleed slate band in italic serif — one per piece. The piece closes on
 * the funding note.
 */

$kicker = ($post['category_name'] ?? '') !== ''
    ? 'Opinion · ' . $post['category_name']
    : 'Opinion';
$more = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id'], 3);
?>

<div class="sd-wrap">
  <article class="sd-article">
    <div class="sd-measure">
      <p class="sd-kicker sd-kicker--red"><?= e($kicker) ?></p>
      <h1><?= e($post['title']) ?></h1>
      <?php if ($post['lede']): ?><p class="standfirst"><?= e($post['lede']) ?></p><?php endif; ?>
      <div class="byrow">
        <span>By <strong><?php if (!empty($post['author_slug'])): ?><a style="color:inherit" href="<?= e(url('author/' . $post['author_slug'])) ?>"><?= e($post['byline']) ?></a><?php else: ?><?= e($post['byline'] ?: setting('site_title')) ?><?php endif; ?></strong> &middot; <?= e(fmt_date($post['published_at'], 'j F Y')) ?></span>
        <span><?= (int) read_minutes($post) ?> min read</span>
      </div>
    </div>

    <?php if ($post['image']): ?>
    <figure class="hero">
      <img src="<?= e($post['image']) ?>" alt="<?= e($post['image_caption'] ?: $post['title']) ?>">
      <?php if ($post['image_caption'] !== '' || $post['image_credit'] !== ''): ?>
      <figcaption class="sd-measure"><?= e($post['image_caption']) ?><?php if ($post['image_credit'] !== ''): ?> <em><?= e($post['image_credit']) ?></em><?php endif; ?></figcaption>
      <?php endif; ?>
    </figure>
    <?php endif; ?>

    <?php if (!empty($post['correction'])): ?>
    <div class="sd-measure">
      <div class="correction">
        <span class="k">Correction &middot; <?= e(fmt_date($post['corrected_at'], 'j F Y')) ?></span>
        <p><?= e((string) $post['correction']) ?></p>
      </div>
    </div>
    <?php endif; ?>

    <?php
    // The pull quote is full-bleed slate, so the body is split around any
    // blockquote rather than wrapped whole in the 660px measure.
    $body = sanitize_html((string) $post['body']);
    $parts = preg_split('#(<blockquote\b.*?</blockquote>)#is', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $chunk) {
        if (trim($chunk) === '') {
            continue;
        }
        if (stripos($chunk, '<blockquote') === 0) {
            echo '<div class="bodycopy">' . $chunk . '</div>';
        } else {
            echo '<div class="sd-measure"><div class="bodycopy">' . $chunk . '</div></div>';
        }
    }
    ?>

    <div class="sd-measure">
      <?= ad_slot('article') ?>

      <?php if ($post['source_url']): ?>
      <p style="font-size:15px;color:var(--grey)">Source material: <a href="<?= e($post['source_url']) ?>" rel="nofollow noopener"><?= e(parse_url($post['source_url'], PHP_URL_HOST) ?: $post['source_url']) ?></a></p>
      <?php endif; ?>

      <p class="sd-fundnote"><?= e(setting('funding_note', 'The Standard is funded by readers. No advertising from anyone the paper covers.')) ?> <a href="<?= e(url('corrections')) ?>">Corrections policy</a>.</p>

      <?php if ($tags): ?>
      <div class="sd-tags">
        <?php foreach ($tags as $tag): ?>
        <a href="<?= e(url('search') . '?q=' . urlencode($tag['name'])) ?>"><?= e($tag['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="sd-share">
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
  </article>

  <?php if ($more): ?>
  <section class="sd-more" aria-label="More arguments">
    <div class="sd-sechead">
      <h2>More arguments</h2>
      <a href="<?= e(url('search')) ?>">The archive</a>
    </div>
    <div class="sd-river">
      <?php foreach ($more as $p): ?>
      <a class="sd-card" href="<?= e(url('story/' . $p['slug'])) ?>">
        <?php if ($p['image']): ?>
        <span class="ph"><img src="<?= e($p['image']) ?>" alt="" loading="lazy"></span>
        <?php else: ?>
        <span class="noph"><span><?= e($p['title']) ?></span></span>
        <?php endif; ?>
        <span class="body">
          <span class="sd-kicker">Opinion<?= !empty($p['category_name']) ? ' · ' . e($p['category_name']) : '' ?></span>
          <h2><?= e($p['title']) ?></h2>
          <span class="by"><?= (int) read_minutes($p) ?> min read</span>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>
