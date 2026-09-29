# Taking torontotelegraph.ca live — deployment runbook (brand-complete launch)

Toronto Telegraph (slug `toronto-telegraph`, template `telegraph`)
launches with its brand build applied and **six inaugural service
stories** — signed launch notes about the paper itself, no invented
local news. Identity from the owner's package: the double-blue
heritage palette (Leafs Navy `#00205B`, Argos Oxford `#0C2340`, Argos
Cambridge `#5F8FB1`, white, charcoal, soft silver); the
maple-leaf-and-telegraph-key roundel; bold modern sans headlines over
classic serif body. Tagline: "Toronto's Independent Voice." Footprint:
Toronto.

## Owner gates BEFORE any launch order

1. **The registered domain is CONFIRMED: `torontotelegraph.ca`** —
   owner-confirmed 29 Sep. (The package's website mockup shows a .com
   in its browser chrome; the REGISTRATION is the .ca — do not
   "correct" it.)
2. DNS: apex and www A records → the VPS.

## Step 0 — Discover and pin

- Discover the serving release from the enabled nginx blocks (strip
  inline comments, `readlink -f`); never assume a path.
- The release tree must contain `assets/sites/toronto-telegraph/`
  (with `mark.svg` and `mark-reversed.svg`) and
  `app/views/front-telegraph.php`, and the pack's `settings` must
  read `site_title` "Toronto Telegraph" (the brand build dropped the
  foundation's "The"). If any of that is missing, stop — the brand
  build is not in a shipped release yet.

## Step 1 — DNS

`torontotelegraph.ca` and `www.torontotelegraph.ca` → the VPS; verify
from the box with `dig @1.1.1.1` (the local resolver may hold a
negative-cache answer from before the records existed).

## Step 2 — Generate the nginx block (never copy one)

    bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
      torontotelegraph.ca www.torontotelegraph.ca

`$SOCKET` is the BARE fpm socket path (`/run/php/php8.3-fpm.sock`) —
the generator adds `unix:` itself. Both address families; no
`default_server`; test and reload.

## Step 3 — TLS

certbot for both hostnames; confirm `listen 443 ssl` survived on BOTH
families.

## Step 4 — Seed

    cd "$REL" && PP_SITE=toronto-telegraph php tools/seed-launch.php

Pre-seed expectation table (verify only what exists):

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Template class `t-telegraph`, telegraph.css (double-blue brand), roundel SVGs | Release tree | Present before the seed |
| Title "Toronto Telegraph", tagline "Toronto's Independent Voice" | This pack's settings | Absent until this seed — the tagline reads the network default before it |
| Desks in the nav | Shared `categories` + chrome.nav | ALL five exist network-wide already (`culture` since Bison's seed) — read the real list with `categories_all()` from `$REL`, assert no absences |
| Stories | This pack | Absent before this seed; SIX after — the pack's launch notes, slugs prefixed `telegraph-` |

Idempotent: re-running adds only what is missing.

## Step 5 — Verify (served bytes, two-failure hard stop)

1. `https://torontotelegraph.ca/` → 200, `<title>` contains "Toronto
   Telegraph", body class `t-telegraph`,
   `/assets/css/telegraph.css` linked.
2. Front page shows the six inaugural stories; the hero is the no-art
   variant (navy panel, faint roundel watermark) carrying "Truth.
   Perspective. Toronto." — the empty state is NOT expected. The rail
   shows the Weather box, the numbered Trending Now list, and the
   navy Morning Wire subscribe box.
3. Nav on the white bar: Home + five desks + Search + the navy
   Subscribe button. The desk order is City, Sports, Business,
   Culture, Opinion — "City" is `desk_labels` renaming `local-news`
   in the brand file; the desk page stays `/desk/local-news`. The
   nameplate's stacked wordmark is the literal text "Toronto" and
   "Telegraph" in the served bytes — CSS uppercases it, so grep mixed
   case, not TORONTO (the Bleuet Blanc lesson).
4. `/desk/culture` → 200. The section front prints the desk name
   only — this template deliberately shows no desk description
   (descriptions are shared network-wide), so its absence is a PASS.
5. Typography at the right layer: telegraph.css @imports
   /assets/css/fonts.css and names ONLY families fonts.css declares —
   parse fonts.css rules (don't count comment mentions) and confirm
   real @font-face rules for Archivo, Archivo Narrow and Source
   Serif 4 with local /assets/fonts srcs.
6. `noindex` present on every page (indexing off) — a PASS.
7. `/api/ingest` and `/api/ingest-media` → 401 without a token;
   `/admin/` → login page.
8. Spot-check two sister papers still serve their own mastheads.

## Step 6 — Close out

No newsletter sends; indexing stays OFF; no ingest key exists for
this paper until the owner mints one. Report: release SHA, seed lines
(six story lines), verify table, TLS expiry.
