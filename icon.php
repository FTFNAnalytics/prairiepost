<?php
/**
 * Per-host icons. /favicon.ico, the PNG sizes, the touch icon and the
 * manifest are routes because one release tree serves every paper: the
 * host picks the file. Generated nginx blocks already send an unknown
 * path to router.php; a legacy block does the same once the upgrader's
 * front controller has converted it.
 */
require __DIR__ . '/app/bootstrap.php';

$kind = (string) ($_GET['kind'] ?? '');
[$type, $body, $status] = pp_icon_response($kind);
http_response_code($status);
header('Content-Type: ' . $type);
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
echo $body;
