<?php
/**
 * The Kitchener Chronicle — article page (design pkg plate 05).
 * Included by article.php after page_header(); $post is resolved.
 *
 * Share rail, kicker, headline, italic dek, byline row between
 * hairlines, sketch figure, 66ch body with the gold pull quote, the
 * provenance box for agent filings, related titles over a navy rule,
 * and the navy newsletter card in the aside.
 */
$kcRelated = !empty($post['category_id'])
    ? posts_in_category((int) $post['category_id'], 3, [(int) $post['id']])
    : [];
$kcMail = 'mailto:?subject=' . rawurlencode((string) $post['title'])
        . '&body=' . rawurlencode(url('story/' . $post['slug']));
?>

<div class="kc-art in">
  <div class="cols">
    <aside class="kc-share" aria-label="Share">
      <span class="lbl">Share</span>
      <a href="<?= e($kcMail) ?>">Email</a>
      <a href="#" onclick="window.print();return false">Print</a>
    </aside>

    <article>
      <?php if ($post['category_name']): ?>
      <span class="kc-kicker" style="margin-bottom:14px"><?= e(pp_desk_label($post['category_slug'], $post['category_name'])) ?></span>
      <?php endif; ?>
      <h1><?= e($post['title']) ?></h1>
      <?php if ($post['lede']): ?><p class="dek"><?= e($post['lede']) ?></p><?php endif; ?>
      <div class="byrow">
        <?php if ($post['byline']): ?><span class="who">By <?= e($post['byline']) ?></span><span>&middot;</span><?php endif; ?>
        <span>Published <?= e(pp_date_long(strtotime((string) $post["published_at"]))) ?></span>
        <span>&middot;</span><span><?= e(read_minutes($post)) ?> min read</span>
      </div>

      <?php if ($post['image']): ?>
      <figure class="lede-fig"><img src="<?= e($post['image']) ?>" alt=""></figure>
      <?php if ($post['dateline']): ?><figcaption style="margin-bottom:32px"><?= e($post['dateline']) ?>. <span>Sketch: Chronicle graphics</span></figcaption><?php endif; ?>
      <?php endif; ?>

      <div class="kc-body">
        <?= sanitize_html((string) $post['body']) ?>
      </div>

<?= pp_provenance_box($post) ?>
      <?php if ($kcRelated): ?>
      <div class="kc-related">
        <span class="kc-kicker">More on this desk</span>
        <?php foreach ($kcRelated as $p): ?>
        <h2><a href="<?= e(post_href($p)) ?>"><?= e($p['title']) ?></a></h2>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </article>

    <aside class="kc-aside">
      <div class="kc-topics">
        <h2>In this story</h2>
        <p><?= e(implode(' · ', array_filter([
            $post['dateline'] ?: null,
            pp_desk_label($post['category_slug'], $post['category_name']) ?: null,
            'Waterloo Region',
        ]))) ?></p>
      </div>
      <div class="kc-nlcard">
        <span class="kc-kicker"><?= e(setting('newsletter_heading', 'The Morning Chronicle')) ?></span>
        <div class="pitch"><?= e(setting('newsletter_copy', 'The region in six items, by 6 a.m.')) ?></div>
        <a class="go" href="<?= e(url('newsletter/')) ?>">Sign up free</a>
      </div>
    </aside>
  </div>
</div>
