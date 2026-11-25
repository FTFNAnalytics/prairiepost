<?php
/**
 * Live weather for every paper (hub only, every 30 minutes).
 *
 * Reads each configured paper's city from Environment and Climate Change
 * Canada and stores it as that paper's `weather_live` setting
 * (app/weather.php). Run it through the wrapper so the dashboard and the
 * watch see each outcome:
 *
 *     PP_SITE=civismedia php cron/run.php weather
 *
 * One city failing is a WARN line: its papers keep their last reading,
 * which hides itself once it is three hours old. Every city failing is an
 * ERROR, which marks the run failed. CLI only.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!pp_is_hub()) {
    exit("The weather job runs on the hub: PP_SITE=civismedia php cron/run.php weather\n");
}

$started = microtime(true);
[$lines, $ok, $failed] = pp_weather_refresh_all();
foreach ($lines as $line) {
    echo $line, "\n";
}
if ($ok === 0 && $failed > 0) {
    echo "ERROR every city failed; no paper's weather was refreshed\n";
}
printf("----\n%d city(ies) refreshed, %d failed. %.1fs.\n", $ok, $failed, microtime(true) - $started);
exit($ok === 0 && $failed > 0 ? 1 : 0);
