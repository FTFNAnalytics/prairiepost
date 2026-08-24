<?php
/**
 * Le Bleuet Blanc — la une d'une rubrique (chrome.template = "bleuetblanc").
 * Incluse par section.php après page_header(); $cat, $posts, $page et $pages
 * sont déjà résolus.
 *
 * Un aplat bleu porte le nom de la rubrique et sa description, puis la liste
 * revient sur papier. « Le fil » est la seule rubrique qui porte le magenta.
 */

$signature = function (array $p): string {
    $bouts = [];
    if (!empty($p['byline'])) {
        $bouts[] = pp_t('By') . ' ' . $p['byline'];
    }
    $bouts[] = read_minutes($p) . ' ' . pp_t('min read');
    return e(implode(' · ', $bouts));
};

$direct = ($cat['slug'] ?? '') === 'le-fil';
?>

<div class="bb-rubtete">
  <?php if ($direct): ?><p style="margin:0 0 12px"><span class="bb-direct">En direct</span></p><?php endif; ?>
  <h1><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h1>
  <?php if (!empty($cat['description'])): ?><p><?= e($cat['description']) ?></p><?php endif; ?>
</div>

<div class="bb-corps-page">
  <?php if (!$posts): ?>
  <div class="bb-vide">
    Rien de publié dans <?= e(pp_desk_label($cat['slug'], $cat['name'])) ?> pour l’instant.
    Voyez <a href="/">la une</a> ou une autre rubrique.
  </div>
  <?php else: ?>
  <div class="bb-liste">
    <?php foreach ($posts as $p): ?>
    <article class="item">
      <?php if ($p['image']): ?>
      <a class="shot" href="<?= e(url('story/' . $p['slug'])) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e($p['image']) ?>" alt="" loading="lazy"></a>
      <?php endif; ?>
      <h3><a style="color:inherit" href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 150)) ?></p><?php endif; ?>
      <p class="sig"><?= $signature($p) ?></p>
    </article>
    <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
  <nav class="bb-pagination" aria-label="Pages">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?><span aria-current="page"><?= $i ?></span>
      <?php else: ?><a href="<?= e(url('desk/' . $cat['slug']) . '?page=' . $i) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>
