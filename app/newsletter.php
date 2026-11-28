<?php
/**
 * The Prairie Dispatch — The 6 a.m.
 * Compiles the morning edition from the last 24 hours, renders it as
 * email-safe HTML in the brand's register, and sends it to the active list.
 */

require_once PP_ROOT . '/app/mailer.php';

/** Stories for this morning's edition: last 24 hours, hero first. */
function pp_edition_posts(): array
{
    $since = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $sql = 'SELECT ' . PP_POST_COLS . ' FROM posts p' . pp_site_join() . PP_POST_JOINS . '
            WHERE ' . pp_published_where() . ' AND p.published_at >= ?
            ORDER BY CASE WHEN ps.placement = \'hero\' THEN 0 ELSE 1 END, p.published_at DESC LIMIT 25';
    $stmt = db()->prepare($sql);
    $stmt->execute([now(), $since]);
    return pp_normalize_public_posts($stmt->fetchAll());
}

/** Compile today's edition. Returns [subject, html, post_count]. */
function pp_compile_edition(): array
{
    $posts = pp_edition_posts();
    $siteTitle = setting('site_title', 'The Prairie Dispatch');
    $dateLine = pp_date('l, F j, Y');
    $subject = setting('newsletter_heading', 'The 6 a.m.') . ' — ' . pp_date('l, M j')
        . ($posts ? ': ' . $posts[0]['title'] : '');

    $pal = pp_brand_palette();
    $ink = $pal['ink']; $paper = $pal['paper']; $board = $pal['board']; $sky = $pal['sky'];
    $muted = $pal['muted']; $red = $pal['red'];
    $head = "font-family:'Arial Narrow',Arial,Helvetica,sans-serif;font-weight:bold;color:$ink";
    $bodyF = "font-family:Georgia,'Times New Roman',serif;color:$ink";
    $mono = "font-family:'Courier New',Courier,monospace;letter-spacing:2px;text-transform:uppercase";

    $h = fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $link = fn (array $p) => site_url() . '/story/' . $p['slug'];

    $out  = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>' . $h($subject) . '</title></head>';
    $out .= "<body style=\"margin:0;padding:0;background:$paper;\">";
    $out .= "<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:$paper;\"><tr><td align=\"center\" style=\"padding:22px 12px;\">";
    $out .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">';

    // Masthead: the wordmark in type, on the horizon rule.
    $out .= "<tr><td style=\"$head;font-size:34px;letter-spacing:1px;padding:6px 0 8px;\">" . strtoupper($h($siteTitle)) . '</td></tr>';
    $out .= "<tr><td style=\"border-top:4px solid $ink;font-size:0;line-height:2px;\">&nbsp;</td></tr>";
    $out .= "<tr><td style=\"border-top:1px solid $board;padding:8px 0 20px;\">"
          . "<span style=\"$mono;font-size:10px;color:$muted;\">" . $h(setting('newsletter_heading', 'The 6 a.m.')) . ' &middot; ' . $h($dateLine) . '</span></td></tr>';

    if (!$posts) {
        $out .= "<tr><td style=\"$bodyF;font-size:16px;padding:6px 0 18px;\">No stories filed in the past day. The wire keeps watch; the next edition lands when the news does.</td></tr>";
    } else {
        // Hero.
        $heroPost = $posts[0];
        if (!empty($heroPost['category_name'])) {
            $deskColor = empty($heroPost['category_color_is_fill']) ? pp_desk_hex($heroPost['category_slug'] ?? null, $heroPost['category_color']) : $ink;
            $out .= "<tr><td style=\"$mono;font-size:10px;color:" . $h($deskColor) . ';padding:2px 0 6px;">' . $h($heroPost['category_name']) . '</td></tr>';
        }
        $out .= "<tr><td style=\"$head;font-size:26px;line-height:1.1;padding:0 0 8px;\"><a href=\"" . $h($link($heroPost)) . "\" style=\"color:$ink;text-decoration:none;\">" . $h($heroPost['title']) . '</a></td></tr>';
        if ($heroPost['lede']) {
            $out .= "<tr><td style=\"$bodyF;font-style:italic;font-size:16px;line-height:1.5;padding:0 0 8px;\">" . $h($heroPost['lede']) . '</td></tr>';
        }
        $out .= "<tr><td style=\"$mono;font-size:9.5px;color:$muted;padding:0 0 18px;\">" . dateline($heroPost) . '</td></tr>';

        // The rest, grouped by desk in desk order.
        $byDesk = [];
        foreach (array_slice($posts, 1) as $p) {
            $byDesk[$p['category_name'] ?? 'More from the paper'][] = $p;
        }
        foreach ($byDesk as $desk => $deskPosts) {
            $first = $deskPosts[0];
            $deskColor = (!empty($first['category_color']) && empty($first['category_color_is_fill'])) ? pp_desk_hex($first['category_slug'] ?? null, $first['category_color']) : $ink;
            $out .= "<tr><td style=\"border-top:2px solid $ink;padding:14px 0 4px;\"><span style=\"$mono;font-size:10px;color:" . $h($deskColor) . ';">' . $h($desk) . '</span></td></tr>';
            foreach ($deskPosts as $p) {
                $out .= "<tr><td style=\"padding:4px 0 2px;\"><a href=\"" . $h($link($p)) . "\" style=\"$head;font-size:17px;line-height:1.25;color:$ink;text-decoration:none;\">" . $h($p['title']) . '</a></td></tr>';
                if ($p['lede']) {
                    $out .= "<tr><td style=\"$bodyF;font-size:14px;line-height:1.45;color:#3C4F3F;padding:0 0 10px;\">" . $h(excerpt($p['lede'], 140)) . '</td></tr>';
                }
            }
        }
    }

    // The rail, in miniature: forecast then closing prices.
    $weather = pp_weather_today();
    if (!empty($weather['line'])) {
        $out .= "<tr><td style=\"background:$sky;padding:12px 14px;margin-top:8px;\">"
              . "<span style=\"$mono;font-size:9.5px;color:$ink;\">The forecast</span>"
              . "<div style=\"$bodyF;font-size:14px;line-height:1.45;color:$ink;padding-top:4px;\">"
              . $h(($weather['temp'] ?? '') . ' — ' . $weather['line']) . '</div>'
              . "<div style=\"$mono;font-size:9.5px;color:$ink;padding-top:4px;\">" . $h($weather['credit'] . ' · ' . $weather['observed']) . '</div></td></tr>';
    }
    $markets = pp_fresh_rows('markets');
    if ($markets) {
        $out .= "<tr><td style=\"padding:14px 0 0;\"><span style=\"$mono;font-size:10px;color:$muted;\">Closing prices</span>"
              . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:6px;">';
        foreach ($markets as $row) {
            [$name, $price, $change] = array_pad($row, 3, '');
            $cc = str_starts_with($change, '-') ? $red : $pal['hill'];
            $out .= "<tr><td style=\"font-family:'Courier New',Courier,monospace;font-size:12px;color:$ink;border-bottom:1px dotted $board;padding:4px 0;\">" . $h($name) . '</td>'
                  . "<td align=\"right\" style=\"font-family:'Courier New',Courier,monospace;font-size:12px;color:$ink;border-bottom:1px dotted $board;padding:4px 0;\">" . $h($price) . '</td>'
                  . "<td align=\"right\" width=\"64\" style=\"font-family:'Courier New',Courier,monospace;font-size:12px;color:$cc;border-bottom:1px dotted $board;padding:4px 0;\">" . $h(str_replace('-', '−', $change)) . '</td></tr>';
        }
        $out .= '</table></td></tr>';
    }

    // Footer: who we are, where we are (CASL), and the way out.
    $address = setting('paper_address');
    $out .= "<tr><td style=\"border-top:4px solid $ink;padding:16px 0 0;margin-top:20px;\">"
          . "<span style=\"$mono;font-size:9.5px;color:$muted;\">" . $h($siteTitle) . ' &middot; ' . $h(setting('tagline', 'News to the horizon')) . '</span>'
          . ($address !== '' ? "<div style=\"$bodyF;font-size:12.5px;color:$muted;padding-top:6px;\">" . $h($address) . '</div>' : '')
          . "<div style=\"$bodyF;font-size:12.5px;color:$muted;padding-top:8px;\">You're receiving this because you subscribed at "
          . '<a href="' . $h(site_url()) . "\" style=\"color:$muted;\">" . $h(parse_url(site_url(), PHP_URL_HOST) ?: $siteTitle) . '</a>. '
          . "<a href=\"%%UNSUB%%\" style=\"color:$muted;\">Unsubscribe</a> any time — it takes one click and works immediately.</div>"
          . '</td></tr>';

    $out .= '</table></td></tr></table></body></html>';

    return [$subject, $out, count($posts)];
}

/** Enqueue active subscribers into newsletter_deliveries for $editionId (idempotent). */
function pp_queue_edition_deliveries(int $editionId): int
{
    $pdo = db();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'mysql'
        ? 'INSERT IGNORE INTO newsletter_deliveries (newsletter_id, subscriber_id, email, token, status, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        : 'INSERT INTO newsletter_deliveries (newsletter_id, subscriber_id, email, token, status, created_at) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(newsletter_id, subscriber_id) DO NOTHING';
    $ins = $pdo->prepare($sql);
    $ts = now();
    $queued = 0;
    foreach (active_subscribers() as $sub) {
        $ins->execute([$editionId, (int) $sub['id'], (string) $sub['email'], (string) ($sub['token'] ?? ''), 'pending', $ts]);
        $queued += $ins->rowCount();
    }
    return $queued;
}

/**
 * Drain up to $batchSize pending rows from newsletter_deliveries for in-progress
 * editions on this site. Resumable across cron ticks without duplicating sends.
 * Returns ['edition_id' => int, 'sent' => int, 'failed' => int, 'remaining' => int, 'completed' => bool].
 */
function pp_process_newsletter_batch(?int $editionId = null, int $batchSize = 100, int $sleepMicros = 150000): array
{
    $pdo = db();
    $batchSize = max(1, min(1000, $batchSize));
    if ($editionId !== null) {
        $stmt = $pdo->prepare('SELECT * FROM newsletters WHERE id = ? AND site_id = ?');
        $stmt->execute([$editionId, current_site_id()]);
        $edition = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM newsletters WHERE site_id = ? AND status = 'sending' ORDER BY id ASC LIMIT 1");
        $stmt->execute([current_site_id()]);
        $edition = $stmt->fetch();
    }
    if (!$edition) {
        return ['edition_id' => 0, 'sent' => 0, 'failed' => 0, 'remaining' => 0, 'completed' => false];
    }

    $eid = (int) $edition['id'];
    $subject = (string) $edition['subject'];
    $html = (string) $edition['html'];

    $rows = $pdo->prepare("SELECT * FROM newsletter_deliveries WHERE newsletter_id = ? AND status = 'pending' ORDER BY id ASC LIMIT " . (int) $batchSize);
    $rows->execute([$eid]);
    $pending = $rows->fetchAll();

    $sentNow = 0;
    $failedNow = 0;
    $upd = $pdo->prepare('UPDATE newsletter_deliveries SET status = ?, error = ?, attempts = attempts + 1, sent_at = ? WHERE id = ?');
    $sender = $GLOBALS['pp_newsletter_sender'] ?? null;

    foreach ($pending as $row) {
        $unsub = site_url() . '/newsletter/unsubscribe?t=' . urlencode((string) $row['token']);
        $body = str_replace('%%UNSUB%%', $unsub, $html);
        $err = is_callable($sender)
            ? $sender((string) $row['email'], $subject, $body, $unsub)
            : pp_send_mail((string) $row['email'], $subject, $body, $unsub);
        if ($err === null) {
            $sentNow++;
            $upd->execute(['sent', '', now(), (int) $row['id']]);
        } else {
            $failedNow++;
            $upd->execute(['failed', mb_substr((string) $err, 0, 255), null, (int) $row['id']]);
        }
        if ($sleepMicros > 0) {
            usleep($sleepMicros);
        }
    }

    $countsStmt = $pdo->prepare('SELECT status, COUNT(*) AS n FROM newsletter_deliveries WHERE newsletter_id = ? GROUP BY status');
    $countsStmt->execute([$eid]);
    $counts = ['pending' => 0, 'sent' => 0, 'failed' => 0];
    foreach ($countsStmt as $c) {
        $counts[(string) $c['status']] = (int) $c['n'];
    }

    $completed = $counts['pending'] === 0;
    if ($completed) {
        $finalStatus = ($counts['failed'] > 0 && $counts['sent'] === 0) ? 'failed' : 'sent';
        $pdo->prepare('UPDATE newsletters SET status = ?, recipients = ?, sent_at = ? WHERE id = ?')
            ->execute([$finalStatus, $counts['sent'], now(), $eid]);
    } else {
        $pdo->prepare('UPDATE newsletters SET recipients = ? WHERE id = ?')
            ->execute([$counts['sent'], $eid]);
    }

    return [
        'edition_id' => $eid,
        'sent'       => $sentNow,
        'failed'     => $failedNow,
        'total_sent' => $counts['sent'],
        'total_fail' => $counts['failed'],
        'remaining'  => $counts['pending'],
        'completed'  => $completed,
    ];
}

/** Send (or queue + start draining) today's edition. Returns a human-readable summary. */
function pp_send_edition(bool $force = false, bool $testOnly = false, string $testAddress = '', ?int $now = null, ?int $batchSize = null, int $sleepMicros = 150000): string
{
    $today = pp_paper_date('Y-m-d', $now);
    [$subject, $html, $count] = pp_compile_edition();

    if ($testOnly) {
        $err = pp_send_mail($testAddress, '[TEST] ' . $subject, str_replace('%%UNSUB%%', site_url() . '/newsletter/', $html));
        return $err === null ? "Test edition sent to $testAddress." : "Test failed: $err";
    }

    $existing = newsletter_by_date($today);
    if ($existing) {
        if (($existing['status'] ?? '') === 'sending') {
            $batch = $batchSize ?? max(1, (int) (setting('newsletter_batch_size', '100') ?: 100));
            $res = pp_process_newsletter_batch((int) $existing['id'], $batch, $sleepMicros);
            return $res['completed']
                ? "Edition resumed and finished — {$res['total_sent']} sent" . ($res['total_fail'] ? " ({$res['total_fail']} failed)" : '') . '.'
                : "Edition in progress — sent {$res['sent']} in this batch ({$res['remaining']} remaining).";
        }
        return "This morning's edition already went out.";
    }
    if ($count === 0 && !$force) {
        return 'No stories in the last 24 hours — no edition sent. (Send anyway from the admin if you want the quiet-day note to go.)';
    }

    db()->prepare('INSERT INTO newsletters (site_id, edition_date, subject, html, status, recipients, created_at)
                   VALUES (?, ?, ?, ?, ?, 0, ?)')
        ->execute([current_site_id(), $today, $subject, $html, 'sending', now()]);
    $editionId = pp_last_id('newsletters');

    pp_queue_edition_deliveries($editionId);
    $batch = $batchSize ?? max(1, (int) (setting('newsletter_batch_size', '100') ?: 100));
    $res = pp_process_newsletter_batch($editionId, $batch, $sleepMicros);

    if (!$res['completed']) {
        return "Edition queued — sent {$res['sent']} so far, {$res['remaining']} remaining (resumes automatically on the next cron tick).";
    }
    $sent = $res['total_sent'];
    $failed = $res['total_fail'];
    return "Edition sent to $sent subscriber" . ($sent === 1 ? '' : 's')
        . ($failed ? " ($failed failed — check the mail settings)" : '') . '.';
}

/** Called by the cron: resume any in-progress edition, or start today's after the configured hour. */
function pp_maybe_send_daily(?int $now = null, ?int $batchSize = null, int $sleepMicros = 150000): string
{
    if (setting('newsletter_enabled', '0') !== '1') {
        return 'newsletter: disabled';
    }
    // Always drain any in-progress outbox batch first (resumes interrupted or large sends).
    $batch = $batchSize ?? max(1, (int) (setting('newsletter_batch_size', '100') ?: 100));
    $res = pp_process_newsletter_batch(null, $batch, $sleepMicros);
    if ($res['edition_id'] > 0) {
        return $res['completed']
            ? "newsletter: finished edition #{$res['edition_id']} ({$res['total_sent']} sent" . ($res['total_fail'] ? ", {$res['total_fail']} failed" : '') . ')'
            : "newsletter: sent {$res['sent']} for edition #{$res['edition_id']} ({$res['remaining']} remaining)";
    }
    $hour = (int) (setting('newsletter_send_hour', '6') ?: 6);
    if ((int) pp_paper_date('G', $now) < $hour) {
        return 'newsletter: before the ' . $hour . " o'clock send";
    }
    if (newsletter_by_date(pp_paper_date('Y-m-d', $now))) {
        return 'newsletter: already sent today';
    }
    return 'newsletter: ' . pp_send_edition(false, false, '', $now, $batch, $sleepMicros);
}

/** Confirmation email for double opt-in. */
function pp_send_confirmation(array $subscriber): ?string
{
    $confirm = site_url() . '/newsletter/confirm?t=' . urlencode($subscriber['token']);
    $siteTitle = setting('site_title', 'The Prairie Dispatch');
    $html = '<!DOCTYPE html><html lang="en"><body style="font-family:Georgia,serif;color:#17301C;background:#F1F2F0;padding:24px;">'
        . '<div style="max-width:520px;margin:0 auto;">'
        . "<div style=\"font-family:'Arial Narrow',Arial,sans-serif;font-weight:bold;font-size:26px;border-bottom:4px solid #17301C;padding-bottom:6px;\">" . strtoupper(e($siteTitle)) . '</div>'
        . '<p style="font-size:16px;line-height:1.6;">You asked for <strong>' . e(setting('newsletter_heading', 'The 6 a.m.')) . '</strong> — one email before the day starts. One click confirms it was really you:</p>'
        . '<p><a href="' . e($confirm) . '" style="background:#77B2D6;color:#0F2A1A;padding:11px 18px;text-decoration:none;font-family:\'Courier New\',monospace;font-size:13px;letter-spacing:2px;">CONFIRM THE SUBSCRIPTION</a></p>'
        . '<p style="font-size:13px;color:#5A6A5C;">Didn\'t sign up? Do nothing — this address stays off the list.</p>'
        . '</div></body></html>';
    return pp_send_mail($subscriber['email'], 'Confirm: ' . setting('newsletter_heading', 'The 6 a.m.'), $html);
}

/** Whether mail sending is configured well enough for double opt-in. */
function pp_mail_configured(): bool
{
    return trim(setting('smtp_host')) !== '' || trim(setting('mail_from')) !== '';
}
