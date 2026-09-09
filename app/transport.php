<?php
/**
 * The shared outbound HTTP transport for UNTRUSTED URLs — feed fetches,
 * link previews, ingest images: every case where a URL someone else wrote
 * makes this server issue a request (F05).
 *
 * What it guarantees, and the old http_get() didn't:
 *
 *  - http/https only, no credentials in the URL, ever.
 *  - The hostname's A AND AAAA answers are all resolved and each address
 *    is checked against the non-public blocklist (loopback, RFC1918,
 *    CGNAT, link-local — the cloud metadata service lives there — ULA,
 *    multicast, reserved, documentation ranges). ONE non-public answer
 *    rejects the whole host: a mixed public/private answer is treated as
 *    hostile, and there is no fallback connection to anything unchecked.
 *  - The connection is PINNED to the validated addresses (CURLOPT_RESOLVE),
 *    so a DNS answer that changes between validation and connect cannot
 *    redirect the socket. Host header, TLS SNI and certificate validation
 *    all still see the original hostname.
 *  - Redirects are never followed blindly: each hop's Location is resolved
 *    against the current URL, re-validated and re-pinned, up to a hop cap.
 *  - The body is size-capped AS IT STREAMS (after decompression), so an
 *    endless or decompression-inflated response cannot buffer unbounded.
 *  - The proxy environment is ignored: a proxy would resolve names itself
 *    and silently undo the pinning, so these requests always go direct.
 *
 * Fixed-origin authenticated provider clients (Anthropic in app/ai.php,
 * Google in app/google.php) are deliberately NOT this: they talk to
 * hardcoded hosts with credentials and keep their own transport.
 */

/** Address space the server must never fetch from on someone else's say-so. */
function pp_http_blocked_cidrs(): array
{
    return [
        // IPv4
        '0.0.0.0/8',        // "this network"
        '10.0.0.0/8',       // private
        '100.64.0.0/10',    // CGNAT
        '127.0.0.0/8',      // loopback
        '169.254.0.0/16',   // link-local (incl. 169.254.169.254 metadata)
        '172.16.0.0/12',    // private
        '192.0.0.0/24',     // IETF protocol assignments
        '192.0.2.0/24',     // TEST-NET-1
        '192.168.0.0/16',   // private
        '198.18.0.0/15',    // benchmarking
        '198.51.100.0/24',  // TEST-NET-2
        '203.0.113.0/24',   // TEST-NET-3
        '224.0.0.0/4',      // multicast
        '240.0.0.0/4',      // reserved
        // IPv6
        '::/128',           // unspecified
        '::1/128',          // loopback
        '64:ff9b::/96',     // NAT64 (embeds v4, checked separately too)
        '100::/64',         // discard
        '2001:db8::/32',    // documentation
        'fc00::/7',         // unique local
        'fe80::/10',        // link-local
        'ff00::/8',         // multicast
    ];
}

/**
 * Is this literal IP address public under the blocklist? IPv4-mapped IPv6
 * (::ffff:a.b.c.d) and NAT64 (64:ff9b::a.b.c.d) embed an IPv4 address —
 * the embedded address is extracted and judged as IPv4, so a private v4
 * cannot ride in on a v6 notation.
 */
function pp_ip_is_public(string $ip): bool
{
    $ip = strtolower(trim($ip, "[] \t"));
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return false; // not an address at all — never "public"
    }
    if (strlen($bin) === 16) {
        // ::ffff:0:0/96 (v4-mapped) and 64:ff9b::/96 (NAT64): judge the
        // embedded IPv4 address by the IPv4 rules.
        $mapped = substr($bin, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff"
               || substr($bin, 0, 12) === "\x00\x64\xff\x9b\x00\x00\x00\x00\x00\x00\x00\x00";
        if ($mapped) {
            return pp_ip_is_public(inet_ntop(substr($bin, 12)));
        }
    }
    foreach (pp_http_blocked_cidrs() as $cidr) {
        if (pp_ip_in_cidr($ip, $cidr)) {
            return false;
        }
    }
    return true;
}

/** Internal: is $ip acceptable, counting the CLI test allowlist? */
function pp_http_ip_ok(string $ip): bool
{
    $extra = $GLOBALS['pp_http_test']['public_ips'] ?? [];
    if ($extra && in_array(strtolower(trim($ip, '[]')), $extra, true)) {
        return true;
    }
    return pp_ip_is_public($ip);
}

/**
 * Test instrumentation — refuses to arm outside a CLI process that set
 * PP_TRANSPORT_TEST=1. FPM/production requests can never reach it: no
 * request path calls this function, the SAPI check fails there anyway,
 * and nothing reads the environment flag from config. Keys:
 *   resolver   callable(string $host): array of IP strings
 *   public_ips array of literal IPs to treat as public (fixture servers)
 */
function pp_http_test_hooks(array $hooks): void
{
    if (PHP_SAPI !== 'cli' || getenv('PP_TRANSPORT_TEST') !== '1') {
        throw new RuntimeException('transport test hooks are CLI test instrumentation only');
    }
    $GLOBALS['pp_http_test'] = [
        'resolver'   => $hooks['resolver'] ?? null,
        'public_ips' => array_map('strtolower', $hooks['public_ips'] ?? []),
    ];
}

/**
 * Resolve a hostname to the addresses the transport may connect to.
 * Returns [ips, null] — every address resolved AND public — or
 * [null, reason]. A single non-public answer poisons the whole host.
 */
function pp_http_resolve(string $host): array
{
    $bare = trim($host, '[]');
    if (filter_var($bare, FILTER_VALIDATE_IP)) {
        return pp_http_ip_ok($bare) ? [[$bare], null] : [null, 'address is not publicly routable'];
    }
    if ($resolver = ($GLOBALS['pp_http_test']['resolver'] ?? null)) {
        $ips = (array) $resolver($host);
    } else {
        $ips = array_merge(
            gethostbynamel($host) ?: [],
            array_column(dns_get_record($host, DNS_AAAA) ?: [], 'ipv6')
        );
    }
    $ips = array_values(array_unique(array_filter($ips)));
    if (!$ips) {
        return [null, 'hostname did not resolve'];
    }
    foreach ($ips as $ip) {
        if (!pp_http_ip_ok((string) $ip)) {
            return [null, 'hostname resolves to a non-public address'];
        }
    }
    return [$ips, null];
}

/** Validate one URL for the transport; returns [parts, null] or [null, reason]. */
function pp_http_check_url(string $url): array
{
    if (strlen($url) > 2048) {
        return [null, 'URL too long'];
    }
    $p = parse_url($url);
    if (!is_array($p)) {
        return [null, 'unparseable URL'];
    }
    $scheme = strtolower((string) ($p['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return [null, 'only http and https URLs are fetched'];
    }
    if (isset($p['user']) || isset($p['pass'])) {
        return [null, 'URLs with embedded credentials are refused'];
    }
    $host = (string) ($p['host'] ?? '');
    if ($host === '') {
        return [null, 'URL has no host'];
    }
    $p['scheme'] = $scheme;
    $p['port'] = (int) ($p['port'] ?? ($scheme === 'https' ? 443 : 80));
    return [$p, null];
}

/**
 * Fetch an untrusted URL. Returns [body|null, error|null].
 * Options: timeout (s, whole transfer), connect_timeout (s), max_bytes
 * (streamed cap, post-decompression), max_redirects, user_agent.
 */
function pp_http_get(string $url, array $opt = []): array
{
    $timeout   = (int) ($opt['timeout'] ?? 12);
    $connectTo = (int) ($opt['connect_timeout'] ?? 8);
    $maxBytes  = (int) ($opt['max_bytes'] ?? 10 * 1024 * 1024);
    $maxHops   = (int) ($opt['max_redirects'] ?? 5);
    $agent     = (string) ($opt['user_agent'] ?? 'PrairieDispatch/1.0 (+news reader)');

    $deadline = microtime(true) + $timeout;
    for ($hop = 0; ; $hop++) {
        [$p, $err] = pp_http_check_url($url);
        if ($err !== null) {
            return [null, $err . ($hop ? " (redirect hop $hop)" : '')];
        }
        [$ips, $err] = pp_http_resolve((string) $p['host']);
        if ($err !== null) {
            return [null, $err . ($hop ? " (redirect hop $hop)" : '')];
        }

        $remaining = (int) ceil($deadline - microtime(true));
        if ($remaining <= 0) {
            return [null, 'request timed out'];
        }

        $body = '';
        $overflow = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false,           // every hop revalidated here
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT        => min($remaining, $timeout),
            CURLOPT_CONNECTTIMEOUT => $connectTo,
            CURLOPT_USERAGENT      => $agent,
            CURLOPT_ENCODING       => '',              // decompress; the cap below sees decompressed bytes
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROXY          => '',              // a proxy would resolve names itself and undo the pinning
            // Pin the connection to the addresses that passed validation;
            // hostname, SNI and certificate checks are unaffected.
            CURLOPT_RESOLVE        => [$p['host'] . ':' . $p['port'] . ':' . implode(',', $ips)],
            CURLOPT_WRITEFUNCTION  => function ($c, $chunk) use (&$body, &$overflow, $maxBytes) {
                $body .= $chunk;
                if (strlen($body) > $maxBytes) {
                    $overflow = true;
                    return -1; // aborts the transfer
                }
                return strlen($chunk);
            },
        ]);
        $okExec = curl_exec($ch);
        $curlErr = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $location = curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: null;
        curl_close($ch);

        if ($overflow) {
            return [null, 'response larger than ' . $maxBytes . ' bytes'];
        }
        if ($okExec === false && $code === 0) {
            return [null, $curlErr !== '' ? $curlErr : 'request failed'];
        }

        if (in_array($code, [301, 302, 303, 307, 308], true) && $location !== null) {
            if ($hop + 1 > $maxHops) {
                return [null, 'too many redirects'];
            }
            $url = $location; // CURLINFO_REDIRECT_URL is already absolute
            continue;
        }
        if ($code >= 400) {
            return [null, 'HTTP ' . $code];
        }
        return [$body, null];
    }
}
