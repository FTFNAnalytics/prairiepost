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

## Responses you must handle

| Code | Meaning |
| --- | --- |
| 201 | Created (draft). `slug` and `id` identify it in the newsroom. |
| 200 + `duplicate` | This exact story was already filed; nothing new was created. |
| 401 | Missing, unknown or revoked key. |
| 403 | The key is not scoped to that site or desk — or the key is draft-only: on `/api/ingest-publish` the body carries `status: "draft"` and `reason` and the story is untouched; on `/api/ingest-queue` the body carries `reason` and no stories. |
| 409 | `/api/ingest-publish` only: the story is not waiting (already published, unflagged, or changed state mid-request). Fetch the queue again. |
| 422 | The payload broke a rule; `error` says which. Fix and resend. |
| 429 | Hourly rate limit; wait and resend. |

## Operational notes (network side)

- Routes live in three homes: `router.php`, `.htaccess`, and each
  paper's nginx block. A new paper's block comes from
  `tools/vps/make-vhost.sh`; legacy blocks need `/api/ingest`,
  `/api/ingest-media`, `/api/ingest-queue` and `/api/ingest-publish`
  added in a routes pass. The bare `.php` endpoints answer everywhere
  regardless.
- Key management UI: hub `/admin/api-keys.php` (admins). CLI
  equivalent: `php tools/make-agent.php` from a release directory.
- Every filing and upload writes an `audit_log` row naming the key.
