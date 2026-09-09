<?php
/**
 * The safe outbound transport (F05): destination policy across address
 * families and notations, per-hop redirect validation, DNS rebinding,
 * streamed size caps (compressed included), timeouts, and the fetch paths
 * that must keep working. Resolver and destination allowances are injected
 * through CLI-only test hooks; the connection policy itself — pinning,
 * hop-by-hop revalidation — runs for real against a loopback fixture.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
putenv('PP_TRANSPORT_TEST=1');

$root = dirname(__DIR__);
require $root . '/app/models.php';    // pp_ip_in_cidr
require $root . '/app/transport.php';
require $root . '/app/helpers.php';   // pp_url_is_public, parse_feed

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

/* --- Destination policy: literal addresses, every notation ---------------- */

foreach ([
    ['127.0.0.1', false, 'v4 loopback'],
    ['127.8.9.10', false, 'v4 loopback, deep'],
    ['10.1.2.3', false, 'RFC1918 10/8'],
    ['172.16.5.5', false, 'RFC1918 172.16/12'],
    ['172.32.0.1', true, '172.32 is public (12-bit mask edge)'],
    ['192.168.1.1', false, 'RFC1918 192.168/16'],
    ['169.254.169.254', false, 'link-local / cloud metadata'],
    ['100.64.1.1', false, 'CGNAT'],
    ['0.0.0.0', false, 'this-network'],
    ['224.0.0.1', false, 'multicast'],
    ['240.0.0.1', false, 'reserved'],
    ['198.18.0.7', false, 'benchmarking'],
    ['203.0.113.9', false, 'TEST-NET-3'],
    ['8.8.8.8', true, 'public v4'],
    ['::1', false, 'v6 loopback'],
    ['fe80::1', false, 'v6 link-local'],
    ['fc00::1', false, 'v6 ULA fc'],
    ['fd12:3456::1', false, 'v6 ULA fd'],
    ['ff02::1', false, 'v6 multicast'],
    ['2001:db8::1', false, 'v6 documentation'],
    ['::ffff:127.0.0.1', false, 'v4-mapped loopback'],
    ['::ffff:10.0.0.1', false, 'v4-mapped RFC1918'],
    ['::ffff:8.8.8.8', true, 'v4-mapped public stays public'],
    ['64:ff9b::7f00:1', false, 'NAT64-embedded loopback'],
    ['2607:f8b0:4004::64', true, 'public v6'],
    ['banana', false, 'not an address'],
] as [$ip, $want, $label]) {
    ok(pp_ip_is_public($ip) === $want, "policy: $label ($ip)");
}

/* --- URL validation -------------------------------------------------------- */

foreach ([
    ['ftp://example.com/x', 'ftp scheme'],
    ['file:///etc/passwd', 'file scheme'],
    ['gopher://example.com/', 'gopher scheme'],
    ['http://user:pass@example.com/', 'embedded credentials'],
    ['http:///nohost', 'missing host'],
    ['not a url', 'unparseable'],
] as [$bad, $label]) {
    [, $err] = pp_http_check_url($bad);
    ok($err !== null, "url refused: $label");
}
[$p, $err] = pp_http_check_url('https://example.com/path?q=1');
ok($err === null && $p['port'] === 443, 'https URL accepted with default port');

/* --- Resolution policy with an injected resolver --------------------------- */

pp_http_test_hooks(['resolver' => function (string $host) {
    return match ($host) {
        'all-public.test'  => ['93.184.216.34', '2606:2800:220:1::1'],
        'mixed.test'       => ['93.184.216.34', '10.0.0.5'],
        'private-only.test' => ['192.168.7.7'],
        '2130706433'       => ['127.0.0.1'],   // what libc makes of a decimal literal
        default            => [],
    };
}]);
[$ips, $err] = pp_http_resolve('all-public.test');
ok($err === null && count($ips) === 2, 'all-public host resolves');
[, $err] = pp_http_resolve('mixed.test');
ok($err !== null, 'ONE private answer rejects the whole host (mixed DNS)');
[, $err] = pp_http_resolve('private-only.test');
ok($err !== null, 'private-only host rejected');
[, $err] = pp_http_resolve('nx.test');
ok($err !== null, 'unresolvable host rejected');
ok(pp_url_is_public('http://2130706433/') === false, 'decimal IP literal blocked after resolution');
ok(pp_url_is_public('http://mixed.test/x') === false, 'pp_url_is_public follows the same policy');
ok(pp_url_is_public('http://all-public.test/x') === true, 'pp_url_is_public passes clean hosts');

/* --- Live behavior against a loopback fixture server ----------------------- */

$port = 8500 + random_int(0, 90);
$log = sys_get_temp_dir() . '/pp-transport-server-' . $port . '.log';
$pid = (int) trim((string) shell_exec(
    'cd ' . escapeshellarg($root) . ' && ' . escapeshellarg(PHP_BINARY)
    . ' -S 127.0.0.1:' . $port . ' tests/fixtures/transport-server.php >' . escapeshellarg($log) . ' 2>&1 & echo $!'
));
usleep(600000);
register_shutdown_function(function () use ($pid, $log) {
    if ($pid) {
        @posix_kill($pid, 15);
    }
    @unlink($log);
});

// The fixture stands in for the public internet: hostnames resolve through
// the injected resolver, and 127.0.0.1 is declared public FOR THIS TEST
// PROCESS ONLY (CLI hook; production cannot arm it).
$rebindCalls = 0;
pp_http_test_hooks([
    'public_ips' => ['127.0.0.1'],
    'resolver' => function (string $host) use (&$rebindCalls) {
        return match ($host) {
            'public-a.test', 'public-b.test' => ['127.0.0.1'],
            'internal.test'  => ['10.0.0.5'],
            'metadata.test'  => ['169.254.169.254'],
            'rebind.test'    => (++$rebindCalls === 1) ? ['127.0.0.1'] : ['10.0.0.5'],
            default          => [],
        };
    },
]);
$base = "http://public-a.test:$port";

[$body, $err] = pp_http_get("$base/ok");
ok($err === null && str_contains((string) $body, 'FIXTURE-OK'), "plain fetch works ($err)");

[$body, $err] = pp_http_get("$base/redir-rel");
ok($err === null && str_contains((string) $body, 'FIXTURE-OK'), 'relative redirect resolved against the current URL and followed');

[$body, $err] = pp_http_get("$base/redir-abs?" . http_build_query(['to' => "http://public-b.test:$port/ok"]));
ok($err === null && str_contains((string) $body, 'FIXTURE-OK'), 'cross-host redirect to a public host followed');

[$body, $err] = pp_http_get("$base/redir-abs?" . http_build_query(['to' => "http://internal.test:$port/ok"]));
ok($body === null && $err !== null && str_contains($err, 'hop'), 'public-to-private redirect blocked at the hop');

[$body, $err] = pp_http_get("$base/redir-abs?" . http_build_query(['to' => "http://metadata.test:$port/latest/meta-data/"]));
ok($body === null && $err !== null, 'redirect to the metadata service blocked');

[$body, $err] = pp_http_get("$base/redir-abs?" . http_build_query(['to' => "file:///etc/passwd"]));
ok($body === null && $err !== null, 'redirect to a non-http scheme blocked');

[$body, $err] = pp_http_get("$base/loop");
ok($body === null && str_contains((string) $err, 'redirect'), 'redirect loop stopped at the hop cap');

// DNS rebinding: the first resolution says public, the answer changes for
// the next hop — the transport re-resolves and refuses.
[$body, $err] = pp_http_get("http://rebind.test:$port/redir-rel");
ok($body === null && $err !== null, 'DNS answer changing between hops is caught (rebinding)');
ok($rebindCalls >= 2, 'every hop re-resolved, none trusted from cache');

[$body, $err] = pp_http_get("http://internal.test:$port/ok");
ok($body === null && $err !== null, 'direct request to a private-resolving host refused before connecting');

[$body, $err] = pp_http_get("$base/big", ['max_bytes' => 1024 * 1024]);
ok($body === null && str_contains((string) $err, 'larger'), 'oversized body aborted mid-stream');

[$body, $err] = pp_http_get("$base/gzip-big", ['max_bytes' => 1024 * 1024]);
ok($body === null && str_contains((string) $err, 'larger'), 'compressed body counted DECOMPRESSED against the cap');

[$body, $err] = pp_http_get("$base/missing");
ok($body === null && $err === 'HTTP 404', 'HTTP errors reported');

[$body, $err] = pp_http_get("https://public-a.test:$port/ok");
ok($body === null && $err !== null, 'https against a non-TLS endpoint fails closed (verification stays on)');

// The consumers: a feed fetched through the transport still parses.
[$xml, $err] = pp_http_get("$base/rss");
[$items, $perr] = parse_feed((string) $xml);
ok($err === null && $perr === null && count($items) === 1 && $items[0]['title'] === 'Fixture item',
    'RSS fetch + parse through the transport');

// And an image still arrives intact.
[$png, $err] = pp_http_get("$base/img");
ok($err === null && substr((string) $png, 1, 3) === 'PNG', 'image fetch through the transport');

[$body, $err] = pp_http_get("$base/slow", ['timeout' => 1]);
ok($body === null && $err !== null, 'overall timeout enforced');

/* --- The hooks are test-only ------------------------------------------------ */

putenv('PP_TRANSPORT_TEST');
$threw = false;
try {
    pp_http_test_hooks(['public_ips' => ['127.0.0.1']]);
} catch (RuntimeException) {
    $threw = true;
}
ok($threw, 'test hooks refuse to arm without the CLI test flag');

exit($fails ? 1 : 0);
