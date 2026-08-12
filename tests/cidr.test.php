<?php
/** The allowlist matcher and validator (pure functions in models.php). */
require dirname(__DIR__) . '/app/models.php';

$fails = 0;
$cases = [
    ['127.0.0.1', '127.0.0.0/8', true],
    ['128.0.0.1', '127.0.0.0/8', false],
    ['10.1.2.3', '10.1.2.3', true],
    ['10.1.2.4', '10.1.2.3', false],
    ['192.168.1.77', '192.168.1.64/26', true],
    ['192.168.1.130', '192.168.1.64/26', false],
    ['2001:db8::1', '2001:db8::/32', true],
    ['2001:db9::1', '2001:db8::/32', false],
    ['::1', '::1', true],
    ['10.0.0.1', '2001:db8::/32', false],   // family mismatch
    ['10.0.0.1', '0.0.0.0/0', true],
    ['10.0.0.1', '10.0.0.0/33', false],     // impossible mask
    ['10.0.0.1', 'banana', false],
];
foreach ($cases as [$ip, $cidr, $want]) {
    if (pp_ip_in_cidr($ip, $cidr) !== $want) {
        echo "FAIL match $ip in $cidr\n";
        $fails++;
    }
}
foreach ([['203.0.113.7', true], ['203.0.113.0/24', true], ['2001:db8::/32', true],
          ['nope', false], ['10.0.0.0/40', false], ['10.0.0.0/x', false], ['', false]] as [$entry, $want]) {
    if (pp_cidr_valid($entry) !== $want) {
        echo "FAIL valid '$entry'\n";
        $fails++;
    }
}
exit($fails ? 1 : 0);
