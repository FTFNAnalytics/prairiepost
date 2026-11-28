<?php
/** The Prairie Dispatch — newsletter signup endpoint and page. */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

require __DIR__ . '/app/newsletter.php';

/** Per-IP sliding-window rate limit for /subscribe (5 POSTs per 15 minutes per site). */
function pp_subscribe_ip_limited(int $siteId, string $ip, int $limit = 5, int $windowSec = 900): bool
{
    $envDir = in_array(PHP_SAPI, ['cli', 'cli-server'], true) ? (string) getenv('PP_SUBSCRIBE_RL_DIR') : '';
    if ($envDir !== '') {
        $dir = $envDir;
    } elseif (is_dir(PP_ROOT . '/data/cache') && is_writable(PP_ROOT . '/data/cache')) {
        $dir = PP_ROOT . '/data/cache/subscribe-rl';
    } else {
        $dir = rtrim(sys_get_temp_dir(), '/') . '/pp-subscribe-rl';
    }
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return false;
    }
    $key = hash('sha256', $siteId . '|' . ($ip !== '' ? $ip : 'unknown'));
    $path = $dir . '/' . $key . '.txt';
    $fp = @fopen($path, 'c+');
    if ($fp === false) {
        return false;
    }
    $limited = false;
    if (flock($fp, LOCK_EX)) {
        $now = time();
        $cutoff = $now - $windowSec;
        $raw = stream_get_contents($fp);
        $stamps = array_values(array_filter(
            array_map('intval', preg_split('/\R+/', trim((string) $raw)) ?: []),
            static fn (int $t): bool => $t > $cutoff
        ));
        if (count($stamps) >= $limit) {
            $limited = true;
        } else {
            $stamps[] = $now;
        }
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, implode("\n", $stamps) . "\n");
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $limited;
}

$rateLimited = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot: real readers never see or fill the hidden field.
    $honeypot = (string) ($_POST['website'] ?? '');
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $confirming = false;

    if ($honeypot === '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $siteId = current_site_id();
        $ip = pp_client_ip();

        // Two-layer flood protection: per-IP sliding window + per-site hourly cap.
        $siteStmt = db()->prepare('SELECT COUNT(*) FROM subscribers WHERE site_id = ? AND created_at > ?');
        $siteStmt->execute([$siteId, date('Y-m-d H:i:s', time() - 3600)]);
        $siteHourly = (int) $siteStmt->fetchColumn();

        if (pp_subscribe_ip_limited($siteId, $ip) || $siteHourly >= 60) {
            $rateLimited = true;
            http_response_code(429);
        } else {
            $stmt = db()->prepare('SELECT * FROM subscribers WHERE site_id = ? AND email = ?');
            $stmt->execute([$siteId, $email]);
            $existing = $stmt->fetch();

            // With mail configured, CASL-grade double opt-in; without it, the
            // form's submission is recorded as the consent.
            $doubleOptIn = pp_mail_configured();

            if ($existing) {
                if ($existing['status'] !== 'active') {
                    if ($doubleOptIn) {
                        $recent = !empty($existing['created_at']) && strtotime((string) $existing['created_at']) > time() - 300;
                        db()->prepare("UPDATE subscribers SET status = 'pending' WHERE id = ?")->execute([$existing['id']]);
                        if (!$recent) {
                            pp_send_confirmation($existing);
                        }
                        $confirming = true;
                    } else {
                        db()->prepare("UPDATE subscribers SET status = 'active', consent_note = 'web form (re-subscribed)' WHERE id = ?")
                            ->execute([$existing['id']]);
                    }
                }
            } else {
                $token = bin2hex(random_bytes(16));
                $status = $doubleOptIn ? 'pending' : 'active';
                $note = $doubleOptIn ? 'web form, confirmation email sent' : 'web form (single opt-in — mail not configured)';
                db()->prepare('INSERT INTO subscribers (site_id, email, status, token, consent_note, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$siteId, $email, $status, $token, $note, now()]);
                if ($doubleOptIn) {
                    pp_send_confirmation(['email' => $email, 'token' => $token]);
                    $confirming = true;
                }
            }
        }
    }
    if (!$rateLimited) {
        redirect(url('subscribe') . '?subscribed=1' . ($confirming ? '&confirm=1' : ''));
    }
}

page_header([
    'title' => setting('newsletter_heading', 'The 6 a.m.'),
    'description' => setting('newsletter_copy'),
]);
?>

<div class="pagehead wrap">
  <span class="pp-meta" style="color:#5A6A5C">Newsletter</span>
  <h1><?= e(setting('newsletter_heading', 'The 6 a.m.')) ?></h1>
  <p class="desc"><?= e(setting('newsletter_copy')) ?></p>
  <div class="pp-horizon"></div>
</div>

<div class="archive wrap">
  <?php if ($rateLimited): ?>
  <p class="pp-meta" role="alert" style="color:#9C3B22;margin-bottom:16px">Too many signup attempts from this connection. Please wait fifteen minutes and try again.</p>
  <?php endif; ?>
  <div style="max-width:420px"><?= signup_block() ?></div>
  <p class="pp-meta" style="margin-top:22px;color:#5A6A5C">One email a day. Unsubscribe any time by replying with the word “stop”.</p>
</div>

<?php page_footer(); ?>
