# Taking mississaugamonitor.com live — deployment runbook

This runbook takes **The Mississauga Monitor** live on the VPS that
serves the network. It is the first launch under the phase-1 machinery,
and it shows: **there is no config-file step.** The launch is DNS, a
generated nginx block, a certificate, and the seeder. The tenant
mapping is a database row the seeder writes from this paper's pack.

Its domain is **mississaugamonitor.com**. The bare domain is canonical;
`www` serves through the same block.

**The rules that hold throughout:**

- Never print `app/config.site.php` or `config.php` — and this launch
  never touches either. Nginx blocks are fine to read.
- No manual database edits. Migrations and the seeder do all writes.
- Don't touch another paper's server block, cron file, or anything
  belonging to CivisMedia or the Institute.
- Service actions: `nginx -t && systemctl reload nginx`, and certbot.
- **Stop after two failed attempts at the same verification** with an
  exact report.
- Discover server facts; `$REL` is the release root found in step 0.
- Verify text at the layer that produces it (CLAUDE.md lesson 10):
  every string below is stated as it appears in the served HTML.

## 0 · Map, and get the paper into the release

Enumerate the enabled vhosts (`readlink -f` the roots); record `$REL`
and the live domain list. Confirm the paper reached the release by
content:

```bash
ls "$REL/assets/sites/mississauga-monitor/"   # launch.php palette.json mark.svg mark-reversed.svg favicon.svg img/
ls "$REL/assets/css/monitor.css"
ls "$REL/assets/fonts/inter-latin.woff2" "$REL/assets/fonts/inter-italic-latin.woff2"
ls "$REL/app/views/chrome/monitor-head.php" "$REL/app/views/chrome/monitor-foot.php"
ls "$REL/app/views/front-monitor.php" "$REL/app/views/article-monitor.php" "$REL/app/views/section-monitor.php"
```

This paper vendors **Inter** (two woff2 files above); Playfair Display
already ships. If files are missing, the release predates the merge:
run `bash "$REL/tools/vps/upgrade-papers.sh"` as root, confirm no
FATAL/rollback, update `$REL`, and immediately re-run the full network
regression (every live domain 200 with its own title; English controls
`<html lang="en">`, bleuetblanc.ca `<html lang="fr">`) — the first
request after an upgrade runs pending migrations.

## 1 · DNS

`mississaugamonitor.com` and `www.mississaugamonitor.com` must resolve
to this server (`dig +short`, cross-check `/etc/hosts` if odd).

## 2 · nginx block — generated, not copied

```bash
SOCKET=$(grep -rhEo 'unix:[^;]+' /etc/nginx/sites-enabled/pickeringpost | head -1 | sed 's/unix://')
bash "$REL/tools/vps/make-vhost.sh" "$REL" "$SOCKET" \
  mississaugamonitor.com www.mississaugamonitor.com \
  > /etc/nginx/sites-available/mississaugamonitor
ln -s /etc/nginx/sites-available/mississaugamonitor /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

The generated block is a front controller: routes live in router.php,
so no rewrite list exists to drift. Both port-80 address families are
in the template, so certbot mirrors both onto 443.

**Verify:** `curl -sI -H "Host: mississaugamonitor.com" http://127.0.0.1/`
returns 200. **The page is another paper's chrome at this point** — the
tenant row arrives with the seeder in step 4. Do not stop over it. A
404 immediately after reload settles in seconds; re-probe once.

## 3 · TLS

`certbot --nginx -d mississaugamonitor.com -d www.mississaugamonitor.com`

Then, before any curl: the block must carry BOTH `listen 443 ssl;` and
`listen [::]:443 ssl;`; mirror any missing `ssl_`/`include` lines from
a sibling. Verify both names serve with a valid certificate, http
redirects to https, and `openssl s_client -servername
mississaugamonitor.com` returns `CN = mississaugamonitor.com`. If it
returns `CN = reject.invalid`, the hostname missed the block's
server_name; if another paper's CN, a listen line is missing — fix this
block only, never add `default_server` anywhere. Confirm every sister
domain still serves its own CN.

## 4 · Seed — identity, desks, domains, stories, in one command

```bash
cd "$REL" && PP_SITE=mississauga-monitor php tools/seed-launch.php
```

**Read the output for `COLLISION`, `CONFLICT` or `FAILED` first** —
any such line, or a non-zero exit, is a full stop; report the lines,
run nothing twice. Expected:

- `domain: mississaugamonitor.com` and `domain: www.mississaugamonitor.com`
  — the tenant mapping, written here, no config edit anywhere.
- desk added: **Development, Courts, Peel Region, Live** (four — City
  Hall, Transit, Communities, Business & Markets and Environment
  already exist network-wide and are reused).
- `setting:` lines including `wire_desks` and `automated_byline` — this
  paper launches Hermes-ready.
- **`Done — 18 stories added.`** with zero skips.

Confirm resolution: `php tools/resolve-host.php mississaugamonitor.com`
prints `db      mississauga-monitor` (and the same for `www.`).

## 5 · Verify the live paper

On https://mississaugamonitor.com — all strings as served bytes:

1. `<title>The Mississauga Monitor — Local news. Trusted source.` and
   `<html lang="en">`; source contains `monitor.css` and `t-monitor`.
2. The masthead: the triangle mark beside **The / Mississauga Monitor**,
   nav Home · City Hall · Transit · Development · Courts · Business ·
   Peel Region · Communities · Search, and the orange Subscribe button.
3. The orange breaking bar with the served text `Breaking news` and the
   Highway 401 headline (settings-driven; the newsroom clears it by
   blanking `breaking_label`).
4. The navy hero carries *Mississauga unveils new waterfront vision for
   Lakeview* over the skyline illustration; the Featured story card
   carries the Hurontario LRT story.
5. `/desk/live` is the only page whose HTML contains
   `class="mm-live"` — the orange LIVE badge (served text `Live`,
   uppercased by CSS). `/desk/city-hall`, `/desk/transit`,
   `/desk/development`, `/desk/courts`, `/desk/business`,
   `/desk/peel-region`, `/desk/communities`, `/desk/environment` all
   200 and non-empty.
6. `/story/mm-38-storeys-rathburn-kariya` — the pull quote renders in a
   `<blockquote>` with a `<cite>` naming Councillor Nair.
7. `/search`, `/corrections`, `/feed/`, `/sitemap.xml`, `/newsletter/`
   all 200.
8. Headlines paint in **Playfair Display** and chrome in **Inter** —
   assert `/assets/fonts/inter-latin.woff2` returns 200 and the served
   CSS contains `@font-face` rules naming both; nothing loads from a
   font CDN.
9. The full network loop: every existing domain still 200 as itself,
   English controls still `lang="en"`, bleuetblanc.ca `lang="fr"`.

## 6 · Cron, mail, Hermes

Nothing new in cron (the network fetch covers the two GTA sources). Do
**not** enable the newsletter. The paper is Hermes-ready but no token
exists for it: when the owner says so, issue one with
`php tools/make-agent.php create --name hermes-mississauga
--sites mississauga-monitor --desks live,city-hall,transit` (scope to
taste), store it root-only under `/root/hermes-tokens/`, and never
print it.

## What NOT to do

- No config-file edit — if you find yourself about to edit
  `app/config.site.php`, stop: this launch does not use it.
- No `git pull` in a release; no manual database writes; no
  `default_server`; no touching other papers' blocks or crons.
- The demonstration stories are the newsroom's to replace, not yours
  to improve. A skipped story on the seeder is a full stop, not a
  retry.
