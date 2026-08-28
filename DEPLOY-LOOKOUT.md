# Taking londonlookout.com live — deployment runbook

This runbook takes **The London Lookout** live on the VPS that already
serves the network. Same codebase, same shared database — a release
upgrade, a generated nginx block, a certificate, and one seed command.
**There is no config-file step.** The tenant mapping is two database
rows the paper's own launch pack writes through the seeder.

**Hold this runbook until DNS exists.** At the time of writing,
`londonlookout.com` is not pointed at the server. Step 0's DNS check is
a hard gate: if either name fails to resolve to this host, stop there
and report — nothing below works without it.

Site slug `london-lookout`, template `lookout` (different strings on
purpose). The bare domain is canonical; `www` serves the same paper.

**This paper launches with twenty-one demonstration stories** written to
the brand book's voice section. They are illustrative, not journalism;
the newsroom replaces them.

## Step 0 — Discover, pin, and check DNS

Enumerate the enabled vhosts; record the release root and the live
domain list. Pin the merge commit in a scratch clone (never inside the
release): require it to be an ancestor of
`origin/claude/master-dashboard-control-room-nr3mp4`.

DNS gate, from the box:

    dig +short londonlookout.com A
    dig +short www.londonlookout.com A

Both must return this server's address. **If they do not, stop here.**

Baseline: the full network loop (every existing hostname 200 with its
own exact title) plus the language controls (three `lang="en"` papers,
bleuetblanc.ca `lang="fr"`).

## Step 1 — Release upgrade

As root: `bash "$REL/tools/vps/upgrade-papers.sh"` (the pre-staged
tarball route via `PP_RELEASE_TARBALL` if codeload is unreachable).
Verify: no FATAL, no rollback; title and stylesheet-set guards pass;
every paper on the new release; the Lookout's files present:

    ls "$REL/assets/sites/london-lookout/" "$REL/assets/sites/london-lookout/img/"
    ls "$REL/assets/css/lookout.css"
    ls "$REL/app/views/chrome/lookout-head.php" "$REL/app/views/front-lookout.php"

Immediately after the first request: the full network loop and language
controls. No migration is expected — schema stays 16.

## Step 2 — Generate the nginx block (never copy one)

    SOCKET=$(grep -rhEo 'unix:[^;]+' "$(readlink -f /etc/nginx/sites-enabled/kitchenerchronicle)" | head -1 | sed 's/unix://')
    bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
      londonlookout.com www.londonlookout.com \
      > /etc/nginx/sites-available/londonlookout
    ln -s /etc/nginx/sites-available/londonlookout /etc/nginx/sites-enabled/
    nginx -t && systemctl reload nginx

Verify: `curl -sI -H "Host: londonlookout.com" http://127.0.0.1/`
returns 200. **The page is another paper's chrome at this point** — the
tenant rows arrive with the seeder. An immediate post-reload 404 settles
in seconds; re-probe once.

## Step 3 — TLS

    certbot --nginx -d londonlookout.com -d www.londonlookout.com

Before any curl: the block must carry BOTH `listen 443 ssl;` and
`listen [::]:443 ssl;` plus the options-ssl include and ssl_dhparam —
mirror a sibling if any is missing. Then: valid certificate on both
names; http redirects to https; SNI returns CN = londonlookout.com;
never add default_server (the catch-all owns it); every sister domain
still serves its own CN.

## Step 4 — Seed: one command

    cd "$REL" && PP_SITE=london-lookout php tools/seed-launch.php

Read for COLLISION, CONFLICT or FAILED first — any such line, or a
non-zero exit, is a full stop. Expected:

- `domain: londonlookout.com` and `domain: www.londonlookout.com`
- **NO `desk added:` lines at all.** All six desks this paper uses
  (local-news, city-hall, business, events, sports, opinion) already
  exist network-wide and are reused. Their absence from the output is
  the pack working — this is the first launch in the network's history
  that adds no desk.
- `setting:` lines including `council_tracker`, `open_files`,
  `council_agenda`, `member_pitch`, `weather_line` and
  `automated_byline` (no wire desk — future Hermes filings land as
  drafts).
- `source added: CBC London` (CBC Toronto is shared and skipped by URL
  match — expected).
- `Done — 21 stories added.` with zero skips.

Then confirm resolution comes from the database:

    php tools/resolve-host.php londonlookout.com
    php tools/resolve-host.php www.londonlookout.com

Both must print: `db london-lookout`

## Step 5 — Verify the live paper (served bytes)

On https://londonlookout.com:

1. `<title>The London Lookout — Eyes on the Forest City` and
   `<html lang="en">`; the source contains `lookout.css` and
   `t-lookout`.
2. The masthead: the served HTML contains `mark-reversed.svg`, the
   split wordmark (`The London` above `LOOKOUT`), and the utility strip
   with today's date and `Forest City`.
3. The front carries the paper's two signature panels: the source
   contains `ll-tracker` with "Wellington bus corridor, phase 3", and
   `ll-files` with "Open files we are still chasing" and "Day 41".
4. The desk columns are Local News, Events and Business, with Opinion
   and Sports paired beneath. `/desk/local-news /desk/city-hall
   /desk/business /desk/events /desk/sports /desk/opinion` — all 200,
   none empty. **Do not stop over desk descriptions that differ from
   this pack**: desks are shared network-wide and the seeder never
   overwrites an existing desk's description.
5. `/story/ll-missed-inspections-adelaide-portfolio` — 200; contains a
   `<blockquote>` with a `<cite>` naming Coun. A. Deshpande, and an
   `ll-updates` section ("Update log") ending in "Published."
6. `/search /corrections /feed/ /sitemap.xml /newsletter/` — all 200.
7. Fonts: every family the page names is declared by `@font-face` in a
   stylesheet the page loads, with all `src` urls local — `lookout.css`
   contains `@import url('/assets/css/fonts.css')` and the only family
   it names is Inter, declared there. No external host appears as a
   LOADED resource anywhere in the page or its CSS graph.
8. Ingest is closed here too: POST https://londonlookout.com/ingest.php
   with no Authorization header → 401 "missing bearer token".
9. The final full network loop over every enabled hostname including
   both new names, plus the named-vhost certificate sweep.

## Step 6 — Close out

No cron changes (the network fetch covers this paper's two sources).
Newsletter stays OFF. No Hermes token — owner-ordered separately, and
this paper is drafts-only when one comes.

**Two design features are deliberately not in this build, and should be
reported as deferred rather than treated as defects:**

- **"How we know this."** The brand book's methodology box is the
  platform's provenance box, which renders automatically for
  agent-filed stories carrying sources. Human-written stories do not
  show it yet — that needs the provenance box to accept sources without
  an agent, tracked in PLAN's backlog.
- **The multi-entry update log.** The template shows what the platform
  can prove: publication, plus a revision line when the story was
  edited after publishing. Per-entry dated updates need the ingest
  revision path, also in the backlog.
