# Taking kermodechronicle.ca live — deployment runbook

This runbook takes the **Kermode Chronicle** live on the VPS that already
serves the network (prairiedispatch.ca, plus edmontonecho.com and
grandeprairiegazette.ca as they deploy). It assumes DEPLOY.md was
followed for the Dispatch: same server, same release directory, same
Supabase database. Follow the steps in order; each ends with a
verification to pass before moving on. Nothing here requires editing
application code.

The Chronicle's domain is **kermodechronicle.ca**. The bare domain is
canonical; `www` serves via the same block.

**The one rule stands:** credentials never enter the repository.
`config.php` lives only on the server.

---

## 0 · What you are deploying

- Branch `claude/prairie-post-news-site-hiffgl`, at or after commit
  `cf1173e` — **pull the latest HEAD first.** The Chronicle's template,
  brand assets (including the bear crest), and launch package all live
  in that commit; a stale release renders the paper in the wrong design.
- The Chronicle is **the same codebase and the same release directory**
  as the other papers — another nginx server block and a config mapping,
  never a copy of the code.
- No database work: the site self-provisions its row in the shared
  Supabase schema on first request; its launch content ships in the repo.

```bash
cd /path/to/release && git fetch origin claude/prairie-post-news-site-hiffgl \
  && git checkout claude/prairie-post-news-site-hiffgl \
  && git pull origin claude/prairie-post-news-site-hiffgl
```

**Verify:** `git log --oneline -1` is at `cf1173e` or later, and
`ls assets/sites/kermode-chronicle/img/` lists ten photo-*.svg files.

## 1 · DNS check (both names)

```bash
dig +short kermodechronicle.ca A
dig +short www.kermodechronicle.ca A
```

If the server reports `dig: command not found`, do **not** stop — use
`getent`, which is part of glibc and needs no install (note it consults
`/etc/hosts` before DNS, so cross-check that file if the answer looks
wrong):

```bash
getent ahostsv4 kermodechronicle.ca      | awk '{print $1}' | sort -u
getent ahostsv4 www.kermodechronicle.ca  | awk '{print $1}' | sort -u
```

**Verify:** both return the VPS address. If either record is missing,
report back to the owner with which one is needed before continuing —
certbot in step 3 needs every name it certifies to resolve.

## 2 · nginx server block

Copy the block from DEPLOY.md §3a **verbatim** except for two lines:

```nginx
server_name kermodechronicle.ca www.kermodechronicle.ca;
root /path/to/release;    # the SAME directory the other papers serve
```

Keep every protection rule and rewrite exactly as written — the deny
rules for `/app/`, `/data/`, `/uploads/*.php` and the config files are
load-bearing. Enable the site and reload nginx.

**Verify:** `nginx -t` passes;
`curl -H 'Host: kermodechronicle.ca' http://127.0.0.1/` returns HTML
(any paper's — the host mapping comes in step 4).

## 3 · TLS

```bash
certbot --nginx -d kermodechronicle.ca -d www.kermodechronicle.ca
```

**Verify:** `https://kermodechronicle.ca/` serves with a valid
certificate; plain http redirects to https; the www name serves too.

## 4 · The host mapping in config.php

Edit the server's existing `config.php` (do not copy a fresh example —
the Supabase credentials in it are the live ones). The full network
pattern, safe to install in one edit even for papers not yet deployed:

```php
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');

// ... inside the returned array:
'site_slug' => match (true) {
    str_contains($host, 'kermodechronicle')     => 'kermode-chronicle',
    str_contains($host, 'edmontonecho')         => 'edmonton-echo',
    str_contains($host, 'grandeprairiegazette') => 'grande-prairie-gazette',
    default                                     => 'prairiedispatch',
},
'site_url' => match (true) {
    str_contains($host, 'kermodechronicle')     => 'https://kermodechronicle.ca',
    str_contains($host, 'edmontonecho')         => 'https://edmontonecho.com',
    str_contains($host, 'grandeprairiegazette') => 'https://www.grandeprairiegazette.ca',
    default                                     => 'https://prairiedispatch.ca',
},
```

Everything else in config.php — driver, pooler credentials, schema
`prairiedispatch`, timezone — stays exactly as it is. (The network runs
on America/Edmonton; if the owner wants Chronicle timestamps in Pacific
time, that is a follow-up decision, not a launch blocker.)

**Verify:** `php -l config.php` passes, then:
- `https://kermodechronicle.ca/` renders **the Chronicle design**: navy
  masthead with the bear crest and serif "Kermode Chronicle" wordmark,
  serif nav (Environment · Wildlife · Climate · …), mint Subscribe
  button.
- Every other live paper still renders its own chrome (Dispatch cream
  classic; Echo dark navy/orange; Gazette aurora). If two domains show
  the same paper, the mapping isn't matching.

The first Chronicle request joins it to the network automatically: a
site row and default settings, nothing else touched. There is **no
founding-account form** — existing network admins sign in at `/admin/`
immediately.

## 5 · Launch content — one command

From the release directory:

```bash
PP_SITE=kermode-chronicle php tools/seed-launch.php
```

This fills the paper so it is finished on day one: identity ("Kermode
Chronicle", "Reporting from the coast and the interior", the Lekwungen
acknowledgment in the footer, The Coast Report newsletter), eight
shared desks (Environment, Wildlife, Climate, Resources, Communities,
Coast, Interior, Culture — created only where missing), five BC wire
feeds, the Field Notes rail note, and eighteen launch stories with
commissioned art. Safe to re-run; it never overwrites a setting the
newsroom has already changed. Expect the output to end
`Done — 18 stories added.`

**Verify, all on https://kermodechronicle.ca:**
- `/` — full-bleed hero headed "Province defers logging on 2,100
  hectares in the Nass Valley", second lead with the sockeye
  illustration, the Latest rail with timestamps, the mint Field Notes
  box with the crest, three illustrated cards, The Coast Report band,
  and Opinion + Communities columns
- `/story/province-defers-logging-on-2100-hectares-in-the-nass-valley` renders
- `/card/province-defers-logging-on-2100-hectares-in-the-nass-valley.png`
  returns a 1200×630 PNG
- `/desk/environment`, `/desk/coast`, `/feed/`, `/sitemap.xml` all 200
- `/app/bootstrap.php` returns **403**

## 6 · Cron

Add another job beside the existing ones (the CLI has no hostname, so
the site must be explicit), daily at **06:00**:

```
PP_SITE=kermode-chronicle php /path/to/release/cron/fetch-news.php
```

Overlap with the other papers' jobs is harmless — feed fetching is
shared and de-duplicated; each paper's job matters for its own
newsletter and scheduled stories.

**Verify:** run it once by hand; expect `ok` lines for CBC British
Columbia, The Narwhal, Terrace Standard, Prince Rupert Northern View,
and Times Colonist, then check the wire tabs in `/admin/` show a
British Columbia tab with items.

## 7 · Mail — before enabling The Coast Report

Per-domain, never inherited from the other papers:

1. Create the sending mailbox, e.g. `report@kermodechronicle.ca`, and
   make sure `tips@kermodechronicle.ca` exists too — the launch package
   prints it as the tips line.
2. DNS for kermodechronicle.ca: the host's **SPF** record, **DKIM**
   signing, then DMARC once both pass. Without these the editions land
   in spam.
3. In the Chronicle's `/admin` → newsletter settings: SMTP host/port/
   user/password, From `report@kermodechronicle.ca`, From name
   `Kermode Chronicle`, and the paper's **postal mailing address**
   (CASL requires it in every edition; the footer template prints it).
4. **Send me a test** → confirm it lands in a real inbox at Gmail and
   one other provider, not spam.
5. Only then enable daily sending. The Coast Report is pitched as a
   weekly letter — until a weekly cadence exists, the daily send simply
   goes out on days with published stories, which is acceptable at
   launch; a true weekly schedule is a follow-up feature, not a
   launch blocker.

## 8 · Same-day housekeeping

- Confirm HTTPS is forced on both Chronicle hostnames.
- The eighteen launch stories are demonstration content in the paper's
  voice — the newsroom replaces them with real reporting at its own
  pace (Newsroom → Stories). Desks, feeds, the Field Notes note and
  settings are keepers.
- Update the strip's location/weather line (Settings) and point the
  Field Notes link somewhere real when the sightings record exists —
  it currently links to the site search for "kermode".
- Snapshot check on the other live papers: each front page still
  renders its own chrome after the config edit.

## What NOT to do

- Don't run `supabase/schema.sql` — the database is long since
  initialized.
- Don't change `site_slug` mappings after first boot; the slug is the
  site's permanent identity.
- Don't copy the release directory per domain. One directory, many
  server blocks — that's what keeps images and syndicated stories
  working across the network.
- Don't enable the newsletter before SPF/DKIM pass on
  kermodechronicle.ca specifically.
