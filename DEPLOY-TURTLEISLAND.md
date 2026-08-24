# Taking turtleislandtimes.ca live — deployment runbook

This runbook takes **Turtle Island Times** live on the VPS that already
serves the network. Same codebase, same shared database — a new nginx
server block and a tenant mapping, never a copy of the code.

**This paper launches with no stories.** Its launch pack seeds identity,
desks and wire sources only; editorial comes from the newsroom. That
removes the whole `posts.slug` collision class from this deployment, and
it means the front page reads "the newsroom hasn't filed yet" until the
first real story is published. **That is the expected end state of this
runbook, not a failure.**

Its domain is **turtleislandtimes.ca**. The bare domain is canonical;
`www` serves and redirects through the same block.

**The rules that hold throughout:**

- Credentials never leave the server and are never printed. Never `cat` a
  config file.
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
that list, not any list in a document, is what steps 2 and 5 check.

Confirm by content, since a release directory is a tarball extract:

```bash
ls "$REL/assets/sites/turtle-island-times/"   # launch.php palette.json mark.svg mark-reversed.svg favicon.svg og-default.png
ls "$REL/assets/css/turtleisland.css"
ls "$REL/assets/fonts/source-serif-4-latin.woff2" "$REL/assets/fonts/source-serif-4-italic-latin.woff2"
ls "$REL/app/views/front-turtleisland.php" "$REL/app/views/article-turtleisland.php" "$REL/app/views/section-turtleisland.php"
```

If any are missing, the release predates the merge. As root:

```bash
bash "$REL/tools/vps/upgrade-papers.sh"
```

**Verify:**

- The script ends without `FATAL` and does not roll back.
- Re-run step 0's loop; every paper now serves from the new release
  directory. **Update `$REL` to it.**
- The files above are present.
- `php -l "$REL/app/views/front-turtleisland.php"` passes.
- Every brand file still parses:
  `for f in "$REL"/assets/sites/*/palette.json; do php -r "json_decode(file_get_contents('$f'), true, 512, JSON_THROW_ON_ERROR); echo '$f ok', PHP_EOL;"; done`

**Schema note:** this release is additive against the live schema. The
**first request** after the upgrade runs any pending migration, which a
`curl` in step 3 can trigger — which is why the network check is step 2,
not the last step.

## 1 · DNS

```bash
dig +short turtleislandtimes.ca A
dig +short www.turtleislandtimes.ca A
```

(If `dig` is absent: `getent hosts turtleislandtimes.ca` — it reads
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
curl -sk --resolve tricitiestorch.ca:443:127.0.0.1 https://tricitiestorch.ca/ | grep -o 'torch\.css'
curl -sk --resolve sudburystandard.ca:443:127.0.0.1 https://sudburystandard.ca/ | grep -o 'standard\.css'
```

Both must print a match. Empty output means the release lacks that
paper's assets — **stop and report**.

## 3 · nginx server block

Add one new block for turtleislandtimes.ca + www, rooted at `$REL`. Copy
an existing paper's block as the base — take the `fastcgi_pass` socket,
the deny locations and the rewrite list **from the server**, not from
this page.

Turtle Island needs the same route set the Standard uses, including:

```nginx
rewrite ^/about/?$   /about.php   last;
```

Do **not** add `/region/` — that is Western Wire's alone.

```bash
nginx -t && systemctl reload nginx
```

**Verify:** `curl -sI -H "Host: turtleislandtimes.ca" http://127.0.0.1/`
returns 200. **Any paper's chrome is correct at this point** — the tenant
mapping is step 5. Do not stop because it looks like another paper.

## 4 · TLS

```bash
certbot --nginx -d turtleislandtimes.ca -d www.turtleislandtimes.ca
```

**Verify:** `https://turtleislandtimes.ca/` serves with a valid
certificate; plain http redirects to https.

## 5 · The tenant mapping (pre-authorized, tightly scoped)

The live configuration is `$REL/app/config.site.php`. Expect the
environment to require **fresh human authorization** before this file is
touched, and expect the prompt to time out if nobody is watching. Treat
this as pre-authorized and finish in one pass: back up, add exactly three
semantic entries, lint, stop.

```bash
cp -a "$REL/app/config.site.php" \
   "/root/prairiepost-config-pre-turtleisland-$(date -u +%Y%m%dT%H%M%SZ).php"
chmod 600 /root/prairiepost-config-pre-turtleisland-*.php
```

The live file uses a **three-stage exact-match tenant selector**:
hostname → tenant key, tenant key → site slug, site slug → canonical URL.
It is not the two `str_contains` matches in `config.example.php`.
**Mirror the architecture you find in the file** — do not paste literal
config lines from any runbook.

Add exactly these three entries, nothing else:

| Stage | Add |
| --- | --- |
| hostname → tenant | `turtleislandtimes.ca` **and** `www.turtleislandtimes.ca` resolve to this paper's tenant key |
| tenant → site slug | that key maps to slug **`turtle-island-times`** |
| slug → canonical URL | `turtle-island-times` maps to **`https://turtleislandtimes.ca`** (bare domain, no trailing slash) |

Every other arm stays byte-for-byte unchanged. Do not edit `config.php` —
it is generated and the next upgrade overwrites it.

```bash
php -l "$REL/app/config.site.php"
php -l "$REL/config.php"
```

**Verify:** `https://turtleislandtimes.ca/` renders the paper's chrome —
a deep ink masthead, the turtle reversed out above the nameplate, a
rust-coloured nav rail, and a paper-coloured column floating on a
dot-screened ink field. The source contains `turtleisland.css` and the
`t-turtleisland` body class. `www` reaches the same paper.

**The pre-seed state below is CORRECT. Do not stop over any of it.**

| You will see | Because |
| --- | --- |
| The nameplate already reads **Turtle Island Times** | auto-provisioning names a new site `ucwords(str_replace('-',' ',$slug))`, and for this slug that is exactly the paper's title. It looks seeded because the slug flatters it — it is not. Do not treat the correct nameplate as evidence the seeder has run |
| The tagline reads **News to the horizon** | the network default in `pp_site_default_settings()`; the paper's own tagline is written by step 6, and is the clearest single signal that step 6 has happened |
| The utility bar shows **Subscribe and Sign in but no Contact** | the Contact link is guarded on `contact_email`, which defaults to empty and is written by step 6 |
| The nav shows Home, **whichever of News / Land & Water / Language / Culture / Governance already exist network-wide**, then About | desks are shared across the network, so any a sister paper already seeded appear now and the rest appear after step 6. `news` and `culture` are very likely already present |
| The front page says the newsroom hasn't filed yet | correct, and it stays that way — this pack seeds no stories |

To see the live desk list rather than guessing:
`cd "$REL" && php -r 'require "app/bootstrap.php"; foreach (categories_all() as $c) echo $c["slug"], "\n";'`

Then re-run step 2's loop once: every other paper is still itself.

## 6 · Identity, desks and sources — one command

```bash
cd "$REL" && PP_SITE=turtle-island-times php tools/seed-launch.php
```

Expect the output to end **`Done — 0 stories added.`** Zero is correct
and is the point of this pack. The trailing sentence "The front page is
live" is the seeder's stock line; ignore it.

The seeder prints `desk added:` only for desks the shared database was
**missing**. Any of News, Land & Water, Language, Culture or Governance
that a sister paper already seeded is matched by slug and reused, so it
will not appear in that output — its absence is the pack working, not a
failure. Check the nav after the run, not the `desk added:` lines.

**Verify on https://turtleislandtimes.ca:**

1. The utility bar's tagline now reads **Independent news from across
   the territories**, not "News to the horizon". This is the check that
   tells you the seeder ran — the nameplate does not, because it read
   correctly beforehand.
2. The utility bar's right side now reads **Subscribe · Contact · Sign
   in**. Contact appears **at this step**, guarded on `contact_email`.
3. The nameplate reads **Turtle Island Times** with the turtle reversed
   out above it and the caps cutting into its shell.
4. The rust rail carries Home · News · Land & Water · Language ·
   Culture · Governance · About, and a search box.
5. `/desk/news`, `/desk/land-water`, `/desk/language`, `/desk/culture`,
   `/desk/governance` — all 200, each showing its desk name in the ink
   block in place of the nameplate, and its standing description beneath.
6. `/about` — 200. A 404 means the step 3 rewrite is missing.
7. `/search`, `/corrections`, `/feed/`, `/sitemap.xml` — all 200.
8. Headlines render in **Source Serif 4**, not a fallback serif. The
   paper self-hosts the face from `/assets/fonts/`; nothing loads from a
   font CDN. Confirm the two woff2 files return 200.
9. Re-run step 2's loop. Every other paper is still itself.

## 7 · Cron and mail

Nothing new to install. The network-wide fetch already covers this
paper's five sources; they populate the newsroom's story-idea feed and
publish nothing on their own.

Do **not** enable the newsletter. `newsletter_enabled` stays off until
the owner's mailboxes, SMTP identity, mailing address, SPF and DKIM are
in place and a test send has arrived.

## 8 · Handing over to the newsroom

The paper is live and empty, which is the intended state. The newsroom
signs in at `/admin/` with an existing network account and files the
first story. Until then the front page carries the empty-state line
rather than placeholder copy.

Two things worth telling the desk:

- The front page's Featured slot follows the post marked featured;
  everything else falls into Latest newest-first.
- A story without a photograph is not broken — its tile renders as an ink
  block carrying the headline, by design, rather than reaching for stock
  art.

## Troubleshooting

| Symptom | Likely cause |
| --- | --- |
| `/about` 404s but `/about.php` works | the `/about` rewrite is missing from the block (step 3) |
| The paper serves another paper's chrome | the hostname arm didn't match — check all three stages, and that you edited `app/config.site.php`, not the generated `config.php` |
| Tagline still reads "News to the horizon" | the seeder hasn't run (step 6), or it ran with a different `PP_SITE`. The nameplate is not a useful signal here — it reads correctly either way |
| Headlines render in a plain serif | the `assets/fonts/source-serif-4-*.woff2` files didn't come across in the release copy |
| The turtle is missing from the masthead | `mark-reversed.svg` is missing from `assets/sites/turtle-island-times/` |
| The rail is magenta rather than rust | `--spot` in `turtleisland.css` was changed; the package offers both and the build ships rust |
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
- Don't force a verification green. Two failures on the same step is a
  full stop and a report.
