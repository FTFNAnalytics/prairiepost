# Taking edmontonecho.com live — deployment runbook

This runbook takes **The Edmonton Echo** live on the VPS that already
serves prairiedispatch.ca (and, if step order follows the docket,
grandeprairiegazette.ca). It assumes DEPLOY.md was followed for the
Dispatch: same server, same release directory, same Supabase database.
Follow the steps in order; each ends with a verification to pass before
moving on. Nothing here requires editing application code.

The Echo's domain is **edmontonecho.com** (.com — the launch package's
footer email matches). The bare domain is canonical; `www` serves and
redirects via the same block.

**The one rule stands:** credentials never enter the repository.
`config.php` lives only on the server.

---

## 0 · What you are deploying

- Branch `claude/prairie-post-news-site-hiffgl`, at or after commit
  `b17f706` — **pull the latest HEAD first.** The Echo's dark broadsheet
  template (echo-v3), its brand assets, and its launch package all live
  in recent commits; a stale release renders the Echo in the wrong
  design, and an older launch package prints a `.ca` contact address.
- The Echo is **the same codebase and the same release directory** as the
  other papers — a third nginx server block and a config mapping, never a
  copy of the code.
- No database work: the site self-provisions its row in the shared
  Supabase schema on first request; its launch content ships in the repo.

```bash
cd /path/to/release && git fetch origin claude/prairie-post-news-site-hiffgl \
  && git checkout claude/prairie-post-news-site-hiffgl \
  && git pull origin claude/prairie-post-news-site-hiffgl
```

**Verify:** `git log --oneline -1` is at `b17f706` or later, and
`ls assets/sites/edmonton-echo/img/` lists seven photo-*.svg files.

## 1 · DNS check (both names)

```bash
dig +short edmontonecho.com A
dig +short www.edmontonecho.com A
```

If the server reports `dig: command not found`, do **not** stop — use
`getent`, which is part of glibc and needs no install (note it consults
`/etc/hosts` before DNS, so cross-check that file if the answer looks
wrong):

```bash
getent ahostsv4 edmontonecho.com      | awk '{print $1}' | sort -u
getent ahostsv4 www.edmontonecho.com  | awk '{print $1}' | sort -u
```

**Verify:** both return the VPS address. If either is missing, report
back to the owner with which record is needed before continuing —
certbot in step 3 needs every name it certifies to resolve.

## 2 · nginx server block

Copy the block from DEPLOY.md §3a **verbatim** except for two lines:

```nginx
server_name edmontonecho.com www.edmontonecho.com;
root /path/to/release;    # the SAME directory the other papers serve
```

Keep every protection rule and rewrite exactly as written — the deny
rules for `/app/`, `/data/`, `/uploads/*.php` and the config files are
load-bearing. Enable the site and reload nginx.

**Verify:** `nginx -t` passes;
`curl -H 'Host: edmontonecho.com' http://127.0.0.1/` returns HTML (any
paper's — the host mapping comes in step 4).

## 3 · TLS

```bash
certbot --nginx -d edmontonecho.com -d www.edmontonecho.com
```

**Verify:** `https://edmontonecho.com/` serves with a valid certificate;
plain http redirects to https; `https://www.edmontonecho.com/` also
serves (redirecting www→bare is nice-to-have, not required — canonical
tags already point at the bare domain).

## 4 · The host mapping in config.php

Edit the server's existing `config.php` (do not copy a fresh example —
the Supabase credentials in it are the live ones). The full three-paper
pattern:

```php
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');

// ... inside the returned array:
'site_slug' => match (true) {
    str_contains($host, 'edmontonecho')         => 'edmonton-echo',
    str_contains($host, 'grandeprairiegazette') => 'grande-prairie-gazette',
    default                                     => 'prairiedispatch',
},
'site_url' => match (true) {
    str_contains($host, 'edmontonecho')         => 'https://edmontonecho.com',
    str_contains($host, 'grandeprairiegazette') => 'https://www.grandeprairiegazette.ca',
    default                                     => 'https://prairiedispatch.ca',
},
```

If the Gazette hasn't deployed yet, its two lines are harmless to include
now. Everything else in config.php — driver, pooler credentials, schema
`prairiedispatch`, timezone — stays exactly as it is.

**Verify:** `php -l config.php` passes, then:
- `https://edmontonecho.com/` renders **the dark broadsheet design**:
  navy page, boxed EDMONTON (white) / ECHO (orange) masthead, tab nav.
- `https://prairiedispatch.ca/` still renders the Dispatch's cream
  classic chrome (and the Gazette, if live, its aurora chrome). If two
  domains show the same paper, the mapping isn't matching.

The first Echo request joins it to the network automatically: a site row
and default settings, nothing else touched. There is **no
founding-account form** — existing network admins sign in at `/admin/`
immediately.

## 5 · Launch content — one command

From the release directory:

```bash
PP_SITE=edmonton-echo php tools/seed-launch.php
```

This fills the paper so it is finished on day one: identity ("The
Edmonton Echo", "Edmonton, first thing"), the Sports and Culture desks,
an `edmonton` wire region with three feeds (St. Albert Gazette, Taproot
Edmonton, Daily Hive Edmonton), the weather/traffic/events rails, the
footer contact `tips@edmontonecho.com`, and ten launch stories with
commissioned art. Safe to re-run; it never overwrites a setting the
newsroom has already changed. Expect the output to end
`Done — 10 stories added.`

**Verify, all on https://edmontonecho.com:**
- `/` — 16:9 lead with the "Top story" badge headed "Two hours on
  curbside parking…", Quick News list with illustrated thumbnails,
  Trending Now ranked 1–5, the Edmonton weather card, orange left-rail
  boxes (Latest news / Sections / Traffic / Events)
- `/story/two-hours-on-curbside-parking-and-council-finally-says-what-downtown-is-for` renders in the dark chrome
- `/card/two-hours-on-curbside-parking-and-council-finally-says-what-downtown-is-for.png`
  returns a 1200×630 PNG in navy/orange
- `/desk/sports`, `/desk/culture`, `/feed/`, `/sitemap.xml` all 200
- `/app/bootstrap.php` returns **403**

## 6 · Cron

Add another job beside the existing ones (the CLI has no hostname, so
the site must be explicit), daily at **06:00 America/Edmonton**:

```
PP_SITE=edmonton-echo php /path/to/release/cron/fetch-news.php
```

Overlap with the other papers' jobs is harmless — feed fetching is
shared and de-duplicated; each paper's job matters for its own
newsletter and scheduled stories.

**Verify:** run it once by hand; expect `ok` lines for the three
Edmonton feeds, then check the wire tabs in `/admin/` show an Edmonton
tab with items.

## 7 · Mail — before enabling The 6 a.m.

Per-domain, never inherited from the other papers:

1. Create the sending mailbox, e.g. `sixam@edmontonecho.com`, and make
   sure `tips@edmontonecho.com` exists too — the launch package prints
   it in the site footer.
2. DNS for edmontonecho.com: the host's **SPF** record, **DKIM**
   signing, then DMARC once both pass. Without these the editions land
   in spam.
3. In the Echo's `/admin` → newsletter settings: SMTP host/port/user/
   password, From `sixam@edmontonecho.com`, From name
   `The Edmonton Echo`, and the paper's **postal mailing address**
   (CASL requires it in every edition; the footer template prints it).
4. **Send me a test** → confirm it lands in a real inbox at Gmail and
   one other provider, not spam.
5. Only then enable daily sending.

## 8 · Same-day housekeeping

- Confirm HTTPS is forced on both Echo hostnames.
- The ten launch stories are demonstration content in the paper's voice —
  the newsroom replaces them with real reporting at its own pace
  (Newsroom → Stories). Desks, feeds, rails and settings are keepers.
- Refresh the rails with current, real items: the weather card
  (Settings → forecast JSON, including the `fact_label`/`fact` pair the
  card's third stat uses), the Traffic and Events boxes, and the
  breaking banner if there's news.
- Snapshot check on the other live papers: each front page still renders
  its own chrome after the config edit.

## What NOT to do

- Don't run `supabase/schema.sql` — the database is long since
  initialized.
- Don't change `site_slug` mappings after first boot; the slug is the
  site's permanent identity.
- Don't copy the release directory per domain. One directory, many
  server blocks — that's what keeps images and syndicated stories
  working across the network.
- Don't enable the newsletter before SPF/DKIM pass on edmontonecho.com
  specifically.
