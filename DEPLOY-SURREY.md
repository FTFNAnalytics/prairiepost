# Taking surreystandard.ca live — deployment runbook (foundation launch)

Surrey Standard (slug `surrey-standard`, template `surrey`) is a
FOUNDATION launch: identity, desks and wire sources, **zero stories by
design** — the front page carries its empty state until the newsroom
files, and that is the intended result of this runbook. The brand
package lands later as an ordinary release upgrade; no re-seed needed.

Config-edit-free flow (tenant resolution is database-first): DNS,
generated nginx block, cert, seed.

## Step 0 — Discover and pin

- Discover the serving release from the enabled nginx blocks (strip
  inline comments, `readlink -f`); never assume a path.
- The release tree must contain `assets/sites/surrey-standard/` and
  `app/views/front-surrey.php`. If it doesn't, stop — the foundations
  are not in a shipped release yet.

## Step 1 — DNS

`surreystandard.ca` and `www.surreystandard.ca` → the VPS; verify with
dig from the box.

## Step 2 — Generate the nginx block (never copy one)

    bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
      surreystandard.ca www.surreystandard.ca

Both address families; no `default_server`; test and reload.

## Step 3 — TLS

certbot for both hostnames; confirm `listen 443 ssl` survived on BOTH
families.

## Step 4 — Seed

    cd "$REL" && PP_SITE=surrey-standard php tools/seed-launch.php

Pre-seed expectation table (verify only what exists):

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Template class `t-surrey`, surrey.css (navy/lime brand, Playfair headlines), leaf-S monogram | Release tree | Present before the seed |
| Title "The Surrey Standard", tagline "News that matters. Stories that connect." | This pack's settings | Absent until this seed |
| Desks in the nav | Shared `categories` + chrome.nav | `education` may print "desk added"; the rest exist network-wide — read the real list with `categories_all()` from `$REL`, assert no absences |
| Stories | This pack | NONE — by design, before AND after |

Idempotent: a re-run prints "exists, skipped" and changes nothing.

## Step 5 — Verify (served bytes, two-failure hard stop)

1. `https://surreystandard.ca/` → 200, `<title>` contains "The Surrey
   Standard", body class `t-surrey`, `/assets/css/surrey.css` linked.
2. Front page shows the EMPTY STATE ("The first edition is being set")
   — that is a PASS.
3. Nav: Front Page + six desks (Surrey News, City Hall, Development,
   Education, Sports, Opinion — `desk_labels` renames local-news to
   "Surrey News"; the desk page itself stays `/desk/local-news`). Nav
   renders uppercase via CSS `text-transform`; the SERVED bytes are
   mixed case — grep "City Hall", not "CITY HALL".
4. `/desk/education` → 200 with the desk description.
5. Typography at the right layer: surrey.css must contain @font-face
   rules naming playfair-display-latin.woff2 (parse rules, don't count
   comment mentions), and the woff2 files must be in the release under
   /assets/fonts/.
6. `noindex` present on every page (indexing off) — a PASS.
7. `/api/ingest` → 401 without a token; `/admin/` → login page.
8. Spot-check two sister papers still serve their own mastheads.

## Step 6 — Close out

No newsletter sends; indexing stays OFF; no Hermes token exists for
this paper. Report: release SHA, seed lines, verify table, TLS expiry.
