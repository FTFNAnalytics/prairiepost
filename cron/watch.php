<?php
/**
 * The watch — the hub's own five-minute patrol (via cron/run.php watch).
 * It checks what a person would check: do all nine domains answer, are
 * the TLS certificates comfortably far from expiry, are the cron jobs
 * actually running, is the wire fresh, is the nightly backup recent, is
 * anyone hammering the sign-in form. The findings land in one snapshot
 * setting (the dashboard reads it) and, when something is wrong and an
 * alert address is configured, one damped email — never a flood.
 *
 * Run from the shell:  PP_SITE=civismedia php cron/run.php watch
 */
if (!defined('PP_ROOT')) {
    require dirname(__DIR__) . '/app/bootstrap.php';
}
if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}
if (!pp_is_hub()) {
    exit("The watch runs on the hub — PP_SITE=civismedia.\n");
}

$problems = [];
$snapshot = ['at' => now(), 'domains' => [], 'certs' => [], 'jobs' => [], 'backup' => null, 'login_failures' => 0, 'wire_fresh' => true];

/* -- 1 · Every masthead answers ------------------------------------------ */
$domains = [];
foreach (db()->query('SELECT slug, domain FROM sites ORDER BY id') as $site) {
    if (trim((string) $site['domain']) !== '') {
        $domains[$site['slug']] = trim((string) $site['domain']);
    }
}
foreach ($domains as $slug => $domain) {
    $code = 0;
    $ch = curl_init("https://$domain/");
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => false, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'CivisWatch/1.0 (+network self-check)',
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $snapshot['domains'][$domain] = $code;
    if ($code !== 200) {
        $problems[] = "$domain answers $code";
    }

    // Certificate expiry, while we're here (best effort, never a fatal).
    $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'SNI_enabled' => true, 'verify_peer' => false, 'verify_peer_name' => false]]);
    $sock = @stream_socket_client("ssl://$domain:443", $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $ctx);
    if ($sock) {
        $cert = stream_context_get_params($sock)['options']['ssl']['peer_certificate'] ?? null;
        if ($cert && ($parsed = openssl_x509_parse($cert))) {
            $days = (int) floor((($parsed['validTo_time_t'] ?? 0) - time()) / 86400);
            $snapshot['certs'][$domain] = $days;
            if ($days < 14) {
                $problems[] = "$domain certificate expires in {$days}d";
            }
        }
        fclose($sock);
    }
}

/* -- 2 · The crons are actually running ----------------------------------- */
$expect = ['monitor' => 2 * 3600 + 900, 'agents' => 35 * 60, 'analytics' => 26 * 3600];
$latest = pp_ops_latest();
foreach ($expect as $job => $maxAge) {
    $run = $latest[$job] ?? null;
    $age = $run ? time() - strtotime((string) $run['started_at']) : null;
    $snapshot['jobs'][$job] = [
        'ok'  => $run ? (bool) $run['ok'] : null,
        'age' => $age,
    ];
    if (!$run) {
        $problems[] = "cron '$job' has never run through the wrapper";
    } elseif ($age > $maxAge) {
        $problems[] = "cron '$job' last ran " . round($age / 3600, 1) . "h ago";
    } elseif (!$run['ok']) {
        $problems[] = "cron '$job' failed on its last run";
    }
}

/* -- 3 · The wire is fresh (covers all the papers' fetch crons at once) --- */
$last = (string) (db()->query('SELECT MAX(fetched_at) FROM news_items')->fetchColumn() ?: '');
if ($last !== '' && strtotime($last) < time() - 26 * 3600) {
    $snapshot['wire_fresh'] = false;
    $problems[] = 'no paper has fetched wire items in over a day';
}

/* -- 4 · Last night's backup happened ------------------------------------- */
$stateFile = setting('backup_state_file', '/var/backups/civis/state.json');
if (is_readable($stateFile)) {
    $state = json_decode((string) file_get_contents($stateFile), true) ?: [];
    // finished_epoch is timezone-proof; finished_at is only the fallback
    // (and can skew when the box clock and app timezone disagree).
    $age = isset($state['finished_epoch']) ? max(0, time() - (int) $state['finished_epoch'])
         : (isset($state['finished_at']) ? max(0, time() - strtotime((string) $state['finished_at'])) : null);
    $snapshot['backup'] = ['ok' => (bool) ($state['ok'] ?? false), 'age' => $age, 'db_bytes' => $state['db_bytes'] ?? null];
    if (empty($state['ok'])) {
        $problems[] = 'the last backup FAILED — see /var/log/civis/backup.log';
    } elseif ($age !== null && $age > 26 * 3600) {
        $problems[] = 'no backup in ' . round($age / 3600) . 'h';
    }
}
// No state file = backups not set up yet; the dashboard says so quietly.

/* -- 5 · Someone hammering the door ---------------------------------------- */
$stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE succeeded = 0 AND created_at > ?');
$stmt->execute([date('Y-m-d H:i:s', time() - 3600)]);
$failures = (int) $stmt->fetchColumn();
$snapshot['login_failures'] = $failures;
if ($failures >= 30) {
    $problems[] = "$failures failed sign-ins in the last hour";
}

/* -- Report ----------------------------------------------------------------- */
set_setting('ops_snapshot', json_encode($snapshot, JSON_UNESCAPED_SLASHES));

foreach ($snapshot['domains'] as $d => $c) {
    echo str_pad((string) $c, 4), $d, isset($snapshot['certs'][$d]) ? '  cert ' . $snapshot['certs'][$d] . 'd' : '', "\n";
}
if ($problems) {
    // ERROR lines make run.php file this pass as failed — by design.
    foreach ($problems as $p) {
        echo "ERROR $p\n";
    }
} else {
    echo "all quiet: " . count($domains) . " domains up, crons on time\n";
}

/* -- One damped alert email, if configured ---------------------------------- */
$to = trim(setting('ops_alert_email'));
if ($problems && $to !== '') {
    $lastAlert = (int) (setting('ops_last_alert') ?: 0);
    if (time() - $lastAlert > 6 * 3600) {
        $body = '<p>The Civis Media watch found:</p><ul>';
        foreach ($problems as $p) {
            $body .= '<li>' . e($p) . '</li>';
        }
        $body .= '</ul><p>Details: <a href="' . e(site_url()) . '/admin/ops.php">the ops page</a>. '
               . 'This alert repeats at most every six hours while trouble persists.</p>';
        $err = pp_send_mail($to, '[Civis watch] ' . count($problems) . ' problem(s) on the network', $body);
        if ($err === null) {
            set_setting('ops_last_alert', (string) time());
            echo "alert emailed to $to\n";
        } else {
            echo "alert email failed: $err\n";
        }
    } else {
        echo "alert damped (last sent " . round((time() - $lastAlert) / 60) . "m ago)\n";
    }
}
