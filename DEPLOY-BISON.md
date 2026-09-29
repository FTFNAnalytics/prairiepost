# Taking bisonbulletin.ca live — deployment runbook (brand-complete launch)

The Bison Bulletin (slug `bison-bulletin`, template `bison`) launches
with its brand build applied and **six inaugural service stories** —
signed launch notes about the paper itself, no invented local news.
Identity from the owner's package: bison red `#8B0000` on prairie
cream `#F5F5DC`; Montserrat headlines over an Open Sans interface;
the red bison mark on the solid red masthead bar. Tagline: "Manitoba
News You Can Trust." Footprint: PROVINCE-WIDE (settled by the
package's own "Manitoba News" subtitle).

## Owner gates BEFORE any launch order

1. **Confirm the REGISTERED domain.** The pack assumes
   `bisonbulletin.ca` (the Burrard lesson: sheet and registration can
   differ). Fix the pack first if they do.
2. DNS: apex and www A records → the VPS.

## Step 0 — Discover and pin

- Discover the serving release from the enabled nginx blocks (strip
  inline comments, `readlink -f`); never assume a path.
- The release tree must contain `assets/sites/bison-bulletin/` (with
  `mark.svg` and `mark-reversed.svg`), `app/views/front-bison.php`,
  and `assets/fonts/montserrat-latin.woff2`. If it doesn't, stop —
  the brand build is not in a shipped release yet.

## Step 1 — DNS

`bisonbulletin.ca` and `www.bisonbulletin.ca` → the VPS; verify from
the box with `dig @1.1.1.1` (the local resolver may hold a
negative-cache answer from before the records existed).

## Step 2 — Generate the nginx block (never copy one)

    bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
      bisonbulletin.ca www.bisonbulletin.ca

`$SOCKET` is the BARE fpm socket path (`/run/php/php8.3-fpm.sock`) —
the generator adds `unix:` itself. Both address families; no
`default_server`; test and reload.

## Step 3 — TLS

certbot for both hostnames; confirm `listen 443 ssl` survived on BOTH
families.

## Step 4 — Seed

    cd "$REL" && PP_SITE=bison-bulletin php tools/seed-launch.php

Pre-seed expectation table (verify only what exists):

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Template class `t-bison`, bison.css (red/cream brand), bison SVGs | Release tree | Present before the seed |
| Title "The Bison Bulletin", tagline "Manitoba News You Can Trust" | This pack's settings | Absent until this seed — the tagline reads the network default before it |
| Desks in the nav | Shared `categories` + chrome.nav | ALL six exist network-wide already — read the real list with `categories_all()` from `$REL`, assert no absences |
| Stories | This pack | Absent before this seed; SIX after — the pack's launch notes, slugs prefixed `bison-` |

Idempotent: re-running adds only what is missing.

## Step 5 — Verify (served bytes, two-failure hard stop)

1. `https://bisonbulletin.ca/` → 200, `<title>` contains "The Bison
   Bulletin", body class `t-bison`, `/assets/css/bison.css` linked.
2. Front page shows the six inaugural stories; the hero is the
   no-art variant (red panel, faint bison watermark) carrying
   "Manitoba news you can trust: what that sentence commits us to" —
   the empty state is NOT expected.
3. Nav on the red bar: Home + six desks + Search (`desk_labels`
   renames `local-news` to "News"; the desk page stays
   `/desk/local-news`). The SERVED bytes carry "The Bison Bulletin"
   as written — no CSS case transform on the nameplate.
4. `/desk/politics` → 200. The section front prints the desk name
   only — this template deliberately shows no desk description
   (descriptions are shared network-wide), so its absence is a PASS.
5. Typography at the right layer: bison.css @imports
   /assets/css/fonts.css, and fonts.css contains real @font-face
   rules naming Montserrat AND Open Sans with local /assets/fonts
   srcs (parse rules, don't count comment mentions).
6. `noindex` present on every page (indexing off) — a PASS.
7. `/api/ingest` and `/api/ingest-media` → 401 without a token;
   `/admin/` → login page.
8. Spot-check two sister papers still serve their own mastheads.

## Step 6 — Close out

No newsletter sends; indexing stays OFF; no ingest key exists for
this paper until the owner mints one. Report: release SHA, seed lines
(six story lines), verify table, TLS expiry.
