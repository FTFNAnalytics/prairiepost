# Claude notes — the Prairie Dispatch network

One codebase serves the whole network (twenty-five live tenants —
twenty-four papers and the CivisMedia hub — as of the Brandon
Bulletin). Each paper
is a tenant mapping in the server-only config, a
row in the shared database, a front template in `app/views/`, assets in
`assets/sites/<slug>/`, a launch pack (`launch.php`) applied by
`tools/seed-launch.php`, and a `DEPLOY-<NAME>.md` runbook.

**The release branch is `claude/master-dashboard-control-room-nr3mp4`.**
It is *not* the repository's default branch — GitHub still defaults to
`claude/prairie-post-news-site-hiffgl`, which is a dead line (no
CivisMedia hub, no Sudbury). The release branch is the string hardcoded
in `tools/vps/upgrade-papers.sh`; that script is the only thing that
defines what production runs.

**The VPS does not pull.** `upgrade-papers.sh` resolves the release
branch's head, extracts it to an immutable release directory
`/var/www/prairiepost-<sha12>-<label>`, carries each old release's
configuration forward verbatim as `app/config.site.php`, writes a
generated `config.php` wrapper over it, repoints the nginx blocks and
cron files, and rolls the group back if a domain stops serving its own
masthead. So: `config.php` in a live release is generated — the tenant
configuration to edit is `app/config.site.php`, and new code reaches
production only through that script.

Never deploy from a branch that is not merged into the release branch.
The Torch went live from `deploy/torch-on-3fd4f13` and stayed outside
the trunk for weeks; the next upgrade would have repointed
tricitiestorch.ca at a tree containing none of its templates or assets,
and the script's masthead check could not have caught it because the
title comes from the database, not the tree.

## Launching a site: lessons from the rollouts (Brampton, Western Wire)

These are the failure classes that have actually bitten deployments.
Check every new runbook and every set of deployment-agent instructions
against this list before shipping them.

1. **Verify only what exists at that step.** This defect has now shipped
   **three times** — Brampton's ticker/Brief rail, Western Wire's
   wordmark/province links, and Sudbury's Tips link and desk nav. Every
   time the deployment was healthy and the gate was wrong, and every
   time the remedy was written down as a principle that then failed to
   stop the next one. So stop writing it as a principle. Before a
   pre-seed Verify item goes into a runbook, run this classification,
   and put the result in the runbook itself:

   | Where the value comes from | State before the seeder |
   | --- | --- |
   | Release tree — stylesheet, templates, `palette.json` `chrome` keys (`place`, `hero_*`, `lead_kicker`, `nav` order) | **Present.** Safe to check. |
   | `pp_site_default_settings()` in `app/seed.php` | **Present, at network-default values** — the tagline reads "News to the horizon", never the paper's own. |
   | The launch pack's `settings` array | **Absent.** Everything here is post-seed, including values that gate chrome. |
   | The global `categories` table | **Unknowable in advance.** Desks are shared network-wide, so any desk a sister paper already seeded is *already in the nav*. |
   | `posts` | Absent. |

   Two mechanical checks that would have caught all three:

   - For every chrome element in a pre-seed Verify item, grep the
     template for its guard. `<?php if (setting('contact_email') !== ''):`
     wrapped around the Tips link, with `contact_email` in the launch
     pack, means Tips is post-seed. The guard is the answer — don't
     reason about it.
   - Never assert an *absence* that depends on shared network state.
     "The nav carries no desk links" cannot be derived from the pack.
     Ship the query instead — `categories_all()` run from `$REL` — and
     let the agent read the real list.

   Write pre-seed expectations as a table of *what you will see and
   why*, not as a list of what will be missing. A positive expectation
   can be matched; "do not stop over the absence of things I have not
   enumerated" cannot.

2. **The live `config.php` has drifted from `config.example.php`.**
   Production uses a three-stage exact-match tenant selector
   (hostname → tenant, tenant → site slug, slug → canonical URL), not
   the example file's two `str_contains` matches. Never hand a
   deployment agent literal config lines to paste — state the semantics
   (host X resolves to slug Y with canonical URL Z) and have the agent
   mirror the architecture it finds in the live file. Runbooks should
   describe the mapping semantically for the same reason.

3. **`posts.slug` is unique across the whole shared database.** Launch
   content titled after a story that already exists anywhere on the
   network slugifies to a taken slug and is silently skipped
   ("story exists, skipped"). Aggregator wire items that link to sister
   stories must carry explicit slugs (`'slug' => 'wire-…'`; the seeder
   supports the key). Test any launch pack against a database that
   already contains the content it references — a fresh SQLite hides
   this entire failure class.

4. **Launch packs must be desk-self-sufficient.** Desks (categories)
   are shared network-wide, so a pack can accidentally depend on a desk
   another paper seeded first (Western Wire's sports items relied on
   Brampton's Sports desk). List every desk the pack's stories use in
   the pack's own `desks` array — the seeder creates only what's
   missing, so duplicates are harmless.

5. **Agents discover server facts; instructions don't assert them.**
   The release root comes from the enabled nginx blocks — strip inline
   comments and `readlink -f` (some blocks use the
   `/var/www/prairiepost-current` symlink, some the physical
   `/var/www/prairiepost-c1d012f`; they are the same checkout). Pin
   releases by requiring a merge commit to be an ancestor of the
   release branch's HEAD, not by naming feature branches — merged
   feature branches go stale.

6. **Expect the action guard at `config.php`.** The server environment
   requires fresh human authorization before the shared config is
   touched, and the prompt times out. Write instructions so the config
   edit is a pre-authorized, tightly-scoped step (exactly N semantic
   entries, nothing else, no credential output, root-only pre-edit
   backup) so a timeout doesn't strand the deployment mid-run.

7. **New public routes need three homes:** `router.php`, `.htaccess`,
   and the nginx rewrite list in the new site's runbook (existing
   papers' blocks don't need routes they don't use — `/region/` lives
   only in Western Wire's block). Miss one and the route 404s only in
   production. **This ends with the first roll whose upgrader carries
   `pp_front_controller()` (PLAN.md 9.2).** That roll gives the
   hand-written blocks a `router.php` fallback. From then on a route
   needs `router.php` and `.htaccess` only, and the roll's report shows
   which blocks were converted ("paths no rewrite names now reach
   router.php").

   The first such roll (388f1bd, 26 Nov) stopped at `nginx -t`. Five
   live blocks nest their first PHP handler inside `location ^~ /admin/`,
   and no block in the harness that tested the edit did, so the new
   `location = /router.php` landed inside `/admin/`. Test an edit to the
   blocks against copies of the live ones (`tools/vps/fixtures/nginx/`,
   `tools/test-nginx-edits.sh`), never only against blocks written for
   the test.

   A `router.php` fallback still doesn't reach a route whose path ends
   in an extension a block's static-file regex names (`.ico`, `.png`,
   `.svg`…). nginx picks the regex before `location /`, and a missing
   file is a static 404. Such a route needs an exact `location =` in
   the upgrader, as `pp_icon_routes()` gives the icon URLs (#108).

8. **The first request after a pull runs pending migrations.** Any
   curl during nginx/TLS verification can trigger it. Schedule the
   all-papers regression spot-check immediately after the first request
   against new code, not at the end of the runbook.

9. **A web font is only loaded by the stylesheet that declares it, and
   you cannot verify typography by looking.** `pickering.css` named
   `'Source Serif 4'` at the head of its stack and never declared the
   face. The woff2 files were in the release — Turtle Island brought
   them — but the only `@font-face` lived in `turtleisland.css`, which
   Pickering does not load, and naming a family in a stack fetches
   nothing. The masthead rendered in Liberation Serif.

   The screenshots looked right. A Times clone at masthead size is not
   distinguishable from the intended face by eye, which is precisely why
   the visual check passed and the deployment agent's mechanical one did
   not. Two rules follow:

   - Every paper that names a self-hosted family must declare the
     `@font-face` in **its own** stylesheet. Duplicating the block across
     papers is correct and costs nothing — browsers dedupe by URL.
   - Verify a typeface by asking the renderer, never by looking:
     `CSS.getPlatformFontsForNode` over CDP returns the family that
     actually painted and an `isCustomFont` flag. Failing a browser,
     assert that a stylesheet the page loads contains a real `@font-face`
     rule naming the file — and parse rules rather than grepping, since
     comments mentioning `@font-face` inflate a naive count.

10a. **Verify in an environment with production's capabilities.** The
    sandbox has no outbound network, so a stylesheet's @import of a
    font CDN silently no-ops there — CDP faithfully reported the
    fallback face as "what paints," and a conclusion built on that
    renamed six live papers' typography. When a check's result depends
    on a network fetch, verify the mechanism (the import exists and
    resolves) rather than the local outcome.

10. **Verify text at the layer that produces it.** CSS
    `text-transform: uppercase` means the painted text differs from the
    served bytes — Le Bleuet Blanc's badge paints "EN DIRECT" while the
    HTML says `En direct`, and an agent's crawler greping raw HTML for
    the uppercase literal hard-stopped a healthy deployment twice over
    its absence. When a Verify item names a visible string, state the
    string as it appears in the served HTML, and say so when CSS
    transforms it.

10b. **A launch that skips the config edit skips everything only the
    config supplies.** Since migration 15, a paper's hostnames live in the
    `domains` table and a launch needs no config arm. But `site_url` still
    came only from the config, so thirteen papers launched that way
    printed the flagship's address in every canonical, sitemap and feed for
    up to twelve weeks. Every check passed, because every check looked at
    the masthead, which comes from the database. `site_url` was the only
    per-paper value the config alone supplied, and it now falls back to the
    database (#101). Before moving anything out of the config path, list
    what that path supplies (`pp_config()` reads). A launch verifies the
    front's canonical host, not just its masthead.

11. **Keep what worked:** hard stop after two failed verifications with
   an exact report (both Western Wire stops were correct and caught
   real defects); per-step Verify gates; no manual database edits ever
   (the seeder and migrations do all writes); seeders idempotent and
   safe to re-run; demo/launch outbound links only to pages we control.

## The deployment model on this line (read before writing any runbook)

Production is served from **immutable release directories**
`/var/www/prairiepost-<sha>-<label>`, built from a branch tarball by
`tools/vps/upgrade-papers.sh`. The live root is NOT a Git checkout and
must never be `git pull`ed or rsynced into.

- The live tenant mapping is **`app/config.site.php`**. The release's
  `config.php` is a generated wrapper that adds `hub_slug`; editing it
  is silently discarded by the next upgrade.
- `uploads/` is a symlink to `/var/www/prairiepost-shared-uploads`.
- `upgrade-papers.sh` captures every domain's front-page title before it
  changes anything and rolls a whole release group back if any domain
  fails to serve its own masthead afterwards. Prefer it over any
  hand-written deployment sequence, and pass `PP_BRANCH` to pin a deploy
  to a line other than the branch head.
- **The default branch is not what production runs.** Confirm which
  branch and commit a release directory was built from — the directory
  name carries the short SHA — before writing or following a runbook.

## The two agents — never conflate them

Two separate agents work this network, holding different credentials,
and a brief must never mix their steps:

- **The VPS agent** holds root SSH to the server and nothing else. It
  deploys releases, runs the repo's `tools/` from a release directory,
  reads logs, and reports. It holds no ingest token and never files,
  edits, or publishes a story. Its briefs are server work only.
- **The news agent (Hermes)** holds one ingest bearer token and nothing
  else — no SSH, no shell, no database access. It reaches the network
  only through `POST /api/ingest`, which is write-only and files drafts.
  Everything it knows about the wire comes from exports the VPS agent
  produces (`tools/wire-map.php`). The ingest surface's reads —
  lookup, and (since step 24) GET /api/ingest/stories — answer only
  keys minted with that endpoint's grant, never by default.

The dependency runs one way: when a task needs both (deploy, then file),
brief the VPS agent first, wait for its report, then brief the news
agent. Never give the news agent a shell command or the VPS agent a
filing step — each such mix has produced a stopped run or an agent
holding a capability it should not have.

## Current operational state (as of the story-lookup roll, 21 Nov 2026)

- **Newest (26 Nov, late): production is on `117c158a545b`, at schema
  26.** `docs/briefs/roll-f07735dd1e3d.md` records that roll as
  succeeded. Its report hasn't reached this file.
  - It carries everything through #107: #90–#98, #101, #102, #104,
    #105 and #107. Where a bullet below calls one of those "not
    rolled", this bullet is newer.
  - Checked from outside the same night:
    - `/tips` answers 200 on Toronto and Kelowna;
    - Toronto's canonical names `torontotelegraph.ca` (#101);
    - all 27 fronts answer 200 with exactly one `noindex`, so no paper
      is open to search engines;
    - `/favicon.ico` 404s on Edmonton and Prairie Dispatch, two of the
      five hand-written blocks #108 fixes, and is 200 on Portage and
      Bison.
  - The ingest limits in force are #102's code: 200 filings, 200
    uploads and 120 edits per lane, per token per hour, unless the box
    sets an override. The roll's D15 table proves it, and hasn't
    reached this file.
  - Not rolled: #103 (the search-engine switch) and #108 (icon URLs on
    the hand-written blocks). Their brief is
    `docs/briefs/roll-f07735dd1e3d.md`.
- **25 Nov, checked from outside: thirteen papers call themselves
  prairiedispatch.ca.** On Bison, Brandon, Burrard, Cariboo, Kitchener,
  London, Mississauga, Portage, Red River, Rideau, Steeltown, Surrey and
  Toronto, `site_url()` returns `https://prairiedispatch.ca`. These
  carry that address:
  - every canonical link, `og:url`, share image and story data;
  - every sitemap URL and every feed link.

  The flagship has none of those stories, so a feed or a share lands on
  a 404. The thirteen are exactly the papers launched config-edit-free.
  They resolve their tenant from the `domains` table, but `site_url()`
  read only the config, whose default is the flagship's address. **Fixed
  in code (#101), not rolled:** `site_url()` replaces a configured URL
  that names another paper with the paper's own canonical host
  (`sites.domain`, else its shortest `domains` hostname). No config edit
  is needed. Fix it before any of the thirteen is indexed (PLAN.md 10.0).
- **24 Nov, checked from outside (no agent report yet).**
  - **The `7cb82fb` roll has run.** All 27 domains serve
    `/assets/js/bulk.js`, which only the `7cb82fb` tree carries. It was
    code-only, so the schema stays 25. Its report hasn't reached this
    file, but its routes pass and upload limit landed: every domain
    answers `401` on `/api/ingest/update`, `/api/ingest/stories` and
    `/api/ingest/drafts/{ref}`, and to a 1.5 MB body.
  - **Eight papers load analytics.** Kermode Chronicle carries the GA4
    tag `G-RTEJHC0DFB`, pasted into its Analytics snippet setting on
    24 Nov. It is served once, in `<head>`, on every public page type,
    and is absent from the sign-in page and from every sister paper.
    The evening crawl the same day found seven more papers loading their
    own Google Tag Manager container, one ID each: Burrard
    `GTM-577M22PZ`, Edmonton `GTM-PXR2FHTQ`, Grande Prairie
    `GTM-NWFCM75J`, Portage `GTM-TFM38BG7`, Prairie Dispatch
    `GTM-K9VN3L7C`, Surrey `GTM-KQ3222B6` and Toronto `GTM-PZ6PWL6K`.
    No paper has a privacy page.
  - **thepacificpost.com's apex had two stray Hostinger records,
    deleted by the owner the same day.**
    - An A record, `217.21.85.231` (a Hostinger server answering 403),
      failed about half of apex requests over TLS.
    - An AAAA record, `2a02:4780:11:974:0:20d7:f3ee:1`, wasn't the VPS
      either. The VPS is `srv810445.hstgr.cloud`, whose IPv6 is
      `2a02:4780:2d:6f65::1`.

    The zone sits on Hostinger's `dns-parking.com` nameservers (serial
    `2026112402` after the fix). It now holds only A records for `@`
    and `www`, both the VPS, and Google and Cloudflare agree. No paper
    publishes an AAAA record, so the network is IPv4-only in DNS. Add
    AAAA records only after PLAN.md 9.2's both-family audit.

    Public DNS can be wrong like this, so a brief pins every HTTP check
    to the box (`curl --resolve "$host:443:127.0.0.1"`, certificates
    still verified), as the upgrader's own probes always have. Write
    future briefs the same way.
- **Latest known state (23 Nov, from the VPS agent's report when the
  `7cb82fb` roll stopped at A3).** All 27 sites serve release
  `feb2b1f4f6eb` (the merge of #84), rolled the night of 22 Nov, at
  schema version **25**. The 22 Nov roll to `835f90b` also completed, and
  was reported that day. Neither report reached this file, which is why
  the first issue of the roll brief expected `4ff557d2b737` or
  `835f90b85d2a` and stopped. That was lesson 5 working: the agent acted
  on the server, not on this file. 27 sites is the 25 below plus, by
  count, Steeltown and Toronto. Where the bullets below disagree, this
  one is newer.
- **Release in production as of 21 Nov (superseded above): `4ff557d2b737`** (branch commit
  `4ff557d2b737482a2915f00f62ec322308ae70ed`), serving BOTH release
  groups: `/var/www/prairiepost-4ff557d2b737-shared` (the papers) and
  `/var/www/prairiepost-4ff557d2b737-civismedia` (the hub). The
  21 Nov roll found production on `680a0ff84e44` at schema 22 — a
  16 Nov roll this file had no record of had already applied step 22
  — which is lesson 5 working: the agent acted on
  `migrate.php --status`, not on this file, and only step 23
  applied. Recovery past steps 22/23 is FORWARD ONLY: no rollback
  by repointing (docs/MIGRATION-COMPAT.md). The 25 domains split
  14 legacy nginx blocks + 11 generated (router.php) blocks. Both
  groups roll together; `upgrade-papers.sh` upgrades every prairiepost
  group and migrates their shared schema exactly once. Never write a
  brief that forbids touching the hub release (that stale rule aborted
  the first Phase-2 roll).
- **Twenty-five live domains pass the masthead guard**: the seventeen
  original papers plus surreystandard.ca (site #18),
  cariboocompass.ca (#19), burrardbrief.ca, rideaureview.ca (#21),
  and the Manitoba four launched 29 Sep — bisonbulletin.ca (#22),
  portagepress.ca (#23), redriverregister.ca (#24) and
  brandonbulletin.com (#25, the network's ONE non-.ca masthead) —
  plus the hub. TLS on the Manitoba four runs to Dec 28 2026.
- **Manitoba is COMPLETE and LIVE (29 Sep, two runs)**: Bison
  (province-wide, #22), Portage (Winnipeg, #23), Register (the
  valley, #24) launched from release 62131795c639 behind per-paper
  DNS gates; Brandon (the southwest, #25) followed the same day
  after a roll to d68a89f, whose tree carries the corrected
  brandonbulletin.com pack — the first attempt's .ca NXDOMAIN gate
  had skipped it cleanly, which is the pinned-brief design working.
  Each paper seeded its six-story inaugural edition and opens with
  its no-art editorial hero — no empty states. Portage's first seed
  created the `arts` desk network-wide; Brandon's seed added no
  desks (all four existed). Certbot first attempt on all four; both
  address families everywhere; all four fail closed 401 on
  /api/ingest and /api/ingest-media; no ingest credential exists for
  any of them.
- **The upgrader's extension preflight asks PHP directly**
  (`extension_loaded()` via `php -r`). The old `php -m | grep -q`
  pipeline under `set -o pipefail` failed nondeterministically via
  SIGPIPE and aborted a healthy roll twice on 24 Sep (dom, then curl
  on the retry) — never reintroduce a grep -q pipeline into a
  pipefail preflight.
- **Inaugural editions are seeded.** Surrey, Cariboo and Burrard each
  carry exactly six launch notes — signed editorial-board mission
  piece plus one desk-method/service note per desk, every story ABOUT
  the paper itself, true by construction. This is the pattern for
  filling a new front page WITHOUT growing debt item 1 (invented
  editorial about real cities): no fabricated events, votes, figures
  or human bylines, links internal only.
- **rideaureview.ca is LIVE (25 Sep)** — launch-only from the live
  release once the registrar A record moved from parking to the VPS:
  vhost generated (both families, bare socket path), certbot first
  attempt, seed created site #21 with desks `gatineau` and `corridor`
  new network-wide and the six-story inaugural edition. The pack's
  third wire source (the CBC national feed) printed no "source added"
  line because the URL was already in the shared sources table —
  seeder dedupe by design, not a defect.
- **Schema version 23 as of 21 Nov (25 since the feb2b1f4f6eb roll),
  journaled** (step 21: `posts.awaiting_image`;
  step 22: `ingest_agents.draft_only`, NEWS1; step 23: the
  story-lookup lane — `ingest_receipts` with
  UNIQUE (site_id, source_story_id), `ingest_agents.lookup_scope`,
  the `ingest_ref_key` settings row). `tools/migrate.php` is the
  only mutator, the app refuses (503/exit 2) on a non-ready schema,
  and the roll migrates before any traffic switch.
- **The Phase 1+2 hardening is live**: HTML Purifier sanitizer,
  editorial authorization, SSRF-safe transport, CLI-only first admin,
  per-site opt-in indexing (every page noindex until enabled — none
  enabled yet), internal-path denial on every vhost (vhost.template
  for new blocks; `/etc/nginx/snippets/prairiepost-deny.conf` in
  legacy blocks; the snippet deliberately carries NO dotfile/ACME
  locations — blocks own those, duplicates are an nginx emerg).
- **Backups**: nightly cron invokes the RELEASE's own
  `tools/backup.sh` (repointed by each roll); manifest-backed sets
  under `/var/backups/civis` (latest verified
  `20261121-031701-2a02ecf4`); discovery scoped to
  `/var/www/prairiepost-*` roots, so foreign tenants read as
  out-of-scope by design. Off-site transfer is NOT configured — an
  owner step (hook + key).
- **The box hosts FOREIGN tenants**: the Institute
  (`/var/www/cies-*`) and Calgary Dispatch (`/srv/calgarydispatch/…`,
  its own vhost). Network tooling ignores them by root-prefix and
  briefs must never require anything of them. Shared-fate caveat: any
  tenant's broken vhost fails the global `nginx -t`.
- **Dormant tenants in the tree**: `steeltown-standard` is
  BRAND-COMPLETE (29 Sep, from the owner's package: Tiger-Cats gold
  #FCB525 / Hamilton navy #073674 / crimson #A8353A / camel #E7D2AD,
  the chain-ring S/I-beam mark traced five-mask from the package,
  Archivo Narrow condensed headlines + Source Serif 4 body (both
  already in fonts.css), "Local News. Steeltown Strong.",
  site_title "Steeltown Standard" — the brand dropped the
  foundation's "The" — desks per the package mockup Local
  News/Sports/Community/Opinion, six inaugural notes, revised
  DEPLOY-STEELTOWN.md; domain OWNER-CONFIRMED steeltownstandard.ca).
  Its DNS is DONE: apex and www A → the VPS, verified 23 Nov.
  `toronto-telegraph`
  is BRAND-COMPLETE too (29 Sep, from the owner's package: the
  double-blue heritage palette — Leafs Navy #00205B, Argos Oxford
  #0C2340, Argos Cambridge #5F8FB1, soft silver — the
  maple-leaf-and-telegraph-key roundel traced from the package's app
  icon, Archivo/Archivo Narrow headlines + Source Serif 4 body,
  "Toronto's Independent Voice", site_title "Toronto Telegraph" —
  the "The" dropped — desks per the package mockup
  City/Sports/Business/Culture/Opinion via desk_labels overrides
  ("City" for local-news; "Business" for the shared category's
  "Business & Markets" name), six inaugural notes, revised
  DEPLOY-TORONTO.md; domain OWNER-CONFIRMED torontotelegraph.ca).
  Its DNS is DONE too, verified the same day. The 23 Nov report counts
  27 sites, so the launch has run; its own report hasn't reached this
  file. It was one VPS brief for both:
  `docs/briefs/launch-steeltown-toronto.md` (launch-only from the live
  release, no pin; it checks for a stopped or running roll first, and
  adds `client_max_body_size 10m` to the two new blocks because the
  live release's template predates it). Only `terminal-city-times`
  (DEPLOY-TERMINALCITY.md) remains
  foundation-only, awaiting its brand package. Foundations get
  their six-story inaugural editions at brand-build time, never
  earlier. The wider slate (Rideau siblings, Atlantic papers)
  awaits packages.
- **The expansion name slate is in `NETWORK-EXPANSION.md`** (chosen
  23 Nov 2026): twelve mastheads for the North, Atlantic Canada,
  Quebec and Saskatchewan, with their domains, the reasons, the open
  questions and every rejected name. Nothing is registered or built
  yet. Read it before proposing names for a new region; record new
  name decisions there, never only in a chat.
- **Deploys are pinned**: the VPS agent resolves the release branch
  head via the API, requires the exact full SHA from the brief, and
  fetches `upgrade-papers.sh` at that SHA. Rolls are preceded by a
  fresh verified backup (the preflight enforces it, 26-hour window).
  Launch-only work discovers `$REL` from the enabled nginx blocks and
  needs no pin.
- **Agent ingest v2 is live (27 Sep).** Hub admins mint, revoke and
  re-enable the ingest bearer keys on `/admin/api-keys.php` (same
  `ingest_agents` table and sha256-at-rest as `tools/make-agent.php`;
  raw key shown exactly once; every action audited). The same key
  uploads featured graphics via `POST /api/ingest-media` (multipart or
  raw body, sniffed AND decoded, 8 MB cap, server-named file under
  /uploads) and files stories via `POST /api/ingest` referencing the
  returned path — everything lands as a DRAFT; keys carry no publish
  power (`wire_desks` stays the per-paper editorial exception). The
  27 Sep routes pass added both /api routes to the 14 legacy nginx
  blocks (root-only backups at /root/nginx-routes-pass-20260927T231803Z/);
  the 7 generated blocks route through router.php and needed nothing.
  All 25 domains fail closed with JSON 401 (the four Manitoba
  blocks are generated and route through router.php) — the old
  prairiedispatch /api/ingest 404 gap is CLOSED. Grande Prairie's apex is a redirect
  alias; its canonical endpoint host is www.grandeprairiegazette.ca.
  Contract for agent authors: `docs/api-ingest.md`.
- **The publish-on-image lane is live (28 Sep).** A filing flagged
  `publish_on_image` (Courier: `ready: yes`) waits as a draft with an
  "awaiting image" chip in its admin list; GET /api/ingest-queue is
  the image agent's worklist (the ingest surface's ONLY read, over
  pipeline state these tokens created); POST /api/ingest-publish
  attaches the featured image and publishes in one guarded write,
  returning the canonical URL for the social posts. No flag, no
  publish — newsroom drafts 409 from this lane. The flag lives on the
  ROW, never on the key. Publishing or scheduling a flagged story by
  hand consumes the flag in the same write as the status, so a story
  an editor publishes and later moves back to draft never returns to
  the image queue (the Stories list's bulk publish does this from the
  7cb82fb roll on; the story editor was fixed on 24 Nov, after that
  pin, and rides the roll after it). Operating guide for the two agents:
  `docs/agent-workflow.md`. The 28 Sep mini routes pass added both
  routes to the same 14 legacy blocks (backups at
  /root/nginx-mini-routes-20260928T020338Z); all 25 domains fail
  closed 401 on both.
- **The story-lookup lane is LIVE (21 Nov, release 4ff557d2b737).**
  POST /api/ingest/lookup is read-only (writes NOTHING, not even
  last_used_at) behind the separate article:lookup grant
  (lookup_scope '' none / own / all; make-agent --lookup, or the hub
  key form). Scope is one paper and desk per call, checked against
  the key's papers, its desks, AND the serving hostname; every scope
  failure answers the same 403 {"ok":false,"error":"out of scope"},
  existence notwithstanding. Matching: the stored source_story_id
  first (the receipts the filing path keeps), then the bounded
  exact-normalized-headline + canonical-source-URL comparison — one
  line of evidence, two full matches (ambiguity picks no winner), a
  receipt whose article moved desks or off the paper, or a bound
  hit all answer review_required, never not_found. A match returns
  only {ref, status, url?}. POST /api/ingest takes an optional
  source_story_id: UNIQUE (site_id, source_story_id) in the
  DATABASE makes two simultaneous submissions one article (story,
  receipt, sources and tags commit in ONE transaction); an
  identical retry returns the original receipt, a different payload
  under a used id is 409 and never touches editorial edits.
  /api/ingest-queue unchanged; no publish-permission changes. The
  21 Nov routes pass added /api/ingest/lookup to the 14 legacy
  blocks (root-only backups at
  /root/routes-pass-lookup-20261121T181222Z/); the 11 generated
  blocks route through router.php and needed nothing; all 25
  domains fail closed 401 on the route. Keys `noon-kermode`
  (kermode-chronicle) and `noon-portage` (portage-press) are minted
  --lookup=all --draft-only, NO desk list yet (the owner had named
  none; desk-explicit is one revoke + create), tokens root-only
  under /root/hermes-tokens/. Live proofs: both papers returned a
  full-evidence `match` with the published URL, and each key asking
  about the other paper got the three-word 403. The noon job stays
  disconnected pending the owner's review of those receipts.
  Contract: docs/api-ingest.md §5; compat: docs/MIGRATION-COMPAT.md
  step 23. Not separately re-confirmed on 21 Nov: per-site indexing
  still off (nothing in the roll touches it).
- **The article-editing API is LIVE (release `feb2b1f4f6eb`, schema
  25).** It completes the 22 Nov update and story-read lanes (PR #81,
  merged at 835f90b; schema step 24: `ingest_agents.update_published`,
  `ingest_agents.read_scope`, `post_revisions.image_credit`). The
  835f90b roll and a later one to feb2b1f4f6eb both ran and were
  reported to the owner, not to this file. Whether their routes passes
  reached every block is what the 7cb82fb brief's D5 and D6 check, and
  any roll still acts on `migrate.php --status`, not on this line
  (lesson 5). Schema step
  25: `posts.image_alt` (default ''), `ingest_agents.edit_drafts`
  (default 0), `post_revisions.image_alt` (nullable). One engine,
  `app/ingest-edit.php`, behind two lanes, each its own grant set at
  minting: PATCH /api/ingest/drafts/{ref} (`--edit-drafts`; status
  exactly 'draft', editors' drafts included; never publishes,
  schedules, approves, moves desks or touches awaiting_image) and
  POST /api/ingest/update (`--update-published`; now also alt text,
  caption, credit, tags, sources, meta_description and `new_desk`
  within the key's desks; never slug, publication date, status,
  byline, papers or correction). Both REQUIRE `if_version` (the
  reader's version, which now covers status, desk, text, cover, SEO,
  tags and sources), recheck it under a row lock, commit story + tags +
  sources + revisions + audit in ONE transaction, refuse unknown
  fields (422), and answer an already-applied edit `200 unchanged`
  (retry-safe). The reader returns image_alt, meta_description and
  sources as {url,title}. Slug renames are NOT built (no redirect
  infrastructure exists) — generic edits never change the URL.
  Contract: docs/api-ingest.md §1, §6, §7, §8; compat:
  docs/MIGRATION-COMPAT.md step 25. **The Kermode 413:** nginx's 1 MB
  default `client_max_body_size` (set nowhere — not in
  tools/vps/vhost.template, not in the legacy blocks) refused a 1.6 MB
  PNG before PHP saw it; PHP's own 2 MB `upload_max_filesize` would
  have refused the next size up. Fixed in the tree by a release-root
  `.user.ini` (10M/12M, per-directory so foreign tenants' pools are
  untouched), `client_max_body_size 10m` in vhost.template, and a JSON
  413 from /api/ingest-media naming `max_bytes`; the EXISTING 25 blocks
  need the directive added in the roll's routes pass (generated blocks
  are never regenerated). Rolling needs: a pinned roll (steps 24 and/or
  25 migrate), a routes pass adding /api/ingest/update,
  /api/ingest/stories (if 835f90b's pass did not) and the regex route
  `^/api/ingest/drafts/[^/]+/?$` to the 14 legacy blocks, plus
  `client_max_body_size 10m` on every block that lacks it (all 25 as
  of 23 Nov; blocks created by the Steeltown/Toronto launch brief
  carry it from creation), and re-minting the agents' keys
  with the grants the owner orders (--read all --edit-drafts
  --update-published). **The roll brief is written:**
  `docs/briefs/roll-7cb82fb9c20a.md` (23 Nov) pins the roll to
  `7cb82fb9c20aa53c886e80bbe7896bffe3da1bb4`, which carries the bulk
  status change and the newsroom branding below. It covers the preflight
  checks, the routes pass and upload limit on every block, and a
  ten-item verification, with every command run here first (the legacy
  routes against a real nginx). Key re-minting is left out, for the
  owner's separate order. **Revision 2 (23 Nov)** followed the first
  run's A3 stop: it is a code-only roll from `feb2b1f4f6eb` at schema 25
  (8 commits, no migration). A3 now requires the live release to be an
  ancestor of the pin, using the GitHub compare API, instead of naming
  releases this file might have wrong. Write future rolls' A3 the same
  way.
- **The Stories list's bulk status change is LIVE (rolled with
  `7cb82fb`; see the 24 Nov check).** A checkbox per row and a bar beside "+ New story"
  (`admin/posts.php`, `assets/js/bulk.js`, `pp_bulk_set_status()` in
  app/models.php): editors move up to 100 ticked stories at once to
  published (live now), in review or draft; authors move only their own
  drafts and in-review work, and never publish. Each write is a
  compare-and-swap on the status the list showed, so a stale page skips
  a story a colleague changed instead of acting on it. Publishing does
  what the story editor does (stamps an empty or future `published_at`,
  maps an unassigned story to the paper whose admin it is, fires the
  agent auto-queue) and also clears `awaiting_image`; one audit row,
  `stories.bulk_status`, per action. No schema step and no new route.
- **Each paper's newsroom wears the paper — LIVE (rolled with
  `7cb82fb`; Kermode's admin wore its accent in the owner's 24 Nov
  screenshot).** On a paper's own domain the admin takes that paper's
  masthead (its mark or image wordmark, its name set the way its front
  page sets it), its rail/band/nav colours, its accent and spot (buttons,
  links, status chips) and its fonts, from the `admin` block of
  `assets/sites/<slug>/palette.json`; `app/admin-theme.php` validates the
  block (hex colours, plain font stacks, fixed keywords only) and writes
  one inline `:root` rule. Every paper has a block, chosen from its
  rendered masthead; the hub has none and renders pixel-identical.
  `tests/admin-brand.test.php` checks every block — contrast for every
  pairing drawn, accent and spot far enough apart that chips stay
  distinct, and every font declared in a sheet the admin loads
  (Playfair Display is declared in admin.css itself, lesson 9). The
  admin sheet's URL carries a content hash, because no static asset is
  served with Cache-Control. A new paper's block is scaffolded by
  `tools/make-paper.php` and replaced at brand time. No schema step.
- **Embedded image metadata is stripped — MERGED (24 Nov, #91), not
  yet rolled (PLAN.md 9.1).** Until now every upload path stored bytes as
  received, so a phone photo published its GPS position, camera and
  time. Every image written under /uploads now goes through
  `pp_image_clean()` (`app/imagemeta.php`):
  - the newsroom's uploads, `/api/ingest-media`, `image` URLs fetched by
    `/api/ingest`, and wire images cached from a link;
  - a lossless container rewrite of JPEG/PNG/WebP/GIF (no GD, no
    re-encode) that keeps the colour profile, JFIF/Adobe markers,
    animation and a non-default orientation, alone;
  - EXIF, XMP, IPTC, comments, text chunks, MPF, C2PA Content
    Credentials and anything after the image's end marker are dropped;
  - a file it cannot walk end to end is refused (ingest-media answers
    422), never stored as it came.

  No schema step and no new route. **The roll brief is written:**
  `docs/briefs/roll-72f8adccbcca.md` (24 Nov) pins
  `72f8adccbcca4bca12aefb937d4d21cbdfc62e8e`, the merge of #90 after
  #91. That is 11 commits and code-only from `7cb82fb` at schema 25, and
  the upgrader is unchanged (sha256 `273b747f…`). It then cleans the
  uploads already on disk:
  - `tools/strip-image-metadata.php` runs as `www-data`, which owns the
    shared uploads: a dry run, `--apply`, and a second dry run that must
    find nothing;
  - an owner/group/mode diff of every image before and after;
  - served bytes compared with the cleaned files.

  Every command in it was run here against a local TLS nginx, fake
  releases built from the pin, and a `www-data`-owned uploads tree.
  Backup sets taken before the cleanup still hold the originals until
  they age out.
- **Live weather — MERGED (25 Nov, #94), not rolled (PLAN.md 9.6).** The typed `weather_line` and
  `weather_today` settings are gone from Settings, the seed and every
  pack; their production rows stay, unread. In their place:
  - the hub's `weather` job (`cron/weather.php` through `cron/run.php`,
    at :07 and :37) reads ECCC city pages from `api.weather.gc.ca`
    through `pp_http_get()`, fetches each city once, and stores each
    paper's reading as its `weather_live` setting;
  - a paper opts in with a `weather` block in its `palette.json` (the
    city-page id and the place name); 19 do, and
    `tests/weather.test.php` pins which;
  - `app/weather.php` is the only reader. Nothing older than three hours
    shows, every rendering credits ECCC and links to the forecast,
    conditions are only what the station reported, and the observation
    time is on the city's own clock.

  Rolling it needs the cron line added to `/etc/cron.d/civismedia` (the
  upgrader repoints cron files, never extends them) and tzdata 2026e or
  newer on the box. British Columbia, Alberta and Manitoba stopped
  changing clocks in 2026 (ECCC prints PCT, ABT and MBT), and older data
  prints their times an hour early. The sandbox's tzdata is current;
  production's is a server fact the brief checks (lesson 10a). Story
  times still print in the network zone on every paper (PLAN.md 9.8).
- **The whiteboard and the tips form — MERGED (25 Nov, #95), not rolled
  (PLAN.md 9.10).** The owner's order: "Draw on the
  whiteboard from the Calgary Dispatch. Have the tips form go to a new
  whiteboard on each site admin portal." Calgary Dispatch's source is
  `FTFNAnalytics/CalgaryDispatch`, branch `feature/whiteboard` (a
  FastAPI/React app, a foreign tenant on the box). Here:
  - `admin/whiteboard.php` (editors and admins; authors get 403) shows
    one paper's notes (tip, lead, rumour or fact; unverified, checking,
    confirmed or debunked), scoped by `site_id` in every read and write
    in `app/whiteboard.php`;
  - `tips.php` (`/tips`) pins a reader's tip to that paper's board and
    keeps only what the reader typed: no IP, no device details, and no
    audit row, because `pp_audit()` records an IP. Never add one;
  - turning a note into a draft copies the tip and its link, never the
    tipster's name or contact, and a guarded `post_id` claim keeps it to
    one draft per note;
  - every paper's Tips and Contact links point at `/tips`. No chrome may
    link a mailto to the paper again, and `tests/whiteboard.test.php`
    checks it. `contact_email` now matters only on the hub.

  Schema step 26 adds `whiteboard_notes` and the `tips_form_key` row
  (forward only, like every step since 21). Rolling it needs `/tips`
  (with `/about`) routed on the hand-written nginx blocks, and the tips
  POST kept out of the access logs, which would otherwise pair each
  sender's IP with the tip's time.
- **Launch-day widgets retired — MERGED (25 Nov, #96), not rolled
  (PLAN.md 9.7).** `app/freshness.php` gives each
  time-bound widget a clock: a banner needs `breaking_until` ahead (set
  by Settings), traffic and prices need `traffic_items_saved` /
  `markets_saved` within 12 / 30 hours, and an event needs a readable
  date that hasn't passed. Read these widgets only through
  `pp_breaking_live()`, `pp_fresh_rows()` and `pp_events_upcoming()`,
  never through `setting()` directly, or a launch-day value comes back.
  London's council tracker, open files, membership band and city-hall
  agenda are removed. No schema step, no server step.
- **Each paper prints on its own clock — MERGED (25 Nov, #97), not
  rolled (PLAN.md 9.8).** Each `palette.json` carries a `"zone"`. Every
  printed time on a public page goes through `pp_date()` (or
  `fmt_date()`, `time_label()`, `pp_clock()`, which use it); never print
  a time with bare `date()`. Storage and `strtotime()` stay on the
  network clock (config `timezone`). The admin stays on the network
  clock via `PP_NEWSROOM_CLOCK`, because its inputs are read on it.
  Western Wire and Turtle Island have no zone and print the network's
  time.
- **The upgrader converts hand-written blocks — MERGED (25 Nov, #98),
  not rolled (PLAN.md 9.2).** In the vhost loop, `pp_front_controller()` sends a
  legacy `location /`'s `=404` fallback to `router.php`, and
  `pp_tips_unlogged()` gives every block `location ~ ^/tips/?$` with
  `access_log off`. Both are idempotent by marker, inserted beside the PHP
  handler, and use the block's own php-fpm socket. They were tested on a
  real nginx and php-fpm. `vhost.template` carries the tips location from
  creation. Its sha256 at the merge is `d28307e3…`.
- **The ingest hourly limits are raised — #102 (26 Nov), not rolled.**
  The owner's order: "raise the Hermes ingest rate limits so one
  publishing key can run a full multi-site news round in an hour."
  Every lane stopped a token at 60 an hour. Uploads and both edit lanes
  had it in code; filings read a paper's `ingest_hourly_limit`, which
  nothing sets. Now `pp_ingest_hourly_limit()` (`app/ingest.php`) gives
  each lane its number:
  - from `PP_INGEST_STORY_LIMIT_PER_HOUR`, `PP_INGEST_MEDIA_LIMIT_PER_HOUR`
    or `PP_INGEST_EDIT_LIMIT_PER_HOUR`, else the same name in the config,
    else 200 filings, 200 uploads and 120 edits on each edit lane;
  - only a whole number from 1 to 100000 counts;
  - each lane counts as before (audit trail, or the posts a token filed),
    and the `429` names the limit;
  - a paper's own `ingest_hourly_limit` still comes first for filings.

  Nothing is set on the server: the defaults are the numbers. DEPLOY.md
  section 12 has the settings and the check that prints what the papers
  enforce. `tests/ingest-limits.test.php` holds it.
- **The search-engine switch — MERGED (26 Nov, #103, at `2f336b1`), not
  rolled; it rolls with #108's brief.** The owner's
  order: "add a button the settings dashboard for each site that turns
  on the indexing." A "Search engines" panel at the top of Settings
  (`admin/settings.php`, administrators only) replaces the old checkbox:
  - Open and Hide buttons write the paper's own `indexing_enabled`,
    each a CSRF-guarded POST with an audit row (`indexing.on` /
    `indexing.off`);
  - Open refuses, and its button is disabled, while any of the paper's
    demonstration stories is published or scheduled, or while its pages
    name a host that isn't the paper's (`pp_indexing_readiness()`,
    `app/indexing.php`). The panel lists those stories, each linked to
    its editor;
  - demonstration copy is declared. The fifteen packs of invented
    editorial say `'demonstration' => true`, and the flagship's twelve
    are the core seed's `pp_seed_stories()`. Those sixteen papers stay
    shut until their newsrooms draft them (269 stories as seeded); the
    ten inaugural-edition papers can open as soon as it rolls.
    `tools/demo-inventory.php` now reports the same list;
  - an About page without its own text, and the missing privacy page,
    are named beside the button but don't refuse. The privacy page is
    the owner's decision (PLAN.md, policy decisions).

  Never retitle a demonstration story in its pack: production's copy
  keeps the slug it was seeded with, and the check finds stories by
  slug. No schema step and no new route; code-only, it rides any roll.
  `tests/indexing-switch.test.php` holds it.
- **Each paper's own icons and share card — LIVE (#104 and #105, rolled
  with `117c158` on 26 Nov).**
  - Every paper serves an icon set drawn from its own mark
    (`assets/sites/<slug>/icons/`), its own `og-default.png`,
    `twitter:image` and `theme-color`.
  - `/favicon.ico`, `/apple-touch-icon.png`, `/icon-32.png` and
    `/site.webmanifest` are routes (`icon.php`), one file per host.
  - Before, `/favicon.ico` was a 404, and a missing mark or card fell
    back to the flagship's.
  - On the five hand-written blocks below the icon URLs still 404 until
    #108 rolls.

  `tests/icons.test.php` holds it.
- **The 388f1bd roll stopped at `nginx -t` (26 Nov); fixed by #107.**
  - The upgrader inserted `location = /router.php` inside
    `location ^~ /admin/` in five hand-written blocks: Edmonton, Grande
    Prairie, Kermode, Prairie Dispatch and Pacific Post (lesson 7). It
    then put every vhost and cron file back before the schema step.
  - #107 (`117c158`) inserts only beside a PHP handler that is a direct
    child of `server`, and points only the server-level `location /`
    at `router.php`. The upgrader's sha256 became `dd1fb181…`, and the
    `117c158` roll then succeeded (see the first bullet).
- **Icon URLs reach `router.php` on every block — MERGED (27 Nov,
  #108), not rolled.**
  - The five hand-written blocks carry
    `location ~* \.(?:svg|png|…|ico)$`, which nginx picks before
    `location /`. So `/favicon.ico` and the touch icons were answered
    from disk, and 404'd.
  - The upgrader's `pp_icon_routes()` adds an exact `location =` per
    icon and manifest URL, passed to `router.php`. An exact match beats
    any regex.
  - `/assets` and `/uploads` keep the static location.
  - It is idempotent by its `pp-icon-routes` marker, and
    `tools/test-nginx-edits.sh` covers it. The upgrader's sha256 is now
    `06e1c268…`.
- **The roll brief to run is `docs/briefs/roll-f07735dd1e3d.md`**
  (#108's, 26–27 Nov). It pins
  `f07735dd1e3db5cfcf4a36cf046f05f30d267baa`, #108's code commit, whose
  parent is the merge of #103. So it rolls #103 and #108 together.
  - It is code-only from `117c158a545b`: the schema stays 26.
  - It checks every icon URL on all 27 hosts.
  - S1–S4, added in #109, check that the switch arrived, that no paper
    is open, and that every front still says `noindex`. They print
    `tools/demo-inventory.php` verbatim.
  - S1–S4 were run here on nginx 1.24 and php-fpm 8.3 against a release
    tree built from the pin. The release-root line was run on copies of
    block files.
- **Done or superseded:**
  - `docs/briefs/roll-117c158a545b.md` ran (26 Nov);
  - `docs/briefs/roll-388f1bd03ffe.md` stopped at `nginx -t`, and
    `117c158` replaced it;
  - `docs/briefs/roll-acb508aa4fd0.md` was the procedure the `117c158`
    roll carried out. It pins
    `acb508aa4fd016dafeb0c12dbda25dc7de793bed`, #102's code commit, and
    added A16 and D15: the ingest limits before the roll, and the live
    ones after it, reported as a table.
- **Superseded: `docs/briefs/roll-6e3cfab33b32.md`**
  (25 Nov). It pins `6e3cfab33b323a7facc93e96eb268eecc27b46cc`, #101's
  code commit: the address fix, 8 commits after `1458b09`, 22 after
  `72f8adc` and 33 after `7cb82fb`. Its A5 stops until #101 is merged.
  It supersedes the brief below, carries all of #90–#98 from whichever of
  those three releases production is on, and adds A9 and D4: every
  domain's canonical, `og:url`, sitemap and feed must name the domain
  itself. Its address check was run here on nginx 1.24 and php-fpm 8.3
  over verified TLS. It found the two config-edit-free papers in that
  harness naming the flagship on `1458b09`, and every domain naming
  itself on the pin.
- **Superseded: the roll brief for #90–#98,**
  `docs/briefs/roll-1458b09870e0.md` (25 Nov). It pins
  `1458b09870e02f83f8ebd9acafcf6fce04d8e2cb`, the merge of #98: 14
  commits after `72f8adc`, or 25 after `7cb82fb` if the 72f8adc brief
  never ran (its A3 tells which). It covers:
  - the preflight, including the tzdata check (`15 16 17`, updating
    `tzdata` if needed) and reachability of `api.weather.gc.ca`;
  - the pinned roll, which raises the schema to 26 (forward only) and
    edits the blocks;
  - the weather cron line and its first run;
  - the image cleanup if `m > 0`;
  - thirteen checks: `/about` and `/tips` on every domain, a marker probe
    proving `/tips` never reaches the access logs, the block counts, and
    each paper's clock.

  Its new commands were run here against nginx 1.24 and php-fpm 8.3.
- **Test residue awaiting owner cleanup (admin UI, never SQL):** on
  Surrey — draft #315 (`routes-check-agent-ingest-v2-draft`), and
  story #316 (`routes-check-publish-on-image-test`) which the 28 Sep
  live proof PUBLISHED through the lane and is publicly visible until
  an editor unpublishes or deletes it; plus two tiny test PNGs under
  /uploads/2026/09/.
- Ingest tokens: `hermes-quebec`, `hermes-mississauga` (filing),
  and since 21 Nov `noon-kermode` and `noon-portage` (draft-only,
  lookup=all, per-paper, desk-unbound) — all root-only under
  `/root/hermes-tokens/`. No token exists for Surrey or any other
  paper. No paper has its newsletter enabled.


## Known debt, in the order it should be paid

**The paydown schedule now lives in `PLAN.md`**: the platform phases,
the compliance gate every new paper is built against, and (since
24 Nov) Phase 9, the next work, with what waits on the owner. Phase 10
(proposed 25 Nov) is the reader-facing work from a review of every site
against news and design practice. The list
below remains the inventory; PLAN.md is the order and the acceptance
tests. Check any new-paper work against the PLAN.md gate before starting
it.

1. **[HALF PAID — PLAN.md 0.4: every page is noindex until its paper's
   `indexing_enabled` is set, and none is. Since #103 Settings refuses to
   set it while any of the paper's demonstration stories is live.]**
   What remains: the older
   papers' content is invented editorial about a real city, including
   figures and votes attributed to real councils, publicly readable if
   not indexed. Fourteen of those packs sign it with about 80 people
   who do not exist, and nine of those papers still show at least one
   such name on their front (25 Nov; PLAN.md 10.3). Replace a paper's
   pack before its indexing is switched on, and before the next
   tranche (Western Wire, Bleuet Blanc, Turtle Island) launches. The
   newer papers' inaugural editions are about the papers
   themselves, true by construction, so launches no longer grow this.
   Original text: every paper is indexable — `robots.txt` is
   `Allow: /` with the sitemap advertised. (`robots.txt` stays `Allow`
   on purpose: a disallowed crawler never sees the noindex.)
2. **[PAID — phase 2, Aug 24: convention dispatch + chrome partials;
   a new paper is files only, scaffolded by tools/make-paper.php]**
   A new paper edits four shared files — a dispatch arm in
   `index.php`, `article.php` and `section.php`, and header + footer
   chrome in `ui.php` (28 template branches there already). The Torch
   and the Standard conflicted in all four. Before papers 11–15, replace
   the if-chains with a convention (`front-{$template}.php` if it
   exists) and move each paper's chrome into its own partial, so a new
   paper is new files only.
3. **[CORRECTED, Aug 25 — this finding was half-wrong, and the Aug 24
   stack rename that acted on it changed six live papers' typography
   for a day.]** The stacks named Source Serif 4 "without declaring
   it" — but the stylesheets @imported it from fonts.googleapis.com at
   runtime, so readers' browsers painted Source Serif 4 all along; the
   "phantom font" CDP check ran in a sandbox with no network, where the
   import silently no-ops (lesson 10a). Fixed by vendoring every family
   the network names into /assets/fonts/, declaring them all in
   /assets/css/fonts.css (a local @import replaces every Google
   import), restoring the six stacks to lead with Source Serif 4, and
   removing the Google preconnects from the shared chrome. Nothing
   loads from a font CDN anywhere. Original text follows for the
   record: Six papers name a font they never declare. `westernwire.css`,
   `pacific.css`, `aurora.css`, `chronicle.css`, `bulletin.css` and
   `broadsheet.css` all put `'Source Serif 4'` at the head of a stack
   with no `@font-face` anywhere they load. They predate the self-hosted
   woff2 files and have always fallen back to Georgia — which is what
   their owners have seen and approved, so this is a decision to make
   rather than a bug to fix quietly. Declaring the face on all six would
   change six live papers' typography in one release; decide whether
   that is wanted before doing it.

4. **[OPEN — PLAN.md 9.2, with converting the 14 legacy blocks.]**
   **Nginx has no `default_server` on 443, and the papers are not
   symmetric across address families.** With no explicit default, nginx
   falls back to the first block bound to each socket — Brampton on
   IPv4, the Institute on IPv6. A paper whose block is missing one
   family's `listen` line silently inherits that default's certificate
   and fails hostname verification for every client in that family.
   Turtle Island hit exactly this: certbot mirrored a single-family
   listen set, produced `listen [::]:443 ssl` with no IPv4 equivalent,
   reported success, passed `nginx -t`, and served Brampton's
   certificate to every A-record client. Audit all eleven blocks for
   both `listen 443 ssl` and `listen [::]:443 ssl`, and decide whether
   an explicit `default_server` that rejects unknown SNI is wanted
   rather than letting the first-loaded paper serve as the fallback.

5. **[PAID — PLAN.md 1.4: before a roll the upgrader records each
   domain's status, title AND front-page stylesheet set (`front_css`),
   and rolls the group back if any of them changes.]**
   **`upgrade-papers.sh` cannot detect template loss.** Its guard
   compares each domain's `<title>`, which comes from the database, so a
   paper whose CSS and templates are missing from the release still
   passes. Assert that each paper's expected stylesheet appears in the
   served HTML.
6. **`posts.slug` is UNIQUE network-wide.** At 15 papers that is ~225
   slugs in one namespace in the same register, and the seeder's failure
   mode is a silent `story exists, skipped`. Scope it per site or have
   the seeder prefix automatically.
7. **[OPEN — the owner's switch, PLAN.md 0.3.]** **GitHub's default
   branch is the dead line.** Repoint it to the release branch so the
   trunk that is visible is the trunk that ships.

## Phase 1 security invariants (do not regress)

Full detail and evidence: `docs/build/phase-01-handoff.md`.

- **Stored HTML** renders and saves only through `sanitize_html()`, which
  is HTML Purifier (parser-based, vendored under `vendor/`, pinned in
  `composer.lock`) — never reintroduce regex sanitization. JSON inside
  HTML (JSON-LD, inline `var X =`) goes through `pp_json_for_html()`.
- **Post writes** (edit, autosave, restore, delete, and the Stories
  list's bulk status change) ask
  `pp_post_write_denied()` against the PERSISTED row and, for authors,
  write through `pp_guarded_post_update()` with a state condition —
  published/scheduled stories change only at an editor's desk. The bulk
  change (`pp_bulk_set_status()`) conditions EVERY write, editors'
  included, on the status the list showed.
  **ONE owner-ordered exception (Nov 2026):** `POST /api/ingest/update`
  lets a key minted with `update_published` change the title, lede,
  body and image/caption/credit of a PUBLISHED story within its
  papers, hostname and desks — never slug, status, dates, byline,
  desk, tags, meta description or correction; body through
  `sanitize_html()`; a compare-and-swap write; the pre-update state and
  every agent change snapshotted into `post_revisions` (with
  `image_credit`, so an editor's restore is exact) and audited.
  **Widened by the owner's order of 23 Nov 2026** to the cover's alt
  text, tags, sources, meta description and desk (within the key's
  desks) — still never slug, status, dates, byline, papers or
  correction; `if_version` now required and rechecked under a row
  lock; story, tags, sources, revisions and audit in one transaction
  (`app/ingest-edit.php`). The same order added the DRAFT lane,
  `PATCH /api/ingest/drafts/{ref}` (grant `edit_drafts`): the same
  fields minus desk, on status exactly 'draft' only, never a status
  change. Do not widen either further — any new field or status needs
  the owner's order, and a row in `tests/ingest-edit.test.php`.
- **Untrusted URLs** are fetched only through `http_get()`/`pp_http_get()`
  (app/transport.php): destination policy, per-hop redirect validation,
  pinned connections, streamed size caps. Never hand a user-supplied URL
  to raw curl.
- **Stored images** (24 Nov 2026): every image written under /uploads
  goes through `pp_image_clean()` (`app/imagemeta.php`), which strips
  embedded metadata and refuses a file it cannot walk. Never
  `move_uploaded_file()`, copy or write image bytes there directly. A
  new upload path writes the cleaned bytes, and gets a case in
  `tests/imagemeta-http.test.php`.
- **First administrator** comes from `php tools/setup-admin.php` on the
  server; the login page never creates accounts.
- **Indexing is per-site opt-in** (`indexing_enabled`, default OFF —
  every page noindex until an administrator presses Open in Settings'
  "Search engines" panel). Open refuses while any of the paper's
  demonstration stories is live or its pages name another host
  (`pp_indexing_readiness()`); a pack of invented editorial says
  `'demonstration' => true`. Never set the switch any other way.
- **CI** (`tools/render-gate.sh`): `[render]` in a PR title excuses ONLY
  a classified rendered-output diff (baseline exit 10) — and PR titles
  are environment data, never shell source.
