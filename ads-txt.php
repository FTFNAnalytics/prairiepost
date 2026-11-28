<?php
/**
 * Per-site dynamic /ads.txt endpoint.
 * Configured in Admin -> Settings -> Advertising & analytics (setting: ads_txt).
 * Answers 404 when empty so papers without an ad network don't publish a blank file.
 */
require __DIR__ . '/app/bootstrap.php';

$raw = (string) setting('ads_txt', '');
// Strip NULs and non-printable control characters while preserving tabs/newlines.
$clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $raw);
$clean = str_replace(["\r\n", "\r"], "\n", (string) $clean);
$clean = trim($clean);

if ($clean === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo "Not found\n";
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-Robots-Tag: noindex');
echo $clean . "\n";
