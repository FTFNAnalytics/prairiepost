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

## Responses you must handle

| Code | Meaning |
| --- | --- |
| 201 | Created (draft). `slug` and `id` identify it in the newsroom. |
| 200 + `duplicate` | This exact story was already filed; nothing new was created. |
| 401 | Missing, unknown or revoked key. |
| 403 | The key is not scoped to that site or desk. |
| 422 | The payload broke a rule; `error` says which. Fix and resend. |
| 429 | Hourly rate limit; wait and resend. |

## Operational notes (network side)

- Routes live in three homes: `router.php`, `.htaccess`, and each
  paper's nginx block. A new paper's block comes from
  `tools/vps/make-vhost.sh`; legacy blocks need `/api/ingest` and
  `/api/ingest-media` added in a routes pass. `/ingest.php` and
  `/ingest-media.php` answer everywhere regardless.
- Key management UI: hub `/admin/api-keys.php` (admins). CLI
  equivalent: `php tools/make-agent.php` from a release directory.
- Every filing and upload writes an `audit_log` row naming the key.
