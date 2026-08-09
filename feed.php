<?php
/** The Prairie Post — RSS 2.0 feed of the latest 20 stories. */
require __DIR__ . '/app/bootstrap.php';

$posts = latest_posts(20);
$siteTitle = setting('site_title', 'The Prairie Post');

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title><?= e($siteTitle) ?></title>
  <link><?= e(site_url()) ?>/</link>
  <description><?= e(setting('meta_description')) ?></description>
  <language>en-ca</language>
  <lastBuildDate><?= e(date(DATE_RSS)) ?></lastBuildDate>
  <atom:link href="<?= e(site_url()) ?>/feed/" rel="self" type="application/rss+xml"/>
<?php foreach ($posts as $post): ?>
  <item>
    <title><?= e($post['title']) ?></title>
    <link><?= e(site_url()) ?>/story/<?= e($post['slug']) ?></link>
    <guid isPermaLink="true"><?= e(site_url()) ?>/story/<?= e($post['slug']) ?></guid>
    <pubDate><?= e(date(DATE_RSS, strtotime($post['published_at']))) ?></pubDate>
    <?php if ($post['category_name']): ?><category><?= e($post['category_name']) ?></category>
    <?php endif; ?><description><?= e(excerpt((string) ($post['lede'] ?: $post['body']), 300)) ?></description>
  </item>
<?php endforeach; ?>
</channel>
</rss>
