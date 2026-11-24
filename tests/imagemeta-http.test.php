<?php
/**
 * Embedded metadata never reaches /uploads, over real HTTP: a phone photo
 * carrying a GPS location goes in through the agents' upload route
 * (POST /api/ingest-media, raw body and multipart) and the newsroom's
 * (an editor's browser posting to /admin/upload.php), and what lands on
 * disk is read back by PHP's own EXIF reader. A photo that cannot be
 * walked end to end is refused on both, never stored as it came.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
require __DIR__ . '/lib/images.php';

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

if (!function_exists('imagecreatetruecolor')) {
    echo "SKIP the whole suite: GD is not installed, so no test images can be drawn\n";
    exit(0);
}

$fx = pp_fixture_create('imagemetahttp');
pp_fixture_prepare($fx);
$work = $fx['work'];
putenv('PP_CONFIG=' . $fx['config']);
define('PP_TEST_BOOT', 1);
require $root . '/app/bootstrap.php';
$pdo = db();
echo 'engine: ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . " ({$fx['id']})\n";

$token = 'hermes_' . bin2hex(random_bytes(28));
$pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, created_at) VALUES (?,?,?,?,1,?)')
    ->execute(['metadata-uploader', hash('sha256', $token), 'prairiedispatch', '', now()]);
$pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
    ->execute(['Ed Photo', 'editor-imagemeta@test.local', password_hash('correct horse pp', PASSWORD_DEFAULT), 'editor', 'ed-photo-imagemeta', now()]);

$port = 8700 + random_int(0, 80);
$pid = (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($fx['config'])
    . ' ' . escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' router.php >'
    . escapeshellarg("$work/server.log") . ' 2>&1 & echo $!'));
usleep(700000);
$stored = [];
register_shutdown_function(function () use ($pid, $fx, &$stored, $root) {
    if ($pid) {
        @posix_kill($pid, 15);
    }
    foreach ($stored as $f) {
        @unlink($root . $f);
    }
    pp_fixture_destroy($fx);
});

/** One request; a cookie jar makes it a browser session. Returns [code, json, body]. */
function req(int $port, string $path, array $opt): array
{
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false]);
    $headers = isset($opt['token']) ? ['Authorization: Bearer ' . $opt['token']] : [];
    if (isset($opt['jar'])) {
        curl_setopt_array($ch, [CURLOPT_COOKIEJAR => $opt['jar'], CURLOPT_COOKIEFILE => $opt['jar']]);
    }
    if (isset($opt['raw'])) {
        $headers[] = 'Content-Type: ' . $opt['ctype'];
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $opt['raw']]);
    } elseif (isset($opt['form'])) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $opt['form']]);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, (array) json_decode($body, true), $body];
}

/** Every property a reader could learn where, when and with what the photo was taken. */
function carries_nothing(string $bytes): bool
{
    foreach (['TestCam', 'Phone X', '2026:11:24', 'GPSLatitude', 'GPSLongitude', 'Jane Source', 'Pinned to the fridge'] as $needle) {
        if (str_contains($bytes, $needle)) {
            return false;
        }
    }
    $exif = exif_of($bytes, 'image/jpeg');
    return !isset($exif['GPSLatitude']) && !isset($exif['Make']) && !isset($exif['DateTime']);
}

$photo = phone_jpeg();
$cut = truncated_jpeg();
if (function_exists('exif_read_data')) {
    $raw = exif_of($photo, 'image/jpeg');
    ok(isset($raw['GPSLatitude']) && ($raw['Make'] ?? '') === 'TestCam', 'fixture: PHP reads a GPS location and a camera in the photo sent');
} else {
    echo "SKIP the independent EXIF-reader checks: the exif extension is not installed\n";
}
$png = substr(render('imagepng', picture()), 0, 33) . chunk('tEXt', "Comment\0Pinned to the fridge")
     . substr(render('imagepng', picture()), 33);

/* --- The agents' route: POST /api/ingest-media --------------------------------- */

[$code, $j] = req($port, '/api/ingest-media?name=field%20photo', ['token' => $token, 'raw' => $photo, 'ctype' => 'image/jpeg']);
$path = (string) ($j['path'] ?? '');
$onDisk = $path !== '' ? (string) @file_get_contents($root . $path) : '';
$stored[] = $path;
ok($code === 201 && $onDisk !== '', "ingest-media (raw body): the photo is accepted and stored (got $code)");
ok(carries_nothing($onDisk), 'ingest-media (raw body): the stored file carries no location, camera or time');
ok(same_pixels($photo, $onDisk), 'ingest-media (raw body): the stored picture is the picture sent');
ok(($j['bytes'] ?? null) === strlen($photo), 'ingest-media: `bytes` still reports what was sent');
if (function_exists('exif_read_data')) {
    ok((exif_of($onDisk, 'image/jpeg')['Orientation'] ?? null) === 6, 'ingest-media: the orientation is kept, so the photo still displays upright');
}

$file = "$work/fridge.png";
file_put_contents($file, $png);
[$code, $j] = req($port, '/api/ingest-media', ['token' => $token, 'form' => ['file' => new CURLFile($file, 'image/png', 'fridge.png')]]);
$path = (string) ($j['path'] ?? '');
$onDisk = $path !== '' ? (string) @file_get_contents($root . $path) : '';
$stored[] = $path;
ok($code === 201 && $onDisk !== '' && !str_contains($onDisk, 'Pinned to the fridge') && same_pixels($png, $onDisk),
    "ingest-media (multipart): a PNG's text chunk is gone, its pixels are not (got $code)");

[$code, $j] = req($port, '/api/ingest-media', ['token' => $token, 'raw' => $cut, 'ctype' => 'image/jpeg']);
ok($code === 422 && str_contains((string) ($j['error'] ?? ''), 'embedded metadata'),
    "ingest-media: a photo that cannot be walked is refused with a 422 that says why (got $code)");

/* --- The newsroom's route: an editor's browser posting to /admin/upload.php ------ */

$jar = "$work/jar";
[, , $page] = req($port, '/admin/login.php', ['jar' => $jar]);
preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
$csrf = $m[1] ?? '';
[$code] = req($port, '/admin/login.php', ['jar' => $jar,
    'form' => http_build_query(['csrf' => $csrf, 'email' => 'editor-imagemeta@test.local', 'password' => 'correct horse pp'])]);
ok($code === 302, "the editor signs in (got $code)");

$file = "$work/IMG_0042.jpg";
file_put_contents($file, $photo);
[$code, $j] = req($port, '/admin/upload.php', ['jar' => $jar,
    'form' => ['csrf' => $csrf, 'file' => new CURLFile($file, 'image/jpeg', 'IMG_0042.jpg')]]);
$url = (string) ($j['url'] ?? '');
$onDisk = $url !== '' ? (string) @file_get_contents($root . $url) : '';
$stored[] = $url;
ok($code === 200 && str_starts_with($url, '/uploads/') && $onDisk !== '', 'admin upload: the photo is accepted (' . ($j['error'] ?? $url) . ')');
ok(carries_nothing($onDisk) && same_pixels($photo, $onDisk), 'admin upload: the stored file carries no location, camera or time, and the same picture');

$file = "$work/cut.jpg";
file_put_contents($file, $cut);
[$code, $j] = req($port, '/admin/upload.php', ['jar' => $jar,
    'form' => ['csrf' => $csrf, 'file' => new CURLFile($file, 'image/jpeg', 'cut.jpg')]]);
ok(!isset($j['url']) && str_contains((string) ($j['error'] ?? ''), 'embedded metadata'),
    'admin upload: a photo that cannot be walked is refused and says why');

exit($fails ? 1 : 0);
