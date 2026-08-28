<?php
/**
 * The London Lookout — article page (site canvas §isArticle).
 * Included by article.php after page_header(); $post is resolved.
 *
 * Breadcrumb, kicker rule, headline, standfirst, the byline row with its
 * initials disc, the lead figure, the body with its pull quote, then the
 * accountability furniture the brand book asks for: "How we know this"
 * (the shared provenance box — it also carries an agent's sources when
 * Hermes files here) and the update log.
 *
 * The update log shows what the platform can currently prove: publication,
 * plus a revision line when the story has been edited since. A multi-entry
 * log needs the ingest revision path — tracked in PLAN's backlog.
 */
$llRelated = !empty($post['category_id'])
    ? posts_in_category((int) $post['category_id'], 2, [(int) $post['id']])
    : [];
$llMost = latest_posts(4, [(int) $post['id']]);
$llArt = fn (array $p) => $p['image'] ?: site_asset('img/skyline-night.svg');
$llInitials = function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $w) {
        $out .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return $out ?: 'LL';
};
$llPub = strtotime((string) $post['published_at']);
$llUpd = strtotime((string) ($post['updated_at'] ?: $post['published_at']));
?>

<div class="ll-art ll-wrap">
  <div class="ll-crumbs">
    <a href="/">Home</a>
    <?php if ($post['category_name']): ?>
    <span class="sep">/</span>
    <a href="<?= e(url('desk/' . $post['category_slug'])) ?>"><?= e(pp_desk_label($post['category_slug'], $post['category_name'])) ?></a>
    <?php endif; ?>
    <?php if ($post['dateline']): ?><span class="sep">/</span><span><?= e($post['dateline']) ?></span><?php endif; ?>
  </div>

  <div class="ll-artgrid">
    <article class="ll-artmain">
      <div class="ll-lead tagrow" style="display:flex;align-items:center;gap:12px">
        <?php if ($post['category_name']): ?>
        <span class="ll-kick"><?= e(pp_desk_label($post['category_slug'], $post['category_name'])) ?></span>
        <span style="width:22px;height:1px;background:var(--ll-accent)"></span>
        <?php endif; ?>
        <span style="font-size:11px;color:var(--ll-n600)"><?= e(read_minutes($post)) ?> min read</span>
      </div>
      <h1><?= e($post['title']) ?></h1>
      <?php if ($post['lede']): ?><p class="stand"><?= e($post['lede']) ?></p><?php endif; ?>

      <div class="ll-byrow">
        <div class="who">
          <?php if ($post['byline']): ?>
          <span class="av"><?= e($llInitials((string) $post['byline'])) ?></span>
          <span>
            <span class="n">By <?= e($post['byline']) ?></span><br>
            <span class="t"><?= e(pp_date_long($llPub)) ?><?= $llUpd > $llPub ? ' · Updated ' . e(date('H:i', $llUpd)) : '' ?></span>
          </span>
          <?php else: ?>
          <span class="t"><?= e(pp_date_long($llPub)) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <figure class="lede-fig"><img src="<?= e($llArt($post)) ?>" alt=""></figure>
      <?php if ($post['image'] && $post['image_caption']): ?>
      <figcaption style="margin-bottom:24px"><?= e($post['image_caption']) ?> <?php if ($post['image_credit']): ?><span class="credit">Photo: <?= e($post['image_credit']) ?></span><?php endif; ?></figcaption>
      <?php endif; ?>

      <div class="ll-body">
        <?= sanitize_html((string) $post['body']) ?>
      </div>

<?= pp_provenance_box($post) ?>
      <section class="ll-updates" aria-label="Update log">
        <span class="ll-kick ll-kick--n">Update log</span>
        <?php if ($llUpd > $llPub): ?>
        <div class="row">
          <span class="when"><?= e(date('j M H:i', $llUpd)) ?></span>
          <span class="what">Story updated after publication.</span>
        </div>
        <div class="ll-hair"></div>
        <?php endif; ?>
        <div class="row">
          <span class="when"><?= e(date('j M H:i', $llPub)) ?></span>
          <span class="what">Published.</span>
        </div>
      </section>

      <?php if (setting('contact_email') !== ''): ?>
      <div class="ll-tipbox">
        <h3>Do you know something about this story?</h3>
        <p>We read every reply. Nothing is published without your consent, and documents can come to us without your name attached.</p>
        <p><a class="ll-btn" href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a></p>
      </div>
      <?php endif; ?>

      <?php if ($llRelated): ?>
      <section class="ll-related">
        <h3 style="font-size:20px">More from this desk</h3>
        <div class="grid">
          <?php foreach ($llRelated as $p): ?>
          <article>
            <figure><img src="<?= e($llArt($p)) ?>" alt=""></figure>
            <span class="ll-kick ll-kick--n"><?= e($p['dateline'] ?: pp_desk_label($p['category_slug'], $p['category_name'])) ?></span>
            <h3 style="margin-top:7px"><a href="<?= e(post_href($p)) ?>" style="color:inherit"><?= e($p['title']) ?></a></h3>
          </article>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
    </article>

    <aside class="ll-aside">
      <?php if ($llMost): ?>
      <section class="panel">
        <div class="head"><span class="ll-kick ll-kick--n">Most read today</span></div>
        <?php foreach ($llMost as $i => $p): ?>
        <?php if ($i): ?><div class="ll-hair"></div><?php endif; ?>
        <div class="rank">
          <span class="n"><?= $i + 1 ?></span>
          <h3><a href="<?= e(post_href($p)) ?>" style="color:inherit"><?= e($p['title']) ?></a></h3>
        </div>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>
      <div class="ll-digest">
        <span class="ll-kick"><?= e(setting('newsletter_heading', 'The 7am digest')) ?></span>
        <p><?= e(setting('newsletter_copy', 'Everything London needs to know, in one email before work.')) ?></p>
        <a class="ll-btn" href="<?= e(url('newsletter/')) ?>">Subscribe free</a>
      </div>
    </aside>
  </div>
</div>
