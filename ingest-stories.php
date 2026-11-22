<?php
/**
 * GET /api/ingest/stories — read a paper's stories: a list, or one story
 * in full.
 *
 * Owner-ordered (Nov 2026): an agent must be able to see every story on
 * its papers, published or not. It is the read half of the update lane:
 * to change a live article safely an agent reads it here, edits, and
 * sends the `version` back to /api/ingest/update as `if_version`.
 *
 * Permission is its own grant, set at minting
 * (ingest_agents.read_scope; make-agent --read, or the hub key form):
 *
 *   ''           none — refused outright (the default)
 *   'published'  the paper's live stories
 *   'all'        every story in the editorial queue too — drafts, review
 *                and scheduled, whoever made them, editors included
 *
 * Scope is the key's papers, the paper THIS hostname serves, and the
 * key's desks. An explicit request outside it — another paper, another
 * hostname, a desk the key does not hold, a status the grant does not
 * cover — is the same three-word 403 the other ingest reads give. A story
 * the key may not see answers 404, exactly like one that does not exist.
 *
 * What it returns is the story as a reader or an editor sees it, and
 * nothing about the people in the newsroom: no author accounts, no
 * editor names, no review notes, no internal ids or draft slugs. Stories
 * are named by the same opaque `ref` the lookup hands out.
 *
 * This endpoint WRITES NOTHING — not an audit row, not last_used_at.
 *
 * Request:  Authorization: Bearer <token>
 *   List:   GET /api/ingest/stories?site=<slug>[&desk=<slug>]
 *               [&status=published|draft|in_review|scheduled|unpublished|all]
 *               [&updated_since=<YYYY-MM-DD HH:MM:SS>][&limit=1..100][&cursor=<next>]
 *   One:    GET /api/ingest/stories?site=<slug>&ref=<ar_…> | &url=<public url>
 * Response: 200 {ok, stories: [{ref, status, desk, title, url?,
 *                               published_at, updated_at, image, version}],
 *                next?}
 *           200 {ok, story: {ref, status, desk, title, lede, body, byline,
 *                            dateline, image, image_caption, image_credit,
 *                            correction, tags, sources, source_story_id?,
 *                            url?, published_at, updated_at, version}}
 *           401/403/404/422 {ok: false, error, ...}
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/ingest.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    pp_ingest_respond(405, ['ok' => false, 'error' => 'GET, with the bearer token and ?site=']);
}

/* --- Authentication: token -> agent, fail closed (app/ingest.php) --------- */
$agent = pp_ingest_authenticate();

/* --- Capability: after authentication, before any read ------------------- */
if ($agent['read'] === '') {
    pp_ingest_respond(403, [
        'ok' => false,
        'error' => 'this token has no read permission',
        'reason' => 'story reading is granted at minting (read_scope published|all); without it this surface answers nothing',
    ]);
}
$granted = $agent['read'] === 'all' ? ['draft', 'in_review', 'scheduled', 'published'] : ['published'];

/* --- Scope: one paper, on its own hostname; the key's desks --------------- */
$outOfScope = static fn (): never => pp_ingest_respond(403, ['ok' => false, 'error' => 'out of scope']);
$q = static fn (string $k): string => trim((string) ($_GET[$k] ?? ''));

$siteSlug = slugify($q('site'));
if ($siteSlug === '' || !in_array($siteSlug, $agent['sites'], true)) {
    $outOfScope();
}
$host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
$stmt = db()->prepare('SELECT site_slug FROM domains WHERE hostname = ?');
$stmt->execute([$host]);
$served = $stmt->fetch();
if (!$served || (string) $served['site_slug'] !== $siteSlug) {
    $outOfScope();
}
$siteRow = db()->prepare('SELECT id FROM sites WHERE slug = ?');
$siteRow->execute([$siteSlug]);
$site = $siteRow->fetch();
if (!$site) {
    $outOfScope();
}
$siteId = (int) $site['id'];

// The desks this request may read: the one asked for (when the key holds
// it), else every desk the key holds — and every desk for an unbound key.
$deskIds = null;   // null = any desk
$deskParam = $q('desk');
if ($deskParam !== '') {
    $deskSlug = slugify($deskParam);
    if ($agent['desks'] && !in_array($deskSlug, $agent['desks'], true)) {
        $outOfScope();
    }
    $s = db()->prepare('SELECT id FROM categories WHERE slug = ?');
    $s->execute([$deskSlug]);
    $id = $s->fetchColumn();
    if ($id === false) {
        pp_ingest_respond(422, ['ok' => false, 'error' => "unknown desk '{$deskSlug}'"]);
    }
    $deskIds = [(int) $id];
} elseif ($agent['desks']) {
    $marks = implode(',', array_fill(0, count($agent['desks']), '?'));
    $s = db()->prepare("SELECT id FROM categories WHERE slug IN ($marks)");
    $s->execute($agent['desks']);
    $deskIds = array_map('intval', array_column($s->fetchAll(), 'id'));
    if (!$deskIds) {
        $deskIds = [0];   // the key's desks no longer exist: nothing to read
    }
}

$refKey = pp_ingest_ref_key();
if ($refKey === '') {
    pp_ingest_respond(500, ['ok' => false, 'error' => 'reading is not provisioned — run tools/migrate.php --apply']);
}

/** The SQL that limits rows to this site, the readable desks and $statuses. */
$scopeSql = static function (array $statuses) use ($siteId, $deskIds): array {
    $sql = ' FROM posts p JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = ?
             LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.post_type = \'story\'';
    $args = [$siteId];
    if ($deskIds !== null) {
        $sql .= ' AND p.category_id IN (' . implode(',', array_fill(0, count($deskIds), '?')) . ')';
        $args = [...$args, ...$deskIds];
    }
    $sql .= ' AND p.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
    return [$sql, [...$args, ...$statuses]];
};
$cols = 'p.id, p.slug, p.status, p.title, p.lede, p.body, p.byline, p.dateline, p.image, p.image_caption,
         p.image_credit, p.correction, p.published_at, p.updated_at, c.slug AS desk';
$publicUrl = static fn (array $p): ?string => $p['status'] === 'published' ? pp_story_public_url($siteSlug, (string) $p['slug']) : null;

/* --- One story, in full ---------------------------------------------------- */
$ref = $q('ref');
$url = $q('url');
if ($ref !== '' || $url !== '') {
    if ($ref !== '' && $url !== '') {
        pp_ingest_respond(422, ['ok' => false, 'error' => 'send one of ref or url, not both']);
    }
    [$from, $args] = $scopeSql($granted);
    $post = false;
    if ($url !== '') {
        $u = parse_url($url);
        if (!is_array($u) || empty($u['host']) || !preg_match('#^/story/([a-z0-9][a-z0-9-]*)/?$#', (string) ($u['path'] ?? ''), $m)) {
            pp_ingest_respond(422, ['ok' => false, 'error' => 'url must be a public story address: https://<this paper>/story/<slug>']);
        }
        if (strtolower((string) $u['host']) !== $host) {
            $outOfScope();
        }
        $s = db()->prepare("SELECT $cols $from AND p.slug = ?");
        $s->execute([...$args, $m[1]]);
        $post = $s->fetch();
        $s->closeCursor();
    } else {
        if (!preg_match('/^ar_[0-9a-f]{24}$/', $ref)) {
            pp_ingest_respond(422, ['ok' => false, 'error' => 'ref must be an article reference (ar_ + 24 hex)']);
        }
        $s = db()->prepare("SELECT p.id $from");
        $s->execute($args);
        foreach ($s->fetchAll() as $row) {
            if (hash_equals(pp_ingest_article_ref($siteId, (int) $row['id'], $refKey), $ref)) {
                $one = db()->prepare("SELECT $cols FROM posts p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?");
                $one->execute([(int) $row['id']]);
                $post = $one->fetch();
                $one->closeCursor();
                break;
            }
        }
    }
    // Not there, or not this key's to see: one answer for both.
    if (!$post) {
        pp_ingest_respond(404, ['ok' => false, 'error' => 'no story with that reference that this key may read']);
    }
    $id = (int) $post['id'];
    $t = db()->prepare('SELECT t.name FROM post_tags pt JOIN tags t ON t.id = pt.tag_id WHERE pt.post_id = ? ORDER BY t.name');
    $t->execute([$id]);
    $tags = array_column($t->fetchAll(), 'name');
    $src = db()->prepare('SELECT url FROM story_sources WHERE post_id = ? ORDER BY id');
    $src->execute([$id]);
    $sources = array_column($src->fetchAll(), 'url');
    $r = db()->prepare('SELECT source_story_id FROM ingest_receipts WHERE site_id = ? AND post_id = ?');
    $r->execute([$siteId, $id]);
    $ssid = $r->fetchColumn();

    $story = [
        'ref' => pp_ingest_article_ref($siteId, $id, $refKey),
        'status' => (string) $post['status'],
        'desk' => (string) ($post['desk'] ?? ''),
        'title' => (string) $post['title'],
        'lede' => (string) ($post['lede'] ?? ''),
        'body' => (string) ($post['body'] ?? ''),
        'byline' => (string) $post['byline'],
        'dateline' => (string) $post['dateline'],
        'image' => (string) $post['image'],
        'image_caption' => (string) $post['image_caption'],
        'image_credit' => (string) ($post['image_credit'] ?? ''),
        'correction' => (string) ($post['correction'] ?? ''),
        'tags' => $tags,
        'sources' => $sources,
    ];
    if ($ssid !== false) {
        $story['source_story_id'] = (string) $ssid;
    }
    if (($u = $publicUrl($post)) !== null) {
        $story['url'] = $u;
    }
    $story['published_at'] = $post['published_at'] !== null ? (string) $post['published_at'] : null;
    $story['updated_at'] = (string) $post['updated_at'];
    $story['version'] = pp_ingest_story_version($post);
    pp_ingest_respond(200, ['ok' => true, 'story' => $story]);
}

/* --- The list --------------------------------------------------------------- */
$filters = [
    'all' => $granted,
    'published' => ['published'],
    'draft' => ['draft'],
    'in_review' => ['in_review'],
    'scheduled' => ['scheduled'],
    'unpublished' => ['draft', 'in_review', 'scheduled'],
];
$statusParam = $q('status') !== '' ? $q('status') : 'all';
if (!isset($filters[$statusParam])) {
    pp_ingest_respond(422, ['ok' => false, 'error' => 'status must be one of: ' . implode(', ', array_keys($filters))]);
}
$statuses = $filters[$statusParam];
if (array_diff($statuses, $granted)) {
    $outOfScope();   // a status this key's grant does not cover
}

$limit = $q('limit') !== '' ? (int) $q('limit') : 50;
if ($limit < 1 || $limit > 100) {
    pp_ingest_respond(422, ['ok' => false, 'error' => 'limit must be 1 to 100']);
}
[$from, $args] = $scopeSql($statuses);
$cursor = $q('cursor');
if ($cursor !== '') {
    if (!preg_match('/^c_(\d{1,12})$/', $cursor, $cm)) {
        pp_ingest_respond(422, ['ok' => false, 'error' => 'cursor must be the `next` value from a previous page']);
    }
    $from .= ' AND p.id < ?';
    $args[] = (int) $cm[1];
}
$since = $q('updated_since');
if ($since !== '') {
    $ts = strtotime($since);
    if ($ts === false) {
        pp_ingest_respond(422, ['ok' => false, 'error' => 'updated_since must be a date-time, e.g. 2026-11-22 12:00:00']);
    }
    $from .= ' AND p.updated_at >= ?';
    $args[] = date('Y-m-d H:i:s', $ts);
}
$s = db()->prepare("SELECT $cols $from ORDER BY p.id DESC LIMIT " . ($limit + 1));
$s->execute($args);
$rows = $s->fetchAll();
$s->closeCursor();

$more = count($rows) > $limit;
$rows = array_slice($rows, 0, $limit);
$stories = [];
foreach ($rows as $p) {
    $item = [
        'ref' => pp_ingest_article_ref($siteId, (int) $p['id'], $refKey),
        'status' => (string) $p['status'],
        'desk' => (string) ($p['desk'] ?? ''),
        'title' => (string) $p['title'],
    ];
    if (($u = $publicUrl($p)) !== null) {
        $item['url'] = $u;
    }
    $item['published_at'] = $p['published_at'] !== null ? (string) $p['published_at'] : null;
    $item['updated_at'] = (string) $p['updated_at'];
    $item['image'] = (string) $p['image'];
    $item['version'] = pp_ingest_story_version($p);
    $stories[] = $item;
}
$out = ['ok' => true, 'stories' => $stories];
if ($more && $rows) {
    $out['next'] = 'c_' . (int) end($rows)['id'];
}
pp_ingest_respond(200, $out);
