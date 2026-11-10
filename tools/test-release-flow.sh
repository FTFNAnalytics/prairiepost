#!/usr/bin/env bash
#
# Exercise tools/vps/upgrade-papers.sh against a SYNTHETIC box — the
# Phase 2 release-procedure proof, never run against the real VPS. SLOW
# (builds genuine old releases from their own commits and migrates them).
#
#   bash tools/test-release-flow.sh
#
# Scenarios:
#   1. happy roll: an old (83e1467, schema-18) release serving a
#      populated v18 database; candidate = HEAD (git archive). Preflight
#      passes on a fresh backup state; crons pause and resume; the shared
#      schema migrates ONCE though two release groups share it; vhosts
#      repoint; the fixture smoke boots every group ready.
#   2. migration failure: a wrong-shape squatter table makes the step
#      fail — the roll aborts BEFORE any serving switch, vhosts and crons
#      restored to the old release.
#   3. missing recovery point: preflight refuses to roll at all.
#
# A roll that RAISES the stored schema version, from a release that has
# the readiness gate (d68a89f, schema 21 — what production ran when step
# 22 was written). After such a roll the old release is not a fallback:
#   4. the happy roll — the rise is read and reported, the closing text
#      offers no repoint, and the OLD release is then asked what it makes
#      of the database: its runner says "ahead", its CLI refuses, and
#      over HTTP it answers 503.
#   5. a group fails verification after the rise — it is HELD on the new
#      release, not restored, and the roll exits non-zero.
#   6. the same failure on a roll that does NOT change the version (old
#      release = HEAD) — the group is restored and the closing text
#      offers the repoint, exactly as before.
#   7. the nginx test fails BEFORE the migration — the roll stops with
#      the schema untouched and "nothing changed" is true.
#   8. the nginx test fails AFTER the migration — nothing is put back
#      and the message does not say "nothing changed".
#   9. the migration fails part-way, after one step committed (v18 box,
#      step 20 blocked) — nothing is put back, and the message does not
#      say the box still serves the old release.
#  10. the nginx test fails on a roll that does not change the version —
#      everything is restored and "nothing changed" is said, as before.
#
# Review findings on PR 73, each with the scenario that pins it:
#  11. the stored version cannot be read ONCE after a no-op step — the
#      read is retried and the roll restores as it always has;
#  12. it cannot be read at all after a no-op step that exited 0 — taken
#      as unchanged, restore as before;
#  13. it cannot be read at all after a step that raised it — unknown,
#      treated as a rise: the group is held and the output says so;
#  14. one group's config does not evaluate — the roll stops before the
#      pause and the migration, everything restored, nothing changed;
#  15. the papers' group (the one with the cron file) fails with no
#      version change — its vhost AND its cron file come back from .bak;
#      the hub's group, which passed, keeps its new release (and, since
#      scenario 6, a passing group keeps its cron file too);
#  16. a second run while one holds the lock — refused before it touches
#      anything;
#  17. SIGTERM after the cron files were paused, before the migration —
#      the handler puts every vhost and cron file back and says nothing
#      changed; the next run is an ordinary roll;
#  18. the nginx test fails after the rise, so the roll stops with the
#      files naming the new release — the next run FINISHES that roll:
#      it reads the version the first run found, holds nothing to its
#      own 503, puts nothing back, offers no rollback, writes the marks.
#
# The re-review of PR 73 (the stopped-roll path), each with its scenario:
#  19. SIGTERM after the reload, during verification — the handler says
#      nginx is on the new release; the next run finishes against the
#      baseline the first run saved, and marks the release;
#  20. a group held after a failed verification gets no completion mark,
#      so a run told to try again verifies it again, instead of saying
#      there is nothing to do;
#  21. a roll into a directory an earlier roll completed (a roll back)
#      clears that directory's old mark first, so a stop leaves it
#      unfinished, not "done";
#  22. a run pinned to a different commit while the vhosts name a release
#      no run finished is refused before it touches anything;
#  23. a cron file an earlier, stopped run left .paused with no active
#      copy comes back on the next run, whatever release it names;
#  24. a pin that is not a commit id, and a pre-staged tarball that is
#      not the pinned commit, are refused; the release tree is renamed
#      into place, so no half copy is ever reused.
#
# A fixture has no nginx and no TLS stack: the nginx test is skipped
# unless PP_UPGRADE_FIXTURE_NGINX_FAIL forces it to fail, and
# verification is a CLI boot of each new release directory. What a
# failed group gets (restore or hold) is the code a real box runs.
#
set -uo pipefail
cd "$(dirname "$0")/.."
ROOT=$(pwd)
OLD_COMMIT=83e146758c4cefb4334a97b2472dd9f0bc85cb99    # the v18-era release (schema 18, own seed-all); predates the readiness gate
GATED_COMMIT=d68a89fa67c491ea046a39a2ea4186a61e2464ea  # schema 21, WITH the readiness gate (production's release, 29 Sep 2026)
FAILS=0
ok() { if eval "$2"; then echo "ok   $1"; else echo "FAIL $1"; FAILS=1; fi; }
# Here-strings, not `echo | grep -q`: under pipefail a grep that exits on
# its first match leaves echo writing into a closed pipe, and the
# pipeline fails for output that DID match. A roll's output is large
# enough for that when the fixture runs unprivileged (CI).
has()   { grep -qF -- "$2" <<< "$1"; }
hasnt() { ! grep -qF -- "$2" <<< "$1"; }
show()  { grep -v "Operation not permitted" <<< "$1" | sed 's/^/   | /'; }  # chown noise from an unprivileged run

# A shallow clone (CI) does not carry the historical releases.
need_commit() {
  git cat-file -e "$1^{commit}" 2>/dev/null && return 0
  git fetch --quiet --no-tags --depth=1 origin "$1" 2>/dev/null
  git cat-file -e "$1^{commit}" 2>/dev/null && return 0
  echo "FATAL: commit $1 is not in this clone and could not be fetched from origin"
  exit 1
}
need_commit "$OLD_COMMIT"
need_commit "$GATED_COMMIT"

HEADSHA=$(git rev-parse HEAD)
HEAD12=${HEADSHA:0:12}
HEADV=$(sed -n "s/^define('PP_SCHEMA_VERSION', *\([0-9]*\));.*/\1/p" app/bootstrap.php)
[ -n "$HEADV" ] || { echo "FATAL: cannot read PP_SCHEMA_VERSION from app/bootstrap.php"; exit 1; }
TARBALL=$(mktemp --suffix=.tgz)
git archive --prefix="prairiepost-$HEADSHA/" "$HEADSHA" | gzip > "$TARBALL"

# One database per old release, seeded by ITS OWN tooling — provenance:
# the commit named. Every box built from that release starts from a copy.
SEEDS=$(mktemp -d)
BOXES=()
seed_db() { # commit -> sets $SEED_DB
  SEED_DB="$SEEDS/${1:0:12}.sqlite"
  [ -f "$SEED_DB" ] && return 0
  git worktree add --detach "$SEEDS/wt-${1:0:12}" "$1" >/dev/null 2>&1 \
    || { echo "FATAL: cannot check out $1"; exit 1; }
  ( cd "$SEEDS/wt-${1:0:12}" && bash tools/seed-all.sh "$SEED_DB" >/dev/null 2>&1 ) \
    || { echo "FATAL: old-release seed failed ($1)"; exit 1; }
  git worktree remove --force "$SEEDS/wt-${1:0:12}" 2>/dev/null
}
build_box() { # box-dir [commit]  -> populates $BOX
  BOX="$1"
  local commit="${2:-$OLD_COMMIT}"
  BOXES+=("$BOX")
  mkdir -p "$BOX"/{www,vhosts,cron,backups,data}
  # A genuine old release with a genuine database of its own version.
  git worktree add --detach "$BOX/www/prairiepost-oldsha11-shared" "$commit" >/dev/null 2>&1 \
    || { echo "FATAL: cannot check out $commit"; exit 1; }
  seed_db "$commit"
  cp "$SEED_DB" "$BOX/data/net.sqlite"
  cat > "$BOX/www/prairiepost-oldsha11-shared/config.php" <<CFG
<?php
return ['db' => ['driver' => 'sqlite', 'sqlite_path' => '$BOX/data/net.sqlite'],
        'site_slug' => 'prairiedispatch', 'hub_slug' => 'civismedia',
        'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];
CFG
  # A second release directory sharing the SAME database (the hub's own
  # release in production) — proves migrate-once-per-schema.
  cp -r "$BOX/www/prairiepost-oldsha11-shared" "$BOX/www/prairiepost-oldsha11-civismedia"
  rm -rf "$BOX/www/prairiepost-oldsha11-civismedia/.git" "$BOX/www/prairiepost-oldsha11-shared/.git" 2>/dev/null
  printf 'server {\n  root %s; # shared\n  server_name kitchenerchronicle.test;\n}\n' "$BOX/www/prairiepost-oldsha11-shared" > "$BOX/vhosts/kitchenerchronicle"
  printf 'server {\n  root %s;\n  server_name civismedia.test;\n}\n' "$BOX/www/prairiepost-oldsha11-civismedia" > "$BOX/vhosts/civismedia"
  printf '17 3 * * * root php %s/cron/fetch-news.php\n' "$BOX/www/prairiepost-oldsha11-shared" > "$BOX/cron/pp-fetch"
  # A historical backup artifact from an "earlier run": references the old
  # release but MUST be ignored (cron.d never runs dotted names, and the
  # 4163fe0 roll wrongly rewrote and paused such files).
  printf '17 3 * * * root php %s/cron/fetch-news.php\n' "$BOX/www/prairiepost-oldsha11-shared" > "$BOX/cron/pp-fetch.bak.20250101"
  php -r 'echo json_encode(["ok" => true, "set_id" => "fixture-set", "finished_epoch" => time(), "finished_at" => date("Y-m-d H:i:s")]);' > "$BOX/backups/state.json"
}
# Make ONE release group fail verification for a real reason: that
# group's release resolves to a tenant that has no site row, so its new
# release refuses to boot. The other group is untouched and passes.
break_tenant() { # box-dir civismedia|shared
  cat > "$1/www/prairiepost-oldsha11-$2/config.php" <<CFG
<?php
return ['db' => ['driver' => 'sqlite', 'sqlite_path' => '$1/data/net.sqlite'],
        'site_slug' => 'no-such-paper', 'hub_slug' => 'civismedia',
        'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];
CFG
}
break_hub_tenant() { break_tenant "$1" civismedia; }
# A config that lints but cannot be evaluated: the roll must stop before
# it changes anything, because it cannot even learn which database this
# group uses.
break_config_eval() { # box-dir civismedia|shared
  printf '<?php\nthrow new RuntimeException("this config does not evaluate in the fixture");\n' > "$1/www/prairiepost-oldsha11-$2/config.php"
}
marked_done() { [ -f "$1/.pp-roll-complete" ]; } # a release directory a roll finished on
run_upgrade() { # box-dir [VAR=value ...] -> rc  (the fixture knobs go in as VAR=value)
  local box="$1"
  shift
  env PP_UPGRADE_FIXTURE=1 \
    PP_UPGRADE_VHOSTS_DIR="$box/vhosts" \
    PP_UPGRADE_CRON_DIR="$box/cron" \
    PP_UPGRADE_WWW="$box/www" \
    PP_UPGRADE_SNIPPET_DIR="$box/snippets" \
    PP_UPGRADE_LOCK="$box/upgrade.lock" \
    PP_UPGRADE_LOG_DIR="$box/log" \
    PP_UPGRADE_SHA="$HEAD12" \
    PP_RELEASE_TARBALL="$TARBALL" \
    PP_BACKUP_DEST="$box/backups" \
    "$@" \
    bash "$ROOT/tools/vps/upgrade-papers.sh"
}
stored_version() { # box-dir -> the schema version stored in its database
  php -r '$p = new PDO("sqlite:" . $argv[1]); echo $p->query("SELECT svalue FROM settings WHERE site_id=0 AND skey=\"schema_version\"")->fetchColumn();' "$1/data/net.sqlite"
}
on_new() { grep -q "$HEAD12" "$1"; }                                # a vhost or cron file names the new release
on_old() { grep -q oldsha11 "$1" && ! grep -q "$HEAD12" "$1"; }     # ... or still the old one, untouched
restore_lines() { grep -cE '^ +restoring release group' <<< "$1"; } # the restore line, not the "NOT restoring" one
line_of() { grep -nF -- "$2" <<< "$1" | head -1 | cut -d: -f1; }
# What a release directory answers over real HTTP (php -S, its own
# config.php): sets $HTTP_CODE and leaves the body in $HTTP_BODY.
HTTP_BODY=$(mktemp)
http_front() { # release-dir
  local port pid i
  port=$((20000 + RANDOM % 20000))
  HTTP_CODE=""
  ( cd "$1" && exec php -S "127.0.0.1:$port" router.php >/dev/null 2>&1 ) &
  pid=$!
  for i in 1 2 3 4 5 6 7 8 9 10 11 12; do
    HTTP_CODE=$(curl -s -o "$HTTP_BODY" -w '%{http_code}' -m 10 "http://127.0.0.1:$port/" 2>/dev/null)
    [ -n "$HTTP_CODE" ] && [ "$HTTP_CODE" != "000" ] && break
    sleep 0.5
  done
  kill "$pid" 2>/dev/null
  wait "$pid" 2>/dev/null
}

echo "== scenario 1: the happy roll (v18 -> HEAD, two groups, one schema)"
B1=$(mktemp -d)
build_box "$B1"
OUT1=$(run_upgrade "$B1" 2>&1); RC1=$?
grep -E "^(PASS|FAIL|FATAL)|migrating|already migrated|paused|resumed|recovery point|stored version|stored schema version" <<< "$OUT1" | sed 's/^/   /'
ok "roll succeeds"                            "[ $RC1 = 0 ]"
ok "preflight saw the recovery point"         'has "$OUT1" "recovery point: fixture-set"'
ok "crons paused for the window"              'has "$OUT1" "paused: pp-fetch"'
ok "crons resumed after verification"         'has "$OUT1" "resumed: pp-fetch"'
ok "cron file exists unpaused and repointed"  "[ -f $B1/cron/pp-fetch ] && [ ! -f $B1/cron/pp-fetch.paused ] && grep -q ${HEADSHA:0:12} $B1/cron/pp-fetch"
ok "historical .bak cron ignored entirely"    "grep -q oldsha11 $B1/cron/pp-fetch.bak.20250101 && ! grep -q ${HEADSHA:0:12} $B1/cron/pp-fetch.bak.20250101 && [ ! -f $B1/cron/pp-fetch.bak.20250101.paused ]"
ok "the shared schema migrated exactly once"  '[ "$(grep -c "migrating the schema" <<< "$OUT1")" = 1 ]'
ok "the second group reused the migration"    'has "$OUT1" "already migrated this run"'
ok "legacy vhosts got the deny snippet injected" "grep -q prairiepost-deny $B1/vhosts/kitchenerchronicle && [ -f $B1/snippets/prairiepost-deny.conf ]"
ok "both vhosts repointed to the new release" "grep -q ${HEADSHA:0:12} $B1/vhosts/kitchenerchronicle && grep -q ${HEADSHA:0:12} $B1/vhosts/civismedia"
ok "both groups boot ready as their tenants"  '[ "$(grep -c "PASS (fixture)" <<< "$OUT1")" = 2 ]'
J=$(php -r '$p = new PDO("sqlite:" . $argv[1]); echo implode(",", $p->query("SELECT version || \":\" || status FROM schema_migrations ORDER BY version")->fetchAll(PDO::FETCH_COLUMN));' "$B1/data/net.sqlite")
ok "journal: adopted at 18, steps applied"    'has "$J" "18:adopted,19:applied,20:applied"'
ok "the rise is read and reported (18 -> $HEADV)" 'has "$OUT1" "stored schema version after the step: $HEADV — ROSE from 18"'
ok "so the closing text offers no repoint"    'hasnt "$OUT1" "Rollback for any release group" && has "$OUT1" "NOT a rollback for this roll"'

echo
echo "== scenario 2: migration failure aborts BEFORE the serving switch"
B2=$(mktemp -d)
build_box "$B2"
php -r '$p = new PDO("sqlite:" . $argv[1]); $p->exec("CREATE TABLE media_orders (wrong_shape INTEGER)");' "$B2/data/net.sqlite"
OUT2=$(run_upgrade "$B2" 2>&1); RC2=$?
ok "the roll fails"                           "[ $RC2 != 0 ]"
ok "it names the migration as the cause"      'has "$OUT2" "MIGRATION FAILED"'
ok "vhosts still point at the OLD release"    "grep -q oldsha11 $B2/vhosts/kitchenerchronicle && ! grep -q ${HEADSHA:0:12} $B2/vhosts/kitchenerchronicle"
ok "cron restored to the OLD release, unpaused" "[ -f $B2/cron/pp-fetch ] && grep -q oldsha11 $B2/cron/pp-fetch && [ ! -f $B2/cron/pp-fetch.paused ]"
ok "the database is behind, not corrupted"    '[ "$(stored_version "$B2")" = 18 ]'
ok "no step committed, so the old wording stands" 'has "$OUT2" "the schema migration failed; the box still serves the old release" && hasnt "$OUT2" "ROSE" && hasnt "$OUT2" "was not readable both before and after"'

echo
echo "== scenario 3: no recovery point, no roll"
B3=$(mktemp -d)
build_box "$B3"
rm "$B3/backups/state.json"
OUT3=$(run_upgrade "$B3" 2>&1); RC3=$?
ok "preflight refuses"                        "[ $RC3 != 0 ]"
ok "and says why"                             'has "$OUT3" "recovery point"'
ok "nothing changed"                          "grep -q oldsha11 $B3/vhosts/kitchenerchronicle"

echo
echo "== scenario 4: a roll that RAISES the version from a gated release (21 -> $HEADV)"
B4=$(mktemp -d)
build_box "$B4" "$GATED_COMMIT"
OLD4="$B4/www/prairiepost-oldsha11-shared"
ok "the box starts at schema 21"              '[ "$(stored_version "$B4")" = 21 ]'
OUT4=$(run_upgrade "$B4" 2>&1); RC4=$?
show "$(sed -n '/^== Read each schema/,$p' <<< "$OUT4")"
ok "roll succeeds"                            '[ "$RC4" = 0 ]'
ok "the stored version is read before the step" 'has "$OUT4" "behind — stored version 21, this release is built for $HEADV"'
ok "the nginx test is placed before the migration" '[ -n "$(line_of "$OUT4" "nginx config test BEFORE the migration")" ] && [ "$(line_of "$OUT4" "nginx config test BEFORE the migration")" -lt "$(line_of "$OUT4" "migrating the schema")" ]'
ok "the rise is read back and reported"       'has "$OUT4" "stored schema version after the step: $HEADV — ROSE from 21"'
ok "the database is at $HEADV"                '[ "$(stored_version "$B4")" = "$HEADV" ]'
ok "both groups boot ready as their tenants"  '[ "$(grep -c "PASS (fixture)" <<< "$OUT4")" = 2 ]'
ok "crons resumed on the new release"         '[ -f "$B4/cron/pp-fetch" ] && [ ! -f "$B4/cron/pp-fetch.paused" ] && on_new "$B4/cron/pp-fetch"'
ok "the closing text does NOT offer a repoint" 'hasnt "$OUT4" "Rollback for any release group" && hasnt "$OUT4" "cp <vhost>.bak"'
ok "it says the old directories are not a rollback" 'has "$OUT4" "NOT a rollback for this roll" && has "$OUT4" "ROSE from 21 to $HEADV in this roll"'
# The consequence itself, asked of the OLD release against the migrated
# database: this is what a repoint would have put back in front of readers.
ST4=$( (cd "$OLD4" && php tools/migrate.php --status) 2>&1 ); STRC4=$?
ok "OLD release, its own runner: ahead, exit 6" '[ "$STRC4" = 6 ] && has "$ST4" "ahead (stored version $HEADV, target 21)"'
BOOT4=$( (cd "$OLD4" && php -r 'require "app/bootstrap.php"; db();') 2>&1 ); BOOTRC4=$?
ok "OLD release, CLI boot: refuses, exit 2"   '[ "$BOOTRC4" = 2 ] && has "$BOOT4" "schema ahead (stored version $HEADV, this code needs 21)"'
http_front "$OLD4"
echo "   OLD release over HTTP: $HTTP_CODE  $(grep -o '<title>[^<]*</title>' "$HTTP_BODY" | head -1)"
ok "OLD release, HTTP front page: 503"        '[ "$HTTP_CODE" = 503 ] && grep -q "This site is being updated" "$HTTP_BODY"'
http_front "$B4/www/prairiepost-$HEAD12-kitchenerchronicle"
echo "   NEW release over HTTP: $HTTP_CODE  $(grep -o '<title>[^<]*</title>' "$HTTP_BODY" | head -1)"
ok "NEW release, HTTP front page: 200"        '[ "$HTTP_CODE" = 200 ]'
ok "both new release directories carry the completion mark" 'marked_done "$B4/www/prairiepost-$HEAD12-kitchenerchronicle" && marked_done "$B4/www/prairiepost-$HEAD12-civismedia"'
ok "each carries the saved baseline and the before-version (21)" '[ -f "$B4/www/prairiepost-$HEAD12-civismedia/.pp-roll-baseline" ] && grep -q "^version_before=21" "$B4/www/prairiepost-$HEAD12-civismedia/.pp-roll-before" && grep -q "^version_before=21" "$B4/www/prairiepost-$HEAD12-kitchenerchronicle/.pp-roll-before"'
ok "the tree was renamed into place: no temporary copy remains (scenario 24)" '[ "$(ls -d "$B4"/www/*.tmp.* 2>/dev/null | wc -l)" = 0 ]'
ok "the run was logged"                      '[ "$(ls "$B4/log" | wc -l)" = 1 ] && grep -q "^== Done" "$B4"/log/roll-*.log'

echo
echo "== scenario 5: a group fails verification AFTER the rise — held, not restored"
B5=$(mktemp -d)
build_box "$B5" "$GATED_COMMIT"
break_hub_tenant "$B5"
OUT5=$(run_upgrade "$B5" 2>&1); RC5=$?
show "$(sed -n '/^== Verify every domain/,$p' <<< "$OUT5")"
ok "the roll exits non-zero"                  '[ "$RC5" != 0 ]'
ok "the schema did rise"                      'has "$OUT5" "ROSE from 21" && [ "$(stored_version "$B5")" = "$HEADV" ]'
ok "one group failed, one passed"             '[ "$(grep -c "FAIL (fixture)" <<< "$OUT5")" = 1 ] && [ "$(grep -c "PASS (fixture)" <<< "$OUT5")" = 1 ]'
ok "the failed group is NOT restored"         'has "$OUT5" "NOT restoring release group" && [ "$(restore_lines "$OUT5")" = 0 ]'
ok "and the roll says why"                    'has "$OUT5" "ROSE from 21 to $HEADV in this roll" && has "$OUT5" "answers 503"'
ok "its vhost stays on the new release"       'on_new "$B5/vhosts/civismedia"'
ok "the passing group stays on the new release" 'on_new "$B5/vhosts/kitchenerchronicle"'
ok "crons resumed on the new release"         '[ -f "$B5/cron/pp-fetch" ] && [ ! -f "$B5/cron/pp-fetch.paused" ] && on_new "$B5/cron/pp-fetch"'
ok "it does not claim a restore happened"     'hasnt "$OUT5" "at least one release group was restored"'
ok "it ends NOT done, with no repoint offered" 'has "$OUT5" "NOT done" && hasnt "$OUT5" "Rollback for any release group" && hasnt "$OUT5" "cp <vhost>.bak"'
ok "the group that passed is marked; the held one is not" 'marked_done "$B5/www/prairiepost-$HEAD12-kitchenerchronicle" && ! marked_done "$B5/www/prairiepost-$HEAD12-civismedia"'
OUT5b=$(run_upgrade "$B5" 2>&1); RC5b=$?
ok "a run after NOT done verifies the held group again (scenario 20)" '[ "$RC5b" != 0 ] && has "$OUT5b" "no run finished that roll" && [ "$(grep -c "FAIL (fixture)" <<< "$OUT5b")" = 1 ] && hasnt "$OUT5b" "Nothing to upgrade" && has "$OUT5b" "NOT done"'

echo
echo "== scenario 6: the same failure, NO version change — restored, exactly as before"
B6=$(mktemp -d)
build_box "$B6" "$HEADSHA"
break_hub_tenant "$B6"
OUT6=$(run_upgrade "$B6" 2>&1); RC6=$?
show "$(sed -n '/^== Read each schema/,$p' <<< "$OUT6")"
ok "the roll ends as it always has (exit 0, with a WARN)" '[ "$RC6" = 0 ] && has "$OUT6" "WARN: at least one release group was restored"'
ok "nothing was pending and the version is unchanged" 'has "$OUT6" "stored version $HEADV — nothing pending" && has "$OUT6" "stored schema version after the step: $HEADV (unchanged)"'
ok "no nginx test is added before the migration" 'hasnt "$OUT6" "nginx config test BEFORE the migration"'
ok "the failed group IS restored"             '[ "$(restore_lines "$OUT6")" = 1 ] && hasnt "$OUT6" "NOT restoring"'
ok "its vhost is back on the old release"     'on_old "$B6/vhosts/civismedia"'
ok "the passing group stays on the new release" 'on_new "$B6/vhosts/kitchenerchronicle"'
ok "the closing text is the old one, word for word" 'has "$OUT6" "Old release directories stay on disk. Rollback for any release group:" && has "$OUT6" "  cp <vhost>.bak." && has "$OUT6" "  cp <cronfile>.bak." && has "$OUT6" "  nginx -t && systemctl reload nginx" && hasnt "$OUT6" "NOT a rollback"'
ok "the passing group's cron file is back, unpaused, on the new release" '[ -f "$B6/cron/pp-fetch" ] && [ ! -f "$B6/cron/pp-fetch.paused" ] && on_new "$B6/cron/pp-fetch" && has "$OUT6" "resumed: pp-fetch"'
ok "only the group that stayed up is marked complete" 'marked_done "$B6/www/prairiepost-$HEAD12-kitchenerchronicle" && ! marked_done "$B6/www/prairiepost-$HEAD12-civismedia"'

echo
echo "== scenario 7: nginx test fails BEFORE the migration — stopped, schema untouched"
B7=$(mktemp -d)
build_box "$B7" "$GATED_COMMIT"
OUT7=$(run_upgrade "$B7" PP_UPGRADE_FIXTURE_NGINX_FAIL=pre 2>&1); RC7=$?
show "$(sed -n '/^== Read each schema/,$p' <<< "$OUT7")"
ok "the roll fails"                           '[ "$RC7" != 0 ]'
ok "it stops before the migration"            'has "$OUT7" "nginx test failed BEFORE the schema migration" && hasnt "$OUT7" "migrating the schema"'
ok "the schema was not touched"               '[ "$(stored_version "$B7")" = 21 ]'
ok "vhosts are back on the old release"       'on_old "$B7/vhosts/kitchenerchronicle" && on_old "$B7/vhosts/civismedia"'
ok "cron is back on the old release, unpaused" '[ -f "$B7/cron/pp-fetch" ] && on_old "$B7/cron/pp-fetch" && [ ! -f "$B7/cron/pp-fetch.paused" ]'
BOOT7=$( (cd "$B7/www/prairiepost-oldsha11-shared" && php -r 'require "app/bootstrap.php"; db(); echo current_site()["slug"];') 2>&1 ); BOOTRC7=$?
ok "so \"nothing changed\" is true: the OLD release still boots ready" '[ "$BOOTRC7" = 0 ] && [ "$BOOT7" = prairiedispatch ] && has "$OUT7" "nothing changed"'

echo
echo "== scenario 8: nginx test fails AFTER the migration — nothing put back, and it says so"
B8=$(mktemp -d)
build_box "$B8" "$GATED_COMMIT"
OUT8=$(run_upgrade "$B8" PP_UPGRADE_FIXTURE_NGINX_FAIL=post 2>&1); RC8=$?
show "$(sed -n '/^== nginx config test + reload/,$p' <<< "$OUT8")"
ok "the roll fails"                           '[ "$RC8" != 0 ]'
ok "the schema rose"                          '[ "$(stored_version "$B8")" = "$HEADV" ] && has "$OUT8" "ROSE from 21 to $HEADV in this roll"'
ok "it does NOT say nothing changed"          'hasnt "$OUT8" "nothing changed" && has "$OUT8" "nginx test failed AFTER the migration changed the schema"'
ok "vhost files are left on the new release"  'on_new "$B8/vhosts/kitchenerchronicle" && on_new "$B8/vhosts/civismedia"'
ok "crons resumed on the new release"         '[ -f "$B8/cron/pp-fetch" ] && [ ! -f "$B8/cron/pp-fetch.paused" ] && on_new "$B8/cron/pp-fetch"'
ok "no repoint is offered"                    'hasnt "$OUT8" "Rollback for any release group" && has "$OUT8" "Do NOT copy the .bak."'

echo
echo "== scenario 9: migration fails part-way, AFTER a step committed (v18, step 20 blocked)"
B9=$(mktemp -d)
build_box "$B9"
php -r '$p = new PDO("sqlite:" . $argv[1]); $p->exec("CREATE INDEX idx_post_tags_tag ON post_tags (tag_id)");' "$B9/data/net.sqlite"
OUT9=$(run_upgrade "$B9" 2>&1); RC9=$?
show "$(sed -n '/^== Quiesce scheduled writers/,$p' <<< "$OUT9")"
ok "the roll fails"                           '[ "$RC9" != 0 ]'
ok "step 19 committed before step 20 failed"  '[ "$(stored_version "$B9")" = 19 ]'
ok "it reports the rise (18 -> 19)"           'has "$OUT9" "MIGRATION FAILED — after the stored schema version had already risen" && has "$OUT9" "ROSE from 18 to 19 in this roll"'
ok "it does NOT say the box still serves the old release" 'hasnt "$OUT9" "the box still serves the old release" && hasnt "$OUT9" "rolling every vhost and cron file back"'
ok "vhost files are left on the new release"  'on_new "$B9/vhosts/kitchenerchronicle" && on_new "$B9/vhosts/civismedia"'
ok "cron file is active, on the new release"  '[ -f "$B9/cron/pp-fetch" ] && [ ! -f "$B9/cron/pp-fetch.paused" ] && on_new "$B9/cron/pp-fetch"'

echo
echo "== scenario 10: nginx test fails, NO version change — restored, \"nothing changed\", as before"
B10=$(mktemp -d)
build_box "$B10" "$HEADSHA"
OUT10=$(run_upgrade "$B10" PP_UPGRADE_FIXTURE_NGINX_FAIL=post 2>&1); RC10=$?
show "$(sed -n '/^== nginx config test + reload/,$p' <<< "$OUT10")"
ok "the roll fails"                           '[ "$RC10" != 0 ]'
ok "the old message, word for word"           'has "$OUT10" "nginx test failed — every vhost and cron file restored, nothing changed"'
ok "vhosts are back on the old release"       'on_old "$B10/vhosts/kitchenerchronicle" && on_old "$B10/vhosts/civismedia"'
ok "cron is back on the old release, unpaused" '[ -f "$B10/cron/pp-fetch" ] && on_old "$B10/cron/pp-fetch" && [ ! -f "$B10/cron/pp-fetch.paused" ]'
ok "the version did not move"                 '[ "$(stored_version "$B10")" = "$HEADV" ] && hasnt "$OUT10" "ROSE"'

echo
echo "== scenario 11: the stored version cannot be read ONCE after a no-op step — retried, restore as before"
B11=$(mktemp -d)
build_box "$B11" "$HEADSHA"
break_hub_tenant "$B11"
OUT11=$(run_upgrade "$B11" PP_UPGRADE_FIXTURE_STATUS_FAIL=1 2>&1); RC11=$?
show "$(sed -n '/^== Quiesce scheduled writers/,/^== nginx config test/p' <<< "$OUT11")"
ok "the roll ends as it always has (exit 0, with a WARN)" '[ "$RC11" = 0 ] && has "$OUT11" "WARN: at least one release group was restored"'
ok "one read failed and the retry answered"   '[ "$(grep -c "post-step --status read made to fail" <<< "$OUT11")" = 1 ] && has "$OUT11" "stored schema version after the step: $HEADV (unchanged)"'
ok "the failed group IS restored, not held"   '[ "$(restore_lines "$OUT11")" = 1 ] && hasnt "$OUT11" "NOT restoring" && hasnt "$OUT11" "could not be confirmed" && on_old "$B11/vhosts/civismedia"'
ok "the closing text is the old one"          'has "$OUT11" "Rollback for any release group:" && hasnt "$OUT11" "NOT a rollback"'

echo
echo "== scenario 12: the stored version cannot be read AT ALL after a no-op step — taken as unchanged"
B12=$(mktemp -d)
build_box "$B12" "$HEADSHA"
break_hub_tenant "$B12"
OUT12=$(run_upgrade "$B12" PP_UPGRADE_FIXTURE_STATUS_FAIL=9 2>&1); RC12=$?
show "$(sed -n '/^== Quiesce scheduled writers/,/^== nginx config test/p' <<< "$OUT12")"
ok "the roll ends as it always has (exit 0, with a WARN)" '[ "$RC12" = 0 ] && has "$OUT12" "WARN: at least one release group was restored"'
ok "three reads failed; the step was a no-op that exited 0, so: unchanged" '[ "$(grep -c "post-step --status read made to fail" <<< "$OUT12")" = 3 ] && has "$OUT12" "taken as unchanged ($HEADV) because nothing was pending and the step exited 0"'
ok "the failed group IS restored, not held"   '[ "$(restore_lines "$OUT12")" = 1 ] && hasnt "$OUT12" "NOT restoring" && on_old "$B12/vhosts/civismedia"'
ok "the closing text is the old one"          'has "$OUT12" "Rollback for any release group:" && hasnt "$OUT12" "NOT a rollback"'

echo
echo "== scenario 13: the stored version cannot be read at all after a step that RAISED it — unknown, treated as a rise"
B13=$(mktemp -d)
build_box "$B13" "$GATED_COMMIT"
break_hub_tenant "$B13"
OUT13=$(run_upgrade "$B13" PP_UPGRADE_FIXTURE_STATUS_FAIL=9 2>&1); RC13=$?
show "$(sed -n '/^== Verify every domain/,$p' <<< "$OUT13")"
ok "the roll exits non-zero"                  '[ "$RC13" != 0 ]'
ok "the database did move to $HEADV"          '[ "$(stored_version "$B13")" = "$HEADV" ]'
ok "the version is reported as unconfirmed, and treated as risen" 'has "$OUT13" "could not be confirmed unchanged" && has "$OUT13" "treated as risen"'
ok "the failed group is HELD, not restored"   'has "$OUT13" "NOT restoring release group" && [ "$(restore_lines "$OUT13")" = 0 ] && on_new "$B13/vhosts/civismedia"'
ok "no repoint is offered"                    'has "$OUT13" "NOT done" && hasnt "$OUT13" "Rollback for any release group"'

echo
echo "== scenario 14: one group's config does not evaluate — stopped before the pause, nothing changed"
B14=$(mktemp -d)
build_box "$B14" "$GATED_COMMIT"
break_config_eval "$B14" civismedia
OUT14=$(run_upgrade "$B14" 2>&1); RC14=$?
show "$(sed -n '/^== Read each schema/,$p' <<< "$OUT14")"
ok "the roll fails"                           '[ "$RC14" != 0 ]'
ok "it names the group it could not read"     'has "$OUT14" "cannot read the database identity from $B14/www/prairiepost-$HEAD12-civismedia/config.php"'
ok "before any pause or migration"            'hasnt "$OUT14" "paused:" && hasnt "$OUT14" "migrating the schema" && [ "$(stored_version "$B14")" = 21 ]'
ok "vhosts and cron are back on the old release, unpaused" 'on_old "$B14/vhosts/kitchenerchronicle" && on_old "$B14/vhosts/civismedia" && [ -f "$B14/cron/pp-fetch" ] && on_old "$B14/cron/pp-fetch" && [ ! -f "$B14/cron/pp-fetch.paused" ]'
ok "and it says so"                           'has "$OUT14" "every vhost and cron file restored, the schema was not touched, nothing changed"'

echo
echo "== scenario 15: the papers' group fails with NO version change — its vhost and its cron file come back"
B15=$(mktemp -d)
build_box "$B15" "$HEADSHA"
break_tenant "$B15" shared
OUT15=$(run_upgrade "$B15" 2>&1); RC15=$?
show "$(sed -n '/^== Verify every domain/,/^== Fallback/p' <<< "$OUT15")"
ok "the roll ends as it always has (exit 0, with a WARN)" '[ "$RC15" = 0 ] && has "$OUT15" "WARN: at least one release group was restored"'
ok "the papers' group is restored"            '[ "$(restore_lines "$OUT15")" = 1 ] && on_old "$B15/vhosts/kitchenerchronicle"'
ok "its cron file is back on the old release, unpaused, no residue" '[ -f "$B15/cron/pp-fetch" ] && on_old "$B15/cron/pp-fetch" && [ ! -f "$B15/cron/pp-fetch.paused" ]'
ok "the hub's group stays on the new release" 'on_new "$B15/vhosts/civismedia" && marked_done "$B15/www/prairiepost-$HEAD12-civismedia"'

echo
echo "== scenario 16: a second run while one holds the lock — refused, nothing touched"
B16=$(mktemp -d)
build_box "$B16" "$GATED_COMMIT"
( flock 9; sleep 30 ) 9>"$B16/upgrade.lock" &
HOLDER=$!
sleep 1
OUT16=$(run_upgrade "$B16" 2>&1); RC16=$?
pkill -P "$HOLDER" 2>/dev/null; kill "$HOLDER" 2>/dev/null; wait "$HOLDER" 2>/dev/null
show "$OUT16"
ok "the roll refuses"                         '[ "$RC16" != 0 ] && has "$OUT16" "one roll at a time"'
ok "before touching anything"                 'on_old "$B16/vhosts/kitchenerchronicle" && [ "$(ls "$B16/vhosts" | grep -c bak)" = 0 ] && [ ! -d "$B16/www/prairiepost-$HEAD12-kitchenerchronicle" ] && [ "$(stored_version "$B16")" = 21 ]'

echo
echo "== scenario 17: SIGTERM after the pause — crons resumed, state reported; a rerun finishes the roll"
B17=$(mktemp -d)
build_box "$B17" "$GATED_COMMIT"
OUT17=$(run_upgrade "$B17" PP_UPGRADE_FIXTURE_DIE=after-pause 2>&1); RC17=$?
show "$(sed -n '/^== Quiesce scheduled writers/,$p' <<< "$OUT17")"
ok "the run stops with the signal's exit code" '[ "$RC17" = 143 ]'
ok "it says what was interrupted"             'has "$OUT17" "INTERRUPTED by SIGTERM during: Quiesce scheduled writers"'
ok "before the migration, it puts everything back and says nothing changed" 'has "$OUT17" "every vhost and cron file restored to the old release" && on_old "$B17/vhosts/kitchenerchronicle" && on_old "$B17/vhosts/civismedia" && [ -f "$B17/cron/pp-fetch" ] && on_old "$B17/cron/pp-fetch" && [ ! -f "$B17/cron/pp-fetch.paused" ] && [ "$(stored_version "$B17")" = 21 ]'
ok "nothing is marked complete"               '! marked_done "$B17/www/prairiepost-$HEAD12-kitchenerchronicle"'
OUT17b=$(run_upgrade "$B17" 2>&1); RC17b=$?
ok "the next run is an ordinary roll, and completes" '[ "$RC17b" = 0 ] && has "$OUT17b" "prairiepost-oldsha11-shared -> " && hasnt "$OUT17b" "finishing it" && has "$OUT17b" "ROSE from 21 to $HEADV" && [ "$(grep -c "PASS (fixture)" <<< "$OUT17b")" = 2 ] && marked_done "$B17/www/prairiepost-$HEAD12-kitchenerchronicle"'
OUT17c=$(run_upgrade "$B17" 2>&1); RC17c=$?
ok "a third run has nothing to do"            '[ "$RC17c" = 0 ] && has "$OUT17c" "Nothing to upgrade" && [ "$(grep -c "already on $HEAD12" <<< "$OUT17c")" = 2 ]'

echo
echo "== scenario 18: the nginx test fails after the rise; the next run FINISHES the roll against what the first run saved"
B18=$(mktemp -d)
build_box "$B18" "$GATED_COMMIT"
OUT18=$(run_upgrade "$B18" PP_UPGRADE_FIXTURE_NGINX_FAIL=post 2>&1); RC18=$?
ok "the first run stops after the rise with the files on the new release" '[ "$RC18" != 0 ] && [ "$(stored_version "$B18")" = "$HEADV" ] && on_new "$B18/vhosts/kitchenerchronicle" && ! marked_done "$B18/www/prairiepost-$HEAD12-kitchenerchronicle"'
ok "it saved the baseline and the version it found (21)" 'grep -q "^version_before=21" "$B18/www/prairiepost-$HEAD12-kitchenerchronicle/.pp-roll-before" && grep -q "^version_before=21" "$B18/www/prairiepost-$HEAD12-civismedia/.pp-roll-before" && [ -f "$B18/www/prairiepost-$HEAD12-civismedia/.pp-roll-baseline" ]'
OUT18b=$(run_upgrade "$B18" 2>&1); RC18b=$?
show "$(sed -n '/^== Upgrade each release/,/^== nginx config test/p;/^== Verify every domain/,$p' <<< "$OUT18b")"
ok "the rerun finishes: exit 0, both groups pass, marks written" '[ "$RC18b" = 0 ] && has "$OUT18b" "no run finished that roll" && [ "$(grep -c "PASS (fixture)" <<< "$OUT18b")" = 2 ] && marked_done "$B18/www/prairiepost-$HEAD12-kitchenerchronicle" && marked_done "$B18/www/prairiepost-$HEAD12-civismedia"'
ok "it judges the roll by the version the first run found: 21, risen to $HEADV" 'has "$OUT18b" "found stored version 21; it is $HEADV now" && has "$OUT18b" "ROSE from 21 to $HEADV"'
ok "it holds the domains to the saved baseline, not to its own start" 'has "$OUT18b" "held to the baseline the run that started this roll saved"'
ok "it puts nothing back and offers NO rollback" 'hasnt "$OUT18b" "Rollback for any release group" && hasnt "$OUT18b" "restoring release group" && has "$OUT18b" "NOT a rollback" && has "$OUT18b" "finished a roll an earlier run started"'
ok "crons active on the new release"          '[ -f "$B18/cron/pp-fetch" ] && [ ! -f "$B18/cron/pp-fetch.paused" ] && on_new "$B18/cron/pp-fetch"'

echo
echo "== scenario 19: SIGTERM after the reload, during verification — the rerun finishes against the saved baseline"
B19=$(mktemp -d)
build_box "$B19" "$GATED_COMMIT"
OUT19=$(run_upgrade "$B19" PP_UPGRADE_FIXTURE_DIE=after-reload 2>&1); RC19=$?
show "$(sed -n '/INTERRUPTED/,$p' <<< "$OUT19")"
ok "the run stops with the signal's exit code" '[ "$RC19" = 143 ]'
ok "it says nginx is on the new release and the schema rose" 'has "$OUT19" "nginx: reloaded onto the new release" && has "$OUT19" "ROSE from 21 to $HEADV" && [ "$(stored_version "$B19")" = "$HEADV" ]'
ok "it resumed the cron file; nothing is marked" 'has "$OUT19" "resumed: pp-fetch" && [ ! -f "$B19/cron/pp-fetch.paused" ] && ! marked_done "$B19/www/prairiepost-$HEAD12-kitchenerchronicle"'
OUT19b=$(run_upgrade "$B19" 2>&1); RC19b=$?
ok "the rerun finishes against the saved baseline and marks both releases" '[ "$RC19b" = 0 ] && has "$OUT19b" "held to the baseline the run that started this roll saved" && [ "$(grep -c "PASS (fixture)" <<< "$OUT19b")" = 2 ] && marked_done "$B19/www/prairiepost-$HEAD12-kitchenerchronicle" && marked_done "$B19/www/prairiepost-$HEAD12-civismedia" && hasnt "$OUT19b" "Rollback for any release group"'

echo
echo "== scenario 21: a roll into a directory an earlier roll completed clears its old mark"
B21=$(mktemp -d)
build_box "$B21" "$GATED_COMMIT"
OUT21=$(run_upgrade "$B21" 2>&1); RC21=$?
ok "a clean roll first"                       '[ "$RC21" = 0 ] && marked_done "$B21/www/prairiepost-$HEAD12-kitchenerchronicle"'
# "roll back" by hand: the vhosts and the cron file name the old release again
for f in "$B21"/vhosts/kitchenerchronicle "$B21"/vhosts/civismedia "$B21"/cron/pp-fetch; do cp "$(ls -t "$f".bak.* | head -1)" "$f"; done
OUT21b=$(run_upgrade "$B21" PP_UPGRADE_FIXTURE_NGINX_FAIL=post 2>&1); RC21b=$?
ok "the roll into the existing directory clears its old mark, then stops at the nginx test" '[ "$RC21b" != 0 ] && has "$OUT21b" "completion mark is cleared" && ! marked_done "$B21/www/prairiepost-$HEAD12-kitchenerchronicle" && on_new "$B21/vhosts/kitchenerchronicle"'
OUT21c=$(run_upgrade "$B21" 2>&1); RC21c=$?
ok "so the next run finishes it instead of saying there is nothing to do" '[ "$RC21c" = 0 ] && has "$OUT21c" "no run finished that roll" && hasnt "$OUT21c" "Nothing to upgrade" && marked_done "$B21/www/prairiepost-$HEAD12-kitchenerchronicle"'

echo
echo "== scenario 22: a run pinned to a DIFFERENT commit while the vhosts name an unfinished release — refused"
B22=$(mktemp -d)
build_box "$B22" "$GATED_COMMIT"
OUT22=$(run_upgrade "$B22" PP_UPGRADE_FIXTURE_NGINX_FAIL=post 2>&1); RC22=$?
OUT22b=$(run_upgrade "$B22" PP_UPGRADE_SHA=0123456789abcdef0123456789abcdef01234567 2>&1); RC22b=$?
show "$(sed -n '/^== Map the papers/,$p' <<< "$OUT22b")"
ok "refused in the mapping step, before anything is touched" '[ "$RC22" != 0 ] && [ "$RC22b" != 0 ] && has "$OUT22b" "no run finished. Finish that roll first" && hasnt "$OUT22b" "Fetch the release template" && on_new "$B22/vhosts/kitchenerchronicle"'
OUT22c=$(run_upgrade "$B22" 2>&1); RC22c=$?
ok "the run at the right commit finishes"     '[ "$RC22c" = 0 ] && marked_done "$B22/www/prairiepost-$HEAD12-kitchenerchronicle"'

echo
echo "== scenario 23: a cron file left .paused by an earlier, stopped run comes back on the next run"
B23=$(mktemp -d)
build_box "$B23" "$GATED_COMMIT"
mv "$B23/cron/pp-fetch" "$B23/cron/pp-fetch.paused"
OUT23=$(run_upgrade "$B23" 2>&1); RC23=$?
ok "the roll brings it back, then pauses and resumes it like any other" '[ "$RC23" = 0 ] && has "$OUT23" "pp-fetch was left paused by an earlier, stopped roll" && has "$OUT23" "paused: pp-fetch" && has "$OUT23" "resumed: pp-fetch"'
ok "it ends active, on the new release"       '[ -f "$B23/cron/pp-fetch" ] && [ ! -f "$B23/cron/pp-fetch.paused" ] && on_new "$B23/cron/pp-fetch"'

echo
echo "== scenario 24: a pin that is not a commit id, and a tarball that is not the pin, are refused"
B24=$(mktemp -d)
build_box "$B24" "$GATED_COMMIT"
OUT24=$(run_upgrade "$B24" PP_UPGRADE_SHA=not-a-sha 2>&1); RC24=$?
ok "a bad pin is refused before anything is read" '[ "$RC24" != 0 ] && has "$OUT24" "PP_UPGRADE_SHA must be a lowercase hex commit id" && hasnt "$OUT24" "Map the papers"'
OTHER=$(mktemp --suffix=.tgz); git archive --prefix="prairiepost-0123456789abcdef0123456789abcdef01234567/" "$HEADSHA" | gzip > "$OTHER"
OUT24b=$(run_upgrade "$B24" PP_RELEASE_TARBALL="$OTHER" 2>&1); RC24b=$?
rm -f "$OTHER"
ok "a pre-staged tarball for another commit is refused" '[ "$RC24b" != 0 ] && has "$OUT24b" "is not the pinned commit $HEAD12" && on_old "$B24/vhosts/kitchenerchronicle" && [ "$(stored_version "$B24")" = 21 ]'

for B in "${BOXES[@]}"; do
  git worktree remove --force "$B/www/prairiepost-oldsha11-shared" 2>/dev/null
  rm -rf "$B"
done
git worktree prune 2>/dev/null
rm -rf "$SEEDS"
rm -f "$TARBALL" "$HTTP_BODY"

echo
if [ "$FAILS" = "1" ]; then
  echo "RELEASE FLOW BROKEN."
  exit 1
fi
echo "All release-flow scenarios correct."
