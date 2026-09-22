# Taking burrardbrief.ca live — deployment runbook (foundation launch)

The Burrard Brief (slug `burrard-brief`, template `burrard`) is a
FOUNDATION launch: identity, desks and wire sources only — **zero
stories by design**, like Turtle Island and Pickering. The front page
carries its empty state until the newsroom files; that is the intended
result of this runbook, not an unfinished deployment. The brand package
(final palette, wordmark, typography) lands as an ordinary release
upgrade later and requires no re-seed.

Written against the config-edit-free launch flow proven by the Monitor
and the Chronicle: DNS, generated nginx block, cert, seed. No shared
config edit is required — tenant resolution is database-first.

## Step 0 — Discover and pin

- Discover the serving release from the enabled nginx blocks (strip
  inline comments, `readlink -f`). Do not assume a path.
- This paper requires a release whose tree contains
  `assets/sites/burrard-brief/` and `app/views/front-burrard.php`.
  Confirm both exist in the release you will point the block at; if
  they don't, stop — the foundations have not shipped in a release yet.

## Step 1 — DNS

`burrardbrief.ca` and `www.burrardbrief.ca` → the VPS. Owner-confirmed
before this runbook starts; verify with dig from the box.

## Step 2 — Generate the nginx block (never copy one)

    bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
      burrardbrief.ca www.burrardbrief.ca

Both address families, no `default_server` (the reject catch-all owns
that), test and reload.

## Step 3 — TLS

certbot for both hostnames; confirm the block kept `listen 443 ssl` on
BOTH families afterwards (the Turtle Island lesson).

## Step 4 — Seed: identity, desks, domains, sources, one command

    cd "$REL" && PP_SITE=burrard-brief php tools/seed-launch.php

Expected output classification (lesson 1 — verify only what exists):

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Template class `t-burrard`, burrard.css, placeholder mark | Release tree | Present before the seed |
| Site title "The Burrard Brief", tagline "Vancouver, in brief" | This pack's settings | Absent until this seed |
| Desks in the nav | Shared `categories` + chrome.nav filter | `culture` may print "desk added"; the other five exist network-wide — run `categories_all()` from `$REL` for the real list, do not assert absences |
| Stories | This pack | NONE — by design, before AND after |

The seeder is idempotent; a re-run prints "exists, skipped" lines and
changes nothing.

## Step 5 — Verify the live paper (served bytes, two-failure hard stop)

1. `https://burrardbrief.ca/` → 200, `<title>` contains "The Burrard
   Brief", body class `t-burrard`, stylesheet link
   `/assets/css/burrard.css` in the HTML.
2. The front page shows the EMPTY STATE ("The first brief is being
   written") — that is a PASS for this launch.
3. Nav carries Home plus the six desks (The City, City Hall, Transit,
   Business, Culture, Opinion — `desk_labels` renames local-news to
   "The City" in the nav; the desk page itself is `/desk/local-news`).
4. `/desk/city-hall` → 200 with the desk header and its description.
5. Every page carries `noindex` until the owner enables indexing in
   Settings — presence of noindex is a PASS, not a defect.
6. `/api/ingest` → 401 without a token. `/admin/` → login page.
7. Spot-check two sister papers still serve their own mastheads.

## Step 6 — Close out

- No newsletter sends (owner mail setup pending network-wide).
- Indexing stays OFF until the owner ticks it per paper.
- No Hermes token exists for this paper; any future filing lands as a
  draft only after the owner orders a token minted.
- Report: release SHA, seed output lines, the verify table, TLS expiry.
