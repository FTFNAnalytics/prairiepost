# Taking sudburystandard.ca live — deployment runbook

This runbook takes **The Sudbury Standard** live on the VPS that already
serves the network. Same codebase, same shared database — a new nginx
server block and a tenant mapping, never a copy of the code.

The Standard is an **opinion desk**. Its front page leads on one
argument rather than a news mix, its article page carries a full-bleed
pull quote, and it has a public `/about` page that the other papers do
not. Everything else — desks, tags, newsletter, admin — is the network's
standard machinery.

Its domain is **sudburystandard.ca**. The bare domain is canonical;
`www` serves and redirects through the same block.

**The rules that hold throughout:**

- Credentials never enter the repository. The tenant configuration lives
  only on the server.
- No manual database edits, ever. Migrations and the seeder do all
  writes.
- Don't touch another paper's server block, cron file, or config arm.
- Service actions are limited to `nginx -t && systemctl reload nginx`
  and certbot.
- **Stop after two failed verifications of the same step** and report
  exactly what you ran, what you expected, and what you got. Do not
  improvise a fix around a failing gate.

---

## How this network is actually deployed (read before step 0)

Production does **not** run a `git pull` in a working directory.

- The release branch is **`claude/master-dashboard-control-room-nr3mp4`**.
  It is not the repository's default branch. Anything merged elsewhere
  is not in production.
- `tools/vps/upgrade-papers.sh` resolves that branch's head SHA, extracts
  a fresh tree to an **immutable release directory**
  `/var/www/prairiepost-<sha12>-<label>`, copies each old release's
  configuration forward verbatim as `app/config.site.php`, writes a
  generated `config.php` wrapper over it, repoints the nginx blocks and
  cron files, and rolls the whole group back if any domain stops serving
  its own masthead.
- So `config.php` in a live release is a **generated wrapper**. The file
  you edit for tenant mapping is **`app/config.site.php`**.
- Release roots are discovered from the enabled nginx blocks, not
  assumed. Some blocks point at a symlink, some at the physical
  directory; `readlink -f` resolves both to the same checkout.

Discover the current state rather than trusting anything written here:

```bash
for link in /etc/nginx/sites-enabled/*; do
  vh=$(readlink -f "$link")
  root=$(grep -Eo '^\s*root\s+[^;#]+' "$vh" | head -1 | awk '{print $2}')
  name=$(grep -Eo 'server_name[[:space:]]+[^;]+' "$vh" | head -1 | cut -d' ' -f2-)
  printf '%-28s %-38s %s\n' "$(basename "$link")" "$name" "$(readlink -f "$root" 2>/dev/null)"
done
```

Note the release root that the papers currently serve from. Every path
below written as `$REL` means that directory.

## 0 · Get the Standard into the release

The Standard's code must be an ancestor of the release branch's head
before anything else happens. Pin by commit, not by branch name — merged
feature branches go stale.

```bash
cd "$REL"
git --version >/dev/null 2>&1   # release dirs are tarball extracts; git may not apply
```

The release directory is an extract, not a clone, so verify by content:

```bash
ls "$REL/assets/sites/sudbury-standard/"     # launch.php palette.json mark.svg og-default.png img/
ls "$REL/assets/css/standard.css"
ls "$REL/app/views/front-standard.php" "$REL/app/views/article-standard.php" \
   "$REL/app/views/section-standard.php" "$REL/about.php"
```

If those are missing, the release predates the Standard merge. Run the
upgrade — as root, from anywhere:

```bash
bash "$REL/tools/vps/upgrade-papers.sh"
```

It builds a new release at the branch's current head, repoints every
paper, verifies each domain still serves **its own** masthead title, and
rolls back the whole group if one does not.

**Verify:**

- The script ends without `FATAL` and prints the new release path.
- Re-run the discovery loop above. Every existing paper now serves from
  the new `/var/www/prairiepost-<sha12>-…` directory. **Update `$REL` to
  it.**
- Every existing domain still answers 200 with its own masthead — the
  script checks this itself, but confirm two by hand.
- The Standard's files are now present under `$REL` (the `ls` checks
  above).
- `php -l "$REL/about.php" && php -l "$REL/app/views/front-standard.php"`.
- Every brand file still parses:
  `for f in "$REL"/assets/sites/*/palette.json; do php -r "json_decode(file_get_contents('$f'), true, 512, JSON_THROW_ON_ERROR); echo '$f ok', PHP_EOL;"; done`

**Schema note:** this release is additive against the live schema. The
**first request** after the upgrade runs any pending migration, which
means a `curl` during step 2 or 3 can trigger it. That is why the
all-papers regression check is step 4, immediately after the first
request against the new code — not at the end of this runbook.

## 1 · DNS check (both names)

```bash
dig +short sudburystandard.ca A
dig +short www.sudburystandard.ca A
```

If `dig` is missing use `getent hosts sudburystandard.ca` (it consults
`/etc/hosts` first, so cross-check that file if the answer looks odd).
Both names must resolve to this server before the TLS step. Nothing
below works without it.

## 2 · nginx server block

Add a new block alongside the existing ones, rooted at `$REL`. It is the
standard paper block **plus one rewrite the older blocks don't have** —
the Standard's `/about` page.

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name sudburystandard.ca www.sudburystandard.ca;

    root /var/www/prairiepost-<sha12>-<label>;   # $REL, as discovered
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
    rewrite ^/about/?$                /about.php             last;
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

Take the `fastcgi_pass` socket and the deny/location conventions from an
existing block rather than from this page — the server is the authority.

The `/about` rewrite is harmless on the other papers' blocks but only
the Standard needs it. Leave theirs alone.

```bash
nginx -t && systemctl reload nginx
```

**Verify:** `curl -sI -H "Host: sudburystandard.ca" http://127.0.0.1/`
returns 200 and HTML. **Any paper's chrome is correct at this point** —
the tenant mapping is step 4. Do not stop because it looks like the
Prairie Dispatch.

## 3 · TLS

Issue the certificate the way the other papers were issued (certbot
shown; match what the server actually uses):

```bash
certbot --nginx -d sudburystandard.ca -d www.sudburystandard.ca
```

**Verify:** `https://sudburystandard.ca/` serves with a valid
certificate and plain http redirects to https.

## 4 · Network regression check — do this now

The curls above were the first requests against the new release, so any
pending migration has run. Confirm the rest of the network before going
further.

**Verify — for each existing domain** (prairiedispatch.ca,
edmontonecho.com, thepacificpost.ca, kelownacurrent.ca,
kermodechronicle.ca, gpgazette.ca, bramptonbulletin.ca, westernwire.ca,
and civismedia if enabled):

```bash
for d in prairiedispatch.ca edmontonecho.com thepacificpost.ca kelownacurrent.ca \
         kermodechronicle.ca gpgazette.ca bramptonbulletin.ca westernwire.ca; do
  printf '%-24s %s  %s\n' "$d" \
    "$(curl -sk -o /dev/null -w '%{http_code}' --resolve "$d:443:127.0.0.1" "https://$d/")" \
    "$(curl -sk --resolve "$d:443:127.0.0.1" "https://$d/" | grep -o '<title>[^<]*' | head -1)"
done
```

Every domain must answer **200 with its own masthead in the title**. Open
one story on two of them. If any paper is serving another paper's
chrome, **stop** — that is the tenant-mapping failure mode this network
is most exposed to, and it rolls back by restoring the `.bak.*` vhost
files the upgrade script wrote.

## 5 · The tenant mapping (pre-authorized, tightly scoped)

The live configuration is `$REL/app/config.site.php`. Expect the server
environment to require **fresh human authorization** before this file is
touched, and expect the prompt to time out if nobody is there. Treat
this step as pre-authorized and self-contained: back up, make exactly
the entries below, lint, done. No other edit, no printing of file
contents, no credential output.

```bash
cp -a "$REL/app/config.site.php" "/root/prairiepost-config-pre-sudbury-$(date -u +%Y%m%dT%H%M%SZ).php"
chmod 600 /root/prairiepost-config-pre-sudbury-*.php
```

The live file uses a **three-stage exact-match tenant selector**:
hostname → tenant key, tenant key → site slug, site slug → canonical
URL. It is not the two `str_contains` matches in
`config.example.php`. **Mirror the architecture you find in the file** —
do not paste literal lines from any runbook.

Add exactly these three semantic entries, nothing else:

| Stage | Add |
| --- | --- |
| hostname → tenant | `sudburystandard.ca` **and** `www.sudburystandard.ca` resolve to the Standard's tenant key |
| tenant → site slug | that tenant key maps to site slug **`sudbury-standard`** |
| slug → canonical URL | `sudbury-standard` maps to **`https://sudburystandard.ca`** (bare domain, no trailing slash) |

Every other arm stays byte-for-byte as it was.

```bash
php -l "$REL/app/config.site.php"
php -l "$REL/config.php"          # the generated wrapper must still lint
```

**Verify:**

Everything checked here comes from the **release tree** — the stylesheet
and templates, and `palette.json`'s `chrome` keys. Nothing here comes
from the database, because the database holds only what
auto-provisioning wrote.

- `https://sudburystandard.ca/` renders **the Standard's chrome**: a
  navy utility bar whose left side reads "Sudbury, Ontario · <today>"
  (`chrome.place`, from the palette), a white masthead with the
  blackletter **S** monogram, a navy section nav, a paper-grey page
  body, and `/assets/css/standard.css` and the `t-standard` body class
  in the source.
- `https://www.sudburystandard.ca/` reaches the same paper.
- The hero band renders — "From the desk" over "Latest from Sudbury",
  both `chrome` keys — above an empty front page.
- The first request self-provisioned the site row. Existing network
  admins sign in at `/admin/` immediately — there is no founding-account
  form.
- Re-run step 4's loop once. Every other paper is still itself.

**The pre-seed state below is CORRECT. Do not stop over any of it.**

| You will see | Because |
| --- | --- |
| Title "Sudbury Standard — News to the horizon" | auto-provisioned name; "News to the horizon" is the network default tagline in `pp_site_default_settings()` |
| Utility bar shows **Newsletter and Sign in only — no Tips** | the Tips link is guarded on `contact_email`, which defaults to empty and is written by step 5's seeder |
| Nav shows Home, **whichever of Council / Mining / Housing / Letters already exist network-wide**, then About | desks are shared across the whole network. `pp_nav_categories()` filters the global category list by the palette's `nav` array, so a desk a sister paper already seeded appears now and the rest appear after the seeder. At the time of writing that means **Housing only** — but verify against the live category list rather than against this sentence |
| `/about` renders headings with no body copy | `about_body` is a setting, written by the seeder |
| No stories | correct |

If you want the desk list rather than a guess:
`php -r 'require "app/bootstrap.php"; foreach (categories_all() as $c) echo $c["slug"], "\n";'`
run from `$REL`. Any of the Standard's four desks in that output will
appear in the nav before the seeder runs.

## 6 · Launch content — one command

From the release directory:

```bash
cd "$REL" && PP_SITE=sudbury-standard php tools/seed-launch.php
```

This writes the Standard's identity, its four desks (Council, Mining,
Housing, Letters — the pack lists every desk it uses, so it does not
depend on another paper having seeded one first), four Ontario wire
sources, and **13 launch pieces**: one lead editorial, nine desk
editorials and three letters. Expect the output to end
`Done — 13 stories added.`

The seeder prints `desk added:` only for desks the shared database was
**missing**. A desk a sister paper already seeded is matched by slug and
reused, so it will not appear in that output — its absence is the pack
working as designed, not a failure. Check the nav after the run, not the
`desk added:` lines.

The Tips link in the utility bar appears **at this step**, not before:
`contact_email` is one of the settings this command writes.

Safe to re-run. It never overwrites a setting the newsroom has already
changed, matches desks by slug and sources by URL, and **skips a story
whose slug already exists anywhere in the shared database**. A line
reading `story exists, skipped` is not an error — but if you see one at
launch, that title collides with an existing story on another paper and
the piece did **not** publish. Report it rather than working around it.

**Verify, all on https://sudburystandard.ca:**

- `/` — the masthead reads **The Sudbury / STANDARD** with the
  blackletter S, and the nav carries Home · Council · Mining · Housing ·
  Letters · About. These are the identity checks deferred from step 5.
- `/` — the hero band shows the basin illustration under a navy wash,
  eyebrow "From the desk", headline "Latest from Sudbury".
- `/` — the lead card is "Council spent four years arguing about a
  parking lot" with the kicker **THE ARGUMENT** in Press Red. Press Red
  appears **only** there and on the newsletter's Join button — if it
  appears on the other cards' kickers, the wrong template is rendering.
- `/` — the rail shows the Latest panel and the slate **Weekly Standard**
  box; the river below shows six more pieces, photo-less ones rendering
  as a navy block carrying kicker and headline.
- Read times vary across cards (1–4 min). Every card reading "1 min"
  means the seeder ran against an older launch pack.
- `/story/council-spent-four-years-arguing-about-a-parking-lot` — 660px
  measure, and the pull quote breaks out as a **full-bleed slate band**.
- `/desk/mining` — navy band with the desk description, wide lead card,
  river beneath.
- `/about` — renders through the nginx rewrite. If it 404s, the rewrite
  is missing from step 2's block.
- `/feed/`, `/sitemap.xml`, `/corrections` — all 200.
- Nothing links off the network: every outbound link in the launch
  content points at a page we control.

## 7 · Cron

Nothing new to install. The network-wide fetch already pulls every
enabled source, including the Standard's four, into the shared pool.
Confirm after the next run that Newsroom → Dashboard on the Standard
shows fresh Ontario headlines.

If the server runs a per-site cron for newsletter sends, add
`PP_SITE=sudbury-standard` when the newsletter is enabled — not before.

## 8 · Mail — before enabling The Weekly Standard

`newsletter_enabled` stays **off** until the owner's mail is set up, the
same sequence as Western Wire: create `tips@sudburystandard.ca` (the
launch settings and the About page print it) and the sending mailbox,
set the SMTP identity and the paper's mailing address in Newsroom →
Settings, publish SPF and DKIM for sudburystandard.ca, then send
yourself a test from Newsroom → The Weekly Standard. Flip the setting
only after a test send arrives.

## 9 · Same-day housekeeping

- The 13 launch pieces are **illustrative editorial written for the
  launch**, not reported fact. They name a real city and real
  institutions and contain invented specifics — dates, dollar figures,
  vote counts. Replace them with real editorial before the paper is
  promoted anywhere, and take down any piece the newsroom is not
  prepared to stand behind.
- `breaking_label` and `breaking_url` are deliberately empty; the
  Standard has no ticker.
- The About page is settings-driven (`about_heading`,
  `about_standfirst`, `about_body`) — edit it in Newsroom → Settings,
  not in the template.
- `funding_note` prints at the foot of every piece. Keep it accurate;
  one of the launch letters holds the paper to it.

## Troubleshooting

| Symptom | Likely cause |
| --- | --- |
| `/about` 404s but `/about.php` works | the `/about` rewrite is missing from the Standard's server block (step 2) |
| The Standard serves another paper's chrome | the hostname arm didn't match — check all three stages of the selector, and that you edited `app/config.site.php`, not the generated `config.php` |
| Masthead reads "Sudbury-standard" | the seeder hasn't run (step 6), or it ran against a different `PP_SITE` |
| Headlines render in a plain serif | `assets/fonts/*.woff2` didn't come across in the release copy — the paper self-hosts its five faces and loads nothing from a font CDN |
| A launch piece is missing and the seeder said "story exists, skipped" | its slug is taken by a story on another paper — report it, don't rename around it |
| Cards all read "1 min read" | the release predates the launch-pack expansion; re-run the upgrade |
| Another paper broke after the upgrade | restore that vhost's `.bak.*` file written by `upgrade-papers.sh`, `nginx -t`, reload, and report |

## What NOT to do

- Don't `git pull` inside a release directory. Releases are immutable
  extracts; new code arrives only through `upgrade-papers.sh`.
- Don't edit the generated `config.php`. The tenant configuration is
  `app/config.site.php`.
- Don't copy the release directory for the Standard — it is a server
  block plus a mapping, on the same checkout as every other paper.
- Don't change `site_slug` mappings after first boot.
- Don't hand-edit the shared database. The seeder and the migrations do
  every write.
- Don't force a verification green. Two failures on the same step is a
  full stop and a report.
