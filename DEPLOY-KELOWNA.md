# Taking kelownacurrent.ca live — deployment runbook

This runbook takes **Kelowna Current** live on the VPS that already
serves the network. It assumes DEPLOY.md was followed for the Dispatch:
same server, same release directory, same Supabase database. Follow the
steps in order; each ends with a verification to pass before moving on.
Nothing here requires editing application code.

The Current's domain is **kelownacurrent.ca**. The bare domain is
canonical; `www` serves via the same block.

**The one rule stands:** credentials never enter the repository.
`config.php` lives only on the server.

---

## 0 · What you are deploying

- Branch `claude/prairie-post-news-site-hiffgl` at the latest HEAD —
  **pull first.** The Current's template, brand assets and launch
  package are in the most recent commits.
- The Current is **the same codebase and the same release directory** as
  the other papers — another nginx server block and a config mapping,
  never a copy of the code.
- No database work: the site self-provisions its row in the shared
  Supabase schema on first request; its launch content ships in the repo.

```bash
cd /path/to/release && git fetch origin claude/prairie-post-news-site-hiffgl \
  && git checkout claude/prairie-post-news-site-hiffgl \
  && git pull origin claude/prairie-post-news-site-hiffgl
```

**Verify:** `ls assets/sites/kelowna-current/img/` lists twelve
photo-*.svg files.

## 1 · DNS check (both names)

```bash
dig +short kelownacurrent.ca A
dig +short www.kelownacurrent.ca A
```

**Verify:** both return the VPS address. If either record is missing,
report back to the owner with which one is needed before continuing.

## 2 · nginx server block

Copy the block from DEPLOY.md §3a **verbatim** except for two lines:

```nginx
server_name kelownacurrent.ca www.kelownacurrent.ca;
root /path/to/release;    # the SAME directory the other papers serve
```

Keep every protection rule and rewrite exactly as written. Enable the
site and reload nginx.

**Verify:** `nginx -t` passes;
`curl -H 'Host: kelownacurrent.ca' http://127.0.0.1/` returns HTML.

## 3 · TLS

```bash
certbot --nginx -d kelownacurrent.ca -d www.kelownacurrent.ca
```

**Verify:** `https://kelownacurrent.ca/` serves with a valid
certificate; plain http redirects to https.

## 4 · The host mapping in config.php

Edit the server's existing `config.php`. The full six-paper pattern,
safe to install in one edit even for papers not yet deployed:

```php
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');

// ... inside the returned array:
'site_slug' => match (true) {
    str_contains($host, 'kelownacurrent')       => 'kelowna-current',
    str_contains($host, 'thepacificpost')       => 'pacific-post',
    str_contains($host, 'kermodechronicle')     => 'kermode-chronicle',
    str_contains($host, 'edmontonecho')         => 'edmonton-echo',
    str_contains($host, 'grandeprairiegazette') => 'grande-prairie-gazette',
    default                                     => 'prairiedispatch',
},
'site_url' => match (true) {
    str_contains($host, 'kelownacurrent')       => 'https://kelownacurrent.ca',
    str_contains($host, 'thepacificpost')       => 'https://thepacificpost.com',
    str_contains($host, 'kermodechronicle')     => 'https://kermodechronicle.ca',
    str_contains($host, 'edmontonecho')         => 'https://edmontonecho.com',
    str_contains($host, 'grandeprairiegazette') => 'https://www.grandeprairiegazette.ca',
    default                                     => 'https://prairiedispatch.ca',
},
```

Everything else in config.php stays exactly as it is.

**Verify:** `php -l config.php` passes, then:
- `https://kelownacurrent.ca/` renders **the Current's design**: the
  centred KELOWNA / Current masthead with the teal wave, the sticky
  uppercase nav, and the teal "The Current" briefing strip.
- Every other live paper still renders its own chrome. If two domains
  show the same paper, the mapping isn't matching.

The first request joins the site to the network automatically; existing
network admins sign in at `/admin/` immediately — no founding-account
form.

## 5 · Launch content — one command

From the release directory:

```bash
PP_SITE=kelowna-current php tools/seed-launch.php
```

This fills the paper: identity (Kelowna Current, "From the Okanagan.
Across British Columbia.", The Morning Current newsletter), the
Okanagan, Economy and Housing desks, four verified Okanagan wire feeds,
and twenty launch stories with commissioned art in the brand's own
illustration style. Safe to re-run; it never overwrites a setting the
newsroom has already changed. Expect the output to end
`Done — 20 stories added.`

**Verify, all on https://kelownacurrent.ca:**
- `/` — "The valley's next decade…" hero with the lake illustration,
  Today's briefing rail with square thumbs, B.C. in Brief with gold
  numerals, Across the Regions fully illustrated, the navy Politics &
  Public Life lead, the mist Okanagan Life panel, and the Morning
  Current signup block
- `/story/the-valley-s-next-decade-will-be-decided-by-what-it-builds-now` renders
- `/card/the-valley-s-next-decade-will-be-decided-by-what-it-builds-now.png`
  returns a 1200×630 PNG
- `/desk/okanagan`, `/desk/economy`, `/feed/`, `/sitemap.xml` all 200
- `/app/bootstrap.php` returns **403**

## 6 · Cron

Add another job beside the existing ones, daily at **06:00**:

```
PP_SITE=kelowna-current php /path/to/release/cron/fetch-news.php
```

**Verify:** run it once by hand; expect `ok` lines for Kelowna Capital
News, Vernon Morning Star, Penticton Western News and Global Okanagan,
then check the wire tabs in `/admin/` show an Okanagan tab with items.

## 7 · Mail — before enabling The Morning Current

Per-domain, never inherited:

1. Create the sending mailbox, e.g. `morning@kelownacurrent.ca`, and
   make sure `tips@kelownacurrent.ca` exists too — the launch package
   prints it as the contact address.
2. DNS for kelownacurrent.ca: **SPF**, **DKIM**, then DMARC once both
   pass.
3. In the Current's `/admin` → newsletter settings: SMTP details, From
   `morning@kelownacurrent.ca`, From name `Kelowna Current`, and the
   paper's **postal mailing address** (CASL).
4. **Send me a test** → confirm it lands in a real inbox, not spam.
5. Only then enable daily sending.

## 8 · Same-day housekeeping

- Confirm HTTPS is forced on both hostnames.
- The twenty launch stories are demonstration content — the newsroom
  replaces them with real reporting at its own pace. Desks, feeds and
  settings are keepers.
- The Current strip currently carries the morning-briefing message in
  its teal variant (Settings → breaking banner label + link). For
  genuinely urgent news the guide reserves Summer Clay: change
  `strip_tone` in the site's palette.json from `teal` to `clay` (a
  repo change) or ask the developer to make the tone admin-editable.
- Update the utility bar's weather line (Settings) with live conditions.
- Snapshot check on the other live papers after the config edit.

## What NOT to do

- Don't run `supabase/schema.sql`.
- Don't change `site_slug` mappings after first boot.
- Don't copy the release directory per domain.
- Don't enable the newsletter before SPF/DKIM pass on kelownacurrent.ca
  specifically.
