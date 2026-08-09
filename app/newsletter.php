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
            ORDER BY CASE WHEN p.placement = \'hero\' THEN 0 ELSE 1 END, p.published_at DESC LIMIT 25';
    $stmt = db()->prepare($sql);
    $stmt->execute([now(), $since]);
    return $stmt->fetchAll();
}

/** Compile today's edition. Returns [subject, html, post_count]. */
function pp_compile_edition(): array
{
    $posts = pp_edition_posts();
    $siteTitle = setting('site_title', 'The Prairie Dispatch');
    $dateLine = date('l, F j, Y');
    $subject = setting('newsletter_heading', 'The 6 a.m.') . ' — ' . date('l, M j')
        . ($posts ? ': ' . $posts[0]['title'] : '');

    $ink = '#17301C'; $paper = '#F1F2F0'; $board = '#C4C0B4'; $sky = '#77B2D6';
    $muted = '#5A6A5C'; $red = '#9C3B22';
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
            $deskColor = empty($heroPost['category_color_is_fill']) ? $heroPost['category_color'] : $ink;
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
            $deskColor = (!empty($first['category_color']) && empty($first['category_color_is_fill'])) ? $first['category_color'] : $ink;
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
    $weather = setting_json('weather_today');
    if (!empty($weather['line'])) {
        $out .= "<tr><td style=\"background:$sky;padding:12px 14px;margin-top:8px;\">"
              . "<span style=\"$mono;font-size:9.5px;color:$ink;\">The forecast</span>"
              . "<div style=\"$bodyF;font-size:14px;line-height:1.45;color:$ink;padding-top:4px;\">"
              . $h(($weather['temp'] ?? '') . ' — ' . $weather['line']) . '</div></td></tr>';
    }
    $markets = setting_json('markets');
    if ($markets) {
        $out .= "<tr><td style=\"padding:14px 0 0;\"><span style=\"$mono;font-size:10px;color:$muted;\">Closing prices</span>"
              . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:6px;">';
        foreach ($markets as $row) {
            [$name, $price, $change] = array_pad($row, 3, '');
            $cc = str_starts_with($change, '-') ? $red : '#3F5A22';
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

/** Send (or record) today's edition. Returns a human-readable summary. */
function pp_send_edition(bool $force = false, bool $testOnly = false, string $testAddress = ''): string
{
    $today = date('Y-m-d');
    [$subject, $html, $count] = pp_compile_edition();

    if ($testOnly) {
        $err = pp_send_mail($testAddress, '[TEST] ' . $subject, str_replace('%%UNSUB%%', site_url() . '/newsletter/', $html));
        return $err === null ? "Test edition sent to $testAddress." : "Test failed: $err";
    }

    if (newsletter_by_date($today)) {
        return "This morning's edition already went out.";
    }
    if ($count === 0 && !$force) {
        return 'No stories in the last 24 hours — no edition sent. (Send anyway from the admin if you want the quiet-day note to go.)';
    }

    db()->prepare('INSERT INTO newsletters (site_id, edition_date, subject, html, status, recipients, created_at)
                   VALUES (?, ?, ?, ?, ?, 0, ?)')
        ->execute([current_site_id(), $today, $subject, $html, 'sending', now()]);
    $editionId = pp_last_id('newsletters');

    $sent = 0;
    $failed = 0;
    foreach (active_subscribers() as $sub) {
        $unsub = site_url() . '/newsletter/unsubscribe?t=' . urlencode($sub['token']);
        $err = pp_send_mail($sub['email'], $subject, str_replace('%%UNSUB%%', $unsub, $html), $unsub);
        $err === null ? $sent++ : $failed++;
        usleep(150000); // a gentle pace keeps shared-host rate limits friendly
    }

    db()->prepare('UPDATE newsletters SET status = ?, recipients = ?, sent_at = ? WHERE id = ?')
        ->execute([$failed > 0 && $sent === 0 ? 'failed' : 'sent', $sent, now(), $editionId]);

    return "Edition sent to $sent subscriber" . ($sent === 1 ? '' : 's')
        . ($failed ? " ($failed failed — check the mail settings)" : '') . '.';
}

/** Called by the cron: send once per day, after the configured hour. */
function pp_maybe_send_daily(): string
{
    if (setting('newsletter_enabled', '0') !== '1') {
        return 'newsletter: disabled';
    }
    $hour = (int) (setting('newsletter_send_hour', '6') ?: 6);
    if ((int) date('G') < $hour) {
        return 'newsletter: before the ' . $hour . " o'clock send";
    }
    if (newsletter_by_date(date('Y-m-d'))) {
        return 'newsletter: already sent today';
    }
    return 'newsletter: ' . pp_send_edition();
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
