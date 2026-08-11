<?php
/**
 * Launch-content seeder for a site joining the network.
 *
 * Joining sites deliberately self-provision with no sample content; this tool
 * loads a paper's committed launch package — assets/sites/<slug>/launch.php —
 * and fills the paper so it looks finished on day one: desks, settings, wire
 * sources, and launch stories with art.
 *
 * Run once per site, from the web root:
 *
 *     PP_SITE=edmonton-echo php tools/seed-launch.php
 *
 * Safe to re-run: stories are skipped when their slug already exists, desks
 * and sources are matched by slug/URL, and settings are only written where
 * the newsroom hasn't already saved a value.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';
require_once PP_ROOT . '/app/seed.php';   // pp_seed_last_id(), pp_site_default_settings()

$site = current_site();
$siteId = (int) $site['id'];
$slug = $site['slug'];
$file = PP_ROOT . '/assets/sites/' . $slug . '/launch.php';
if (!is_file($file)) {
    exit("No launch package for '{$slug}' — expected {$file}\n");
}
$pack = require $file;
$pdo = db();
$now = date('Y-m-d H:i:s');

echo "Launch package: {$slug} (site #{$siteId}, {$site['name']})\n";

/* --- Desks (shared network-wide; created only if missing) ----------------- */
$catId = [];
foreach ($pdo->query('SELECT id, slug FROM categories') as $row) {
    $catId[$row['slug']] = (int) $row['id'];
}
$maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort), 0) FROM categories')->fetchColumn();
$insCat = $pdo->prepare('INSERT INTO categories (name, slug, color, color_is_fill, description, sort) VALUES (?, ?, ?, ?, ?, ?)');
foreach ($pack['desks'] ?? [] as $d) {
    if (isset($catId[$d['slug']])) {
        continue;
    }
    $insCat->execute([$d['name'], $d['slug'], $d['color'], (int) ($d['color_is_fill'] ?? 0), $d['description'] ?? '', ++$maxSort]);
    $catId[$d['slug']] = pp_seed_last_id($pdo, 'categories');
    echo "  desk added: {$d['name']}\n";
}

/* --- Settings: write over untouched defaults, never over newsroom edits --- */
$defaults = pp_site_default_settings($site['name']);
foreach ($pack['settings'] ?? [] as $k => $v) {
    $current = setting($k);
    if ($current !== '' && $current !== ($defaults[$k] ?? '')) {
        continue;   // the newsroom changed this one — leave it alone
    }
    set_setting($k, $v);
    echo "  setting: {$k}\n";
}

/* --- Site row name: the auto-generated name yields to the launch identity -- */
$auto = ucwords(str_replace('-', ' ', $slug));
$packTitle = (string) ($pack['settings']['site_title'] ?? '');
if ($site['name'] === $auto && $packTitle !== '' && $packTitle !== $site['name']) {
    $pdo->prepare('UPDATE sites SET name = ? WHERE id = ?')->execute([$packTitle, $siteId]);
    echo "  site name: {$packTitle}\n";
}

/* --- Wire sources (shared; matched by URL) -------------------------------- */
$known = $pdo->query('SELECT url FROM sources')->fetchAll(PDO::FETCH_COLUMN);
$insSrc = $pdo->prepare('INSERT INTO sources (name, url, region, enabled) VALUES (?, ?, ?, 1)');
foreach ($pack['sources'] ?? [] as $s) {
    if (in_array($s[1], $known, true)) {
        continue;
    }
    $insSrc->execute($s);
    echo "  source added: {$s[0]}\n";
}

/* --- Stories -------------------------------------------------------------- */
$insPost = $pdo->prepare('INSERT INTO posts
    (title, slug, category_id, byline, dateline, lede, body, image, image_caption, image_credit,
     meta_description, source_url, source_name, post_type, region,
     status, is_featured, placement, views, published_at, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$insMap = $pdo->prepare('INSERT INTO post_sites (post_id, site_id) VALUES (?, ?)');
$selPost = $pdo->prepare('SELECT id FROM posts WHERE slug = ?');
$selTag = $pdo->prepare('SELECT id FROM tags WHERE slug = ?');
$insTag = $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');
$insPT  = $pdo->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)');

$added = 0;
foreach ($pack['stories'] ?? [] as $s) {
    // A pack may set an explicit slug. Aggregator packs need this: posts.slug
    // is unique across the whole network, and a wire item titled after the
    // sister-paper story it links to would otherwise collide with that story's
    // own slug on a shared database and be skipped as "already exists".
    $pslug = $s['slug'] ?? slugify($s['title']);
    $selPost->execute([$pslug]);
    if ($selPost->fetch()) {
        echo "  story exists, skipped: {$pslug}\n";
        continue;
    }
    if (!isset($catId[$s['desk']])) {
        echo "  story SKIPPED (unknown desk '{$s['desk']}'): {$pslug}\n";
        continue;
    }
    $insPost->execute([
        $s['title'], $pslug, $catId[$s['desk']], $s['byline'] ?? '', $s['dateline'] ?? '',
        $s['lede'], $s['body'] ?? '', $s['image'] ?? '', $s['image_caption'] ?? '', $s['image_credit'] ?? '',
        excerpt($s['lede'], 155), $s['source_url'] ?? '', $s['source_name'] ?? '',
        ($s['type'] ?? '') === 'link' ? 'link' : 'story', $s['region'] ?? '',
        'published', (int) ($s['featured'] ?? 0), $s['placement'] ?? '',
        (int) ($s['views'] ?? 0), $s['published'], $s['published'], $s['published'],
    ]);
    $postId = pp_seed_last_id($pdo, 'posts');
    $insMap->execute([$postId, $siteId]);
    foreach (array_filter(array_map('trim', explode(',', $s['tags'] ?? ''))) as $name) {
        $tslug = slugify($name);
        $selTag->execute([$tslug]);
        $tag = $selTag->fetch();
        $tagId = $tag ? (int) $tag['id'] : null;
        if ($tagId === null) {
            $insTag->execute([$name, $tslug]);
            $tagId = pp_seed_last_id($pdo, 'tags');
        }
        $insPT->execute([$postId, $tagId]);
    }
    $added++;
    echo "  story: {$s['title']}\n";
}

echo "Done — {$added} stories added. The front page is live.\n";
