# Taking Civis Media live — deployment handoff

This file walks a deployment operator (human or agent) through putting
**civismedia.ca** live from this repository: the network's ninth deployment,
and the first that isn't a paper. The public face is a quiet
communications-and-advertising shop; `/admin` is the network's **control
room** — every story on every paper, the whole newswire, the roadmap, and
the contact-form inquiries.

Read DEPLOY.md first if you haven't deployed a site of this network before —
the mechanics (release, config, verification discipline) are the same, and
§10 there covers how additional sites join. This file covers only what the
hub does differently.

**Scripted path (the network's VPS):** the whole runbook below is automated
for the existing box in `tools/vps/` — `discover.sh` (read-only server map),
`deploy-civis.sh` (release dir, config copied from the Dispatch's, vhost,
first boot, launch package, certbot, verification), and
`upgrade-papers.sh` (every paper to the branch's current release: one new
release dir per release *group* — a directory serving several vhosts
upgrades once — with the existing config carried **verbatim** as
`app/config.site.php` behind a wrapper that adds `hub_slug` at request
time, vhosts repointed, crons updated, and tenant-aware verification:
every domain must serve 200 with the same front-page title it had before
the change, or its whole group's vhosts and cron files revert — and the
network's **shared uploads** directory, symlinked into every release so
campaign creatives and syndicated images resolve on every domain). One paste each into the VPS terminal; the manual steps below
remain the reference and the path for any other server.

**The one rule stands:** credentials never enter this repository.
`config.php` is gitignored and lives only on the server.

---

## 0 · What you are deploying

- The same release as every paper — **never a fork**. The hub is a site row
  in the shared database (`site_slug = 'civismedia'`) plus one config key
  (`hub_slug`) that turns on the control-room pages for that site only.
- Requires schema **v8** (this release migrates the shared database in
  place on first request — `posts.origin`, `inquiries`, `roadmap_items`).
  Deploy this release to the whole network before or together with the hub:
  the migration is backward-compatible, but only this release knows the
  new tables exist.
- The brochure template (`civis`), brand package, launch settings, and the
  control-room pages are all in the repository under
  `assets/sites/civismedia/`, `app/views/front-civis.php`, and
  `admin/network-*.php` / `admin/roadmap.php` / `admin/inquiries.php`.

## 1 · Decide where it lives

Two equally good options (the open question in the roadmap):

- **Same server as the papers** — the document root is the same release
  directory; civismedia.ca becomes one more host in the `site_slug` map.
  Cheapest, and the shared `uploads/` rule is automatic.
- **Its own server** — a fresh clone of the same release, `config.php`
  pointing at the same Supabase pooler. Remember the shared-`uploads/`
  caveat from DEPLOY.md §10 if stories with images will ever be filed from
  the hub.

DNS: point `civismedia.ca` (and `www`) at the chosen server; TLS from the
host panel or certbot as usual.

## 2 · Config

In `config.php` on the **hub's** deployment:

```php
'site_slug' => match (true) {
    str_contains($_SERVER['HTTP_HOST'] ?? '', 'civismedia') => 'civismedia',
    // …the existing host mappings stay exactly as they are…
    default => 'prairiedispatch',
},

// The control room: which site of the network is the hub.
'hub_slug' => 'civismedia',
```

Then, on **every paper's** `config.php`, add the same one line the next
time you touch each server:

```php
'hub_slug' => 'civismedia',
```

Why: the papers use it too — it keeps the hub out of every *Runs on*
picker and site list, so nobody ever maps a story to the brochure. Nothing
breaks without it; the hub just shows up as a pointless checkbox until
it's added. (`config.example.php` now documents the key.)

## 3 · Server block (nginx)

The §3a pattern from DEPLOY.md, plus the contact route. The protection
rules are identical:

```nginx
server {
    server_name civismedia.ca www.civismedia.ca;
    root /var/www/current;          # the shared release directory
    index index.php;

    location ~ ^/(app|data)/            { deny all; }
    location ~ ^/uploads/.+\.(php|phtml|phar)$ { deny all; }
    location ~ ^/(config\.php|config\.example\.php|router\.php)$ { deny all; }

    rewrite ^/contact/?$              /contact.php           last;
    rewrite ^/api/media/?$            /media-gateway.php     last;
    # The story/desk/feed rewrites from §3a can stay — they resolve to
    # nothing on the hub (no stories are mapped to it) — but the brochure
    # only actually needs /contact.

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location / { try_files $uri $uri/ =404; }
}
```

On Apache, the repository's `.htaccess` already carries the `/contact`
rewrite — nothing to add.

## 4 · First boot joins, then the launch package

1. Request `https://civismedia.ca/` once. The site row and its default
   settings self-provision; the shared database migrates to v8 if this is
   the first v8 release to touch it. **No founding-account step** —
   newsroom accounts are network-wide, so existing admins sign straight
   into `/admin`.
2. Run the launch package, from the web root:

   ```
   PP_SITE=civismedia php tools/seed-launch.php
   ```

   It sets the identity (Civis Media · Communications & advertising), the
   brochure copy (headline, services, approach), and renames the
   auto-provisioned site row. No desks, no sources, no stories — the hub
   is not a paper. Safe to re-run; it never overwrites a saved edit.

## 5 · Verify

- `/` renders the brochure — slate hero, four services, the contact form.
  **No masthead, no desks, no newsletter block, no admin link.**
- `/story/anything` returns **404** (no stories are mapped to the hub).
- Submit the contact form once: it lands on `/?sent=1#contact`, and the row
  appears under **/admin → Inquiries**.
- `/admin/` shows **Control room** in the bar, with **Network desk**,
  **Newswire**, **Inquiries**, and **Roadmap** in the nav; the dashboard
  opens with **The network, this morning** — one row per paper.
- The **Roadmap** page lists the seven phases (Phase 1 in progress) and
  the open questions.
- `https://civismedia.ca/app/bootstrap.php` returns **403**.
- Spot-check one paper's `/admin`: still says **Newsroom**, still has
  **The 6 a.m.**, and has none of the control-room items.

## 6 · Settings to finish, same day

In `/admin → Settings` on the hub:

- **contact_email** — the inbox that gets a copy of every inquiry (and the
  address shown on the brochure). Until it's set, inquiries only land in
  the admin list, which still works.
- **paper_address** — appears in the brochure footer if set.
- SMTP under the mail settings if you want the inquiry copies delivered
  through a real mailbox (same mechanics as a paper's newsletter mail).
- Brochure copy lives in `civis_headline`, `civis_sub`, `civis_services`,
  `civis_approach` — edit in Settings, no release needed.

## 7 · Cron

**None needed for Phase 1.** The papers' existing cron jobs already fetch
the shared wire and flip scheduled stories network-wide. The hub gets its
own jobs when later phases land (`cron/monitor.php`, `cron/agents.php`,
`cron/analytics.php` — see the roadmap).

## 8 · What NOT to do

- Don't map stories to the Civis Media site — the brochure ignores them,
  and the pickers hide it for exactly that reason.
- Don't create a founding account — there is no such step on a joining
  site; the sign-in page is the normal one.
- Don't run `supabase/schema.sql` against the live database — the app
  migrates itself (schema.sql is for empty-database manual setup only).
- Don't skip the `hub_slug` line on the hub's config — without it the
  control room simply doesn't exist and civismedia.ca behaves like an
  empty paper.
- Don't put the Anthropic key or Google credentials anywhere yet — those
  belong to later phases, and to `config.php`/files outside the web root
  when they come.

## 9 · Phase 3 — connecting the research & drafting desk

The desk (Control room → **Research desk**) ships disabled and simply says
"not connected" until the hub gets its key. To turn it on:

1. **Deploy the Phase 3 release to the hub** (`deploy-civis.sh` as usual —
   the new vhost carries `fastcgi_read_timeout 300`, because a draft call
   can legitimately run a couple of minutes).
2. **Add the key to the hub's `config.php`** — one line, on the box only:

   ```php
   'anthropic_api_key' => 'sk-ant-…',
   ```

   The key never enters the database or the repository, and the papers'
   configs don't get it — the desk exists only on the hub.
3. **Model** (optional): Settings → *The research desk* on the hub —
   blank uses `claude-opus-5`.
4. **Disclosure** (optional, per paper): Settings → *The research desk* on
   each paper — blank (default) prints nothing; `1` prints "Prepared with
   AI assistance and reviewed by an editor." under the byline of stories
   the desk helped draft; any other text prints verbatim.

Verify: the desk builds a research brief from a pasted URL; **Draft working
copy** files a draft that opens in the editor with the AI-assisted banner,
`origin = ai`, your byline, and a provenance note leading the body; the
draft appears in the network desk's *AI-assisted* filter; nothing published
changes anywhere. The authorship rule is enforced, not advisory: drafts land
as drafts, and only a person moves them.

## 10 · Phase 4 — the media monitoring desk goes live

One deploy does it: `deploy-civis.sh` as usual. First request migrates the
shared database to **v10** (three additive tables), the new vhost carries
the `/api/monitor` rewrite, and the deploy now installs the hub's hourly
cron (`/etc/cron.d/civismedia`, repointed on every deploy) for the desk's
feed pull and retention pruning. Then:

1. **Generate the ingest token** — hub `/admin → Settings → The monitoring
   desk` → *Generate the token*. Until it exists, `/api/monitor` answers
   503 and nothing can deliver.
2. **Hand the scraping agent its contract.** It POSTs batches (≤200 items)
   of JSON:

   ```
   POST https://civismedia.ca/api/monitor
   Authorization: Bearer <token from Settings>
   Content-Type: application/json

   [{"source": "BC Gov News", "level": "provincial", "region": "okanagan",
     "doc_type": "release", "title": "…", "url": "https://…",
     "summary": "…", "body_excerpt": "…", "published_at": "2026-08-12T14:00:00Z"}]
   ```

   `level` ∈ `federal|provincial|municipal|agency`; `doc_type` ∈
   `release|gazette|order-in-council|hansard|bill|tender|agenda|minutes|
   decision|report|other`. The reply reports `added / duplicates /
   rejected` with a per-item reason for every rejection — unknown
   vocabulary is named, never silently coerced, so the agent's author sees
   exactly what to fix. De-duplication is by URL: re-delivering the same
   batch is always safe.
3. **Add the desk's own feeds** (editors, on the board): government
   newsrooms and wire services mostly publish RSS — polled hourly, *Fetch
   now* for immediately.

Verify: Control room → **Monitoring** shows the board; a captured URL
lands as `new`; a test POST with the token answers `{"ok":true,…}` and the
item appears; flag it and it shows in the dashboard's **monitoring pulse**;
*Start draft* opens the editor with the item's provenance; *Ideas* files
pitches once the research desk's key exists (everything else on the board
works without it).

## 11 · Phase 5 — analytics, Search Console, and the story-gap report

One service account, added as a viewer everywhere, pulled nightly — no
OAuth dance, no third-party dashboard. Setup is once for the network plus
two fields per paper:

1. **Google Cloud, once**: create a project → enable the **Analytics Data
   API** and the **Search Console API** → create a **service account** (no
   roles needed) → create a **JSON key** for it and download it.
2. **Put the key on the hub box, outside every web root** — e.g.:

   ```bash
   mkdir -p /etc/prairiepost && nano /etc/prairiepost/google-sa.json   # paste the JSON
   chown root:www-data /etc/prairiepost/google-sa.json && chmod 640 /etc/prairiepost/google-sa.json
   ```

   then add to the hub's `config.php`:

   ```php
   'google_sa_json' => '/etc/prairiepost/google-sa.json',
   ```

   (Carried forward automatically on future deploys, like the Anthropic key.)
3. **Grant the account access, once per property.** Its email (shown on
   the hub's Analytics page once the key is in) goes in as **Viewer** on
   each paper's GA4 property and **Restricted** user on each Search
   Console property.
4. **Two fields per paper**, in that paper's Settings: `ga4_property_id`
   (the numeric id) and `gsc_site_url` (e.g. `sc-domain:kelownacurrent.ca`).
5. Deploy (`deploy-civis.sh`) — migration v11 runs on first request, and
   the hub cron file now carries the nightly analytics pull (02:43).
   First pull backfills a month; nights re-pull a trailing window, so the
   two-day Search Console lag fills itself in.

Control room → **Analytics**: the network rollup, each paper's traffic,
acquisition mix, top stories (GA views beside our own read counter), top
queries — and the **story-gap report**: almost-ranking queries, rising
queries, uncovered demand, unused wire heat, and monitoring clusters,
with an optional button that turns the gaps into pitches on the ideas
docket once the research desk's key exists.

Phase 5 also settles the **canonical policy**: in the story editor's
*Runs on* picker, a widely-syndicated story can mark one ticked paper
**home** — the other copies then emit `rel=canonical` to the home paper,
so one paper accrues the search ranking. Default unchanged: no home
paper, every copy self-canonical.

## 12 · Phase 6 — the agent control room

One deploy: migration v12 runs on first request, and the hub's cron file
gains the runner (`cron/agents.php`, every ten minutes). Then:

1. **Seed the entity directory** (once, on the box) — Canada's elected
   officials from Open North's free Represent API:

   ```bash
   cd /var/www/prairiepost-<current-release> && PP_SITE=civismedia php tools/import-represent.php
   ```

   Default sets: House of Commons plus the Alberta, B.C. and Ontario
   legislatures; name any other [Represent set](https://represent.opennorth.ca/)
   as arguments. Re-running never overwrites — curation always wins.
   Then curate under `/admin → Entities`: fix bio URLs, add aliases,
   pause anything that shouldn't link.
2. **How work flows.** Editors queue passes from a story's editor
   (*Agents* panel), in bulk from the network desk, or by story id on the
   agent desk; the runner claims tasks with a guarded update (overlapping
   runs can't double-execute) and parks every result in **needs review**.
   On the desk an editor sees exactly what would change — the linkifier
   shows the story with proposed anchors outlined — and approves (applied
   through the standard sanitizer, stamped with their name) or rejects
   with a note. Stale protection: a linkify proposal built before an edit
   refuses to apply.
3. **Auto-queue at publish** (optional): hub Settings → *The agent desk* —
   per-kind checkboxes, all off by default. Auto-queue only files the
   task; auto-<em>apply</em> deliberately doesn't exist.
4. The linkifier needs no AI. The SEO meta writer and tagger call Claude,
   so they report "not connected" until the research desk's key is in.

Verify: queue the linkifier on a story naming a seeded politician → *Run
queued now* → the proposal shows the outlined anchor → approve → the
story body carries the link; the dashboard rail counts open proposals.

## 13 · Phase 7 — hardening: two-step sign-in, throttling, audit trail

One deploy: migration v13 runs on first request (TOTP columns on users,
`login_attempts`, `audit_log`). No new cron, no vhost change — the hourly
monitor pass now also prunes the two ledgers (attempts after 30 days,
audit after ~13 months).

**Heads-up before you deploy:** two-step is **required for hub
administrators by default**. On their next sign-in to civismedia.ca they
land on their profile page and nothing else opens until they enrol —
a funnel, not a lockout. Have an authenticator app ready (Google
Authenticator, Aegis, 1Password, Authy — anything TOTP). Papers are not
funnelled; anyone can still enable two-step voluntarily from their
profile.

1. **Enrol** (each hub admin): profile page → *Set up two-step sign-in*
   → enter the shown key in the app (no QR on purpose — no third-party
   code at the sign-in path) → type the six-digit code to confirm.
   The secret only saves once a working code proves the app holds it.
2. **Sign-in flow from then on:** email + passphrase, then the code.
   Five minutes to complete the second step, one 30-second period of
   clock drift tolerated either side.
3. **Throttle** (all sites, automatic): 6 failed tries per account or
   20 per address inside 15 minutes → "wait fifteen minutes." Successes
   never count; the window simply expires. Failures at the code step
   count too, so codes can't be brute-forced.
4. **Audit trail** (hub, admins): `/admin → Audit`. Sign-ins, settings
   saves, token rotations, campaign changes, syndication moves, agent
   approvals/rejections, account and entity changes, two-step on/off —
   who, what, when, from which address. Append-only by design; there is
   deliberately no delete button.
5. **Address allowlist** (optional, off by default): hub Settings →
   *Security*. One IP or CIDR per line; applies to signed-in hub pages
   only, never the papers. The form refuses a list that doesn't cover
   the address you're saving from, so you can't lock yourself out from
   the form. If a stale list ever locks the control room after your IP
   changes, clear it from the box:

   ```bash
   cd /var/www/prairiepost-<current-release> \
     && PP_SITE=civismedia php -r 'require "app/bootstrap.php"; set_setting("admin_ip_allowlist", "");'
   ```
6. **Turning the requirement off** (not recommended): hub Settings →
   *Security* → untick *Require two-step sign-in*.
7. **Lost authenticator:** another hub admin clears the flag from the
   box, then the person re-enrols:

   ```bash
   cd /var/www/prairiepost-<current-release> \
     && PP_SITE=civismedia php -r 'require "app/bootstrap.php";
        db()->prepare("UPDATE users SET totp_secret = \"\", totp_enabled = 0 WHERE email = ?")->execute(["person@example.com"]);'
   ```

Verify: sign out and back in → funnel to profile → enrol with a real
app → sign out → email + passphrase + code gets you in; wrong passphrase
six times on a dummy email shows the wait message; `/admin → Audit`
carries the whole story.

## 14 · Phase 8 — resilience: backups, the watch, revisions, the edge

Four independent pieces. The hub deploy carries the app side (migration
v14: revision history, session lifecycle, the ops ledger); two new
scripts set up backups and the edge kit; the papers upgrade closes the
version gap. Everyone gets signed out once when v14 lands (sessions gain
an epoch) — one re-login, no data touched.

1. **Deploy the hub** as always. The cron file now routes every job
   through `cron/run.php` (outcomes land on the dashboard's Operations
   panel and `/admin → Ops`) and adds the five-minute **watch**: nine
   domains, certificate expiry, cron staleness, wire freshness, backup
   age, sign-in pressure. Set the alert email under Settings → Security
   (uses the Newsletter page's SMTP details; without SMTP the dashboard
   still shows everything).
2. **Backups** (once):

   ```bash
   curl -fsSL https://raw.githubusercontent.com/FTFNAnalytics/prairiepost/claude/master-dashboard-control-room-nr3mp4/tools/vps/setup-backups.sh -o /root/setup-backups.sh
   less /root/setup-backups.sh && bash /root/setup-backups.sh
   ```

   Nightly at 03:17: `pg_dump` of the shared database, the shared
   uploads, and box state (vhosts/crons/configs) into
   `/var/backups/civis/` — 7 daily + 4 weekly, first run immediate.
   Off-site: drop an executable at `/etc/civis-backup-offsite` (e.g. two
   lines of rclone) and it receives each night's files. **Still on this
   box until you do** — an off-site target is the remaining step.
3. **Rehearse the restore** (after setup, then ~monthly):

   ```bash
   curl -fsSL https://raw.githubusercontent.com/FTFNAnalytics/prairiepost/claude/master-dashboard-control-room-nr3mp4/tools/vps/restore-drill.sh -o /root/restore-drill.sh
   less /root/restore-drill.sh && bash /root/restore-drill.sh
   ```

   Restores the newest dump into a scratch database on a **local**
   Postgres (installed on first run, localhost-only), asserts sites/
   users/posts/entities survive, prints the newest story, drops the
   scratch. The live database is never involved.
4. **The edge kit** (once; hub deploys re-apply it to the hub
   automatically afterwards):

   ```bash
   curl -fsSL https://raw.githubusercontent.com/FTFNAnalytics/prairiepost/claude/master-dashboard-control-room-nr3mp4/tools/vps/harden-edge.sh -o /root/harden-edge.sh
   less /root/harden-edge.sh && bash /root/harden-edge.sh
   ```

   Every prairiepost vhost gains security headers (HSTS, nosniff,
   frame, referrer, permissions), a per-IP request limit (30 r/s,
   burst 120 → 429), and a 60-second microcache for anonymous readers —
   a traffic spike is served from memory instead of PHP + the database.
   Signed-in sessions and admin/cron/api/ad paths always bypass it.
   Check it: `curl -sI https://prairiedispatch.ca/ | grep -i x-pp-cache`
   twice — MISS then HIT. Ad impressions now count via a footer beacon
   (`/ad?imp=…`, never cached), so cached views still count; clicks were
   always beacon-style.
5. **Upgrade the papers** (the same approved script) so all nine sites
   share this release — that also brings the papers the Phase 7
   sign-in protections and the lazy sessions the microcache relies on.
   Until then paper pages mostly say `X-PP-Cache: BYPASS` (their older
   release still opens a session on every request) — expected, not a
   fault.
6. **What changed for the newsroom:** every story now keeps a capped
   history — the editor shows it under **History**, any revision can be
   read in full and restored (the restore is itself a revision).
   Corrections work as before (they've been there since Phase 1);
   history is the new part. Profiles gain **Sign out everywhere else**;
   a passphrase change now signs out every other session automatically;
   Accounts gains a per-person **Sign out** (revoke) button. Sessions
   idle out after 12 h and expire 14 days after sign-in (Settings →
   Security to tune; 0 disables).

Verify after deploy: dashboard shows the Operations panel (the watch
reports within five minutes); `/admin → Ops` lists runs; edit a story
twice → History shows both, open one → Restore puts it back;
`php tests/run.php` passes on the release; two curls to a paper front
page read MISS then HIT.

## Troubleshooting quick table

| Symptom | Cause → fix |
|---|---|
| Front page renders a newspaper, not the brochure | The site resolved to a paper slug — check the host map in `config.php`, and that `assets/sites/civismedia/palette.json` deployed (it selects the `civis` template) |
| `/admin` says Newsroom, no control-room items | `hub_slug` missing or misspelled in the hub's `config.php` |
| Contact form always bounces back with `err=rate` | More than five posts from one IP in an hour — that's the rate limit doing its job; wait, or check for a proxy collapsing all visitors to one IP |
| Inquiry stored but no email copy | `contact_email` unset, or SMTP not configured — the row in /admin → Inquiries is the source of truth either way |
| Papers suddenly show a "Civis Media" checkbox in Runs on | That paper's `config.php` doesn't have `hub_slug` yet — add the one line (§2) |
| Hub admin stuck on the profile page after deploy | That's Phase 7's enrolment funnel — set up two-step there and everything unlocks (§13) |
| "Too many attempts. Wait fifteen minutes" | The sign-in throttle — 6 failures per account / 20 per address in 15 min. It expires on its own; check `/admin → Audit` and the `login_attempts` table if it wasn't you |
| Control room answers 403 "limited to approved addresses" | The IP allowlist no longer covers you (address changed?) — clear it from the box (§13.5) |
| Everyone got signed out after the Phase 8 deploy | Expected, once — sessions gained an epoch in v14; sign in again |
| Dashboard says "backup FAILED" or "backups not set up" | Read `/var/log/civis/backup.log`, re-run `/usr/local/bin/civis-backup.sh`; "not set up" means run setup-backups.sh (§14.2) |
| Front pages always say X-PP-Cache: BYPASS | The browser holds a `ppsession` cookie (sign out or use a private window), or that paper hasn't been upgraded to the lazy-session release yet (§14.5) |
| A reader reports a page 429 | The per-IP limit (30 r/s, burst 120) — real readers never hit it; a proxy collapsing many readers to one IP might. Raise the rate in `/etc/nginx/conf.d/civis-edge-zones.conf` |
| An edit went wrong and Save made it worse | Story editor → History → open the last good revision → Restore. Nothing is ever lost — the bad save stays in history too |
