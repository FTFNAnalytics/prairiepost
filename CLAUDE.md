# Claude notes — the Prairie Dispatch network

One codebase serves the whole network (ten papers as of the Sudbury
Standard). Each paper is a tenant mapping in the server-only config, a
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

## Current operational state (as of the phase-1 machinery pass)

- **Release in production: `ef183469e9b0`**, at
  `/var/www/prairiepost-ef183469e9b0-shared`, serving all thirteen papers
  as one release group. Schema version 15.
- **Tenant resolution is database-first.** All 22 paper hostnames have
  `domains` rows and resolve from them (`tools/resolve-host.php` says
  `db` for every one); the config selector remains as a do-nothing
  fallback until its arms are retired. A new paper's launch is now
  config-edit-free: DNS, generated nginx block, cert, seed.
- **A catch-all `default_server` is live** on both ports and both
  address families, returning 444 behind the self-signed
  `reject.invalid` certificate at `/etc/nginx/reject/`. Unknown
  hostnames and bare-IP probes no longer fall through to Brampton/the
  Institute. It is the only block allowed to say `default_server`.
  All thirteen paper blocks are dual-family symmetric (Brampton,
  Kelowna and Turtle Island were repaired in this pass).
- One soft spot from the pass: nginx's combined log format does not
  record the Host header, so bare-IP monitoring could only be ruled out
  indirectly. If some external uptime check goes red after this date,
  point it at a named paper hostname — the bare IP now answers 444.
- The **CivisMedia hub** runs from its own release,
  `/var/www/prairiepost-d298039b1d40`, and is deliberately left behind by
  every paper upgrade. The Institute (`cies`, `/var/www/cies-v0.17`) is
  an unrelated site on the same box — never touch it.
- The thirteen papers: prairiedispatch.ca, edmontonecho.com,
  thepacificpost.com, kelownacurrent.ca, kermodechronicle.ca,
  grandeprairiegazette.ca, bramptonbulletin.com, westernwire.ca,
  tricitiestorch.ca, sudburystandard.ca, turtleislandtimes.ca,
  pickeringpost.ca, bleuetblanc.ca — twenty-two hostnames with the `www`
  variants. Discover the live set from the enabled nginx blocks rather
  than from this list.
- **bleuetblanc.ca** (site #14, slug `bleuet-blanc`, tenant key equal to
  the slug) is the network's first French-language paper, launched with
  its 25-story demonstration package — all slugs `bb-`-prefixed, zero
  collisions on seed. Its ten desks are new to the network
  (`culture-qc`, `sports-qc`, `le-fil`, …). TLS expiry Nov 22 2026.
- **`php8.3-intl` is now installed on the box** (a one-time exception to
  the service-action rule, authorized for the French launch). The i18n
  layer degrades to English dates without it, so an English masthead
  dateline on a French paper is the symptom to check first.
- **turtleislandtimes.ca** and **pickeringpost.ca** both launched with
  **zero stories** by design: identity, desks and sources only, editorial
  from the newsroom. Their front pages carry the empty state until the
  desk files. That is the intended state, not an unfinished deployment.
- Every paper uses the network-wide fetch cron. No paper has a dedicated
  one.
- **No paper has its newsletter enabled.** All are pending owner mail
  setup (mailboxes, SMTP identity, mailing address, SPF, DKIM, test
  send).
- Pre-edit config backups live in `/root/`, one per launch, mode 0600.
- Merged feature branches and `deploy/torch-on-3fd4f13` are stale now
  that the network is on `8a037f365b77`.

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
2. **A new paper edits four shared files** — a dispatch arm in
   `index.php`, `article.php` and `section.php`, and header + footer
   chrome in `ui.php` (28 template branches there already). The Torch
   and the Standard conflicted in all four. Before papers 11–15, replace
   the if-chains with a convention (`front-{$template}.php` if it
   exists) and move each paper's chrome into its own partial, so a new
   paper is new files only.
3. **Six papers name a font they never declare.** `westernwire.css`,
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
