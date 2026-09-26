<?php
/**
 * POST /api/ingest-media — featured-image upload for the ingest agents.
 *
 * The companion to /api/ingest: an agent whose graphic exists only on its
 * own machine uploads the bytes here FIRST, gets back an `/uploads/…`
 * path, and passes that path as `image` in the story filing. The same
 * bearer token authenticates both calls; a token that cannot file a
 * story cannot park files here either.
 *
 * The server owns what agents must not: the MIME sniff and image decode
 * (bytes that are not a real JPEG/PNG/WebP/GIF are refused, whatever
 * they were named), the stored filename, the size cap, and the audit
 * row. Files land under /uploads/YYYY/MM, where every vhost and
 * .htaccess refuses to execute PHP — an upload is data here, never code.
 *
 * Request:  Authorization: Bearer <token>
 *           EITHER multipart/form-data with the image in `file`
 *             (optional `name` field seeds the stored basename)
 *           OR the raw image bytes as the request body
 *             (optional ?name= query seeds the stored basename)
 * Response: 201 {ok, path, bytes}   — pass `path` as the story's image
 *           4xx {ok: false, error}  — the reason, never a coercion
 */
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

function pp_media_out(int $code, array $body): never
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    pp_media_out(405, ['ok' => false, 'error' => 'POST one image per call']);
}

/* --- Authentication: token -> agent row, fail closed ---------------------- */
$auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(\S{20,200})$/i', $auth, $m)) {
    pp_media_out(401, ['ok' => false, 'error' => 'missing bearer token']);
}
$stmt = db()->prepare('SELECT * FROM ingest_agents WHERE token_hash = ?');
$stmt->execute([hash('sha256', $m[1])]);
$agent = $stmt->fetch();
if (!$agent || (int) $agent['enabled'] !== 1) {
    // A revoked agent and an unknown token answer identically.
    pp_media_out(401, ['ok' => false, 'error' => 'unknown or revoked token']);
}

/* --- Rate limit: uploads per token per hour, from the audit trail --------- */
$limit = 60;
$stmt = db()->prepare("SELECT COUNT(*) FROM audit_log WHERE user_name = ? AND action = 'ingest-media' AND created_at > ?");
$stmt->execute(['hermes:' . $agent['name'], date('Y-m-d H:i:s', time() - 3600)]);
if ((int) $stmt->fetchColumn() >= $limit) {
    pp_media_out(429, ['ok' => false, 'error' => "rate limit: at most {$limit} uploads per hour for this token"]);
}

/* --- The bytes: multipart `file`, or the raw request body ----------------- */
$maxBytes = 8 * 1024 * 1024;
$baseName = '';
$bytes = '';
if (!empty($_FILES['file'])) {
    $f = $_FILES['file'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        pp_media_out(422, ['ok' => false, 'error' => 'the upload failed (code ' . (int) ($f['error'] ?? -1) . ')']);
    }
    if ((int) $f['size'] > $maxBytes) {
        pp_media_out(422, ['ok' => false, 'error' => 'the image is over 8 MB']);
    }
    $bytes = (string) file_get_contents((string) $f['tmp_name']);
    $baseName = (string) ($_POST['name'] ?? pathinfo((string) $f['name'], PATHINFO_FILENAME));
} else {
    // Raw-body form: cap the read at one byte past the limit so an
    // oversized stream is refused without buffering all of it.
    $bytes = (string) file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if (strlen($bytes) > $maxBytes) {
        pp_media_out(422, ['ok' => false, 'error' => 'the image is over 8 MB']);
    }
    $baseName = (string) ($_GET['name'] ?? '');
}
$baseName = mb_substr(trim(strip_tags($baseName)), 0, 80) ?: 'agent-image';

[$path, $err] = pp_store_image_bytes($bytes, $baseName);
if ($path === null) {
    pp_media_out(422, ['ok' => false, 'error' => (string) $err]);
}

$now = now();
db()->prepare('UPDATE ingest_agents SET last_used_at = ? WHERE id = ?')->execute([$now, (int) $agent['id']]);
db()->prepare('INSERT INTO audit_log (site_id, user_id, user_name, action, target, detail, ip, created_at)
    VALUES (0, 0, ?, ?, ?, ?, ?, ?)')
    ->execute(['hermes:' . $agent['name'], 'ingest-media', $path,
        strlen($bytes) . ' bytes', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $now]);

pp_media_out(201, ['ok' => true, 'path' => $path, 'bytes' => strlen($bytes)]);
