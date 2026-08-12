<?php
/**
 * TOTP two-factor, in pure PHP — RFC 6238 over RFC 4226, SHA-1, 30-second
 * period, six digits: what every authenticator app speaks. No QR library
 * and no dependency: enrolment shows the manual-entry key and the
 * otpauth:// URI, which is all an app needs.
 *
 * The secret lives in users.totp_secret and is compared with hash_equals;
 * verification accepts one period of clock drift either side.
 */

function pp_base32_encode(string $bin): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $out = '';
    $buffer = 0;
    $bits = 0;
    foreach (str_split($bin) as $byte) {
        $buffer = ($buffer << 8) | ord($byte);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $out .= $alphabet[($buffer >> $bits) & 31];
        }
    }
    if ($bits > 0) {
        $out .= $alphabet[($buffer << (5 - $bits)) & 31];
    }
    return $out;
}

function pp_base32_decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $clean = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
    $out = '';
    $buffer = 0;
    $bits = 0;
    foreach (str_split($clean) as $char) {
        $val = strpos($alphabet, $char);
        if ($val === false) {
            continue;
        }
        $buffer = ($buffer << 5) | $val;
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $out .= chr(($buffer >> $bits) & 255);
        }
    }
    return $out;
}

/** A fresh 160-bit secret, base32 — ready for any authenticator app. */
function pp_totp_new_secret(): string
{
    return pp_base32_encode(random_bytes(20));
}

/** The six-digit code for one moment in time. */
function pp_totp_at(string $secret, int $timestamp): string
{
    $counter = pack('J', intdiv($timestamp, 30));
    $hash = hash_hmac('sha1', $counter, pp_base32_decode($secret), true);
    $offset = ord($hash[19]) & 0x0F;
    $code = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        (ord($hash[$offset + 1]) << 16) |
        (ord($hash[$offset + 2]) << 8) |
        ord($hash[$offset + 3])
    ) % 1000000;
    return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
}

/** Accepts the current code, or its neighbours for one period of drift. */
function pp_totp_verify(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6 || $secret === '') {
        return false;
    }
    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(pp_totp_at($secret, $now + $i * 30), $code)) {
            return true;
        }
    }
    return false;
}

/** What an authenticator app enrols from — shown once, at setup. */
function pp_totp_uri(string $secret, string $email, string $issuer): string
{
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($email)
         . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}
