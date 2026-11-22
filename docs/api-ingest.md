# The ingest API — filing stories and featured images by agent

Every paper on the network exposes the same two endpoints. A bearer
key authenticates both; keys are minted on the CivisMedia control
room's **API keys** page (hub admins) or by `tools/make-agent.php` on
the server — the two are interchangeable, and the database stores only
a SHA-256 of the key.

**Everything filed through this API lands as a DRAFT** in the paper's
newsroom, behind the same publish gate as any other story. (The one
exception is a desk a paper's editors have listed in their own
`wire_desks` setting — an editorial choice per paper, not a property
of the key.)

A key is scoped to one or more papers, and optionally to desks. A key
for paper A answers 403 on paper B. Revocation (one click on the API
keys page) takes effect on the key's next request, identically to a
key that never existed.

**Draft-only keys.** A key minted draft-only (the "Draft-only" box on
the API keys page, or `tools/make-agent.php create … --draft-only`) can
file and upload but never publish. For such a key the server withholds
both publish instructions on every path that can set a story live: a
desk in the paper's `wire_desks` still files a *draft*, the
`publish_on_image` flag is not honoured (the story is never queued for
the image agent), and `POST /api/ingest-publish` refuses it with 403,
leaving the story untouched. Each such response says so —
`"status":"draft"` plus a `reason` string. The publish queue is closed
to it as well: `GET /api/ingest-queue` answers 403, so a draft-only key
cannot read the headlines and ledes of stories waiting to be published.
Only the paper's newsroom publishes what a draft-only key files. This is the credential an external evidence feed
holds before it may deliver to any desk (milestone NEWS1); the
capability lives on the key, never in a request.

## 1. Upload the featured image (optional): `POST /api/ingest-media`

Send the bytes either as `multipart/form-data` (field `file`, optional
field `name` to seed the stored filename) or as the raw request body:

    curl -X POST https://<paper-domain>/api/ingest-media \
      -H "Authorization: Bearer $KEY" \
      -H "Content-Type: image/png" \
      --data-binary @featured.png

    → 201 {"ok":true,"path":"/uploads/2026/09/featured-1a2b3c.png","bytes":48210}

Rules the server enforces: JPEG, PNG, WebP or GIF only, sniffed from
the bytes and verified to decode as an image (a renamed file is
refused); 8 MB cap; the server names the stored file; at most 60
uploads per key per hour. Uploads land under `/uploads/`, where no
vhost will ever execute PHP.

## 2. File the story: `POST /api/ingest`

    curl -X POST https://<paper-domain>/api/ingest \
      -H "Authorization: Bearer $KEY" \
      -H "Content-Type: application/json" \
      -d '{
        "site": "<site-slug>",
        "desk": "local-news",
        "title": "…",
        "lede": "…",
        "body": "<p>…</p>",
        "image": "/uploads/2026/09/featured-1a2b3c.png",
        "image_caption": "…",
        "image_credit": "…",
        "dateline": "OTTAWA",
        "tags": "council, transit",
        "external_id": "your-cms-id-123",
        "source_story_id": "noon-2026-11-20-001",
        "sources": [{"url": "https://…", "title": "…"}]
      }'

    → 201 {"ok":true,"id":123,"slug":"…","status":"draft"}

    → 201 {"ok":true,"id":123,"slug":"…","status":"draft",
           "reason":"draft_only token: this credential files drafts for the
                     newsroom to publish (wire-desk auto-publish withheld)"}
      — a draft-only key, whatever the desk or flag asked for

- `image` is either an `/uploads/…` path returned by step 1, or an
  https URL the **server** fetches itself (through the SSRF guard —
  private addresses are refused). An image failure never sinks the
  filing; the draft lands without a picture and the response says why.
- The server owns the slug (auto-suffixed on collision — a filing is
  never silently skipped), the byline (the paper's `automated_byline`
  setting), sanitization (HTML Purifier over `body`), and the audit
  row.
- `external_id` makes re-files idempotent: an exact re-file answers
  `200 {"ok":true,"duplicate":true,…}` instead of creating a copy.
- `source_story_id` (optional, ≤ 120 visible characters) is the
  stronger form: your stable id for the source story, unique per
  paper and **enforced by the database** — `UNIQUE (site,
  source_story_id)` — so two simultaneous submissions create exactly
  one article, whichever request wins. An **identical** retry answers
  the original receipt: `200 {"ok":true,"duplicate":true,"id":…,
  "slug":…,"status":…}` (plus `url` once published). A **different**
  payload under an already-used id answers
  `409 {"ok":false,"error":…}` and changes nothing — an editor's
  edits are never overwritten from this path. Do not guess ids for
  stories filed before ids existed: look them up (section 5) instead.
  Genuine updates stay on the newsroom's editorial path, exactly as
  for `external_id` below.
- **There is no update call.** Once a story is filed it belongs to the
  newsroom: a re-file with the same `external_id` is confirmed against
  the stored story and changes nothing in it, even after an editor has
  rewritten it (the answer carries the story's current `slug`). When
  what you filed has changed, file a **new** draft under a new
  `external_id` that names the earlier story by its `id` and says what
  changed. The earlier story, its revision history, the editor's note
  and its tags are left exactly as they were, whether it is still a
  draft or already published; a correction reaches readers only when
  an editor records it on the story. `tests/correction-note.test.php`
  holds this.
- Rate limit: at most 60 filings per key per hour by default (the
  paper's `ingest_hourly_limit` setting).
- `"publish_on_image": true` is the filing agent's **ready checkbox**:
  this copy is approved and publishes the moment the story has a
  featured image. With an `image` in the same filing it publishes
  immediately (the response carries `url`); without one it lands as a
  draft with `"awaiting_image": true` and waits in the queue below.

## 3. The image agent's queue: `GET /api/ingest-queue`

Lists the stories flagged `publish_on_image` that are still waiting
for art, for the papers this key is scoped to — id, slug, site, desk,
title, lede, dateline, oldest first. This is the ingest surface's only
read, and it reads only pipeline state these keys created.

    curl https://<paper-domain>/api/ingest-queue \
      -H "Authorization: Bearer $KEY"

    → 200 {"ok":true,"stories":[{"id":123,"slug":"…","site":"…",
           "desk":"…","title":"…","lede":"…",…}]}

A draft-only key is refused here. The queue is the worklist of the
agent that publishes, which that key cannot do, and its rows are other
filers' unpublished headlines and ledes:

    → 403 {"ok":false,"error":"this token is draft-only — …",
           "reason":"draft_only token: it cannot publish, so it cannot
                     read the stories waiting to be published"}

It is a refusal rather than an empty list on purpose: a key of the
wrong kind shows up at once instead of looking like a queue with
nothing in it. A missing, unknown or revoked key answers 401 first, as
everywhere else.

## 4. Attach the art and publish: `POST /api/ingest-publish`

    curl -X POST https://<paper-domain>/api/ingest-publish \
      -H "Authorization: Bearer $KEY" \
      -H "Content-Type: application/json" \
      -d '{"story":"<slug-or-id>",
           "image":"/uploads/2026/09/…png",
           "image_caption":"…","image_credit":"…"}'

    → 200 {"ok":true,"id":123,"slug":"…","status":"published",
           "url":"https://<canonical-domain>/story/…","image":"…"}

Attaching and publishing happen in ONE guarded write, and only on a
story that was filed with `publish_on_image` and is still waiting —
anything else answers 409, so an ordinary newsroom draft can never be
published from this lane. The `url` in the response is the published
address, ready for the social posts that follow.

A draft-only key is refused here. The capability is checked once the
request is known to name a waiting story in the key's scope, so a
malformed request, an unknown story, a story on another paper or one
that is not waiting answers 422, 404, 403 or 409 first, exactly as it
does for any key. The refusal itself:

    → 403 {"ok":false,"error":"this token is draft-only — …",
           "id":123,"slug":"…","status":"draft",
           "reason":"draft_only token: attach-and-publish refused, nothing written"}

The story is not touched: the art is not attached and it keeps waiting
for a key that may publish, or for its newsroom. The refusal is
recorded in the paper's audit log.

## 5. Has this story already been filed? `POST /api/ingest/lookup` (read-only)

For a filing job that must not refile what the newsroom already has —
whether it arrived through this API, was hand-entered by an editor, or
predates stable story ids. **Separate permission**: the key must be
minted with the `article:lookup` grant (`lookup_scope`): `own` answers
over the key's own filings plus published stories; `all` answers over
the full editorial queue — ordinary drafts, review, scheduled and
published, whoever created them — within the key's papers and desks,
and is what finding editor-created or other filers' drafts requires. A
key without the grant is refused 403 outright.

    curl -X POST https://<paper-domain>/api/ingest/lookup \
      -H "Authorization: Bearer $KEY" \
      -H "Content-Type: application/json" \
      -d '{
        "site": "<site-slug>",
        "desk": "local-news",
        "headline": "Council weighs the harbour cleanup",
        "source_story_id": "noon-2026-11-20-001",
        "source_url": "https://city.example/agenda/4411"
      }'

    → 200 {"ok":true,"result":"match",
           "article":{"ref":"ar_…","status":"published",
                      "url":"https://<paper-domain>/story/…"}}
    → 200 {"ok":true,"result":"review_required"}
    → 200 {"ok":true,"result":"not_found"}

- **Scope is one paper and one desk per call**, checked three ways on
  the server: the `site` must be among the key's papers, must be the
  paper the request's hostname serves, and the `desk` must be within
  the key's desks. Every scope failure answers the same
  `403 {"ok":false,"error":"out of scope"}`, whether or not the site,
  desk or story exists — an out-of-scope request learns nothing.
- `source_story_id` is checked **first**, against the receipts the
  filing path stores, regardless of artwork or editorial status. Only
  then the bounded legacy comparison: the **exact normalized
  headline** (lowercased, whitespace collapsed) together with the
  **canonical primary-source URL** where both sides have one. A URL
  alone, or a merely similar headline, is never a match.
- `review_required` means legacy coverage could not be ruled out —
  exact headline but no URL to confirm, conflicting URL evidence,
  more than one story carrying full evidence (ambiguity never picks
  a winner), a receipt whose article an editor moved to another desk
  or off the paper, or a scan that hit its bound. Hand it to a
  person; do not file.
- For a verified match the answer is **small and closed**: an opaque
  `ref` (stable per article, resolvable only by the server), the
  editorial `status` (`draft`, `scheduled`, `published`), and the
  public `url` when published. Never draft copy, editor details, or
  any listing.
- This endpoint **writes nothing** — no audit row, not even
  `last_used_at` — and no answer from it authorizes a filing: auth
  errors, timeouts and ambiguous matches must be treated as "do not
  file"; if you file anyway, the `source_story_id` uniqueness above is
  what still guarantees one article. `/api/ingest-queue` remains the
  artwork queue, unchanged.

## 6. Update a published story: `POST /api/ingest/update`

Changes the **headline, lede, body and featured image** (with its
caption and credit) of a story that is **already published**. This is
the one exception to "a published story changes only at an editor's
desk", and it is a **separate grant**: the key must be minted with
`update_published` (`tools/make-agent.php --update-published`, or the
"May update published stories" box on the hub key form). A key without
it is refused 403 before anything is read. The grant is independent of
draft-only: a draft-only key holding it still publishes nothing new,
but can edit what is already live.

    curl -X POST https://<paper-domain>/api/ingest/update \
      -H "Authorization: Bearer $KEY" \
      -H "Content-Type: application/json" \
      -d '{
        "site": "<site-slug>",
        "desk": "local-news",
        "ref": "ar_…",
        "title": "Updated headline",
        "body": "<p>Updated copy…</p>",
        "image": "/uploads/2026/11/new-photo-1a2b3c.jpg",
        "image_caption": "…",
        "image_credit": "…"
      }'

    → 200 {"ok":true,"ref":"ar_…","status":"published",
           "url":"https://<paper-domain>/story/…",
           "updated":["title","body","image","image_caption","image_credit"],
           "version":"v_…","previous_image":"/uploads/…"}
    → 200 {"ok":true,"unchanged":true,"ref":…,"status":"published",
           "url":…,"version":…}   — nothing differed; nothing written

- **Which story:** send `ref` (from `/api/ingest/lookup`) **or** `url`
  (the story's public address on this paper) — exactly one. The story
  must be on the requested `desk`.
- **Send only what changes.** Fields: `title`, `lede`, `body`, `image`,
  `image_caption`, `image_credit`. `body` is sanitized like any editor
  save. `image` is an `/uploads/…` path from `/api/ingest-media` —
  upload first. A **new** image replaces the caption and credit with
  what this request sends (empty when omitted): the old ones describe
  a different picture.
- **Never changes:** the URL (slug — a new headline keeps the old
  address), status, publish date, byline, desk, papers, tags, meta
  description, or the correction note. Corrections are still recorded
  by an editor.
- **Only published stories.** Drafts, review and scheduled stories are
  the newsroom's: `409`.
- **Scope** is exactly lookup's: the key's papers, the paper this
  hostname serves, the key's desks; every scope failure is the same
  `403 {"ok":false,"error":"out of scope"}`. A story another paper
  also carries is refused (`409`) — it is that paper's page too.
- **History and audit.** The state before the update is written into
  the story's revision history (when history does not already hold
  it), and so is every agent change, signed `hermes:<key>`. An editor
  can restore any earlier version from the story's history page —
  picture, caption **and credit** come back together. Every update is
  also an audit row naming the key.
- **Concurrency.** The write is a compare-and-swap on what the request
  read: if an editor saves or unpublishes the story mid-request,
  nothing is written and you get `409`. To refuse to overwrite
  anything changed since **your** last look, send the `version` from
  your previous response as `if_version`; a mismatch is `409` with the
  current `version`. Review before resending.
- Rate limit: 60 updates per key per hour (`429`).

## 7. Read a paper's stories: `GET /api/ingest/stories`

Lists a paper's stories, or returns one in full. **Separate grant**,
set at minting (`--read published|all`, or the hub key form's "Read
stories" select): `published` reads the paper's live stories; `all`
reads every story in the editorial queue too — drafts, review and
scheduled, whoever made them, editors included. A key without it is
refused 403. This is the read half of section 6: read a story, edit
it, and send its `version` back as `if_version`.

    # The list (newest first, 50 by default, at most 100 per page)
    curl "https://<paper-domain>/api/ingest/stories?site=<site-slug>&status=all&limit=50" \
      -H "Authorization: Bearer $KEY"

    → 200 {"ok":true,"stories":[
             {"ref":"ar_…","status":"published","desk":"politics","title":"…",
              "url":"https://<paper-domain>/story/…","published_at":"…",
              "updated_at":"…","image":"/uploads/…","version":"v_…"},
             {"ref":"ar_…","status":"draft","desk":"local-news","title":"…",
              "published_at":null,"updated_at":"…","image":"","version":"v_…"}],
           "next":"c_…"}

    # One story, in full — by ref or by public URL
    curl "https://<paper-domain>/api/ingest/stories?site=<site-slug>&ref=ar_…" \
      -H "Authorization: Bearer $KEY"

    → 200 {"ok":true,"story":{"ref":"ar_…","status":"published","desk":"…",
           "title":"…","lede":"…","body":"<p>…</p>","byline":"…","dateline":"…",
           "image":"/uploads/…","image_caption":"…","image_credit":"…",
           "correction":"…","tags":["…"],"sources":["https://…"],
           "source_story_id":"…","url":"https://…","published_at":"…",
           "updated_at":"…","version":"v_…"}}

- **Filters (list):** `desk` (one desk; omitted = every desk the key
  holds), `status` = `published` | `draft` | `in_review` |
  `scheduled` | `unpublished` (the three not-yet-live states) | `all`
  (default — everything the grant covers), `updated_since`
  (`YYYY-MM-DD HH:MM:SS`, for syncing), `limit` (1–100), and `cursor`
  — pass the previous page's `next` until it is absent.
- **Scope** is the other reads': the key's papers, the paper this
  hostname serves, the key's desks — and the statuses the grant
  covers. Asking outside it is the same `403 {"ok":false,"error":"out
  of scope"}`. A story the key may not read answers `404`, exactly as
  one that does not exist.
- **What it never returns:** author accounts, editor names, review
  notes, the filing key's name, internal ids, or a draft's slug. Only
  published stories carry a `url`. `source_story_id` appears when the
  story was filed with one.
- **Writes nothing** — no audit row, not even `last_used_at`.

## Responses you must handle

| Code | Meaning |
| --- | --- |
| 201 | Created (draft). `slug` and `id` identify it in the newsroom. |
| 200 + `duplicate` | This exact story was already filed; nothing new was created. |
| 401 | Missing, unknown or revoked key. |
| 403 | The key is not scoped to that site or desk — or the key is draft-only: on `/api/ingest-publish` the body carries `status: "draft"` and `reason` and the story is untouched; on `/api/ingest-queue` the body carries `reason` and no stories. On `/api/ingest/lookup`, `/api/ingest/update` and `/api/ingest/stories`: the key lacks that endpoint's grant, or the request is out of scope (`"out of scope"`, revealing nothing). |
| 404 | `/api/ingest/update` and `/api/ingest/stories`: no story with that reference that this key may see (absent and out-of-reach answer alike). |
| 409 | `/api/ingest-publish`: the story is not waiting (already published, unflagged, or changed state mid-request) — fetch the queue again. `/api/ingest`: the `source_story_id` was already filed with different content — the stored article is untouched; corrections go through the newsroom. `/api/ingest/update`: the story is not published, is carried by another paper, changed mid-request, or no longer matches your `if_version` — nothing was written. |
| 422 | The payload broke a rule; `error` says which. Fix and resend. |
| 429 | Hourly rate limit; wait and resend. |

## Operational notes (network side)

- Routes live in three homes: `router.php`, `.htaccess`, and each
  paper's nginx block. A new paper's block comes from
  `tools/vps/make-vhost.sh`; legacy blocks need `/api/ingest`,
  `/api/ingest-media`, `/api/ingest-queue`, `/api/ingest-publish`,
  `/api/ingest/lookup`, `/api/ingest/update` and
  `/api/ingest/stories` added in a routes pass. The bare `.php`
  endpoints answer everywhere regardless.
- The seven endpoints share one token check: `pp_ingest_authenticate()`
  in `app/ingest.php` turns the bearer key into an agent (id, name,
  papers, desks, whether it may publish), and
  `pp_ingest_require_publish()` is the 403 a draft-only key gets. A new
  ingest endpoint calls them rather than reading the key itself, and
  gets a row in `tests/ingest-auth.test.php` saying what a revoked key
  and a draft-only key receive from it; that test fails while an
  `ingest*.php` file or an `/api/ingest…` route exists that it does not
  cover.
- Key management UI: hub `/admin/api-keys.php` (admins). CLI
  equivalent: `php tools/make-agent.php` from a release directory.
- Every filing and upload writes an `audit_log` row naming the key.
