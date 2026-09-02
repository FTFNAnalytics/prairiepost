<?php
/** The ingest image path: byte storage rules and the public-address guard. */
define('PP_ROOT', sys_get_temp_dir() . '/pp-imagestore-' . getmypid());
mkdir(PP_ROOT);
require dirname(__DIR__) . '/app/helpers.php';

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

// A real (tiny) PNG is accepted and lands under /uploads/YYYY/MM.
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
[$path, $err] = pp_store_image_bytes($png, 'Test Image');
ok($err === null && $path !== null, 'a PNG stores cleanly');
ok(is_file(PP_ROOT . $path), 'the stored file exists where the path says');
ok(str_ends_with((string) $path, '.png'), 'the extension comes from the sniffed MIME');
ok(str_starts_with((string) $path, '/uploads/'), 'the public path is under /uploads');

// Bytes that are not an image are refused, whatever they were named.
[$p2, $e2] = pp_store_image_bytes('<?php echo "nope";', 'evil.png');
ok($p2 === null && $e2 !== null, 'non-image bytes are refused');

[$p3, $e3] = pp_store_image_bytes('', 'empty');
ok($p3 === null, 'empty bytes are refused');

[$p4, $e4] = pp_store_image_bytes(str_repeat('x', 9 * 1024 * 1024), 'big');
ok($p4 === null && str_contains((string) $e4, '8 MB'), 'the 8 MB cap holds');

// The public-address guard: schemes and address space.
ok(!pp_url_is_public('ftp://example.com/a.png'), 'non-http schemes are refused');
ok(!pp_url_is_public('https://127.0.0.1/a.png'), 'loopback is refused');
ok(!pp_url_is_public('https://10.0.0.8/a.png'), 'RFC1918 10/8 is refused');
ok(!pp_url_is_public('https://192.168.1.5/a.png'), 'RFC1918 192.168/16 is refused');
ok(!pp_url_is_public('https://169.254.169.254/latest'), 'link-local metadata addresses are refused');
ok(pp_url_is_public('https://93.184.216.34/a.png'), 'a public literal address passes');
ok(!pp_url_is_public('https:///nohost'), 'a URL with no host is refused');

exit($fails ? 1 : 0);
