<?php
/** Image upload endpoint. MIME-checked, renamed, stored under /uploads/YYYY/MM/. */
require dirname(__DIR__) . '/app/bootstrap.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    echo json_encode(['error' => 'No file arrived. Pick an image and try again.']);
    exit;
}
csrf_check();

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'The upload failed (code ' . (int) $file['error'] . '). Try a smaller file.']);
    exit;
}
if ($file['size'] > 8 * 1024 * 1024) {
    echo json_encode(['error' => 'That file is over 8 MB. Resize it and try again.']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$extensions = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];
if (!isset($extensions[$mime])) {
    echo json_encode(['error' => 'Only JPEG, PNG, WebP or GIF images can be uploaded.']);
    exit;
}

$dir = PP_ROOT . '/uploads/' . date('Y/m');
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$base = slugify(pathinfo($file['name'], PATHINFO_FILENAME)) ?: 'image';
$name = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $extensions[$mime];

if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
    echo json_encode(['error' => "The server couldn't write the file. Check that /uploads/ is writable."]);
    exit;
}

echo json_encode(['url' => '/uploads/' . date('Y/m') . '/' . $name]);
