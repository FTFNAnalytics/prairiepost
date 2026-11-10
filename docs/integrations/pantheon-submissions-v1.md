# Pantheon submissions gateway — v1 contract

Status: implementation foundation · 2026-08-27  
Canonical owner: PrairiePost/Civis Media control room

## Purpose

Accept reviewable media requests from Pantheon without giving Pantheon a
newsroom session or direct database access. The gateway covers:

- unpaid editorial pitches;
- clearly labelled paid sponsored posts; and
- display-ad requests across any subset of the fifteen papers.

The existing network-ad system remains the serving mechanism: an approved
campaign fans out to ordinary per-site `ads` rows, preserving the current
slots, schedules, impressions and click counters. The gateway creates an
intake/review record first; it never turns an external request directly into a
live campaign or published story.

## Non-negotiable boundary

- Civis Media controls inventory, price, editorial review, publication,
  labelling, scheduling, pausing and rejection.
- Payment does not guarantee editorial coverage.
- An editorial pitch does not enter the paid campaign workflow.
- A sponsored post never masquerades as newsroom-authored editorial.
- Pantheon tenant IDs and objective IDs are opaque references.
- No voter records, target lists, private strategy or Pantheon credentials are
  accepted.
- The token is server-side, scoped, hashed at rest and revocable. Unknown and
  revoked tokens answer identically.
- Every accepted payload is size-limited, validated and idempotent.
- Human review remains between an external submission and any use of
  `pp_sync_campaign_ads()` or the post publication flow.

## Paper catalogue

`GET /api/v1/pantheon/properties` returns only active paper sites, excluding
the `civismedia` hub. The bootstrap fixture is
`docs/integrations/pantheon-property-catalog.example.json`.

The runtime response will be generated from the database and will eventually
include:

- site slug, public name, canonical domain and languages;
- active/paused state;
- served-market names and versioned service-area geometry references;
- accepted request types;
- display slots and creative constraints;
- inventory/rate-card availability timestamps.

Pantheon may cache the catalogue briefly but must treat it as advisory until a
request is quoted and accepted.

## Routes

| Method | Route | Authentication | Result |
|---|---|---|---|
| GET | `/api/v1/pantheon/properties` | scoped token | current catalogue |
| POST | `/api/v1/pantheon/requests` | scoped token + idempotency key | create or return the same intake request |
| GET | `/api/v1/pantheon/requests/{request_id}` | scoped token | status, quote, schedule and metrics |
| POST | `/api/v1/pantheon/requests/{request_id}/decision` | scoped token | accept quote, cancel, or answer a change request |

The first gateway iteration can use polling. Signed status webhooks are a
later optimization and do not change the state model.

## Authentication and transport

Use the established ingest posture with a distinct table and token purpose:

- bearer tokens contain at least 32 random bytes;
- store only SHA-256 hashes;
- scope tokens to `catalog:read`, `requests:write` and
  `requests:read`;
- optionally restrict allowed Pantheon environment and source IPs;
- enforce HTTPS and reject browser-origin requests;
- never reuse a Hermes ingest token;
- audit token identity, request ID, action, result and timestamp without
  logging full creative bodies or credentials.

Writes require `Idempotency-Key` and `X-Pantheon-Contract-Version`.
A repeated key with an identical hash returns the original result; a repeated
key with different content returns 409.

## Request validation

The JSON Schema is
`docs/integrations/pantheon-request.schema.json`. Server validation also
enforces:

- one of `editorial_pitch`, `sponsored_post` or `display_ad`;
- one or more current paper slugs;
- UTF-8 text and strict field-size ceilings;
- HTTPS source and landing URLs;
- requested dates in a coherent order;
- CAD budget values at two decimal places;
- advertiser identity for paid requests;
- political/issue classification explicitly supplied;
- no embedded HTML, scripts, remote embeds or inline credentials;
- attachments represented by a manifest only until a governed upload path
  exists.

## Persistence proposed for the gateway pilot

`media_requests`

- `id`, `external_request_id UNIQUE`, `idempotency_key UNIQUE`;
- `request_type`, `contract_version`, `payload_hash`;
- opaque `tenant_ref` and `objective_ref`;
- `status`, `submitted_at`, `updated_at`, `reviewed_by`;
- sanitized brief fields, requested dates and budget cap;
- advertiser/disclosure fields for paid work;
- rejection/hold reason safe to return to Pantheon.

`media_request_sites`

- request ID, site ID, requested format/slot and current site-level state;
- unique request × site.

`media_request_events`

- append-only status history, actor kind/ref, safe note and timestamp.

`media_request_versions`

- immutable canonical payload, hash, version and prior-version reference.

Do not overload `campaigns` with unaccepted requests. Once a display request
is approved, a Civis administrator deliberately creates or links the
`campaigns` row and uses the existing fan-out. Sponsored posts deliberately
enter the existing newsroom review flow. Editorial pitches create a newsroom
assignment/idea only after acceptance.

## State model

`submitted → acknowledged → needs_changes | quoted | accepted | rejected →
client_approved → scheduled → live → completed`

- `editorial_pitch`: acknowledged → needs_changes / accepted / rejected.
- paid work: quoted → client_approved only after Pantheon returns its immutable
  approval reference and payload hash.
- material changes create a new request version and return to review.
- Civis can pause or reject before or after scheduling.
- live and completed display requests report the existing per-paper impression
  and click counters; no new ad-serving path is introduced.

## Control-room UI

Add a hub-admin page with separate lanes:

1. Editorial pitches — newsroom review, no prices or paid-conversion action.
2. Sponsored posts — advertiser, disclosure, sources, claims and copy review.
3. Display ads — properties, slot, creative, dates, quote and campaign link.

Reviewers see the Pantheon request ID and objective reference but not a private
political dossier. Accept/reject/quote actions create audit events. Campaign
creation and publication remain separate explicit actions.

## First vertical test

Use Tri Cities Torch in a non-billable sandbox record:

1. Pantheon reads the catalogue.
2. Pantheon submits the same display request twice with one idempotency key.
3. Civis stores one intake record.
4. An administrator requests a change, then quotes it.
5. Pantheon returns a human approval reference.
6. Civis links a paused campaign; no creative serves.
7. A Civis administrator schedules and enables it.
8. Pantheon polls the status and receives per-property counts.
9. Pausing the request pauses the linked campaign and is visible in both
   histories.

The production gate additionally requires authentication tests, payload-limit
tests, replay/idempotency tests, migration verification on every supported
database driver, CSRF separation from the session UI, and an all-fifteen-paper
regression check after the release branch is upgraded.
