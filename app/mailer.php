<?php
/**
 * The Prairie Dispatch — outgoing mail.
 * A deliberately small SMTP client (EHLO / STARTTLS / AUTH LOGIN / DATA) with
 * a PHP mail() fallback when no SMTP host is configured. No dependencies.
 */

/** Send one HTML email. Returns null on success, or a plain-English error. */
function pp_send_mail(string $to, string $subject, string $html, string $listUnsub = ''): ?string
{
    $from = setting('mail_from');
    if ($from === '') {
        $host = parse_url(site_url(), PHP_URL_HOST) ?: 'localhost';
        $from = 'newsletter@' . preg_replace('/^www\./', '', $host);
    }
    $fromName = setting('mail_from_name', setting('site_title', 'The Prairie Dispatch'));

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFrom = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

    $headers = [
        'From: ' . $encodedFrom . ' <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if ($listUnsub !== '') {
        $headers[] = 'List-Unsubscribe: <' . $listUnsub . '>';
    }

    $smtpHost = trim(setting('smtp_host'));
    if ($smtpHost === '') {
        $ok = @mail($to, $encodedSubject, $html, implode("\r\n", $headers));
        return $ok ? null : "PHP mail() refused the message — configure SMTP under The 6 a.m. settings.";
    }

    return pp_smtp_send($smtpHost, $to, $from, $encodedSubject, $html, $headers);
}

function pp_smtp_send(string $host, string $to, string $from, string $subject, string $html, array $headers): ?string
{
    $port = (int) (setting('smtp_port', '587') ?: 587);
    $secure = setting('smtp_secure', 'tls');          // tls | ssl | none
    $user = setting('smtp_user');
    $pass = setting('smtp_pass');
    $timeout = 12;

    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['SNI_enabled' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return "Couldn't reach $host:$port — " . ($errstr ?: 'connection failed') . '.';
    }
    stream_set_timeout($fp, $timeout);

    $read = function () use ($fp): array {
        $text = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $text .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        return [(int) substr($text, 0, 3), trim($text)];
    };
    $say = function (string $cmd, array $expect) use ($fp, $read): ?string {
        fwrite($fp, $cmd . "\r\n");
        [$code, $text] = $read();
        return in_array($code, $expect, true) ? null : "$cmd → $text";
    };

    [$code] = $read();
    if ($code !== 220) {
        fclose($fp);
        return "$host didn't greet as an SMTP server (code $code).";
    }

    $me = parse_url(site_url(), PHP_URL_HOST) ?: 'localhost';
    if ($err = $say("EHLO $me", [250])) { fclose($fp); return $err; }

    if ($secure === 'tls') {
        if ($err = $say('STARTTLS', [220])) { fclose($fp); return $err; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return "STARTTLS handshake with $host failed.";
        }
        if ($err = $say("EHLO $me", [250])) { fclose($fp); return $err; }
    }

    if ($user !== '') {
        if (($err = $say('AUTH LOGIN', [334]))
            || ($err = $say(base64_encode($user), [334]))
            || ($err = $say(base64_encode($pass), [235]))) {
            fclose($fp);
            return 'Sign-in to the mail server failed — check the SMTP user and password.';
        }
    }

    if (($err = $say("MAIL FROM:<$from>", [250]))
        || ($err = $say("RCPT TO:<$to>", [250, 251]))
        || ($err = $say('DATA', [354]))) {
        fclose($fp);
        return $err;
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $html);
    $normalized = preg_replace('/^\./m', '..', $normalized);          // SMTP dot-stuffing
    $normalized = str_replace("\n", "\r\n", $normalized);
    $body = implode("\r\n", array_merge($headers, ['To: <' . $to . '>', 'Subject: ' . $subject, 'Date: ' . date(DATE_RFC2822), '', '']))
          . $normalized;
    fwrite($fp, $body . "\r\n.\r\n");
    [$code, $text] = $read();
    $say('QUIT', [221]);
    fclose($fp);

    return $code === 250 ? null : "The server declined the message: $text";
}
