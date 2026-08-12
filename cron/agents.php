<?php
/**
 * The agent runner (hub, every ten minutes). Claims queued tasks one at a
 * time with a guarded UPDATE — two overlapping runs can never execute the
 * same task — hands each to its handler, and parks the proposal in
 * needs_review for an editor. Failures land as failed with the reason in
 * the log; nothing retries silently.
 *
 * Run from the shell:  PP_SITE=civismedia php cron/agents.php
 * Or over HTTP:        /cron/agents.php?key=CRON_SECRET
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/agents.php';

if (PHP_SAPI !== 'cli') {
    $key = (string) ($_GET['key'] ?? '');
    $secret = setting('cron_secret');
    if ($secret === '' || !hash_equals($secret, $key)) {
        http_response_code(403);
        exit("Missing or wrong key. The cron URL, secret included, is shown in Settings.\n");
    }
    header('Content-Type: text/plain; charset=utf-8');
}
if (!pp_is_hub()) {
    exit("The agent desk runs on the hub — PP_SITE=civismedia (or the hub host).\n");
}

echo pp_agents_process(10) . "\n";
