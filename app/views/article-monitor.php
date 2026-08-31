<?php
/**
 * The Mississauga Monitor — article (design canvas §02).
 * $post is resolved; $canonical and $cardUrl are set by article.php.
 * Kicker row carries desk + ward (from the dateline); pull quotes take
 * the 3px orange left rule; the provenance box renders for agent copy.
 */
$mmUpdated = ($post['updated_at'] ?? '') > ($post['published_at'] ?? '') ? $post['updated_at'] : '';
?>
<div class="mm-page mm-body-wrap">
  <article class="mm-article">
    <div class="kickrow">
      <?php if ($post['category_name']): ?>
      <span class="mm-kicker"><a href="<?= e(url('desk/' . $post['category_slug'])) ?>"><?= e(pp_desk_label($post['category_slug'], $post['category_name'])) ?></a></span>
      <?php endif; ?>
      <?php if ($post['dateline']): ?><span class="mm-kicker mm-kicker--muted"><?= e($post['dateline']) ?></span><?php endif; ?>
    </div>
    <h1><?= e($post['title']) ?></h1>
    <?php if ($post['lede']): ?><p class="standfirst"><?= e($post['lede']) ?></p><?php endif; ?>
    <div class="authrow">
      <?php if ($post['byline']): ?>
      <span class="who"><?php echo !empty($post['author_slug'])
          ? '<a href="' . e(url('author/' . $post['author_slug'])) . '">' . e($post['byline']) . '</a>'
          : e($post['byline']); ?></span>
      <?php endif; ?>
      <span><?= e(fmt_date($post['published_at'], 'F j, Y g:i a')) ?></span>
      <?php if ($mmUpdated): ?><span>Updated <?= e(fmt_date($mmUpdated, 'g:i a')) ?></span><?php endif; ?>
      <span><?= e(read_minutes($post)) ?> min read</span>
    </div>

    <?php if (!empty($post['correction'])): ?>
    <div class="mm-card" style="border-left:3px solid var(--mm-orange); margin-bottom:22px">
      <span class="mm-kicker" style="color:var(--mm-org-dk)">Correction</span>
      <p style="margin:8px 0 0; font-size:14.5px"><?= e((string) $post['correction']) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($post['image']): ?>
    <figure>
      <img src="<?= e($post['image']) ?>" alt="<?= e($post['image_caption'] ?: $post['title']) ?>">
      <?php if ($post['image_caption'] || $post['image_credit']): ?>
      <figcaption><?= e($post['image_caption']) ?><?= $post['image_credit'] ? ' — ' . e($post['image_credit']) : '' ?></figcaption>
      <?php endif; ?>
    </figure>
    <?php endif; ?>

    <div class="copy">
<?= sanitize_html((string) $post['body']) ?>
    </div>
    <?php $tags = tags_for_post((int) $post['id']); if ($tags): ?>
    <div class="mm-tags">
      <?php foreach ($tags as $tag): ?>
      <a href="<?= e(url('search')) ?>?q=<?= e(urlencode($tag['name'])) ?>"><?= e($tag['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <p class="mm-meta" style="margin-top:20px">Spotted an error? <a href="<?= e(url('corrections')) ?>">Let us know</a>.</p>
  </article>
</div>
