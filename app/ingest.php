<?php
/**
 * The ingest endpoints' shared ground: who is calling, what they may do,
 * and how they are answered.
 *
 * Four endpoints answer the ingest bearer token —
 *
 *   POST /api/ingest          ingest.php          file one story
 *   POST /api/ingest-media    ingest-media.php    upload a featured image
 *   GET  /api/ingest-queue    ingest-queue.php    stories waiting for art
 *   POST /api/ingest-publish  ingest-publish.php  attach the art and publish
 *
 * — and each used to carry its own copy of the token check, its own reading
 * of the token's papers, desks and draft_only flag, and its own JSON
 * emitter. A rule added to three copies and not the fourth is how the
 * publish queue went on answering draft-only tokens until review. The
 * token is turned into an agent HERE and nowhere else:
 *
 *   pp_ingest_authenticate()     bearer token -> agent, or 401
 *   pp_ingest_require_publish()  403 for an agent that may not publish
 *   pp_ingest_respond()          the one JSON emitter
 *
 * What stays in each endpoint is what is its own: the method it accepts,
 * its payload rules, the order of its checks and the wording of its
 * answers. tests/ingest-auth.test.php holds every endpoint to the same
 * credential rules and fails when an endpoint exists that it has no row
 * for.
 *
 * Loaded by those four files only — not from app/bootstrap.php, so no page
 * request pays for it. Two neighbours deliberately do not use it, because
 * they authenticate differently: monitor-ingest.php (one network token held
 * in the hub's settings; it has its own emitter, named pp_ingest_out, which
 * is why the one here is not) and media-gateway.php (the media_clients
 * table, with operation scopes).
 */

/** Emit one JSON answer and stop. Every ingest answer leaves through here. */
function pp_ingest_respond(int $code, array $body): never
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Authenticate the request's bearer token, failing closed: no usable
 * Authorization header, a token in no row, or a row that is not enabled
 * each end the request with 401 before the endpoint does anything else.
 *
 * The agent that comes back is everything an endpoint may know about the
 * credential:
 *
 *   id           ingest_agents.id
 *   name         the token's name — it signs filings (posts.filed_by) and
 *                audit rows
 *   sites        slugs of the papers it may write to; empty means none
 *   desks        slugs of the desks it is held to; empty means any desk
 *   may_publish  false for a draft-only token
 *
 * may_publish is read from the token row and nowhere else: a request can
 * neither claim the capability nor shed the restriction. It is stated
 * positively so that code which misreads it fails closed — a key that is
 * missing or misspelt is not `true`.
 *
 * @return array{id: int, name: string, sites: list<string>, desks: list<string>, may_publish: bool}
 */
function pp_ingest_authenticate(): array
{
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer\s+(\S{20,200})$/i', $auth, $m)) {
        pp_ingest_respond(401, ['ok' => false, 'error' => 'missing bearer token']);
    }
    $stmt = db()->prepare('SELECT * FROM ingest_agents WHERE token_hash = ?');
    $stmt->execute([hash('sha256', $m[1])]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['enabled'] !== 1) {
        // A revoked agent and an unknown token answer identically.
        pp_ingest_respond(401, ['ok' => false, 'error' => 'unknown or revoked token']);
    }
    $list = static fn (string $csv): array => array_values(array_filter(array_map('trim', explode(',', $csv))));
    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'sites' => $list((string) $row['sites']),
        'desks' => $list((string) $row['desks']),
        'may_publish' => (int) ($row['draft_only'] ?? 0) !== 1,
    ];
}

/**
 * Refuse a publishing step to an agent that may not publish; return
 * normally for one that may.
 *
 * Where an endpoint calls this is the endpoint's decision and part of its
 * contract: the queue refuses straight after authentication, while
 * attach-and-publish refuses only once the request is known to name a
 * waiting story in the token's scope, so that its 422, 404, 403 and 409
 * answers come first for every token alike. (POST /api/ingest never calls
 * it: a draft-only token is not refused there, its filing is kept as a
 * draft.)
 *
 * $refusal is the endpoint's own 403 body — `error`, `reason`, and
 * whatever identifies the story — sent with `ok: false` in front.
 *
 * $audit, when given, is written first:
 * ['site_id' => int, 'action' => string, 'target' => string,
 *  'detail' => string, 'post_id' => int]. The row goes through
 * pp_ingest_audit(), which cannot throw, so it can never turn the 403 into
 * something else.
 */
function pp_ingest_require_publish(array $agent, array $refusal, ?array $audit = null): void
{
    if (($agent['may_publish'] ?? false) === true) {
        return;
    }
    if ($audit !== null) {
        pp_ingest_audit((int) $audit['site_id'], (string) $agent['name'], (string) $audit['action'],
            (string) $audit['target'], (string) $audit['detail'], (int) ($audit['post_id'] ?? 0));
    }
    pp_ingest_respond(403, ['ok' => false] + $refusal);
}
