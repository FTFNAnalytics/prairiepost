# Taking terminalcitytimes.ca live — deployment runbook (foundation launch)

Terminal City Times (slug `terminal-city-times`, template
`terminalcity`) is a FOUNDATION launch: identity, desks and wire
sources, **zero stories by design** — the front page carries its empty
state until the newsroom files, and that is the intended result of this
runbook. The brand package lands later as an ordinary release upgrade;
no re-seed needed. This is Vancouver's broadsheet of record; The
Burrard Brief (separate runbook) is its quick-read sibling — two
tenants, one release, one shared database.

Config-edit-free flow (tenant resolution is database-first): DNS,
generated nginx block, cert, seed.

## Step 0 — Discover and pin

- Discover the serving release from the enabled nginx blocks (strip
  inline comments, `readlink -f`); never assume a path.
- The release tree must contain `assets/sites/terminal-city-times/` and
  `app/views/front-terminalcity.php`. If it doesn't, stop — the
  foundations are not in a shipped release yet.

## Step 1 — DNS

`terminalcitytimes.ca` and `www.terminalcitytimes.ca` → the VPS; verify
with dig from the box.

## Step 2 — Generate the nginx block (never copy one)

    bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
      terminalcitytimes.ca www.terminalcitytimes.ca

Both address families; no `default_server`; test and reload.

## Step 3 — TLS

certbot for both hostnames; confirm `listen 443 ssl` survived on BOTH
families.

## Step 4 — Seed

    cd "$REL" && PP_SITE=terminal-city-times php tools/seed-launch.php

Pre-seed expectation table (verify only what exists):

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Template class `t-terminalcity`, terminalcity.css, roundel placeholder mark | Release tree | Present before the seed |
| Title "Terminal City Times", tagline "News from the end of steel" | This pack's settings | Absent until this seed |
| Desks in the nav | Shared `categories` + chrome.nav | `arts` may print "desk added"; the rest exist network-wide — read the real list with `categories_all()` from `$REL`, assert no absences |
| Stories | This pack | NONE — by design, before AND after |

Idempotent: a re-run prints "exists, skipped" and changes nothing.

## Step 5 — Verify (served bytes, two-failure hard stop)

1. `https://terminalcitytimes.ca/` → 200, `<title>` contains "Terminal
   City Times", body class `t-terminalcity`,
   `/assets/css/terminalcity.css` linked.
2. Front page shows the EMPTY STATE ("The first edition is on the
   press") — that is a PASS.
3. Nav: Front Page + six desks (Local News, City Hall, Business, Arts,
   Sports, Opinion). Nav renders in uppercase via CSS `text-transform`;
   the SERVED bytes are mixed case — grep for "City Hall", not
   "CITY HALL" (the Bleuet Blanc lesson).
4. `/desk/arts` → 200 with the desk description.
5. `noindex` present on every page (indexing off) — a PASS.
6. `/api/ingest` → 401 without a token; `/admin/` → login page.
7. Spot-check two sister papers still serve their own mastheads.

## Step 6 — Close out

No newsletter sends; indexing stays OFF; no Hermes token exists for
this paper. Report: release SHA, seed lines, verify table, TLS expiry.
