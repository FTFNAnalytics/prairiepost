#!/usr/bin/env bash
#
# Exercise tools/vps/upgrade-papers.sh against a SYNTHETIC box — the
# Phase 2 release-procedure proof, never run against the real VPS. SLOW
# (builds a genuine old release from the v18-era commit and migrates it).
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
set -uo pipefail
cd "$(dirname "$0")/.."
ROOT=$(pwd)
OLD_COMMIT=83e1467   # the v18-era release (schema 18, own seed-all)
FAILS=0
ok() { if eval "$2"; then echo "ok   $1"; else echo "FAIL $1"; FAILS=1; fi; }

HEADSHA=$(git rev-parse HEAD)
TARBALL=$(mktemp --suffix=.tgz)
git archive --prefix="prairiepost-head/" "$HEADSHA" | gzip > "$TARBALL"

build_box() { # box-dir  -> populates $BOX
  BOX="$1"
  mkdir -p "$BOX"/{www,vhosts,cron,backups,data}
  # A genuine old release with a genuine v18 database, built by ITS OWN
  # tooling — provenance: commit $OLD_COMMIT.
  git worktree add --detach "$BOX/www/prairiepost-oldsha11-shared" "$OLD_COMMIT" >/dev/null 2>&1
  ( cd "$BOX/www/prairiepost-oldsha11-shared" && bash tools/seed-all.sh "$BOX/data/net.sqlite" >/dev/null 2>&1 ) \
    || { echo "FATAL: old-release seed failed"; exit 1; }
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
  php -r 'echo json_encode(["ok" => true, "set_id" => "fixture-set", "finished_epoch" => time(), "finished_at" => date("Y-m-d H:i:s")]);' > "$BOX/backups/state.json"
}
run_upgrade() { # box-dir -> rc
  PP_UPGRADE_FIXTURE=1 \
  PP_UPGRADE_VHOSTS_DIR="$1/vhosts" \
  PP_UPGRADE_CRON_DIR="$1/cron" \
  PP_UPGRADE_WWW="$1/www" \
  PP_UPGRADE_SHA="${HEADSHA:0:12}" \
  PP_RELEASE_TARBALL="$TARBALL" \
  PP_BACKUP_DEST="$1/backups" \
  bash "$ROOT/tools/vps/upgrade-papers.sh"
}

echo "== scenario 1: the happy roll (v18 -> HEAD, two groups, one schema)"
B1=$(mktemp -d)
build_box "$B1"
OUT1=$(run_upgrade "$B1" 2>&1); RC1=$?
echo "$OUT1" | grep -E "^(PASS|FAIL|FATAL)|migrating|already migrated|paused|resumed|recovery point" | sed 's/^/   /'
ok "roll succeeds"                            "[ $RC1 = 0 ]"
ok "preflight saw the recovery point"         "echo \"\$OUT1\" | grep -q 'recovery point: fixture-set'"
ok "crons paused for the window"              "echo \"\$OUT1\" | grep -q 'paused: pp-fetch'"
ok "crons resumed after verification"         "echo \"\$OUT1\" | grep -q 'resumed: pp-fetch'"
ok "cron file exists unpaused and repointed"  "[ -f $B1/cron/pp-fetch ] && [ ! -f $B1/cron/pp-fetch.paused ] && grep -q ${HEADSHA:0:12} $B1/cron/pp-fetch"
ok "the shared schema migrated exactly once"  "[ \$(echo \"\$OUT1\" | grep -c 'migrating the schema') = 1 ]"
ok "the second group reused the migration"    "echo \"\$OUT1\" | grep -q 'already migrated this run'"
ok "both vhosts repointed to the new release" "grep -q ${HEADSHA:0:12} $B1/vhosts/kitchenerchronicle && grep -q ${HEADSHA:0:12} $B1/vhosts/civismedia"
ok "both groups boot ready as their tenants"  "[ \$(echo \"\$OUT1\" | grep -c 'PASS (fixture)') = 2 ]"
J=$(php -r '$p = new PDO("sqlite:" . $argv[1]); echo implode(",", $p->query("SELECT version || \":\" || status FROM schema_migrations ORDER BY version")->fetchAll(PDO::FETCH_COLUMN));' "$B1/data/net.sqlite")
ok "journal: adopted at 18, steps applied"    "echo $J | grep -q '18:adopted,19:applied,20:applied'"

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

echo
echo "== scenario 3: no recovery point, no roll"
B3=$(mktemp -d)
build_box "$B3"
rm "$B3/backups/state.json"
OUT3=$(run_upgrade "$B3" 2>&1); RC3=$?
ok "preflight refuses"                        "[ $RC3 != 0 ]"
ok "and says why"                             "echo \"\$OUT3\" | grep -q 'recovery point'"
ok "nothing changed"                          "grep -q oldsha11 $B3/vhosts/kitchenerchronicle"

for B in "$B1" "$B2" "$B3"; do
  git worktree remove --force "$B/www/prairiepost-oldsha11-shared" 2>/dev/null
  rm -rf "$B"
done
git worktree prune 2>/dev/null
rm -f "$TARBALL"

echo
if [ "$FAILS" = "1" ]; then
  echo "RELEASE FLOW BROKEN."
  exit 1
fi
echo "All release-flow scenarios correct."
