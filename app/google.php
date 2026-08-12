<?php
/**
 * Google Analytics 4 + Search Console, in plain PHP — one service account,
 * added as Viewer on each GA4 property and Restricted on each Search
 * Console property. No OAuth dance, no SDK: an RS256-signed JWT
 * (openssl_sign) exchanged for a one-hour token.
 *
 * The service-account JSON lives OUTSIDE the web root, its path in
 * config.php ('google_sa_json'). It never enters the repository or the
 * database. Per-site property ids are ordinary settings.
 */

function pp_google_sa_path(): string
{
    return trim((string) pp_config('google_sa_json', ''));
}

function pp_google_sa(): ?array
{
    static $sa = false;
    if ($sa === false) {
        $path = pp_google_sa_path();
        $sa = null;
        if ($path !== '' && is_readable($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded) && !empty($decoded['client_email']) && !empty($decoded['private_key'])) {
                $sa = $decoded;
            }
        }
    }
    return $sa;
}

function pp_google_enabled(): bool
{
    return pp_google_sa() !== null;
}

/**
 * A one-hour access token for both read scopes, cached per request.
 * Returns [token|null, error|null].
 */
function pp_google_token(): array
{
    static $cached = null;
    static $expires = 0;
    if ($cached !== null && time() < $expires - 60) {
        return [$cached, null];
    }
    $sa = pp_google_sa();
    if ($sa === null) {
        return [null, "no service account — set google_sa_json in config.php to the JSON key's path"];
    }
    // Overridable only for a local stub or an outbound proxy — not a setting.
    $tokenUrl = trim((string) pp_config('google_token_url', '')) ?: (string) ($sa['token_uri'] ?? 'https://oauth2.googleapis.com/token');

    $b64 = fn (string $d): string => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    $now = time();
    $jwt = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly',
        'aud'   => $tokenUrl,
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));
    if (!openssl_sign($jwt, $sig, (string) $sa['private_key'], OPENSSL_ALGO_SHA256)) {
        return [null, "the private key in the service-account JSON couldn't sign (openssl)"];
    }
    $assertion = $jwt . '.' . $b64($sig);

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $assertion,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        return [null, 'token request failed: ' . ($err ?: 'network error')];
    }
    $data = json_decode((string) $body, true);
    if (empty($data['access_token'])) {
        return [null, 'token exchange refused: ' . mb_substr((string) $body, 0, 200)];
    }
    $cached = (string) $data['access_token'];
    $expires = $now + (int) ($data['expires_in'] ?? 3600);
    return [$cached, null];
}

/** POST JSON to a Google API with the bearer token. Returns [data|null, error|null]. */
function pp_google_post(string $url, array $payload): array
{
    [$token, $err] = pp_google_token();
    if ($token === null) {
        return [null, $err];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'authorization: Bearer ' . $token,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($body === false) {
        return [null, 'request failed: ' . ($err ?: 'network error')];
    }
    $data = json_decode((string) $body, true);
    if (!is_array($data)) {
        return [null, "HTTP $code with an unreadable body"];
    }
    if (isset($data['error'])) {
        $msg = is_array($data['error']) ? (string) ($data['error']['message'] ?? 'unknown') : (string) $data['error'];
        return [null, "HTTP $code: $msg"];
    }
    return [$data, null];
}

/** GA4 Data API runReport for one property (numeric id, with or without the prefix). */
function pp_ga4_run_report(string $propertyId, array $body): array
{
    $base = rtrim((string) pp_config('google_ga_base', 'https://analyticsdata.googleapis.com'), '/');
    $property = str_starts_with($propertyId, 'properties/') ? $propertyId : 'properties/' . $propertyId;
    return pp_google_post("$base/v1beta/$property:runReport", $body);
}

/** Search Console query for one property (sc-domain:example.ca or a URL prefix). */
function pp_gsc_query(string $siteUrl, array $body): array
{
    $base = rtrim((string) pp_config('google_gsc_base', 'https://www.googleapis.com'), '/');
    return pp_google_post("$base/webmasters/v3/sites/" . rawurlencode($siteUrl) . '/searchAnalytics/query', $body);
}
