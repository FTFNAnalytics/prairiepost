# Migration compatibility and rollback limits

Every schema migration must declare, BEFORE it ships, what happens to
code that is older than it — because on this network several release
directories share one database, and during the upgrade window (and after
a code rollback) old code runs against the new schema.

## The rules

1. **Repointing code does not reverse data or schema changes.** Rolling
   a release group back to its old directory leaves the database at the
   NEW version. That is only safe when the table below says the old code
   can still read AND write it.
2. **Restoring a database from backup is a separate operator decision.**
   It discards every write since the backup, for every paper sharing the
   schema — it is never an automatic per-paper rollback, and
   `upgrade-papers.sh` will never do it for you.
3. **The pre-Phase-1 releases are not a fallback.** Rolling back past
   `e5c5fe0` reintroduces audited vulnerabilities (stored XSS, the
   approval bypass, SSRF, first-visitor admin). Fix forward.
4. **A migration old code cannot write through requires the full-stop
   procedure**: pause the cron files, put nginx on the maintenance page
   for the affected vhosts, migrate, switch releases, verify, resume.
   `upgrade-papers.sh` refuses nothing here — the AUTHOR of such a
   migration must say so in this file, and the operator must follow the
   full-stop runbook instead of the ordinary roll.

## How writers are quiesced on an ordinary roll

`upgrade-papers.sh` (from Phase 2):

- pauses every affected cron file for the migration window (cron.d
  ignores dotted filenames) and resumes them only after verification;
- rewrites cron files to the new release, whose readiness gate makes any
  early run exit non-zero before business writes;
- the authorized web-cron route on NEW code meets the same gate; on OLD
  code it keeps writing, which is exactly why rule 4 exists — the
  ordinary roll is only ordinary while migrations stay old-write-safe;
- migrates each logical schema exactly once, whatever number of release
  groups share it, under the runner's per-schema lock.

## The table

| Migration | What it does | Old code reads? | Old code writes? | Cutover requirement | Rollback after it ran |
| --- | --- | --- | --- | --- | --- |
| ≤ 19 (historical ladder) | pre-Phase-2 history; adopted into the journal after catalog validation | — | — | already deployed everywhere | not applicable |
| 20 (Phase 2) | three additive indexes (post_tags, news_items, audit_log) | **yes** | **yes** | none — ordinary roll | old (v19) code runs unchanged against v20; repoint freely |
| journal table (`schema_migrations`, runner-owned) | additive bookkeeping table | **yes** (ignored) | **yes** (untouched) | none | old code never reads it |
| 22 (NEWS1, draft-only ingest tokens) | one additive column, `ingest_agents.draft_only INTEGER NOT NULL DEFAULT 0`; every existing token gets 0 and keeps its behaviour | **no** — it refuses: a release built for 21 has the exact-version readiness gate, reads 22 as `ahead` and answers 503 | **no** — it stops before any business write (cron and CLI exit 2), so it cannot write anything invalid | ordinary roll, no full-stop (old code cannot write). Every paper answers 503 from the moment the step commits until nginx reloads onto the new release | **not by repointing** — a release built for 21 answers 503 on every paper. Fix forward, or restore the database (rule 2). Read "Step 22: before you run the roll" below |
| FUTURE: Phase 3 placement rework | replaces network-global placement writes | to be declared | **expected NO** | full-stop for the write paths, or a feature-flag stop of old writers, BEFORE the cutover — the mechanism exists now (pause crons + maintenance page per vhost); Phase 3 must fill in this row before it ships | to be declared |

**Retaining old columns does not make old writers safe** — an old writer
can still produce rows the new code considers invalid. The question this
table answers is about WRITES, not about columns existing.

## Step 22: before you run the roll

Read this before `tools/vps/upgrade-papers.sh` carries a release built
for schema 22 (the release branch at or after `8e9eb4c`) onto a box
whose database is at 21.

**What the step is.** One statement, `ALTER TABLE ingest_agents ADD
COLUMN draft_only INTEGER NOT NULL DEFAULT 0`, and the version stamp.
It is additive and needs no backfill. Every token that already exists
gets `draft_only = 0`, which is exactly the behaviour it had before:
wire desks and `publish_on_image` still publish for it, and its scope
and revocation are untouched. Nothing becomes draft-only until someone
mints a token with `--draft-only` (`DEPLOY.md`, section 11).

**What is different about it: there is no way back by repointing.** The
new column would not trouble old SQL, but old code never gets that far.
Every release since Phase 2 refuses to serve unless the stored schema
version equals the one it was built for (`db()` in `app/bootstrap.php`;
`tests/unavailable.test.php` holds it to that). Against a database at
22, a release built for 21 classifies the schema as `ahead` and answers
503 "This site is being updated" on every paper it serves; its cron and
CLI jobs exit 2 before any business write. So old code cannot damage
anything, and it cannot serve anything either. Three things lead back
to such a release, and once this step has run all three end in 503:

- the script's own group restore, when a domain fails the masthead
  check after the reload — it copies that group's old vhost and cron
  files back and reloads nginx;
- its restore after a failed `nginx -t` — the message says "nothing
  changed", but by then the schema has;
- the "Rollback for any release group" lines it prints when it is done.

The migration itself failing is the one safe case. On PostgreSQL the
step and its journal row commit together or not at all, so a failed
step leaves the schema at 21, the script restores the vhost and cron
files, and the old release goes on serving.

**The window.** The script migrates before nginx reloads. From the
moment the step commits until the reload completes, the old release is
still the one answering, so requests that reach it get 503 for that
interval — seconds on an ordinary roll. Scheduled writers are paused
for it, and both release groups (the papers and the hub) share the
schema, so it applies to all of them at once.

**What recovery actually is.**

1. **Fix forward.** Run the ordinary roll again, pinned to a commit
   built for schema 22. The schema is already at 22, so
   `tools/migrate.php --apply` reports nothing pending and only the
   code switches. A group the script restored stays on 503 until this
   is done. This is the path to plan for.
2. **Restore the database from the pre-roll backup.** That is rule 2:
   a separate operator decision that discards every write since the
   backup, for every paper and the hub together. The repository has no
   in-place restore for production — `tools/restore-drill.sh` restores
   a set only into fresh space and refuses a database in which the
   schema already exists — so returning to 21 means serving from a
   restored copy, which needs a guarded config change and has never
   been rehearsed against production (`docs/build/phase-02-handoff.md`
   records the live restore as not done).

There is no third path. `tools/migrate.php` has no downgrade and
refuses an ahead schema (exit 6), and editing the version stamp or
dropping the column by hand is a manual database edit, which this
network does not make.

**So, before the roll:** confirm the pre-roll backup is the verified
recovery point (the script's preflight insists on a fresh one), pin the
roll to the exact commit, and have the fix-forward ready — a second
pinned roll — instead of counting on the old release.

## Forward repair

When a migration fails mid-run, `tools/migrate.php --status` classifies
the database (behind / partial / inconsistent) and prints the repair:
transactional engines roll the failed step back whole (rerun after
removing the obstacle); MySQL's `--apply --resume-partial` completes a
step only when its catalog markers prove it landed, and otherwise prints
the exact step definition to finish by hand. The application refuses to
serve anything but a ready schema in the meantime, which is the guard
rail that makes repair unhurried.
