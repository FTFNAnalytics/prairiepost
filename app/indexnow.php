<?php
/**
 * Automated IndexNow submission client & key-verification helper.
 *
 * Fail-closed design:
 *   - Never submits for a paper whose `indexing_enabled` setting is not '1'.
 *   - Never submits unless that paper has a valid `indexnow_key` setting.
 *   - Never submits localhost / private / .test / .local hostnames unless an
 *     explicit `indexnow_endpoint` test override is configured.
 *   - Never throws or blocks an editorial publish if IndexNow is unreachable.
 */

function pp_indexnow_valid_key(string $key): bool
{
    return preg_match('/^[a-zA-Z0-9-]{8,128}$/', $key) === 1;
}

/** Read a site's IndexNow key (validated, or '' when unset/invalid). */
function pp_indexnow_key(?int $siteId = null): string
{
    if ($siteId === null || $siteId === current_site_id()) {
        $raw = trim((string) setting('indexnow_key', ''));
    } else {
        $stmt = db()->prepare("SELECT svalue FROM settings WHERE site_id = ? AND skey = 'indexnow_key'");
        $stmt->execute([$siteId]);
        $raw = trim((string) ($stmt->fetchColumn() ?: ''));
    }
    return pp_indexnow_valid_key($raw) ? $raw : '';
}

/** Generate and store a fresh 32-hex IndexNow verification key for a site. */
function pp_indexnow_generate_key(?int $siteId = null): string
{
    $key = bin2hex(random_bytes(16));
    set_setting('indexnow_key', $key, $siteId);
    return $key;
}

/**
 * Serve GET /<key>.txt when $requestedKey matches the current site's key.
 * Returns true if served (caller exits), false to let router return 404.
 */
function pp_indexnow_serve_key(string $requestedKey): bool
{
    $key = pp_indexnow_key();
    if ($key === '' || !hash_equals($key, $requestedKey)) {
        return false;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo $key;
    return true;
}

/** Check whether a site is cleared for search-engine indexing. */
function pp_indexnow_site_indexed(int $siteId): bool
{
    $stmt = db()->prepare("SELECT svalue FROM settings WHERE site_id = ? AND skey = 'indexing_enabled'");
    $stmt->execute([$siteId]);
    return (string) ($stmt->fetchColumn() ?: '') === '1';
}

/**
 * Submit one or more canonical URLs for $siteId to IndexNow.
 * Returns ['sent' => bool, 'status' => int, 'reason' => string].
 */
function pp_indexnow_notify(array $urls, ?int $siteId = null): array
{
    $sid = $siteId ?? current_site_id();
    if (!pp_indexnow_site_indexed($sid)) {
        return ['sent' => false, 'status' => 0, 'reason' => 'indexing_disabled'];
    }
    $key = pp_indexnow_key($sid);
    if ($key === '') {
        return ['sent' => false, 'status' => 0, 'reason' => 'no_key'];
    }

    $cleanUrls = [];
    foreach ($urls as $u) {
        $u = trim((string) $u);
        if ($u !== '' && preg_match('#^https?://#i', $u)) {
            $cleanUrls[] = $u;
        }
    }
    $cleanUrls = array_values(array_unique($cleanUrls));
    if (!$cleanUrls) {
        return ['sent' => false, 'status' => 0, 'reason' => 'no_urls'];
    }

    $host = strtolower((string) parse_url($cleanUrls[0], PHP_URL_HOST));
    $endpoint = trim((string) pp_config('indexnow_endpoint', ''));
    $isCustomEndpoint = $endpoint !== '';
    if ($host === '') {
        return ['sent' => false, 'status' => 0, 'reason' => 'no_host'];
    }
    if (!$isCustomEndpoint && (
        $host === 'localhost'
        || filter_var($host, FILTER_VALIDATE_IP) !== false
        || str_ends_with($host, '.local')
        || str_ends_with($host, '.test')
        || str_ends_with($host, '.invalid')
    )) {
        return ['sent' => false, 'status' => 0, 'reason' => 'non_public_host'];
    }

    $scheme = strtolower((string) (parse_url($cleanUrls[0], PHP_URL_SCHEME) ?: 'https'));
    $port = parse_url($cleanUrls[0], PHP_URL_PORT);
    $origin = $scheme . '://' . $host . ($port ? ':' . $port : '');
    $payload = [
        'host'        => $host,
        'key'         => $key,
        'keyLocation' => $origin . '/' . $key . '.txt',
        'urlList'     => array_slice($cleanUrls, 0, 100),
    ];

    $targetUrl = $isCustomEndpoint ? $endpoint : 'https://api.indexnow.org/indexnow';
    if (!function_exists('curl_init')) {
        return ['sent' => false, 'status' => 0, 'reason' => 'no_curl'];
    }
    $ch = curl_init($targetUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $ok = $res !== false && $code >= 200 && $code < 300;
    try {
        pp_audit(
            $ok ? 'indexnow.ping' : 'indexnow.failed',
            $host,
            $ok ? count($payload['urlList']) . ' url(s) HTTP ' . $code : ('HTTP ' . $code . ($err !== '' ? ' ' . $err : ''))
        );
    } catch (Throwable) {
        // Never fail a publish because audit logging failed.
    }
    return ['sent' => $ok, 'status' => $code, 'reason' => $ok ? 'ok' : ($err ?: "http_$code")];
}

/**
 * Notify IndexNow for every indexed, keyed paper that carries $postId.
 * Returns map of [site_id => result].
 */
function pp_indexnow_notify_post(int $postId): array
{
    if ($postId <= 0) {
        return [];
    }
    $stmt = db()->prepare("SELECT id, slug, status FROM posts WHERE id = ? AND status = 'published'");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    if (!$post || trim((string) $post['slug']) === '') {
        return [];
    }
    $slug = (string) $post['slug'];
    $results = [];
    $sStmt = db()->prepare('SELECT s.id, s.slug, s.domain FROM post_sites ps JOIN sites s ON s.id = ps.site_id WHERE ps.post_id = ?');
    $sStmt->execute([$postId]);
    foreach ($sStmt->fetchAll() as $site) {
        $sid = (int) $site['id'];
        $domain = trim((string) ($site['domain'] ?? ''));
        if ($domain === '' && $sid === current_site_id()) {
            $url = site_url() . '/story/' . $slug;
        } elseif ($domain !== '') {
            $url = 'https://' . preg_replace('#^https?://#i', '', rtrim($domain, '/')) . '/story/' . $slug;
        } else {
            continue;
        }
        $results[$sid] = pp_indexnow_notify([$url], $sid);
    }
    return $results;
}
