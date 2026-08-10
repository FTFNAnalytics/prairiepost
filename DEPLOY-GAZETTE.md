# Taking grandeprairiegazette.ca live — deployment runbook

This runbook takes the **Grande Prairie Gazette** live on the VPS that
already serves prairiedispatch.ca. It assumes DEPLOY.md was followed for
the Dispatch: same server, same release directory, same Supabase database.
Follow the steps in order; each ends with a verification to pass before
moving on. Nothing here requires editing application code.

DNS for `www.grandeprairiegazette.ca` already points at the VPS
(user-confirmed). Step 1 checks the apex too.

**The one rule stands:** credentials never enter the repository.
`config.php` lives only on the server.

---

## 0 · What you are deploying

- Branch `claude/prairie-post-news-site-hiffgl`, at or after commit
  `0f097a5` — **pull the latest HEAD first.** The Gazette's aurora
  template, brand assets, and launch package all live in recent commits;
  a stale release directory will render the Gazette in the wrong design.
- The Gazette is **the same codebase and the same release directory** as
  the Dispatch — a second nginx server block and a config mapping, not a
  second copy of the code.
- No database work of any kind: the site self-provisions its row in the
  shared Supabase schema on first request, and its launch content ships
  in the repo.

```bash
cd /path/to/release && git fetch origin claude/prairie-post-news-site-hiffgl \
  && git checkout claude/prairie-post-news-site-hiffgl \
  && git pull origin claude/prairie-post-news-site-hiffgl
```

**Verify:** `git log --oneline -1` shows `0f097a5` or later, and
`ls assets/sites/grande-prairie-gazette/img/` lists nine photo-*.svg files.

## 1 · DNS check (both names)

`www.grandeprairiegazette.ca` is done. Confirm the **apex** also resolves
to the VPS so bare-domain visitors and the TLS certificate both work:

```bash
dig +short grandeprairiegazette.ca A
dig +short www.grandeprairiegazette.ca A
```

**Verify:** both return the VPS address. If the apex is missing, add an
A record for `@` (or an ALIAS/ANAME) before continuing — certbot needs it
in step 3.

## 2 · nginx server block

Copy the block from DEPLOY.md §3a **verbatim** except for two lines:

```nginx
server_name grandeprairiegazette.ca www.grandeprairiegazette.ca;
root /path/to/release;    # the SAME directory prairiedispatch.ca serves
```

Keep every protection rule and rewrite exactly as written — the deny
rules for `/app/`, `/data/`, `/uploads/*.php` and the config files are
load-bearing. Enable the site and reload nginx.

**Verify:** `nginx -t` passes;
`curl -H 'Host: www.grandeprairiegazette.ca' http://127.0.0.1/` returns
HTML (any of the papers is fine at this stage — the host mapping comes
in step 4).

## 3 · TLS

```bash
certbot --nginx -d grandeprairiegazette.ca -d www.grandeprairiegazette.ca
```

**Verify:** `https://www.grandeprairiegazette.ca/` serves with a valid
certificate; plain http redirects to https.

## 4 · The host mapping in config.php

Edit the server's existing `config.php` (do not copy a fresh example —
the Supabase credentials in it are the live ones). Replace the two
identity lines with host-aware versions. The full pattern, covering all
three papers:

```php
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');

// ... inside the returned array:
'site_slug' => match (true) {
    str_contains($host, 'grandeprairiegazette') => 'grande-prairie-gazette',
    str_contains($host, 'edmontonecho')         => 'edmonton-echo',
    default                                     => 'prairiedispatch',
},
'site_url' => match (true) {
    str_contains($host, 'grandeprairiegazette') => 'https://www.grandeprairiegazette.ca',
    str_contains($host, 'edmontonecho')         => 'https://edmontonecho.ca',
    default                                     => 'https://prairiedispatch.ca',
},
```

`www` is the Gazette's canonical form (it's what the DNS was set up for);
the apex serves too and canonical tags point readers and crawlers at www.
Everything else in config.php — driver, pooler credentials, schema
`prairiedispatch`, timezone — stays exactly as it is.

**Verify:** `php -l config.php` passes, then:
- `https://www.grandeprairiegazette.ca/` renders **the aurora design**:
  deep-purple masthead with the Gazette logo card, white nav row
  (NEWS · POLITICS · ENERGY · …).
- `https://prairiedispatch.ca/` still renders the Dispatch's cream
  classic chrome. If both domains show the same paper, the mapping isn't
  matching — check the host strings.

The first Gazette request also joins it to the network automatically: a
site row and default settings, nothing else touched. There is **no
founding-account form** — existing network admins sign in at `/admin/`
immediately.

## 5 · Launch content — one command

From the release directory:

```bash
PP_SITE=grande-prairie-gazette php tools/seed-launch.php
```

This fills the paper so it is finished on day one: identity settings
(Peace Country's daily, The Morning Aurora), the Energy and Sports desks,
three Peace Country wire feeds, the dated events rail, and eleven launch
stories with commissioned art. Safe to re-run; it never overwrites a
setting the newsroom has already changed. Expect the output to end
`Done — 11 stories added.`

**Verify, all on https://www.grandeprairiegazette.ca:**
- `/` — aurora-photo hero headed "Aurora season opens…", Top stories
  with illustrated thumbnails, Most read populated, "This week in
  Grande Prairie" events with date blocks
- `/story/aurora-season-opens-and-the-countys-dark-sky-pullouts-draw-their-first-full-night` renders
- `/card/aurora-season-opens-and-the-countys-dark-sky-pullouts-draw-their-first-full-night.png`
  returns a 1200×630 PNG in midnight/emerald
- `/desk/energy`, `/feed/`, `/sitemap.xml` all 200
- `/app/bootstrap.php` returns **403**

## 6 · Cron

Add a second job beside the Dispatch's (the CLI has no hostname, so the
site must be explicit), daily at **06:00 America/Edmonton**:

```
PP_SITE=grande-prairie-gazette php /path/to/release/cron/fetch-news.php
```

Overlap with the Dispatch's job is harmless — feed fetching is shared and
de-duplicated; each paper's job matters for its own newsletter and
scheduled stories.

**Verify:** run it once by hand; expect `ok` lines for EverythingGP,
My Grande Prairie Now, and Fairview Post, then check the wire tabs in
`/admin/` show Peace Country items.

## 7 · Mail — before enabling The Morning Aurora

Per-domain, never inherited from the Dispatch:

1. Create the sending mailbox, e.g. `aurora@grandeprairiegazette.ca`, and
   make sure `tips@grandeprairiegazette.ca` exists too — the launch
   package already prints it in the site footer.
2. DNS for grandeprairiegazette.ca: the host's **SPF** record, **DKIM**
   signing, then DMARC once both pass. Without these the editions land
   in spam.
3. In the Gazette's `/admin` → newsletter settings: SMTP host/port/user/
   password, From `aurora@grandeprairiegazette.ca`, From name
   `Grande Prairie Gazette`, and the paper's **postal mailing address**
   (CASL requires it in every edition; the footer template prints it).
4. **Send me a test** → confirm it lands in a real inbox at Gmail and one
   other provider, not spam.
5. Only then enable daily sending. The heading and pitch are already set
   by the launch package ("The Morning Aurora", 6 a.m.).

## 8 · Same-day housekeeping

- Confirm HTTPS is forced on both Gazette hostnames.
- The eleven launch stories are demonstration content in the paper's
  voice — the newsroom replaces them with real reporting at its own
  pace (Newsroom → Stories). Desks, feeds, events and settings are
  keepers.
- Update the strip weather line (Settings → "Sky-bar weather line") and
  the events rail with current, real items.
- Snapshot check on prairiedispatch.ca and any other live paper: front
  page still renders their own chrome after the config edit.

## What NOT to do

- Don't run `supabase/schema.sql` — the database is long since
  initialized.
- Don't change `site_slug` mappings after first boot; the slug is the
  site's permanent identity.
- Don't copy the release directory per domain. One directory, many
  server blocks — that's what keeps images and syndicated stories
  working across the network.
- Don't enable the newsletter before SPF/DKIM pass on
  grandeprairiegazette.ca specifically.
