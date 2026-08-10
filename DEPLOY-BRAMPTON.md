# Taking bramptonbulletin.com live — deployment runbook

This runbook takes **The Brampton Bulletin** live on the VPS that
already serves the network. It assumes DEPLOY.md was followed for the
Dispatch: same server, same release directory, same Supabase database.
Follow the steps in order; each ends with a verification to pass before
moving on. Nothing here requires editing application code.

Step 0 verifies the integrity of the release before anything else.
Every JSON file at the repository HEAD is verified valid, so a JSON
parse error on the server always means the *server's copy* of the
release is stale, partially pulled, or was hand-edited — step 0 detects
and repairs all three. Do not transcribe or re-type file contents;
always operate on the pulled files directly.

The Bulletin's domain is **bramptonbulletin.com**. The bare domain is
canonical; `www` serves via the same block.

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
git log --oneline -1        # must include the Brampton Bulletin commit or later
ls assets/sites/brampton-bulletin/img/    # six photo-*.svg files

# every brand file must parse — prints one "No error" line per site:
for f in assets/sites/*/palette.json; do
  php -r '$f = $argv[1]; json_decode(file_get_contents($f));
    echo $f, ": ", json_last_error_msg(), PHP_EOL;' "$f"
done

php -l tools/seed-launch.php
php -l assets/sites/brampton-bulletin/launch.php
```

**Verify:** six art files list, every palette line ends `No error`, and
both lint checks pass. If any palette prints an error here, run the
`git reset --hard` above and re-check — the repository copy is
known-good, so a server-side parse error always means local drift. Do
not proceed until this block is clean.

## 1 · DNS check (both names)

Any DNS lookup tool answers this step — which tool does not matter,
only the addresses do. Use `dig` when the server has it:

```bash
dig +short bramptonbulletin.com A
dig +short www.bramptonbulletin.com A
```

If the server reports `dig: command not found`, do **not** stop — use
`getent`, which is part of glibc and needs no install:

```bash
getent ahostsv4 bramptonbulletin.com     | awk '{print $1}' | sort -u
getent ahostsv4 www.bramptonbulletin.com | awk '{print $1}' | sort -u
```

One caveat with `getent`: it consults `/etc/hosts` before DNS. If its
answer differs from what the registrar shows, check `/etc/hosts` for a
stale entry for either name before trusting the result.

Optionally install `dig` for next time — `apt-get install -y dnsutils`
on Debian/Ubuntu, `dnf install -y bind-utils` on RHEL/Alma — but do not
let a package problem block the deployment; the `getent` answer is
sufficient.

**Verify:** both names resolve to the VPS address (via either tool).
If either record is missing, report back to the owner with which one
is needed before continuing — certbot in step 3 needs every name it
certifies to resolve.

## 2 · nginx server block

Copy the block from DEPLOY.md §3a **verbatim** except for two lines:

```nginx
server_name bramptonbulletin.com www.bramptonbulletin.com;
root /path/to/release;    # the SAME directory the other papers serve
```

Keep every protection rule and rewrite exactly as written. Enable the
site and reload nginx.

**Verify:** `nginx -t` passes;
`curl -H 'Host: bramptonbulletin.com' http://127.0.0.1/` returns HTML.

## 3 · TLS

```bash
certbot --nginx -d bramptonbulletin.com -d www.bramptonbulletin.com
```

**Verify:** `https://bramptonbulletin.com/` serves with a valid
certificate; plain http redirects to https.

## 4 · The host mapping in config.php

Edit the server's existing `config.php` **in a plain-text editor on the
server** — do not paste through anything that rewrites quotes. The full
seven-paper pattern, safe to install in one edit even for papers not
yet deployed:

```php
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');

// ... inside the returned array:
'site_slug' => match (true) {
    str_contains($host, 'bramptonbulletin')     => 'brampton-bulletin',
    str_contains($host, 'kelownacurrent')       => 'kelowna-current',
    str_contains($host, 'thepacificpost')       => 'pacific-post',
    str_contains($host, 'kermodechronicle')     => 'kermode-chronicle',
    str_contains($host, 'edmontonecho')         => 'edmonton-echo',
    str_contains($host, 'grandeprairiegazette') => 'grande-prairie-gazette',
    default                                     => 'prairiedispatch',
},
'site_url' => match (true) {
    str_contains($host, 'bramptonbulletin')     => 'https://bramptonbulletin.com',
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
- `https://bramptonbulletin.com/` renders **the Bulletin's chrome**:
  the newsprint page with the flower mark, the "Brampton Bulletin"
  nameplate with the yellow offset-print shadow, the thick-thin rules,
  and the `/assets/css/bulletin.css` stylesheet.
- Do **not** expect the LIVE ticker or The Brief numbered rail yet,
  and do not stop over their absence. The ticker renders only when
  the `breaking_label`/`breaking_url` settings exist, and the Brief
  rail only when there are stories — step 5's seeder writes both.
  Before seeding, the nav shows a Search link where the ticker will
  be, and the front page is otherwise nearly empty; that is correct.
  Their checks are in step 5.
- Every other live paper still renders its own chrome. If two domains
  show the same paper, the mapping isn't matching.

The first request joins the site to the network automatically; existing
network admins sign in at `/admin/` immediately — no founding-account
form.

## 5 · Launch content — one command

From the release directory:

```bash
PP_SITE=brampton-bulletin php tools/seed-launch.php
```

This fills the paper: identity (The Brampton Bulletin, "Brampton first.
The GTA in full.", The Brief newsletter), the City Hall / Peel & Courts
/ Transit / Housing / GTA desks, four verified GTA wire feeds, and
eighteen launch stories with commissioned art in the brand's newsprint
illustration style. Safe to re-run; it never overwrites a setting the
newsroom has already changed. Expect the output to end
`Done — 18 stories added.`

**Verify, all on https://bramptonbulletin.com:**
- `/` — the brick **LIVE** ticker now shows at the right of the nav
  (the seeder wrote `breaking_label` and `breaking_url`) and The Brief
  rail is numbered 01–05 — these are the checks deferred from step 4
- `/` — the "Council votes 7–4…" hero with the night-council
  illustration, The Brief rail numbered 01–05, the three-up row (410 /
  fourplexes / warehouses, all illustrated), Sports + the italic
  Opinion column, the Around the GTA band with the MISSISSAUGA /
  CALEDON / TORONTO / VAUGHAN kickers, and The Brief signup block
  above the footer
- `/story/council-votes-7-4-to-freeze-development-charges-for-two-years`
  renders with the green kicker and the drop-rule article head
- `/card/council-votes-7-4-to-freeze-development-charges-for-two-years.png`
  returns a 1200×630 PNG
- `/desk/city-hall`, `/desk/transit`, `/desk/gta`, `/feed/`,
  `/sitemap.xml` all 200
- `/app/bootstrap.php` returns **403**

## 6 · Cron

Add another job beside the existing ones, daily at **06:00**:

```
PP_SITE=brampton-bulletin php /path/to/release/cron/fetch-news.php
```

**Verify:** run it once by hand; expect `ok` lines for insauga,
Bramptonist, Global News Toronto and CBC Toronto, then check the wire
tabs in `/admin/` show Brampton and Greater Toronto tabs with items.

## 7 · Mail — before enabling The Brief

Per-domain, never inherited:

1. Create the sending mailbox, e.g. `brief@bramptonbulletin.com`, and
   make sure `tips@bramptonbulletin.com` exists too — the launch
   package prints it as the contact address.
2. DNS for bramptonbulletin.com: **SPF**, **DKIM**, then DMARC once
   both pass.
3. In the Bulletin's `/admin` → newsletter settings: SMTP details, From
   `brief@bramptonbulletin.com`, From name `The Brampton Bulletin`, and
   the paper's **postal mailing address** (CASL).
4. **Send me a test** → confirm it lands in a real inbox, not spam.
5. Only then enable daily sending.

## 8 · Same-day housekeeping

- Confirm HTTPS is forced on both hostnames.
- The eighteen launch stories are demonstration content — the newsroom
  replaces them with real reporting at its own pace. Desks, feeds and
  settings are keepers.
- The LIVE ticker in the nav is driven by Settings → breaking banner
  label + link; it currently points at the budget hero. Clear the label
  to collapse the ticker to the search link.
- Update the utility bar's weather line (Settings) with live
  conditions — the launch value carries the design's smog-advisory
  example.
- Snapshot check on the other live papers after the config edit.

## Troubleshooting

| Symptom | Cause → fix |
|---|---|
| "Unexpected character" in any JSON | Server copy drifted from the repo → step 0's `git reset --hard`, then re-run the palette validation loop |
| LIVE ticker / Brief rail absent right after step 4 | Expected, not a failure — the ticker needs the `breaking_label`/`breaking_url` settings and the rail needs stories, both written by step 5's seeder → proceed to step 5, then confirm both there |
| Front shows another paper's design | Host mapping not matching → check the `str_contains` strings in config.php against the actual Host header |
| Seeder reports `0 stories added` on first run | It already ran (matched by slug) — not an error; verify the front instead |
| Palette validates but the site 500s | `php -l config.php`; if clean, set `debug => true` temporarily and read the error |
| Art boxes empty in your own page-test screenshots | Images are lazy-loaded; scroll the page before capturing — real readers are unaffected |
| Nav shows a desk the Bulletin doesn't use | The nav is pinned in `assets/sites/brampton-bulletin/palette.json` → `chrome.nav`; desks not listed there never appear |

## What NOT to do

- Don't run `supabase/schema.sql`.
- Don't change `site_slug` mappings after first boot.
- Don't copy the release directory per domain.
- Don't re-type or transcribe repository files — pull them.
- Don't enable the newsletter before SPF/DKIM pass on
  bramptonbulletin.com specifically.
