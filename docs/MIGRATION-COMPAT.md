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
| FUTURE: Phase 3 placement rework | replaces network-global placement writes | to be declared | **expected NO** | full-stop for the write paths, or a feature-flag stop of old writers, BEFORE the cutover — the mechanism exists now (pause crons + maintenance page per vhost); Phase 3 must fill in this row before it ships | to be declared |

**Retaining old columns does not make old writers safe** — an old writer
can still produce rows the new code considers invalid. The question this
table answers is about WRITES, not about columns existing.

## Forward repair

When a migration fails mid-run, `tools/migrate.php --status` classifies
the database (behind / partial / inconsistent) and prints the repair:
transactional engines roll the failed step back whole (rerun after
removing the obstacle); MySQL's `--apply --resume-partial` completes a
step only when its catalog markers prove it landed, and otherwise prints
the exact step definition to finish by hand. The application refuses to
serve anything but a ready schema in the meantime, which is the guard
rail that makes repair unhurried.
