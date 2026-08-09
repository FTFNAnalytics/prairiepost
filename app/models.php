<?php
/** The Prairie Post — query layer. All access goes through prepared statements. */

/* --- Posts -------------------------------------------------------------- */

const PP_POST_COLS = "p.*, c.name AS category_name, c.slug AS category_slug,
    c.color AS category_color, c.color_is_fill AS category_color_is_fill,
    u.slug AS author_slug";

const PP_POST_JOINS = ' LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN users u ON u.id = p.author_id';

function pp_published_where(): string
{
    return "p.status = 'published' AND p.published_at <= ?";
}

/** Scope a public query to the current site's published mapping. */
function pp_site_join(): string
{
    return ' JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = ' . current_site_id();
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function featured_post(): ?array
{
    $sql = 'SELECT ' . PP_POST_COLS . ' FROM posts p' . pp_site_join() . PP_POST_JOINS . '
            WHERE ' . pp_published_where() . '
            ORDER BY p.is_featured DESC, p.published_at DESC LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute([now()]);
    return $stmt->fetch() ?: null;
}

function latest_posts(int $limit, array $excludeIds = [], int $offset = 0): array
{
    $params = [now()];
    $not = '';
    if ($excludeIds) {
        $not = ' AND p.id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
        $params = array_merge($params, array_map('intval', $excludeIds));
    }
    $sql = 'SELECT ' . PP_POST_COLS . ' FROM posts p' . pp_site_join() . PP_POST_JOINS . '
            WHERE ' . pp_published_where() . $not . '
            ORDER BY p.published_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function posts_in_category(int $categoryId, int $limit, array $excludeIds = [], int $offset = 0): array
{
    $params = [now(), $categoryId];
    $not = '';
    if ($excludeIds) {
        $not = ' AND p.id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
        $params = array_merge($params, array_map('intval', $excludeIds));
    }
    $sql = 'SELECT ' . PP_POST_COLS . ' FROM posts p' . pp_site_join() . PP_POST_JOINS . '
            WHERE ' . pp_published_where() . ' AND p.category_id = ?' . $not . '
            ORDER BY p.published_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function count_posts_in_category(int $categoryId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) AS n FROM posts p' . pp_site_join()
        . ' WHERE ' . pp_published_where() . ' AND p.category_id = ?');
    $stmt->execute([now(), $categoryId]);
    return (int) $stmt->fetch()['n'];
}

function post_by_slug(string $slug): ?array
{
    $sql = 'SELECT ' . PP_POST_COLS . ' FROM posts p' . pp_site_join() . PP_POST_JOINS . '
            WHERE p.slug = ? AND ' . pp_published_where() . ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute([$slug, now()]);
    return $stmt->fetch() ?: null;
}

function search_posts(string $q, int $limit, int $offset = 0): array
{
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    $op = pp_like();
    $sql = 'SELECT ' . PP_POST_COLS . ' FROM posts p' . pp_site_join() . PP_POST_JOINS . '
            WHERE ' . pp_published_where() . " AND (p.title $op ? OR p.lede $op ? OR p.body $op ? OR p.dateline $op ?)
            ORDER BY p.published_at DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset;
    $stmt = db()->prepare($sql);
    $stmt->execute([now(), $like, $like, $like, $like]);
    return $stmt->fetchAll();
}

function count_search_posts(string $q): int
{
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    $op = pp_like();
    $stmt = db()->prepare('SELECT COUNT(*) AS n FROM posts p' . pp_site_join()
        . ' WHERE ' . pp_published_where() . " AND (p.title $op ? OR p.lede $op ? OR p.body $op ? OR p.dateline $op ?)");
    $stmt->execute([now(), $like, $like, $like, $like]);
    return (int) $stmt->fetch()['n'];
}

function related_posts(?int $categoryId, int $excludeId, int $limit = 3): array
{
    if ($categoryId) {
        $posts = posts_in_category($categoryId, $limit, [$excludeId]);
        if (count($posts) >= $limit) {
            return $posts;
        }
        $more = latest_posts($limit - count($posts), array_merge([$excludeId], array_column($posts, 'id')));
        return array_merge($posts, $more);
    }
    return latest_posts($limit, [$excludeId]);
}

function posts_by_author(int $authorId, int $limit, int $offset = 0): array
{
    $sql = 'SELECT ' . PP_POST_COLS . ' FROM posts p' . pp_site_join() . PP_POST_JOINS . '
            WHERE ' . pp_published_where() . ' AND p.author_id = ?
            ORDER BY p.published_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    $stmt = db()->prepare($sql);
    $stmt->execute([now(), $authorId]);
    return $stmt->fetchAll();
}

function count_posts_by_author(int $authorId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) AS n FROM posts p' . pp_site_join()
        . ' WHERE ' . pp_published_where() . ' AND p.author_id = ?');
    $stmt->execute([now(), $authorId]);
    return (int) $stmt->fetch()['n'];
}

/** Unique slug for a new/updated post. */
function unique_post_slug(string $title, int $ignoreId = 0): string
{
    $base = slugify($title);
    $slug = $base;
    $n = 2;
    while (true) {
        $stmt = db()->prepare('SELECT id FROM posts WHERE slug = ? AND id != ?');
        $stmt->execute([$slug, $ignoreId]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $n++;
    }
}

/* --- Sites & syndication ------------------------------------------------- */

function sites_all(): array
{
    return db()->query('SELECT * FROM sites ORDER BY name')->fetchAll();
}

function site_ids_for_post(int $postId): array
{
    $stmt = db()->prepare('SELECT site_id FROM post_sites WHERE post_id = ?');
    $stmt->execute([$postId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'site_id'));
}

function set_post_sites(int $postId, array $siteIds): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM post_sites WHERE post_id = ?')->execute([$postId]);
    $ins = $pdo->prepare('INSERT INTO post_sites (post_id, site_id) VALUES (?, ?)');
    foreach (array_unique(array_map('intval', $siteIds)) as $siteId) {
        if ($siteId > 0) {
            $ins->execute([$postId, $siteId]);
        }
    }
}

/* --- Categories ---------------------------------------------------------- */

function categories_all(): array
{
    return db()->query('SELECT * FROM categories ORDER BY sort, name')->fetchAll();
}

function category_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM categories WHERE slug = ?');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/* --- Tags ---------------------------------------------------------------- */

function tags_for_post(int $postId): array
{
    $stmt = db()->prepare('SELECT t.* FROM tags t JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name');
    $stmt->execute([$postId]);
    return $stmt->fetchAll();
}

function set_post_tags(int $postId, string $commaList): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM post_tags WHERE post_id = ?')->execute([$postId]);
    foreach (array_filter(array_map('trim', explode(',', $commaList))) as $name) {
        $slug = slugify($name);
        $stmt = $pdo->prepare('SELECT id FROM tags WHERE slug = ?');
        $stmt->execute([$slug]);
        $tag = $stmt->fetch();
        if (!$tag) {
            $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)')->execute([$name, $slug]);
            $tagId = pp_last_id('tags');
        } else {
            $tagId = (int) $tag['id'];
        }
        $pdo->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)')->execute([$postId, $tagId]);
    }
}

/* --- Users ---------------------------------------------------------------- */

function user_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([mb_strtolower(trim($email))]);
    return $stmt->fetch() ?: null;
}

function user_by_slug(string $slug): ?array
{
    $stmt = db()->prepare("SELECT * FROM users WHERE slug = ? AND slug != ''");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function users_count(): int
{
    return (int) db()->query('SELECT COUNT(*) AS n FROM users')->fetch()['n'];
}

/** Unique profile slug for an account. */
function unique_user_slug(string $name, int $ignoreId = 0): string
{
    $base = slugify($name);
    $slug = $base;
    $n = 2;
    while (true) {
        $stmt = db()->prepare('SELECT id FROM users WHERE slug = ? AND id != ?');
        $stmt->execute([$slug, $ignoreId]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $n++;
    }
}

/* --- News pull ------------------------------------------------------------ */

function news_items_for_region(string $region, int $limit = 40, bool $includeUsed = true): array
{
    $sql = 'SELECT n.*, s.name AS source_name FROM news_items n
            JOIN sources s ON s.id = n.source_id
            WHERE n.region = ?' . ($includeUsed ? '' : ' AND n.used = 0') . '
            ORDER BY COALESCE(n.published_at, n.fetched_at) DESC LIMIT ' . (int) $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute([$region]);
    return $stmt->fetchAll();
}

function sources_all(): array
{
    return db()->query('SELECT * FROM sources ORDER BY region, name')->fetchAll();
}

/* --- Advertising ----------------------------------------------------------- */

/** All ads for this site, newest first. */
function ads_all(): array
{
    $stmt = db()->prepare('SELECT * FROM ads WHERE site_id = ? ORDER BY placement, name');
    $stmt->execute([current_site_id()]);
    return $stmt->fetchAll();
}

function ad_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM ads WHERE id = ? AND site_id = ?');
    $stmt->execute([$id, current_site_id()]);
    return $stmt->fetch() ?: null;
}

/** Whether an ad is inside its schedule window right now. */
function ad_is_live(array $ad): bool
{
    if (!$ad['enabled']) {
        return false;
    }
    $now = now();
    if (!empty($ad['start_at']) && $ad['start_at'] > $now) {
        return false;
    }
    if (!empty($ad['end_at']) && $ad['end_at'] < $now) {
        return false;
    }
    return true;
}

/** Pick one live ad for a placement — round-robin by fewest impressions. */
function ad_for_placement(string $placement): ?array
{
    $stmt = db()->prepare('SELECT * FROM ads WHERE site_id = ? AND placement = ? AND enabled = 1');
    $stmt->execute([current_site_id(), $placement]);
    $live = array_values(array_filter($stmt->fetchAll(), 'ad_is_live'));
    if (!$live) {
        return null;
    }
    usort($live, fn ($a, $b) => $a['impressions'] <=> $b['impressions']);
    $ad = $live[0];
    db()->prepare('UPDATE ads SET impressions = impressions + 1 WHERE id = ?')->execute([$ad['id']]);
    return $ad;
}

/* --- Stats for the dashboard ---------------------------------------------- */

function pp_counts(): array
{
    $db = db();
    $sub = $db->prepare('SELECT COUNT(*) AS n FROM subscribers WHERE site_id = ?');
    $sub->execute([current_site_id()]);
    return [
        'published'   => (int) $db->query("SELECT COUNT(*) AS n FROM posts WHERE status = 'published'")->fetch()['n'],
        'drafts'      => (int) $db->query("SELECT COUNT(*) AS n FROM posts WHERE status = 'draft'")->fetch()['n'],
        'in_review'   => (int) $db->query("SELECT COUNT(*) AS n FROM posts WHERE status = 'in_review'")->fetch()['n'],
        'scheduled'   => (int) $db->query("SELECT COUNT(*) AS n FROM posts WHERE status = 'scheduled'")->fetch()['n'],
        'subscribers' => (int) $sub->fetch()['n'],
        'sources'     => (int) $db->query('SELECT COUNT(*) AS n FROM sources WHERE enabled = 1')->fetch()['n'],
    ];
}

/** Stories waiting for an editor, oldest submission first. */
function review_queue(int $limit = 20): array
{
    $sql = "SELECT p.*, c.name AS category_name, u.name AS author_name
            FROM posts p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN users u ON u.id = p.author_id
            WHERE p.status = 'in_review'
            ORDER BY p.updated_at ASC LIMIT " . (int) $limit;
    return db()->query($sql)->fetchAll();
}
