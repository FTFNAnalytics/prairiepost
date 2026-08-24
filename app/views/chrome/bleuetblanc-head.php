<?php
// La ligne sous le nom du journal : la devise à la une, la rubrique ailleurs,
// pour que le lecteur sache toujours dans quelle section il se trouve.
$bbRub = $tagline;
// Toutes les rubriques, pas seulement celles de la barre : « le fil » n'y
// figure pas et doit tout de même se nommer.
foreach ($activeDesk !== '' ? categories_all() : [] as $c) {
    if ($c['slug'] === $activeDesk) {
        $bbRub = pp_desk_label($c['slug'], $c['name']);
        break;
    }
}
?>
<div class="bb-page">
  <div class="bb-bandeau">
    <span>Édition du <?= e(pp_date_full()) ?></span>
    <span class="grp">
      <a href="<?= e(url('newsletter/')) ?>"><?= e(pp_t('Newsletters')) ?></a>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(pp_t('Contact')) ?></a><?php endif; ?>
      <a href="/admin/"><?= e(pp_t('Sign in')) ?></a>
    </span>
  </div>

  <header class="bb-tete">
    <a class="bb-lock" href="/" aria-label="<?= e($siteTitle) ?>">
      <span class="sym" aria-hidden="true"><img src="<?= e(site_asset('mark.svg')) ?>" alt="" width="46" height="46"></span>
      <span>
        <span class="bb-nom"><?= e($siteTitle) ?></span>
        <span class="rub" style="display:block"><?= e($bbRub) ?></span>
      </span>
    </a>
    <form class="bb-chercher" method="get" action="<?= e(url('search')) ?>" role="search">
      <input type="search" name="q" placeholder="<?= e(pp_t('Search')) ?>" aria-label="<?= e(pp_t('Search the archive')) ?>">
      <button class="bb-btn" type="submit"><?= e(pp_t('Search')) ?></button>
    </form>
  </header>

  <nav class="bb-rubriques" aria-label="<?= e(pp_t('Sections')) ?>">
    <a href="/"<?= ($GLOBALS['pp_front_page'] ?? false) ? ' aria-current="page"' : '' ?>><?= e(pp_t('Home')) ?></a>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
  </nav>
