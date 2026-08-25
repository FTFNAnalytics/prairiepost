# Taking kitchenerchronicle.com live — deployment runbook

This runbook takes **The Kitchener Chronicle** live on the VPS that
already serves the network. Same codebase, same shared database — a
release upgrade, a generated nginx block, a certificate, and one seed
command. **There is no config-file step.** The tenant mapping is two
database rows the paper's own launch pack writes through the seeder.
If any step seems to require editing `app/config.site.php`, stop: the
step is wrong, not the file.

Its domain is **kitchenerchronicle.com**. The bare domain is canonical;
`www` serves the same paper. Site slug `kitchener-chronicle`, template
`kitchener` (different strings on purpose: the slug is hyphenated data,
the template is the file name and the `t-kitchener` body class).

**This paper launches with nineteen demonstration stories** drawn from
its design canvas. They are illustrative, not journalism; the newsroom
replaces them. Every slug is prefixed `kc-` and the pack was tested
against a database holding all fourteen sister papers' content with
zero collisions.

## Step 0 — Discover and pin

Enumerate the enabled vhosts and record the release root and the live
domain list. Pin the merge commit in a scratch clone (never inside the
release): the deployment brief names the commit; require it to be an
ancestor of `origin/claude/master-dashboard-control-room-nr3mp4`.

DNS pre-check: `kitchenerchronicle.com` and `www.kitchenerchronicle.com`
both resolve to this server.

Baseline: the full network loop — every existing hostname 200 with its
own exact title; language controls (three `lang="en"` checks,
bleuetblanc.ca `lang="fr"`).

## Step 1 — Release upgrade

As root: `bash "$REL/tools/vps/upgrade-papers.sh"` (with the pre-staged
tarball route if codeload is unreachable — the script supports
`PP_RELEASE_TARBALL`). Verify: no FATAL, no rollback; title and
stylesheet-set guards pass; every paper on the new release; the
Chronicle's files present by content:

    ls "$REL/assets/sites/kitchener-chronicle/"
    ls "$REL/assets/css/kitchener.css"
    ls "$REL/app/views/chrome/kitchener-head.php" "$REL/app/views/front-kitchener.php"

Immediately after the first request against the new release (it runs
any pending migration — none is expected this launch): re-run the full
network loop and language controls.

## Step 2 — Generate the nginx block (never copy one)

    SOCKET=$(grep -rhEo 'unix:[^;]+' "$(readlink -f /etc/nginx/sites-enabled/mississaugamonitor)" | head -1 | sed 's/unix://')
    bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
      kitchenerchronicle.com www.kitchenerchronicle.com \
      > /etc/nginx/sites-available/kitchenerchronicle
    ln -s /etc/nginx/sites-available/kitchenerchronicle /etc/nginx/sites-enabled/
    nginx -t && systemctl reload nginx

The generated block is a front controller (routes live in router.php)
with both port-80 address families. Verify:
`curl -sI -H "Host: kitchenerchronicle.com" http://127.0.0.1/` returns
200. **The page is another paper's chrome at this point** — the tenant
rows arrive with the seeder in step 4; do not stop over it. An
immediate post-reload 404 settles in seconds; re-probe once.

## Step 3 — TLS

    certbot --nginx -d kitchenerchronicle.com -d www.kitchenerchronicle.com

Before any curl: the block must carry BOTH `listen 443 ssl;` and
`listen [::]:443 ssl;` plus the options-ssl include and ssl_dhparam —
mirror a sibling if certbot omitted any. Then: valid certificate on
both names; http redirects to https on both; SNI returns
CN = kitchenerchronicle.com (reject.invalid means server_name missed;
another paper's CN means a listen line is missing — fix THIS block
only, never add default_server). Every sister domain still serves its
own CN.

## Step 4 — Seed: identity, desks, domains, stories, one command

    cd "$REL" && PP_SITE=kitchener-chronicle php tools/seed-launch.php

Read the output for COLLISION, CONFLICT or FAILED first — any such
line, or a non-zero exit, is a full stop. Expected output:

- `domain: kitchenerchronicle.com` and `domain: www.kitchenerchronicle.com`
  (the tenant mapping — written here, no config edit anywhere)
- `desk added: Ontario` — **exactly one**. Local News, Politics,
  Business & Markets, Sports and Culture already exist network-wide and
  are silently reused; their absence from "desk added" is the pack
  working. (This paper labels local-news "Local" and business
  "Business" in its own palette.json — the shared desk names are never
  touched.)
- `setting:` lines including `weather_line` and `automated_byline`
  (this paper declares **no wire desk** — every future Hermes filing
  lands as a draft)
- `Done — 19 stories added.` with zero skips.

Then confirm resolution comes from the database:

    php tools/resolve-host.php kitchenerchronicle.com
    php tools/resolve-host.php www.kitchenerchronicle.com

Both must print: `db kitchener-chronicle`

## Step 5 — Verify the live paper (every string as served bytes)

On https://kitchenerchronicle.com:

1. `<title>The Kitchener Chronicle — Kitchener · Waterloo · Ontario`
   and `<html lang="en">`; the source contains `kitchener.css` and
   `t-kitchener`.
2. The masthead: the served HTML contains `skyline-ghost.svg`, the gold
   "The" over the nameplate, and a dateline reading `Vol. CXVII` (the
   volume is derived from the year — 2026 renders CXVII; a later year
   renders a different numeral, which is correct, not drift).
3. The hero: served HTML contains "Region approves $1.9-billion plan
   for a second ION line"; the Ontario band contains "Ontario focus"
   and "Two-way, all-day GO service to Kitchener slips again, to 2029".
4. Desks: /desk/local-news /desk/politics /desk/business /desk/sports
   /desk/culture /desk/ontario — all 200, none empty. /desk/ontario is
   the ONLY desk page whose HTML contains `kc-civic` (the Ontario-green
   treatment). **Do not stop over desk descriptions that differ from
   this paper's launch pack**: desks are shared network-wide and the
   seeder never overwrites an existing desk's description — Politics
   showing a sister paper's standing description is the platform
   working, not an error.
5. /story/kc-ion-stage-two-approved — 200; contains a `<blockquote>`
   with a `<cite>` naming Coun. Priya Ellison.
6. /search /corrections /feed/ /sitemap.xml /newsletter/ — all 200.
7. Fonts, phrased at the layer that produces them: every family the
   page names is declared by `@font-face` in a stylesheet the page
   loads, with all `src` urls local — this paper's `kitchener.css`
   contains `@import url('/assets/css/fonts.css');` and names
   'Source Serif 4', which fonts.css declares (both valid shapes of
   this rule are documented in DEPLOY-MONITOR.md). No external host
   appears as a loaded resource anywhere in the page or its CSS graph.
8. Ingest is closed here too: POST
   https://kitchenerchronicle.com/ingest.php with no Authorization
   header → 401 {"ok":false,"error":"missing bearer token"}.
9. The final full network loop: every existing domain 200 as itself,
   language controls unchanged, certificate sweep clean.

## Step 6 — Close out

Nothing new in cron (the network fetch covers this paper's two
sources; the CBC Toronto feed is shared and already registered). Do NOT
enable the newsletter. Do NOT issue a Hermes token — that is a
separate, owner-ordered step, and this paper is drafts-only when it
comes (no wire desk).

Deferred by design, report only: the election-night live-results page
(design plate 06) is a special-format hub the platform does not yet
serve — it ships with the live-blog format work, tracked alongside the
Monitor's election hub in the standing backlog.
