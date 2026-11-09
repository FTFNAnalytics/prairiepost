# Migration compatibility and rollback limits

Every schema migration must declare, BEFORE it ships, what happens to
code that is older than it — because on this network several release
directories share one database, and during the upgrade window (and after
a code rollback) old code runs against the new schema.

## The rules

1. **Repointing code does not reverse data or schema changes.** Rolling
   a release group back to its old directory leaves the database at the
   NEW version. That is only safe when the table below says the old code
   can still read AND write it. Since Phase 2 a step that raises the
   stored version rules it out by construction, and `upgrade-papers.sh`
   no longer does it on such a roll ("When a roll raises the stored
   version").
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
- the authorized web-cron route meets the same gate, on NEW code and,
  since Phase 2, on OLD code too. A release with the gate checks the
  stored version each time a request or a job first opens the database
  (`db()` in `app/bootstrap.php`), so from the moment a step commits
  and raises the version, old code answers 503 and its jobs exit 2
  before any business write. It does not keep writing. Two things are
  left. A request or job that had already opened its connection when
  the step committed finishes on the old code. And code from before
  Phase 2 has no gate and does keep writing; no such release serves
  production any more. Rule 4 exists for that remainder — the ordinary
  roll is only ordinary while an old writer already in flight can
  finish safely against the new schema;
- migrates each logical schema exactly once, whatever number of release
  groups share it, under the runner's per-schema lock.

## When a roll raises the stored version

From the moment a step raises the stored version, the old release
directories are not a fallback (rule 1, and the gate above: a release
built for the earlier version answers 503 on every paper).
`upgrade-papers.sh` knows when that has happened. It reads the stored
version before the migration step and again after it — `php
tools/migrate.php --status` from the new release directory, which never
writes — prints both, and from then on does not point anything back at
the old release:

| Where the roll fails | The version did not change | The version rose |
| --- | --- | --- |
| `nginx -t` BEFORE the migration (run only when a step is pending, with the rewritten vhost files in place) | not run | every vhost and cron file restored, the schema untouched: "nothing changed" is true, and the old release serves |
| The migration | files restored; the old release serves | possible only when an earlier step of the same roll had committed: nothing is put back, and neither release can serve until the migration is finished from the new release directory |
| `nginx -t` after the migration | files restored; "nothing changed" | nothing is put back. nginx is still running the old release, so every paper answers 503 until the error is fixed and nginx reloads onto the new one |
| A domain fails the masthead check | its release group is restored — vhosts and cron files — and the old release serves | the group is HELD on the new release and the roll exits non-zero. The domain that failed goes on serving what failed; the other domains of the group are untouched |
| Nothing fails: the closing text | offers the repoint as a rollback | says the old directories are not one |

A reading the script could not take counts as a rise wherever the new
release is known to be ready, because "it did not change" would then be
a guess. Every cell is exercised by `tools/test-release-flow.sh`
(scenarios 2 and 4 to 10), which CI runs. In those scenarios the
verification stage is a CLI boot of each new release. Scenarios 11 to
14 run the masthead guard itself — the capture before the roll and the
per-domain comparison after it — against a stand-in that answers plain
HTTP from whichever release directory each vhost file names. No fixture
has nginx or TLS.

### When a roll that raised the schema version fails

A held group is the script declining to choose for you. After a rise
there are two bad options for a group with a failing domain, and it
takes the one that leaves the most papers up:

- **Put the group back** (what the script does when the version has not
  changed). After a rise that is 503 on every domain the group serves —
  twenty-four papers for one failing masthead — and an ordinary roll
  then holds each of them to that 503 ("What a second roll can and
  cannot do", below). The script no longer does this, and nobody should
  do it by hand for a group.
- **Hold the group on the new release** (what it does now). Every
  domain that passed keeps serving. The one that failed keeps serving
  whatever failed its check — another paper's masthead, a changed set
  of stylesheets, a different status code — in public, until someone
  acts.

From a held roll the operator has these moves:

1. **Leave the domain up.** Right when what failed is tolerable, and
   all there is to do when the guard objected to something intended,
   such as a stylesheet set the release changed on purpose: the next
   roll takes its baseline from what is served then. If the domain
   needs a fix, the roll that carries the fix needs the recovery
   baseline (below), or it will put the fix back.
2. **Take that one domain down.** Right when it is serving the wrong
   paper. Copy the roll's own backup of that single vhost back and
   reload: the old release answers the gate's 503 for that domain and
   nothing else moves. Keep the file the roll wrote, under a name the
   script ignores, in case the take-down has to be undone:

       cp <vhost> <vhost>.bak.<stamp>.new
       cp <vhost>.bak.<stamp> <vhost>
       nginx -t && systemctl reload nginx

   An ordinary roll leaves the domain down: it holds it to its 503 and
   puts its group of one back. It comes back with the roll that carries
   the fix, held to the recovery baseline (below), or by hand: once a
   fixed release is on the box, point this vhost's `root` at that
   release directory and reload. Not rehearsed on a server. The fixture
   runs the take-down, the ordinary roll that leaves the domain down
   and the recovery roll that brings it back (scenario 14), against its
   HTTP stand-in.
3. **Restore the database.** Rule 2, and not a way to get one paper
   back.

**What a second roll can and cannot do.** The masthead guard holds each
domain to what it answered when THAT roll started, not to what it
should answer. A second roll therefore replaces the release cleanly for
every domain that is healthy when it starts. For a domain that is
failing when it starts, the repair itself reads as a failure — 200
where it answered 503, the right stylesheet set where it served the
wrong one — and, because the second roll does not raise the version,
the script puts that domain's release group back where it came from. If
the failing domain is still in the papers' group, that undoes the fix
for all of them (no outage: they return to the first new release). If
it was taken down first (move 2) it is a group of one: the other papers
move to the fixed release and that domain stays on 503. Either way an
ordinary roll does not mend the failing domain, and it is meant not to:
a domain taken down on purpose answers the same 503, and no roll may
bring it back unasked. The fixture runs each of these (the assertions
marked GAP in scenarios 12 and 13 of `tools/test-release-flow.sh`, and
scenario 14). The roll also says so itself: a domain answering the
gate's 503 is named before anything changes, and a failed verification
ends with a pointer to what follows.

### The recovery baseline

What mends such a domain is a roll that has been told what the domain
SHOULD answer. The script cannot work that out, but an earlier roll
captured it while the domain was healthy.

- **Every roll saves the baseline it captured**, before it changes
  anything: `/var/lib/prairiepost-upgrade/baseline-<stamp>.txt`, one
  line per domain, `domain|status code|stylesheet set|<title>`, under a
  header that says when it was written and which release the roll was
  going to. The roll prints the path (`saved: …`). The file is a
  record. No roll reads one unless it is told to, and none is ever
  overwritten.
- **`PP_UPGRADE_BASELINE=<file>` holds a roll to one of them.** Each
  domain the file names is held to its record there instead of to what
  it answers when the roll starts: the same status code, `<title>` and
  stylesheet set, compared the same way. A domain the file does not
  name is held to what it answers now, as always. The file is read and
  checked before anything changes. One that is missing or malformed,
  or that names none of the box's domains, stops the roll there.
- **The output says which baseline each domain was held to**: where
  the baseline is taken (`held to the RECOVERY baseline: …`, with `and
  NOT to what it answers now: …` under it when the two differ), on
  every PASS and FAIL line (`[recovery baseline; when this roll started
  it answered 503]`), and in a closing list.

It is the ordinary roll with one more variable in its environment. Only
the reference changes: nothing about the comparison, and nothing about
what a failing group gets.

**Which file.** The one saved by the last roll that STARTED on a
healthy box.

- After a roll that ended "NOT done" with a group held: that roll's own
  file. Its closing text prints the line to use.
- After vhost files were copied back onto an old release, so that the
  papers answer the gate's 503: the file saved by the roll that raised
  the version, the one before the copy-back. An ordinary roll on such a
  box names the newest saved baseline in which those domains answered
  200 with a title.
- Read the file before using it. "The newest with a 200" is not
  "healthy": a roll that started while a paper was serving without its
  stylesheet saved exactly that, and a roll held to it fails the
  repaired paper.
- A box with no file from a healthy start (it broke before the first
  roll by a script that saves them) can be given one by hand, in the
  same format. What each domain should answer is in the output of its
  last good roll, which prints every title and stylesheet set.

**What it does not change.**

- An ordinary roll. With the variable unset there is no difference in
  what is captured, compared or printed, apart from the saved file and
  its one `saved:` line.
- What a failing group gets. A domain that does not match its record
  FAILS, and its release group is restored — or held, if this roll
  raised the version — as on any roll. On a box that came into the roll
  on the gate's 503, "restored" means back to 503 for every domain of
  that group, and the roll prints that the release it is restoring to
  is not built for the stored version. Correct the record or the
  release and roll again.
- A tenant with no site row. It answers 503 "This site is not
  provisioned here", and on a release built for another version it
  answers the gate's 503 like every other domain. Its record says 503,
  so it is held to 503. Nothing reads a 503 as "should be 200".
- A domain taken down on purpose (move 2). It stays down through every
  ordinary roll. Only a roll whose named baseline holds a healthy
  record for it brings it back, and only if it then answers as that
  record says.

**Limits.**

- A record is as old as the roll that saved it. A title comes from the
  database (the site title and tagline), so an editor's change since
  then makes that domain fail its record, and so does a release that
  changes a stylesheet set on purpose. The FAIL line prints both
  sides. Correct that domain's line and roll again.
- A hand-edited record can ask for less than a capture would. A 200
  with no title passes any 200, and a title with no stylesheet set
  skips that comparison. The roll says so under that domain's line when
  it reads the file.
- A domain taken down alone and brought back by a recovery roll lands
  in a release directory of its own, `prairiepost-<sha>-<vhost>`,
  because it is no longer in the papers' group, and later rolls keep
  rolling it as its own group. To rejoin it, point its vhost's `root`
  at the papers' directory and reload.
- A box where nginx never loaded the new release (the roll said `nginx
  test failed AFTER the migration`) is not a case for a roll. Fix the
  nginx error and reload.
- Run by the fixture only, on SQLite, against a stand-in that serves
  what the vhost files name. Not exercised: real nginx and TLS,
  PostgreSQL, the production box.

## The table

| Migration | What it does | Old code reads? | Old code writes? | Cutover requirement | Rollback after it ran |
| --- | --- | --- | --- | --- | --- |
| ≤ 19 (historical ladder) | pre-Phase-2 history; adopted into the journal after catalog validation | — | — | already deployed everywhere | not applicable |
| 20 (Phase 2) | three additive indexes (post_tags, news_items, audit_log) | **yes** | **yes** | none — ordinary roll | old (v19) code runs unchanged against v20; repoint freely |
| journal table (`schema_migrations`, runner-owned) | additive bookkeeping table | **yes** (ignored) | **yes** (untouched) | none | old code never reads it |
| 21 (the publish-on-image lane, `26d5171`) | one additive column, `posts.awaiting_image INTEGER NOT NULL DEFAULT 0`; every existing story gets 0 and nothing publishes that did not before | **no** — it refuses: a release built for 20 has the exact-version readiness gate, reads 21 as `ahead` and answers 503 | **no** — it stops before any business write (cron and CLI exit 2) | ordinary roll; in production since the 28 Sep 2026 roll | **not by repointing** — a release built for 20 answers 503 on every paper, and predates the lane. Fix forward |
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
anything, and it cannot serve anything either.

**What the script does about it.** Three things in
`upgrade-papers.sh` used to lead back to such a release — the group
restore after a failed masthead check, the restore after a failed
`nginx -t` (whose message said "nothing changed"), and the "Rollback
for any release group" lines printed at the end. It now reads the
stored version before and after the step, and on this roll, where it
will read 21 and then 22, none of the three happens ("When a roll
raises the stored version", above):

- `nginx -t` runs once BEFORE the migration, with the rewritten vhost
  files in place. If the configuration will not load, the roll stops
  there with everything restored and the schema still at 21;
- if `nginx -t` fails after the migration all the same, nothing is put
  back and the message says the schema has changed. nginx is still
  running the release built for 21, so every paper answers 503 until
  the error is fixed and `nginx -t && systemctl reload nginx` loads
  the new release;
- a group with a domain that fails the masthead check is held on the
  new release, the roll exits non-zero, and the output names the group
  and says its failing domain is still being served;
- the closing text says the old directories are not a rollback.

What can still lead back is a person copying the `.bak.<stamp>` files
over a group's vhosts. Don't.

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

1. **Fix forward.** A second roll, pinned to a commit built for schema
   22. The schema is already at 22, so `tools/migrate.php --apply`
   reports nothing pending and only the code switches. For a release
   group whose domains are all healthy when it starts, that is the
   ordinary roll. If a domain was the problem — it failed the first
   roll's masthead check and its group was held, or it is on 503 — the
   second roll has to be told what that domain should answer, or it
   reads the repair as a failure and puts it back: run it with
   `PP_UPGRADE_BASELINE` naming the baseline the first roll saved. The
   first roll's "NOT done" text prints that line ("The recovery
   baseline", above). Plan for the second roll, and keep the first
   roll's output.
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
pinned roll — instead of counting on the old release. If the roll ends
"NOT done", it has held a group: send the output back and choose from
"When a roll that raised the schema version fails" before touching a
vhost. That output also names the saved baseline the fixing roll will
need.

## Forward repair

When a migration fails mid-run, `tools/migrate.php --status` classifies
the database (behind / partial / inconsistent) and prints the repair:
transactional engines roll the failed step back whole (rerun after
removing the obstacle); MySQL's `--apply --resume-partial` completes a
step only when its catalog markers prove it landed, and otherwise prints
the exact step definition to finish by hand. The application refuses to
serve anything but a ready schema in the meantime, which is the guard
rail that makes repair unhurried.
