<?php
/**
 * Embedded metadata never reaches /uploads.
 *
 * A phone photo carries EXIF: the GPS position it was taken at, the
 * device, the time, often a thumbnail of the uncropped original. Until
 * this file existed every upload path stored the bytes exactly as they
 * arrived, so a published photo also published where it was taken. For a
 * newsroom that is a source-protection failure, not a tidiness one.
 *
 * pp_image_strip_metadata() rewrites the container without it, and does
 * so losslessly: the compressed image data is copied byte for byte (no
 * re-encode, so no quality loss and no dependency on GD), and only what a
 * picture needs in order to look right survives:
 *
 *   JPEG  JFIF (APP0), the colour profile (APP2 ICC_PROFILE), Adobe's
 *         colour-transform marker (APP14) and every coding segment.
 *         Dropped: EXIF and XMP (APP1), IPTC (APP13), MPF and every other
 *         APPn, comments, and anything after the end-of-image marker
 *         (phones append gain maps and depth maps there, each with EXIF
 *         of its own).
 *   PNG   the critical chunks and the colour, transparency, display and
 *         animation ones. Dropped: eXIf, tEXt, zTXt, iTXt, tIME, anything
 *         unknown, and anything after IEND.
 *   WebP  image, alpha, animation and colour-profile chunks. Dropped:
 *         EXIF, XMP and anything unknown; the VP8X flags follow.
 *   GIF   image, graphic-control and plain-text blocks, and the looping
 *         and colour-profile application blocks. Dropped: comments, XMP
 *         and every other application block, and anything after the
 *         trailer.
 *
 * Orientation is the one EXIF field that changes how a photo looks, so a
 * non-default orientation is carried over alone, in a minimal EXIF block
 * of its own. A file the parser cannot walk from end to end is refused
 * (null): an image is never stored unexamined.
 *
 * Content Credentials (C2PA: JPEG APP11, PNG caBX, the WebP C2PA chunk)
 * go too, and are named in the report. A manifest can embed the same EXIF
 * and creator assertions, and its hash covers the rest of the file, so
 * once anything else is removed it would no longer validate and would read
 * as tampered. Saying a graphic was generated is the visible credit
 * line's job (image_credit).
 */

/** The four formats the upload paths accept. */
const PP_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

/**
 * Strip embedded metadata from an image's bytes. Returns
 * ['bytes' => the cleaned file, 'removed' => labels of what was taken
 * out], or null when the bytes are not a well-formed file of $mime.
 */
function pp_image_strip_metadata(string $bytes, string $mime): ?array
{
    return match ($mime) {
        'image/jpeg' => pp_jpeg_strip($bytes),
        'image/png'  => pp_png_strip($bytes),
        'image/webp' => pp_webp_strip($bytes),
        'image/gif'  => pp_gif_strip($bytes),
        default      => null,
    };
}

/**
 * The store paths' gate: strip the metadata, then make sure what is left
 * is still the same picture (it decodes, with the same type and size).
 * Returns [clean bytes, removed labels, null] or [null, [], reason].
 */
function pp_image_clean(string $bytes, string $mime): array
{
    $refused = 'the image could not be read closely enough to remove its embedded metadata '
             . '(location, camera details); re-save it as a new file and try again';
    $result = pp_image_strip_metadata($bytes, $mime);
    if ($result === null) {
        return [null, [], $refused];
    }
    $before = @getimagesizefromstring($bytes);
    $after = @getimagesizefromstring($result['bytes']);
    if ($after === false || ($before !== false
        && [$before[0], $before[1], $before[2]] !== [$after[0], $after[1], $after[2]])) {
        return [null, [], $refused];
    }
    return [$result['bytes'], $result['removed'], null];
}

/* --- EXIF (TIFF) --------------------------------------------------------- */

/**
 * The entries of a TIFF structure's first directory (IFD0), as
 * tag => [type, count, first SHORT of the value field]. Null when the
 * header or the directory doesn't fit inside the bytes.
 */
function pp_tiff_ifd0(string $tiff): ?array
{
    $n = strlen($tiff);
    if ($n < 8) {
        return null;
    }
    $order = substr($tiff, 0, 2);
    if ($order !== 'II' && $order !== 'MM') {
        return null;
    }
    $le = $order === 'II';
    $u16 = fn (int $at): ?int => $at >= 0 && $at + 2 <= $n ? unpack($le ? 'v' : 'n', substr($tiff, $at, 2))[1] : null;
    $u32 = fn (int $at): ?int => $at >= 0 && $at + 4 <= $n ? unpack($le ? 'V' : 'N', substr($tiff, $at, 4))[1] : null;
    if ($u16(2) !== 42) {
        return null;
    }
    $ifd = $u32(4);
    $count = $ifd === null ? null : $u16($ifd);
    if ($ifd === null || $ifd < 8 || $count === null || $ifd + 2 + 12 * $count > $n) {
        return null;
    }
    $entries = [];
    for ($i = 0; $i < $count; $i++) {
        $at = $ifd + 2 + 12 * $i;
        $entries[$u16($at)] = [$u16($at + 2), $u32($at + 4), $u16($at + 8)];
    }
    return $entries;
}

/** EXIF orientation (1–8) from a TIFF structure, or null when it has none. */
function pp_tiff_orientation(string $tiff): ?int
{
    $tag = pp_tiff_ifd0($tiff)[0x0112] ?? null;
    if ($tag === null || $tag[0] !== 3 || $tag[1] < 1) {
        return null;
    }
    return $tag[2] >= 1 && $tag[2] <= 8 ? $tag[2] : null;
}

/** Does this TIFF structure point at a GPS directory? */
function pp_tiff_has_gps(string $tiff): bool
{
    return isset(pp_tiff_ifd0($tiff)[0x8825]);
}

/** A minimal TIFF structure holding nothing but an orientation. */
function pp_tiff_orientation_only(int $orientation): string
{
    return "MM\x00\x2A" . pack('N', 8)                  // big-endian, IFD0 at offset 8
         . pack('n', 1)                                 // one entry
         . pack('nnN', 0x0112, 3, 1) . pack('nn', $orientation, 0)
         . pack('N', 0);                                // no further directory
}

/**
 * Is this exactly the block pp_tiff_orientation_only() writes? It carries
 * nothing but the orientation that is kept, so there is nothing to report,
 * and a second pass over a cleaned file finds nothing to do.
 */
function pp_tiff_is_orientation_only(string $tiff): bool
{
    $orientation = pp_tiff_orientation($tiff);
    return $orientation !== null && $orientation !== 1 && $tiff === pp_tiff_orientation_only($orientation);
}

/** The label for an EXIF block, saying whether it held a location. */
function pp_exif_label(string $tiff): string
{
    return pp_tiff_has_gps($tiff) ? 'EXIF (with GPS location)' : 'EXIF';
}

/** The label for an XMP packet, saying whether it held a location. */
function pp_xmp_label(string $xmp): string
{
    return stripos($xmp, 'GPSLatitude') !== false || stripos($xmp, 'GPSLongitude') !== false
        ? 'XMP (with GPS location)' : 'XMP';
}

/* --- JPEG ----------------------------------------------------------------- */

function pp_jpeg_strip(string $b): ?array
{
    $n = strlen($b);
    if ($n < 4 || substr($b, 0, 2) !== "\xFF\xD8") {
        return null;
    }
    $parts = ["\xFF\xD8"];
    $removed = [];
    $orientation = null;
    $sawFrame = $sawScan = $ended = false;
    $pos = 2;
    while ($pos < $n) {
        if ($b[$pos] !== "\xFF") {
            return null;
        }
        while ($pos < $n && $b[$pos] === "\xFF") {
            $pos++;                                     // fill bytes before a marker
        }
        if ($pos >= $n) {
            return null;
        }
        $code = ord($b[$pos++]);
        if ($code === 0xD9) {
            $parts[] = "\xFF\xD9";
            $ended = true;
            break;
        }
        if ($code === 0x00 || $code === 0xD8) {
            return null;
        }
        if (($code >= 0xD0 && $code <= 0xD7) || $code === 0x01) {
            $parts[] = "\xFF" . chr($code);             // markers without a length
            continue;
        }
        if ($pos + 2 > $n) {
            return null;
        }
        $len = unpack('n', substr($b, $pos, 2))[1];
        if ($len < 2 || $pos + $len > $n) {
            return null;
        }
        $data = substr($b, $pos + 2, $len - 2);
        $segment = "\xFF" . chr($code) . substr($b, $pos, $len);
        $pos += $len;

        if ($code >= 0xE0 && $code <= 0xEF) {          // APPn
            if (($code === 0xE0 && str_starts_with($data, "JFIF\0"))
                || ($code === 0xE2 && str_starts_with($data, "ICC_PROFILE\0"))
                || ($code === 0xEE && str_starts_with($data, 'Adobe'))) {
                $parts[] = $segment;
            } elseif ($code === 0xE1 && str_starts_with($data, "Exif\0\0")) {
                $tiff = substr($data, 6);
                $orientation ??= pp_tiff_orientation($tiff);
                if (!pp_tiff_is_orientation_only($tiff)) {
                    $removed[] = pp_exif_label($tiff);
                }
            } elseif ($code === 0xE1 && str_contains(substr($data, 0, 64), 'ns.adobe.com/x')) {   // XMP and extended XMP
                $removed[] = pp_xmp_label($data);
            } elseif ($code === 0xED) {
                $removed[] = 'IPTC';
            } elseif ($code === 0xE2 && str_starts_with($data, "MPF\0")) {
                $removed[] = 'MPF (an index of extra images)';
            } elseif ($code === 0xEB && str_contains(substr($data, 0, 64), 'c2pa')) {   // JUMBF boxes
                $removed[] = 'Content Credentials (C2PA)';
            } else {
                $removed[] = sprintf('APP%d block', $code - 0xE0);
            }
            continue;
        }
        if ($code === 0xFE) {
            $removed[] = 'comment';
            continue;
        }
        $parts[] = $segment;
        if ($code >= 0xC0 && $code <= 0xCF && !in_array($code, [0xC4, 0xC8, 0xCC], true)) {
            $sawFrame = true;
        }
        if ($code === 0xDA) {
            // Entropy-coded data runs to the next marker that isn't a
            // stuffed 0x00, a restart marker or another fill byte.
            $sawScan = true;
            $start = $pos;
            while (true) {
                $ff = strpos($b, "\xFF", $pos);
                if ($ff === false || $ff + 1 >= $n) {
                    return null;                        // the scan never ends
                }
                $next = ord($b[$ff + 1]);
                if ($next === 0x00 || $next === 0xFF || ($next >= 0xD0 && $next <= 0xD7)) {
                    $pos = $ff + 1;
                    continue;
                }
                break;
            }
            $parts[] = substr($b, $start, $ff - $start);
            $pos = $ff;
        }
    }
    if (!$ended || !$sawFrame || !$sawScan) {
        return null;
    }
    if ($pos < $n) {
        $removed[] = 'data after the end of the image';
    }
    if ($orientation !== null && $orientation !== 1) {
        $tiff = pp_tiff_orientation_only($orientation);
        $app1 = "\xFF\xE1" . pack('n', 2 + 6 + strlen($tiff)) . "Exif\0\0" . $tiff;
        // After JFIF when the file opens with it, otherwise straight after SOI.
        $at = isset($parts[1]) && str_starts_with($parts[1], "\xFF\xE0") ? 2 : 1;
        array_splice($parts, $at, 0, [$app1]);
    }
    return ['bytes' => implode('', $parts), 'removed' => $removed];
}

/* --- PNG ------------------------------------------------------------------ */

function pp_png_strip(string $b): ?array
{
    $n = strlen($b);
    if ($n < 8 || substr($b, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        return null;
    }
    $keep = ['IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'cHRM', 'gAMA', 'iCCP', 'sBIT', 'sRGB',
             'cICP', 'mDCv', 'cLLi', 'bKGD', 'pHYs', 'acTL', 'fcTL', 'fdAT'];
    $chunks = [];
    $removed = [];
    $orientation = null;
    $ended = false;
    $pos = 8;
    while ($pos + 12 <= $n) {
        $len = unpack('N', substr($b, $pos, 4))[1];
        $type = substr($b, $pos + 4, 4);
        if (preg_match('/^[A-Za-z]{4}$/', $type) !== 1 || $len > $n - $pos - 12) {
            return null;
        }
        if ($chunks === [] && $type !== 'IHDR') {
            return null;
        }
        $data = substr($b, $pos + 8, $len);
        $whole = substr($b, $pos, 12 + $len);
        $pos += 12 + $len;
        if (in_array($type, $keep, true)) {
            $chunks[] = $whole;
            if ($type === 'IEND') {
                $ended = true;
                break;
            }
            continue;
        }
        if ($type === 'eXIf') {
            $orientation ??= pp_tiff_orientation($data);
            if (pp_tiff_is_orientation_only($data)) {
                continue;
            }
        }
        $removed[] = match ($type) {
            'eXIf'                 => pp_exif_label($data),
            'tEXt', 'zTXt', 'iTXt' => str_starts_with($data, "XML:com.adobe.xmp\0") ? pp_xmp_label($data) : 'text (' . explode("\0", $data, 2)[0] . ')',
            'tIME'                 => 'timestamp',
            'caBX'                 => 'Content Credentials (C2PA)',
            default                => "chunk $type",
        };
    }
    if (!$ended) {
        return null;
    }
    if ($pos < $n) {
        $removed[] = 'data after the end of the image';
    }
    if ($orientation !== null && $orientation !== 1) {
        $tiff = pp_tiff_orientation_only($orientation);
        $exif = pack('N', strlen($tiff)) . 'eXIf' . $tiff . pack('N', crc32('eXIf' . $tiff));
        array_splice($chunks, 1, 0, [$exif]);   // after IHDR, before any image data
    }
    return ['bytes' => "\x89PNG\r\n\x1a\n" . implode('', $chunks), 'removed' => $removed];
}

/* --- WebP ----------------------------------------------------------------- */

function pp_webp_strip(string $b): ?array
{
    $n = strlen($b);
    if ($n < 20 || substr($b, 0, 4) !== 'RIFF' || substr($b, 8, 4) !== 'WEBP') {
        return null;
    }
    $end = 8 + unpack('V', substr($b, 4, 4))[1];
    if ($end > $n) {
        return null;
    }
    $keep = ['VP8X', 'VP8 ', 'VP8L', 'ALPH', 'ANIM', 'ANMF', 'ICCP'];
    $chunks = [];
    $removed = [];
    $orientation = null;
    $hasImage = false;
    $pos = 12;
    while ($pos + 8 <= $end) {
        $fourcc = substr($b, $pos, 4);
        $size = unpack('V', substr($b, $pos + 4, 4))[1];
        if ($size > $end - $pos - 8) {
            return null;
        }
        $data = substr($b, $pos + 8, $size);
        $pos += 8 + $size + ($size & 1);
        if (in_array($fourcc, $keep, true)) {
            $chunks[] = [$fourcc, $fourcc . pack('V', $size) . $data . ($size & 1 ? "\0" : '')];
            $hasImage = $hasImage || in_array($fourcc, ['VP8 ', 'VP8L', 'ANMF'], true);
            continue;
        }
        if ($fourcc === 'EXIF') {
            $tiff = str_starts_with($data, "Exif\0\0") ? substr($data, 6) : $data;
            $orientation ??= pp_tiff_orientation($tiff);
            if (!pp_tiff_is_orientation_only($tiff)) {
                $removed[] = pp_exif_label($tiff);
            }
        } elseif ($fourcc === 'XMP ') {
            $removed[] = pp_xmp_label($data);
        } elseif ($fourcc === 'C2PA') {
            $removed[] = 'Content Credentials (C2PA)';
        } else {
            $removed[] = 'chunk ' . rtrim($fourcc);
        }
    }
    if (!$hasImage) {
        return null;
    }
    if ($end < $n) {
        $removed[] = 'data after the end of the image';
    }
    $body = '';
    if ($chunks[0][0] === 'VP8X') {
        $vp8x = $chunks[0][1];
        if (strlen($vp8x) < 18) {
            return null;
        }
        $flags = ord($vp8x[8]) & ~0x0C;                // EXIF (0x08) and XMP (0x04) off
        $exif = '';
        if ($orientation !== null && $orientation !== 1) {
            $tiff = pp_tiff_orientation_only($orientation);
            $exif = 'EXIF' . pack('V', strlen($tiff)) . $tiff . (strlen($tiff) & 1 ? "\0" : '');
            $flags |= 0x08;
        }
        $vp8x[8] = chr($flags);
        $chunks[0][1] = $vp8x;
        $body = implode('', array_column($chunks, 1)) . $exif;   // EXIF follows the image data
    } else {
        // The simple format cannot carry metadata, or an orientation.
        $body = implode('', array_column($chunks, 1));
    }
    return ['bytes' => 'RIFF' . pack('V', 4 + strlen($body)) . 'WEBP' . $body, 'removed' => $removed];
}

/* --- GIF ------------------------------------------------------------------ */

function pp_gif_strip(string $b): ?array
{
    $n = strlen($b);
    if ($n < 13 || !in_array(substr($b, 0, 6), ['GIF87a', 'GIF89a'], true)) {
        return null;
    }
    $packed = ord($b[10]);
    $pos = 13 + (($packed & 0x80) ? 3 * (2 << ($packed & 0x07)) : 0);
    if ($pos > $n) {
        return null;
    }
    $out = substr($b, 0, $pos);
    $removed = [];
    // Data sub-blocks: [size][bytes]... ended by a zero size. Returns the
    // offset just past the terminator, or null when they run off the end.
    $subBlocks = function (int $at) use ($b, $n): ?int {
        while ($at < $n) {
            $size = ord($b[$at]);
            $at += 1 + $size;
            if ($size === 0) {
                return $at <= $n ? $at : null;
            }
        }
        return null;
    };
    $ended = false;
    while ($pos < $n) {
        $intro = ord($b[$pos]);
        if ($intro === 0x3B) {
            $out .= ';';
            $pos++;
            $ended = true;
            break;
        }
        if ($intro === 0x2C) {                           // an image
            if ($pos + 10 > $n) {
                return null;
            }
            $local = ord($b[$pos + 9]);
            $dataAt = $pos + 10 + (($local & 0x80) ? 3 * (2 << ($local & 0x07)) : 0) + 1;
            $next = $dataAt <= $n ? $subBlocks($dataAt) : null;
            if ($next === null) {
                return null;
            }
            $out .= substr($b, $pos, $next - $pos);
            $pos = $next;
            continue;
        }
        if ($intro !== 0x21 || $pos + 2 > $n) {
            return null;
        }
        $label = ord($b[$pos + 1]);
        $next = $subBlocks($pos + 2);
        if ($next === null) {
            return null;
        }
        $block = substr($b, $pos, $next - $pos);
        $pos = $next;
        if ($label === 0xF9 || $label === 0x01) {        // graphic control, plain text
            $out .= $block;
        } elseif ($label === 0xFF) {
            $app = substr($block, 3, 11);
            if (in_array($app, ['NETSCAPE2.0', 'ANIMEXTS1.0', 'ICCRGBG1012'], true)) {
                $out .= $block;
            } else {
                $removed[] = str_starts_with($app, 'XMP Data') ? pp_xmp_label($block) : 'application block (' . rtrim(substr($app, 0, 8)) . ')';
            }
        } elseif ($label === 0xFE) {
            $removed[] = 'comment';
        } else {
            $removed[] = sprintf('extension 0x%02X', $label);
        }
    }
    if (!$ended) {
        return null;
    }
    if ($pos < $n) {
        $removed[] = 'data after the end of the image';
    }
    return ['bytes' => $out, 'removed' => $removed];
}
