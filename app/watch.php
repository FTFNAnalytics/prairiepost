<?php
/**
 * Parallel domain health prober for cron/watch.php.
 * Uses curl_multi with a bounded concurrency window so 50+ papers finish in a
 * couple of seconds instead of risking a 5-minute cron overrun.
 */

/**
 * Probe a list of domains concurrently and return [domain => http_status_code].
 * $domains may be associative ([slug => host]) or a flat list ([host, ...]).
 * Each entry may include an optional port (e.g. "127.0.0.1:8765").
 */
function pp_watch_probe_domains(array $domains, int $concurrency = 10, int $timeout = 12, string $scheme = 'https'): array
{
    $list = array_values(array_unique(array_filter(array_map(
        static fn ($d) => trim((string) $d),
        $domains
    ), static fn ($d) => $d !== '')));

    $results = [];
    foreach ($list as $d) {
        $results[$d] = 0;
    }
    if (!$list) {
        return $results;
    }

    $concurrency = max(1, min(32, $concurrency));
    $connectTimeout = min(8, max(2, $timeout));
    $mh = curl_multi_init();
    $activeHandles = []; // handleId => ['ch' => CurlHandle, 'domain' => string]
    $cursor = 0;
    $total = count($list);

    $enqueue = static function () use (&$cursor, $total, $list, $mh, &$activeHandles, $scheme, $timeout, $connectTimeout): void {
        $domain = $list[$cursor++];
        $ch = curl_init($scheme . '://' . $domain . '/');
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'CivisWatch/1.0 (+network self-check)',
        ]);
        curl_multi_add_handle($mh, $ch);
        $activeHandles[spl_object_id($ch)] = ['ch' => $ch, 'domain' => $domain];
    };

    while ($cursor < $total && count($activeHandles) < $concurrency) {
        $enqueue();
    }

    do {
        do {
            $status = curl_multi_exec($mh, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        while (($done = curl_multi_info_read($mh)) !== false) {
            $ch = $done['handle'];
            $hid = spl_object_id($ch);
            if (isset($activeHandles[$hid])) {
                $domain = $activeHandles[$hid]['domain'];
                $results[$domain] = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                unset($activeHandles[$hid]);
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($cursor < $total) {
                $enqueue();
                $running = 1;
            }
        }

        if ($running > 0) {
            $sel = curl_multi_select($mh, 0.5);
            if ($sel === -1) {
                usleep(10000);
            }
        }
    } while ($running > 0 || $activeHandles);

    curl_multi_close($mh);
    return $results;
}
