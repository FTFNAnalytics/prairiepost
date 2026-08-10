<?php
/**
 * Development router for PHP's built-in server. Mirrors .htaccess.
 * Usage: php -S localhost:8080 router.php
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/story/([a-z0-9-]+)/?$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/article.php';
    return true;
}
if (preg_match('#^/desk/([a-z0-9-]+)/?$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/section.php';
    return true;
}
if (preg_match('#^/region/([a-z0-9-]+)/?$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/region.php';
    return true;
}
if (preg_match('#^/author/([a-z0-9-]+)/?$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/author.php';
    return true;
}
if (preg_match('#^/card/([a-z0-9-]+)\.png$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/card.php';
    return true;
}
if (preg_match('#^/newsletter(/.*)?$#', $path, $m)) {
    $_GET['path'] = $m[1] ?? '';
    require __DIR__ . '/newsletter.php';
    return true;
}
$map = [
    '/search'       => '/search.php',
    '/feed'         => '/feed.php',
    '/feed/'        => '/feed.php',
    '/sitemap.xml'  => '/sitemap.php',
    '/subscribe'    => '/subscribe.php',
    '/ad'           => '/ad.php',
    '/corrections'  => '/corrections.php',
];
if (isset($map[$path])) {
    require __DIR__ . $map[$path];
    return true;
}
if (preg_match('#^/(app|data)/#', $path)) {
    http_response_code(403);
    return true;
}
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false; // static file or explicit .php script
}
if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}
if (is_dir(__DIR__ . $path) && is_file(__DIR__ . rtrim($path, '/') . '/index.php')) {
    require __DIR__ . rtrim($path, '/') . '/index.php';
    return true;
}
http_response_code(404);
require __DIR__ . '/article.php'; // renders the branded not-found page
return true;
