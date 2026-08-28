<?php
/**
 * The London Lookout — section front (site canvas §isSection).
 * Included by section.php; $cat, $posts, $page, $pages resolved.
 *
 * Section header with its standing description and story count, an
 * image lead, then the thumbnail list. The "next at city hall" panel is
 * settings-driven and appears on the City Hall desk only.
 */
$llLead = $posts[0] ?? null;
$llRest = array_slice($posts, 1);
$llArt = fn (array $p) => $p['image'] ?: site_asset('img/skyline-night.svg');
$llAgenda = json_decode((string) setting('council_agenda'), true);
$llAgenda = (is_array($llAgenda) && $cat['slug'] === 'city-hall') ? $llAgenda : [];
$llCount = count_posts_in_category((int) $cat['id']);
?>

<div class="ll-sec ll-wrap">
  <header class="ll-sechead">
    <div>
      <span class="ll-kick">Section</span>
      <h1><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h1>
      <?php if (!empty($cat['description'])): ?><p><?= e($cat['description']) ?></p><?php endif; ?>
    </div>
    <span class="count"><?= e(number_format($llCount)) ?> <?= $llCount === 1 ? 'story' : 'stories' ?></span>
  </header>

  <div class="ll-artgrid" style="padding-top:26px">
    <div>
      <?php if ($llLead): ?>
      <article class="ll-seclead" style="padding-top:0">
        <figure><img src="<?= e($llArt($llLead)) ?>" alt=""></figure>
        <div>
          <span class="ll-kick"><?= e($llLead['dateline'] ?: pp_desk_label($cat['slug'], $cat['name'])) ?></span>
          <h2 style="margin-top:11px"><a href="<?= e(post_href($llLead)) ?>" style="color:inherit"><?= e($llLead['title']) ?></a></h2>
          <?php if ($llLead['lede']): ?><p><?= e(excerpt($llLead['lede'], 190)) ?></p><?php endif; ?>
          <p class="ll-meta" style="margin-top:11px"><?php if ($llLead['byline']): ?>By <?= e($llLead['byline']) ?> · <?php endif; ?><?= e(read_minutes($llLead)) ?> min</p>
        </div>
      </article>
      <?php endif; ?>

      <?php if ($llRest): ?>
      <div class="ll-rule" style="margin-top:26px"></div>
      <div class="ll-list">
        <?php foreach ($llRest as $p): ?>
        <article>
          <figure><img src="<?= e($llArt($p)) ?>" alt=""></figure>
          <div>
            <span class="ll-kick ll-kick--n"><?= e($p['dateline'] ?: pp_desk_label($p['category_slug'], $p['category_name'])) ?></span>
            <h2 style="margin-top:8px"><a href="<?= e(post_href($p)) ?>" style="color:inherit"><?= e($p['title']) ?></a></h2>
            <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 130)) ?></p><?php endif; ?>
            <p class="ll-meta" style="margin-top:8px"><?php if ($p['byline']): ?>By <?= e($p['byline']) ?> · <?php endif; ?><?= e(date('j F', strtotime((string) $p['published_at']))) ?></p>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if (!$posts): ?>
      <p class="ll-empty">Nothing on this desk yet — the newsroom is on it.</p>
      <?php endif; ?>

      <?php if (($pages ?? 1) > 1 && ($page ?? 1) < $pages): ?>
      <div class="ll-more"><a class="ll-btn" href="<?= e(url('desk/' . $cat['slug'])) ?>?page=<?= (int) $page + 1 ?>">Load more <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a></div>
      <?php endif; ?>
    </div>

    <aside class="ll-aside">
      <?php if ($llAgenda): ?>
      <section class="ll-tracker">
        <span class="ll-kick">Next at City Hall</span>
        <div style="margin-top:13px">
          <?php foreach ($llAgenda as $i => $row): ?>
          <?php if ($i): ?><div class="ll-hair"></div><?php endif; ?>
          <div style="padding:8px 0">
            <div style="font-size:13px;font-weight:500;line-height:1.35"><?= e((string) ($row['what'] ?? '')) ?></div>
            <div style="font-size:11px;line-height:1.5;color:var(--ll-n600)"><?= e((string) ($row['when'] ?? '')) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <span style="display:block;font-size:10px;line-height:1.5;color:var(--ll-n600);margin-top:10px">Agendas post five days ahead. We read them so you don&rsquo;t have to.</span>
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
