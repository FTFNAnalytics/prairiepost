# Claude notes — the Prairie Dispatch network

One codebase serves the whole network (eight papers as of Western Wire).
Each paper is a `site_slug` mapping in the server-only `config.php`, a row
in the shared Supabase database, a front template in `app/views/`, assets
in `assets/sites/<slug>/`, a launch pack (`launch.php`) applied by
`tools/seed-launch.php`, and a `DEPLOY-<NAME>.md` runbook. The default
branch `claude/prairie-post-news-site-hiffgl` IS the production release
branch — the VPS pulls its HEAD. Land changes there via PR; never assume
a feature branch is what production runs.

## Launching a site: lessons from the rollouts (Brampton, Western Wire)

These are the failure classes that have actually bitten deployments.
Check every new runbook and every set of deployment-agent instructions
against this list before shipping them.

1. **Verify only what exists at that step.** A site's identity
   (`site_title`, `tagline`, `regions`) and all content are written by
   the step-5 seeder — they do not exist at the config-mapping step.
   Chrome checks before seeding are structural only (template class,
   stylesheet, layout); identity and content checks belong after the
   seed, with an explicit "do not stop over their absence" note in the
   earlier step. This same ordering defect shipped twice (Brampton's
   ticker/Brief rail, Western Wire's wordmark/province links). When
   writing a Verify item, trace each expected value to the step that
   writes it.

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

9. **Keep what worked:** hard stop after two failed verifications with
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

## Current operational state (as of the Western Wire launch)

- westernwire.ca is live (site_id 8, slug `westernwire`), served from
  the shared release; TLS via certbot, expiry Nov 2026.
- Western Wire uses the network-wide fetch cron — no dedicated cron.
- Its newsletter (`newsletter_enabled`) is OFF pending owner mail setup
  (sixam@/tips@ mailboxes, SMTP identity, mailing address, SPF, DKIM,
  test send).
- A pre-Western-Wire backup of the live config exists at
  `/root/prairiepost-config-pre-westernwire-20260811T041024Z.php`.
