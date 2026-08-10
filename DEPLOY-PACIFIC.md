# Taking thepacificpost.com live — deployment runbook

This runbook takes **The Pacific Post** live on the VPS that already
serves the network. It assumes DEPLOY.md was followed for the Dispatch:
same server, same release directory, same Supabase database. Follow the
steps in order; each ends with a verification to pass before moving on.
Nothing here requires editing application code.

The Post's domain is **thepacificpost.com**. The bare domain is
canonical; `www` serves via the same block.

**The one rule stands:** credentials never enter the repository.
`config.php` lives only on the server.

---

## 0 · What you are deploying

- Branch `claude/prairie-post-news-site-hiffgl` at the latest HEAD —
  **pull first.** The Post's template, nameplate assets, and launch
  package are in the most recent commits.
- The Post is **the same codebase and the same release directory** as
  the other papers — another nginx server block and a config mapping,
  never a copy of the code.
- No database work: the site self-provisions its row in the shared
  Supabase schema on first request; its launch content ships in the repo.

```bash
cd /path/to/release && git fetch origin claude/prairie-post-news-site-hiffgl \
  && git checkout claude/prairie-post-news-site-hiffgl \
  && git pull origin claude/prairie-post-news-site-hiffgl
```

**Verify:** `ls assets/sites/pacific-post/img/` lists ten photo-*.svg
files.

## 1 · DNS check (both names)

```bash
dig +short thepacificpost.com A
dig +short www.thepacificpost.com A
```

**Verify:** both return the VPS address. If either record is missing,
report back to the owner with which one is needed before continuing.

## 2 · nginx server block

Copy the block from DEPLOY.md §3a **verbatim** except for two lines:

```nginx
server_name thepacificpost.com www.thepacificpost.com;
root /path/to/release;    # the SAME directory the other papers serve
```

Keep every protection rule and rewrite exactly as written. Enable the
site and reload nginx.

**Verify:** `nginx -t` passes;
`curl -H 'Host: thepacificpost.com' http://127.0.0.1/` returns HTML.

## 3 · TLS

```bash
certbot --nginx -d thepacificpost.com -d www.thepacificpost.com
```

**Verify:** `https://thepacificpost.com/` serves with a valid
certificate; plain http redirects to https.

## 4 · The host mapping in config.php

Edit the server's existing `config.php`. The full five-paper pattern,
safe to install in one edit even for papers not yet deployed:

```php
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');

// ... inside the returned array:
'site_slug' => match (true) {
    str_contains($host, 'thepacificpost')       => 'pacific-post',
    str_contains($host, 'kermodechronicle')     => 'kermode-chronicle',
    str_contains($host, 'edmontonecho')         => 'edmonton-echo',
    str_contains($host, 'grandeprairiegazette') => 'grande-prairie-gazette',
    default                                     => 'prairiedispatch',
},
'site_url' => match (true) {
    str_contains($host, 'thepacificpost')       => 'https://thepacificpost.com',
    str_contains($host, 'kermodechronicle')     => 'https://kermodechronicle.ca',
    str_contains($host, 'edmontonecho')         => 'https://edmontonecho.com',
    str_contains($host, 'grandeprairiegazette') => 'https://www.grandeprairiegazette.ca',
    default                                     => 'https://prairiedispatch.ca',
},
```

Everything else in config.php stays exactly as it is.

**Verify:** `php -l config.php` passes, then:
- `https://thepacificpost.com/` renders **the Post's design**: the
  centred THE PACIFIC POST nameplate with the mountain mark between
  thick black rules, serif nav with inlet-blue active tabs.
- Every other live paper still renders its own chrome. If two domains
  show the same paper, the mapping isn't matching.

The first request joins the site to the network automatically; existing
network admins sign in at `/admin/` immediately — no founding-account
form.

## 5 · Launch content — one command

From the release directory:

```bash
PP_SITE=pacific-post php tools/seed-launch.php
```

This fills the paper: identity (The Pacific Post, Your source for B.C.
news, The Morning Ferry newsletter, the Musqueam/Squamish/Tsleil-Waututh
acknowledgment in the footer), the BC News desk, four Vancouver/BC wire
feeds, and thirteen launch stories with commissioned art. Safe to
re-run; it never overwrites a setting the newsroom has already changed.
Expect the output to end `Done — 13 stories added.`

**Verify, all on https://thepacificpost.com:**
- `/` — Broadway-subway lead with the tunnel illustration, Opinion and
  Trending now rail modules, The Morning Ferry signup card, and the
  "Across British Columbia" band fully illustrated
- `/story/tunnelling-ends-on-the-broadway-subway-three-months-ahead-of-schedule` renders,
  with the cedar drop cap on the first paragraph
- `/card/tunnelling-ends-on-the-broadway-subway-three-months-ahead-of-schedule.png`
  returns a 1200×630 PNG
- `/desk/bc-news`, `/feed/`, `/sitemap.xml` all 200
- `/app/bootstrap.php` returns **403**

## 6 · Cron

Add another job beside the existing ones, daily at **06:00**:

```
PP_SITE=pacific-post php /path/to/release/cron/fetch-news.php
```

**Verify:** run it once by hand; expect `ok` lines for Daily Hive
Vancouver, Vancouver Is Awesome, North Shore News and Global BC, then
check the wire tabs in `/admin/` show a Greater Vancouver tab with
items.

## 7 · Mail — before enabling The Morning Ferry

Per-domain, never inherited:

1. Create the sending mailbox, e.g. `ferry@thepacificpost.com`, and make
   sure `tips@thepacificpost.com` exists too — the launch package prints
   it as the contact address.
2. DNS for thepacificpost.com: **SPF**, **DKIM**, then DMARC once both
   pass.
3. In the Post's `/admin` → newsletter settings: SMTP details, From
   `ferry@thepacificpost.com`, From name `The Pacific Post`, and the
   paper's **postal mailing address** (CASL).
4. **Send me a test** → confirm it lands in a real inbox, not spam.
5. Only then enable daily sending — The Morning Ferry is pitched at
   8 a.m., so set the send hour to 7 in the newsletter settings.

## 8 · Same-day housekeeping

- Confirm HTTPS is forced on both hostnames.
- The thirteen launch stories are demonstration content — the newsroom
  replaces them with real reporting at its own pace. Desks, feeds and
  settings are keepers.
- The breaking strip is off by default; it appears when Settings →
  breaking banner label + link are filled, and Signal orange is reserved
  for exactly that.
- Snapshot check on the other live papers after the config edit.

## What NOT to do

- Don't run `supabase/schema.sql`.
- Don't change `site_slug` mappings after first boot.
- Don't copy the release directory per domain.
- Don't enable the newsletter before SPF/DKIM pass on thepacificpost.com
  specifically.
