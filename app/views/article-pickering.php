<?php
/**
 * The Pickering Post — article (chrome.template = "pickering").
 * Included by article.php after page_header(); $post, $tags and $canonical
 * are already resolved.
 *
 * Body copy sets to the package's 58-character measure at 17px. Standing
 * copy is never greyed and never centred — only the byline row and the
 * caption drop to the quiet ink, because they are furniture rather than
 * running text.
 */

$more = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id'], 3);
$deskName = $post['category_name'] ?? '';
$deskSlug = $post['category_slug'] ?? '';

$slugClass = 'pk-slug';
if ($deskSlug === 'opinion') {
    $slugClass .= ' pk-slug--opinion';
} elseif ($deskSlug === 'obituaries') {
    $slugClass .= ' pk-slug--obit';
} elseif ($deskSlug === 'breaking') {
    $slugClass .= ' pk-slug--breaking';
}
?>

<div class="pk-main">
  <div class="pk-well">
    <article class="pk-art">
      <div class="head">
        <?php if ($deskName !== ''): ?>
        <a class="<?= e($slugClass) ?>" href="<?= e(url('desk/' . $deskSlug)) ?>"><?= e(pp_desk_label($deskSlug ?: null, $deskName)) ?></a>
        <?php endif; ?>
        <h1><?= e($post['title']) ?></h1>
        <?php if ($post['lede']): ?><p class="standfirst"><?= e($post['lede']) ?></p><?php endif; ?>
        <div class="byrow">
          <span><strong><?php if (!empty($post['author_slug'])): ?><a style="color:inherit" href="<?= e(url('author/' . $post['author_slug'])) ?>"><?= e($post['byline']) ?></a><?php else: ?><?= e($post['byline'] ?: setting('site_title')) ?><?php endif; ?></strong></span>
          <?php if (!empty($post['dateline'])): ?><span><?= e($post['dateline']) ?></span><?php endif; ?>
          <span><?= e(fmt_date($post['published_at'], 'F j, Y')) ?></span>
          <span><?= (int) read_minutes($post) ?> min read</span>
        </div>
      </div>

      <?php if ($post['image']): ?>
      <figure>
        <img src="<?= e($post['image']) ?>" alt="<?= e($post['image_caption'] ?: $post['title']) ?>">
        <?php if ($post['image_caption'] !== '' || $post['image_credit'] !== ''): ?>
        <figcaption><?= e($post['image_caption']) ?><?php if ($post['image_credit'] !== ''): ?> <em><?= e($post['image_credit']) ?></em><?php endif; ?></figcaption>
        <?php endif; ?>
      </figure>
      <?php endif; ?>

      <?php if (!empty($post['correction'])): ?>
      <p class="pk-note">
        <strong>Correction &middot; <?= e(fmt_date($post['corrected_at'], 'F j, Y')) ?></strong><br>
        <?= e((string) $post['correction']) ?>
      </p>
      <?php endif; ?>

      <div class="pk-body"><?= sanitize_html((string) $post['body']) ?></div>
<?= pp_provenance_box($post) ?>

      <div style="max-width:58ch">
        <?= ad_slot('article') ?>

        <?php if ($post['source_url']): ?>
        <p class="pk-note">Source material: <a href="<?= e($post['source_url']) ?>" rel="nofollow noopener"><?= e(parse_url($post['source_url'], PHP_URL_HOST) ?: $post['source_url']) ?></a></p>
        <?php endif; ?>

        <p class="pk-note">Something wrong in this story? <a href="<?= e(url('corrections')) ?>">Tell the newsroom</a>.</p>

        <?php if ($tags): ?>
        <p class="pk-note">
          <?php foreach ($tags as $tag): ?>
          <a href="<?= e(url('search') . '?q=' . urlencode($tag['name'])) ?>"><?= e($tag['name']) ?></a>
          <?php endforeach; ?>
        </p>
        <?php endif; ?>

        <p class="pk-note">
          Share:
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?= e(urlencode($canonical)) ?>" target="_blank" rel="noopener">Facebook</a> &middot;
          <a href="https://bsky.app/intent/compose?text=<?= e(urlencode($post['title'] . ' ' . $canonical)) ?>" target="_blank" rel="noopener">Bluesky</a> &middot;
          <a href="mailto:?subject=<?= e(rawurlencode($post['title'])) ?>&amp;body=<?= e(rawurlencode($canonical)) ?>">Email</a>
        </p>
      </div>
    </article>

    <?php if ($more): ?>
    <section style="margin:56px 0 0" aria-label="More from this desk">
      <div class="pk-sechead">
        <h2><?= $deskName !== '' ? 'More from ' . e(pp_desk_label($deskSlug ?: null, $deskName)) : 'More stories' ?></h2>
        <a href="<?= e(url('search')) ?>">The archive</a>
      </div>
      <div class="pk-grid">
        <?php foreach ($more as $p): ?>
        <article class="pk-item">
          <?php if ($p['image']): ?>
          <a class="shot" href="<?= e(url('story/' . $p['slug'])) ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= e($p['image']) ?>" alt="" loading="lazy">
          </a>
          <?php endif; ?>
          <h3><a style="color:inherit" href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
          <p class="by"><?= (int) read_minutes($p) ?> min read</p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>

  <aside class="pk-rail">
    <div class="pk-sub">
      <h3><?= e(setting('newsletter_heading', 'The morning email')) ?></h3>
      <?php if (isset($_GET['subscribed'])): ?>
      <p class="done">You're on the list.</p>
      <?php else: ?>
      <p><?= e(setting('newsletter_copy', 'Six stories from Pickering in your inbox by seven.')) ?></p>
      <form method="post" action="<?= e(url('subscribe')) ?>">
        <input type="email" name="email" required placeholder="you@example.ca" aria-label="Email address">
        <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button type="submit">Sign up</button>
      </form>
      <?php endif; ?>
    </div>

    <div class="pk-mod">
      <div class="bd">
        <form class="pk-search" method="get" action="<?= e(url('search')) ?>" role="search">
          <input type="search" name="q" placeholder="Search the archive" aria-label="Search the archive">
          <button class="pk-btn" type="submit">Go</button>
        </form>
      </div>
    </div>

    <?= ad_slot('rail') ?>
  </aside>
</div>
