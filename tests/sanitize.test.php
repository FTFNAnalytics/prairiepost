<?php
/**
 * The parser-based sanitizer every stored story body passes through, and
 * the HTML-context JSON encoder. Includes the two harmless audit fixtures
 * (F01): the entity-encoded javascript: link and the script-terminating
 * headline. Neither may survive in executable form — and legitimate
 * editorial formatting must survive intact.
 */
define('PP_ROOT', dirname(__DIR__));
require PP_ROOT . '/app/helpers.php';
require PP_ROOT . '/app/security.php';

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

/* --- The original audit fixtures ---------------------------------------- */

// F01a: entity-encoded scheme. The regex sanitizer preserved this verbatim.
$out = sanitize_html('<p><a href="jav&#x61;script:document.title=\'AUDIT_HTML_EXECUTED\'">Audit sanitizer link</a></p>');
ok(stripos($out, 'javascript') === false && !str_contains($out, 'AUDIT_HTML_EXECUTED')
    && str_contains($out, 'Audit sanitizer link'), 'audit fixture: encoded javascript: link neutralized, text kept');
ok(!preg_match('/href\s*=/i', $out), 'audit fixture: no href survives on the neutralized link');

// F01b: headline that terminates an inline JSON-LD script block.
$headline = 'Audit script marker </script><script>document.title=123456789</script>';
$json = pp_json_for_html(['headline' => $headline]);
ok(!str_contains($json, '</script>') && !str_contains($json, '<script'), 'audit fixture: JSON cannot close the script element');
$decoded = json_decode($json, true);
ok(($decoded['headline'] ?? '') === $headline, 'audit fixture: JSON round-trips the headline losslessly');

/* --- Executable constructs ----------------------------------------------- */

$out = sanitize_html('<p>Keep <strong>this</strong></p><script>alert(1)</script>');
ok(str_contains($out, '<strong>this</strong>') && !str_contains($out, '<script'), 'script stripped, editorial kept');

$out = sanitize_html('<p onclick="x()" onmouseover=\'y()\' ONERROR=z()>hi</p>');
ok(stripos($out, 'onclick') === false && stripos($out, 'onmouseover') === false && stripos($out, 'onerror') === false,
    'event handlers stripped in every quoting style');

$out = sanitize_html('<a href="javascript:alert(1)">x</a><a href="https://example.ca">y</a>');
ok(stripos($out, 'javascript:') === false && str_contains($out, 'https://example.ca'), 'javascript: URLs stripped, real links kept');

$out = sanitize_html('<a href="JaVaScRiPt&#58;alert(1)">x</a>');
ok(stripos($out, 'alert') === false || !preg_match('/href\s*=\s*["\'][^"\']*(script|alert)/i', $out),
    'mixed-case entity-encoded scheme neutralized');

$out = sanitize_html("<a href=\"java\tscript:alert(1)\">x</a>");
ok(!preg_match('/href\s*=\s*["\'][a-z]*script:/i', $out), 'control characters inside the scheme neutralized');

$out = sanitize_html('<a href="data:text/html,<script>1</script>">x</a>');
ok(stripos($out, 'data:') === false, 'data: URLs dropped entirely');

$out = sanitize_html('<img src="x" onerror="document.title=\'pwn\'">');
ok(stripos($out, 'onerror') === false && !str_contains($out, 'pwn'), 'img onerror stripped');

$out = sanitize_html('<p><b>unclosed <script>alert(1)</p>');
ok(!str_contains($out, '<script') && !str_contains($out, 'alert(1)'), 'malformed markup cannot smuggle a script');

$out = sanitize_html('<svg onload=alert(1)><iframe src="https://evil.example"></iframe><object data="x"></object>');
ok(stripos($out, '<svg') === false && stripos($out, '<iframe') === false && stripos($out, '<object') === false,
    'svg, iframe, object never allowed');

$out = sanitize_html('<a href="vbscript:x">v</a><a href="file:///etc/passwd">f</a>');
ok(stripos($out, 'vbscript') === false && stripos($out, 'file:') === false, 'other schemes dropped');

/* --- Legitimate editorial formatting survives ---------------------------- */

$legit = '<h2>Crosshead</h2><p style="text-align:center">Para with <em>em</em>, <strong>strong</strong>, '
       . '<a href="https://example.com/story" title="t">a link</a> and an &amp; entity.</p>'
       . '<blockquote><p>Quoted.</p></blockquote><ul><li>one</li><li>two</li></ul>'
       . '<figure><img src="/uploads/2026/08/field.jpg" alt="Field" width="820" height="547" loading="lazy">'
       . '<figcaption>Canola, August</figcaption></figure>'
       . '<table><thead><tr><th scope="col">Ward</th></tr></thead><tbody><tr><td rowspan="2">1</td></tr></tbody></table><hr>';
$out = sanitize_html($legit);
foreach (['<h2>Crosshead</h2>', '<em>em</em>', '<strong>strong</strong>', 'https://example.com/story',
          '<blockquote>', '<li>one</li>', '<figure>', '/uploads/2026/08/field.jpg', 'alt="Field"',
          'loading="lazy"', '<figcaption>Canola, August</figcaption>', '<table>', 'scope="col"',
          'rowspan="2"', 'text-align:center', '&amp;'] as $needle) {
    ok(str_contains($out, $needle), "legit formatting survives: $needle");
}
ok(sanitize_html($out) === $out, 'sanitizer is idempotent on its own output');
ok(sanitize_html('') === '' && sanitize_html('   ') === '', 'empty input stays empty');

// Unicode prose passes through undamaged.
$fr = '<p>À Chicoutimi, l\'aréna — «rénovée» — rouvre.</p>';
$out = sanitize_html($fr);
ok(str_contains($out, 'À Chicoutimi') && str_contains($out, '«rénovée»'), 'unicode prose intact');

/* --- JSON-for-HTML edge cases -------------------------------------------- */

ok(pp_json_for_html(['a' => "\xB1\x31"]) !== '' && json_decode(pp_json_for_html(['a' => "\xB1\x31"]), true) !== null,
    'invalid UTF-8 is substituted, not fatal');
$j = pp_json_for_html(['x' => '<!--<script>--></script><script>']);
ok(!str_contains($j, '<') && !str_contains($j, '>'), 'no raw angle brackets ever reach the script element');
ok(json_decode(pp_json_for_html(NAN)) === null || pp_json_for_html(NAN) === '""',
    'unencodable scalars degrade to inert output');

exit($fails ? 1 : 0);
