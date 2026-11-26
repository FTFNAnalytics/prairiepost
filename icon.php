<?php
/**
 * Well-known icon URLs for the paper this hostname is.
 *
 * /favicon.ico and /apple-touch-icon.png are what Safari, iOS and Google's
 * favicon crawler request when a page's <link> tags are not enough. One
 * tree serves every paper, so the file cannot sit at the web root; this
 * serves the current tenant's copy (assets/sites/<slug>/, else the network
 * file when that exists) and 404s when neither does. Nothing else is served
 * from here.
 */
require __DIR__ . '/app/bootstrap.php';

pp_robots_header();

$path = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
$path = rtrim($path, '/');
$known = [
    '/favicon.ico' => ['favicon.ico', 'image/x-icon'],
    '/apple-touch-icon.png' => ['apple-touch-icon.png', 'image/png'],
    '/apple-touch-icon-precomposed.png' => ['apple-touch-icon.png', 'image/png'],
];
if (!isset($known[$path])) {
    http_response_code(404);
    exit;
}
[$file, $type] = $known[$path];
$asset = pp_existing_asset($file);
if ($asset === null) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $type);
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile(PP_ROOT . $asset);
