# The Hermes ingest contract

How a reporting agent files a story into the network. One endpoint, one
story per call, everything behind a scoped token.

## Endpoint

    POST https://<paper-domain>/api/ingest
    Authorization: Bearer <token>
    Content-Type: application/json

(`/ingest.php` is the same endpoint on server blocks that predate the
front controller.) The token comes from `tools/make-agent.php create`,
is shown exactly once, and is stored only as a hash. It is scoped to
specific papers (`--sites`) and optionally desks (`--desks`); requests
outside the scope answer 403. Revocation (`revoke`) turns the token
into a 401, indistinguishable from one that never existed.

## Payload

    {
      "site":  "bleuet-blanc",          // required, in the token's scope
      "desk":  "actualites",            // required, must already exist
      "title": "…", "lede": "…",        // required
      "body":  "<p>…</p>",              // required; sanitized server-side
      "dateline": "Québec",             // optional
      "tags": "a, b",                   // optional, at most 10
      "suggested_slug": "…",            // optional; the server decides
      "external_id": "court-2026-081",  // optional but recommended —
                                        // the idempotency key
      "sources": [                      // optional, at most 20
        {"url": "https://…", "title": "…", "retrieved_at": "2026-08-24 15:00:00"}
      ]
    }

## What the server owns (agents cannot override these)

- **Slug** — allocated server-side, auto-suffixed on collision. Silent
  skips do not exist on this path.
- **Byline** — the site's `automated_byline` setting. An agent cannot
  sign a story as a person.
- **Status** — `draft`, behind the newsroom's existing publish gate.
  The single exception: a desk listed in the site's `wire_desks`
  setting publishes immediately, and still carries the automated-report
  treatment.
- **Sanitization** — the same allowlist as every newsroom save.
- **Provenance** — the agent's name and every source are stored and
  rendered on the article as the automated-report box.

## Responses

| Code | Meaning |
| --- | --- |
| 201 `{ok, id, slug, status}` | filed (`status` is `draft` or `published`) |
| 200 `{ok, duplicate: true, id, slug}` | exact re-file — confirmed, not duplicated. Keyed on `external_id` when given, else on title+body |
| 400 / 422 | malformed payload — the error names the exact field |
| 401 | missing, unknown, or revoked token — fail closed |
| 403 | token not scoped to that site or desk |
| 429 | over the site's `ingest_hourly_limit` (default 60/hour/token) |

## Rules for agent authors

- Always send `external_id` — a stable id from your source system.
  Retries then cost nothing and can never double-file.
- File to the most specific desk; never file the same story to two
  papers unless it genuinely belongs to both (each files separately).
- `sources` is not optional in spirit: a story with no sources will be
  rejected by editors. Put every public record you drew on in it.
- A 4xx tells you exactly what to fix. Do not retry 4xx unchanged; do
  retry 5xx and network failures (idempotent under `external_id`).
