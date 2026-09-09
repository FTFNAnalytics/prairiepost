# Phase 2 handoff — database migrations, verification, backups, recovery

For the independent auditor. Finding IDs F07 and F12 trace to the
original audit; the Phase 1 carryovers are named as such. The Phase 1
handoff (docs/build/phase-01-handoff.md) is preserved unedited as
historical evidence; where this phase supersedes an instruction there,
the correction is HERE.

## 1–4. Revision record

- Repository: https://github.com/FTFNAnalytics/prairiepost
- Working branch: `codex/phase-02-migrations-recovery`
- Base SHA: `9ab9c4f1fb83ffee100ed2abeb025c0438d88b3c` — the verified tip
  of the Phase 1 audit branch `claude/western-wire-aggregator-iw8pth`,
  which remains unmerged and untouched (its own base was the audited
  release head `3328704…`). No Phase 1 audit corrections had arrived
  during this work; none are incorporated beyond Phase 1 as pushed.
- Head SHA: the tip of this branch (the commit adding this document);
  implementation commits, in order: `2bf4c6b` (steps 1–3), `77305f3`
  (step 4), `19009a3` (steps 5–6), `af77977` (step 7), then step 8 and
  this handoff.
- PR: none opened (not requested).

## 5–7. What changed

### F12 — unsafe automatic migration

**Was:** `db()` installed, seeded and migrated on first use, from any
request; a status query could create the schema; rollback of code left
no account of schema state; `supabase/schema.sql` described version 17
while the code was 19.

**Is:**
- `db()` (app/bootstrap.php) connects and VERIFIES once per process
  (`pp_schema_status`): `ready` serves; `empty`/`behind`/`ahead`/
  `partial` refuse — web requests get a controlled 503 (`Cache-Control:
  no-store`, `Retry-After`, unbranded, no internals; detail to the error
  log), CLI jobs exit non-zero before business writes. A missing SQLite
  file is reported without being created. Static assets serve with no
  database at all. `pp_domain_site_slug` no longer swallows database
  errors into a wrong-tenant fallback, and an unknown tenant refuses
  instead of being seeded into existence (`current_site` creates
  nothing; `pp_current_site_or_null` exists for legitimately global
  contexts — the audit log's site-0 sentinel rows).
- `tools/migrate.php` is the only mutator. `--status` / `--dry-run`
  never write; `--apply` installs fresh (schema only — seeding is the
  separate, explicit `tools/seed-core.php` + per-paper
  `tools/seed-launch.php`, which now provisions its own site row) or
  runs pending steps one at a time. Steps live in `pp_migrations()`
  (app/db.php) — the former ladder sliced verbatim into per-version
  closures, each journaled in `schema_migrations` with a sha256 of its
  source as applied.
- Locking: one runner per LOGICAL database/schema — Postgres session
  advisory lock keyed on the schema name with a bounded `lock_timeout`
  (PP_MIGRATE_LOCK_TIMEOUT) and a same-backend proof that REFUSES
  transaction poolers (`db.pgsql.maintenance` config overlay points the
  runner at the direct endpoint); SQLite `BEGIN IMMEDIATE` +
  busy_timeout; MySQL `GET_LOCK`. Scratch schemas never contend.
- Crash consistency: Postgres/SQLite commit each step's DDL and its
  journal row as one transaction; MySQL (auto-committing DDL) records
  'started' first — a surviving 'started' row classifies the database
  `partial`, the application refuses to serve it, `--apply` refuses to
  march past it, and `--apply --resume-partial` completes it ONLY when
  the step's catalog markers (created tables probed WITH their first
  column, added columns) are really present; otherwise it prints the
  exact step definition for manual completion and stops.
- Adoption: a journal-less database (everything built before this
  phase) is adopted only after `mg_validate_claimed_version` confirms
  the catalog matches the claimed version — every marker at or below it
  present, every later marker table absent. Inconsistent claims are
  refused with the discrepancies listed; nothing fabricates history or
  bumps a partially migrated database.
- Migration 20 (the only new step): three supporting indexes from the
  integrity pass. Fresh-install parity enforced: the same indexes are in
  `pp_schema_ddl`, and the fresh-vs-upgraded catalog comparison caught a
  REAL pre-existing drift (`idx_social_shares_post` existed only on the
  migration path) which is fixed.
- `supabase/schema.sql` is now GENERATED (`tools/schema-sql.php
  --check|--write`) at version 20 and proven equivalent by LIVE catalog
  comparison: a database bootstrapped from the SQL file and one
  installed by the runner have identical columns, nullability, and
  indexes on PostgreSQL 16, both stamped and journaled. The v17
  standalone bootstrap no longer exists to document.
- Release integration (`tools/vps/upgrade-papers.sh`): preflight
  (candidate carries the runner and the vendored sanitizer; PHP
  extensions; a FRESH VERIFIED backup set is a hard gate, overridable
  only by an explicit logged flag) → build release dirs → quiesce
  (affected cron files paused via dotted rename; resumed only after
  verification; rewritten cron entries point at gated new code) →
  migrate each logical schema ONCE (identity = driver+endpoint+db+
  schema) BEFORE the serving switch, aborting with full vhost/cron
  restore on failure → nginx test/reload → verify → resume. Old-code
  writers during the window are governed by `docs/MIGRATION-COMPAT.md`:
  the per-migration compatibility table (v20: old code reads AND writes
  safely; the Phase 3 placement rework row is PRE-DECLARED as requiring
  the full-stop procedure), plus the standing rules — repointing code
  reverses nothing, restoring the database is a separate whole-schema
  operator decision, and pre-Phase-1 releases are never a fallback.

### F07 — incomplete backups, inadequate restore verification

**Was:** the nightly job archived `config.php` wrappers but not the
`app/config.site.php` they require, suppressed box-state tar failures,
had no manifest or checksums, and the drill staged dumps world-readable,
continued after failed `pg_restore`, and accepted `posts >= 0`.

**Is:** `tools/backup.sh` (repo-versioned; `tools/vps/setup-backups.sh`
is now a thin installer pointing cron at the RELEASE's copy so backup
logic rolls with releases) — see the step 5–6 commit message and the
script headers for the full inventory. Key properties, all tested:
umask 077 from creation; both config layers per active release,
verbatim; wrapper-without-config.site.php is a run failure; pg_dump via
a 0600 passfile; uploads/vhosts/cron archives with read-back validation;
logical counts + sha256 manifest with DECLARED external dependencies
(TLS certs, nginx globals, OS packages, code-by-SHA, role provisioning);
staged → atomic publish; unique set ids; truthful JSON state (PHP-
encoded); whole-set retention that never removes the last verified set;
overlap lock with stale-pid reclaim; optional off-site (openssl
aes-256-pbkdf2, key file, never argv) that is required-failing or
loudly-unverified, never silent — `cron/watch.php` narrowly surfaces a
failed off-site transfer even when the local run succeeded.

`tools/restore-drill.sh` (replaces `tools/vps/restore-drill.sh`):
manifest-first verification (presence, bytes, sha256 — corrupt or
truncated sets never begin), archive-member validation before extraction
(absolute, `..`, escaping link targets refused), explicit FRESH targets
only (the schema name comes from the manifest; an existing schema — i.e.
production — is refused by construction), fatal `pg_restore
--exit-on-error`, EXACT count and version equality against the manifest,
a live sequence probe, machine-readable results.

### Phase 1 carryovers closed

- **Tests hardcoded SQLite** → `tests/lib/fixture.php`: every DB suite
  builds through an engine-aware facility (`PP_TEST_DB=sqlite|pgsql|
  mysql` + connection env), asserts the ACTUAL PDO driver and server
  version into its output, owns a unique schema/database/file identity,
  and destroys only its own. CI gained real PostgreSQL 16 and MariaDB
  service jobs running the full suite.
- **MySQL unchanged-save conflict** → the MySQL connection sets
  `PDO::MYSQL_ATTR_FOUND_ROWS` (matched-rows semantics); every
  `rowCount()` consumer audited (two DELETEs, one always-changing claim
  — all safe); `pp_guarded_update_failure()` distinguishes missing from
  state-refused for MESSAGES only (the atomic update stays the
  enforcement); tests/guarded.test.php proves the allowed unchanged
  save, the refusals, and the two-connection race on all three engines.
- **Apache/nginx never exercised** → `tools/test-server-enforcement.sh`
  runs REAL nginx+FPM (a make-vhost.sh block AND a patched legacy block)
  and REAL Apache+FPM through the committed `.htaccess`: 81 checks. And
  because a template protects only new sites, the upgrade roll now
  installs `tools/vps/pp-deny.conf` as a shared snippet and injects an
  include into any enabled vhost predating the rules (asserted in the
  synthetic release flow).
- **Shared render database** → `tools/baseline.sh` gives each tree its
  own fixture: base seeded by the REF's own tooling, head a `VACUUM
  INTO` copy that only the head's runner migrates; logical-count
  equivalence checked after migration and after every render. New
  NON-waivable classes 6 (head fixture migration failed) and 7 (fixture
  divergence); `[render]` still excuses exit 10 alone.

### Reopened Phase 1 defects

None found. Three latent defects OUTSIDE Phase 1's scope surfaced and
are fixed: the fresh-DDL index drift (above), a SQLite WAL-switch race
on simultaneous first connections (busy_timeout ordering + tolerant
switch — found by hammering the concurrent-runner test), and
`setup-admin` crashing after account creation on a migrated-but-unseeded
install (audit's `current_site` dependency; fixed via the site-0
sentinel path, test-covered).

## 8–11. Reproduction

Runtime used: PHP 8.4.19 CLI + FPM 8.4 (ext: pdo_sqlite, pdo_pgsql,
pdo_mysql, dom, curl, mbstring, intl, gd, posix), SQLite 3.45.1,
PostgreSQL 16.13, MariaDB 10.11.14, nginx and Apache 2.4 from Ubuntu
24.04. CI runs the same suites on ubuntu-latest with service containers
(see .github/workflows/php-ci.yml).

    # fast, per engine (each DB suite prints the engine it really ran on)
    php tests/run.php
    PP_TEST_DB=pgsql PP_TEST_PG_HOST=… PP_TEST_PG_PORT=… \
      PP_TEST_PG_DB=… PP_TEST_PG_USER=… [PP_TEST_PG_PASS=…] php tests/run.php
    PP_TEST_DB=mysql PP_TEST_MY_SOCKET=… (or HOST/PORT/USER/PASS) php tests/run.php

    # harnesses (slow, real seeds/renders/servers)
    bash tools/seed-all.sh /tmp/net.sqlite
    bash tools/baseline.sh                      # smoke
    bash tools/baseline.sh <ref>                # two-fixture compare
    bash tools/test-baseline-classes.sh         # 7 classifications
    bash tools/test-release-flow.sh             # synthetic upgrade roll
    bash tools/test-server-enforcement.sh       # real nginx + Apache
    php tools/schema-sql.php --check
    php tools/integrity-report.php
    PP_CHROMIUM=… node tools/xss-browser-check.mjs …   # as Phase 1

Historical fixture provenance: the v18 network database is seeded by
commit `83e1467`'s own `tools/seed-all.sh` (test-release-flow does this
live); the v16 fixture by commit `41abec7`'s auto-install boot plus its
`seed-launch` (commands in the step 1–3 commit message). No fixture
simulates an old schema by editing a version stamp.

## 12–18. Migration interface details

Interface, exit codes (0/2/3/4/5/6/7), locking, journaling, adoption,
resume and repair are documented in the `tools/migrate.php` header;
dry-run examples appear in tests/migrate.test.php's transcript.
Interruption rule: transactional engines leave nothing half-recorded
(rerun after removing the obstacle); MySQL leaves a 'started' row that
gates the application off and resumes only against proven catalog
markers. Verification/compatibility limits per migration:
docs/MIGRATION-COMPAT.md.

## 15. Privileges

Documented grant set for the PostgreSQL runtime role (and proven in
tests/privileges.test.php against a live server):

    GRANT CONNECT ON DATABASE …;
    GRANT USAGE ON SCHEMA prairiedispatch TO runtime;
    GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA … TO runtime;
    GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA … TO runtime;
    REVOKE INSERT, UPDATE, DELETE ON prairiedispatch.schema_migrations FROM runtime;

The suite proves the runtime role serves the whole application
(rendering, search, feeds, a real INSERT) and cannot CREATE/ALTER/DROP
tables or schemas nor write the journal (it must READ it — the
readiness gate depends on that); the migration runner under that role
fails cleanly without echoing credentials and writes nothing. Migration
credentials belong in the maintenance overlay (`db.pgsql.maintenance`),
which ordinary FPM config need not carry. First-admin provisioning
needs the runtime grants only (its lock is `LOCK TABLE users`, which
table-DML grants permit on Postgres — verified by the setup suite on
pg). Backup needs read (pg_dump) on the schema; restore drills need a
role able to create objects in a FRESH database only.

## 19. Integrity

`php tools/integrity-report.php` — read-only, ids only. On a freshly
seeded 17-paper network: **0 orphans across 21 audited relations**; four
uncovered lookups found, three with real query paths became migration
20. Owner decisions deliberately left open (the report prints them):
foreign keys on join tables (needs SQLite rebuilds + fresh-DDL parity
work), the settings/audit `site_id = 0` sentinel exemptions, and
`posts.author_id` (launch packs seed author 1 before any user exists —
a plain FK would reject every pack). No cascade deletion anywhere; no
data was repaired or deleted.

## 20–27. Backup and restore evidence

Synthetic manifest example (from tests/backup.test.php's happy path):
set id `YYYYMMDD-HHMMSS-xxxxxxxx`; files db.dump / uploads.tar.gz /
vhosts.tar.gz / cron.tar.gz / config/<release>/{config.php,
config.site.php} / releases.txt, each with bytes+sha256+mode; counts
{sites, posts, users, domains, post_revisions, subscribers};
excluded_external_dependencies as listed above. Encryption: openssl
`aes-256-cbc -pbkdf2 -pass file:<key>`; key recovery = the operator's
copy of `/root/civis-backup.key` held OFF the box (documented in
setup-backups.sh; the key is never inside the encrypted set). Off-site
policy: optional by default — a local-only success explicitly reports
off-site recovery UNVERIFIED in state.json and the ops watch;
`PP_OFFSITE_REQUIRED=1` makes transfer failure a run failure (local set
preserved either way).

Failure/retention and status-reader behavior: tests/backup.test.php
(every required component's failure produces exit≠0 + ok:false + intact
prior sets; incomplete sets can never look healthy because ok:true is
written only after full validation and atomic publish).

Isolated restore: tests/restore.test.php (sqlite AND real PostgreSQL,
~5s on this synthetic fixture — NOT a production estimate). Source
destroyed; recovery from the transferred ENCRYPTED copy + declared
inputs (the key, the release by SHA via git archive — pinned sanitizer
verified inside); drill into a freshly created database; served and
verified: fronts and domain mappings, per-site settings, syndicated
body, draft invisibility, scheduled row, revisions incl. the sanitizer
snapshot, upload hash equality, admin login, founding-admin refusal,
author-state guard, XSS inertness, indexing directives (the one
explicitly enabled site and only it), journal presence. Confidentiality
and refusal: private modes throughout; incomplete, corrupt,
path-traversal and non-empty-target sets all refuse (nothing escapes,
nothing existing is touched). **A real off-site service rehearsal on a
clean host with production-size data remains for the operator** — see
the outstanding table.

## 28–32. Release runbook updates

The immutable-release model is unchanged. The roll (upgrade-papers.sh)
now: preflights (extensions, vendored code, migrate --status semantics
via the candidate runner, verified recovery point), quiesces scheduled
writers, migrates each shared schema exactly once BEFORE switching,
verifies, resumes. Operator prerequisites: a fresh backup set (the
script enforces it), and — one-time, after this phase deploys — the
adoption run journals the production database at its validated version.
Configuration semantics preserved: configs are copied verbatim,
host-dependent logic is never evaluated flat, and the wrapper/
config.site.php split is honored everywhere (backup, drill, roll).
Rollback and forward repair: docs/MIGRATION-COMPAT.md.

## 33. Outstanding verification

| Item | State |
| --- | --- |
| SQLite suites | RUN — passing (3.45.1) |
| PostgreSQL suites | RUN — passing (16.13, disposable local server; CI job added) |
| MySQL/MariaDB suites | RUN — passing (10.11.14; backup/restore suites SKIP by design — the backup job dumps pgsql/sqlite only) |
| Apache/nginx enforcement | RUN — passing (81 checks, real servers, disposable fixtures) |
| Transport fixture limits (Phase 1) | CARRIED — live bad-certificate TLS and live IPv6-literal fetches remain fixture-limited exactly as documented in the Phase 1 handoff |
| GitHub default branch | STILL the obsolete `claude/prairie-post-news-site-hiffgl`; repository-admin flip to the release branch remains a manual action |
| Live content cleanup (sanitize-content --apply) | NOT RUN on production, per instructions (operator step post-deploy) |
| Per-paper indexing enablement | NOT DONE anywhere live, per instructions |
| Production deployment / live migration / live restore | NOT DONE, per instructions |
| Real off-site transfer + clean-host restore rehearsal | NOT RUN — no off-site service exists yet; the exact rehearsal is: install the off-site hook + key, run tools/backup.sh, copy the .tar.enc and the key to a clean host with the release by SHA, decrypt, run tools/restore-drill.sh with explicit fresh targets, and replay tests/restore.test.php's verification list |
| Production-size recovery timing / objectives | NOT MEASURED — the 5s figure is the synthetic fixture only |

## Summary for the owner (paste to the auditor)

Phase 2 on branch `codex/phase-02-migrations-recovery` (base = Phase 1
tip `9ab9c4f`, untouched). What changed: requests can no longer install,
migrate or seed anything — an unprepared schema answers a controlled
503 and CLI jobs stop, while `tools/migrate.php` (journaled,
checksummed, per-schema-locked, adoption-validating, crash-consistent,
resume-with-proof) is the only mutator, integrated into the release roll
as preflight → quiesce → migrate-once-per-schema → switch → verify →
resume, with a per-migration compatibility table. Backups are complete
sets (both config layers, uploads, server state, manifest with
checksums and declared external dependencies), private from creation,
truthful about every failure, rotated whole; the restore drill refuses
corrupt/hostile/non-fresh targets and verifies EXACTLY, and a full
isolated exercise — source destroyed, recovery from the encrypted
off-site copy into fresh PostgreSQL — passes including every Phase 1
security behavior. Database tests genuinely run on SQLite, PostgreSQL
16 and MariaDB (drivers asserted in evidence; CI service jobs added);
the MySQL unchanged-save defect is fixed with matched-rows semantics;
real nginx and Apache enforce the path rules (81 checks), and existing
live vhosts receive them via an injected shared snippet on the next
roll. Render comparisons use separate per-tree fixtures with two new
non-waivable failure classes. Not done, deliberately: anything on the
live VPS, real off-site rehearsal, indexing enablement, merges.
Reviewer focus: the migration runner's crash/resume semantics
(tools/migrate.php + tests/migrate.test.php), the adoption validator,
the restore drill's refusal paths, and the quiesce/compatibility story
in docs/MIGRATION-COMPAT.md.
