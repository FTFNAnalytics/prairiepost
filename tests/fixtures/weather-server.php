<?php
/**
 * Stand-in for api.weather.gc.ca in tests/weather.test.php:
 * php -S 127.0.0.1:PORT tests/fixtures/weather-server.php
 *
 *   /items/<id>       the fixture record tests/fixtures/eccc-<id>.json, observed 20 minutes ago
 *   /items/qc-998     a body that is not JSON
 *   anything else     404, as the real API answers an unknown city
 *
 * Test-only.
 */
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (($log = getenv('PP_WEATHER_FIXTURE_LOG')) !== false && $log !== '') {
    file_put_contents($log, $path . "\n", FILE_APPEND);   // the test counts fetches per city
}
if (preg_match('#^/items/([a-z]{2}-\d{1,3})$#', $path, $m) !== 1) {
    http_response_code(404);
    return;
}
if ($m[1] === 'qc-998') {
    header('Content-Type: text/html');
    echo '<html>maintenance</html>';
    return;
}
$file = __DIR__ . '/eccc-' . $m[1] . '.json';
if (!is_file($file)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo '{"code":"NotFound","description":"identifier not found"}';
    return;
}
header('Content-Type: application/geo+json');
echo str_replace('__OBSERVED__', gmdate('Y-m-d\TH:i:s\Z', time() - 1200), (string) file_get_contents($file));
