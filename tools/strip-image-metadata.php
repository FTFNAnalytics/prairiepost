<?php
/**
 * Strip embedded metadata (GPS location, camera, time, comments) from the
 * images already in /uploads: the ones stored before every upload path
 * started doing it itself (app/imagemeta.php).
 *
 *     php tools/strip-image-metadata.php              dry run: report, change nothing
 *     php tools/strip-image-metadata.php --apply      rewrite the files that carry metadata
 *     php tools/strip-image-metadata.php --dir=PATH   another directory (default: this release's uploads/)
 *
 * Lossless and safe to re-run: the compressed image data is copied byte for
 * byte, and a second run finds nothing left to do. Each rewrite is checked
 * first (the result still decodes, as the same type and size) and lands
 * atomically: a temporary file in the same directory, given the original's
 * owner, group and mode, renamed over it. A file the parser cannot walk is
 * reported and left exactly as it is. Needs no database and no config.
 *
 * Exit codes: 0 done, or nothing to do; 1 at least one file could not be
 * rewritten (its original is untouched); 64 usage.
 */
if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}

define('PP_ROOT', dirname(__DIR__));
require PP_ROOT . '/app/imagemeta.php';

$apply = false;
$dir = PP_ROOT . '/uploads';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } elseif (str_starts_with($arg, '--dir=')) {
        $dir = substr($arg, 6);
    } else {
        fwrite(STDERR, "Usage: php tools/strip-image-metadata.php [--apply] [--dir=PATH]\n");
        exit(64);
    }
}
$root = realpath($dir);   // uploads/ is a symlink to the shared directory in a release
if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "No such directory: $dir\n");
    exit(64);
}

$paths = [];
$walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($walk as $file) {
    if ($file->isFile() && !$file->isLink() && preg_match('/\.(jpe?g|png|webp|gif)$/i', $file->getFilename()) === 1) {
        $paths[] = $file->getPathname();
    }
}
sort($paths);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$n = ['images' => 0, 'with' => 0, 'gps' => 0, 'cleaned' => 0, 'unparsed' => 0, 'failed' => 0];
$asRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;
foreach ($paths as $path) {
    $rel = substr($path, strlen($root) + 1);
    $bytes = file_get_contents($path);
    if ($bytes === false) {
        $n['failed']++;
        echo "FAIL  $rel: could not be read\n";
        continue;
    }
    $mime = (string) $finfo->buffer($bytes);
    if (!in_array($mime, PP_IMAGE_MIMES, true)) {
        continue;   // named like an image but isn't one: not this tool's business
    }
    $n['images']++;
    [$clean, $removed] = pp_image_clean($bytes, $mime);
    if ($clean === null) {
        $n['unparsed']++;
        echo "SKIP  $rel: could not be parsed, so it was left exactly as it is\n";
        continue;
    }
    if ($removed === []) {
        continue;
    }
    $n['with']++;
    if (preg_grep('/GPS/', $removed)) {
        $n['gps']++;
    }
    echo ($apply ? 'CLEAN ' : 'WOULD ') . "$rel: " . implode(', ', array_unique($removed)) . "\n";
    if (!$apply) {
        continue;
    }
    $stat = stat($path);
    $tmp = dirname($path) . '/.pp-strip-' . bin2hex(random_bytes(6));
    $written = file_put_contents($tmp, $clean) === strlen($clean)
        && chmod($tmp, $stat['mode'] & 07777)
        && (!$asRoot || (chown($tmp, $stat['uid']) && chgrp($tmp, $stat['gid'])))
        && rename($tmp, $path);
    if (!$written) {
        @unlink($tmp);
        $n['failed']++;
        echo "FAIL  $rel: the cleaned file could not be written; the original is untouched\n";
        continue;
    }
    $n['cleaned']++;
}

echo "\n{$n['images']} image(s) in $root: {$n['with']} carried embedded metadata"
   . ($n['with'] ? " ({$n['gps']} with a GPS location)" : '') . ".\n";
if ($apply) {
    echo "Cleaned {$n['cleaned']}.\n";
} elseif ($n['with']) {
    echo "Dry run: nothing changed. Run again with --apply to clean them.\n";
}
if ($n['unparsed']) {
    echo "{$n['unparsed']} could not be parsed and were left as they are (listed as SKIP above).\n";
}
if ($n['failed']) {
    echo "{$n['failed']} could not be rewritten (listed as FAIL above); their originals are untouched.\n";
}
exit($n['failed'] ? 1 : 0);
