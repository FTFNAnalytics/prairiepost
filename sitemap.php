<?php
/** The Prairie Dispatch — XML sitemap. */
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

$base = site_url();
$rows = [];
$rows[] = [$base . '/', date('Y-m-d'), 'hourly', '1.0'];
foreach (categories_all() as $cat) {
    $rows[] = [$base . '/desk/' . $cat['slug'], date('Y-m-d'), 'daily', '0.7'];
}
$stmt = db()->prepare("SELECT p.slug, p.published_at, p.updated_at FROM posts p
    JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = " . current_site_id() . "
    WHERE p.status = 'published' AND p.published_at <= ? ORDER BY p.published_at DESC LIMIT 5000");
$stmt->execute([now()]);
foreach ($stmt as $post) {
    $rows[] = [
        $base . '/story/' . $post['slug'],
        date('Y-m-d', strtotime($post['updated_at'] ?: $post['published_at'])),
        'monthly',
        '0.6',
    ];
}
$authors = db()->query("SELECT DISTINCT u.slug FROM users u
    JOIN posts p ON p.author_id = u.id AND p.status = 'published'
    JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = " . current_site_id() . "
    WHERE u.slug != ''")->fetchAll();
foreach ($authors as $a) {
    $rows[] = [$base . '/author/' . $a['slug'], date('Y-m-d'), 'weekly', '0.4'];
}
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($rows as [$loc, $mod, $freq, $pri]): ?>
  <url><loc><?= e($loc) ?></loc><lastmod><?= e($mod) ?></lastmod><changefreq><?= e($freq) ?></changefreq><priority><?= e($pri) ?></priority></url>
<?php endforeach; ?>
</urlset>
