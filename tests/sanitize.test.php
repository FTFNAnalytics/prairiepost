<?php
/** The HTML whitelist every stored story body passes through. */
require dirname(__DIR__) . '/app/helpers.php';

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

$out = sanitize_html('<p>Keep <strong>this</strong></p><script>alert(1)</script>');
ok(str_contains($out, '<strong>this</strong>') && !str_contains($out, '<script'), 'script stripped, editorial kept');

$out = sanitize_html('<p onclick="x()">hi</p>');
ok(!str_contains($out, 'onclick'), 'event handlers stripped');

$out = sanitize_html('<a href="javascript:alert(1)">x</a><a href="https://example.ca">y</a>');
ok(!str_contains($out, 'javascript:') && str_contains($out, 'https://example.ca'), 'javascript: URLs stripped, real links kept');

exit($fails ? 1 : 0);
