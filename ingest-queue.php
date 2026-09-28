<?php
/**
 * GET /api/ingest-queue — the image agent's worklist.
 *
 * Lists the stories a filing agent flagged `publish_on_image` that are
 * still waiting for their featured image: everything the second agent
 * needs to generate art (headline, lede, desk, site) and nothing else.
 * Scoped exactly like every other ingest call — a token sees only the
 * queue of the papers it may write to. This is deliberately the ONLY
 * read the ingest surface offers, and it reads pipeline state the same
 * tokens created; the archive stays without a read API.
 *
 * Request:  Authorization: Bearer <token>
 * Response: 200 {ok, stories: [{id, slug, site, desk, title, lede,
 *                               dateline, filed_by, filed_at}]}
 *           Oldest first, at most 100.
 */
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

function pp_queue_out(int $code, array $body): never
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    pp_queue_out(405, ['ok' => false, 'error' => 'GET, with the bearer token']);
}

/* --- Authentication: token -> agent row, fail closed ---------------------- */
$auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(\S{20,200})$/i', $auth, $m)) {
    pp_queue_out(401, ['ok' => false, 'error' => 'missing bearer token']);
}
$stmt = db()->prepare('SELECT * FROM ingest_agents WHERE token_hash = ?');
$stmt->execute([hash('sha256', $m[1])]);
$agent = $stmt->fetch();
if (!$agent || (int) $agent['enabled'] !== 1) {
    // A revoked agent and an unknown token answer identically.
    pp_queue_out(401, ['ok' => false, 'error' => 'unknown or revoked token']);
}
$agentSites = array_values(array_filter(array_map('trim', explode(',', (string) $agent['sites']))));
if (!$agentSites) {
    pp_queue_out(200, ['ok' => true, 'stories' => []]);
}

$marks = implode(',', array_fill(0, count($agentSites), '?'));
$stmt = db()->prepare(
    "SELECT p.id, p.slug, s.slug AS site, c.slug AS desk, p.title, p.lede,
            p.dateline, p.filed_by, p.created_at AS filed_at
       FROM posts p
       JOIN post_sites ps ON ps.post_id = p.id
       JOIN sites s ON s.id = ps.site_id AND s.slug IN ($marks)
       LEFT JOIN categories c ON c.id = p.category_id
      WHERE p.awaiting_image = 1 AND p.status = 'draft'
      ORDER BY p.id
      LIMIT 100"
);
$stmt->execute($agentSites);

$stories = [];
foreach ($stmt as $row) {
    $stories[] = [
        'id' => (int) $row['id'],
        'slug' => (string) $row['slug'],
        'site' => (string) $row['site'],
        'desk' => (string) ($row['desk'] ?? ''),
        'title' => (string) $row['title'],
        'lede' => (string) $row['lede'],
        'dateline' => (string) $row['dateline'],
        'filed_by' => (string) $row['filed_by'],
        'filed_at' => (string) $row['filed_at'],
    ];
}
pp_queue_out(200, ['ok' => true, 'stories' => $stories]);
