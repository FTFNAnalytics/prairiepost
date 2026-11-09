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
# its first match can fail the pipeline for output that DID match.
has()   { grep -qF -- "$2" <<< "$1"; }
hasnt() { ! grep -qF -- "$2" <<< "$1"; }
show()  { sed 's/^/   | /' <<< "$1"; }

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
git archive --prefix="prairiepost-head/" "$HEADSHA" | gzip > "$TARBALL"

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
# Make ONE release group fail verification for a real reason: the hub's
# own release resolves to a tenant that has no site row, so its new
# release refuses to boot. The papers' group is untouched and passes.
break_hub_tenant() { # box-dir
  cat > "$1/www/prairiepost-oldsha11-civismedia/config.php" <<CFG
<?php
return ['db' => ['driver' => 'sqlite', 'sqlite_path' => '$1/data/net.sqlite'],
        'site_slug' => 'no-such-paper', 'hub_slug' => 'civismedia',
        'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];
CFG
}
run_upgrade() { # box-dir [pre|post: force that nginx test to fail] -> rc
  PP_UPGRADE_FIXTURE=1 \
  PP_UPGRADE_FIXTURE_NGINX_FAIL="${2:-}" \
  PP_UPGRADE_VHOSTS_DIR="$1/vhosts" \
  PP_UPGRADE_CRON_DIR="$1/cron" \
  PP_UPGRADE_WWW="$1/www" \
  PP_UPGRADE_SNIPPET_DIR="$1/snippets" \
  PP_UPGRADE_SHA="$HEAD12" \
  PP_RELEASE_TARBALL="$TARBALL" \
  PP_BACKUP_DEST="$1/backups" \
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
echo "$OUT1" | grep -E "^(PASS|FAIL|FATAL)|migrating|already migrated|paused|resumed|recovery point|stored version|stored schema version" | sed 's/^/   /'
ok "roll succeeds"                            "[ $RC1 = 0 ]"
ok "preflight saw the recovery point"         "echo \"\$OUT1\" | grep -q 'recovery point: fixture-set'"
ok "crons paused for the window"              "echo \"\$OUT1\" | grep -q 'paused: pp-fetch'"
ok "crons resumed after verification"         "echo \"\$OUT1\" | grep -q 'resumed: pp-fetch'"
ok "cron file exists unpaused and repointed"  "[ -f $B1/cron/pp-fetch ] && [ ! -f $B1/cron/pp-fetch.paused ] && grep -q ${HEADSHA:0:12} $B1/cron/pp-fetch"
ok "historical .bak cron ignored entirely"    "grep -q oldsha11 $B1/cron/pp-fetch.bak.20250101 && ! grep -q ${HEADSHA:0:12} $B1/cron/pp-fetch.bak.20250101 && [ ! -f $B1/cron/pp-fetch.bak.20250101.paused ]"
ok "the shared schema migrated exactly once"  "[ \$(echo \"\$OUT1\" | grep -c 'migrating the schema') = 1 ]"
ok "the second group reused the migration"    "echo \"\$OUT1\" | grep -q 'already migrated this run'"
ok "legacy vhosts got the deny snippet injected" "grep -q prairiepost-deny $B1/vhosts/kitchenerchronicle && [ -f $B1/snippets/prairiepost-deny.conf ]"
ok "both vhosts repointed to the new release" "grep -q ${HEADSHA:0:12} $B1/vhosts/kitchenerchronicle && grep -q ${HEADSHA:0:12} $B1/vhosts/civismedia"
ok "both groups boot ready as their tenants"  "[ \$(echo \"\$OUT1\" | grep -c 'PASS (fixture)') = 2 ]"
J=$(php -r '$p = new PDO("sqlite:" . $argv[1]); echo implode(",", $p->query("SELECT version || \":\" || status FROM schema_migrations ORDER BY version")->fetchAll(PDO::FETCH_COLUMN));' "$B1/data/net.sqlite")
ok "journal: adopted at 18, steps applied"    "echo $J | grep -q '18:adopted,19:applied,20:applied'"
ok "the rise is read and reported (18 -> $HEADV)" 'has "$OUT1" "stored schema version after the step: $HEADV — ROSE from 18"'
ok "so the closing text offers no repoint"    'hasnt "$OUT1" "Rollback for any release group" && has "$OUT1" "NOT a rollback for this roll"'

echo
echo "== scenario 2: migration failure aborts BEFORE the serving switch"
B2=$(mktemp -d)
build_box "$B2"
php -r '$p = new PDO("sqlite:" . $argv[1]); $p->exec("CREATE TABLE media_orders (wrong_shape INTEGER)");' "$B2/data/net.sqlite"
OUT2=$(run_upgrade "$B2" 2>&1); RC2=$?
ok "the roll fails"                           "[ $RC2 != 0 ]"
ok "it names the migration as the cause"      "echo \"\$OUT2\" | grep -q 'MIGRATION FAILED'"
ok "vhosts still point at the OLD release"    "grep -q oldsha11 $B2/vhosts/kitchenerchronicle && ! grep -q ${HEADSHA:0:12} $B2/vhosts/kitchenerchronicle"
ok "cron restored to the OLD release, unpaused" "[ -f $B2/cron/pp-fetch ] && grep -q oldsha11 $B2/cron/pp-fetch && [ ! -f $B2/cron/pp-fetch.paused ]"
ok "the database is behind, not corrupted"    "php -r '\$p = new PDO(\"sqlite:$B2/data/net.sqlite\"); echo \$p->query(\"SELECT svalue FROM settings WHERE site_id=0 AND skey=\\\"schema_version\\\"\")->fetchColumn();' | grep -q '^18$'"
ok "no step committed, so the old wording stands" 'has "$OUT2" "the schema migration failed; the box still serves the old release" && hasnt "$OUT2" "ROSE" && hasnt "$OUT2" "could not be read back"'

echo
echo "== scenario 3: no recovery point, no roll"
B3=$(mktemp -d)
build_box "$B3"
rm "$B3/backups/state.json"
OUT3=$(run_upgrade "$B3" 2>&1); RC3=$?
ok "preflight refuses"                        "[ $RC3 != 0 ]"
ok "and says why"                             "echo \"\$OUT3\" | grep -q 'recovery point'"
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
http_front "$B4/www/prairiepost-$HEAD12-shared"
echo "   NEW release over HTTP: $HTTP_CODE  $(grep -o '<title>[^<]*</title>' "$HTTP_BODY" | head -1)"
ok "NEW release, HTTP front page: 200"        '[ "$HTTP_CODE" = 200 ]'

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
# Observed, not asserted: this path was left exactly as it was, and what
# it does to the PASSING group's cron file is worth seeing.
if [ -f "$B6/cron/pp-fetch" ]; then C6="present"; elif [ -f "$B6/cron/pp-fetch.paused" ]; then C6="still paused"; else C6="GONE (only $(cd "$B6/cron" && ls | tr '\n' ' ')remain)"; fi
echo "note the passing group's cron file after the other group's restore: $C6"

echo
echo "== scenario 7: nginx test fails BEFORE the migration — stopped, schema untouched"
B7=$(mktemp -d)
build_box "$B7" "$GATED_COMMIT"
OUT7=$(run_upgrade "$B7" pre 2>&1); RC7=$?
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
OUT8=$(run_upgrade "$B8" post 2>&1); RC8=$?
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
OUT10=$(run_upgrade "$B10" post 2>&1); RC10=$?
show "$(sed -n '/^== nginx config test + reload/,$p' <<< "$OUT10")"
ok "the roll fails"                           '[ "$RC10" != 0 ]'
ok "the old message, word for word"           'has "$OUT10" "nginx test failed — every vhost and cron file restored, nothing changed"'
ok "vhosts are back on the old release"       'on_old "$B10/vhosts/kitchenerchronicle" && on_old "$B10/vhosts/civismedia"'
ok "cron is back on the old release, unpaused" '[ -f "$B10/cron/pp-fetch" ] && on_old "$B10/cron/pp-fetch" && [ ! -f "$B10/cron/pp-fetch.paused" ]'
ok "the version did not move"                 '[ "$(stored_version "$B10")" = "$HEADV" ] && hasnt "$OUT10" "ROSE"'

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
