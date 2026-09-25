# Claude notes — the Prairie Dispatch network

One codebase serves the whole network (twenty-one live tenants —
twenty papers and the CivisMedia hub — as of the Rideau Review). Each paper
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
   production.

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
  produces (`tools/wire-map.php`); there is no read API.

The dependency runs one way: when a task needs both (deploy, then file),
brief the VPS agent first, wait for its report, then brief the news
agent. Never give the news agent a shell command or the VPS agent a
filing step — each such mix has produced a stopped run or an agent
holding a capability it should not have.

## Current operational state (as of the inaugural-editions roll, 25 Sep 2026)

- **Release in production: `8b91a9f4bb73`** (branch head
  `8b91a9f4bb7310685f3a82fb225cb66fea727907`), serving BOTH release
  groups: `/var/www/prairiepost-8b91a9f4bb73-shared` (the papers) and
  `/var/www/prairiepost-8b91a9f4bb73-civismedia` (the hub). Both
  groups roll together; `upgrade-papers.sh` upgrades every prairiepost
  group and migrates their shared schema exactly once. Never write a
  brief that forbids touching the hub release (that stale rule aborted
  the first Phase-2 roll).
- **Twenty-one live domains pass the masthead guard**: the seventeen
  original papers plus surreystandard.ca (site #18),
  cariboocompass.ca (#19), burrardbrief.ca and rideaureview.ca
  (site #21), plus the hub. TLS on the four newest runs to
  Dec 22-24 2026.
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
- **Schema version 20, journaled.** `tools/migrate.php` is the only
  mutator, the app refuses (503/exit 2) on a non-ready schema, and
  the roll migrates before any traffic switch.
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
  `20260925-031702-404fd26a`); discovery scoped to
  `/var/www/prairiepost-*` roots, so foreign tenants read as
  out-of-scope by design. Off-site transfer is NOT configured — an
  owner step (hook + key).
- **The box hosts FOREIGN tenants**: the Institute
  (`/var/www/cies-*`) and Calgary Dispatch (`/srv/calgarydispatch/…`,
  its own vhost). Network tooling ignores them by root-prefix and
  briefs must never require anything of them. Shared-fate caveat: any
  tenant's broken vhost fails the global `nginx -t`.
- **One tenant remains dormant in the tree**: `terminal-city-times`
  (foundation only, DEPLOY-TERMINALCITY.md, awaiting its brand
  package). The wider slate (Steeltown, Red River, Winnipeg, Rideau
  siblings, Atlantic papers) awaits packages.
- **Deploys are pinned**: the VPS agent resolves the release branch
  head via the API, requires the exact full SHA from the brief, and
  fetches `upgrade-papers.sh` at that SHA. Rolls are preceded by a
  fresh verified backup (the preflight enforces it, 26-hour window).
  Launch-only work discovers `$REL` from the enabled nginx blocks and
  needs no pin.
- Known benign gap: `prairiedispatch.ca /api/ingest` answers 404 (its
  legacy block predates the pretty route; `/ingest.php` answers 401
  correctly). Fix belongs to a per-block routes pass, not a hand edit.
- Hermes tokens: unchanged (`hermes-quebec`, `hermes-mississauga`,
  root-only under `/root/hermes-tokens/`). No token exists for Surrey
  or any newer paper. No paper has its newsletter enabled.


## Known debt, in the order it should be paid

**The paydown schedule now lives in `PLAN.md`** — four phases and a
compliance gate that papers 14 and 15 are built against. The list below
remains the inventory; PLAN.md is the order and the acceptance tests.
Check any new-paper work against the PLAN.md gate before starting it.

1. **Every paper is indexable** — `robots.txt` is `Allow: /` with the
   sitemap advertised — and every paper's content is invented editorial
   about a real city, including figures and votes attributed to real
   councils. Either `Disallow: /` until the content is real, or replace
   the packs. This grows with each launch.
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

4. **Nginx has no `default_server` on 443, and the papers are not
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

5. **`upgrade-papers.sh` cannot detect template loss.** Its guard
   compares each domain's `<title>`, which comes from the database, so a
   paper whose CSS and templates are missing from the release still
   passes. Assert that each paper's expected stylesheet appears in the
   served HTML.
6. **`posts.slug` is UNIQUE network-wide.** At 15 papers that is ~225
   slugs in one namespace in the same register, and the seeder's failure
   mode is a silent `story exists, skipped`. Scope it per site or have
   the seeder prefix automatically.
7. **GitHub's default branch is the dead line.** Repoint it to the
   release branch so the trunk that is visible is the trunk that ships.

## Phase 1 security invariants (do not regress)

Full detail and evidence: `docs/build/phase-01-handoff.md`.

- **Stored HTML** renders and saves only through `sanitize_html()`, which
  is HTML Purifier (parser-based, vendored under `vendor/`, pinned in
  `composer.lock`) — never reintroduce regex sanitization. JSON inside
  HTML (JSON-LD, inline `var X =`) goes through `pp_json_for_html()`.
- **Post writes** (edit, autosave, restore, delete) ask
  `pp_post_write_denied()` against the PERSISTED row and, for authors,
  write through `pp_guarded_post_update()` with a state condition —
  published/scheduled stories change only at an editor's desk.
- **Untrusted URLs** are fetched only through `http_get()`/`pp_http_get()`
  (app/transport.php): destination policy, per-hop redirect validation,
  pinned connections, streamed size caps. Never hand a user-supplied URL
  to raw curl.
- **First administrator** comes from `php tools/setup-admin.php` on the
  server; the login page never creates accounts.
- **Indexing is per-site opt-in** (`indexing_enabled`, default OFF —
  every page noindex until an admin ticks the box in Settings).
- **CI** (`tools/render-gate.sh`): `[render]` in a PR title excuses ONLY
  a classified rendered-output diff (baseline exit 10) — and PR titles
  are environment data, never shell source.
