# Taking burrardbrief.ca live — deployment runbook (brand-complete launch)

The Burrard Brief (slug `burrard-brief`, template `burrard`) launches
with its brand package applied but **zero stories by design** — the
front page carries its empty state until the newsroom files, and that
is the intended result of this runbook. Identity: the North Shore
mountains over Burrard Inlet, one serif (Source Serif 4), Inlet Teal
interactive, Forest Green reserved for Opinion, "The Morning Brief" as
the product.

Config-edit-free flow (tenant resolution is database-first): DNS,
generated nginx block, cert, seed.

## Step 0 — Discover and pin

- Discover the serving release from the enabled nginx blocks (strip
  inline comments, `readlink -f`); never assume a path.
- The release tree must contain `assets/sites/burrard-brief/`
  (including `img/` with the fourteen scene photographs and the three
  mountain-mark SVGs) and `app/views/front-burrard.php`. If it
  doesn't, stop — the brand build is not in a shipped release yet.

## Step 1 — DNS

`burrardbrief.ca` and `www.burrardbrief.ca` → the VPS; verify with dig
from the box. (The brand sheet's mock contact lines mention
theburrardbrief.ca — that is NOT a hostname this paper serves; the
registered domain is burrardbrief.ca.)

## Step 2 — Generate the nginx block (never copy one)

    bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
      burrardbrief.ca www.burrardbrief.ca

Both address families; no `default_server`; test and reload.

## Step 3 — TLS

certbot for both hostnames; confirm `listen 443 ssl` survived on BOTH
families.

## Step 4 — Seed

    cd "$REL" && PP_SITE=burrard-brief php tools/seed-launch.php

Pre-seed expectation table (verify only what exists):

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Template class `t-burrard`, burrard.css (serif/teal brand), mountain-mark SVGs, img/ scene art | Release tree | Present before the seed |
| Title "The Burrard Brief", tagline "Vancouver & Lower Mainland news, briefly." | This pack's settings | Absent until this seed |
| Desks in the nav | Shared `categories` + chrome.nav | `housing` and `environment` may print "desk added"; the rest exist network-wide — read the real list with `categories_all()` from `$REL`, assert no absences |
| Stories | This pack | NONE — by design, before AND after |

Idempotent: a re-run prints "exists, skipped" and changes nothing.

## Step 5 — Verify (served bytes, two-failure hard stop)

1. `https://burrardbrief.ca/` → 200, `<title>` contains "The Burrard
   Brief", body class `t-burrard`, `/assets/css/burrard.css` linked.
2. Front page shows the EMPTY STATE ("The first brief is being
   written") — that is a PASS.
3. Nav: Home + six desks (The City, Housing, City Hall, Transit,
   Environment, Opinion — `desk_labels` renames local-news to "The
   City"; the desk page stays `/desk/local-news`). Nav renders
   uppercase via CSS `text-transform`; the SERVED bytes are mixed case
   — grep "City Hall", not "CITY HALL".
4. `/desk/housing` → 200 with the desk description.
5. Typography at the right layer: burrard.css @imports
   /assets/css/fonts.css, and fonts.css contains a real @font-face
   rule naming Source Serif 4 with a local /assets/fonts src (parse
   rules, don't count comment mentions); fetch one named woff2 → 200.
6. `/assets/sites/burrard-brief/img/skyline.png` → 200 (the scene-art
   fallback set is served).
7. `noindex` present on every page (indexing off) — a PASS.
8. `/api/ingest` → 401 without a token; `/admin/` → login page.
9. Spot-check two sister papers still serve their own mastheads.

## Step 6 — Close out

No newsletter sends; indexing stays OFF; no Hermes token exists for
this paper. Report: release SHA, seed lines, verify table, TLS expiry.
