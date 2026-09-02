# Taking londonlookout.com live — deployment runbook (v4)

This runbook takes **London Lookout** live on the VPS that already
serves the network. It assumes DEPLOY.md was followed for the Dispatch:
same server, same release directory, same Supabase database. Follow the
steps in order; each ends with a verification to pass before moving on.
Nothing here requires editing application code.

It inherits every hard-won lesson from the earlier launches:

- **Release integrity first** (v2): step 0 verifies the server's copy of
  the release before anything else. A JSON parse error on the server
  always means the *server copy* drifted — the repository copy is
  known-good — so the fix is `git reset --hard`, never re-typing files.
- **No `dig` dependency** (v3): the DNS check has a `getent` fallback
  (part of glibc, always present); a missing lookup tool is never a
  reason to halt.
- **Verify only what exists at each step** (v4): the gold "At Six"
  briefing strip renders only once the seeder has written the
  `breaking_label`/`breaking_url` settings, so its absence *before*
  step 5 is correct, not a failure. Step 4 checks only pre-seed chrome;
  the strip check lives in step 5.

The Lookout's domain is **londonlookout.com**. The bare domain is
canonical; `www` serves via the same block. DNS for both names has
already been pointed at the VPS by the owner — step 1 confirms rather
than configures.

**The one rule stands:** credentials never enter the repository.
`config.php` lives only on the server.

---

## 0 · Pull and verify the release

```bash
cd /path/to/release
git fetch origin claude/prairie-post-news-site-hiffgl
git checkout claude/prairie-post-news-site-hiffgl
git status
```

If `git status` shows modified or conflicted tracked files, the release
copy has drifted — repair it (safe: `config.php` is untracked and is
not touched):

```bash
git reset --hard origin/claude/prairie-post-news-site-hiffgl
```

Otherwise just pull:

```bash
git pull origin claude/prairie-post-news-site-hiffgl
```

Then verify the release integrity **on the server**:

```bash
ls assets/sites/london-lookout/          # launch.php, palette.json, favicon.svg, og-default.png, img/
ls assets/sites/london-lookout/img/      # eight photo-*.svg files
ls assets/css/lookout.css app/views/front-lookout.php

# every brand file must parse — prints one "No error" line per site:
for f in assets/sites/*/palette.json; do
  php -r '$f = $argv[1]; json_decode(file_get_contents($f));
    echo $f, ": ", json_last_error_msg(), PHP_EOL;' "$f"
done

php -l tools/seed-launch.php
php -l assets/sites/london-lookout/launch.php
php -l app/views/front-lookout.php
php -l app/views/ui.php
php -l index.php
```

**Verify:** the London Lookout files all list, eight art files show,
every palette line ends `No error`, and every lint check passes. If any
palette prints an error here, run the `git reset --hard` above and
re-check — the repository copy is known-good, so a server-side parse
error always means local drift. Do not proceed until this block is
clean.

## 1 · DNS check (both names)

Any DNS lookup tool answers this step — which tool does not matter,
only the addresses do. Use `dig` when the server has it:

```bash
dig +short londonlookout.com A
dig +short www.londonlookout.com A
```

If the server reports `dig: command not found`, do **not** stop — use
`getent`, which is part of glibc and needs no install:

```bash
getent ahostsv4 londonlookout.com     | awk '{print $1}' | sort -u
getent ahostsv4 www.londonlookout.com | awk '{print $1}' | sort -u
```

One caveat with `getent`: it consults `/etc/hosts` before DNS. If its
answer differs from what the registrar shows, check `/etc/hosts` for a
stale entry for either name before trusting the result.

**Verify:** both names resolve to the VPS address (via either tool).
The owner has already configured DNS for this launch; if either record
is nonetheless missing — most commonly the `www` record — report back
with which one is needed before continuing, because certbot in step 3
needs every name it certifies to resolve.

## 2 · nginx server block

Copy the block from DEPLOY.md §3a **verbatim** except for two lines:

```nginx
server_name londonlookout.com www.londonlookout.com;
root /path/to/release;    # the SAME directory the other papers serve
```

Keep every protection rule and rewrite exactly as written. Enable the
site and reload nginx.

**Verify:** `nginx -t` passes;
`curl -H 'Host: londonlookout.com' http://127.0.0.1/` returns HTML.

## 3 · TLS

```bash
certbot --nginx -d londonlookout.com -d www.londonlookout.com
```

**Verify:** `https://londonlookout.com/` serves with a valid
certificate; plain http redirects to https.

## 4 · The host mapping in config.php

Edit the server's existing `config.php` **in a plain-text editor on the
server** — do not paste through anything that rewrites quotes. Add one
arm to each of the two `match` blocks, leaving every existing arm
untouched:

```php
'site_slug' => match (true) {
    str_contains($host, 'londonlookout')        => 'london-lookout',
    // ... existing arms unchanged ...
    default                                     => 'prairiedispatch',
},
'site_url' => match (true) {
    str_contains($host, 'londonlookout')        => 'https://londonlookout.com',
    // ... existing arms unchanged ...
    default                                     => 'https://prairiedispatch.ca',
},
```

Everything else in config.php stays exactly as it is.

**Verify:** `php -l config.php` passes, then:
- `https://londonlookout.com/` renders **the Lookout's chrome**: the
  dark utility bar with the date, the centred LONDON / LOOKOUT masthead
  with the tower-and-sightline device between the two words, the sticky
  deep-green nav, and the `/assets/css/lookout.css` stylesheet with a
  `t-lookout` body class.
- Do **not** expect the gold "At Six" briefing strip yet, and do not
  stop over its absence. The strip renders only when the
  `breaking_label` and `breaking_url` settings are set, and step 5's
  seeder is what writes them — before seeding it is correctly absent
  (and the front page is otherwise nearly empty). Its check is in
  step 5.
- Every other live paper still renders its own chrome. If two domains
  show the same paper, the mapping isn't matching.

The first request joins the site to the network automatically; existing
network admins sign in at `/admin/` immediately — no founding-account
form.

## 5 · Launch content — one command

From the release directory:

```bash
PP_SITE=london-lookout php tools/seed-launch.php
```

This fills the paper: identity (London Lookout, "From the forks of the
Thames. Across Ontario.", The Lookout at Six newsletter), the London,
Ontario and Campus desks (Politics, Economy, Culture and Opinion are
shared network desks and are only created if missing), eight verified
Ontario wire feeds, and twenty launch stories with commissioned art in
the brand's own illustration style. Safe to re-run; it never overwrites
a setting the newsroom has already changed. Expect the output to end
`Done — 20 stories added.`

**Verify, all on https://londonlookout.com:**
- `/` — the "At Six" briefing strip now shows beneath the nav (the
  seeder wrote `breaking_label` and `breaking_url`) — this is the check
  deferred from step 4
- `/` — "The decade London is deciding right now" hero with the
  forks-at-evening illustration, The Watch rail with four numbered
  files, Ontario in Brief with brick numerals, From the Forks fully
  illustrated (yellow-brick lead, battery plant, campus), the City Hall
  & Queen's Park lead plus three politics rows and one opinion row, the
  fog Forest City Life panel with three illustrated cards, and the
  deep-green Lookout at Six signup block
- `/story/the-decade-london-is-deciding-right-now` renders
- `/card/the-decade-london-is-deciding-right-now.png` returns a
  1200×630 PNG
- `/desk/london`, `/desk/politics`, `/feed/`, `/sitemap.xml` all 200
- `/app/bootstrap.php` returns **403**

## 6 · Cron

Add another job beside the existing ones, daily at **06:00**:

```
PP_SITE=london-lookout php /path/to/release/cron/fetch-news.php
```

**Verify:** run it once by hand; expect `ok` lines for CBC London,
Global News London, CBC Toronto, CBC Ottawa, Global News Toronto,
CBC Politics and Global News Canada, then check the wire tabs in
`/admin/` show London / Ontario / Canada tabs with items. **One
expected wrinkle:** London Free Press is a Postmedia title and
intermittently blocks automated fetchers — an occasional error line for
that one feed is tolerated, not a failure. If it errors on every run
for a week, disable the source in `/admin/` rather than deleting it.

## 7 · Mail — before enabling The Lookout at Six

Per-domain, never inherited:

1. Create the sending mailbox, e.g. `six@londonlookout.com`, and make
   sure `tips@londonlookout.com` exists too — the launch package prints
   it as the contact address.
2. DNS for londonlookout.com: **SPF**, **DKIM**, then DMARC once both
   pass.
3. In the Lookout's `/admin` → newsletter settings: SMTP details, From
   `six@londonlookout.com`, From name `London Lookout`, and the paper's
   **postal mailing address** (CASL).
4. **Send me a test** → confirm it lands in a real inbox, not spam.
5. Only then enable daily sending.

## 8 · Same-day housekeeping

- Confirm HTTPS is forced on both hostnames.
- The twenty launch stories are demonstration content — the newsroom
  replaces them with real reporting at its own pace. Desks, feeds and
  settings are keepers.
- The strip's "At Six" label comes from `strip_label` in the site's
  palette.json chrome; the message and link are Settings → breaking
  banner label + URL, so the newsroom can repoint it at a breaking
  story without a repo change.
- Update the utility bar's weather line (Settings) with live conditions.
- Snapshot check on the other live papers after the config edit.

## Troubleshooting

| Symptom | Cause → fix |
|---|---|
| `dig: command not found` in step 1 | DNS utilities aren't installed on the VPS → use the `getent ahostsv4` fallback shown in step 1 (no install needed), or install `dnsutils` (Debian/Ubuntu) / `bind-utils` (RHEL/Alma) |
| "Unexpected character" in any JSON | Server copy drifted from the repo → step 0's `git reset --hard`, then re-run the palette validation loop |
| Briefing strip absent right after step 4 | Expected, not a failure — the strip needs the `breaking_label`/`breaking_url` settings, which step 5's seeder writes → proceed to step 5, then confirm the strip there |
| Front shows another paper's design | Host mapping not matching → check the `str_contains` strings in config.php against the actual Host header |
| Seeder reports `0 stories added` on first run | It already ran (matched by slug) — not an error; verify the front instead |
| London Free Press feed errors in cron | Postmedia intermittently blocks automated fetchers → tolerated; disable the source in `/admin/` only if it fails every run for a week |
| Palette validates but the site 500s | `php -l config.php`; if clean, set `debug => true` temporarily and read the error |
| Art boxes empty in your own page-test screenshots | Images are lazy-loaded; scroll the page before capturing — real readers are unaffected |

## What NOT to do

- Don't run `supabase/schema.sql`.
- Don't change `site_slug` mappings after first boot.
- Don't copy the release directory per domain.
- Don't re-type or transcribe repository files — pull them.
- Don't enable the newsletter before SPF/DKIM pass on londonlookout.com
  specifically.
