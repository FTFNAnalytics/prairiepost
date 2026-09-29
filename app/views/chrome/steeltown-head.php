<?php /* The Steeltown Standard — masthead chrome (scaffold stub). Variables in scope:
         $siteTitle, $tagline, $activeDesk; nav via pp_nav_categories(). */ ?>
<header class="wrap">
  <p><a href="/" style="font-weight:700"><?= e($siteTitle) ?></a> — <?= e($tagline) ?></p>
  <nav>
    <?php foreach (pp_nav_categories() as $cat): ?>
    <a href="<?= e(url('desk/' . $cat['slug'])) ?>"<?= $activeDesk === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
