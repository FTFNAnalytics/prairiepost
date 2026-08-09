<?php
/** The Prairie Post — XML sitemap. */
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

$base = site_url();
$rows = [];
$rows[] = [$base . '/', date('Y-m-d'), 'hourly', '1.0'];
foreach (categories_all() as $cat) {
    $rows[] = [$base . '/desk/' . $cat['slug'], date('Y-m-d'), 'daily', '0.7'];
}
$stmt = db()->prepare("SELECT slug, published_at, updated_at FROM posts
    WHERE status = 'published' AND published_at <= ? ORDER BY published_at DESC LIMIT 5000");
$stmt->execute([now()]);
foreach ($stmt as $post) {
    $rows[] = [
        $base . '/story/' . $post['slug'],
        date('Y-m-d', strtotime($post['updated_at'] ?: $post['published_at'])),
        'monthly',
        '0.6',
    ];
}
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($rows as [$loc, $mod, $freq, $pri]): ?>
  <url><loc><?= e($loc) ?></loc><lastmod><?= e($mod) ?></lastmod><changefreq><?= e($freq) ?></changefreq><priority><?= e($pri) ?></priority></url>
<?php endforeach; ?>
</urlset>
