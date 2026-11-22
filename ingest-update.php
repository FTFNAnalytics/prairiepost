<?php
/**
 * POST /api/ingest/update — change the headline, lede, body or featured
 * image of a story that is already PUBLISHED.
 *
 * This is the one exception, ordered by the owner in November 2026, to
 * the Phase 1 rule that a published story changes only at an editor's
 * desk. It exists because a live article sometimes needs its picture
 * swapped or its copy updated by the agent that filed it, without anyone
 * signing in. The exception is kept narrow on purpose:
 *
 *   - Only a key minted with the update_published grant may call it
 *     (tools/make-agent.php --update-published, or the hub key form).
 *     Default off; refused outright, before anything is read.
 *   - Only PUBLISHED stories. Drafts, review and scheduled stories stay
 *     the newsroom's (409).
 *   - Only these fields: title, lede, body, image, image_caption,
 *     image_credit. Never the slug (the URL is stable), status, dates,
 *     byline, desk, papers, tags, meta description or correction note.
 *   - Scope exactly as /api/ingest/lookup: the key's papers, the paper
 *     this hostname serves, the key's desks — and the story must sit on
 *     the requested desk. Every scope failure is the same three-word 403.
 *     A story also carried by a paper the key does not cover is refused
 *     (409): changing it would change another paper's page.
 *   - The body goes through sanitize_html() like every editor save; title
 *     and lede are plain text.
 *   - Revision history: the state before the update is snapshotted when
 *     history does not already hold it, and the state after always is, so
 *     an editor can restore any agent change from the story's history
 *     page — image credit included (schema 24).
 *   - The write is a compare-and-swap on what this request read
 *     (updated_at and every changed field, status still 'published'): if
 *     an editor saves or unpublishes the story mid-request, nothing is
 *     written and the answer is 409. `if_version` lets an agent refuse to
 *     overwrite anything changed since ITS last look.
 *   - Every change is audited against the paper, naming the key.
 *
 * Request:  Authorization: Bearer <token>
 *           JSON body: {site, desk, ref | url,
 *                       title?, lede?, body?,
 *                       image?, image_caption?, image_credit?,
 *                       if_version?}
 *           `ref` is the article reference /api/ingest/lookup returns;
 *           `url` is the story's public address on this paper. Send one.
 *           Send only the fields to change. `image` is an /uploads/ path
 *           returned by /api/ingest-media; a NEW image replaces the
 *           caption and credit with what this request sends (empty when
 *           omitted) — the old ones describe a different picture.
 * Response: 200 {ok, ref, status: "published", url, updated: [...],
 *                version, previous_image?}
 *           200 {ok, unchanged: true, ref, status, url, version}
 *           401/403/404/409/422/429 {ok: false, error, ...}
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/ingest.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    pp_ingest_respond(405, ['ok' => false, 'error' => 'POST one update per call']);
}

/* --- Authentication: token -> agent, fail closed (app/ingest.php) --------- */
$agent = pp_ingest_authenticate();

/* --- Capability: after authentication, before any read ------------------- */
if (!$agent['may_update_published']) {
    pp_ingest_respond(403, [
        'ok' => false,
        'error' => 'this token has no update permission',
        'reason' => 'update_published is granted at minting (--update-published); without it this surface changes nothing',
    ]);
}

/* --- Payload -------------------------------------------------------------- */
$raw = file_get_contents('php://input', false, null, 0, 512 * 1024);
$in = json_decode((string) $raw, true);
if (!is_array($in)) {
    pp_ingest_respond(400, ['ok' => false, 'error' => 'body must be a JSON object']);
}

/* --- Scope: one paper, one desk, on that paper's own hostname ------------- */
$outOfScope = static fn (): never => pp_ingest_respond(403, ['ok' => false, 'error' => 'out of scope']);

$siteSlug = slugify((string) ($in['site'] ?? ''));
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

$deskSlug = slugify((string) ($in['desk'] ?? ''));
if ($deskSlug === '' || ($agent['desks'] && !in_array($deskSlug, $agent['desks'], true))) {
    $outOfScope();
}
$deskRow = db()->prepare('SELECT id FROM categories WHERE slug = ?');
$deskRow->execute([$deskSlug]);
$desk = $deskRow->fetch();
if (!$desk) {
    pp_ingest_respond(422, ['ok' => false, 'error' => "unknown desk '{$deskSlug}'"]);
}
$deskId = (int) $desk['id'];

/* --- Which story: the lookup's ref, or the public URL --------------------- */
$ref = trim((string) ($in['ref'] ?? ''));
$url = trim((string) ($in['url'] ?? ''));
if (($ref === '') === ($url === '')) {
    pp_ingest_respond(422, ['ok' => false,
        'error' => 'send exactly one of ref (from /api/ingest/lookup) or url (the story\'s public address on this paper)']);
}
$refKey = pp_ingest_ref_key();
if ($refKey === '') {
    pp_ingest_respond(500, ['ok' => false, 'error' => 'update is not provisioned — run tools/migrate.php --apply']);
}
$cols = 'p.id, p.slug, p.status, p.title, p.lede, p.body, p.image, p.image_caption, p.image_credit, p.updated_at';
$postId = 0;
if ($url !== '') {
    $u = parse_url($url);
    if (!is_array($u) || empty($u['host']) || !preg_match('#^/story/([a-z0-9][a-z0-9-]*)/?$#', (string) ($u['path'] ?? ''), $m)) {
        pp_ingest_respond(422, ['ok' => false, 'error' => 'url must be a public story address: https://<this paper>/story/<slug>']);
    }
    // A story address on another paper's hostname is another paper's story.
    if (strtolower((string) $u['host']) !== $host) {
        $outOfScope();
    }
    $s = db()->prepare("SELECT p.id FROM posts p
        JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = ?
        WHERE p.slug = ? AND p.category_id = ?");
    $s->execute([$siteId, $m[1], $deskId]);
    $postId = (int) ($s->fetchColumn() ?: 0);
    $s->closeCursor();
} else {
    if (!preg_match('/^ar_[0-9a-f]{24}$/', $ref)) {
        pp_ingest_respond(422, ['ok' => false, 'error' => 'ref must be an article reference from /api/ingest/lookup (ar_ + 24 hex)']);
    }
    // A reference is resolvable only here: recompute it over this paper's
    // stories on this desk until one answers to it.
    $s = db()->prepare('SELECT p.id FROM posts p
        JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = ?
        WHERE p.category_id = ?');
    $s->execute([$siteId, $deskId]);
    foreach ($s->fetchAll() as $row) {
        if (hash_equals(pp_ingest_article_ref($siteId, (int) $row['id'], $refKey), $ref)) {
            $postId = (int) $row['id'];
            break;
        }
    }
}
if ($postId === 0) {
    pp_ingest_respond(404, ['ok' => false, 'error' => 'no story with that reference on this paper and desk']);
}
$s = db()->prepare("SELECT $cols FROM posts p WHERE p.id = ?");
$s->execute([$postId]);
$post = $s->fetch();
$s->closeCursor();
if (!$post) {
    pp_ingest_respond(404, ['ok' => false, 'error' => 'no story with that reference on this paper and desk']);
}
$articleRef = pp_ingest_article_ref($siteId, $postId, $refKey);
if ($post['status'] !== 'published') {
    pp_ingest_respond(409, ['ok' => false,
        'error' => 'that story is not published — drafts, review and scheduled stories change at the newsroom\'s desk']);
}
// A story carried by a paper outside this key's papers is that paper's
// page too: changing it is an editor's call.
$marks = implode(',', array_fill(0, count($agent['sites']), '?'));
$s = db()->prepare("SELECT COUNT(*) FROM post_sites ps JOIN sites st ON st.id = ps.site_id
    WHERE ps.post_id = ? AND st.slug NOT IN ($marks)");
$s->execute([$postId, ...$agent['sites']]);
if ((int) $s->fetchColumn() > 0) {
    pp_ingest_respond(409, ['ok' => false,
        'error' => 'this story is also carried by a paper this key does not cover — changing it there is an editor\'s call']);
}

/* --- What to change ------------------------------------------------------- */
$changes = [];
if (array_key_exists('title', $in)) {
    $t = trim(strip_tags((string) $in['title']));
    if ($t === '' || mb_strlen($t) > 255) {
        pp_ingest_respond(422, ['ok' => false, 'error' => 'title cannot be empty, at most 255 characters']);
    }
    $changes['title'] = $t;
}
if (array_key_exists('lede', $in)) {
    $l = trim(strip_tags((string) $in['lede']));
    if ($l === '' || mb_strlen($l) > 1000) {
        pp_ingest_respond(422, ['ok' => false, 'error' => 'lede cannot be empty, at most 1000 characters']);
    }
    $changes['lede'] = $l;
}
if (array_key_exists('body', $in)) {
    $b = trim(sanitize_html((string) $in['body']));
    if ($b === '' || strlen($b) > 200000) {
        pp_ingest_respond(422, ['ok' => false, 'error' => 'body cannot be empty, at most 200 KB after sanitization']);
    }
    $changes['body'] = $b;
}
if (array_key_exists('image', $in)) {
    $img = trim((string) $in['image']);
    if (!preg_match('#^/uploads/\d{4}/\d{2}/[a-z0-9][a-z0-9-]*\.(jpg|jpeg|png|webp|gif)$#', $img) || !is_file(PP_ROOT . $img)) {
        pp_ingest_respond(422, ['ok' => false, 'error' => 'image must be an /uploads/ path returned by /api/ingest-media']);
    }
    $changes['image'] = $img;
}
if (array_key_exists('image_caption', $in)) {
    $changes['image_caption'] = mb_substr(trim(strip_tags((string) $in['image_caption'])), 0, 255);
}
if (array_key_exists('image_credit', $in)) {
    $changes['image_credit'] = mb_substr(trim(strip_tags((string) $in['image_credit'])), 0, 120);
}
if (!$changes) {
    pp_ingest_respond(422, ['ok' => false,
        'error' => 'nothing to update: send at least one of title, lede, body, image, image_caption, image_credit']);
}
$current = static fn (string $k): string => (string) ($post[$k] ?? '');
// A new picture never inherits the old one's caption or credit.
if (isset($changes['image']) && $changes['image'] !== $current('image')) {
    $changes['image_caption'] ??= '';
    $changes['image_credit'] ??= '';
}
$diff = array_filter($changes, static fn ($v, $k) => $v !== $current($k), ARRAY_FILTER_USE_BOTH);

$versionOf = static fn (array $p): string => 'v_' . substr(hash('sha256', implode("\x1f", [
    (int) $p['id'], (string) $p['updated_at'], (string) $p['title'], (string) ($p['lede'] ?? ''),
    (string) ($p['body'] ?? ''), (string) $p['image'], (string) $p['image_caption'], (string) ($p['image_credit'] ?? ''),
])), 0, 16);
$version = $versionOf($post);
$publicUrl = pp_story_public_url($siteSlug, (string) $post['slug']);

$ifVersion = trim((string) ($in['if_version'] ?? ''));
if ($ifVersion !== '' && !hash_equals($version, $ifVersion)) {
    pp_ingest_respond(409, ['ok' => false,
        'error' => 'the story changed since your version — nothing was written; review it and resend',
        'version' => $version]);
}

if (!$diff) {
    // Nothing differs: no write, no history row, no audit row.
    pp_ingest_respond(200, ['ok' => true, 'unchanged' => true, 'ref' => $articleRef,
        'status' => 'published', 'url' => $publicUrl, 'version' => $version]);
}

/* --- Rate limit: updates per token per hour, from the audit trail --------- */
$limit = 60;
$stmt = db()->prepare("SELECT COUNT(*) FROM audit_log WHERE user_name = ? AND action = 'ingest-update' AND created_at > ?");
$stmt->execute([mb_substr('hermes:' . $agent['name'], 0, 120), date('Y-m-d H:i:s', time() - 3600)]);
if ((int) $stmt->fetchColumn() >= $limit) {
    pp_ingest_respond(429, ['ok' => false, 'error' => "rate limit: at most {$limit} updates per hour for this token"]);
}

/* --- History holds the state before the change ----------------------------- */
// Editors snapshot after every save, but a story an agent filed has no
// history yet — so the pre-update state is written down here whenever the
// newest revision does not already say exactly it.
$s = db()->prepare('SELECT title, lede, body, image, image_caption, image_credit FROM post_revisions
    WHERE post_id = ? ORDER BY id DESC LIMIT 1');
$s->execute([$postId]);
$last = $s->fetch();
$s->closeCursor();
$sameAsLast = $last
    && (string) $last['title'] === $current('title')
    && (string) ($last['lede'] ?? '') === $current('lede')
    && (string) ($last['body'] ?? '') === $current('body')
    && (string) $last['image'] === $current('image')
    && (string) $last['image_caption'] === $current('image_caption')
    && ($last['image_credit'] === null || (string) $last['image_credit'] === $current('image_credit'));
if (!$sameAsLast) {
    pp_post_snapshot($postId, 'before agent update', '');
}

/* --- The write: compare-and-swap on what this request read ---------------- */
$now = now();
$set = [];
$args = [];
foreach ($diff as $k => $v) {
    $set[] = "$k = ?";
    $args[] = $v;
}
$set[] = 'updated_at = ?';
$args[] = $now;
$where = ["id = ?", "status = 'published'", 'updated_at = ?'];
$args[] = $postId;
$args[] = (string) $post['updated_at'];
foreach (array_keys($diff) as $k) {
    $where[] = "COALESCE($k, '') = ?";
    $args[] = $current($k);
}
$upd = db()->prepare('UPDATE posts SET ' . implode(', ', $set) . ' WHERE ' . implode(' AND ', $where));
$upd->execute($args);
if ($upd->rowCount() < 1) {
    pp_ingest_respond(409, ['ok' => false,
        'error' => 'the story changed while this update was in flight (an editor saved or unpublished it) — nothing was written; look it up and resend']);
}

pp_post_snapshot($postId, 'agent update', 'hermes:' . $agent['name']);
db()->prepare('UPDATE ingest_agents SET last_used_at = ? WHERE id = ?')->execute([$now, (int) $agent['id']]);
$fields = array_keys($diff);
pp_ingest_audit($siteId, (string) $agent['name'], 'ingest-update', (string) $post['slug'],
    'published story updated: ' . implode(', ', $fields)
        . (isset($diff['image']) ? ' (image ' . ($current('image') !== '' ? $current('image') : 'none') . ' → ' . $diff['image'] . ')' : ''),
    $postId, $now);

$s = db()->prepare("SELECT $cols FROM posts p WHERE p.id = ?");
$s->execute([$postId]);
$after = $s->fetch();
$s->closeCursor();

$out = ['ok' => true, 'ref' => $articleRef, 'status' => 'published', 'url' => $publicUrl,
    'updated' => $fields, 'version' => $after ? $versionOf($after) : ''];
if (isset($diff['image'])) {
    $out['previous_image'] = $current('image');
}
pp_ingest_respond(200, $out);
