<?php
/**
 * The Kitchener Chronicle — section front (design pkg plate 04).
 * Included by section.php; $cat, $posts, $deskColor, $page, $pages resolved.
 *
 * Section title over a 2px rule, an image lead, a two-up, then dated
 * list rows with a gold tag. Civic desks (Ontario green in the palette)
 * carry the green accents in place of gold.
 */
$kcCivic = strcasecmp((string) ($deskColor ?? ''), '#1D5138') === 0;
$kcLead = $posts[0] ?? null;
$kcDuo  = array_slice($posts, 1, 2);
$kcRows = array_slice($posts, 3);
?>

<div class="kc-sec in<?= $kcCivic ? ' kc-civic' : '' ?>">
  <header class="kc-sechead">
    <h1><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h1>
    <?php if (!empty($cat['description'])): ?><p class="desc"><?= e($cat['description']) ?></p><?php endif; ?>
  </header>

  <div class="cols">
    <div>
      <?php if ($kcLead): ?>
      <article class="kc-seclead">
        <?php if ($kcLead['image']): ?><figure><img src="<?= e($kcLead['image']) ?>" alt=""></figure><?php endif; ?>
        <div>
          <?php if ($kcLead['dateline']): ?><span class="kc-kicker"><?= e($kcLead['dateline']) ?></span><?php endif; ?>
          <h2><a href="<?= e(post_href($kcLead)) ?>"><?= e($kcLead['title']) ?></a></h2>
          <?php if ($kcLead['lede']): ?><p><?= e(excerpt($kcLead['lede'], 220)) ?></p><?php endif; ?>
          <p class="kc-meta">
            <?php if ($kcLead['byline']): ?>By <?= e($kcLead['byline']) ?> &middot; <?php endif; ?>
            <?= e(date('j F', strtotime((string) $kcLead['published_at']))) ?>
          </p>
        </div>
      </article>
      <?php endif; ?>

      <?php if ($kcDuo): ?>
      <div class="kc-secduo">
        <?php foreach ($kcDuo as $p): ?>
        <article>
          <?php if ($p['dateline']): ?><span class="kc-kicker" style="margin-bottom:10px"><?= e($p['dateline']) ?></span><?php endif; ?>
          <h2><a href="<?= e(post_href($p)) ?>"><?= e($p['title']) ?></a></h2>
          <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 140)) ?></p><?php endif; ?>
          <?php if ($p['byline']): ?><p class="kc-meta" style="margin-top:12px">By <?= e($p['byline']) ?></p><?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($kcRows): ?>
      <div class="kc-list">
        <?php foreach ($kcRows as $p): ?>
        <div class="item">
          <span class="d"><?= e(date('j M', strtotime((string) $p['published_at']))) ?></span>
          <h2><a href="<?= e(post_href($p)) ?>"><?= e($p['title']) ?></a></h2>
          <span class="tag"><?= e($p['dateline'] ?: read_minutes($p) . ' min') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if (!$posts): ?>
      <p class="kc-empty">Nothing on this desk yet — the newsroom is on it.</p>
      <?php endif; ?>

      <?php if (($pages ?? 1) > 1 && ($page ?? 1) < $pages): ?>
      <div class="kc-more"><a href="<?= e(url('desk/' . $cat['slug'])) ?>?page=<?= (int) $page + 1 ?>">More in <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a></div>
      <?php endif; ?>
    </div>

    <aside class="kc-rail">
      <div class="box">
        <h2>Latest</h2>
        <?php foreach (latest_posts(4, array_map('intval', array_column(array_slice($posts, 0, 3), 'id'))) as $p): ?>
        <div class="row">
          <div class="when"><?= e(pp_desk_label($p['category_slug'], $p['category_name'])) ?></div>
          <a href="<?= e(post_href($p)) ?>"><?= e($p['title']) ?></a>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="kc-nlcard">
        <span class="kc-kicker"><?= e(setting('newsletter_heading', 'The Morning Chronicle')) ?></span>
        <div class="pitch"><?= e(setting('newsletter_copy', 'The region in six items, by 6 a.m.')) ?></div>
        <a class="go" href="<?= e(url('newsletter/')) ?>">Sign up free</a>
      </div>
    </aside>
  </div>
</div>
