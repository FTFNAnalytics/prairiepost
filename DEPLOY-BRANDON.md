# Taking brandonbulletin.com live — deployment runbook (brand-complete launch)

The Brandon Bulletin (slug `brandon-bulletin`, template `brandon`)
launches with its brand build applied and **six inaugural service
stories** — signed launch notes about the paper itself, no invented
local news. Identity from the owner's package: Brandon Gold `#E6BF2E`
over white with black and cool gray, Light Wheat `#F7E5AD` accent
wells; the wheat-over-open-book badge; serif nameplate (BRANDON over
the letterspaced gold BULLETIN) with sans headlines. Tagline: "News
with heart. Rooted in place." Footprint: Brandon and Southwest
Manitoba.

## Owner gates BEFORE any launch order

1. **The registered domain is CONFIRMED: `brandonbulletin.com`** —
   owner-confirmed 29 Sep; the .ca was never registered, making this
   the network's one non-.ca masthead. Do not "correct" it to .ca.
2. DNS: apex and www A records → the VPS.

## Step 0 — Discover and pin

- Discover the serving release from the enabled nginx blocks (strip
  inline comments, `readlink -f`); never assume a path.
- The release tree must contain `assets/sites/brandon-bulletin/`
  (with `mark.svg` and `mark-reversed.svg`) and
  `app/views/front-brandon.php`. If it doesn't, stop — the brand
  build is not in a shipped release yet.

## Step 1 — DNS

`brandonbulletin.com` and `www.brandonbulletin.com` → the VPS; verify
from the box with `dig @1.1.1.1` (the local resolver may hold a
negative-cache answer from before the records existed).

## Step 2 — Generate the nginx block (never copy one)

    bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
      brandonbulletin.com www.brandonbulletin.com

`$SOCKET` is the BARE fpm socket path (`/run/php/php8.3-fpm.sock`) —
the generator adds `unix:` itself. Both address families; no
`default_server`; test and reload.

## Step 3 — TLS

certbot for both hostnames; confirm `listen 443 ssl` survived on BOTH
families.

## Step 4 — Seed

    cd "$REL" && PP_SITE=brandon-bulletin php tools/seed-launch.php

Pre-seed expectation table (verify only what exists):

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Template class `t-brandon`, brandon.css (gold/white brand), badge SVGs | Release tree | Present before the seed |
| Title "The Brandon Bulletin", tagline "News with heart. Rooted in place." | This pack's settings | Absent until this seed — the tagline reads the network default before it |
| Desks in the nav | Shared `categories` + chrome.nav | ALL four exist network-wide already — read the real list with `categories_all()` from `$REL`, assert no absences |
| Stories | This pack | Absent before this seed; SIX after — the pack's launch notes, slugs prefixed `brandon-` |

Idempotent: re-running adds only what is missing.

## Step 5 — Verify (served bytes, two-failure hard stop)

1. `https://brandonbulletin.com/` → 200, `<title>` contains "The
   Brandon Bulletin", body class `t-brandon`,
   `/assets/css/brandon.css` linked.
2. Front page shows the six inaugural stories; the hero is the
   no-art variant (light-wheat panel) carrying "News with heart,
   rooted in place" — the empty state is NOT expected.
3. Nav: Home + four desks + Search (Local News, Sports, Community,
   Opinion; `desk_labels` renames `local-news` to "Local News" and
   `community` to "Community"; the desk pages keep their slugs). The
   nameplate's stacked BRANDON/BULLETIN is literal text in the served
   bytes; the pack's `site_title` "The Brandon Bulletin" appears in
   `<title>` mixed-case.
4. `/desk/community` → 200. The section front prints the desk name
   only — this template deliberately shows no desk description
   (descriptions are shared network-wide), so its absence is a PASS.
5. Typography at the right layer: brandon.css @imports
   /assets/css/fonts.css AND declares Playfair Display ITSELF (parse
   rules, don't count comment mentions); fonts.css contains a real
   @font-face rule naming Inter with a local /assets/fonts src.
6. `noindex` present on every page (indexing off) — a PASS.
7. `/api/ingest` and `/api/ingest-media` → 401 without a token;
   `/admin/` → login page.
8. Spot-check two sister papers still serve their own mastheads.

## Step 6 — Close out

No newsletter sends; indexing stays OFF; no ingest key exists for
this paper until the owner mints one. Report: release SHA, seed lines
(six story lines), verify table, TLS expiry.
