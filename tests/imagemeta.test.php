<?php
/**
 * Embedded metadata never reaches /uploads (app/imagemeta.php).
 *
 * For each format a realistic file is built: a GD-drawn picture carrying
 * what phones and editing software put in it (EXIF with a GPS location,
 * camera and time; XMP with a location; IPTC; comments; text chunks; an
 * image appended after the end marker with EXIF of its own). Stripping
 * must remove all of it, keep what the picture needs (colour profile,
 * JFIF, animation and looping blocks, a non-default orientation, alone),
 * leave the image decoding to IDENTICAL pixels, and be a no-op the second
 * time. PHP's own EXIF reader is the independent witness where it can
 * read the format. Then the store path the agents use, and the tool that
 * cleans what is already on disk: dry run, apply, and a second apply.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('PP_ROOT', sys_get_temp_dir() . '/pp-imagemeta-' . getmypid());
mkdir(PP_ROOT);
register_shutdown_function(fn () => exec('rm -rf ' . escapeshellarg(PP_ROOT)));
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

if (!function_exists('imagecreatetruecolor')) {
    echo "SKIP the whole suite: GD is not installed, so no test images can be drawn\n";
    exit(0);
}
$exifReader = function_exists('exif_read_data');
if (!$exifReader) {
    echo "SKIP the independent EXIF-reader checks: the exif extension is not installed\n";
}

/* --- Fixtures: tests/lib/images.php ---------------------------------------- */

require __DIR__ . '/lib/images.php';
$xmpGps = xmp_gps();

/* --- JPEG: everything a phone and an editing app leave in a photo ------------- */

$base = render('imagejpeg', picture(), null, 90);
$trailer = after_app0(render('imagejpeg', picture(), null, 60), seg(0xE1, "Exif\0\0" . tiff([[0x010F, 2, 'TrailCam']], null)));
$jpeg = after_app0($base,
    seg(0xE1, "Exif\0\0" . camera_exif(6))
    . seg(0xE1, "http://ns.adobe.com/xap/1.0/\0" . $xmpGps)
    . seg(0xED, "Photoshop 3.0\0" . "8BIM\x04\x04\0\0\0\0\0\x0cIPTC-byline")
    . seg(0xFE, 'source lives at 12 Elm St')
    . seg(0xE2, "ICC_PROFILE\0\x01\x01" . str_repeat('P', 64))
    . seg(0xE2, "MPF\0" . str_repeat("\0", 16))
    . seg(0xEB, "JP\0\x01\0\0\0\x01" . c2pa_box())
) . $trailer;

if ($exifReader) {
    $raw = exif_of($jpeg, 'image/jpeg');
    ok(isset($raw['GPSLatitude']) && ($raw['Make'] ?? '') === 'TestCam', 'jpeg fixture: PHP itself reads a GPS location and a camera in the original');
}
$r = pp_image_strip_metadata($jpeg, 'image/jpeg');
ok($r !== null, 'jpeg: the file parses end to end');
$clean = $r['bytes'] ?? '';
foreach (['EXIF (with GPS location)', 'XMP (with GPS location)', 'IPTC', 'comment', 'MPF (an index of extra images)',
          'Content Credentials (C2PA)', 'data after the end of the image'] as $label) {
    ok(in_array($label, $r['removed'] ?? [], true), "jpeg: reports removing $label");
}
foreach (['TestCam', 'Phone X', '2026:11:24', 'GPSLatitude', 'Jane Source', 'Elm St', 'Photoshop 3.0', 'IPTC-byline', "MPF\0", 'GenTool', 'TrailCam'] as $gone) {
    ok(!str_contains($clean, $gone), "jpeg: '$gone' is gone from the stored bytes");
}
ok(str_contains($clean, "ICC_PROFILE\0") && str_contains($clean, "JFIF\0"), 'jpeg: the colour profile and JFIF stay');
ok(str_starts_with($clean, "\xFF\xD8") && str_ends_with($clean, "\xFF\xD9"), 'jpeg: one image, start marker to end marker, nothing after');
if ($exifReader) {
    $after = exif_of($clean, 'image/jpeg');
    ok(($after['Orientation'] ?? null) === 6, 'jpeg: the orientation survives (PHP reads 6), so the photo still displays upright');
    ok(!isset($after['GPSLatitude']) && !isset($after['GPSLongitude']) && !isset($after['Make']) && !isset($after['DateTime']),
        'jpeg: PHP finds no GPS, camera or time in what is stored');
}
ok(same_pixels($jpeg, $clean), 'jpeg: decodes to identical pixels (nothing re-encoded)');
$again = pp_image_strip_metadata($clean, 'image/jpeg');
ok(($again['removed'] ?? null) === [] && ($again['bytes'] ?? '') === $clean, 'jpeg: a second pass finds nothing and changes nothing');

$upright = pp_image_strip_metadata(after_app0($base, seg(0xE1, "Exif\0\0" . camera_exif(1))), 'image/jpeg');
ok($upright !== null && !str_contains($upright['bytes'], "Exif\0\0"), 'jpeg: an upright photo keeps no EXIF block at all');
$le = pp_image_strip_metadata(after_app0($base, seg(0xE1, "Exif\0\0" . camera_exif(3, true))), 'image/jpeg');
ok($le !== null && pp_tiff_orientation(substr($le['bytes'], strpos($le['bytes'], "Exif\0\0") + 6)) === 3,
    'jpeg: little-endian EXIF is read too (orientation 3 carried over)');

// Progressive: metadata between scans is found as well.
$im = picture();
imageinterlace($im, true);
$prog = render('imagejpeg', $im, null, 85);
$second = strpos($prog, "\xFF\xDA", strpos($prog, "\xFF\xDA") + 2);
$progMeta = substr($prog, 0, $second) . seg(0xFE, 'between the scans') . substr($prog, $second);
$rp = pp_image_strip_metadata($progMeta, 'image/jpeg');
ok($rp !== null && !str_contains($rp['bytes'], 'between the scans') && same_pixels($progMeta, $rp['bytes']),
    'jpeg: a progressive photo loses a comment hidden between its scans and decodes identically');

// A photo cut off mid-scan still has a readable header, but can't be walked.
$sos = strpos($jpeg, "\xFF\xDA");
$cut = substr($jpeg, 0, intdiv($sos + strlen($jpeg) - strlen($trailer), 2));
ok(@getimagesizefromstring($cut) !== false, 'jpeg: the truncated fixture still has a readable header');
ok(pp_image_strip_metadata($cut, 'image/jpeg') === null, 'jpeg: a truncated photo cannot be walked, so it is refused');

/* --- PNG ---------------------------------------------------------------------- */

$pngBase = render('imagepng', picture());
$png = substr($pngBase, 0, 33)                                  // signature + IHDR
     . chunk('sRGB', "\0") . chunk('gAMA', pack('N', 45455))
     . chunk('tEXt', "Author\0Jane Source")
     . chunk('iTXt', "XML:com.adobe.xmp\0\0\0\0\0" . $xmpGps)
     . chunk('eXIf', camera_exif(8))
     . chunk('tIME', "\x07\xEA\x0B\x18\x09\x0F\x00")
     . chunk('prVt', 'private app data')
     . chunk('caBX', c2pa_box()) . chunk('deBG', '0E42A1A6A9509FB1')   // what an image generator leaves
     . substr($pngBase, 33) . 'trailing secret';
$r = pp_image_strip_metadata($png, 'image/png');
ok($r !== null, 'png: the file parses end to end');
$clean = $r['bytes'] ?? '';
foreach (['EXIF (with GPS location)', 'XMP (with GPS location)', 'text (Author)', 'timestamp', 'chunk prVt',
          'Content Credentials (C2PA)', 'chunk deBG', 'data after the end of the image'] as $label) {
    ok(in_array($label, $r['removed'] ?? [], true), "png: reports removing $label");
}
foreach (['Jane Source', 'GPSLatitude', 'TestCam', 'tIME', 'prVt', 'GenTool', '0E42A1A6', 'trailing secret'] as $gone) {
    ok(!str_contains($clean, $gone), "png: '$gone' is gone");
}
ok(str_contains($clean, 'sRGB') && str_contains($clean, 'gAMA'), 'png: the colour chunks stay');
$at = strpos($clean, 'eXIf');
$exifLen = $at === false ? 0 : unpack('N', substr($clean, $at - 4, 4))[1];
$exifData = $at === false ? '' : substr($clean, $at + 4, $exifLen);
ok($at !== false && pp_tiff_orientation($exifData) === 8 && !pp_tiff_has_gps($exifData), 'png: only the orientation (8) is carried over');
ok($at !== false && substr($clean, $at + 4 + $exifLen, 4) === pack('N', crc32('eXIf' . $exifData)), 'png: the new eXIf chunk carries a valid CRC');
ok(same_pixels($png, $clean), 'png: decodes to identical pixels');
ok((pp_image_strip_metadata($clean, 'image/png')['removed'] ?? null) === [], 'png: a second pass finds nothing');

/* --- WebP --------------------------------------------------------------------- */

if (!function_exists('imagewebp')) {
    echo "SKIP the WebP cases: this GD has no WebP support\n";
} else {
    $webpBase = render('imagewebp', picture(), null, 80);
    $riff = fn (string $id, string $data) => $id . pack('V', strlen($data)) . $data . (strlen($data) & 1 ? "\0" : '');
    $vp8x = $riff('VP8X', chr(0x20 | 0x08 | 0x04) . "\0\0\0" . substr(pack('V', 47), 0, 3) . substr(pack('V', 31), 0, 3));
    $body = 'WEBP' . $vp8x . $riff('ICCP', 'icc-profile-bytes') . substr($webpBase, 12)
          . $riff('EXIF', camera_exif(6)) . $riff('XMP ', $xmpGps) . $riff('C2PA', c2pa_box()) . $riff('ABCD', 'unknown');
    $webp = 'RIFF' . pack('V', strlen($body)) . $body;
    $r = pp_image_strip_metadata($webp, 'image/webp');
    ok($r !== null, 'webp: the extended file parses end to end');
    $clean = $r['bytes'] ?? '';
    foreach (['EXIF (with GPS location)', 'XMP (with GPS location)', 'Content Credentials (C2PA)', 'chunk ABCD'] as $label) {
        ok(in_array($label, $r['removed'] ?? [], true), "webp: reports removing $label");
    }
    ok(!str_contains($clean, 'GPSLatitude') && !str_contains($clean, 'TestCam') && !str_contains($clean, 'GenTool')
        && !str_contains($clean, 'ABCD'), 'webp: the metadata is gone');
    ok(str_contains($clean, 'ICCP') && (ord($clean[20]) & 0x2C) === 0x28, 'webp: the colour profile stays; VP8X says ICC and EXIF, no longer XMP');
    ok(unpack('V', substr($clean, 4, 4))[1] === strlen($clean) - 8, 'webp: the RIFF size matches the rewritten file');
    $e = strpos($clean, 'EXIF');
    ok($e !== false && pp_tiff_orientation(substr($clean, $e + 8, 26)) === 6, 'webp: only the orientation (6) is carried over');
    ok(same_pixels($webp, $clean), 'webp: decodes to identical pixels');
    ok((pp_image_strip_metadata($clean, 'image/webp')['removed'] ?? null) === [], 'webp: a second pass finds nothing');
}

/* --- GIF ---------------------------------------------------------------------- */

$gifBase = 'GIF89a' . substr(render('imagegif', imagecreatetruecolor(48, 32)), 6);
$packed = ord($gifBase[10]);
$at = 13 + (($packed & 0x80) ? 3 * (2 << ($packed & 0x07)) : 0);
$xmpBlocks = implode('', array_map(fn ($c) => chr(strlen($c)) . $c, str_split($xmpGps, 255)));
$gif = substr($gifBase, 0, $at)
     . "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00"
     . "\x21\xFE" . chr(18) . 'secret gif comment' . "\x00"
     . "\x21\xFF\x0BXMP DataXMP" . $xmpBlocks . "\x00"
     . substr($gifBase, $at) . 'trailing gif junk';
$r = pp_image_strip_metadata($gif, 'image/gif');
ok($r !== null, 'gif: the file parses end to end');
$clean = $r['bytes'] ?? '';
foreach (['comment', 'XMP (with GPS location)', 'data after the end of the image'] as $label) {
    ok(in_array($label, $r['removed'] ?? [], true), "gif: reports removing $label");
}
ok(!str_contains($clean, 'secret gif comment') && !str_contains($clean, 'GPSLatitude') && !str_contains($clean, 'trailing gif junk'), 'gif: the metadata is gone');
ok(str_contains($clean, 'NETSCAPE2.0') && str_ends_with($clean, ';'), 'gif: the looping block stays and the file ends at its trailer');
ok(same_pixels($gif, $clean), 'gif: decodes to identical pixels');

/* --- A wire image cached from another site ------------------------------------- */

// The link poster's "cache a copy" goes through the real transport. A
// loopback server stands in for the outlet; 127.0.0.1 counts as public
// for THIS CLI process only (the transport's test hook refuses to arm
// anywhere else).
putenv('PP_TRANSPORT_TEST=1');
require dirname(__DIR__) . '/app/models.php';      // pp_ip_in_cidr
require dirname(__DIR__) . '/app/transport.php';
require dirname(__DIR__) . '/app/fetch.php';
$outlet = PP_ROOT . '/outlet';
mkdir($outlet);
file_put_contents("$outlet/photo.jpg", $jpeg);
file_put_contents("$outlet/cut.jpg", $cut);
$outletPort = 8600 + random_int(0, 90);
$outletPid = (int) trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $outletPort
    . ' -t ' . escapeshellarg($outlet) . ' >/dev/null 2>&1 & echo $!'));
register_shutdown_function(fn () => $outletPid && @posix_kill($outletPid, 15));
usleep(600000);
pp_http_test_hooks(['public_ips' => ['127.0.0.1'], 'resolver' => fn (string $h) => $h === 'outlet.test' ? ['127.0.0.1'] : []]);

[$cachedPath, $cacheErr] = pp_cache_remote_image("http://outlet.test:$outletPort/photo.jpg");
$cached = (string) @file_get_contents(PP_ROOT . (string) $cachedPath);
ok($cacheErr === null && $cached !== '', 'wire cache: the outlet photo is cached (' . ($cacheErr ?? 'ok') . ')');
ok(!str_contains($cached, 'TestCam') && !str_contains($cached, 'GPSLatitude') && !str_contains($cached, 'TrailCam')
    && same_pixels($jpeg, $cached), "wire cache: another publisher's location and camera are not republished; the picture is");
[$cutPath, $cutErr] = pp_cache_remote_image("http://outlet.test:$outletPort/cut.jpg");
ok($cutPath === null && str_contains((string) $cutErr, 'embedded metadata'), 'wire cache: a photo that cannot be cleaned is not cached');

/* --- The agents' store path ----------------------------------------------------- */

[$path, $err] = pp_store_image_bytes($jpeg, 'Field photo');
ok($err === null && $path !== null, 'store: the GPS-tagged photo is accepted');
$stored = (string) @file_get_contents(PP_ROOT . $path);
ok($stored !== '' && !str_contains($stored, 'TestCam') && !str_contains($stored, 'GPSLatitude') && !str_contains($stored, 'TrailCam'),
    'store: what lands in /uploads carries none of it');
if ($exifReader) {
    $kept = exif_of($stored, 'image/jpeg');
    ok(!isset($kept['GPSLatitude']) && ($kept['Orientation'] ?? null) === 6, 'store: PHP reads the stored file: no GPS, orientation 6');
}
[$p2, $e2] = pp_store_image_bytes($cut, 'cut off');
ok($p2 === null && str_contains((string) $e2, 'embedded metadata'), 'store: a photo that cannot be cleaned is refused, never stored as it came');

/* --- The tool, over what is already on disk ------------------------------------ */

$dir = PP_ROOT . '/disk';
mkdir("$dir/2026/09", 0777, true);
file_put_contents("$dir/2026/09/gps.jpg", $jpeg);
file_put_contents("$dir/2026/09/notes.png", substr($pngBase, 0, 33) . chunk('tEXt', "Comment\0shot from the courthouse roof") . substr($pngBase, 33));
file_put_contents("$dir/clean.gif", $gifBase);
file_put_contents("$dir/cut.jpg", $cut);
file_put_contents("$dir/readme.jpg", 'not an image at all');
chmod("$dir/2026/09/gps.jpg", 0640);
$hashes = fn () => array_map(fn ($f) => md5_file("$dir/$f"), ['2026/09/gps.jpg', '2026/09/notes.png', 'clean.gif', 'cut.jpg', 'readme.jpg']);
$tool = fn (string $args) => [exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/strip-image-metadata.php') . " $args 2>&1", $out, $code), implode("\n", $out), $code];

$before = $hashes();
[, $out, $code] = $tool('--dir=' . escapeshellarg($dir));
ok($code === 0 && str_contains($out, 'WOULD 2026/09/gps.jpg: EXIF (with GPS location)') && str_contains($out, 'WOULD 2026/09/notes.png: text (Comment)'),
    "tool: the dry run names each file and what it carries\n" . $out);
ok(str_contains($out, 'SKIP  cut.jpg') && str_contains($out, '2 carried embedded metadata (1 with a GPS location)') && str_contains($out, 'Dry run: nothing changed'),
    'tool: the dry run counts, flags the GPS one, and skips what it cannot parse');
ok($hashes() === $before, 'tool: the dry run changes no file');

[, $out, $code] = $tool('--apply --dir=' . escapeshellarg($dir));
$afterApply = $hashes();
ok($code === 0 && str_contains($out, 'Cleaned 2.'), "tool: --apply cleans the two\n" . $out);
ok($afterApply[0] !== $before[0] && $afterApply[1] !== $before[1], 'tool: the two files with metadata were rewritten');
ok($afterApply[2] === $before[2] && $afterApply[3] === $before[3] && $afterApply[4] === $before[4],
    'tool: the clean file, the unparseable one and the non-image are untouched');
$onDisk = (string) file_get_contents("$dir/2026/09/gps.jpg");
ok(!str_contains($onDisk, 'GPSLatitude') && !str_contains($onDisk, 'TestCam') && same_pixels($jpeg, $onDisk), 'tool: the rewritten photo has no location and the same pixels');
ok((fileperms("$dir/2026/09/gps.jpg") & 0777) === 0640, "tool: the file's mode is kept");
ok(glob("$dir/2026/09/.pp-strip-*") === [], 'tool: no temporary file is left behind');

[, $out, $code] = $tool('--apply --dir=' . escapeshellarg($dir));
ok($code === 0 && str_contains($out, '0 carried embedded metadata') && $hashes() === $afterApply, 'tool: a second --apply finds nothing and changes nothing');

[, , $code] = $tool('--nonsense');
ok($code === 64, 'tool: an unknown argument is a usage error');

exit($fails ? 1 : 0);
