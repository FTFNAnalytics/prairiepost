# Taking tricitiestorch.ca live — deployment runbook

This runbook adds the **Tri Cities Torch** to the network box that already
serves the eight papers and the CivisMedia hub. It serves Coquitlam, Port
Coquitlam and Port Moody, with Anmore and Belcarra, at
**tricitiestorch.ca** (bare domain canonical, `www` redirects).

**Read this section before anything else — the deployment model is not
what the older paper runbooks describe.**

- Releases are **immutable directories**, `/var/www/prairiepost-<sha>-<label>`,
  built from a branch tarball. You do **not** `git pull` in the live root;
  the live root is not a checkout.
- `tools/vps/upgrade-papers.sh` builds the new release, carries the config
  forward, repoints every vhost and cron file, verifies each domain still
  serves **its own masthead**, and rolls a release group back if any
  domain fails. Use it. Do not hand-assemble a release.
- The live tenant mapping lives in **`app/config.site.php`**, not
  `config.php`. `config.php` is a small generated wrapper that requires
  the site config and adds `hub_slug`. An edit to `config.php` would be
  silently discarded by the next upgrade.
- `uploads/` is a symlink to the shared `/var/www/prairiepost-shared-uploads`.
  Never replace it with a directory.

**This deployment ships the Torch only.** The control-room branch head is
ahead of production with phases the owner has not released; `PP_BRANCH`
pins this deploy to production's current commit plus the Torch, so
nothing else changes.

**The one rule stands:** credentials never enter the repository, and no
config value is ever printed.

---

## 0 · Establish the baseline (read-only)

```bash
bash tools/vps/discover.sh    # or read the enabled vhosts directly
```

Record, and report before changing anything:

- the release directory each paper vhost serves from (they share one),
- the hub's release directory (`civismedia`, upgraded separately — this
  runbook does not touch it),
- every live domain's front-page `<title>`,
- `PP_SCHEMA_VERSION` in the live release's `app/bootstrap.php`.

**Verify:** all eight papers answer 200 with their own masthead. This is
the baseline the upgrade tool holds the deployment to.

## 1 · DNS check (both names)

```bash
dig +short tricitiestorch.ca A
dig +short www.tricitiestorch.ca A
```

Both must resolve to this server; `getent hosts` is the fallback if `dig`
is absent. If DNS is not ready, stop here — everything below depends on it.

## 2 · Upgrade the papers' release to include the Torch code

This is the only step that touches existing papers. It moves all eight to
a new release whose contents are identical to today's, plus the Torch's
template and assets.

```bash
PP_BRANCH=deploy/torch-on-3fd4f13 \
  bash <(curl -fsSL "https://raw.githubusercontent.com/FTFNAnalytics/prairiepost/deploy/torch-on-3fd4f13/tools/vps/upgrade-papers.sh")
```

The script resolves that branch's head, captures every domain's title,
builds `/var/www/prairiepost-<sha>-shared`, copies the live
`app/config.site.php` forward **verbatim**, symlinks the shared uploads,
repoints the vhosts and cron files, reloads nginx, and verifies every
domain still serves its own masthead — rolling the whole group back if
any does not.

**Verify:**
- The script reports success and does not roll back.
- All eight papers answer 200 with the **same titles** recorded in step 0.
- The new release contains `assets/css/torch.css`,
  `app/views/front-torch.php` and `assets/sites/tri-cities-torch/`.
- `app/bootstrap.php` in the new release still declares
  **`PP_SCHEMA_VERSION` 9** — unchanged. The Torch adds no migration.
- `ls -l <new release>/uploads` is still a symlink to the shared uploads.

If the script rolls back, stop and report its output verbatim. Do not
retry by hand.

## 3 · Add the Torch's tenant mapping

Edit **`<new release>/app/config.site.php`** on the server, in a
plain-text editor. It uses a three-stage exact-match tenant selector —
hostname → tenant, tenant → site slug, slug → canonical URL. Mirror the
structure already in the file and add exactly three entries:

- `tricitiestorch.ca` and `www.tricitiestorch.ca` → tenant
  **`tri-cities-torch`**
- tenant `tri-cities-torch` → site slug **`tri-cities-torch`**
- slug `tri-cities-torch` → canonical URL **`https://tricitiestorch.ca`**

Take a root-only backup first, outside the repository and outside the
release. Change nothing else. Never print the file's contents.

**Verify:** `php -l <new release>/app/config.site.php` passes, and all
eight existing papers still serve their own mastheads — the shared config
is live for every one of them the moment it is saved, which makes this
the step most likely to affect them.

## 4 · The Torch's nginx block

Add a new vhost rooted at **the same new release directory** the papers
now use. It is the standard paper block — no `/region/` rewrite, that
belongs to Western Wire.

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name tricitiestorch.ca www.tricitiestorch.ca;

    root /var/www/prairiepost-<sha>-shared;    # the release from step 2
    index index.php;

    location ~ ^/(app|data)/            { deny all; }
    location ~ ^/uploads/.+\.(php|phtml|phar)$ { deny all; }
    location ~ ^/(config\.php|config\.example\.php|router\.php)$ { deny all; }

    rewrite ^/story/([a-z0-9-]+)/?$   /article.php?slug=$1  last;
    rewrite ^/desk/([a-z0-9-]+)/?$    /section.php?slug=$1  last;
    rewrite ^/author/([a-z0-9-]+)/?$  /author.php?slug=$1   last;
    rewrite ^/card/([a-z0-9-]+)\.png$ /card.php?slug=$1     last;
    rewrite ^/newsletter(/.*)?$       /newsletter.php?path=$1 last;
    rewrite ^/search/?$               /search.php            last;
    rewrite ^/feed/?$                 /feed.php              last;
    rewrite ^/sitemap\.xml$           /sitemap.php           last;
    rewrite ^/subscribe/?$            /subscribe.php         last;
    rewrite ^/contact/?$              /contact.php           last;
    rewrite ^/ad/?$                   /ad.php                last;
    rewrite ^/corrections/?$          /corrections.php       last;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;   # match the other blocks
    }
    location / { try_files $uri $uri/ =404; }
}
```

```bash
nginx -t && systemctl reload nginx
```

**Verify:** the domain returns 200 (a `--resolve` curl is fine before
TLS), and the eight existing papers are untouched.

## 5 · TLS

```bash
certbot --nginx -d tricitiestorch.ca -d www.tricitiestorch.ca
```

**Verify:** both names serve a valid certificate; http redirects to https.

## 6 · What the site looks like BEFORE seeding

The first mapped request self-provisions the site row. At this point the
page renders **the Torch's chrome**: the coast gradient nav, the
full-bleed photographic masthead, the banner card overlapping the hero,
and `/assets/css/torch.css`.

Do **not** expect, and do **not** stop over the absence of: the
"Tri Cities / TORCH" wordmark, the five section links, the tagline, or
any story. Those are all written by step 7's seeder. Pre-seed, the
masthead shows the auto-provisioned name and the nav shows the network's
default desks. That is the correct pre-seed state.

## 7 · Launch content — one command

From the new release directory:

```bash
PP_SITE=tri-cities-torch php tools/seed-launch.php
```

Expected output:

- **No `desk added:` lines** — News, Community, Politics, Business,
  Sports and Opinion all already exist network-wide.
- **Some `source added:` lines absent** — feeds a sister paper already
  registered are matched by URL and skipped.
- **No `story exists, skipped:` lines** on a first run. Unexplained skips
  on a first run are a failure; report them.
- Ends with `Done — 18 stories added.`

**Verify, all on https://tricitiestorch.ca:**

- `/` — the masthead now reads **Tri Cities / TORCH** with the tagline,
  and the nav carries **Local News · Community · Politics · Business ·
  Sports** (the identity checks deferred from step 6).
- `/` — rows in the fixed order: the indigo Riverview feature beside the
  Rocky Point photo card; the three-across photo strip; the two-up row
  (indigo card, a photograph, the green Community card); four briefs
  under "Around the Tri-Cities"; the newsletter card.
- `/story/riverview-lands-rezoning-heads-to-public-hearing` — 44px
  headline, italic standfirst, the hero figure wider than the text, the
  gold-ruled pull quote with its attribution on its own line, and the
  sticky "More in Local News" rail.
- `/desk/community` renders a **green** band; `/desk/news` and
  `/desk/politics` the coast gradient; `/desk/business` and
  `/desk/sports` Inlet Blue.
- `/feed/`, `/sitemap.xml`, `/search?q=council` all 200.
- Past 220px of scroll the header lockup fades in at the left of the nav.
- At 390px: one column, the nav a horizontally scrolling strip with the
  gradient intact, the banner card omitted.
- **All eight existing papers still serve their own mastheads.**

## 8 · Hub, cron and mail

- **Hub:** the Torch appears in the CivisMedia control room's network
  views automatically, because they read the shared database. The hub's
  own release is not touched by this deployment.
- **Cron:** nothing to install. The shared fetch already runs, and the
  upgrade tool rewrote the cron paths to the new release. Confirm after
  the next run that the Torch's dashboard shows its region tabs.
- **Mail:** the newsletter stays **off**. Before enabling it: create
  `sixam@` and `tips@tricitiestorch.ca`, set SMTP and the mailing address
  in Settings, send a test, and publish SPF and DKIM for the domain.

## 9 · Same-day housekeeping

- The eighteen launch stories are demonstration content in the paper's
  voice — replace them as real reporting lands.
- The illustrations are drawn stand-ins at the design package's delivery
  ratios; swap in photographs when the picture desk sources them.
- `breaking_label` / `breaking_url` are seeded to the Riverview story,
  which puts the red **Breaking** flag on that article. Clear both in
  Settings when it is no longer the top story.
- The Tri-City News publishes no discoverable RSS feed. If the newsroom
  obtains a working URL, add it under Sources with the `tri-cities`
  region.

## Rollback

Step 2 rolls itself back on failure. After it has succeeded, rollback is
to point the vhosts and cron files back at the previous release directory
— the script leaves a `.bak.<stamp>` beside each file it edits — and
reload nginx. The previous release directory is left intact; do not
delete it until the Torch has been live for a few days.

## Troubleshooting

| Symptom | Likely cause |
| --- | --- |
| A paper serves another paper's masthead | the tenant mapping edit in step 3 — restore the backup of `app/config.site.php` |
| The Torch's config change vanished after an upgrade | it was made in `config.php` (a generated wrapper) instead of `app/config.site.php` |
| Uploaded images 404 on the Torch only | `uploads` in the new release is not the shared symlink |
| Nav shows other papers' desks | the seeder has not run (step 7) |
| Section band is the wrong colour | `chrome.band_tone` maps desk slug → tone in `palette.json` |
| Nav reads "Business & Markets" | `desk_labels` renames shared desks per site — confirm `palette.json` deployed |

## What NOT to do

- Don't `git pull` in a release directory. Releases are immutable; the
  upgrade script builds new ones.
- Don't edit `config.php` in a release. Edit `app/config.site.php`.
- Don't run `upgrade-papers.sh` without `PP_BRANCH` for this deployment —
  the default is the control-room branch head, which carries unreleased
  phases.
- Don't rename shared desks to suit the Torch; `desk_labels` exists for
  exactly that.
- Don't delete the previous release directory on the day of the switch.
