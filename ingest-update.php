<?php
/**
 * POST /api/ingest/update — edit a story that is already PUBLISHED.
 *
 * This is the one exception, ordered by the owner in November 2026, to
 * the Phase 1 rule that a published story changes only at an editor's
 * desk. It exists because a live article sometimes needs its picture
 * swapped or its copy, captions, tags or SEO description updated by the
 * agent that filed it, without anyone signing in. The exception is kept
 * narrow on purpose:
 *
 *   - Only a key minted with the update_published grant may call it
 *     (tools/make-agent.php --update-published, or the hub key form).
 *     Default off; refused outright, before anything is read.
 *   - Only PUBLISHED stories. Drafts, review and scheduled stories stay
 *     the newsroom's (409) — drafts have their own lane and grant,
 *     PATCH /api/ingest/drafts/{ref}.
 *   - Only these fields: title, lede, body, image, image_alt,
 *     image_caption, image_credit, tags, sources, meta_description, and
 *     new_desk (a desk the key holds, on the same paper). Never the slug
 *     (the public URL is stable), status, dates, byline, papers or
 *     correction note: anything else is 422.
 *   - Scope exactly as /api/ingest/lookup: the key's papers, the paper
 *     this hostname serves, the key's desks — and the story must sit on
 *     the requested desk. Every scope failure is the same three-word 403.
 *     A story also carried by a paper the key does not cover is refused
 *     (409): changing it would change another paper's page.
 *   - if_version is REQUIRED and rechecked under the write lock; the
 *     story row, tags, sources, revisions and audit row commit together.
 *
 * The rules live in app/ingest-edit.php, shared with the draft lane, and
 * the contract in docs/api-ingest.md §6.
 *
 * Request:  Authorization: Bearer <token>
 *           JSON body: {site, desk, ref | url, if_version, ...fields}
 * Response: 200 {ok, ref, status: "published", desk, url, updated: [...],
 *                version, previous_image?}
 *           200 {ok, unchanged: true, ref, status, desk, url, version}
 *           401/403/404/409/422/429 {ok: false, error, ...}
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/ingest.php';
require __DIR__ . '/app/ingest-edit.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    pp_ingest_respond(405, ['ok' => false, 'error' => 'POST one update per call']);
}

/* --- Authentication: token -> agent, fail closed (app/ingest.php) --------- */
$agent = pp_ingest_authenticate();

pp_ingest_edit($agent, 'published');
