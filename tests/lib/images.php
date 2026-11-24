<?php
/**
 * Test images that carry what real photos carry: EXIF with a GPS location,
 * camera and time; XMP with a location; JPEG segments, PNG chunks. Shared
 * by tests/imagemeta.test.php (the stripper itself) and
 * tests/imagemeta-http.test.php (every upload path, over HTTP). Needs GD.
 */

/** A 48x32 picture with enough structure that a pixel mix-up would show. */
function picture()
{
    $im = imagecreatetruecolor(48, 32);
    for ($y = 0; $y < 32; $y++) {
        for ($x = 0; $x < 48; $x++) {
            imagesetpixel($im, $x, $y, imagecolorallocate($im, ($x * 5) % 256, ($y * 8) % 256, (($x + $y) * 3) % 256));
        }
    }
    return $im;
}

/** What a GD output function writes, as a string. */
function render(string $fn, ...$args): string
{
    ob_start();
    $fn(...$args);
    return (string) ob_get_clean();
}

/** A TIFF structure: IFD0 entries [tag, type, values], plus a GPS directory. */
function tiff(array $ifd0, ?array $gps, bool $le = false): string
{
    $p16 = fn (int $v) => pack($le ? 'v' : 'n', $v);
    $p32 = fn (int $v) => pack($le ? 'V' : 'N', $v);
    $encode = fn (int $type, $v) => match ($type) {
        2 => [strlen($v) + 1, $v . "\0"],
        3 => [count($v), implode('', array_map($p16, $v))],
        4 => [count($v), implode('', array_map($p32, $v))],
        5 => [count($v), implode('', array_map(fn ($r) => $p32($r[0]) . $p32($r[1]), $v))],
    };
    $ifd = function (array $entries, int $at) use ($encode, $p16, $p32): string {
        usort($entries, fn ($a, $b) => $a[0] <=> $b[0]);
        $dataAt = $at + 2 + 12 * count($entries) + 4;
        $dir = $p16(count($entries));
        $data = '';
        foreach ($entries as [$tag, $type, $v]) {
            [$count, $bytes] = $encode($type, $v);
            $dir .= $p16($tag) . $p16($type) . $p32($count);
            if (strlen($bytes) <= 4) {
                $dir .= str_pad($bytes, 4, "\0");
            } else {
                $dir .= $p32($dataAt + strlen($data));
                $data .= $bytes . (strlen($bytes) % 2 ? "\0" : '');
            }
        }
        return $dir . $p32(0) . $data;
    };
    $head = ($le ? 'II' : 'MM') . $p16(42) . $p32(8);
    if ($gps === null) {
        return $head . $ifd($ifd0, 8);
    }
    $first = $ifd(array_merge($ifd0, [[0x8825, 4, [0]]]), 8);      // sized with a placeholder pointer
    $first = $ifd(array_merge($ifd0, [[0x8825, 4, [8 + strlen($first)]]]), 8);
    return $head . $first . $ifd($gps, 8 + strlen($first));
}

/** What a phone writes: camera, model, orientation, time, and where it was. */
function camera_exif(int $orientation, bool $le = false): string
{
    return tiff([
        [0x010F, 2, 'TestCam'], [0x0110, 2, 'Phone X'], [0x0112, 3, [$orientation]], [0x0132, 2, '2026:11:24 09:15:00'],
    ], [
        [0x0001, 2, 'N'], [0x0002, 5, [[49, 1], [53, 1], [4512, 100]]],
        [0x0003, 2, 'W'], [0x0004, 5, [[97, 1], [8, 1], [3141, 100]]],
    ], $le);
}

/** An XMP packet with a location and a creator. */
function xmp_gps(): string
{
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF><rdf:Description exif:GPSLatitude="49,53.75N" '
         . 'exif:GPSLongitude="97,8.52W" dc:creator="Jane Source"/></rdf:RDF></x:xmpmeta>';
}

/**
 * A Content Credentials (C2PA) manifest store as image generators embed
 * it: a JUMBF superbox labelled c2pa, here carrying a claim about the tool.
 */
function c2pa_box(): string
{
    $desc = 'jumd' . "c2pa\x00\x11\x00\x10\x80\x00\x00\xAA\x00\x38\x9B\x71" . "\x03" . "c2pa\0";
    $claim = 'json' . '{"claim_generator":"GenTool/2.1","assertions":["c2pa.actions.created"]}';
    return pack('N', 8 + 4 + strlen($desc) + 4 + strlen($claim)) . 'jumb'
         . pack('N', 4 + strlen($desc)) . $desc . pack('N', 4 + strlen($claim)) . $claim;
}

/** One JPEG marker segment. */
function seg(int $code, string $data): string
{
    return "\xFF" . chr($code) . pack('n', 2 + strlen($data)) . $data;
}

/** Insert segments after the JPEG's JFIF block (or straight after SOI). */
function after_app0(string $jpeg, string $insert): string
{
    $at = substr($jpeg, 2, 2) === "\xFF\xE0" ? 4 + unpack('n', substr($jpeg, 4, 2))[1] : 2;
    return substr($jpeg, 0, $at) . $insert . substr($jpeg, $at);
}

/** One PNG chunk, with its CRC. */
function chunk(string $type, string $data): string
{
    return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
}

/** A JPEG straight off a phone: EXIF with GPS (orientation 6) and XMP with GPS. */
function phone_jpeg(): string
{
    return after_app0(render('imagejpeg', picture(), null, 90),
        seg(0xE1, "Exif\0\0" . camera_exif(6))
        . seg(0xE1, "http://ns.adobe.com/xap/1.0/\0" . xmp_gps()));
}

/** The same photo cut off halfway through its compressed data. */
function truncated_jpeg(): string
{
    $jpeg = phone_jpeg();
    $sos = strpos($jpeg, "\xFF\xDA");
    return substr($jpeg, 0, intdiv($sos + strlen($jpeg), 2));
}

/** Same size, and every third pixel identical. */
function same_pixels(string $a, string $b): bool
{
    $ia = @imagecreatefromstring($a);
    $ib = @imagecreatefromstring($b);
    if (!$ia || !$ib || imagesx($ia) !== imagesx($ib) || imagesy($ia) !== imagesy($ib)) {
        return false;
    }
    for ($y = 0; $y < imagesy($ia); $y += 3) {
        for ($x = 0; $x < imagesx($ia); $x += 3) {
            if (imagecolorat($ia, $x, $y) !== imagecolorat($ib, $x, $y)) {
                return false;
            }
        }
    }
    return true;
}

/** What PHP's own EXIF reader finds in an image (empty without the extension). */
function exif_of(string $bytes, string $mime): array
{
    if (!function_exists('exif_read_data')) {
        return [];
    }
    return (array) (@exif_read_data('data://' . $mime . ';base64,' . base64_encode($bytes)) ?: []);
}
