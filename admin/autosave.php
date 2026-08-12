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

if (!$post || !can_edit_post($user, $post) || !in_array($post['status'], ['draft', 'in_review'], true)) {
    echo json_encode(['error' => 'not saved']);
    exit;
}

db()->prepare('UPDATE posts SET title = ?, lede = ?, body = ?, updated_at = ? WHERE id = ?')
    ->execute([
        trim((string) ($_POST['title'] ?? $post['title'])) ?: $post['title'],
        trim((string) ($_POST['lede'] ?? '')),
        sanitize_html((string) ($_POST['body'] ?? '')),
        now(),
        $id,
    ]);
// One history snapshot per half hour of typing — not one per keystroke burst.
pp_post_snapshot($id, 'autosave', $user['name'], 1800);

echo json_encode(['saved' => strtolower(str_replace(['AM', 'PM'], ['a.m.', 'p.m.'], date('g:i A')))]);
