# Civis Media — the master control room

civismedia.ca is the ninth deployment of this codebase and the first that
isn't a newspaper. Its public face is a quiet communications-and-advertising
shop. Its `/admin` is the network's master control room: every story, the
whole wire, advertising across all eight papers, Google Analytics and Search
Console reporting, AI-assisted drafting, and a queue of agent tasks that
propose changes for a human to approve.

This document describes the design, the build plan in phases, and the
decisions behind them. Nothing here forks the codebase — the hub is the same
release, the same shared database, the same deployment pattern as every
paper before it.

Alongside the newsroom tools, the control room carries a **media monitoring
desk**: government publications streamed in from an external scraping agent,
press releases captured by feed or by hand, all of it triaged by region and
jurisdiction, with story ideas generated on demand — so journalists see
what's surfacing across the network's territory from one screen.

---

## 1 · What the network already gives us

The control room is mostly **a new lens over data that is already shared**,
which is why this is a series of focused phases rather than a rebuild:

| Requirement | Already in place |
|---|---|
| "Master author files once, assigns to 3 of 8 sites" | **Built.** `posts` are network-global; `post_sites` maps a story to the papers it runs on; the editor's *Runs on* checkboxes drive it (`set_post_sites()` in `app/models.php`). The hub makes this a first-class network view, not a new data model. |
| One sign-in across all sites | **Built.** `users` are network-global; an admin already signs into any paper's `/admin`. |
| Overarching newswire | **Built.** `sources` and `news_items` are shared; every site's morning pull reads the same pool. The hub shows all regions side by side. |
| Ads with real numbers | **Built per-site.** The `ads` table is already keyed by `site_id` with slots, scheduling, impression/click counters. What's missing is creating and reporting on them *across* sites — Phase 2. |
| RSS → article | **Built.** *Start draft* and *Post a link* turn wire items into drafts. The research & drafting desk (Phase 3) is a third door into the same flow. |

New subsystems the network does **not** have yet: the brochure front,
network ad campaigns, the research & drafting desk, the media monitoring
desk, Google Analytics / Search Console ingestion and reporting, and the
agent task queue. Those are Phases 1–6.

## 2 · Architecture decisions

**The hub is a site row, not a special server.** `site_slug =>
'civismedia'` in `config.php` (or the host map), first boot self-provisions
the row exactly like every paper. Same release directory, same schema, same
cron pattern.

**Hub-ness is keyed to the slug, not the deployment.** A new config key:

```php
'hub_slug' => 'civismedia',   // which site of the network is the control room
```

and a helper `pp_is_hub(): bool` (`current_site()['slug'] ===
pp_config('hub_slug')`). Keying on the slug — not a boolean — means the
domain-mapped single-directory deployment (DEPLOY.md §10) stays correct:
edmontonecho.ca and civismedia.ca can share a release directory and only the
civismedia host gets the control room. CLI crons select it with
`PP_SITE=civismedia`.

**Control-room pages are gated twice**: `pp_is_hub()` (page exists only on
the hub) **and** role. Network content tools (stories, newswire, AI drafts,
agent review) require editor; network advertising, analytics configuration,
entities, and settings require admin. A paper's `/admin` is unchanged.

**Secrets live in `config.php` and files outside the repo, never the
database.** The Anthropic API key and the Google service-account JSON exist
only on the hub server. `config.php` is already gitignored; the SA JSON
goes outside the web root with its path in config. No OAuth flows, no
tokens in Postgres.

**Every agent and AI write is a proposal until a human approves it.**
AI-created drafts enter the existing review queue and can never be born
`published`. Agent tasks produce a diff that an editor approves or rejects
in the control room. The same server-side rule that stops an author from
publishing stops the machines.

**AI assists; a journalist is always the author.** The AI tools gather,
summarize, and draft — a person reworks the material, signs it, and answers
for it. Bylines are always a human's: an AI-assisted story carries the
journalist who finished it, with `posts.origin = 'ai'` kept as internal
provenance and the public disclosure line an optional, per-site choice.

## 3 · The public face — civismedia.ca

A generic, confident communications shop: services (brand and identity,
web builds, campaign management, media buying), a short capabilities
statement, a contact form. One page, its own look — **not** the newspaper
chrome.

- **Template.** A `civis` chrome template rendered as a self-contained page.
  Unlike the paper templates, it's checked in `index.php` *before*
  `page_header()` — no masthead, no desk nav, no newsletter block, no
  reader footer. It ships its own CSS the way each front does.
- **Brand.** `assets/sites/civismedia/` via `tools/make-brand.py` —
  its own ink and paper, `palette.json`, `brand.css`, favicon. Corporate,
  not editorial.
- **Content is settings-driven** (headline, services JSON, contact email),
  so copy changes never need a release.
- **Contact form** posts to a new `contact.php` (rewrite `/contact`):
  honeypot + per-IP rate limit, rows into a new `inquiries` table, optional
  mail notification through the existing `app/mailer.php`. A small hub admin
  page lists them.
- **No admin link anywhere on the page** — standard practice; you don't link
  your CMS from your storefront. Admin pages already carry
  `noindex,nofollow`; add `Disallow: /admin/` to the hub's robots response.
- **Isolation is free.** Public story queries join through `post_sites`
  scoped to the current site (`pp_site_join()`), and no stories are mapped
  to the hub — `/story/…` 404s on civismedia.ca by construction. Gate
  `/feed/` and `sitemap.xml` to a minimal brochure sitemap.

## 4 · The build, in phases

Each phase is a deployable release with its own schema migration, in the
same versioned-migration style as `pp_migrate()`. Sizes are relative to one
of the site launches already in the history (Kelowna, Brampton ≈ one build
session each).

**Progress is tracked in the control room, not here.** Phase 1 ships a
Roadmap page (`/admin → Roadmap` on the hub) seeded from this plan — the
database copy is the living document: phase statuses, open questions and
their answers all live there. This file stays the design record.

### Phase 1 — Join the network + control room shell (≈ 1–2 sessions)

The hub goes live and immediately makes the existing network easier to run.

- Brand package, `civis` template, `contact.php`, `DEPLOY-CIVIS.md` runbook
  (same shape as the other deploy docs: DNS, server block, host-map entry,
  `hub_slug`, verification list).
- `pp_is_hub()` + hub admin navigation (the control-room items appear only
  on the hub).
- **Network desk** (`admin/network-posts.php`): every story across the
  network with site chips showing where it runs, filters by site / status /
  desk / origin, and **bulk Runs on** — tick stories, tick papers, apply
  (`set_post_sites()` already does the write).
- **Network newswire** (`admin/network-wire.php`): the whole `news_items`
  pool, all region tabs in one place, with *Start draft* and *Post link*
  carried over.
- **Network health panel** on the hub dashboard: per-site published counts
  and last-publish time, wire source failures (`sources.last_status`),
  newsletter send status per site, review-queue depth. All of it is a query
  away today; nobody can see it in one place.
- **The roadmap** (`admin/roadmap.php`): this plan's phases and open
  questions as a living, database-backed document — every newsroom role
  reads it, editors update statuses and answer questions in place.
- **Inquiries** (`admin/inquiries.php`): what the brochure's contact form
  brought in — new first, mark handled, optional mail copy to
  `contact_email`.
- **Migration v8**: `posts.origin VARCHAR(20) DEFAULT ''` (`''` newsroom ·
  `wire` · `ai` — provenance for filters, reporting, and disclosure), the
  `inquiries` table, and `roadmap_items`.

### Phase 2 — Network advertising (≈ 1 session)

Create a creative once, run it on any subset of papers, read the numbers in
one place — without touching the serving path that already works.

- **Migration v9**: `campaigns` table (`id, name, advertiser, notes,
  created_at`) and `ads.campaign_id INTEGER` (nullable).
- **Fan-out model.** A campaign on the hub takes one creative (house /
  image / embed — the existing three kinds), a slot, a schedule, and a set
  of papers; saving writes **one `ads` row per selected site**, stamped
  with `campaign_id`. Per-site serving (`ad_for_placement()`), rotation,
  and counters are untouched. Editing the campaign updates its rows;
  pausing pauses them all.
- **Campaign reporting**: served / clicks / CTR aggregated by campaign and
  broken out per site — the numbers are already real because clicks route
  through the counter.
- Per-paper ad pages still work for local sales; rows carrying a
  `campaign_id` are labelled *Network campaign — managed from the control
  room* and read-only locally, so a local editor can't half-edit a network
  buy.

### Phase 3 — The research & drafting desk (≈ 1 session)

AI as a research assistant and first-draft aide — never the final author.
A journalist starts here, works the material, and signs the piece.

- **`admin/ai-draft.php` (hub, editors and authors).** Three modes:
  - **Research brief** — from a topic or a set of URLs (wire items,
    monitoring items once Phase 4 lands): a background summary, timeline,
    key figures and quotes with source links, and open questions. No
    article copy at all — raw material for a journalist.
  - **Draft from the wire** — one click from the newswire: headline,
    summary, and the fetched source page as grounding produce working
    copy the journalist rewrites.
  - **Draft from a brief** — a freeform assignment (topic, angle,
    optional URLs).
- **The call.** Claude API, `claude-opus-5` by default (a `settings`
  override exists for the model id), **structured output** via
  `output_config.format` returning `{title, dateline, lede, body_html,
  meta_description, suggested_tags, suggested_desk}` — no fragile parsing.
  Non-streaming with a generous timeout is fine at article length; handle
  `stop_reason === 'refusal'` by surfacing a plain error. The prompt
  requires original wording from the facts, attribution of the source
  outlet by name, and returns thin-source cases as a recommendation to run
  it as a wire link instead.
- **Client choice.** The stack is deliberately Composer-free, so the
  integration is a small raw-HTTP client (`app/ai.php`, ~100 lines on the
  existing curl helper). If we ever accept Composer on the hub only, the
  official `anthropic-ai/sdk` is the drop-in upgrade; the call site is one
  function either way.
- **Guardrails — the authorship policy, enforced.** Drafts land as
  `status = 'draft'`, `origin = 'ai'`, `source_url` set, byline and
  authorship belonging to the journalist who requested it — then the
  normal flow: rework, submit, review, publish, *Runs on*. The AI path
  cannot publish, and nothing ships that a person hasn't rewritten and
  signed. An `ai_disclosure` setting (off by default) can print "Prepared
  with AI assistance and reviewed by an editor" on `origin = 'ai'`
  stories.
- `anthropic_api_key` in the hub's `config.php` only.

### Phase 4 — The media monitoring desk (≈ 1–2 sessions)

One screen where journalists watch what's surfacing across regions and
jurisdictions: government publications from your scraping agent, press
releases captured by feed or by hand, and the story ideas they generate.

- **Migration v10**:
  - `monitor_items (id, feed_id, source_name, level, region, doc_type,
    title, url, url_hash UNIQUE, summary, body_excerpt, published_at,
    fetched_at, status, flagged_by, claimed_by)` — `level` is the
    jurisdiction (`federal · provincial · municipal · agency`), `region`
    reuses the network's existing region keys, `doc_type` is a small
    vocabulary (`release · gazette · order-in-council · hansard · bill ·
    tender · agenda · minutes · decision · report · other`), and `status`
    runs `new → flagged | claimed | used | dismissed`.
  - `monitor_feeds (id, name, url, level, region, doc_type, enabled,
    last_fetched_at, last_status)` — the monitoring desk's own feed list,
    kept separate from the newspaper wire so releases never pollute the
    morning pull.
  - `story_ideas (id, monitor_item_id, site_id, title, angle, rationale,
    region, origin ('ai'|'newsroom'), status ('open'|'claimed'|
    'dismissed'), claimed_by, created_by, created_at)`.
- **The ingest API — the contract with your scraping agent.** Your
  government-publications agent already exists and stays external; it
  delivers through `POST /api/monitor` (a `monitor-ingest.php` endpoint,
  rewritten) with a bearer token from the hub's settings. Body: a JSON
  array of items `{source, level, region, doc_type, title, url, summary,
  body_excerpt?, published_at?}`; the endpoint validates, de-duplicates on
  `url_hash` (the same sha1 discipline as `news_items`), and answers with
  added/duplicate counts. An HTTP contract keeps database credentials out
  of the scraper and validation in one place — the agent never touches
  Postgres.
- **Press-release capture**, two doors:
  - `monitor_feeds` RSS/Atom polling by a new `cron/monitor.php` (hub,
    hourly) — government newsrooms and wire services mostly publish
    feeds, and `http_get()` / `parse_feed()` already exist;
  - **Capture a release** — paste a URL, and the Post-a-link scraper
    (Open Graph title, summary, outlet) prefills a `monitor_items` row
    with `doc_type = 'release'` for anything that arrives outside a feed.
- **The board** (`admin/monitor.php`, hub, all newsroom roles): filter by
  level, region, doc type, source, and status; triage with *Flag*,
  *Dismiss*, *Claim*; act with *Start draft* (prefilled, exactly like the
  wire), *Research brief* (Phase 3's mode, grounded on the item), and
  *Story ideas*.
- **The pulse.** A strip on the hub dashboard: new-item counts by region ×
  jurisdiction for the last seven days plus the latest flagged items —
  "what's popping up where" at a glance, before anyone opens the board.
- **Story ideas.** From one item or a selected cluster, one Claude call
  proposes three to five pitches — headline, angle, why now, suggested
  paper and desk — written to `story_ideas` as `origin = 'ai'`.
  Journalists file their own ideas into the same list by hand. Claiming
  an idea links straight into *Start draft*; the ideas list filters by
  site and region so each paper sees its own docket. Same rule as
  everywhere: ideas are suggestions, never auto-filed stories.
- **Watchlists.** A per-user keyword list (settings JSON); matching new
  items are highlighted on the board and, optionally, delivered as a
  morning email digest through the existing `app/mailer.php`.
- **Retention.** Dismissed and untouched items prune after a configurable
  window (default six months); flagged and used items keep — they're the
  paper trail behind published stories.

Your scraper can stay external forever — the API is the contract. If it
ever moves in-house, it becomes one more task kind on Phase 6's agent
rails without changing how items display.

### Phase 5 — Analytics, Search Console, and the story-gap report (≈ 2 sessions)

One Google Cloud service account, added as a viewer everywhere, pulled
nightly into our own tables — no OAuth dance, no third-party dashboard.

- **Tagging.** Already wired: `page_header()` emits the per-site
  `analytics_code` setting verbatim — this phase just standardizes what
  goes in it (each paper's GA4 gtag snippet).
- **Access, once per site**: create one GCP project, enable the Analytics
  Data API and Search Console API, create a service account; add its email
  as **Viewer** on each GA4 property and as a **Restricted** user on each
  Search Console property. Per-site settings: `ga4_property_id`,
  `gsc_site_url` (e.g. `sc-domain:kelownacurrent.ca`).
- **Auth in plain PHP**: RS256-signed JWT (`openssl_sign`) exchanged for a
  one-hour token — scopes `analytics.readonly` + `webmasters.readonly`.
  SA JSON path in config, outside the web root.
- **`cron/analytics.php`** (hub, nightly): for every configured site pull
  GA4 `runReport` (sessions, users, pageviews, engagement; by date, by
  channel group, top pages) and GSC `searchAnalytics/query` (by query and
  by page — GSC lags ~2 days; the cron re-pulls a trailing window).
- **Migration v11**: `site_metrics_daily (site_id, day, sessions, users,
  pageviews, engaged_sessions, engagement_secs, channels_json,
  top_pages_json)` and `gsc_daily (site_id, day, dim ('query'|'page'),
  key, clicks, impressions, position)`, pruned to 16 months (GSC's own
  retention).
- **Reporting** (`admin/analytics.php`): network rollup and per-site pages —
  traffic trend, acquisition mix, top stories with GA pageviews joined to
  our own `posts.views`, top queries with click-through and position.
- **The story-gap report** — the "suggest new articles" engine, heuristics
  first, no AI required:
  - *Almost ranking*: queries at position 8–25 with meaningful impressions
    → a nudge ("a stronger or fresher story wins this page-one slot");
  - *Rising queries*: week-over-week impression growth;
  - *Uncovered demand*: queries with no matching published story on that
    site (matched against titles and tags);
  - *Wire heat*: topics recurring across the shared wire pool that a given
    paper hasn't touched;
  - *Monitoring heat*: clusters of Phase 4 monitoring items in a region —
    a jurisdiction generating paper that no paper has covered yet.
  - Optionally, one Claude call turns a site's top gaps + recent wire into
    five concrete pitches (headline, angle, suggested desk) — displayed
    beside the numbers, never auto-filed.
- **Canonical strategy** (SEO housekeeping this phase should settle): a
  story on several domains is duplicate content to Google. Add
  `posts.canonical_site_id` (nullable): unset keeps today's self-canonical
  behaviour; set, the other papers emit `rel=canonical` to the home paper.
  Exposed as a radio in the *Runs on* picker. Recommended for
  wide-syndication stories so one paper accrues the ranking.

### Phase 6 — The agent control room (≈ 2 sessions)

A task queue where agents do the tedious passes and editors keep the pen.

- **Migration v12**: `agent_tasks (id, kind, status, post_id, site_id,
  payload, result, log, created_by, created_at, started_at, finished_at,
  reviewed_by, reviewed_at)` — statuses `queued → running →
  needs_review → approved | rejected | failed` — and `entities (id, name,
  slug, kind, url, aliases, enabled, created_at)`.
- **Runner**: `cron/agents.php` (hub, every 10–15 min) claims queued tasks
  (guarded `UPDATE … WHERE status='queued'`, so overlapping runs can't
  double-execute), dispatches to a handler per `kind` in `app/agents/`,
  writes the proposal into `result`, sets `needs_review`.
- **Review UI** (`admin/agents.php`): the queue, and per task a diff view —
  for the linkifier, the article with proposed anchors highlighted.
  Approve applies the change through the existing sanitizer and stamps who
  approved; reject takes a note. Per-kind auto-apply is a deliberate later
  switch, off by default.
- **First three agents**:
  1. **Linkifier** — your worked example. Scans body text for `entities`
     (word-boundary, longest alias first, skips existing links and
     headlines, one link per entity per story) and proposes anchors to the
     bio URL. The entity directory is admin-curated with a seeding tool:
     `tools/import-represent.php` pulls Canadian elected officials (MPs,
     MLAs, councillors) from Open North's free Represent API into
     `entities` — names, districts, profile URLs — so the directory starts
     full.
  2. **SEO meta writer** — fills empty `meta_description` (one Claude call,
     ≤155 chars, from title + lede).
  3. **Tagger** — proposes 3–6 tags from the network's existing tag
     vocabulary so tag pages consolidate instead of fragmenting.
  - Next wave, same rails: internal related-link suggestions between
    network stories, image alt-text writer, corrections-consistency scan,
    and additional monitors feeding the Phase 4 media desk.
- Tasks are enqueued from story pages ("Run linkifier"), in bulk from the
  network desk, or automatically on publish (a per-kind setting).

### Phase 7 — Hardening the keys to eight papers (≈ 1 session)

The hub concentrates power, so it gets locks the papers never needed.

- **TOTP two-factor** for admin-role accounts (pure PHP: base32 secret +
  HMAC-SHA1, manual-entry key + `otpauth://` URI — no dependencies),
  enforced on the hub, optional elsewhere.
- **Login rate limiting** (per-account and per-IP, table-backed) — today
  sign-in is unthrottled.
- **Audit log**: one row per control-room write — who assigned what where,
  ad changes, agent approvals, settings edits. Cheap now, invaluable the
  first time two admins disagree about what happened.
- Optional IP allowlist setting for the hub's `/admin`.
- Deferred but designed-for: per-site role scoping (`user_sites`) if
  freelancers ever need access to one paper only. Today every account is
  network-wide, which is correct for the current team size.

## 5 · Operational notes

- **Crons.** The papers keep their one job each. The hub adds
  `PP_SITE=civismedia php cron/monitor.php` (hourly),
  `PP_SITE=civismedia php cron/agents.php` (10–15 min) and
  `PP_SITE=civismedia php cron/analytics.php` (nightly). The analytics
  cron iterates all sites regardless of which site invokes it; the
  monitor cron polls `monitor_feeds` — your external scraper needs no
  cron here at all, it just POSTs to the ingest API.
- **Uploads.** AI drafts and campaign creatives are written from the hub;
  the shared-`uploads/` rule from DEPLOY.md §10 (one directory, symlinked
  or shared) is now load-bearing — images referenced by syndicated stories
  must resolve on every domain.
- **Database.** All new tables live in the same `prairiedispatch` schema
  and install through `pp_migrate()` — first deploy of each phase upgrades
  in place, same as every migration so far. Two tables have real growth,
  both with prune policies: `gsc_daily` (16 months, indexed
  `(site_id, day, dim)`) and `monitor_items` (dismissed/untouched items
  after six months, indexed `(level, region, fetched_at)`).
- **Backups.** Supabase PITR was a launch-day item in DEPLOY.md §8; the
  hub makes the single database more valuable — verify it's actually on.

## 6 · Suggestions beyond the brief

- **Let the storefront sell the network.** The brochure needs credible
  services; the network *is* one. An "Advertise" page offering placements
  across eight community papers — with a rate card and (post-Phase 5) real
  aggregated audience numbers — turns the front from camouflage into the
  sales channel for Phase 2's campaign inventory, and gives inquiries a
  reason to exist.
- **The imprint line — decided: optional.** "A Civis Media publication"
  ships as a per-site setting, **off by default**; each paper turns it on
  only when you choose. The case for eventually flipping it on stands —
  it's what Google News eligibility reviews and wary advertisers look
  for, and it makes the network ad-sales story legitimate — but nothing
  in the build assumes it. (Independent of not linking the admin: this is
  paper → parent, not parent → papers.)
- **AI disclosure as policy, not garnish.** The `origin` column (Phase 1)
  plus the disclosure setting (Phase 3) means the network can state a
  clear policy — AI assists research and drafting, a journalist authors
  and answers for every story, and labels appear where you choose. Cheap
  now, awkward to retrofit after publication.
- **Duplicate-coverage nudge.** When two papers are about to run
  near-identical stories on the same wire item (same `source_url`), show
  it in the network desk. The data already exists; it's a query and a
  chip.
- **Editorial calendar view.** Scheduled + drafted stories across all
  sites by day, on the hub dashboard — `status = 'scheduled'` already
  holds the dates; this is presentation only.
- **What not to build:** a separate hub codebase (the whole point is one
  release), a real ad server (rotation + counters already exist; an
  iframe/VAST stack is a different business), OAuth-based Google access
  (service account is strictly simpler on shared hosting), or autonomous
  publishing (the review queue is the product's integrity).

## 7 · Decisions to date

- **Imprint line**: built as an optional per-site setting, off by default.
- **AI's role**: research and drafting aide only — a journalist reworks,
  signs, and answers for every story; bylines are always a person's.
- **The scraping agent connects later.** The media desk ships with the
  ingest API as its contract from day one; the external agent plugs in
  whenever it's ready, with no schema or code change on our side.
- **The roadmap lives in the control room.** Seeded from this plan into
  `roadmap_items`; the database copy is the source of truth for progress
  and open questions from Phase 1 on.

## 8 · Open questions

1. **Hosting** — does civismedia.ca land on the existing server (host-map
   entry in the shared release) or its own? Either works; the runbook will
   match.
2. **Canonical policy** — adopt home-paper canonicals for widely-syndicated
   stories (Phase 5), or keep every paper self-canonical?
3. **The scraper's contract** — one sample payload from your
   government-publications agent (its fields, and how it names
   jurisdictions and regions) and `/api/monitor` will be built to match
   it, rather than forcing the agent to change shape.
4. **Email-in for releases** — press releases arrive by email constantly.
   Want a monitored mailbox (IMAP poll into `monitor_items`) inside
   Phase 4, or is feed + paste-a-URL capture enough to start?
5. **Phase order** — advertising before the research desk is the
   revenue-first ordering. The media desk's board and ingest API need only
   Phase 1, so Phase 4 can jump the queue if newsroom value matters more
   right now — its *Research brief* and *Story ideas* buttons simply light
   up once Phase 3's Claude client lands.
