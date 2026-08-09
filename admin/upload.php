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

[$publicUrl, $error] = pp_handle_image_upload($_FILES['file']);
echo json_encode($error !== null ? ['error' => $error] : ['url' => $publicUrl]);
