# Taking pickeringpost.ca live — deployment runbook

This runbook takes **The Pickering Post** live on the VPS that already
serves the network. Same codebase, same shared database — a new nginx
server block and a tenant mapping, never a copy of the code.

**This paper launches with no stories.** Its launch pack seeds identity,
desks and wire sources only; editorial comes from the newsroom. The front
page reads "the newsroom hasn't filed yet" until the first real story is
published. **That is the expected end state of this runbook, not a
failure.** It also means this deployment cannot hit the network-wide
`posts.slug` uniqueness constraint at all.

Its domain is **pickeringpost.ca**. The bare domain is canonical; `www`
serves and redirects through the same block.

**The rules that hold throughout:**

- Credentials never leave the server and are never printed. Never print
  `app/config.site.php` or `config.php`. (This does **not** cover nginx
  server blocks — those hold no secrets and you will need to read one.)
- No manual database edits. Migrations and the seeder do all writes.
- Don't touch another paper's server block, cron file, or config arm.
- Service actions are limited to `nginx -t && systemctl reload nginx`
  and certbot.
- **Stop after two failed attempts at the same verification** and report
  the exact command, what you expected, and what you got.
- Discover server facts. Don't trust paths in any document, including
  this one. `$REL` below means the release root you discover in step 0.

---

## How this network deploys (read before step 0)

Production does **not** run `git pull`.

- The release branch is **`claude/master-dashboard-control-room-nr3mp4`**.
  It is not the repository's default branch.
- `tools/vps/upgrade-papers.sh` resolves that branch's head, extracts it
  to an immutable release directory `/var/www/prairiepost-<sha12>-<label>`,
  carries each old release's configuration forward verbatim as
  `app/config.site.php`, writes a generated `config.php` wrapper over it,
  repoints the nginx blocks and cron files, and rolls the group back if a
  domain stops serving its own masthead.
- So `config.php` in a live release is **generated**. The file you edit
  for tenant mapping is **`app/config.site.php`**.

Discover the current state rather than trusting anything written here:

```bash
for link in /etc/nginx/sites-enabled/*; do
  vh=$(readlink -f "$link")
  root=$(grep -Eo '^\s*root\s+[^;#]+' "$vh" | head -1 | awk '{print $2}')
  name=$(grep -Eo 'server_name[[:space:]]+[^;]+' "$vh" | head -1 | cut -d' ' -f2-)
  printf '%-28s %-40s %s\n' "$(basename "$link")" "$name" "$(readlink -f "$root" 2>/dev/null)"
done
```

## 0 · Map what is running, and get the paper into the release

Run the loop above. Record `$REL` and the full list of live domains —
that list, not any list in a document, is what steps 2 and 6 check.

Confirm by content, since a release directory is a tarball extract:

```bash
ls "$REL/assets/sites/pickering-post/"   # launch.php palette.json mark.svg mark-reversed.svg favicon.svg og-default.png
ls "$REL/assets/css/pickering.css"
ls "$REL/app/views/front-pickering.php" "$REL/app/views/article-pickering.php" "$REL/app/views/section-pickering.php"
```

This paper adds **no font files** — it sets in Source Serif 4, which
Turtle Island already brought into the release.

If any are missing, the release predates the merge. As root:

```bash
bash "$REL/tools/vps/upgrade-papers.sh"
```

**Verify:**

- The script ends without `FATAL` and does not roll back.
- Re-run step 0's loop; every paper now serves from the new release
  directory. **Update `$REL` to it.**
- The files above are present.
- `php -l "$REL/app/views/front-pickering.php"` passes.
- Every brand file still parses:
  `for f in "$REL"/assets/sites/*/palette.json; do php -r "json_decode(file_get_contents('$f'), true, 512, JSON_THROW_ON_ERROR); echo '$f ok', PHP_EOL;"; done`

**Schema note:** this release is additive against the live schema. The
**first request** after the upgrade runs any pending migration, which a
`curl` in step 3 can trigger — which is why the network check is step 2.

## 1 · DNS

```bash
dig +short pickeringpost.ca A
dig +short www.pickeringpost.ca A
```

(If `dig` is absent: `getent hosts pickeringpost.ca` — it reads
`/etc/hosts` first, so cross-check that file if the answer looks odd.)
Both names must resolve to this server before the TLS step.

## 2 · Network regression check — do this now

For every domain found in step 0:

```bash
for d in <domains from step 0>; do
  printf '%-24s %s  %s\n' "$d" \
    "$(curl -sk -o /dev/null -w '%{http_code}' --resolve "$d:443:127.0.0.1" "https://$d/")" \
    "$(curl -sk --resolve "$d:443:127.0.0.1" "https://$d/" | grep -o '<title>[^<]*' | head -1)"
done
```

Every domain must answer 200 with **its own** masthead in the title.

**A 200 and a correct title are not sufficient on their own.** The
masthead comes from the shared database, not the release tree, so a paper
whose template and stylesheet are missing still passes that check. Assert
the tree reached each bespoke paper:

```bash
for pair in "tricitiestorch.ca torch.css" "sudburystandard.ca standard.css" "turtleislandtimes.ca turtleisland.css"; do
  set -- $pair
  printf '%-24s ' "$1"
  curl -sk --resolve "$1:443:127.0.0.1" "https://$1/" | grep -o "$2" | head -1
done
```

Each must print its stylesheet. An empty line means the release lacks
that paper's assets — **stop and report**.

## 3 · nginx server block

Add one new block for pickeringpost.ca + www, rooted at `$REL`. Copy an
existing paper's block as the base — take the `fastcgi_pass` socket, the
deny locations and the rewrite list **from the server**, not from this
page.

Pickering needs the standard route set plus the contact page:

```nginx
rewrite ^/contact/?$   /contact.php   last;
```

Do **not** add `/region/` (Western Wire's) or `/about/` (the Standard's
and Turtle Island's).

```bash
nginx -t && systemctl reload nginx
```

**Verify:** `curl -sI -H "Host: pickeringpost.ca" http://127.0.0.1/`
returns 200. **Any paper's chrome is correct at this point** — the tenant
mapping is step 5. Do not stop because it looks like another paper.

An immediate post-reload probe returning 404 has been seen before on this
box and settles within a few seconds; re-probe once before treating it as
a failure.

## 4 · TLS

```bash
certbot --nginx -d pickeringpost.ca -d www.pickeringpost.ca
```

**Verify the listeners before any curl.** Certbot mirrors the listen
directives it finds. If the block it edits carries only one address
family, certbot adds TLS for only that family, and the result passes
`nginx -t`, reports "Successfully deployed", and still fails for every
real client. **This is what stopped the Turtle Island deployment.**

```bash
awk '/server_name pickeringpost\.ca/,/^}/' /etc/nginx/sites-available/pickeringpost \
  | grep -E 'listen.*443'
```

You must see **two** lines — `listen 443 ssl;` **and**
`listen [::]:443 ssl;`. One alone is the failure below.

Then compare the block's TLS lines against a sibling's and mirror
anything missing — the `options-ssl-nginx.conf` include and `ssl_dhparam`
travel with the certificate lines and have gone missing before:

```bash
awk '/listen.*443/,/^}/' /etc/nginx/sites-available/westernwire | grep -E 'ssl_|include'
```

Only then:

- `https://pickeringpost.ca/` serves with a valid certificate, and so
  does `www`.
- Plain http redirects to https on both names.
- The served certificate is **this paper's**:

```bash
echo | openssl s_client -connect 127.0.0.1:443 -servername pickeringpost.ca 2>/dev/null \
  | openssl x509 -noout -subject
```

Must return `CN = pickeringpost.ca`.

**If it returns another paper's certificate:** no block on this box
declares `default_server`, so nginx falls back to the *first* block bound
to that socket — Brampton on IPv4, the Institute on IPv6. Add the missing
`listen` line to **this paper's block only**, then `nginx -t &&
systemctl reload nginx`. Do not add `default_server` to anything, and do
not touch another paper's block.

Finally, confirm you disturbed nobody else's TLS:

```bash
for d in bramptonbulletin.com sudburystandard.ca tricitiestorch.ca westernwire.ca turtleislandtimes.ca; do
  printf '%-24s ' "$d"
  echo | openssl s_client -connect 127.0.0.1:443 -servername "$d" 2>/dev/null | openssl x509 -noout -subject
done
```

Each must return its own CN.

## 5 · The tenant mapping (pre-authorized, tightly scoped)

The live configuration is `$REL/app/config.site.php`. Expect the
environment to require **fresh human authorization** before this file is
touched, and expect the prompt to time out if nobody is watching. Treat
this as pre-authorized and finish in one pass: back up, add exactly three
semantic entries, lint, stop.

```bash
cp -a "$REL/app/config.site.php" \
   "/root/prairiepost-config-pre-pickering-$(date -u +%Y%m%dT%H%M%SZ).php"
chmod 600 /root/prairiepost-config-pre-pickering-*.php
```

The live file uses a **three-stage exact-match tenant selector**:
hostname → tenant key, tenant key → site slug, site slug → canonical URL.
It is not the two `str_contains` matches in `config.example.php`.
**Mirror the architecture you find in the file** — do not paste literal
config lines from any runbook.

Add exactly these three entries, nothing else:

| Stage | Add |
| --- | --- |
| hostname → tenant | `pickeringpost.ca` **and** `www.pickeringpost.ca` resolve to this paper's tenant key |
| tenant → site slug | that key maps to slug **`pickering-post`** |
| slug → canonical URL | `pickering-post` maps to **`https://pickeringpost.ca`** (bare domain, no trailing slash) |

Every other arm stays byte-for-byte unchanged. Do not edit `config.php` —
it is generated and the next upgrade overwrites it.

```bash
php -l "$REL/app/config.site.php"
php -l "$REL/config.php"
```

**Verify:** `https://pickeringpost.ca/` renders the paper's chrome — a
grey utility strip, a cyan tile carrying **P** beside the nameplate, a
band-navy section nav, and a navy footer. The source contains
`pickering.css` and the `t-pickering` body class. `www` reaches the same
paper.

**The pre-seed state below is CORRECT. Do not stop over any of it.**

| You will see | Because |
| --- | --- |
| The nameplate reads **Pickering Post**, without the leading "The" | auto-provisioning names a site `ucwords(str_replace('-',' ',$slug))`, which for this slug gives "Pickering Post". The seeded title is "The Pickering Post", so the word **The** above the nameplate is what appears at step 6 |
| The tagline reads **News to the horizon** | the network default in `pp_site_default_settings()`. The paper's own tagline is written by step 6, and is the clearest single signal that step 6 has happened |
| The utility strip shows **Newsletter and Sign in but no Contact** | the Contact link is guarded on `contact_email`, which defaults to empty and is written by step 6 |
| The nav shows **Home · Community · Sports · Opinion · Contact** — Local News and Events missing | desks are shared across the network, and the nav lists only those that exist. Measured against a twelve-paper database: `community`, `sports` and `opinion` were already seeded by sister papers and appear immediately; `local-news`, `events`, `obituaries` and `breaking` are new and appear after step 6. Verify against the live desk list below rather than against this row |
| No hero, and "the newsroom hasn't filed yet" | correct, and it stays that way — this pack seeds no stories |

To read the live desk list rather than guess:
`cd "$REL" && php -r 'require "app/bootstrap.php"; foreach (categories_all() as $c) echo $c["slug"], "\n";'`

Then re-run step 2's loop once: every other paper is still itself.

## 6 · Identity, desks and sources — one command

```bash
cd "$REL" && PP_SITE=pickering-post php tools/seed-launch.php
```

Expect the output to end **`Done — 0 stories added.`** Zero is correct
and is the point of this pack. The trailing "The front page is live" is
the seeder's stock line; ignore it. There should be no `skipped` lines at
all, because there are no stories to skip.

The seeder prints `desk added:` only for desks the shared database was
**missing**. Any of the eight that a sister paper already seeded is
matched by slug and reused, so it will not appear in that output — its
absence is the pack working, not a failure. Check the nav after the run,
not the `desk added:` lines.

**Verify on https://pickeringpost.ca:**

1. The utility strip's dateline now reads **Durham Region's daily**, not
   "News to the horizon". **This is the check that tells you the seeder
   ran** — the nameplate is a weaker signal, because it read nearly
   correctly beforehand.
2. The utility strip's right side now reads **Newsletter · Contact ·
   Sign in**. Contact appears at this step, guarded on `contact_email`.
3. The masthead reads **The** over **Pickering Post** beside the cyan
   tile.
4. The navy band carries Home · Local News · Community · Events · Sports
   · Opinion · Contact.
5. `/desk/local-news`, `/desk/community`, `/desk/events`, `/desk/sports`,
   `/desk/opinion`, `/desk/business`, `/desk/obituaries`,
   `/desk/breaking` — all 200. Each shows its name in caps between two
   hairlines, with its standing description beneath.
6. `/contact` — 200. A 404 means the step 3 rewrite is missing.
7. `/search`, `/corrections`, `/feed/`, `/sitemap.xml` — all 200.
8. Headlines render in **Source Serif 4**, not a fallback serif. The face
   is served from `/assets/fonts/source-serif-4-latin.woff2`; confirm it
   returns 200. Nothing loads from a font CDN.
9. The front page still shows the empty state. Correct.
10. Re-run step 2's loop across every domain. All still 200, all still
    themselves.

## 7 · Cron and mail

Nothing new to install. The network-wide fetch already covers this
paper's three sources; they populate the newsroom's story-idea feed and
publish nothing on their own.

Do **not** enable the newsletter. `newsletter_enabled` stays off until
the owner's mailboxes, SMTP identity, mailing address, SPF and DKIM are
in place and a test send has arrived.

## 8 · Handing over to the newsroom

The paper is live and empty, which is the intended state. The newsroom
signs in at `/admin/` with an existing network account and files the
first story.

Three things worth telling the desk:

- The **hero** follows the post marked featured. Give it a photograph —
  the hero is built around one, and without an image it falls back to
  flat navy.
- **Local Events** and **Community Spotlight** on the front page draw
  from the `events` and `community` desks specifically. File to those
  desks and the front page fills itself.
- **Breaking** is the only desk that wears magenta, and it is the only
  place magenta appears anywhere on the paper. Use it for stories that
  are still moving, and move them to their real desk afterwards.

## Troubleshooting

| Symptom | Likely cause |
| --- | --- |
| `/contact` 404s but `/contact.php` works | the `/contact` rewrite is missing from the block (step 3) |
| The paper serves another paper's chrome | the hostname arm didn't match — check all three stages, and that you edited `app/config.site.php`, not the generated `config.php` |
| TLS serves another paper's certificate | the block is missing one address family's `listen` line (step 4) |
| Tagline still reads "News to the horizon" | the seeder hasn't run, or ran with a different `PP_SITE` |
| Headlines render in a plain serif | `assets/fonts/source-serif-4-latin.woff2` didn't come across in the release copy |
| The tile is missing its letter | `favicon.svg` / `mark.svg` embed an 11KB font subset; if the letter is absent the file was truncated in transit |
| The front page has no hero | no post is marked featured, or the featured post has no image |
| Another paper broke after the upgrade | restore that vhost's `.bak.*` file written by `upgrade-papers.sh`, `nginx -t`, reload, and report |

## What NOT to do

- Don't `git pull` inside a release directory. Releases are immutable
  extracts; new code arrives only through `upgrade-papers.sh`.
- Don't edit the generated `config.php`. The tenant configuration is
  `app/config.site.php`.
- Don't copy the release directory for this paper.
- Don't add placeholder stories to make the front page look full. The
  empty state is deliberate and the newsroom fills it.
- Don't hand-edit the shared database.
- Don't add `default_server` to any block while fixing TLS.
- Don't force a verification green. Two failures on the same step is a
  full stop and a report.
