# Taking redriverregister.ca live — deployment runbook (brand-complete launch)

The Red River Register (slug `red-river-register`, template
`redriver`) launches with its brand build applied and **six inaugural
service stories** — signed launch notes about the paper itself
(mission, desk methods, how to reach the newsroom), no invented local
news. Identity: the Rideau chassis as the Register's own — Playfair
nameplate behind the ledger-and-meander mark, Libre Baskerville
headlines, Source Sans 3 interface, ONE Red River clay rule
(`#7E3517`) under the nav, warm paper. Positioning: "The valley, on
the record."

## Owner gates BEFORE any launch order

1. **Footprint sign-off.** The build assumes the Red River Valley
   beyond Winnipeg (Selkirk, Steinbach, Morris, Emerson, the RMs),
   with a Winnipeg sister paper covering the city. If that changes,
   the pack's inaugural notes and desk descriptions are edited FIRST.
2. **Confirm the REGISTERED domain.** The pack assumes
   `redriverregister.ca` (the Burrard lesson: sheet and registration
   can differ). Fix the pack first if they do.
3. DNS: apex and www A records → the VPS.

## Step 0 — Discover and pin

- Discover the serving release from the enabled nginx blocks (strip
  inline comments, `readlink -f`); never assume a path.
- The release tree must contain `assets/sites/red-river-register/`
  (with `mark.svg` and `mark-reversed.svg`) and
  `app/views/front-redriver.php`. If it doesn't, stop — the brand
  build is not in a shipped release yet.

## Step 1 — DNS

`redriverregister.ca` and `www.redriverregister.ca` → the VPS; verify
from the box with `dig @1.1.1.1` (the local resolver may hold a
negative-cache answer from before the records existed).

## Step 2 — Generate the nginx block (never copy one)

    bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
      redriverregister.ca www.redriverregister.ca

`$SOCKET` is the BARE fpm socket path (`/run/php/php8.3-fpm.sock`) —
the generator adds `unix:` itself. Both address families; no
`default_server`; test and reload.

## Step 3 — TLS

certbot for both hostnames; confirm `listen 443 ssl` survived on BOTH
families.

## Step 4 — Seed

    cd "$REL" && PP_SITE=red-river-register php tools/seed-launch.php

Pre-seed expectation table (verify only what exists):

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Template class `t-redriver`, redriver.css (clay/warm-paper brand), ledger-and-meander SVGs | Release tree | Present before the seed |
| Title "The Red River Register", tagline "The valley, on the record." | This pack's settings | Absent until this seed — the tagline reads the network default before it |
| Desks in the nav | Shared `categories` + chrome.nav | ALL five exist network-wide already — read the real list with `categories_all()` from `$REL`, assert no absences |
| Stories | This pack | Absent before this seed; SIX after — the pack's launch notes, slugs prefixed `redriver-` |

Idempotent: re-running adds only what is missing.

## Step 5 — Verify (served bytes, two-failure hard stop)

1. `https://redriverregister.ca/` → 200, `<title>` contains "The Red
   River Register", body class `t-redriver`,
   `/assets/css/redriver.css` linked.
2. Front page shows the six inaugural stories, hero "The valley, on
   the record" — the empty state is NOT expected.
3. Nav: Home + five desks + Search. `desk_labels` renames
   `local-news` to "The Valley"; the desk page stays
   `/desk/local-news`. The nameplate renders uppercase via CSS
   `text-transform`; the SERVED bytes carry "The Red River Register"
   in mixed case — grep the mixed-case form.
4. `/desk/communities` → 200. The section front prints the desk name
   only — this template deliberately shows no desk description
   (descriptions are shared network-wide), so its absence is a PASS.
5. Typography at the right layer: redriver.css @imports
   /assets/css/fonts.css; fonts.css contains real @font-face rules
   naming Libre Baskerville AND Source Sans 3 with local
   /assets/fonts srcs, and redriver.css declares Playfair Display
   ITSELF (parse rules, don't count comment mentions).
6. `noindex` present on every page (indexing off) — a PASS.
7. `/api/ingest` and `/api/ingest-media` → 401 without a token;
   `/admin/` → login page.
8. Spot-check two sister papers still serve their own mastheads.

## Step 6 — Close out

No newsletter sends; indexing stays OFF; no ingest key exists for
this paper until the owner mints one. Report: release SHA, seed
lines (six story lines), verify table, TLS expiry.
