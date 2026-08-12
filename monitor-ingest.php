<?php
/**
 * POST /api/monitor — the contract with the external scraping agent.
 *
 * The agent stays outside: it never touches Postgres, it just delivers JSON
 * here with a bearer token from the hub's Settings. Validation and
 * de-duplication live in one place, on our side.
 *
 * Request:  Authorization: Bearer <monitor_token>
 *           body — a JSON array of items (or {"items": [...]}), each:
 *           {source, level, region, doc_type, title, url,
 *            summary?, body_excerpt?, published_at?}
 *           level ∈ federal|provincial|municipal|agency;
 *           doc_type ∈ release|gazette|order-in-council|hansard|bill|tender|
 *                      agenda|minutes|decision|report|other.
 * Response: {ok, added, duplicates, rejected, errors: [{index, reason}]}
 *
 * Unknown vocabulary is rejected per item with a reason, never coerced — the
 * response tells the agent's author exactly what to fix.
 */
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function pp_ingest_out(int $code, array $body): never
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

if (!pp_is_hub()) {
    pp_ingest_out(404, ['ok' => false, 'error' => 'not found']);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    pp_ingest_out(405, ['ok' => false, 'error' => 'POST a JSON array of items']);
}

$token = trim((string) setting('monitor_token', ''));
if ($token === '') {
    pp_ingest_out(503, ['ok' => false, 'error' => 'ingest is not enabled — generate a token in the hub Settings']);
}
$auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) || !hash_equals($token, $m[1])) {
    pp_ingest_out(401, ['ok' => false, 'error' => 'missing or wrong bearer token']);
}

$raw = file_get_contents('php://input', false, null, 0, 4 * 1024 * 1024);
$data = json_decode((string) $raw, true);
if (is_array($data) && isset($data['items']) && is_array($data['items'])) {
    $data = $data['items'];
}
if (!is_array($data) || $data === [] || !array_is_list($data)) {
    pp_ingest_out(400, ['ok' => false, 'error' => 'body must be a non-empty JSON array of items (or {"items": [...]})']);
}
if (count($data) > 200) {
    pp_ingest_out(400, ['ok' => false, 'error' => 'at most 200 items per call — send batches']);
}

$levels   = array_keys(pp_monitor_levels());
$doctypes = array_keys(pp_monitor_doctypes());
$added = 0;
$dupes = 0;
$errors = [];

foreach ($data as $i => $item) {
    $reject = function (string $why) use (&$errors, $i) {
        $errors[] = ['index' => $i, 'reason' => $why];
    };
    if (!is_array($item)) {
        $reject('item is not an object');
        continue;
    }
    $title = trim((string) ($item['title'] ?? ''));
    $url   = trim((string) ($item['url'] ?? ''));
    $level = strtolower(trim((string) ($item['level'] ?? '')));
    $doc   = strtolower(trim((string) ($item['doc_type'] ?? '')));
    if ($title === '') {
        $reject('title is required');
        continue;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $reject('url must be http(s)');
        continue;
    }
    if (!in_array($level, $levels, true)) {
        $reject("level must be one of: " . implode('|', $levels));
        continue;
    }
    if (!in_array($doc, $doctypes, true)) {
        $reject("doc_type must be one of: " . implode('|', $doctypes));
        continue;
    }
    $when = null;
    if (!empty($item['published_at'])) {
        $ts = strtotime((string) $item['published_at']);
        if ($ts === false) {
            $reject('published_at is not a readable date — RFC 3339 works best');
            continue;
        }
        $when = date('Y-m-d H:i:s', $ts);
    }
    $result = pp_monitor_add_item([
        'source'       => (string) ($item['source'] ?? ''),
        'level'        => $level,
        'region'       => (string) ($item['region'] ?? ''),
        'doc_type'     => $doc,
        'title'        => $title,
        'url'          => $url,
        'summary'      => (string) ($item['summary'] ?? ''),
        'body_excerpt' => (string) ($item['body_excerpt'] ?? ''),
        'published_at' => $when,
    ]);
    $result === 'added' ? $added++ : $dupes++;
}

pp_ingest_out(200, [
    'ok'         => true,
    'added'      => $added,
    'duplicates' => $dupes,
    'rejected'   => count($errors),
    'errors'     => $errors,
]);
