# Taking steeltownstandard.ca live — deployment runbook (brand-complete launch)

Steeltown Standard (slug `steeltown-standard`, template `steeltown`)
launches with its brand build applied and **six inaugural service
stories** — signed launch notes about the paper itself, no invented
local news. Identity from the owner's package: Tiger-Cats gold
`#FCB525` on white with Hamilton navy `#073674`, black, accent crimson
`#A8353A`, steel gray and light camel gold; the chain-ring mark (navy
chain circling a tiger-striped gold S of steel I-beams); condensed
uppercase headlines over serif body. Tagline: "Local News. Steeltown
Strong." Footprint: Hamilton, Ontario.

## Owner gates BEFORE any launch order

1. **The registered domain is CONFIRMED: `steeltownstandard.ca`** —
   owner-confirmed 29 Sep. If any later sheet names a different
   hostname, that is a conflict to resolve with the owner, not a
   correction to apply.
2. DNS: apex and www A records → the VPS.

## Step 0 — Discover and pin

- Discover the serving release from the enabled nginx blocks (strip
  inline comments, `readlink -f`); never assume a path.
- The release tree must contain `assets/sites/steeltown-standard/`
  (with `mark.svg` and `mark-reversed.svg`) and
  `app/views/front-steeltown.php`, and the pack's `settings` must read
  `site_title` "Steeltown Standard" (the brand build dropped the
  foundation's "The"). If any of that is missing, stop — the brand
  build is not in a shipped release yet.

## Step 1 — DNS

`steeltownstandard.ca` and `www.steeltownstandard.ca` → the VPS;
verify from the box with `dig @1.1.1.1` (the local resolver may hold
a negative-cache answer from before the records existed).

## Step 2 — Generate the nginx block (never copy one)

    bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
      steeltownstandard.ca www.steeltownstandard.ca

`$SOCKET` is the BARE fpm socket path (`/run/php/php8.3-fpm.sock`) —
the generator adds `unix:` itself. Both address families; no
`default_server`; test and reload.

## Step 3 — TLS

certbot for both hostnames; confirm `listen 443 ssl` survived on BOTH
families.

## Step 4 — Seed

    cd "$REL" && PP_SITE=steeltown-standard php tools/seed-launch.php

Pre-seed expectation table (verify only what exists):

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Template class `t-steeltown`, steeltown.css (gold/navy brand), chain-ring SVGs | Release tree | Present before the seed |
| Title "Steeltown Standard", tagline "Local News. Steeltown Strong." | This pack's settings | Absent until this seed — the tagline reads the network default before it |
| Desks in the nav | Shared `categories` + chrome.nav | ALL four exist network-wide already — read the real list with `categories_all()` from `$REL`, assert no absences |
| Stories | This pack | Absent before this seed; SIX after — the pack's launch notes, slugs prefixed `steeltown-` |

Idempotent: re-running adds only what is missing.

## Step 5 — Verify (served bytes, two-failure hard stop)

1. `https://steeltownstandard.ca/` → 200, `<title>` contains
   "Steeltown Standard", body class `t-steeltown`,
   `/assets/css/steeltown.css` linked.
2. Front page shows the six inaugural stories; the hero is the no-art
   variant (navy panel, faint chain-ring watermark) carrying "Local
   news, Steeltown strong" — the empty state is NOT expected. Below
   it: the gold-ruled "Latest News" card grid and the camel
   subscribe band.
3. Nav on the white bar: Home + four desks + Search + the gold
   Subscribe button (Local News, Sports, Community, Opinion). The
   nameplate's stacked wordmark is the literal text "Steeltown" and
   "Standard" in the served bytes — CSS uppercases it, so grep
   mixed case, not STEELTOWN (the Bleuet Blanc lesson). The pack's
   `site_title` "Steeltown Standard" appears in `<title>` as
   written.
4. `/desk/community` → 200. The section front prints the desk name
   only — this template deliberately shows no desk description
   (descriptions are shared network-wide), so its absence is a PASS.
5. Typography at the right layer: steeltown.css @imports
   /assets/css/fonts.css and names ONLY families fonts.css declares —
   parse fonts.css rules (don't count comment mentions) and confirm
   real @font-face rules for Archivo Narrow and Source Serif 4 with
   local /assets/fonts srcs.
6. `noindex` present on every page (indexing off) — a PASS.
7. `/api/ingest` and `/api/ingest-media` → 401 without a token;
   `/admin/` → login page.
8. Spot-check two sister papers still serve their own mastheads.

## Step 6 — Close out

No newsletter sends; indexing stays OFF; no ingest key exists for
this paper until the owner mints one. Report: release SHA, seed lines
(six story lines), verify table, TLS expiry.
