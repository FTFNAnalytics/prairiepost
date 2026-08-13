# Taking tricitiestorch.ca live — deployment runbook

This runbook takes the **Tri Cities Torch** live on the VPS that already
serves the network (prairiedispatch.ca, westernwire.ca, and the rest).
Same codebase, same release directory, same shared Supabase database — a
ninth nginx server block and a config mapping, never a copy of the code.
Follow the steps in order; each ends with a verification to pass before
moving on.

The Torch serves **Coquitlam, Port Coquitlam and Port Moody, with Anmore
and Belcarra**. Its domain is **tricitiestorch.ca**; the bare domain is
canonical and `www` serves and redirects through the same block.

**Two things make this deployment simpler than the last one:**

- **No schema change.** The Torch is a conventional paper, not an
  aggregator. The shared database stays at schema version 7 and no
  migration runs. Nothing about the other eight papers' data changes.
- **No new routes.** The Torch uses the routes every paper already has
  (`/story/`, `/desk/`, `/search`, `/feed/`, `/sitemap.xml`,
  `/subscribe`, `/corrections`, `/card/`, `/newsletter`, `/author/`).
  There is no `/region/` rewrite — that one belongs to Western Wire
  alone. Copy the block below as it stands.

**The one rule stands:** credentials never enter the repository.
`config.php` lives only on the server.

---

## 0 · Pull and verify the release

- Branch `claude/prairie-post-news-site-hiffgl` (the network's release
  branch), at or after the Tri Cities Torch merge — **pull the latest
  HEAD first.** This release carries the torch template, the Torch's
  brand assets and launch package, the per-site desk-label override, and
  `<cite>` in the shared story-HTML allowlist (pull quotes).

```bash
cd /path/to/release && git fetch origin claude/prairie-post-news-site-hiffgl \
  && git checkout claude/prairie-post-news-site-hiffgl \
  && git pull origin claude/prairie-post-news-site-hiffgl
```

**Verify:**

```bash
# the Torch's assets are present — expect launch.php, palette.json,
# mark.svg, mark-reversed.svg, favicon.svg, og-default.png and img/
ls assets/sites/tri-cities-torch/
ls assets/sites/tri-cities-torch/img/ | wc -l      # 12 illustrations
ls assets/css/torch.css

# every brand file still parses — one "ok" line per site, nine in total:
for f in assets/sites/*/palette.json; do php -r "json_decode(file_get_contents('$f'), true, 512, JSON_THROW_ON_ERROR); echo '$f ok', PHP_EOL;"; done

# the new code parses:
php -l app/views/front-torch.php && php -l app/views/article-torch.php \
  && php -l app/views/section-torch.php && php -l assets/sites/tri-cities-torch/launch.php
```

**Schema note:** there is no migration in this release. If
`settings.schema_version` reads 7 before the pull it still reads 7
after. Do not go looking for one.

## 1 · DNS check (both names)

```bash
dig +short tricitiestorch.ca A
dig +short www.tricitiestorch.ca A
```

If `dig` is missing use `getent hosts tricitiestorch.ca` (glibc, no
install; it reads `/etc/hosts` first, so cross-check that file if the
answer looks wrong). Both names must resolve to this server before the
TLS step. If they don't, stop here and report — everything below waits
on DNS.

## 2 · nginx server block

Add a ninth block alongside the existing ones. This is the standard
paper block; it needs no route the other papers don't already have.

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name tricitiestorch.ca www.tricitiestorch.ca;

    root /path/to/release;
    index index.php;

    # locations must appear before the PHP handler.
    location ~ ^/(app|data)/            { deny all; }
    location ~ ^/uploads/.+\.(php|phtml|phar)$ { deny all; }
    location ~ ^/(config\.php|config\.example\.php|router\.php)$ { deny all; }

    # Pretty URLs — the .htaccess rewrites.
    rewrite ^/story/([a-z0-9-]+)/?$   /article.php?slug=$1  last;
    rewrite ^/desk/([a-z0-9-]+)/?$    /section.php?slug=$1  last;
    rewrite ^/author/([a-z0-9-]+)/?$  /author.php?slug=$1   last;
    rewrite ^/card/([a-z0-9-]+)\.png$ /card.php?slug=$1     last;
    rewrite ^/newsletter(/.*)?$       /newsletter.php?path=$1 last;
    rewrite ^/search/?$               /search.php            last;
    rewrite ^/feed/?$                 /feed.php              last;
    rewrite ^/sitemap\.xml$           /sitemap.php           last;
    rewrite ^/subscribe/?$            /subscribe.php         last;
    rewrite ^/ad/?$                   /ad.php                last;
    rewrite ^/corrections/?$          /corrections.php       last;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;   # match the existing blocks
    }
    location / { try_files $uri $uri/ =404; }
}
```

```bash
nginx -t && systemctl reload nginx
```

**Verify:** `curl -H "Host: tricitiestorch.ca" http://127.0.0.1/` returns
HTML. Any paper's chrome is acceptable at this point — the mapping comes
in step 4.

## 3 · TLS

Issue the certificate the same way as the other papers (certbot shown;
match whatever the server actually uses):

```bash
certbot --nginx -d tricitiestorch.ca -d www.tricitiestorch.ca
```

**Verify:** `https://tricitiestorch.ca/` serves with a valid certificate,
and plain http redirects to https on both names.

## 4 · The host mapping in config.php

Edit the server's existing `config.php` **in a plain-text editor on the
server**. The live file uses a **three-stage exact-match tenant
selector** — hostname → tenant, tenant → site slug, slug → canonical URL
— not the two `str_contains` matches in `config.example.php`. Mirror the
architecture you find in the file and add exactly three entries, one per
stage, with these semantics:

- `tricitiestorch.ca` and `www.tricitiestorch.ca` resolve to the tenant
  **`tri-cities-torch`**
- the tenant `tri-cities-torch` maps to the site slug
  **`tri-cities-torch`**
- the slug `tri-cities-torch` maps to the canonical URL
  **`https://tricitiestorch.ca`**

Take a root-only backup before the edit. Change nothing else in the
file; never print its credential values.

**Verify:** `php -l config.php` passes, then:

- `https://tricitiestorch.ca/` renders **the Torch's chrome**: the coast
  gradient nav bar at the top, the full-bleed photographic masthead with
  the torch mark, the banner card overlapping the hero's bottom edge, and
  the `/assets/css/torch.css` stylesheet.
- The page is otherwise nearly empty ("No stories published yet") —
  correct until step 5.
- Do **not** expect the "Tri Cities / TORCH" wordmark, the five section
  links, the Torch's tagline, or any story yet, and **do not stop over
  their absence.** The masthead reads the auto-provisioned site name, the
  nav shows the network's default desks, and there is no content:
  `site_title`, `tagline`, the nav's section list and every story are
  written by step 5's seeder. Their checks live in step 5.
- Every other paper still renders its own chrome and its stories still
  open. Spot-check all eight now — this is the only place this release
  touches shared code (`ui.php`, `helpers.php`, `article.php`,
  `section.php`, `index.php`).

The first mapped request self-provisions the site row. Existing network
admins sign in at `/admin/` immediately — no founding-account form.

## 5 · Launch content — one command

From the release directory:

```bash
PP_SITE=tri-cities-torch php tools/seed-launch.php
```

This fills the paper: identity (Tri Cities Torch, "Coquitlam · Port
Coquitlam · Port Moody", The Torch newsletter), the five sections, six
verified Metro Vancouver and B.C. wire feeds, and **eighteen launch
stories** with commissioned art in the brand's illustration style. Safe
to re-run; it never overwrites a setting the newsroom has already
changed. Expect the output to end `Done — 18 stories added.`

Expected output notes, so nothing reads as an error:

- **No `desk added:` lines.** Every desk the Torch uses — News,
  Community, Politics, Business, Sports, Opinion — already exists on the
  network. The pack lists them all so it stands alone; the seeder
  creates only what is missing.
- **Some `source added:` lines may be absent.** Feeds already registered
  by a sister paper are matched by URL and skipped.
- **No `story exists, skipped:` lines on a first run.** The Torch's
  stories are original to it. Unexplained skips on a first run are a
  failure, not a normal outcome — report them.

**Verify, all on https://tricitiestorch.ca:**

- `/` — the masthead now reads **Tri Cities / TORCH** over the coastal
  photograph, and the nav carries **Local News · Community · Politics ·
  Business · Sports** — the identity checks deferred from step 4.
- `/` — the rows run in the fixed order: the indigo Riverview feature
  beside the Rocky Point photo card; a three-across photo strip; the
  two-up row (indigo card, a photograph, the green Community card); four
  briefs under "Around the Tri-Cities"; the newsletter card.
- `/story/riverview-lands-rezoning-heads-to-public-hearing` — the
  article page: 44px headline, italic standfirst, the hero figure wider
  than the text, the gold-ruled pull quote with its attribution on its
  own line, and the sticky "More in Local News" rail at the right.
- `/desk/community` — a **green** section band; `/desk/news` and
  `/desk/politics` band on the coast gradient; `/desk/business` and
  `/desk/sports` on Inlet Blue.
- `/feed/`, `/sitemap.xml`, `/search?q=council` — all 200.
- Scroll the front page past 220px: the header lockup fades in at the
  left of the nav bar.
- At 390px wide: one column throughout, the nav is a horizontally
  scrolling strip with the gradient intact (no hamburger), and the
  banner card is omitted.

## 6 · Cron

Nothing new to install. The existing network-wide cron already calls
`cron/fetch-news.php`, which fetches every enabled source — including the
Torch's new Metro Vancouver feeds — into the shared pool. Confirm after
the next run:

- Newsroom → Dashboard on the Torch shows the Tri-Cities / Metro
  Vancouver / British Columbia region tabs with fresh headlines.

If the server runs a per-site cron with `PP_SITE`, add
`PP_SITE=tri-cities-torch` for the newsletter send; the fetch is shared
either way.

## 7 · Mail — before enabling The Torch newsletter

Same procedure as the other papers: create `sixam@tricitiestorch.ca` (or
the address the owner prefers) and `tips@tricitiestorch.ca` — the launch
settings print the tips address — set the SMTP settings and the paper's
mailing address in Newsroom → Settings, send yourself a test from
Newsroom → The 6 a.m., and publish SPF and DKIM for tricitiestorch.ca in
DNS before flipping `newsletter_enabled`.

## 8 · Same-day housekeeping

- The eighteen launch stories are demonstration content in the paper's
  voice — replace them as real reporting lands. The illustrations in
  `assets/sites/tri-cities-torch/img/` are drawn stand-ins at the design
  package's delivery ratios (21:9 hero, 1600×460 banner, 3:2 cards);
  swap in photographs as the picture desk sources them.
- `breaking_label` / `breaking_url` are seeded to the Riverview story,
  which puts the red **Breaking** flag on that article. Clear both in
  Settings when it is no longer the top story.
- The Tri-City News has no discoverable RSS feed; if the newsroom
  obtains a working URL, add it under Newsroom → Sources with the
  `tri-cities` region.

## Troubleshooting

| Symptom | Likely cause |
| --- | --- |
| The nav shows other papers' desks | the seeder hasn't run (step 5), or `chrome.nav` in `palette.json` isn't being read — check the site slug mapping |
| Section band is the wrong colour | `chrome.band_tone` maps desk slug → tone; a desk not listed there falls back to the indigo feature gradient |
| Nav reads "Business & Markets" | `desk_labels` in `palette.json` renames shared desks per site — confirm the file is deployed |
| Pull-quote attribution runs into the quote | the release predates `<cite>` in `sanitize_html()` — pull again |
| Banner card sits below the hero instead of overlapping | the `.tt-banner` negative margin is being overridden — check that `torch.css` loaded |
| Front page renders another paper's template | the config mapping isn't matching the host (step 4) |

## What NOT to do

- Don't change `site_slug` mappings after first boot.
- Don't copy the release directory — the Torch is a server block plus a
  mapping, on the same checkout as every other paper.
- Don't rename the shared desks to suit the Torch. "News" and "Business
  & Markets" are network-wide names; `desk_labels` renames them for this
  paper only, which is why it exists.
- Don't add the `/region/` rewrite to this block. It belongs to Western
  Wire, and an unused rewrite here only invites confusion later.
