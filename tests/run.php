<?php
/**
 * The test harness:  php tests/run.php
 *
 * Every *.test.php file in this directory runs in its own PHP process (so
 * shims and fixtures can't leak between cases) and passes by exiting 0.
 * No config.php needed and the real database is never involved — DB cases
 * build a throwaway in-memory SQLite from the app's own DDL.
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

$fail = 0;
$files = glob(__DIR__ . '/*.test.php') ?: [];
sort($files);
foreach ($files as $file) {
    $name = basename($file, '.test.php');
    $lines = [];
    $exit = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $lines, $exit);
    printf("%-14s %s\n", $name, $exit === 0 ? 'PASS' : 'FAIL');
    if ($exit !== 0) {
        $fail++;
        foreach ($lines as $l) {
            echo "    $l\n";
        }
    }
}
echo $fail ? "\n$fail file(s) FAILED\n" : "\nall " . count($files) . " test files pass\n";
exit($fail ? 1 : 0);
