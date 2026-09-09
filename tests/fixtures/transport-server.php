<?php
/**
 * Fixture router for the transport tests: php -S 127.0.0.1:PORT this-file.
 * Endpoints model the shapes the safe transport must survive. Test-only.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$q = $_GET;

switch ($path) {
    case '/ok':
        header('Content-Type: text/html; charset=utf-8');
        echo '<html><head><meta property="og:title" content="Fixture Title"></head><body>FIXTURE-OK</body></html>';
        return;

    case '/rss':
        header('Content-Type: application/rss+xml');
        echo '<?xml version="1.0"?><rss version="2.0"><channel><title>Fixture</title>'
           . '<item><title>Fixture item</title><link>http://public-a.test/story</link>'
           . '<description>Body</description><pubDate>Mon, 01 Sep 2026 06:00:00 +0000</pubDate></item></channel></rss>';
        return;

    case '/img':
        header('Content-Type: image/png');
        // 1×1 transparent PNG.
        echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        return;

    case '/redir-rel':
        http_response_code(302);
        header('Location: /ok');
        return;

    case '/redir-abs':
        http_response_code((int) ($q['code'] ?? 302));
        header('Location: ' . ($q['to'] ?? '/ok'));
        return;

    case '/loop':
        http_response_code(302);
        header('Location: /loop');
        return;

    case '/big':
        header('Content-Type: application/octet-stream');
        $chunk = str_repeat('A', 65536);
        for ($i = 0; $i < 64; $i++) { // 4 MB total
            echo $chunk;
            @ob_flush();
            @flush();
        }
        return;

    case '/gzip-big':
        // A small compressed body that inflates far past the cap the test
        // sets — the streamed limit must count DECOMPRESSED bytes.
        header('Content-Type: application/octet-stream');
        header('Content-Encoding: gzip');
        echo gzencode(str_repeat("\0", 2 * 1024 * 1024), 9);
        return;

    case '/slow':
        sleep(4);
        echo 'late';
        return;

    default:
        http_response_code(404);
        echo 'fixture 404';
}
