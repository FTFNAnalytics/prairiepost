# Courier — filing human-approved stories from your own machine

Courier (`tools/courier.py`, Python 3.8+, no dependencies) is the
uploading agent for the ingest API: it takes story bundles a human
writer has approved, uploads each bundle's featured graphic to
`POST /api/ingest-media`, and files the story to `POST /api/ingest`.
**Everything lands as a draft** in the paper's newsroom — Courier
cannot publish, and its key (minted on the CivisMedia control room's
API-keys page) works only for the papers it was scoped to.

## Setup, once

    export PP_INGEST_BASE=https://rideaureview.ca   # any network paper works
    export PP_INGEST_KEY=hermes_…                   # or --key-file key.txt (chmod 600)

Never put the key on the command line and never commit it; the two
mechanisms above are the only ones Courier accepts.

## A bundle

One folder per story:

    my-story/
      story.md        front matter between --- fences, body below
      featured.png    optional; at most one image (JPEG/PNG/WebP/GIF, ≤8 MB)

`docs/courier-example/story.md` is a complete annotated example. Front
matter keys: `site`, `desk`, `title`, `lede` (required); `dateline`,
`tags`, `slug`, `external_id`, `image` (filename, when the folder holds
several), `image_caption`, `image_credit`, repeated `source:` lines
(`url | title`), and `ready: yes` — the approval checkbox: the story
publishes as soon as it has a featured image (immediately when the
bundle carries one, otherwise when the image agent attaches one; see
`docs/agent-workflow.md`). Without `ready:`, everything stays a draft
for the newsroom, as before. A `story.json` with the same fields plus `"body"` works
too. A body that starts with `<` is sent as HTML; anything else becomes
escaped paragraphs. The server sanitizes every filing regardless.

## Running

    python3 tools/courier.py my-story other-story     # named bundles
    python3 tools/courier.py --all ready/             # every bundle under ready/
    python3 tools/courier.py --dry-run --all ready/   # validate only, send nothing

## Why a re-run is always safe

Two independent guards:

1. A successful filing writes `receipt.json` into the bundle (the
   draft's id, slug and time). Bundles with receipts are skipped unless
   you pass `--resend`.
2. Every filing carries an `external_id` (the folder name unless the
   front matter sets one), and the server answers an exact re-file with
   `duplicate` instead of creating a copy.

So a crashed batch is simply run again; only what didn't land is sent.

## Failures

A defective bundle (missing lede, image not in the folder, non-image
bytes) fails **that bundle** and the batch continues; the exit code is
the number of failed bundles. Rate limits (429) and transient transport
errors are retried three times with backoff before counting as failed.
401 means the key is missing, wrong or revoked; 403 means the key is
not scoped to that paper or desk; 422 messages quote the server's
reason verbatim. The full HTTP contract is `docs/api-ingest.md`.
