<?php
/**
 * PATCH /api/ingest/drafts/{ref} — edit an EXISTING draft in place.
 *
 * The lane for "add a cover to the story that is already in the queue"
 * without refiling it: an agent finds the draft with GET
 * /api/ingest/stories (a key with read_scope 'all' sees editors' drafts
 * too), uploads the picture to /api/ingest-media, and PATCHes the draft
 * with the returned path and the reader's version. The story keeps its id,
 * slug, author, status and place in the newsroom's queue.
 *
 *   - Only a key minted with the edit_drafts grant may call it
 *     (tools/make-agent.php --edit-drafts, or the hub key form). Default
 *     off; refused outright, before anything is read.
 *   - Only a story whose status is exactly 'draft'. In review, scheduled
 *     and published stories are 409 — published ones change through
 *     POST /api/ingest/update, under its own grant.
 *   - It never publishes, schedules, approves, submits for review, sets or
 *     clears awaiting-image, changes the desk, the papers, the slug or the
 *     article id. There is no field for any of them: anything outside
 *     title, lede, body, image, image_alt, image_caption, image_credit,
 *     tags, sources and meta_description is 422.
 *   - Scope as every ingest lane: the key's papers, the paper this hostname
 *     serves, the key's desks, and the draft on the requested desk.
 *   - if_version is REQUIRED and rechecked under the write lock; the
 *     story row, tags, sources, revisions and audit row commit together.
 *
 * The rules live in app/ingest-edit.php, shared with the published lane,
 * and the contract in docs/api-ingest.md §8.
 *
 * Request:  PATCH /api/ingest/drafts/ar_<24 hex>
 *           Authorization: Bearer <token>
 *           JSON body: {site, desk, if_version, ...fields}
 * Response: 200 {ok, ref, status: "draft", desk, updated: [...], version,
 *                previous_image?, awaiting_image?}
 *           200 {ok, unchanged: true, ref, status, desk, version}
 *           401/403/404/405/409/422/429 {ok: false, error, ...}
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/ingest.php';
require __DIR__ . '/app/ingest-edit.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'PATCH') {
    pp_ingest_respond(405, ['ok' => false, 'error' => 'PATCH one draft edit per call, at /api/ingest/drafts/{ref}']);
}

/* --- Authentication: token -> agent, fail closed (app/ingest.php) --------- */
$agent = pp_ingest_authenticate();

// The reference is the last segment of the path the client asked for —
// read from the original request line, so no query string can stand in
// for it, whichever server rewrote the route here.
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if (!preg_match('#^/api/ingest/drafts/([^/]+)/?$#', $path, $m)) {
    pp_ingest_respond(404, ['ok' => false, 'error' => 'edit a draft at PATCH /api/ingest/drafts/{ref}']);
}

pp_ingest_edit($agent, 'draft', rawurldecode($m[1]));
