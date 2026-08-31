<?php
/**
 * Scaffold a new paper as files only — the phase-2 compliance-gate shape.
 *
 *     php tools/make-paper.php <site-slug> <template> "The Paper Name"
 *     php tools/make-paper.php aurora-times auroratimes "The Aurora Times"
 *
 * Writes, and refuses to overwrite:
 *
 *     assets/sites/<slug>/palette.json     identity + chrome declaration
 *     assets/sites/<slug>/launch.php       pack skeleton with a domains entry
 *     assets/css/<template>.css            design-system stub
 *     app/views/front-<template>.php       front page stub
 *     app/views/article-<template>.php     article stub
 *     app/views/section-<template>.php     section front stub
 *     app/views/chrome/<template>-head.php masthead partial stub
 *     app/views/chrome/<template>-foot.php footer partial stub
 *
 * Nothing shared is touched — that is the point. The stubs render a plain
 * but complete paper immediately (the scaffold acceptance test in PLAN.md
 * phase 2); the design build replaces their bodies and nothing else.
 * The slug and the template are different names on purpose: the slug is
 * hyphenated data (site row, pack directory), the template is a bare word
 * (file names, body class t-<template>).
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}
$root = dirname(__DIR__);
$slug = $argv[1] ?? '';
$tpl  = $argv[2] ?? '';
$name = $argv[3] ?? '';
if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) || !preg_match('/^[a-z0-9]+$/', $tpl) || $name === '') {
    exit("Usage: php tools/make-paper.php <site-slug> <template> \"The Paper Name\"\n"
       . "  slug: lowercase, hyphens allowed.  template: lowercase, NO hyphens.\n");
}

$files = [
    "assets/sites/$slug/palette.json" => <<<JSON
{
  "palette": {
    "ink":   "#20242B",
    "paper": "#F7F6F3"
  },
  "chrome": {
    "template": "$tpl",
    "nav": []
  }
}
JSON,
    "assets/sites/$slug/launch.php" => <<<PHP
<?php
/**
 * $name — launch pack skeleton (scaffolded by tools/make-paper.php).
 * Fill in desks, settings, sources and stories before launch; every
 * story slug must be explicit and prefixed to avoid the network-wide
 * posts.slug constraint. Test with tools/seed-launch.php against a
 * database seeded from ALL existing packs.
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['CHANGE-ME.ca', 'www.CHANGE-ME.ca'],

    'desks' => [],
    'settings' => [
        'site_title' => '$name',
        'tagline'    => 'CHANGE ME',
    ],
    'sources' => [],
    'stories' => [],
];
PHP,
    "assets/css/$tpl.css" => <<<CSS
/* $name — design system ($tpl template). Scaffolded stub: replace with
   the brand build. Rules to keep from the network's hard lessons:
   every font-family you NAME must be a face you DECLARE (@font-face in
   THIS file — another paper's stylesheet loading it does not count),
   and no inline element may carry block-only properties. */
body.t-$tpl { background: #F7F6F3; color: #20242B; }
.t-$tpl .scaffold-note { padding: 8px 16px; font-size: 13px; opacity: .6; }
CSS,
    "app/views/front-$tpl.php" => <<<PHP
<?php /* $name — front page (scaffold stub; \$hero is already resolved). */ ?>
<div class="wrap">
  <p class="scaffold-note">front-$tpl.php — replace with the design build.</p>
  <?php if (\$hero): ?><h1><a href="<?= e(url('story/' . \$hero['slug'])) ?>"><?= e(\$hero['title']) ?></a></h1><?php endif; ?>
  <?php foreach (latest_posts(10, \$hero ? [(int) \$hero['id']] : []) as \$p): ?>
  <h3><a href="<?= e(url('story/' . \$p['slug'])) ?>"><?= e(\$p['title']) ?></a></h3>
  <?php endforeach; ?>
</div>
PHP,
    "app/views/article-$tpl.php" => <<<PHP
<?php /* $name — article (scaffold stub; \$post is already resolved). */ ?>
<article class="wrap">
  <h1><?= e(\$post['title']) ?></h1>
  <?php if (\$post['lede']): ?><p><em><?= e(\$post['lede']) ?></em></p><?php endif; ?>
  <?= sanitize_html((string) \$post['body']) ?>
</article>
PHP,
    "app/views/section-$tpl.php" => <<<PHP
<?php /* $name — section front (scaffold stub; \$cat and \$posts are resolved). */ ?>
<div class="wrap">
  <h1><?= e(pp_desk_label(\$cat['slug'], \$cat['name'])) ?></h1>
  <?php foreach (\$posts as \$p): ?>
  <h3><a href="<?= e(url('story/' . \$p['slug'])) ?>"><?= e(\$p['title']) ?></a></h3>
  <?php endforeach; ?>
</div>
PHP,
    "app/views/chrome/$tpl-head.php" => <<<PHP
<?php /* $name — masthead chrome (scaffold stub). Variables in scope:
         \$siteTitle, \$tagline, \$activeDesk; nav via pp_nav_categories(). */ ?>
<header class="wrap">
  <p><a href="/" style="font-weight:700"><?= e(\$siteTitle) ?></a> — <?= e(\$tagline) ?></p>
  <nav>
    <?php foreach (pp_nav_categories() as \$cat): ?>
    <a href="<?= e(url('desk/' . \$cat['slug'])) ?>"<?= \$activeDesk === \$cat['slug'] ? ' aria-current="page"' : '' ?>><?= e(pp_desk_label(\$cat['slug'], \$cat['name'])) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
PHP,
    "app/views/chrome/$tpl-foot.php" => <<<PHP
<?php /* $name — footer chrome (scaffold stub). A foot partial closes the
         document itself. */ ?>
<footer class="wrap"><p>&copy; <?= e(date('Y')) ?> <?= e(\$siteTitle) ?></p></footer>
</body>
</html>
PHP,
];

foreach ($files as $rel => $body) {
    $path = "$root/$rel";
    if (file_exists($path)) {
        exit("REFUSED: $rel already exists — this tool never overwrites.\n");
    }
}
foreach ($files as $rel => $body) {
    $path = "$root/$rel";
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }
    file_put_contents($path, rtrim($body) . "\n");
    echo "  wrote $rel\n";
}
echo "Scaffolded '$name' ($slug / t-$tpl) as files only — nothing shared was touched.\n";
echo "Next: fill the launch pack, build $tpl.css and the five view files, then\n";
echo "run the baseline (PLAN.md phase 0.1) before opening the PR.\n";
