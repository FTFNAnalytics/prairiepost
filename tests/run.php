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
$skips = 0;
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
        continue;
    }
    // A passing file can still have skipped part of itself, and says so on
    // a line that starts with SKIP. A database-backed file also prints an
    // "engine:" line naming the server it really ran on. Both used to be
    // thrown away with the rest of a passing file's output, so a run could
    // read as fully green while whole cases had not run.
    foreach ($lines as $l) {
        $isSkip = str_starts_with($l, 'SKIP');
        if ($isSkip) {
            $skips++;
        }
        if ($isSkip || str_starts_with($l, 'engine:')) {
            echo "    $l\n";
        }
    }
}
echo $fail ? "\n$fail file(s) FAILED\n" : "\nall " . count($files) . " test files pass\n";
if ($skips > 0) {
    echo "$skips SKIP line(s) above: those parts did not run in this environment\n";
}
exit($fail ? 1 : 0);
