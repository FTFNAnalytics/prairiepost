# Taking cariboocompass.ca live — deployment runbook (brand-complete launch)

The Cariboo Compass (slug `cariboo-compass`, template `cariboo`)
launches with its brand package applied but **zero stories by design**
— the front page carries its empty state until the newsroom files, and
that is the intended result of this runbook. Identity: the gold
compass rose under the peaks on Deep Forest Green; serif headlines
over a sans body; Compass Gold accents, Lake Blue links.

Config-edit-free flow (tenant resolution is database-first): DNS,
generated nginx block, cert, seed.

## Step 0 — Discover and pin

- Discover the serving release from the enabled nginx blocks (strip
  inline comments, `readlink -f`); never assume a path.
- The release tree must contain `assets/sites/cariboo-compass/` (with
  the compass-rose SVGs) and `app/views/front-cariboo.php`. If it
  doesn't, stop — the brand build is not in a shipped release yet.

## Step 1 — DNS

`cariboocompass.ca` and `www.cariboocompass.ca` → the VPS; verify with
dig from the box.

## Step 2 — Generate the nginx block (never copy one)

    bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
      cariboocompass.ca www.cariboocompass.ca

Both address families; no `default_server`; test and reload.

## Step 3 — TLS

certbot for both hostnames; confirm `listen 443 ssl` survived on BOTH
families.

## Step 4 — Seed

    cd "$REL" && PP_SITE=cariboo-compass php tools/seed-launch.php

Pre-seed expectation table (verify only what exists):

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Template class `t-cariboo`, cariboo.css (green/gold brand), compass-rose SVGs | Release tree | Present before the seed |
| Title "The Cariboo Compass", tagline "Cariboo News. Community First." | This pack's settings | Absent until this seed |
| Desks in the nav | Shared `categories` + chrome.nav | `communities` may print "desk added"; `environment` and `education` exist only if Burrard/Surrey seeded them first — read the real list with `categories_all()` from `$REL`, assert no absences |
| Stories | This pack | NONE — by design, before AND after |

Idempotent: a re-run prints "exists, skipped" and changes nothing.

## Step 5 — Verify (served bytes, two-failure hard stop)

1. `https://cariboocompass.ca/` → 200, `<title>` contains "The Cariboo
   Compass", body class `t-cariboo`, `/assets/css/cariboo.css` linked.
2. Front page shows the EMPTY STATE ("The Compass is finding its first
   bearing") — that is a PASS.
3. Nav: Home + six desks (News, Communities, Business, Environment,
   Education, Opinion — `desk_labels` renames local-news to "News";
   the desk page stays `/desk/local-news`). Nav renders uppercase via
   CSS `text-transform`; the SERVED bytes are mixed case — grep
   "Communities", not "COMMUNITIES".
4. `/desk/communities` → 200 with the desk description.
5. Typography at the right layer: cariboo.css @imports
   /assets/css/fonts.css, and fonts.css contains real @font-face rules
   naming Source Serif 4 AND Source Sans 3 with local /assets/fonts
   srcs (parse rules, don't count comment mentions).
6. `noindex` present on every page (indexing off) — a PASS.
7. `/api/ingest` → 401 without a token; `/admin/` → login page.
8. Spot-check two sister papers still serve their own mastheads.

## Step 6 — Close out

No newsletter sends; indexing stays OFF; no Hermes token exists for
this paper. Report: release SHA, seed lines, verify table, TLS expiry.
