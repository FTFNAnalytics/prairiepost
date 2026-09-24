# Taking rideaureview.ca live — deployment runbook (brand-complete launch)

The Rideau Review (slug `rideau-review`, template `rideau`) launches
with its brand package applied and **six inaugural service stories** —
signed launch notes about the paper itself (mission, desk methods, how
to reach the newsroom), one per desk, no invented local news. The
front page fills at seed time, and that is the intended result of this
runbook. Identity: the lock-and-leaf
mark before the crimson nameplate on warm paper; Playfair Display
nameplate, Libre Baskerville headlines, Source Sans 3 interface;
Rideau crimson `#6B1C28` (deliberately not Globe scarlet); one crimson
rule under the nav. Place-line, always in this order: Ottawa ·
Gatineau · Eastern Ontario.

Config-edit-free flow (tenant resolution is database-first): DNS,
generated nginx block, cert, seed.

## Step 0 — Discover and pin

- Discover the serving release from the enabled nginx blocks (strip
  inline comments, `readlink -f`); never assume a path.
- The release tree must contain `assets/sites/rideau-review/` (with
  `mark.svg` and `mark-reversed.svg`), `app/views/front-rideau.php`,
  and `assets/fonts/libre-baskerville-latin.woff2`. If it doesn't,
  stop — the brand build is not in a shipped release yet.

## Step 1 — DNS

`rideaureview.ca` and `www.rideaureview.ca` → the VPS; verify from the
box with `dig @1.1.1.1` (the local resolver may hold a negative-cache
answer from before the records existed).

## Step 2 — Generate the nginx block (never copy one)

    bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
      rideaureview.ca www.rideaureview.ca

`$SOCKET` is the BARE fpm socket path (`/run/php/php8.3-fpm.sock`) —
the generator adds `unix:` itself. Both address families; no
`default_server`; test and reload.

## Step 3 — TLS

certbot for both hostnames; confirm `listen 443 ssl` survived on BOTH
families.

## Step 4 — Seed

    cd "$REL" && PP_SITE=rideau-review php tools/seed-launch.php

Pre-seed expectation table (verify only what exists):

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Template class `t-rideau`, rideau.css (crimson/warm-paper brand), lock-and-leaf SVGs | Release tree | Present before the seed |
| Title "The Rideau Review", tagline "The capital, closely read." | This pack's settings | Absent until this seed — the tagline reads the network default before it |
| Desks in the nav | Shared `categories` + chrome.nav | `gatineau` and `corridor` should print "desk added" on first seed; `local-news`, `politics`, `culture`, `opinion` exist network-wide already — read the real list with `categories_all()` from `$REL`, assert no absences |
| Stories | This pack | Absent before this seed; SIX after — the pack's launch notes, slugs prefixed `rideau-` |

Idempotent: re-running adds only what is missing — on a database whose
pack predates the stories, a re-run adds exactly the six launch notes
and touches nothing else.

## Step 5 — Verify (served bytes, two-failure hard stop)

1. `https://rideaureview.ca/` → 200, `<title>` contains "The Rideau
   Review", body class `t-rideau`, `/assets/css/rideau.css` linked.
2. Front page shows the six inaugural stories, hero "We report the
   record. Then we review it." — the empty state is NOT expected any
   more.
3. Nav: Home + six desks + Search. `desk_labels` renames `local-news`
   to "Ottawa" and `corridor` to "The Corridor"; the desk pages stay
   `/desk/local-news` and `/desk/corridor`. The nameplate renders
   uppercase via CSS `text-transform`; the SERVED bytes carry "The
   Rideau Review" in mixed case — grep the mixed-case form.
4. `/desk/gatineau` → 200. The section front prints the desk name
   only — this template deliberately shows no desk description
   (descriptions are shared network-wide), so its absence is a PASS.
5. Typography at the right layer: rideau.css @imports
   /assets/css/fonts.css, and fonts.css contains real @font-face rules
   naming Libre Baskerville AND Playfair Display AND Source Sans 3
   with local /assets/fonts srcs (parse rules, don't count comment
   mentions).
6. `noindex` present on every page (indexing off) — a PASS.
7. `/api/ingest` → 401 without a token; `/admin/` → login page.
8. Spot-check two sister papers still serve their own mastheads.

## Step 6 — Close out

No newsletter sends; indexing stays OFF; no Hermes token exists for
this paper. Report: release SHA, seed lines, verify table, TLS expiry.
