<?php
/**
 * The cron wrapper — every scheduled job runs through here so no failure
 * is silent. It runs the real script, captures its output and outcome, and
 * files both in ops_runs (which the hub dashboard and the watch read).
 * The output still reaches stdout, so shell redirects and MAILTO keep
 * working exactly as before.
 *
 *   PP_SITE=civismedia php cron/run.php monitor
 *   php cron/run.php fetch          (a paper's news pull)
 *
 * CLI only. Exit code 0 on success, 1 on failure — cron sees the truth.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

$jobs = [
    'fetch'     => 'fetch-news.php',
    'monitor'   => 'monitor.php',
    'agents'    => 'agents.php',
    'analytics' => 'analytics.php',
    'watch'     => 'watch.php',
];
$job = (string) ($argv[1] ?? '');
if (!isset($jobs[$job])) {
    fwrite(STDERR, "Usage: php cron/run.php <" . implode('|', array_keys($jobs)) . ">\n");
    exit(1);
}

$startedAt = now();
$t0 = microtime(true);

// The job runs as its own process: a fatal, a parse error, even an OOM in
// the job can't take the wrapper down with it — the failure gets recorded
// either way. PP_SITE and the rest of the environment carry through.
$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $jobs[$job]) . ' 2>&1';
$lines = [];
$exit = 0;
exec($cmd, $lines, $exit);
$output = implode("\n", $lines);
echo $output, $output === '' ? '' : "\n";

// Non-zero exit is a failure; so are printed ERROR/FATAL lines (the job
// scripts report per-feed and per-check trouble that way without dying).
$ok = $exit === 0 && !preg_match('/^(ERROR|FATAL)\b/m', $output);

$tail = trim(mb_substr(trim($output), -400));
$note = sprintf('%.1fs · exit %d · %s', microtime(true) - $t0, $exit, $tail === '' ? '(no output)' : $tail);
pp_ops_record($job, $ok, $note, $startedAt);

exit($ok ? 0 : 1);
