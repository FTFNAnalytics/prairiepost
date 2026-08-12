<?php
/** TOTP against RFC 6238's published SHA-1 vectors, plus behaviour. */
require dirname(__DIR__) . '/app/totp.php';

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

// RFC 6238 Appendix B — the SHA-1 rows, with the RFC's 8-digit codes
// truncated to our 6 (the last six digits are the same computation).
$seed = pp_base32_encode('12345678901234567890');
foreach ([59 => '287082', 1111111109 => '081804', 1234567890 => '005924', 2000000000 => '279037'] as $t => $code) {
    ok(pp_totp_at($seed, $t) === $code, "vector t=$t");
}

$secret = pp_totp_new_secret();
ok(strlen($secret) === 32 && preg_match('/^[A-Z2-7]+$/', $secret) === 1, 'secret shape (160-bit base32)');
ok(pp_base32_decode(pp_base32_encode('any bytes at all')) === 'any bytes at all', 'base32 round trip');
ok(pp_totp_verify($secret, pp_totp_at($secret, time())), 'current code verifies');
ok(pp_totp_verify($secret, pp_totp_at($secret, time() - 30)), 'one period of drift tolerated');
ok(!pp_totp_verify($secret, pp_totp_at($secret, time() - 120)), 'stale code refused');
ok(!pp_totp_verify($secret, '000000') || pp_totp_at($secret, time()) === '000000', 'wrong code refused');
ok(!pp_totp_verify('', '123456'), 'empty secret refused');
ok(str_starts_with(pp_totp_uri($secret, 'a@b.ca', 'Civis Media'), 'otpauth://totp/Civis%20Media:a%40b.ca?secret='), 'otpauth uri shape');

exit($fails ? 1 : 0);
