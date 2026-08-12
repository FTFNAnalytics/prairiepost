<?php
/** The Prairie Dispatch — query layer. All access goes through prepared statements. */

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
            WHERE ' . pp_published_where() . "
            ORDER BY CASE WHEN p.placement = 'hero' THEN 0 ELSE 1 END, p.published_at DESC LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([now()]);
    return $stmt->fetch() ?: null;
}

/** The front-featured band: up to four stories an editor placed there. */
function front_featured_posts(array $excludeIds = [], int $limit = 4): array
{
    $params = [now()];
    $not = '';
    if ($excludeIds) {
        $not = ' AND p.id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
        $params = array_merge($params, array_map('intval', $excludeIds));
    }
    $sql = 'SELECT ' . PP_POST_COLS . ' FROM posts p' . pp_site_join() . PP_POST_JOINS . '
            WHERE ' . pp_published_where() . " AND p.placement = 'featured'" . $not . '
            ORDER BY p.published_at DESC LIMIT ' . (int) $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
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
            WHERE ' . pp_published_where() . ' AND p.category_id = ?' . $not . "
            ORDER BY CASE WHEN p.placement = 'desk_lead' THEN 0 ELSE 1 END, p.published_at DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset;
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

/* --- Regions (the aggregator's provinces) -------------------------------- */

function posts_in_region(string $region, int $limit, array $excludeIds = [], int $offset = 0): array
{
    $params = [now(), $region];
    $not = '';
    if ($excludeIds) {
        $not = ' AND p.id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
        $params = array_merge($params, array_map('intval', $excludeIds));
    }
    $sql = 'SELECT ' . PP_POST_COLS . ' FROM posts p' . pp_site_join() . PP_POST_JOINS . '
            WHERE ' . pp_published_where() . ' AND p.region = ?' . $not . '
            ORDER BY p.published_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function count_posts_in_region(string $region): int
{
    $stmt = db()->prepare('SELECT COUNT(*) AS n FROM posts p' . pp_site_join()
        . ' WHERE ' . pp_published_where() . ' AND p.region = ?');
    $stmt->execute([now(), $region]);
    return (int) $stmt->fetch()['n'];
}

/** The site's most-used tags on published stories — the Topics rail. */
function top_tags(int $limit = 8): array
{
    $sql = 'SELECT t.name, t.slug, COUNT(*) AS n FROM tags t
            JOIN post_tags pt ON pt.tag_id = t.id
            JOIN posts p ON p.id = pt.post_id' . pp_site_join() . '
            WHERE ' . pp_published_where() . '
            GROUP BY t.id, t.name, t.slug ORDER BY n DESC, t.name LIMIT ' . (int) $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute([now()]);
    return $stmt->fetchAll();
}

/** The outlets on this site's wire — distinct link-post credits with counts. */
function wire_newsrooms(int $limit = 8): array
{
    $sql = 'SELECT p.source_name AS name, COUNT(*) AS n FROM posts p' . pp_site_join() . '
            WHERE ' . pp_published_where() . " AND p.post_type = 'link' AND p.source_name != ''
            GROUP BY p.source_name ORDER BY n DESC, p.source_name LIMIT " . (int) $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute([now()]);
    return $stmt->fetchAll();
}

/* --- Sites & syndication ------------------------------------------------- */

function sites_all(): array
{
    return db()->query('SELECT * FROM sites ORDER BY name')->fetchAll();
}

/** The sites that are papers — everything except the control-room hub. */
function pp_paper_sites(): array
{
    $hub = slugify((string) pp_config('hub_slug', ''));
    return array_values(array_filter(sites_all(), fn ($s) => $s['slug'] !== $hub));
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

/* --- Trending ---------------------------------------------------------------- */

/** Most-read published stories of the past 48 hours; latest fill the gaps. */
function trending_posts(int $limit = 5): array
{
    $since = date('Y-m-d H:i:s', strtotime('-48 hours'));
    $sql = 'SELECT ' . PP_POST_COLS . ' FROM posts p' . pp_site_join() . PP_POST_JOINS . '
            WHERE ' . pp_published_where() . ' AND p.published_at >= ? AND p.views > 0
            ORDER BY p.views DESC, p.published_at DESC LIMIT ' . (int) $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute([now(), $since]);
    $posts = $stmt->fetchAll();
    if (count($posts) < $limit) {
        $posts = array_merge($posts, latest_posts($limit - count($posts), array_map('intval', array_column($posts, 'id'))));
    }
    return $posts;
}

/* --- Corrections ------------------------------------------------------------ */

/** Published stories carrying a correction, newest correction first. */
function corrected_posts(int $limit = 50): array
{
    $sql = 'SELECT ' . PP_POST_COLS . ' FROM posts p' . pp_site_join() . PP_POST_JOINS . '
            WHERE ' . pp_published_where() . " AND p.correction IS NOT NULL AND p.correction != ''
            ORDER BY p.corrected_at DESC LIMIT " . (int) $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute([now()]);
    return $stmt->fetchAll();
}

/* --- Subscribers & newsletters ---------------------------------------------- */

function subscriber_by_token(string $token): ?array
{
    if ($token === '') {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM subscribers WHERE token = ? AND site_id = ?');
    $stmt->execute([$token, current_site_id()]);
    return $stmt->fetch() ?: null;
}

function active_subscribers(): array
{
    $stmt = db()->prepare("SELECT * FROM subscribers WHERE site_id = ? AND status = 'active' ORDER BY id");
    $stmt->execute([current_site_id()]);
    return $stmt->fetchAll();
}

function newsletter_by_date(string $date): ?array
{
    $stmt = db()->prepare('SELECT * FROM newsletters WHERE site_id = ? AND edition_date = ?');
    $stmt->execute([current_site_id(), $date]);
    return $stmt->fetch() ?: null;
}

function newsletters_recent(int $limit = 30): array
{
    $stmt = db()->prepare('SELECT id, edition_date, subject, status, recipients, sent_at FROM newsletters
        WHERE site_id = ? ORDER BY edition_date DESC LIMIT ' . (int) $limit);
    $stmt->execute([current_site_id()]);
    return $stmt->fetchAll();
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

/**
 * Pick one live ad for a placement — round-robin by fewest impressions.
 * The impression itself is NOT counted here: rendered pages may be served
 * from the nginx microcache, where PHP never runs. The page footer prints
 * a beacon (`/ad?imp=…`, never cached) listing the ads it showed, and that
 * endpoint does the counting — every cached view still counts.
 */
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
    $GLOBALS['pp_ads_shown'][] = (int) $ad['id'];
    return $ad;
}

/** The ads this request rendered — the footer beacon reports them. */
function pp_ads_shown(): array
{
    return array_values(array_unique($GLOBALS['pp_ads_shown'] ?? []));
}

/** Count impressions reported by the footer beacon (site-scoped ids). */
function pp_ads_count_impressions(array $ids): void
{
    $ids = array_slice(array_values(array_unique(array_map('intval', $ids))), 0, 10);
    if (!$ids) {
        return;
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("UPDATE ads SET impressions = impressions + 1 WHERE site_id = ? AND id IN ($marks)");
    $stmt->execute([current_site_id(), ...$ids]);
}

/* --- Network campaigns (the hub's advertising) ---------------------------- */

/**
 * Campaigns with their fleet aggregated: papers carried, served, clicks,
 * and one exemplar ads row's slot/schedule/enabled state (the rows are
 * identical by construction; only site_id and the counters differ).
 */
function campaigns_with_stats(): array
{
    $sql = 'SELECT c.*, COUNT(a.id) AS papers, COALESCE(SUM(a.impressions), 0) AS impressions,
                   COALESCE(SUM(a.clicks), 0) AS clicks,
                   MAX(a.placement) AS placement, MAX(a.kind) AS kind,
                   MAX(a.start_at) AS start_at, MAX(a.end_at) AS end_at,
                   COALESCE(MAX(a.enabled), 0) AS any_enabled
            FROM campaigns c LEFT JOIN ads a ON a.campaign_id = c.id
            GROUP BY c.id, c.name, c.advertiser, c.notes, c.created_at
            ORDER BY c.created_at DESC';
    return db()->query($sql)->fetchAll();
}

function campaign_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM campaigns WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** A campaign's ads rows with their paper names, for the breakdown table. */
function campaign_ads(int $campaignId): array
{
    $stmt = db()->prepare('SELECT a.*, s.name AS site_name, s.slug AS site_slug
                           FROM ads a JOIN sites s ON s.id = a.site_id
                           WHERE a.campaign_id = ? ORDER BY s.name');
    $stmt->execute([$campaignId]);
    return $stmt->fetchAll();
}

/**
 * Fan a campaign out to its papers: one ads row per selected site, all
 * carrying the same creative and schedule. Existing rows are updated in
 * place (their counters survive), newly-ticked papers get a fresh row,
 * and deselected papers' rows are removed — their served/click counts
 * leave the campaign's totals with them. Serving never changes: each row
 * is an ordinary per-site ad that happens to carry the campaign's stamp.
 * Returns [added, updated, removed].
 */
function pp_sync_campaign_ads(int $campaignId, array $siteIds, array $fields): array
{
    $pdo = db();
    $existing = [];
    $stmt = $pdo->prepare('SELECT id, site_id FROM ads WHERE campaign_id = ?');
    $stmt->execute([$campaignId]);
    foreach ($stmt as $row) {
        $existing[(int) $row['site_id']] = (int) $row['id'];
    }
    $siteIds = array_values(array_unique(array_map('intval', $siteIds)));
    $added = $updated = $removed = 0;

    foreach ($siteIds as $siteId) {
        if (isset($existing[$siteId])) {
            $set = implode(', ', array_map(fn ($k) => "$k = ?", array_keys($fields)));
            $pdo->prepare("UPDATE ads SET $set WHERE id = ?")
                ->execute([...array_values($fields), $existing[$siteId]]);
            $updated++;
        } else {
            $ins = $fields + ['site_id' => $siteId, 'campaign_id' => $campaignId, 'created_at' => now()];
            $cols = implode(', ', array_keys($ins));
            $marks = implode(', ', array_fill(0, count($ins), '?'));
            $pdo->prepare("INSERT INTO ads ($cols) VALUES ($marks)")->execute(array_values($ins));
            $added++;
        }
    }
    foreach ($existing as $siteId => $adId) {
        if (!in_array($siteId, $siteIds, true)) {
            $pdo->prepare('DELETE FROM ads WHERE id = ?')->execute([$adId]);
            $removed++;
        }
    }
    return [$added, $updated, $removed];
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

/* --- The media monitoring desk ------------------------------------------ */

/** Jurisdiction vocabulary — the ingest contract validates against this. */
function pp_monitor_levels(): array
{
    return [
        'federal'    => 'Federal',
        'provincial' => 'Provincial',
        'municipal'  => 'Municipal',
        'agency'     => 'Agency',
    ];
}

/** Document-type vocabulary — small on purpose; 'other' catches the rest. */
function pp_monitor_doctypes(): array
{
    return [
        'release'          => 'Press release',
        'gazette'          => 'Gazette',
        'order-in-council' => 'Order in council',
        'hansard'          => 'Hansard',
        'bill'             => 'Bill',
        'tender'           => 'Tender',
        'agenda'           => 'Agenda',
        'minutes'          => 'Minutes',
        'decision'         => 'Decision',
        'report'           => 'Report',
        'other'            => 'Other',
    ];
}

/** Region key → label, merged from every site's regions setting. */
function pp_region_labels(): array
{
    $labels = [];
    foreach (db()->query("SELECT svalue FROM settings WHERE skey = 'regions'") as $row) {
        $decoded = json_decode((string) $row['svalue'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $k => $v) {
                $labels[$k] = $labels[$k] ?? (string) $v;
            }
        }
    }
    return $labels;
}

/**
 * Insert one monitoring item, de-duplicated on url_hash — the one write path
 * shared by the ingest API, the feed poller, and the capture form.
 * Returns 'added' or 'duplicate'.
 */
function pp_monitor_add_item(array $item): string
{
    $db = db();
    $hash = sha1((string) $item['url']);
    $seen = $db->prepare('SELECT 1 FROM monitor_items WHERE url_hash = ?');
    $seen->execute([$hash]);
    if ($seen->fetch()) {
        return 'duplicate';
    }
    try {
        $db->prepare('INSERT INTO monitor_items
                (feed_id, source_name, level, region, doc_type, title, url, url_hash,
                 summary, body_excerpt, published_at, fetched_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([
               $item['feed_id'] ?? null,
               mb_substr(trim((string) ($item['source'] ?? '')), 0, 160),
               $item['level'],
               mb_substr(slugify((string) ($item['region'] ?? '')), 0, 40),
               $item['doc_type'],
               mb_substr(trim((string) $item['title']), 0, 255),
               mb_substr(trim((string) $item['url']), 0, 600),
               $hash,
               mb_substr(trim((string) ($item['summary'] ?? '')), 0, 2000),
               mb_substr(trim((string) ($item['body_excerpt'] ?? '')), 0, 8000),
               $item['published_at'] ?? null,
               now(),
               'new',
           ]);
    } catch (PDOException $e) {
        // A concurrent insert of the same URL is a duplicate, not an error.
        if (str_contains(strtolower($e->getMessage()), 'unique')) {
            return 'duplicate';
        }
        throw $e;
    }
    return 'added';
}

/** Poll one monitoring feed into monitor_items. Returns [addedCount, errorOrNull]. */
function pp_monitor_poll_feed(array $feed): array
{
    [$xml, $err] = http_get($feed['url'], 20);
    $db = db();
    $stamp = $db->prepare('UPDATE monitor_feeds SET last_fetched_at = ?, last_status = ? WHERE id = ?');
    if ($xml === null) {
        $stamp->execute([now(), 'error: ' . mb_substr($err, 0, 240), (int) $feed['id']]);
        return [0, $err];
    }
    [$items, $perr] = parse_feed($xml);
    if ($perr !== null) {
        $stamp->execute([now(), 'error: ' . mb_substr($perr, 0, 240), (int) $feed['id']]);
        return [0, $perr];
    }
    $new = 0;
    foreach ($items as $item) {
        if (trim((string) ($item['title'] ?? '')) === '' || !preg_match('#^https?://#i', (string) ($item['url'] ?? ''))) {
            continue;
        }
        $result = pp_monitor_add_item([
            'feed_id'      => (int) $feed['id'],
            'source'       => $feed['name'],
            'level'        => $feed['level'],
            'region'       => $feed['region'],
            'doc_type'     => $feed['doc_type'],
            'title'        => $item['title'],
            'url'          => $item['url'],
            'summary'      => (string) ($item['summary'] ?? ''),
            'published_at' => !empty($item['published_at']) ? $item['published_at'] : null,
        ]);
        if ($result === 'added') {
            $new++;
        }
    }
    $stamp->execute([now(), 'ok: ' . $new . ' new', (int) $feed['id']]);
    return [$new, null];
}

/* --- Analytics & Search Console ------------------------------------------ */

/** Read another site's setting straight from the shared table. */
function pp_setting_for_site(int $siteId, string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT svalue FROM settings WHERE site_id = ? AND skey = ?');
    $stmt->execute([$siteId, $key]);
    $row = $stmt->fetch();
    return $row !== false ? (string) $row['svalue'] : $default;
}

/** A site's public domain (sites.domain), '' when unknown. */
function pp_site_domain(int $siteId): string
{
    $stmt = db()->prepare('SELECT domain FROM sites WHERE id = ?');
    $stmt->execute([$siteId]);
    return trim((string) ($stmt->fetchColumn() ?: ''));
}

/* --- Hardening: sign-in throttle, audit trail, IP allowlist -------------- */

/** The address nginx saw. We sit directly behind nginx, so no header trust. */
function pp_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** Record a sign-in attempt; the throttle window reads these. */
function pp_login_record(string $email, bool $succeeded): void
{
    try {
        db()->prepare('INSERT INTO login_attempts (email, ip, succeeded, created_at) VALUES (?, ?, ?, ?)')
            ->execute([mb_substr(mb_strtolower(trim($email)), 0, 191), mb_substr(pp_client_ip(), 0, 64), $succeeded ? 1 : 0, now()]);
    } catch (PDOException) {
        // A full disk or racing migration must never turn into a login fatal.
    }
}

/**
 * Too many recent failures for this email or this address? Sliding window:
 * 6 failed tries per account or 20 per address inside 15 minutes locks the
 * form (a "try later" message, no account state touched). Successes never
 * count against anyone; the window simply expires.
 */
function pp_login_blocked(string $email): bool
{
    $since = date('Y-m-d H:i:s', time() - 900);
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE succeeded = 0 AND email = ? AND created_at > ?');
        $stmt->execute([mb_substr(mb_strtolower(trim($email)), 0, 191), $since]);
        if ((int) $stmt->fetchColumn() >= 6) {
            return true;
        }
        $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE succeeded = 0 AND ip = ? AND created_at > ?');
        $stmt->execute([mb_substr(pp_client_ip(), 0, 64), $since]);
        return (int) $stmt->fetchColumn() >= 20;
    } catch (PDOException) {
        return false;
    }
}

/**
 * Append to the audit trail. Best-effort by design: the recorded action has
 * already happened, so a logging hiccup must never roll it back or 500 the
 * page. Pass $asUser when the session user isn't set yet (the sign-in itself).
 */
function pp_audit(string $action, string $target = '', string $detail = '', ?array $asUser = null): void
{
    try {
        $user = $asUser ?? (function_exists('current_user') ? current_user() : null);
        db()->prepare('INSERT INTO audit_log (site_id, user_id, user_name, action, target, detail, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                current_site_id(),
                (int) ($user['id'] ?? 0),
                mb_substr((string) ($user['name'] ?? ''), 0, 120),
                mb_substr($action, 0, 60),
                mb_substr($target, 0, 160),
                $detail,
                mb_substr(pp_client_ip(), 0, 64),
                now(),
            ]);
    } catch (Throwable) {
        // Auditing observes; it never interferes.
    }
}

/**
 * Whether the hub demands TOTP from its administrators. On by default —
 * the control room can edit every masthead — and the hub settings page
 * can switch it off ('0') deliberately.
 */
function pp_totp_required(): bool
{
    return pp_is_hub() && setting('require_totp', '1') !== '0';
}

/** Is this a plausible allowlist entry — an IP, or an IP with /bits? */
function pp_cidr_valid(string $entry): bool
{
    [$net, $bits] = array_pad(explode('/', trim($entry), 2), 2, null);
    $bin = @inet_pton((string) $net);
    if ($bin === false) {
        return false;
    }
    if ($bits === null || $bits === '') {
        return true;
    }
    return ctype_digit((string) $bits) && (int) $bits <= strlen($bin) * 8;
}

/** Does $ip fall inside $cidr ("203.0.113.7", "10.0.0.0/8", v6 alike)? */
function pp_ip_in_cidr(string $ip, string $cidr): bool
{
    [$net, $bits] = array_pad(explode('/', trim($cidr), 2), 2, null);
    $ipBin = @inet_pton($ip);
    $netBin = @inet_pton((string) $net);
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
        return false;
    }
    $max = strlen($netBin) * 8;
    $bits = $bits === null || $bits === '' ? $max : (int) $bits;
    if ($bits < 0 || $bits > $max) {
        return false;
    }
    $bytes = intdiv($bits, 8);
    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
        return false;
    }
    $rem = $bits % 8;
    if ($rem > 0) {
        $mask = (0xFF << (8 - $rem)) & 0xFF;
        if ((ord($ipBin[$bytes]) & $mask) !== (ord($netBin[$bytes]) & $mask)) {
            return false;
        }
    }
    return true;
}

/**
 * Hub admin IP allowlist. Empty setting = open (the default). Entries are
 * IPs or CIDR ranges separated by newlines, commas or spaces. Evaluated
 * only on the hub; a mistake locks out /admin there, never the papers —
 * and the papers' shared settings page can clear it again.
 */
function pp_ip_allowlisted(): bool
{
    $raw = trim(setting('admin_ip_allowlist', ''));
    if ($raw === '') {
        return true;
    }
    $ip = pp_client_ip();
    foreach (preg_split('/[\s,]+/', $raw) ?: [] as $entry) {
        if ($entry !== '' && pp_ip_in_cidr($ip, $entry)) {
            return true;
        }
    }
    return false;
}

/* --- Revision history: what was live, when --------------------------------- */

/**
 * Snapshot a story's current text into its revision history. Called after
 * every content-changing write (create, save, agent approval, restore), so
 * each row answers "what did the story say from this moment on". History is
 * capped per story; autosaves pass $minIntervalSec so a typing session
 * collapses to one snapshot per half hour instead of hundreds.
 */
function pp_post_snapshot(int $postId, string $reason, string $by = '', int $minIntervalSec = 0): void
{
    try {
        $stmt = db()->prepare('SELECT title, lede, body, meta_description, correction, image, image_caption FROM posts WHERE id = ?');
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        if (!$post) {
            return;
        }
        if ($minIntervalSec > 0) {
            $stmt = db()->prepare('SELECT created_at FROM post_revisions WHERE post_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$postId]);
            $last = (string) ($stmt->fetchColumn() ?: '');
            if ($last !== '' && strtotime($last) > time() - $minIntervalSec) {
                return;
            }
        }
        db()->prepare('INSERT INTO post_revisions (post_id, title, lede, body, meta_description, correction, image, image_caption, saved_by, reason, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $postId,
                (string) $post['title'],
                (string) ($post['lede'] ?? ''),
                (string) ($post['body'] ?? ''),
                (string) $post['meta_description'],
                (string) ($post['correction'] ?? ''),
                (string) $post['image'],
                (string) $post['image_caption'],
                mb_substr($by, 0, 120),
                mb_substr($reason, 0, 40),
                now(),
            ]);
        // Cap the history: find the oldest id still worth keeping, drop the rest.
        $stmt = db()->prepare('SELECT id FROM post_revisions WHERE post_id = ? ORDER BY id DESC LIMIT 1 OFFSET 39');
        $stmt->execute([$postId]);
        $cutoff = (int) ($stmt->fetchColumn() ?: 0);
        if ($cutoff > 0) {
            db()->prepare('DELETE FROM post_revisions WHERE post_id = ? AND id < ?')->execute([$postId, $cutoff]);
        }
    } catch (PDOException) {
        // History observes the save; it must never break it.
    }
}

function pp_post_revisions(int $postId, int $limit = 12): array
{
    $stmt = db()->prepare('SELECT id, saved_by, reason, created_at FROM post_revisions WHERE post_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit));
    $stmt->execute([$postId]);
    return $stmt->fetchAll();
}

function pp_revision_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM post_revisions WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/* --- Ops: the cron ledger and the watch snapshot ---------------------------- */

/** Record one cron run; the dashboard and the watch read these. */
function pp_ops_record(string $job, bool $ok, string $note, string $startedAt): void
{
    try {
        db()->prepare('INSERT INTO ops_runs (job, site_id, ok, note, started_at, finished_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([mb_substr($job, 0, 40), current_site_id(), $ok ? 1 : 0, mb_substr($note, 0, 500), $startedAt, now()]);
    } catch (PDOException) {
        // The ledger must never take the job down with it.
    }
}

/** The latest run per job, newest first. */
function pp_ops_latest(): array
{
    $latest = [];
    foreach (db()->query('SELECT * FROM ops_runs ORDER BY id DESC LIMIT 400') as $run) {
        $latest[$run['job']] = $latest[$run['job']] ?? $run;
    }
    return $latest;
}
