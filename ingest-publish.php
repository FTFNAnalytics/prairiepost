<?php
/**
 * POST /api/ingest-publish — attach the featured image and publish.
 *
 * The second half of the publish-on-image lane. The story must have
 * been filed with `publish_on_image` (the text agent's "ready"
 * checkbox) and still be waiting: attaching its featured image here
 * publishes it in the same guarded write, and the response carries the
 * public URL for the social posts that follow. No flag, no publish —
 * this endpoint refuses (409) any story that was not explicitly filed
 * as ready, including everything editors are working on. The image
 * must be an /uploads/… path this install minted via
 * POST /api/ingest-media.
 *
 * Request:  Authorization: Bearer <token>
 *           JSON body: {story, image, image_caption?, image_credit?}
 *           `story` is the slug (or numeric id) from the filing
 *           response or GET /api/ingest-queue.
 * Response: 200 {ok, id, slug, status: "published", url, image}
 *           4xx {ok: false, error} — the reason, never a coercion
 */
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

function pp_pub_out(int $code, array $body): never
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    pp_pub_out(405, ['ok' => false, 'error' => 'POST one JSON attach-and-publish per call']);
}

/* --- Authentication: token -> agent row, fail closed ---------------------- */
$auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(\S{20,200})$/i', $auth, $m)) {
    pp_pub_out(401, ['ok' => false, 'error' => 'missing bearer token']);
}
$stmt = db()->prepare('SELECT * FROM ingest_agents WHERE token_hash = ?');
$stmt->execute([hash('sha256', $m[1])]);
$agent = $stmt->fetch();
if (!$agent || (int) $agent['enabled'] !== 1) {
    // A revoked agent and an unknown token answer identically.
    pp_pub_out(401, ['ok' => false, 'error' => 'unknown or revoked token']);
}
$agentSites = array_filter(array_map('trim', explode(',', (string) $agent['sites'])));

/* --- Payload -------------------------------------------------------------- */
$raw = file_get_contents('php://input', false, null, 0, 64 * 1024);
$in = json_decode((string) $raw, true);
if (!is_array($in)) {
    pp_pub_out(400, ['ok' => false, 'error' => 'body must be a JSON object']);
}

$storyRef = trim((string) ($in['story'] ?? ''));
if ($storyRef === '') {
    pp_pub_out(422, ['ok' => false, 'error' => 'story is required: the slug (or id) from the filing response or /api/ingest-queue']);
}
$imagePath = trim((string) ($in['image'] ?? ''));
if (!preg_match('#^/uploads/\d{4}/\d{2}/[a-z0-9][a-z0-9-]*\.(jpg|jpeg|png|webp|gif)$#', $imagePath)) {
    pp_pub_out(422, ['ok' => false, 'error' => 'image must be an /uploads/ path returned by /api/ingest-media']);
}
if (!is_file(PP_ROOT . $imagePath)) {
    pp_pub_out(422, ['ok' => false, 'error' => 'image names an /uploads/ path that does not exist — upload it first via /api/ingest-media']);
}
$imageCaption = mb_substr(trim(strip_tags((string) ($in['image_caption'] ?? ''))), 0, 255);
$imageCredit = mb_substr(trim(strip_tags((string) ($in['image_credit'] ?? ''))), 0, 120);

/* --- The story: found, in the token's scope, and actually waiting --------- */
$pdo = db();
$col = ctype_digit($storyRef) ? 'p.id' : 'p.slug';
$stmt = $pdo->prepare(
    "SELECT p.id, p.slug, p.status, p.awaiting_image, s.slug AS site
       FROM posts p
       JOIN post_sites ps ON ps.post_id = p.id
       JOIN sites s ON s.id = ps.site_id
      WHERE $col = ? LIMIT 1"
);
$stmt->execute([ctype_digit($storyRef) ? (int) $storyRef : $storyRef]);
$post = $stmt->fetch();
if (!$post) {
    pp_pub_out(404, ['ok' => false, 'error' => 'no such story']);
}
if (!in_array((string) $post['site'], $agentSites, true)) {
    pp_pub_out(403, ['ok' => false, 'error' => 'this token is not scoped to that story\'s site']);
}
if ((int) $post['awaiting_image'] !== 1 || $post['status'] !== 'draft') {
    pp_pub_out(409, ['ok' => false, 'error' => $post['status'] === 'published'
        ? 'that story is already published'
        : 'that story was not filed as publish-on-image — only its newsroom can publish it']);
}

/* --- Attach and publish in one guarded write ------------------------------- */
$now = now();
$upd = $pdo->prepare(
    "UPDATE posts
        SET image = ?, image_caption = ?, image_credit = ?,
            status = 'published', awaiting_image = 0,
            published_at = ?, updated_at = ?
      WHERE id = ? AND status = 'draft' AND awaiting_image = 1"
);
$upd->execute([$imagePath, $imageCaption, $imageCredit, $now, $now, (int) $post['id']]);
if ($upd->rowCount() !== 1) {
    // Lost a race: someone published, unflagged or deleted it meanwhile.
    pp_pub_out(409, ['ok' => false, 'error' => 'the story changed state while this request ran — fetch the queue again']);
}

$pdo->prepare('UPDATE ingest_agents SET last_used_at = ? WHERE id = ?')->execute([$now, (int) $agent['id']]);
$pdo->prepare('INSERT INTO audit_log (site_id, user_id, user_name, action, target, detail, ip, created_at)
    VALUES ((SELECT site_id FROM post_sites WHERE post_id = ? LIMIT 1), 0, ?, ?, ?, ?, ?, ?)')
    ->execute([(int) $post['id'], 'hermes:' . $agent['name'], 'ingest-publish', (string) $post['slug'],
        'featured image attached, published (publish-on-image lane)',
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $now]);

pp_pub_out(200, [
    'ok' => true,
    'id' => (int) $post['id'],
    'slug' => (string) $post['slug'],
    'status' => 'published',
    'url' => pp_story_public_url((string) $post['site'], (string) $post['slug']),
    'image' => $imagePath,
]);
