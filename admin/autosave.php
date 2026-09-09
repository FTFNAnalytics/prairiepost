<?php
/**
 * Autosave endpoint: keeps the text of an open draft safe every half minute.
 * Only touches existing stories still in draft or review — a published story
 * is never modified behind the editor's back.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
$user = require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST only']);
    exit;
}
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM posts WHERE id = ?');
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post || pp_post_write_denied($user, $post, 'autosave') !== null
    || !in_array($post['status'], ['draft', 'in_review'], true)) {
    echo json_encode(['error' => 'not saved']);
    exit;
}

// The state check rides in the UPDATE itself: if an editor publishes or
// schedules the story between the read above and this write, zero rows
// match and the autosave lands nowhere — a published story is never
// modified behind the editor's back, even by a racing request.
$wrote = pp_guarded_post_update($id, [
    'title'      => trim((string) ($_POST['title'] ?? $post['title'])) ?: $post['title'],
    'lede'       => trim((string) ($_POST['lede'] ?? '')),
    'body'       => sanitize_html((string) ($_POST['body'] ?? '')),
    'updated_at' => now(),
], ['draft', 'in_review']);
if (!$wrote) {
    echo json_encode(['error' => 'not saved']);
    exit;
}
// One history snapshot per half hour of typing — not one per keystroke burst.
pp_post_snapshot($id, 'autosave', $user['name'], 1800);

echo json_encode(['saved' => strtolower(str_replace(['AM', 'PM'], ['a.m.', 'p.m.'], date('g:i A')))]);
