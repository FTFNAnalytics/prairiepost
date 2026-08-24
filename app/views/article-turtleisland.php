<?php
/**
 * Turtle Island Times — article (chrome.template = "turtleisland").
 * Included by article.php after page_header(); $post, $tags and $canonical
 * are already resolved.
 *
 * Screen 2a of the package: condensed masthead, one measure, rail on the
 * right. The rail is the only place a rule prints on this paper — a hairline
 * on its left edge — because the article is the one page where two columns
 * of unequal weight sit side by side and whitespace alone won't separate them.
 *
 * Links inside the body take the cyan; the pull quote takes the serif's true
 * italic against a spot-colour edge. No boxes anywhere.
 */

$more = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id'], 4);
$deskName = $post['category_name'] ?? '';
?>

  <div class="ti-art">
    <article>
      <div class="measure">
        <?php if ($deskName !== ''): ?>
        <p class="ti-kicker"><a style="color:inherit" href="<?= e(url('desk/' . $post['category_slug'])) ?>"><?= e(pp_desk_label($post['category_slug'] ?? null, $deskName)) ?></a></p>
        <?php endif; ?>
        <h1><?= e($post['title']) ?></h1>
        <?php if ($post['lede']): ?><p class="standfirst"><?= e($post['lede']) ?></p><?php endif; ?>
        <div class="byrow">
          <span>By <strong><?php if (!empty($post['author_slug'])): ?><a style="color:inherit" href="<?= e(url('author/' . $post['author_slug'])) ?>"><?= e($post['byline']) ?></a><?php else: ?><?= e($post['byline'] ?: setting('site_title')) ?><?php endif; ?></strong></span>
          <?php if (!empty($post['dateline'])): ?><span><?= e($post['dateline']) ?></span><?php endif; ?>
          <span><?= e(fmt_date($post['published_at'], 'j F Y')) ?></span>
          <span><?= (int) read_minutes($post) ?> min</span>
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
      <div class="measure">
        <div class="ti-corr">
          <strong>Correction &middot; <?= e(fmt_date($post['corrected_at'], 'j F Y')) ?></strong><br>
          <?= e((string) $post['correction']) ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="measure">
        <div class="ti-body"><?= sanitize_html((string) $post['body']) ?></div>

        <?= ad_slot('article') ?>

        <?php if ($post['source_url']): ?>
        <p class="ti-corr">Source material: <a href="<?= e($post['source_url']) ?>" rel="nofollow noopener"><?= e(parse_url($post['source_url'], PHP_URL_HOST) ?: $post['source_url']) ?></a></p>
        <?php endif; ?>

        <p class="ti-corr">Corrections and clarifications: <a href="<?= e(url('corrections')) ?>">tell us what we got wrong</a>.</p>

        <?php if ($tags): ?>
        <p class="ti-corr">
          <?php foreach ($tags as $tag): ?>
          <a href="<?= e(url('search') . '?q=' . urlencode($tag['name'])) ?>"><?= e($tag['name']) ?></a>
          <?php endforeach; ?>
        </p>
        <?php endif; ?>
      </div>
    </article>

    <aside class="ti-side">
      <?php if ($more): ?>
      <div>
        <h4><?= $deskName !== '' ? 'More in ' . e(pp_desk_label($post['category_slug'] ?? null, $deskName)) : 'More stories' ?></h4>
        <?php foreach ($more as $p): ?>
        <a class="it" href="<?= e(url('story/' . $p['slug'])) ?>">
          <h5><?= e($p['title']) ?></h5>
          <span class="k"><?= (int) read_minutes($p) ?> min</span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div>
        <h4><?= e(setting('newsletter_heading', 'The morning brief')) ?></h4>
        <?php if (isset($_GET['subscribed'])): ?>
        <p style="font-size:14px">You're on the list.</p>
        <?php else: ?>
        <p style="font-size:14px;color:var(--n-700);margin:0 0 12px"><?= e(setting('newsletter_copy', 'One email, weekday mornings, five minutes.')) ?></p>
        <form method="post" action="<?= e(url('subscribe')) ?>" style="display:flex;flex-direction:column;gap:8px">
          <input type="email" name="email" required placeholder="you@example.ca" aria-label="Email address"
                 style="font-family:var(--serif);font-size:14px;padding:8px 10px;border:1px solid var(--rule);border-radius:2px;background:#fff">
          <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
          <button type="submit" style="font-family:var(--serif);font-size:14px;font-weight:600;padding:8px 14px;border:0;border-radius:2px;background:var(--spot);color:var(--paper);cursor:pointer">Sign up</button>
        </form>
        <?php endif; ?>
      </div>

      <div class="ti-share" style="font-size:13px">
        <h4>Share</h4>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= e(urlencode($canonical)) ?>" target="_blank" rel="noopener">Facebook</a>
        <a href="https://bsky.app/intent/compose?text=<?= e(urlencode($post['title'] . ' ' . $canonical)) ?>" target="_blank" rel="noopener">Bluesky</a>
        <a href="mailto:?subject=<?= e(rawurlencode($post['title'])) ?>&amp;body=<?= e(rawurlencode($canonical)) ?>">Email</a>
      </div>
    </aside>
  </div>
