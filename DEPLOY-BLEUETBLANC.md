# Taking bleuetblanc.ca live — deployment runbook

This runbook takes **Le Bleuet Blanc** live on the VPS that already serves
the network. Same codebase, same shared database — a new nginx server
block and a tenant mapping, never a copy of the code.

Two things make this paper different from the eight before it.

**It is the network's first French-language paper.** The chrome — nav
labels, buttons, the masthead dateline, the words around a byline — is
translated by a new i18n layer that keys off `chrome.lang` in the
paper's `palette.json`. Every other paper omits that key and is
unaffected. Verified by rendering the seven public page types on all
thirteen papers before and after the change: the twelve English papers
came out byte-identical.

**It launches with twenty-five stories.** They are demonstration copy —
Quebec register, plausible, deliberately not journalism — so the front,
the section fronts and the article page can be shown to someone. That
means this deployment *can* hit the network-wide `posts.slug` uniqueness
constraint, and step 6's verification is written accordingly.

Its domain is **bleuetblanc.ca**. The bare domain is canonical; `www`
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

**Pre-flight, and only for this paper: check that PHP has `intl`.**

```bash
php -r 'echo class_exists("IntlDateFormatter") ? "intl present\n" : "INTL ABSENT\n";'
```

French month and weekday names come from ICU. PHP's own `date()` is
locale-independent and always emits English, so without `intl` this
paper serves a masthead reading "Monday, August 24, 2026" on an
otherwise French page. The code degrades to that rather than fataling —
it will not break the site — but it is not a launchable state. If it
prints `INTL ABSENT`, install `php8.x-intl`, reload PHP-FPM, and re-run
the check before going further.

Confirm the paper reached the release by content, since a release
directory is a tarball extract:

```bash
ls "$REL/assets/sites/bleuet-blanc/"        # launch.php palette.json mark.svg mark-reversed.svg favicon.svg img/
ls "$REL/assets/sites/bleuet-blanc/img/"    # bleuetiere.svg traversier.svg theatre.svg cartes-eau.svg
ls "$REL/assets/css/bleuetblanc.css"
ls "$REL/app/i18n.php" "$REL/app/lang/fr.php"
ls "$REL/app/views/front-bleuetblanc.php" "$REL/app/views/article-bleuetblanc.php" "$REL/app/views/section-bleuetblanc.php"
```

This paper adds **no font files** — it sets in Source Serif 4, which
Turtle Island already brought into the release. `bleuetblanc.css`
declares its own `@font-face` rather than relying on another paper's
stylesheet being loaded, which is why it needs no new file and still
paints the real face.

If any are missing, the release predates the merge. As root:

```bash
bash "$REL/tools/vps/upgrade-papers.sh"
```

**Verify:**

- The script ends without `FATAL` and does not roll back.
- Re-run step 0's loop; every paper now serves from the new release
  directory. **Update `$REL` to it.**
- The files above are present.
- `php -l "$REL/app/views/front-bleuetblanc.php"` and
  `php -l "$REL/app/i18n.php"` pass.
- Every brand file still parses:
  `for f in "$REL"/assets/sites/*/palette.json; do php -r "json_decode(file_get_contents('$f'), true, 512, JSON_THROW_ON_ERROR); echo '$f ok', PHP_EOL;"; done`

**Schema note:** this release is additive against the live schema. The
**first request** after the upgrade runs any pending migration, which a
`curl` in step 3 can trigger — which is why the network check is step 2.

## 1 · DNS

```bash
dig +short bleuetblanc.ca A
dig +short www.bleuetblanc.ca A
```

(If `dig` is absent: `getent hosts bleuetblanc.ca` — it reads
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
for pair in "tricitiestorch.ca torch.css" "sudburystandard.ca standard.css" \
            "turtleislandtimes.ca turtleisland.css" "pickeringpost.ca pickering.css"; do
  set -- $pair
  printf '%-24s ' "$1"
  curl -sk --resolve "$1:443:127.0.0.1" "https://$1/" | grep -o "$2" | head -1
done
```

Each must print its stylesheet. An empty line means the release lacks
that paper's assets — **stop and report**.

Because the i18n layer is new shared code, add one assertion the earlier
runbooks did not need — that the English papers are still English:

```bash
for d in bramptonbulletin.com sudburystandard.ca pickeringpost.ca; do
  printf '%-24s ' "$d"
  curl -sk --resolve "$d:443:127.0.0.1" "https://$d/" | grep -o '<html lang="[a-z-]*"' | head -1
done
```

Each must print `<html lang="en">`. Anything else means a paper picked up
a language it never declared — **stop and report**.

## 3 · nginx server block

Add one new block for bleuetblanc.ca + www, rooted at `$REL`. Copy an
existing paper's block as the base — take the `fastcgi_pass` socket, the
deny locations and the rewrite list **from the server**, not from this
page.

Le Bleuet Blanc needs the standard route set plus the contact page:

```nginx
rewrite ^/contact/?$   /contact.php   last;
```

Do **not** add `/region/` (Western Wire's) or `/about/` (the Standard's
and Turtle Island's).

```bash
nginx -t && systemctl reload nginx
```

**Verify:** `curl -sI -H "Host: bleuetblanc.ca" http://127.0.0.1/`
returns 200. **Any paper's chrome is correct at this point** — the tenant
mapping is step 5. Do not stop because it looks like another paper, and
do not stop because the page is in English.

An immediate post-reload probe returning 404 has been seen before on this
box and settles within a few seconds; re-probe once before treating it as
a failure.

## 4 · TLS

```bash
certbot --nginx -d bleuetblanc.ca -d www.bleuetblanc.ca
```

**Verify the listeners before any curl.** Certbot mirrors the listen
directives it finds. If the block it edits carries only one address
family, certbot adds TLS for only that family, and the result passes
`nginx -t`, reports "Successfully deployed", and still fails for every
real client. **This is what stopped the Turtle Island deployment.**

```bash
awk '/server_name bleuetblanc\.ca/,/^}/' /etc/nginx/sites-available/bleuetblanc \
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

- `https://bleuetblanc.ca/` serves with a valid certificate, and so does
  `www`.
- Plain http redirects to https on both names.
- The served certificate is **this paper's**:

```bash
echo | openssl s_client -connect 127.0.0.1:443 -servername bleuetblanc.ca 2>/dev/null \
  | openssl x509 -noout -subject
```

Must return `CN = bleuetblanc.ca`.

**If it returns another paper's certificate:** no block on this box
declares `default_server`, so nginx falls back to the *first* block bound
to that socket — Brampton on IPv4, the Institute on IPv6. Add the missing
`listen` line to **this paper's block only**, then `nginx -t &&
systemctl reload nginx`. Do not add `default_server` to anything, and do
not touch another paper's block.

Finally, confirm you disturbed nobody else's TLS:

```bash
for d in bramptonbulletin.com sudburystandard.ca tricitiestorch.ca \
         westernwire.ca turtleislandtimes.ca pickeringpost.ca; do
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
   "/root/prairiepost-config-pre-bleuetblanc-$(date -u +%Y%m%dT%H%M%SZ).php"
chmod 600 /root/prairiepost-config-pre-bleuetblanc-*.php
```

The live file uses a **three-stage exact-match tenant selector**:
hostname → tenant key, tenant key → site slug, site slug → canonical URL.
It is not the two `str_contains` matches in `config.example.php`.
**Mirror the architecture you find in the file** — do not paste literal
config lines from any runbook.

Add exactly these three entries, nothing else:

| Stage | Add |
| --- | --- |
| hostname → tenant | `bleuetblanc.ca` **and** `www.bleuetblanc.ca` resolve to this paper's tenant key |
| tenant → site slug | that key maps to slug **`bleuet-blanc`** (hyphenated; the CSS class and template name are `bleuetblanc` without the hyphen — they are not the same string and neither is a typo) |
| slug → canonical URL | `bleuet-blanc` maps to **`https://bleuetblanc.ca`** (bare domain, no trailing slash) |

Every other arm stays byte-for-byte unchanged. Do not edit `config.php` —
it is generated and the next upgrade overwrites it.

```bash
php -l "$REL/app/config.site.php"
php -l "$REL/config.php"
```

**Verify:** `https://bleuetblanc.ca/` renders the paper's chrome — a
dark-navy service strip, a three-berry mark beside the nameplate, a blue
nav band, and a blue newsletter panel above the footer. The source
contains `bleuetblanc.css`, the `t-bleuetblanc` body class, and
`<html lang="fr">`.  `www` reaches the same paper.

**The pre-seed state below is CORRECT. Do not stop over any of it.**

| You will see | Because |
| --- | --- |
| The nameplate reads **Bleuet Blanc**, without the leading "Le" | auto-provisioning names a site `ucwords(str_replace('-',' ',$slug))`, which for this slug gives "Bleuet Blanc". The seeded title is "Le Bleuet Blanc", so the word **Le** joins the nameplate at step 6 |
| The line under the nameplate reads **News to the horizon** | the network default in `pp_site_default_settings()`. The paper's own line, "Le Québec, de la région vers le monde", is written by step 6 and is the clearest single signal that step 6 has happened |
| The service strip shows **Infolettres and Se connecter but no Nous joindre** | the contact link is guarded on `contact_email`, which defaults to empty and is written by step 6 |
| **The chrome is already in French** — Accueil, Chercher, Dernières, Rubriques | correct, and it is the one identity signal that does *not* wait for step 6. The language comes from `palette.json`, which ships in the release, not from the database |
| **The nav band carries only Accueil** | measured against a thirteen-paper database: **all ten** of this paper's desks are new to the network. Its slugs — `actualites`, `politique`, `economie`, `regions`, `culture-qc`, `societe`, `environnement`, `sports-qc`, `idees`, `le-fil` — were chosen not to collide with the English papers', so none of them exists yet and the bar is empty until step 6 |
| The masthead dateline reads **Édition du lundi …** in French | the i18n layer, from the release. If it reads "Monday" instead, `intl` is missing — go back to step 0's pre-flight |
| No hero, and an empty-state line | correct; the twenty-five stories arrive at step 6 |

To read the live desk list rather than guess:
`cd "$REL" && php -r 'require "app/bootstrap.php"; foreach (categories_all() as $c) echo $c["slug"], "\n";'`

Then re-run step 2's loop once: every other paper is still itself, and
still `lang="en"`.

## 6 · Identity, desks, sources and the twenty-five stories

```bash
cd "$REL" && PP_SITE=bleuet-blanc php tools/seed-launch.php
```

Expect the output to end **`Done — 25 stories added.`**

**Read the output for the word `skipped` before anything else.** Unlike
the last two launches, this pack carries stories, so it can collide.
`posts.slug` is UNIQUE across the whole shared database and the seeder
silently skips a story whose slug is already taken — the run still
reports success. Every slug in this pack is prefixed `bb-` for that
reason, and the pack was seeded against a database already holding all
twelve sister papers' content with zero collisions.

- `Done — 25 stories added.` and no `skipped` lines → correct.
- **Any** `skipped` line, or a count below 25 → **stop and report the
  exact lines.** Do not re-run, and do not edit the pack on the server.

You should also see **ten** `desk added:` lines — Actualités, Politique,
Économie, Régions, Culture, Société, Environnement, Sports, Idées, Le
fil. All ten are new to the network. Fewer than ten means a slug
collided with an existing desk; report which.

**Verify on https://bleuetblanc.ca:**

1. The line under the nameplate now reads **Le Québec, de la région vers
   le monde**, not "News to the horizon". **This is the check that tells
   you the seeder ran.**
2. The service strip's right side now reads **Infolettres · Nous joindre
   · Se connecter**.
3. The nameplate reads **Le Bleuet Blanc** beside the three-berry mark.
4. The blue band carries **Accueil · Actualités · Politique · Économie ·
   Régions · Culture · Société** — seven items. The bar shows six
   rubriques by design; Environnement, Sports, Idées and Le fil live in
   the footer and at their own URLs.
5. The front page shows a full-width blue lead panel with the headline
   *La bleuetière comme modèle d'exportation*, then three columns — **La
   rédaction**, **Dernières**, **En vogue**.
6. `/desk/actualites` (4 stories), `/desk/politique`, `/desk/economie`,
   `/desk/regions`, `/desk/culture-qc`, `/desk/societe` (3 each),
   `/desk/environnement`, `/desk/le-fil` (2 each), `/desk/idees`,
   `/desk/sports-qc` (1 each) — all 200, none empty.
7. `/desk/le-fil` is the **only** page anywhere on the paper showing
   magenta: a badge above the section head that *paints* as EN DIRECT.
   In the served HTML the badge is `<span class="bb-direct">En direct</span>`
   — the capitals come from `text-transform: uppercase` in the CSS, so a
   crawler greping raw HTML must match `En direct` (or search
   case-insensitively), never the uppercase string. If magenta appears
   anywhere else, report it.
8. `/contact` — 200. A 404 means the step 3 rewrite is missing.
9. `/search`, `/corrections`, `/feed/`, `/sitemap.xml`, `/newsletter/` —
   all 200.
10. The masthead dateline reads **Édition du <jour> <n> <mois> <année>**
    in French, and a timestamp in the Dernières column reads like
    **6 h 15** — lowercase `h`, thin spaces. `6 H 15` or `6:15 a.m.`
    means something is wrong with the language plumbing; report it.
11. `<html lang="fr">` in the page source.
12. Headlines render in **Source Serif 4**, not a fallback serif. The
    face is served from `/assets/fonts/source-serif-4-latin.woff2`;
    confirm it returns 200. Nothing loads from a font CDN.
13. Re-run step 2's loop across every domain. All still 200, all still
    themselves, and the three English papers still `lang="en"`.

## 7 · Cron and mail

Nothing new to install. The network-wide fetch already covers this
paper's two sources; they populate the newsroom's story-idea feed and
publish nothing on their own.

Do **not** enable the newsletter. `newsletter_enabled` stays off until
the owner's mailboxes, SMTP identity, mailing address, SPF and DKIM are
in place and a test send has arrived.

## 8 · Handing over to the newsroom

The paper is live and full, and **everything in it is demonstration
copy**. The twenty-five stories exist so the design can be shown; they
are not journalism and carry no reporting. The newsroom's first job is
to replace them, not to add to them.

Four things worth telling the desk:

- The **manchette** — the full-width blue lead panel — follows the post
  marked featured. It carries no photograph by design; the mark sits
  behind it as a watermark instead. Give it a headline that can hold the
  space.
- **La rédaction**, the left rail, builds itself from the bylines on the
  front page. It skips the collective byline, so a story signed *La
  rédaction* fills the page without adding a name to the rail.
- **Le fil** is the only desk that wears magenta, and it is the only
  place magenta appears anywhere on the paper. Use it while a story is
  still moving, and move it to its real desk afterwards.
- Chrome is translated; **editorial copy never is.** The desk writes in
  French and the platform's furniture follows. If a new word is needed in
  the chrome, it goes in `app/lang/fr.php`, keyed by its English string.

## Troubleshooting

| Symptom | Likely cause |
| --- | --- |
| The masthead reads "Monday, August 24, 2026" on a French page | `intl` is not installed — step 0's pre-flight. The site degrades to English dates rather than fataling, which is why nothing else looks broken |
| The chrome is in English but the stories are in French | `chrome.lang` isn't reaching `pp_lang()` — check that `palette.json` came across in the release and still parses |
| `/contact` 404s but `/contact.php` works | the `/contact` rewrite is missing from the block (step 3) |
| The paper serves another paper's chrome | the hostname arm didn't match — check all three stages, and that you edited `app/config.site.php`, not the generated `config.php` |
| Slug not found / the mapping "looks right" but fails | the slug is `bleuet-blanc`; the template and CSS class are `bleuetblanc`. Mixing them silently falls through to the default chrome |
| TLS serves another paper's certificate | the block is missing one address family's `listen` line (step 4) |
| The seeder reports fewer than 25 stories, or any `skipped` | a slug collision in the shared database — stop and report, don't re-run |
| The nav band shows only Accueil after step 6 | the seeder ran against a different `PP_SITE`, or didn't run |
| Headlines render in a plain serif | `assets/fonts/source-serif-4-latin.woff2` didn't come across in the release copy |
| A section front's photo fills the whole page width | `bleuetblanc.css` is stale — the grid uses `auto-fill`, not `auto-fit`, so a one-story desk keeps a normal column |
| Another paper broke after the upgrade | restore that vhost's `.bak.*` file written by `upgrade-papers.sh`, `nginx -t`, reload, and report |

## What NOT to do

- Don't `git pull` inside a release directory. Releases are immutable
  extracts; new code arrives only through `upgrade-papers.sh`.
- Don't edit the generated `config.php`. The tenant configuration is
  `app/config.site.php`.
- Don't copy the release directory for this paper.
- Don't translate any editorial copy, on this paper or any other.
- Don't add English desks to this paper's nav to make the bar look
  fuller. Its ten desks are deliberately its own.
- Don't re-run the seeder to "fix" a skipped story. A skip means a slug
  is taken network-wide, and re-running skips it again.
- Don't hand-edit the shared database.
- Don't add `default_server` to any block while fixing TLS.
- Don't force a verification green. Two failures on the same step is a
  full stop and a report.
