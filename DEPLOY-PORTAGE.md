# Taking portagepress.ca live — deployment runbook (brand-complete launch)

Portage Press (slug `portage-press`, template `portage`) launches with
its brand build applied and **six inaugural service stories** — signed
launch notes about the paper itself, no invented local news. Identity
from the owner's guidelines: Polar Night Blue `#041E42` masthead and
footer, accent red `#AC162C` (links, category labels, primary CTA),
Dark Gray body; Inter primary with Playfair Display pull quotes; the
P-pin mark. Tagline: "Local News. Winnipeg Matters." This is the
Winnipeg city title, completing Manitoba (Bison = province, Brandon =
southwest, Register = the valley).

## Owner gates BEFORE any launch order

1. **Confirm the REGISTERED domain.** The pack assumes
   `portagepress.ca` — the package's own mockup shows it, but the
   Burrard lesson stands: sheet and registration can differ. Fix the
   pack first if they do.
2. DNS: apex and www A records → the VPS.

## Step 0 — Discover and pin

- Discover the serving release from the enabled nginx blocks (strip
  inline comments, `readlink -f`); never assume a path.
- The release tree must contain `assets/sites/portage-press/` (with
  `mark.svg`, `mark-reversed.svg` and `maple.svg`) and
  `app/views/front-portage.php`. If it doesn't, stop — the brand
  build is not in a shipped release yet.

## Step 1 — DNS

`portagepress.ca` and `www.portagepress.ca` → the VPS; verify from
the box with `dig @1.1.1.1` (the local resolver may hold a
negative-cache answer from before the records existed).

## Step 2 — Generate the nginx block (never copy one)

    bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
      portagepress.ca www.portagepress.ca

`$SOCKET` is the BARE fpm socket path (`/run/php/php8.3-fpm.sock`) —
the generator adds `unix:` itself. Both address families; no
`default_server`; test and reload.

## Step 3 — TLS

certbot for both hostnames; confirm `listen 443 ssl` survived on BOTH
families.

## Step 4 — Seed

    cd "$REL" && PP_SITE=portage-press php tools/seed-launch.php

Pre-seed expectation table (verify only what exists):

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Template class `t-portage`, portage.css (navy/red brand), P-pin SVGs | Release tree | Present before the seed |
| Title "Portage Press", tagline "Local News. Winnipeg Matters." | This pack's settings | Absent until this seed — the tagline reads the network default before it |
| Desks in the nav | Shared `categories` + chrome.nav | `arts` should print "desk added" (it exists only in the never-seeded Terminal City pack); the other five exist network-wide — read the real list with `categories_all()` from `$REL`, assert no absences |
| Stories | This pack | Absent before this seed; SIX after — the pack's launch notes, slugs prefixed `portage-` |

Idempotent: re-running adds only what is missing.

## Step 5 — Verify (served bytes, two-failure hard stop)

1. `https://portagepress.ca/` → 200, `<title>` contains "Portage
   Press", body class `t-portage`, `/assets/css/portage.css` linked.
2. Front page shows the six inaugural stories; the hero is the
   no-art variant (navy panel, faint P-pin watermark) carrying
   "Local news, because Winnipeg matters" — the empty state is NOT
   expected. The rail shows the navy Weather panel, MOST READ, and
   the blue "Stay in the know." panel.
3. Nav on the navy bar: Home + six desks + Search (News, Sports,
   Politics, Business, Arts, Opinion; `desk_labels` renames
   `local-news` to "News"; the desk page stays `/desk/local-news`).
   The nameplate subline renders uppercase via CSS; the SERVED bytes
   carry the tagline "Local News. Winnipeg Matters." as written.
4. `/desk/arts` → 200. The section front prints the desk name only —
   this template deliberately shows no desk description
   (descriptions are shared network-wide), so its absence is a PASS.
5. Typography at the right layer: portage.css @imports
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
(six story lines, including the `arts` desk line), verify table, TLS
expiry.
