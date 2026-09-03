<?php
/**
 * GET/POST /api/media — provider-scoped Pantheon gateway.
 *
 * Query actions: catalog, submit, request, cancel, order, order-status,
 * metrics. The production
 * web server maps /api/media to this file, just as /api/ingest maps to
 * ingest.php. Tokens are issued by tools/make-media-client.php, hashed at
 * rest, and carry explicit operation scopes.
 */
require __DIR__ . '/app/bootstrap.php';

if (!pp_is_hub()) {
    http_response_code(404);
    exit('Not found.');
}

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function pp_media_gateway_out(int $code, array $body): never
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

function pp_media_gateway_client(string $scope): array
{
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer\s+(\S{20,200})$/i', $auth, $m)) {
        pp_media_gateway_out(401, ['ok' => false, 'error' => 'missing bearer token']);
    }
    $stmt = db()->prepare('SELECT * FROM media_clients WHERE token_hash = ?');
    $stmt->execute([hash('sha256', $m[1])]);
    $client = $stmt->fetch();
    if (!$client || (int) $client['enabled'] !== 1) {
        pp_media_gateway_out(401, ['ok' => false, 'error' => 'unknown or revoked token']);
    }
    if (!pp_media_scope_allows($client, $scope)) {
        pp_media_gateway_out(403, ['ok' => false, 'error' => "token is not scoped for {$scope}"]);
    }
    db()->prepare('UPDATE media_clients SET last_used_at = ? WHERE id = ?')->execute([now(), (int) $client['id']]);
    return $client;
}

function pp_media_gateway_body(): array
{
    $limit = 1024 * 1024;
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $limit) {
        throw new PPMediaError(413, 'body exceeds 1 MiB');
    }
    $raw = file_get_contents('php://input', false, null, 0, $limit + 1);
    if (strlen((string) $raw) > $limit) {
        throw new PPMediaError(413, 'body exceeds 1 MiB');
    }
    $body = json_decode((string) $raw, true);
    if (!is_array($body)) {
        throw new PPMediaError(400, 'body must be a JSON object');
    }
    return $body;
}

$action = (string) ($_GET['action'] ?? '');
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$scope = match ($action) {
    'catalog' => 'catalog',
    'submit' => 'submit',
    'request' => 'status',
    'cancel' => 'cancel',
    'order' => 'order',
    'order-status' => 'status',
    'metrics' => 'metrics',
    default => '',
};
if ($scope === '') {
    pp_media_gateway_out(404, ['ok' => false, 'error' => 'unknown media gateway action']);
}
$client = pp_media_gateway_client($scope);

try {
    if ($action === 'catalog' && $method === 'GET') {
        pp_media_gateway_out(200, pp_media_catalog());
    }
    if ($action === 'submit' && $method === 'POST') {
        [$code, $receipt] = pp_media_submit(
            $client,
            pp_media_gateway_body(),
            trim((string) ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? '')),
        );
        pp_media_gateway_out($code, $receipt);
    }
    $publicRef = trim((string) ($_GET['id'] ?? ''));
    if ($action === 'order-status') {
        if ($method !== 'GET') {
            pp_media_gateway_out(405, ['ok' => false, 'error' => 'method not allowed for this action']);
        }
        if (!preg_match('/^cmo_[a-f0-9]{24}$/', $publicRef)) {
            throw new PPMediaError(404, 'media order not found');
        }
        $order = pp_media_order_by_ref($client, $publicRef);
        if (!$order) {
            throw new PPMediaError(404, 'media order not found');
        }
        pp_media_gateway_out(200, pp_media_order_receipt($order));
    }
    if (!preg_match('/^cmr_[a-f0-9]{24}$/', $publicRef)) {
        throw new PPMediaError(404, 'media request not found');
    }
    if ($action === 'order' && $method === 'POST') {
        [$code, $receipt] = pp_media_order_place(
            $client,
            $publicRef,
            pp_media_gateway_body(),
            trim((string) ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? '')),
        );
        pp_media_gateway_out($code, $receipt);
    }
    if ($action === 'request' && $method === 'GET') {
        $request = pp_media_request_by_ref($client, $publicRef);
        if (!$request) {
            throw new PPMediaError(404, 'media request not found');
        }
        pp_media_gateway_out(200, pp_media_receipt($request));
    }
    if ($action === 'cancel' && $method === 'POST') {
        $body = pp_media_gateway_body();
        $headerKey = trim((string) ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? ''));
        if (($body['schemaVersion'] ?? '') !== PP_MEDIA_SCHEMA_VERSION
            || !hash_equals((string) ($body['idempotencyKey'] ?? ''), $headerKey)) {
            throw new PPMediaError(422, 'invalid cancellation contract');
        }
        pp_media_gateway_out(200, pp_media_cancel($client, $publicRef, $headerKey));
    }
    if ($action === 'metrics' && $method === 'GET') {
        pp_media_gateway_out(200, pp_media_metrics($client, $publicRef));
    }
    pp_media_gateway_out(405, ['ok' => false, 'error' => 'method not allowed for this action']);
} catch (PPMediaError $e) {
    pp_media_gateway_out($e->httpCode, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('media gateway: ' . $e->getMessage());
    pp_media_gateway_out(500, ['ok' => false, 'error' => 'media gateway failed']);
}
