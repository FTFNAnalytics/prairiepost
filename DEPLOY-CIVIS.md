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

## Troubleshooting quick table

| Symptom | Cause → fix |
|---|---|
| Front page renders a newspaper, not the brochure | The site resolved to a paper slug — check the host map in `config.php`, and that `assets/sites/civismedia/palette.json` deployed (it selects the `civis` template) |
| `/admin` says Newsroom, no control-room items | `hub_slug` missing or misspelled in the hub's `config.php` |
| Contact form always bounces back with `err=rate` | More than five posts from one IP in an hour — that's the rate limit doing its job; wait, or check for a proxy collapsing all visitors to one IP |
| Inquiry stored but no email copy | `contact_email` unset, or SMTP not configured — the row in /admin → Inquiries is the source of truth either way |
| Papers suddenly show a "Civis Media" checkbox in Runs on | That paper's `config.php` doesn't have `hub_slug` yet — add the one line (§2) |
