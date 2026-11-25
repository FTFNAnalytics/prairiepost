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
# The check of the re-review fixes (a roll that HELD a group, and the log):
#  25. a group held after a rise is marked HELD, not complete, and a roll
#      of another commit (the fix) is NOT refused: the group that passes
#      moves to it; the held group, still failing, goes back to its held
#      release (which serves the schema); with the fault fixed, it moves
#      too;
#  26. a STOPPED roll blocks another commit, and the refusal names
#      PP_UPGRADE_ABANDON; the override must name that roll's commit; with
#      it, the roll is replaced, judged by the version the stopped roll
#      found, and no rollback is offered;
#  27. a hangup and a Ctrl-C sent to the run's whole process group, as a
#      terminal sends them: the hangup stops neither the roll nor its log;
#      the Ctrl-C before the migration puts everything back and its report
#      reaches the log.
#
# The masthead guard itself, over HTTP (PR 74). These boxes have a
# stand-in for nginx (front_start): one PHP built-in server that answers
# each request from whichever release directory the vhost file for its
# Host header names at that moment. Handed its port
# (PP_UPGRADE_FIXTURE_HTTP_PORT), upgrade-papers.sh runs its REAL
# baseline capture and its REAL per-domain comparison — status code,
# <title>, stylesheet set — with only the probe's transport changed
# (plain HTTP on that port instead of TLS on 443). The box is shaped like
# production: one release directory serving several papers by hostname,
# the hub in a directory of its own, and one tenant with no site row.
#  28. a healthy roll that raises the version: every domain is captured
#      before and compared after; the tenant with no site row is held to
#      its 503; the baseline is saved with a record of it; and the roll's
#      lines are word for word what they were before the recovery
#      baseline existed.
#  29. a release missing one paper's template tree, after a rise: the
#      guard catches it by the stylesheet set and the group is HELD. A
#      roll of the fix takes the failing answer as its baseline, reads the
#      repair as a failure and puts the group back (the assertions marked
#      GAP: what a roll does, and must go on doing, when nobody names a
#      recovery baseline). After that restore the pointer names the held
#      release's record, never the roll's own (which holds the failure).
#      The same roll with PP_UPGRADE_BASELINE naming the held roll's
#      record passes the repair, says how old the record is, and says
#      which baseline each domain was held to.
#  30. the vhost files copied back to the old release after a rise: every
#      domain answers the readiness gate's 503. A roll holds each of them
#      to that 503, reads 200 as a failure and puts both groups back
#      (GAP) — and says so before it changes anything, names the saved
#      record, and does not offer the .bak files as a rollback. With the
#      recovery baseline: a roll onto the release that lacks the template
#      still FAILS that paper (a recovery baseline is not a bypass); a
#      roll onto HEAD brings every domain back, and the tenant with no
#      site row stays on the 503 its record says.
#  31. held after a rise, then the failing domain taken down by hand (the
#      single-vhost copy-back in docs/MIGRATION-COMPAT.md). An ordinary
#      roll moves the other papers and leaves that domain down (and the
#      passing group keeps its cron file); a missing or malformed recovery
#      baseline stops a roll before it starts; the recovery roll, with a
#      hand-written file naming that one domain, brings it back.
#  32. PRECEDENCE, a roll being finished: a roll stopped after the reload
#      had saved a baseline taken while a paper was failing. Finishing it
#      as it stands holds the paper to that failure and holds its group;
#      finishing it with PP_UPGRADE_BASELINE, the named file wins over
#      the saved baseline, domain by domain, and the roll completes.
#  33. PRECEDENCE, a roll replaced under PP_UPGRADE_ABANDON, domain by
#      domain: the named file records only the failing paper and wins for
#      it; every other domain is held to the abandoned roll's saved
#      baseline; the new release's own baseline says which is which.
#
# A fixture has no nginx and no TLS stack: the nginx test is skipped
# unless PP_UPGRADE_FIXTURE_NGINX_FAIL forces it to fail, and in
# scenarios 1 to 27 verification is a CLI boot of each new release
# directory (28 to 33 probe over HTTP, above). What a failed group gets
# (restore or hold) is the code a real box runs.
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
  # A hand-written (legacy) block: a PHP handler and a `location /` that
  # 404s whatever no rewrite names, as DEPLOY.md's blocks do.
  printf 'server {\n  root %s; # shared\n  server_name kitchenerchronicle.test;\n  location ~ \\.php$ {\n    fastcgi_pass unix:/run/php/php8.3-fpm.sock;\n  }\n  location / { try_files $uri $uri/ =404; }\n}\n' "$BOX/www/prairiepost-oldsha11-shared" > "$BOX/vhosts/kitchenerchronicle"
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
run_upgrade_session() { # box-dir [VAR=value ...] -> rc; the run leads its own session and process group, as at a terminal
  local box="$1"
  shift
  setsid --wait env PP_UPGRADE_FIXTURE=1 \
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


# ---------------------------------------------------------------------
# The HTTP stage (scenarios 28 on, PR 74).
# ---------------------------------------------------------------------
# A box shaped like production: the papers' release directory serves
# several domains and its config picks the tenant from the hostname on
# each request, as the live one does; the hub has a directory of its own.
#   prairiedispatch.test, kitchenerchronicle.test   the papers' group
#   dormant.test       the papers' group; a tenant with NO site row, which
#                      answers 503 "This site is not provisioned here"
#   civismedia.test    the hub's group
build_http_box() { # box-dir [commit] -> populates $BOX
  build_box "$1" "${2:-$GATED_COMMIT}"
  local dir
  for dir in shared civismedia; do
    cat > "$BOX/www/prairiepost-oldsha11-$dir/config.php" <<CFG
<?php
\$host = strtolower((string) preg_replace('/:\\d+\$/', '', \$_SERVER['HTTP_HOST'] ?? ''));
\$slug = ['prairiedispatch.test' => 'prairiedispatch', 'kitchenerchronicle.test' => 'kitchener-chronicle',
         'civismedia.test' => 'civismedia', 'dormant.test' => 'no-such-paper'][\$host] ?? 'prairiedispatch';
return ['db' => ['driver' => 'sqlite', 'sqlite_path' => '$BOX/data/net.sqlite'],
        'site_slug' => \$slug, 'hub_slug' => 'civismedia',
        'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];
CFG
  done
  printf 'server {\n  root %s;\n  server_name prairiedispatch.test;\n}\n' "$BOX/www/prairiepost-oldsha11-shared" > "$BOX/vhosts/prairiedispatch"
  printf 'server {\n  root %s;\n  server_name dormant.test;\n}\n' "$BOX/www/prairiepost-oldsha11-shared" > "$BOX/vhosts/dormant"
}
# The stand-in for nginx: ONE PHP built-in server per box. Its router
# reads the vhost files on every request and hands the request to the
# release directory that the file for the request's Host names right
# then — so a rewrite, a restore or a hold by upgrade-papers.sh changes
# what the next probe sees. It has no reload: the files are its
# configuration, which is what nginx serves after a successful reload.
FRONT_PID=""
FRONT_PORT=""
front_start() { # box-dir -> sets $FRONT_PORT and $FRONT_PID
  local try i body
  cat > "$1/front.php" <<'FRONT'
<?php
$host = strtolower((string) preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
foreach (glob(getenv('PP_FIXTURE_VHOSTS') . '/*') ?: [] as $vh) {
    // Dotted names are the upgrader's own .bak.* and .paused artifacts.
    if (!is_file($vh) || str_contains(basename($vh), '.')) {
        continue;
    }
    $text = (string) file_get_contents($vh);
    if (!preg_match('/server_name\s+([^;\s]+)/', $text, $name) || strtolower($name[1]) !== $host) {
        continue;
    }
    if (preg_match('/root\s+([^;\s]+)\s*;/', $text, $root) && is_file($root[1] . '/router.php')) {
        header('X-Fixture-Release: ' . basename($root[1]));
        chdir($root[1]);
        return require $root[1] . '/router.php';
    }
    break;
}
http_response_code(502);
header('Content-Type: text/plain');
echo "fixture front: no vhost file names a release directory for host '$host'\n";
return true;
FRONT
  for try in 1 2 3 4 5; do
    FRONT_PORT=$((20000 + RANDOM % 20000))
    ( cd "$1" && PP_FIXTURE_VHOSTS="$1/vhosts" exec php -S "127.0.0.1:$FRONT_PORT" front.php >"$1/front.log" 2>&1 ) &
    FRONT_PID=$!
    for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20; do
      # Its own 502 for a host nobody serves proves it is OURS on that port.
      body=$(curl -s -m 5 -H 'Host: nobody.invalid' "http://127.0.0.1:$FRONT_PORT/" 2>/dev/null)
      case "$body" in "fixture front:"*) return 0 ;; esac
      kill -0 "$FRONT_PID" 2>/dev/null || break   # it exited: the port was taken
      sleep 0.25
    done
    kill "$FRONT_PID" 2>/dev/null
    wait "$FRONT_PID" 2>/dev/null
  done
  echo "FATAL: the fixture's HTTP stand-in did not start"
  exit 1
}
front_stop() {
  [ -n "$FRONT_PID" ] || return 0
  kill "$FRONT_PID" 2>/dev/null
  wait "$FRONT_PID" 2>/dev/null
  FRONT_PID=""
}
# What a DOMAIN answers through the stand-in right now: sets $HTTP_CODE,
# $HTTP_TITLE, $HTTP_CSS (read the way upgrade-papers.sh reads them) and
# $HTTP_FROM (the release directory that answered); body in $HTTP_BODY.
HTTP_HEAD=$(mktemp)
front_get() { # domain
  HTTP_CODE=$(curl -s -m 15 -o "$HTTP_BODY" -D "$HTTP_HEAD" -w '%{http_code}' --resolve "$1:$FRONT_PORT:127.0.0.1" "http://$1:$FRONT_PORT/")
  HTTP_TITLE=$(grep -o '<title>[^<]*</title>' "$HTTP_BODY" | head -1)
  HTTP_CSS=$(grep -o '/assets/css/[a-z0-9._-]*\.css' "$HTTP_BODY" | sort -u | tr '\n' ' ')
  HTTP_FROM=$(sed -n 's/^X-Fixture-Release: //p' "$HTTP_HEAD" | tr -d '\r')
}
gate_503() { # domain -> true when it answers the readiness gate's own 503 page
  front_get "$1"
  [ "$HTTP_CODE" = 503 ] && grep -q "This site is being updated" "$HTTP_BODY"
}
on_rel() { grep -q "prairiepost-$2-" "$1"; }   # a vhost or cron file names the release with that commit
has_line() { grep -qxF -- "$2" <<< "$1"; }     # the output has exactly that line, nothing before or after it
record_in() { printf '%s\n' "$1"/.pp-roll-baseline.* | LC_ALL=C sort | tail -n 1; }   # release dir -> the newest record of a baseline saved in it
records() { ls "$1"/www/*/.pp-roll-baseline.* 2>/dev/null | wc -l; }                   # box-dir -> how many records it holds
named_in() { sed -n "s/.*$2 \\([^ ]*\\)\$/\\1/p" <<< "$1" | head -n 1; }               # output, the words before a path -> that path
gates() { # domains... -> prints how many of them answer the readiness gate's 503 right now
  local n=0 d
  for d in "$@"; do gate_503 "$d" && n=$((n + 1)); done
  echo "$n"
}
roll() { # box-dir [VAR=value ...] -> rc: a roll of an HTTP box, the masthead guard run against its stand-in
  local box="$1"
  shift
  # A roll names its .bak files and its baseline record by the second it
  # started in; a second roll of the same box waits for the next one.
  while ls "$box"/vhosts/*.bak."$(date +%s)" >/dev/null 2>&1; do sleep 0.2; done
  run_upgrade "$box" PP_UPGRADE_FIXTURE_HTTP_PORT="$FRONT_PORT" "$@"
}
# A candidate with one paper's template tree missing — the failure the
# stylesheet check exists for (a release cut from a line that never had
# the paper's files): HEAD's own tree minus the Kitchener Chronicle's
# chrome partials, views and stylesheet, under a commit id of its own as
# codeload names it. Its front page still answers 200 with the right
# <title>, because the title is in the database.
LOSSY_SHA=c0ffee00c0ffee00c0ffee00c0ffee00c0ffee00
LOSSY12=${LOSSY_SHA:0:12}
LOSSY_TARBALL=""
make_lossy_tarball() { # -> sets $LOSSY_TARBALL
  [ -n "$LOSSY_TARBALL" ] && return 0
  local t
  t=$(mktemp -d)
  LOSSY_TARBALL=$(mktemp --suffix=.tgz)
  tar -xzf "$TARBALL" -C "$t" \
    && mv "$t/prairiepost-$HEADSHA" "$t/prairiepost-$LOSSY_SHA" \
    && rm "$t/prairiepost-$LOSSY_SHA"/app/views/chrome/kitchener-*.php "$t/prairiepost-$LOSSY_SHA"/app/views/*-kitchener.php "$t/prairiepost-$LOSSY_SHA/assets/css/kitchener.css" \
    && tar -czf "$LOSSY_TARBALL" -C "$t" "prairiepost-$LOSSY_SHA" \
    || { echo "FATAL: cannot build the candidate without the Chronicle's template tree"; exit 1; }
  rm -rf "$t"
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
ok "the legacy block's fallback now reaches router.php, once" '[ "$(grep -c pp-front-controller $B1/vhosts/kitchenerchronicle)" = 1 ] && grep -qF "try_files \$uri \$uri/ /router.php\$is_args\$args;" $B1/vhosts/kitchenerchronicle && grep -qF "fastcgi_pass unix:/run/php/php8.3-fpm.sock;" $B1/vhosts/kitchenerchronicle && ! grep -qF "=404; }" $B1/vhosts/kitchenerchronicle'
ok "the legacy block keeps /tips out of its access log, once" '[ "$(grep -c pp-tips-unlogged $B1/vhosts/kitchenerchronicle)" = 1 ] && grep -qF "access_log off;" $B1/vhosts/kitchenerchronicle'
ok "a block with no PHP handler is left alone by both" '! grep -q -e pp-front-controller -e pp-tips-unlogged $B1/vhosts/civismedia'
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
ok "the group that passed is marked; the held one is not, and carries the HELD mark" 'marked_done "$B5/www/prairiepost-$HEAD12-kitchenerchronicle" && ! marked_done "$B5/www/prairiepost-$HEAD12-civismedia" && [ -f "$B5/www/prairiepost-$HEAD12-civismedia/.pp-roll-held" ]'
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
ok "before the migration, it puts everything back and says nothing changed" 'has "$OUT17" "every vhost and cron file restored to what it named before this run" && on_old "$B17/vhosts/kitchenerchronicle" && on_old "$B17/vhosts/civismedia" && [ -f "$B17/cron/pp-fetch" ] && on_old "$B17/cron/pp-fetch" && [ ! -f "$B17/cron/pp-fetch.paused" ] && [ "$(stored_version "$B17")" = 21 ]'
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
# The shape of "A, then B, then back to A": the HEAD directory exists and
# carries a completion mark, while the box serves and stores an earlier
# release — the vhosts and the cron file name the old release again, and
# the database is the one that release was seeded with (schema 21).
for f in "$B21"/vhosts/kitchenerchronicle "$B21"/vhosts/civismedia "$B21"/cron/pp-fetch; do cp "$(ls -t "$f".bak.* | head -1)" "$f"; done
cp "$SEEDS/${GATED_COMMIT:0:12}.sqlite" "$B21/data/net.sqlite"
OUT21b=$(run_upgrade "$B21" PP_UPGRADE_FIXTURE_NGINX_FAIL=post 2>&1); RC21b=$?
ok "the roll into the existing directory clears its old mark, then stops at the nginx test after the rise" '[ "$RC21b" != 0 ] && has "$OUT21b" "completion mark is cleared" && ! marked_done "$B21/www/prairiepost-$HEAD12-kitchenerchronicle" && on_new "$B21/vhosts/kitchenerchronicle" && [ "$(stored_version "$B21")" = "$HEADV" ]'
OUT21c=$(run_upgrade "$B21" 2>&1); RC21c=$?
ok "so the next run finishes it instead of saying there is nothing to do" '[ "$RC21c" = 0 ] && has "$OUT21c" "no run finished that roll" && hasnt "$OUT21c" "Nothing to upgrade" && marked_done "$B21/www/prairiepost-$HEAD12-kitchenerchronicle"'

echo
echo "== scenario 22: a run pinned to a DIFFERENT commit while the vhosts name an unfinished release — refused"
B22=$(mktemp -d)
build_box "$B22" "$GATED_COMMIT"
OUT22=$(run_upgrade "$B22" PP_UPGRADE_FIXTURE_NGINX_FAIL=post 2>&1); RC22=$?
OUT22b=$(run_upgrade "$B22" PP_UPGRADE_SHA=0123456789abcdef0123456789abcdef01234567 2>&1); RC22b=$?
show "$(sed -n '/^== Map the papers/,$p' <<< "$OUT22b")"
ok "refused in the mapping step, before anything is touched" '[ "$RC22" != 0 ] && [ "$RC22b" != 0 ] && has "$OUT22b" "no run finished — nothing changed. Finish that roll first" && hasnt "$OUT22b" "Fetch the release template" && on_new "$B22/vhosts/kitchenerchronicle"'
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

# "The fix": HEAD's tree under another commit id, as codeload names it.
FIXSHA=fedcba9876543210fedcba9876543210fedcba98
FIX12=${FIXSHA:0:12}
FIXTGZ=$(mktemp --suffix=.tgz)
git archive --prefix="prairiepost-$FIXSHA/" "$HEADSHA" | gzip > "$FIXTGZ"
on_fix() { grep -q "$FIX12" "$1"; }

echo
echo "== scenario 25: a group HELD after a rise, then a roll of another commit (the fix) — not refused"
B25=$(mktemp -d)
build_box "$B25" "$GATED_COMMIT"
break_hub_tenant "$B25"
OUT25=$(run_upgrade "$B25" 2>&1); RC25=$?
ok "the roll holds the hub's group: marked HELD, not complete; the papers' group complete" '[ "$RC25" != 0 ] && [ -f "$B25/www/prairiepost-$HEAD12-civismedia/.pp-roll-held" ] && ! marked_done "$B25/www/prairiepost-$HEAD12-civismedia" && marked_done "$B25/www/prairiepost-$HEAD12-kitchenerchronicle"'
ok "its closing text: a roll of another commit is not refused" 'has "$OUT25" "a roll of another commit (the fix) is not refused" && hasnt "$OUT25" "leave the domain up while a fix is rolled"'
OUT25b=$(run_upgrade "$B25" PP_UPGRADE_SHA="$FIXSHA" PP_RELEASE_TARBALL="$FIXTGZ" 2>&1); RC25b=$?
show "$(sed -n '/^== Upgrade each release/,/^== Read each schema/p;/^== Verify every domain/,/^== Fallback/p' <<< "$OUT25b")"
ok "the fix is NOT refused, and says it replaces a held release" 'hasnt "$OUT25b" "Finish that roll first" && has "$OUT25b" "ended HELD"'
ok "the papers move to the fix and pass" '[ "$RC25b" = 0 ] && on_fix "$B25/vhosts/kitchenerchronicle" && marked_done "$B25/www/prairiepost-$FIX12-kitchenerchronicle"'
ok "the hub, still failing, goes back to its held release, which serves the schema" 'has "$OUT25b" "WARN: at least one release group was restored" && on_new "$B25/vhosts/civismedia" && [ "$(stored_version "$B25")" = "$HEADV" ] && [ -f "$B25/cron/pp-fetch" ] && [ ! -f "$B25/cron/pp-fetch.paused" ]'
# The fault, fixed (in the fixture it is the hub's carried config).
cat > "$B25/www/prairiepost-$HEAD12-civismedia/app/config.site.php" <<CFG
<?php
return ['db' => ['driver' => 'sqlite', 'sqlite_path' => '$B25/data/net.sqlite'],
        'site_slug' => 'prairiedispatch', 'hub_slug' => 'civismedia',
        'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];
CFG
OUT25c=$(run_upgrade "$B25" PP_UPGRADE_SHA="$FIXSHA" PP_RELEASE_TARBALL="$FIXTGZ" 2>&1); RC25c=$?
ok "with the fault fixed, the hub moves to the fix too; both are marked" '[ "$RC25c" = 0 ] && has "$OUT25c" "already on $FIX12" && on_fix "$B25/vhosts/civismedia" && marked_done "$B25/www/prairiepost-$FIX12-civismedia" && marked_done "$B25/www/prairiepost-$FIX12-kitchenerchronicle"'

echo
echo "== scenario 26: a STOPPED roll that cannot be finished at its own commit — PP_UPGRADE_ABANDON"
B26=$(mktemp -d)
build_box "$B26" "$GATED_COMMIT"
OUT26=$(run_upgrade "$B26" PP_UPGRADE_FIXTURE_NGINX_FAIL=post 2>&1); RC26=$?
ok "the first roll stops after the rise (no mark of either kind)" '[ "$RC26" != 0 ] && [ "$(stored_version "$B26")" = "$HEADV" ] && ! marked_done "$B26/www/prairiepost-$HEAD12-kitchenerchronicle" && [ ! -f "$B26/www/prairiepost-$HEAD12-kitchenerchronicle/.pp-roll-held" ]'
OUT26b=$(run_upgrade "$B26" PP_UPGRADE_SHA="$FIXSHA" PP_RELEASE_TARBALL="$FIXTGZ" 2>&1); RC26b=$?
ok "another commit is refused, and the refusal names the override" '[ "$RC26b" != 0 ] && has "$OUT26b" "Finish that roll first" && has "$OUT26b" "PP_UPGRADE_ABANDON=<its full commit id>" && on_new "$B26/vhosts/kitchenerchronicle"'
OUT26c=$(run_upgrade "$B26" PP_UPGRADE_SHA="$FIXSHA" PP_RELEASE_TARBALL="$FIXTGZ" PP_UPGRADE_ABANDON="$FIXSHA" 2>&1); RC26c=$?
ok "an override naming the commit being rolled is refused" '[ "$RC26c" != 0 ] && has "$OUT26c" "names the commit this run is rolling"'
OUT26d=$(run_upgrade "$B26" PP_UPGRADE_SHA="$FIXSHA" PP_RELEASE_TARBALL="$FIXTGZ" PP_UPGRADE_ABANDON="$HEADSHA" 2>&1); RC26d=$?
show "$(sed -n '/^== Map the papers/,/^== Capture/p;/^== Read each schema/,/^== nginx config test BEFORE/p;/^== Done/,$p' <<< "$OUT26d")"
ok "named, the stopped roll is replaced: both groups on the fix, booted, marked" '[ "$RC26d" = 0 ] && has "$OUT26d" "ABANDONED by PP_UPGRADE_ABANDON" && [ "$(grep -c "PASS (fixture)" <<< "$OUT26d")" = 2 ] && on_fix "$B26/vhosts/kitchenerchronicle" && on_fix "$B26/vhosts/civismedia" && marked_done "$B26/www/prairiepost-$FIX12-kitchenerchronicle" && marked_done "$B26/www/prairiepost-$FIX12-civismedia"'
ok "judged by the version the stopped roll found (21); no rollback offered" 'has "$OUT26d" "found stored version 21; it is $HEADV now" && has "$OUT26d" "ROSE from 21 to $HEADV" && hasnt "$OUT26d" "Rollback for any release group" && has "$OUT26d" "abandoned with PP_UPGRADE_ABANDON"'
OUT26e=$(run_upgrade "$B26" PP_UPGRADE_SHA="$FIXSHA" PP_RELEASE_TARBALL="$FIXTGZ" PP_UPGRADE_ABANDON="$HEADSHA" 2>&1); RC26e=$?
ok "an override left set when nothing is stopped is refused, nothing changed" '[ "$RC26e" != 0 ] && has "$OUT26e" "no vhost names a stopped roll of that commit" && hasnt "$OUT26e" "Capture every domain"'

echo
echo "== scenario 27: a hangup and a Ctrl-C to the run's whole process group, as a terminal sends them"
B27=$(mktemp -d)
build_box "$B27" "$GATED_COMMIT"
OUT27=$(run_upgrade_session "$B27" PP_UPGRADE_FIXTURE_DIE=after-pause:GROUP-HUP 2>&1); RC27=$?
LOG27=$(ls "$B27"/log/roll-*.log 2>/dev/null | tail -n 1)
ok "the hangup reached the whole group, and the roll went on to the end" '[ "$RC27" = 0 ] && has "$OUT27" "SIGHUP to this run" && [ "$(stored_version "$B27")" = "$HEADV" ] && marked_done "$B27/www/prairiepost-$HEAD12-kitchenerchronicle" && marked_done "$B27/www/prairiepost-$HEAD12-civismedia"'
ok "and the log holds the whole run, through Done" '[ -n "$LOG27" ] && [ "$(grep -c "PASS (fixture)" "$LOG27")" = 2 ] && grep -q "^== Done" "$LOG27"'
B27b=$(mktemp -d)
build_box "$B27b" "$GATED_COMMIT"
OUT27b=$(run_upgrade_session "$B27b" PP_UPGRADE_FIXTURE_DIE=after-pause:GROUP-INT 2>&1); RC27b=$?
LOG27b=$(ls "$B27b"/log/roll-*.log 2>/dev/null | tail -n 1)
ok "a Ctrl-C before the migration puts everything back (exit 130)" '[ "$RC27b" = 130 ] && on_old "$B27b/vhosts/kitchenerchronicle" && on_old "$B27b/cron/pp-fetch" && [ ! -f "$B27b/cron/pp-fetch.paused" ] && [ "$(stored_version "$B27b")" = 21 ]'
ok "and the handler's report reached the log" '[ -n "$LOG27b" ] && grep -q "INTERRUPTED by SIGINT" "$LOG27b" && grep -q "nothing changed" "$LOG27b"'
echo
echo "== scenario 28: the masthead guard over HTTP — a healthy roll that raises the version (21 -> $HEADV)"
B28=$(mktemp -d)
build_http_box "$B28"
front_start "$B28"
front_get prairiedispatch.test;    PD_K="$HTTP_CODE"; PD_T="$HTTP_TITLE"; PD_C="$HTTP_CSS"; PD_F="$HTTP_FROM"
front_get kitchenerchronicle.test; KC_K="$HTTP_CODE"; KC_T="$HTTP_TITLE"; KC_C="$HTTP_CSS"
front_get civismedia.test;         HUB_K="$HTTP_CODE"; HUB_T="$HTTP_TITLE"; HUB_C="$HTTP_CSS"; HUB_F="$HTTP_FROM"
front_get dormant.test;            DOR_K="$HTTP_CODE"
echo "   before the roll, through the stand-in, from the old release:"
echo "     prairiedispatch.test     $PD_K  $PD_T  [$PD_C]"
echo "     kitchenerchronicle.test  $KC_K  $KC_T  [$KC_C]"
echo "     civismedia.test          $HUB_K  $HUB_T  [$HUB_C]"
echo "     dormant.test             $DOR_K  $(grep -o 'This site is[^.]*\.' "$HTTP_BODY" | head -1)"
ok "each domain is answered by the OLD release, as its own tenant" '[ "$PD_K" = 200 ] && [ "$KC_K" = 200 ] && [ "$HUB_K" = 200 ] && [ -n "$PD_T" ] && [ -n "$KC_T" ] && [ -n "$HUB_T" ] && [ "$PD_T" != "$KC_T" ] && [ "$KC_T" != "$HUB_T" ] && [ "$PD_F" = prairiepost-oldsha11-shared ] && [ "$HUB_F" = prairiepost-oldsha11-civismedia ]'
ok "the Chronicle links its own stylesheet, the Dispatch does not" 'has "$KC_C" "/assets/css/kitchener.css" && hasnt "$PD_C" "/assets/css/kitchener.css"'
ok "the tenant with no site row answers 503, and it is NOT the gate's page" '[ "$DOR_K" = 503 ] && grep -q "This site is not provisioned here" "$HTTP_BODY" && ! grep -q "This site is being updated" "$HTTP_BODY"'
OUT28=$(roll "$B28" 2>&1); RC28=$?
show "$(sed -n '/^== Capture every domain/,/^== Fetch the release/p' <<< "$OUT28")"
show "$(sed -n '/^== Verify every domain/,$p' <<< "$OUT28")"
ok "roll succeeds"                            '[ "$RC28" = 0 ]'
ok "the schema rose"                          'has "$OUT28" "ROSE from 21" && [ "$(stored_version "$B28")" = "$HEADV" ]'
ok "the baseline is the REAL capture: title and stylesheet set per domain" 'has "$OUT28" "   prairiedispatch.test -> $PD_T [$PD_C]" && has "$OUT28" "   kitchenerchronicle.test -> $KC_T [$KC_C]" && has "$OUT28" "   civismedia.test -> $HUB_T [$HUB_C]"'
ok "the tenant with no site row is held to its 503" 'has "$OUT28" "   dormant.test answered 503 with no title — it will be held to 503 afterwards" && has "$OUT28" "PASS dormant.test -> 503 (unchanged)"'
ok "every domain is compared after the roll, none by CLI boot" 'has "$OUT28" "PASS prairiedispatch.test -> $PD_T" && has "$OUT28" "PASS kitchenerchronicle.test -> $KC_T" && has "$OUT28" "PASS civismedia.test -> $HUB_T" && [ "$(grep -c "^PASS " <<< "$OUT28")" = 4 ] && hasnt "$OUT28" "(fixture) "'
S28="$B28/www/prairiepost-$HEAD12-shared"
F28=$(record_in "$S28")
echo "   the baseline this roll saved in $S28 (and its record $F28):"
sed 's/^/   | /' "$S28/.pp-roll-baseline" 2>/dev/null
ok "the release's baseline is the capture, one line per domain, and its record is the same file" '[ -f "$F28" ] && cmp -s "$F28" "$S28/.pp-roll-baseline" && [ "$(grep -vc "^#" "$F28")" = 4 ] && grep -qxF "kitchenerchronicle.test|200|$KC_C|$KC_T" "$F28" && grep -qxF "prairiedispatch.test|200|$PD_C|$PD_T" "$F28" && grep -qxF "civismedia.test|200|$HUB_C|$HUB_T" "$F28" && grep -qxF "dormant.test|503||" "$F28" && has "$OUT28" "   baseline saved: $F28 (a record"'
# (Whole phrases, not the bare word: unprivileged, chown -R names every
# file of the release on stderr, and one of them is docs/ACCOUNT-RECOVERY.md.)
ok "an ordinary roll is held to nothing else: its lines are the old ones, word for word" 'has_line "$OUT28" "PASS kitchenerchronicle.test -> $KC_T" && has_line "$OUT28" "PASS dormant.test -> 503 (unchanged)" && hasnt "$OUT28" "RECOVERY baseline" && hasnt "$OUT28" "Recovery baseline" && hasnt "$OUT28" "recovery baseline" && hasnt "$OUT28" "readiness gate" && hasnt "$OUT28" "PP_UPGRADE_BASELINE" && hasnt "$OUT28" "Which baseline each domain"'
front_get kitchenerchronicle.test
ok "afterwards the NEW release answers: same masthead, same stylesheets" '[ "$HTTP_CODE" = 200 ] && [ "$HTTP_TITLE" = "$KC_T" ] && [ "$HTTP_CSS" = "$KC_C" ] && [ "$HTTP_FROM" = "prairiepost-$HEAD12-shared" ]'
front_get civismedia.test
ok "and the hub from its own new directory"   '[ "$HTTP_CODE" = 200 ] && [ "$HTTP_TITLE" = "$HUB_T" ] && [ "$HTTP_FROM" = "prairiepost-$HEAD12-civismedia" ]'
front_stop

echo
echo "== scenario 29: a release missing one paper's template tree, after a rise — HELD; then the roll of the fix"
make_lossy_tarball
B29=$(mktemp -d)
build_http_box "$B29"
front_start "$B29"
OUT29=$(roll "$B29" PP_UPGRADE_SHA="$LOSSY_SHA" PP_RELEASE_TARBALL="$LOSSY_TARBALL" 2>&1); RC29=$?
show "$(sed -n '/^== Verify every domain/,$p' <<< "$OUT29")"
L29="$B29/www/prairiepost-$LOSSY12-shared"
ok "roll 1 exits non-zero"                    '[ "$RC29" != 0 ]'
ok "the schema rose"                          'has "$OUT29" "ROSE from 21" && [ "$(stored_version "$B29")" = "$HEADV" ]'
ok "the guard catches it by the stylesheet set (the title is right)" 'has "$OUT29" "FAIL kitchenerchronicle.test stylesheet set changed — was [$KC_C] now [/assets/css/prairie.css ]" && hasnt "$OUT29" "wrong tenant"'
ok "the other three domains pass"             'has "$OUT29" "PASS prairiedispatch.test -> $PD_T" && has "$OUT29" "PASS dormant.test -> 503 (unchanged)" && has "$OUT29" "PASS civismedia.test -> $HUB_T"'
ok "the papers' group is HELD on the new release, not restored, and marked HELD" 'has "$OUT29" "NOT restoring release group" && [ "$(restore_lines "$OUT29")" = 0 ] && on_rel "$B29/vhosts/kitchenerchronicle" "$LOSSY12" && on_rel "$B29/vhosts/prairiedispatch" "$LOSSY12" && has "$OUT29" "NOT done" && [ -f "$L29/.pp-roll-held" ]'
front_get kitchenerchronicle.test
ok "so the Chronicle is served in public without its stylesheet" '[ "$HTTP_CODE" = 200 ] && [ "$HTTP_TITLE" = "$KC_T" ] && [ "$HTTP_CSS" = "/assets/css/prairie.css " ] && [ "$HTTP_FROM" = "prairiepost-$LOSSY12-shared" ]'
front_get prairiedispatch.test
ok "and the Dispatch, same group, is served as before" '[ "$HTTP_CODE" = 200 ] && [ "$HTTP_TITLE" = "$PD_T" ] && [ "$HTTP_CSS" = "$PD_C" ] && [ "$HTTP_FROM" = "prairiepost-$LOSSY12-shared" ]'
F29=$(record_in "$L29")
ok "the held release's baseline records the Chronicle WITH its stylesheet, and the closing text names it" '[ -f "$F29" ] && grep -qxF "kitchenerchronicle.test|200|$KC_C|$KC_T" "$F29" && has_line "$OUT29" "      PP_UPGRADE_BASELINE=$F29"'
# THE GAP (a). Roll 2 is the fix: HEAD has the Chronicle's template tree.
# Nothing tells the roll that the Chronicle was already failing when it
# started — this is a roll WITHOUT a recovery baseline, and it must go on
# behaving exactly like this.
OUT29B=$(roll "$B29" 2>&1); RC29B=$?
show "$(sed -n '/^== Capture every domain/,/^== Fetch the release/p' <<< "$OUT29B")"
show "$(sed -n '/^== Verify every domain/,$p' <<< "$OUT29B")"
ok "GAP roll 2 takes the FAILING answer as the Chronicle's baseline" 'has "$OUT29B" "   kitchenerchronicle.test -> $KC_T [/assets/css/prairie.css ]"'
ok "GAP so the repair reads as a failure"     'has_line "$OUT29B" "FAIL kitchenerchronicle.test stylesheet set changed — was [/assets/css/prairie.css ] now [$KC_C]"'
ok "GAP the version did not rise, so the papers' group goes back to its held release" 'has "$OUT29B" "stored schema version after the step: $HEADV (unchanged)" && [ "$(restore_lines "$OUT29B")" = 1 ] && hasnt "$OUT29B" "NOT restoring" && on_rel "$B29/vhosts/kitchenerchronicle" "$LOSSY12" && on_rel "$B29/vhosts/prairiedispatch" "$LOSSY12" && on_rel "$B29/vhosts/dormant" "$LOSSY12"'
ok "GAP the roll ends exit 0 with a WARN"     '[ "$RC29B" = 0 ] && has "$OUT29B" "WARN: at least one release group was restored"'
front_get kitchenerchronicle.test
ok "GAP the Chronicle is STILL served without its stylesheet" '[ "$HTTP_CODE" = 200 ] && [ "$HTTP_CSS" = "/assets/css/prairie.css " ] && [ "$HTTP_FROM" = "prairiepost-$LOSSY12-shared" ]'
ok "the hub's group, healthy throughout, did move to the fix" 'on_new "$B29/vhosts/civismedia" && has "$OUT29B" "PASS civismedia.test -> $HUB_T"'
F29B=$(record_in "$B29/www/prairiepost-$HEAD12-shared")
ok "roll 2 names the held roll's record as it replaces the held release" 'has "$OUT29B" "ended HELD" && has_line "$OUT29B" "   To hold them instead to what they answered before the held roll changed anything: PP_UPGRADE_BASELINE=$F29"'
P29B=$(sed -n '/^WARN: at least one release group was restored/,/^== /p' <<< "$OUT29B")   # the pointer after the restore
ok "this roll's own record is the FAILING answer, the held roll's is not" '[ "$F29B" != "$F29" ] && grep -qxF "kitchenerchronicle.test|200|/assets/css/prairie.css |$KC_T" "$F29B" && grep -qxF "kitchenerchronicle.test|200|$KC_C|$KC_T" "$F29"'
ok "so after the restore the pointer names the held release's record, NEVER this roll's own" 'has "$P29B" "PP_UPGRADE_BASELINE=<file>" && has_line "$P29B" "The record of prairiepost-$LOSSY12-shared, where this group went back, kept before the roll into it changed anything: $F29" && hasnt "$P29B" "$F29B" && hasnt "$OUT29B" "This roll'"'"'s own"'
# THE RECOVERY ROLL: the same roll of the fix, told to hold the domains
# to the record the held roll saved while the Chronicle was healthy.
OUT29C=$(roll "$B29" PP_UPGRADE_BASELINE="$F29" 2>&1); RC29C=$?
show "$(sed -n '/^== Recovery baseline/,/^== Fetch the release/p' <<< "$OUT29C")"
show "$(sed -n '/^== Verify every domain/,$p' <<< "$OUT29C")"
ok "roll 3 (HEAD, PP_UPGRADE_BASELINE = the held roll's record) succeeds" '[ "$RC29C" = 0 ]'
ok "it says which file, and what the file says about itself" 'has "$OUT29C" "== Recovery baseline (PP_UPGRADE_BASELINE): hold domains to $F29" && has "$OUT29C" "   the file says: written "'
ok "it says how old the record is, and that a later roll has kept a record since" 'has "$OUT29C" "   taken at stamp " && has "$OUT29C" "   NOTE: records kept by later rolls on this box: 1 (the newest at stamp " && has "$OUT29C" "If PP_UPGRADE_BASELINE was left set from an earlier recovery, unset it"'
ok "it lists only the domains it will verify: the hub, already complete on the fix, is set aside" 'has_line "$OUT29C" "   not verified by this roll (already complete on $HEAD12): civismedia.test" && hasnt "$OUT29C" "   civismedia.test: held to the RECOVERY"'
ok "it holds the Chronicle to the healthy record, and says what it answers now" 'has_line "$OUT29C" "   kitchenerchronicle.test: held to the RECOVERY baseline: 200 $KC_T [$KC_C]" && has_line "$OUT29C" "      and NOT to what it answers now: 200 $KC_T [/assets/css/prairie.css ]"'
ok "a domain whose record is what it answers now is told so" 'has_line "$OUT29C" "   prairiedispatch.test: held to the RECOVERY baseline: 200 $PD_T [$PD_C] — which is what it answers now" && has_line "$OUT29C" "   dormant.test: held to the RECOVERY baseline: 503 — which is what it answers now"'
ok "the repair now PASSES, and its line says which baseline" 'has_line "$OUT29C" "PASS kitchenerchronicle.test -> $KC_T [recovery baseline; when this roll started it answered 200 $KC_T [/assets/css/prairie.css ]]"'
ok "so do the domains that were healthy all along" 'has_line "$OUT29C" "PASS prairiedispatch.test -> $PD_T [recovery baseline, which is what it answered when this roll started]" && has_line "$OUT29C" "PASS dormant.test -> 503 [recovery baseline, which is what it answered when this roll started]"'
ok "nothing is restored, nothing held: the papers' group is on the fix, marked complete" '[ "$(restore_lines "$OUT29C")" = 0 ] && hasnt "$OUT29C" "NOT restoring" && [ "$(grep -c "^FAIL " <<< "$OUT29C")" = 0 ] && on_new "$B29/vhosts/kitchenerchronicle" && on_new "$B29/vhosts/prairiedispatch" && on_new "$B29/vhosts/dormant" && marked_done "$B29/www/prairiepost-$HEAD12-shared"'
ok "the roll ends by naming the baseline each verified domain was held to" 'has "$OUT29C" "== Which baseline each domain was held to" && has_line "$OUT29C" "  the recovery baseline $F29: dormant.test kitchenerchronicle.test prairiedispatch.test" && has_line "$OUT29C" "  what it answered when this roll started: none"'
ok "the new release's baseline says which domains it holds to the named file" 'grep -qxF "# kitchenerchronicle.test: held by this roll to PP_UPGRADE_BASELINE ($F29), not to what it answered when the roll started" "$B29/www/prairiepost-$HEAD12-shared/.pp-roll-baseline" && grep -qxF "kitchenerchronicle.test|200|$KC_C|$KC_T" "$B29/www/prairiepost-$HEAD12-shared/.pp-roll-baseline"'
ok "the release it left serves the stored version, so the repoint is offered as before" 'has "$OUT29C" "Old release directories stay on disk. Rollback for any release group:" && hasnt "$OUT29C" "NOT a rollback"'
front_get kitchenerchronicle.test
ok "the Chronicle is served with its stylesheet again, from the fix" '[ "$HTTP_CODE" = 200 ] && [ "$HTTP_TITLE" = "$KC_T" ] && [ "$HTTP_CSS" = "$KC_C" ] && [ "$HTTP_FROM" = "prairiepost-$HEAD12-shared" ]'
ok "cron runs from the fix"                   '[ -f "$B29/cron/pp-fetch" ] && [ ! -f "$B29/cron/pp-fetch.paused" ] && on_new "$B29/cron/pp-fetch"'
front_stop

echo
echo "== scenario 30: the vhost files copied back to the old release after a rise — every domain 503 — then a roll"
B30=$(mktemp -d)
build_http_box "$B30"
front_start "$B30"
OUT30=$(roll "$B30" 2>&1); RC30=$?
ok "the roll that raised the version succeeded" '[ "$RC30" = 0 ] && has "$OUT30" "ROSE from 21" && [ "$(grep -c "^PASS " <<< "$OUT30")" = 4 ]'
S30="$B30/www/prairiepost-$HEAD12-shared"
F30=$(record_in "$S30")
ok "and kept a record of the baseline it captured, from a healthy box" '[ -f "$F30" ] && grep -qxF "kitchenerchronicle.test|200|$KC_C|$KC_T" "$F30" && grep -qxF "dormant.test|503||" "$F30"'
# What the closing text says not to do: every vhost's .bak copied back.
for f in "$B30"/vhosts/*.bak.*; do cp "$f" "${f%.bak.*}"; done
ok "the vhost files name the old release again" 'on_old "$B30/vhosts/kitchenerchronicle" && on_old "$B30/vhosts/prairiedispatch" && on_old "$B30/vhosts/dormant" && on_old "$B30/vhosts/civismedia"'
ok "all four domains answer the readiness gate's 503" '[ "$(gates prairiedispatch.test kitchenerchronicle.test civismedia.test dormant.test)" = 4 ]'
# THE GAP (b). The release the papers need is on the box, and a roll to
# it is the obvious repair. This too is a roll WITHOUT a recovery
# baseline, and it must go on behaving exactly like this: a domain taken
# down on purpose answers the same 503, and no roll may bring it back
# unasked.
OUT30B=$(roll "$B30" 2>&1); RC30B=$?
show "$(sed -n '/^== Capture every domain/,/^== Fetch the release/p' <<< "$OUT30B")"
show "$(sed -n '/^== Verify every domain/,$p' <<< "$OUT30B")"
ok "GAP the roll holds each domain to the 503 it answered" 'has "$OUT30B" "   kitchenerchronicle.test answered 503 with no title — it will be held to 503 afterwards" && has "$OUT30B" "   civismedia.test answered 503 with no title — it will be held to 503 afterwards"'
ok "GAP 200 afterwards reads as a failure"    'has_line "$OUT30B" "FAIL kitchenerchronicle.test answered 200, served 503 before" && has_line "$OUT30B" "FAIL prairiedispatch.test answered 200, served 503 before" && has_line "$OUT30B" "FAIL civismedia.test answered 200, served 503 before"'
ok "GAP the version did not rise, so both groups are put back on the release that cannot serve" 'has "$OUT30B" "stored schema version after the step: $HEADV (unchanged)" && [ "$(restore_lines "$OUT30B")" = 2 ] && on_old "$B30/vhosts/kitchenerchronicle" && on_old "$B30/vhosts/prairiedispatch" && on_old "$B30/vhosts/civismedia"'
ok "GAP the roll ends exit 0 with a WARN"     '[ "$RC30B" = 0 ] && has "$OUT30B" "WARN: at least one release group was restored"'
ok "GAP the box still answers 503 on all four domains" '[ "$(gates prairiedispatch.test kitchenerchronicle.test civismedia.test dormant.test)" = 4 ]'
N30=$(sed -n 's/^         The record in which the most of these (the newest, if several tie) answered 200 with a title (3 of 4): //p' <<< "$OUT30B" | head -n 1)
ok "but it SAYS so before changing anything: the gate's page, by domain, and a record to name" 'has "$OUT30B" "NOTE: the 503 from these is the readiness gate'"'"'s own page (\"This site is being updated\"): civismedia.test dormant.test kitchenerchronicle.test prairiedispatch.test" && [ "$(line_of "$OUT30B" "NOTE: the 503 from these")" -lt "$(line_of "$OUT30B" "== Fetch the release template once")" ] && [ -f "$N30" ] && grep -qxF "kitchenerchronicle.test|200|$KC_C|$KC_T" "$N30"'
ok "the roll replaced the release's .pp-roll-baseline (now the 503s); the record of the healthy one is still there" 'grep -qxF "kitchenerchronicle.test|503||" "$S30/.pp-roll-baseline" && [ -f "$F30" ] && grep -qxF "kitchenerchronicle.test|200|$KC_C|$KC_T" "$F30"'
ok "each restore says the release it goes back to cannot serve" '[ "$(grep -c "is not built for the stored schema version (its own runner says ahead: stored version $HEADV, built for 21). Back on it, every domain of this group answers the readiness gate" <<< "$OUT30B")" = 2 ]'
P30B=$(sed -n '/^WARN: at least one release group was restored/,/^== /p' <<< "$OUT30B")
ok "the release the groups went back to has no record, so the pointer names none (and never this roll's own)" 'has "$P30B" "PP_UPGRADE_BASELINE=<file>" && hasnt "$P30B" "The record of" && hasnt "$OUT30B" "This roll'"'"'s own"'
ok "and the closing text no longer offers those .bak files as a rollback" 'has "$OUT30B" "these are NOT a rollback" && hasnt "$OUT30B" "Rollback for any release group" && has "$OUT30B" "prairiepost-oldsha11-shared — its own runner says ahead: stored version $HEADV, built for 21"'
# A RECOVERY ROLL IS NOT A BYPASS. Told to hold the domains to the healthy
# record, a roll onto the release that lacks the Chronicle's template
# tree still fails the Chronicle — against the recovery baseline now.
OUT30C=$(roll "$B30" PP_UPGRADE_BASELINE="$F30" PP_UPGRADE_SHA="$LOSSY_SHA" PP_RELEASE_TARBALL="$LOSSY_TARBALL" 2>&1); RC30C=$?
show "$(sed -n '/^== Recovery baseline/,/^== Fetch the release/p' <<< "$OUT30C")"
show "$(sed -n '/^== Verify every domain/,$p' <<< "$OUT30C")"
ok "held to the recovery baseline, the gate NOTE has nobody left to name" 'has_line "$OUT30C" "   kitchenerchronicle.test: held to the RECOVERY baseline: 200 $KC_T [$KC_C]" && has_line "$OUT30C" "      and NOT to what it answers now: 503" && hasnt "$OUT30C" "NOTE: the 503 from these"'
ok "the release without the template FAILS the Chronicle against the recovery baseline" 'has_line "$OUT30C" "FAIL kitchenerchronicle.test stylesheet set is not the recovery baseline'"'"'s — baseline [$KC_C] now [/assets/css/prairie.css ] [recovery baseline; when this roll started it answered 503]"'
ok "the Dispatch, same group, passes its record" 'has_line "$OUT30C" "PASS prairiedispatch.test -> $PD_T [recovery baseline; when this roll started it answered 503]"'
ok "the papers' group goes back where it came from, as on any roll that did not raise the version" '[ "$(restore_lines "$OUT30C")" = 1 ] && on_old "$B30/vhosts/kitchenerchronicle" && on_old "$B30/vhosts/prairiedispatch" && [ "$(gates prairiedispatch.test kitchenerchronicle.test)" = 2 ] && [ "$RC30C" = 0 ] && has "$OUT30C" "WARN: at least one release group was restored" && has_line "$OUT30C" "This roll was held to: $F30"'
front_get civismedia.test
ok "the hub's group passed its record and is serving again" 'has_line "$OUT30C" "PASS civismedia.test -> $HUB_T [recovery baseline; when this roll started it answered 503]" && [ "$HTTP_CODE" = 200 ] && [ "$HTTP_TITLE" = "$HUB_T" ] && [ "$HTTP_FROM" = "prairiepost-$LOSSY12-civismedia" ]'
# THE RECOVERY ROLL. The same record, onto HEAD.
OUT30D=$(roll "$B30" PP_UPGRADE_BASELINE="$F30" 2>&1); RC30D=$?
show "$(sed -n '/^== Recovery baseline/,/^== Fetch the release/p' <<< "$OUT30D")"
show "$(sed -n '/^== Verify every domain/,$p' <<< "$OUT30D")"
ok "the recovery roll onto HEAD succeeds"     '[ "$RC30D" = 0 ] && [ "$(restore_lines "$OUT30D")" = 0 ] && [ "$(grep -c "^FAIL " <<< "$OUT30D")" = 0 ]'
ok "the papers PASS against the healthy record: 200 where they answered 503" 'has_line "$OUT30D" "PASS kitchenerchronicle.test -> $KC_T [recovery baseline; when this roll started it answered 503]" && has_line "$OUT30D" "PASS prairiedispatch.test -> $PD_T [recovery baseline; when this roll started it answered 503]"'
ok "the tenant with no site row is held to the 503 in its record, not swept to 200" 'has_line "$OUT30D" "   dormant.test: held to the RECOVERY baseline: 503 — which is what it answers now" && has_line "$OUT30D" "PASS dormant.test -> 503 [recovery baseline, which is what it answered when this roll started]"'
ok "every vhost is on the release built for the stored version, and both groups are marked" 'on_new "$B30/vhosts/kitchenerchronicle" && on_new "$B30/vhosts/prairiedispatch" && on_new "$B30/vhosts/dormant" && on_new "$B30/vhosts/civismedia" && marked_done "$S30" && marked_done "$B30/www/prairiepost-$HEAD12-civismedia"'
front_get kitchenerchronicle.test; K30="$HTTP_CODE|$HTTP_TITLE|$HTTP_CSS|$HTTP_FROM"
front_get prairiedispatch.test;    P30="$HTTP_CODE|$HTTP_TITLE|$HTTP_CSS|$HTTP_FROM"
front_get civismedia.test;         H30="$HTTP_CODE|$HTTP_TITLE|$HTTP_CSS|$HTTP_FROM"
ok "the box serves again: each paper its own masthead and stylesheets" '[ "$K30" = "200|$KC_T|$KC_C|prairiepost-$HEAD12-shared" ] && [ "$P30" = "200|$PD_T|$PD_C|prairiepost-$HEAD12-shared" ] && [ "$H30" = "200|$HUB_T|$HUB_C|prairiepost-$HEAD12-civismedia" ]'
front_get dormant.test
ok "and the unprovisioned tenant its own 503, no longer the gate's" '[ "$HTTP_CODE" = 503 ] && grep -q "This site is not provisioned here" "$HTTP_BODY" && ! grep -q "This site is being updated" "$HTTP_BODY"'
ok "the closing text tells the two old releases apart: one is a rollback, one is 503" 'has "$OUT30D" "these are NOT a rollback" && has "$OUT30D" "prairiepost-oldsha11-shared — its own runner says ahead: stored version $HEADV, built for 21" && hasnt "$OUT30D" "prairiepost-$LOSSY12-civismedia — its own runner" && has_line "$OUT30D" "For the other release groups:" && has "$OUT30D" "Old release directories stay on disk. Rollback for any release group:"'
front_stop

echo
echo "== scenario 31: held after a rise, the failing domain taken down by hand, then rolled back up"
B31=$(mktemp -d)
build_http_box "$B31"
front_start "$B31"
OUT31=$(roll "$B31" PP_UPGRADE_SHA="$LOSSY_SHA" PP_RELEASE_TARBALL="$LOSSY_TARBALL" 2>&1); RC31=$?
F31=$(record_in "$B31/www/prairiepost-$LOSSY12-shared")
ok "roll 1 holds the papers' group, as in scenario 29" '[ "$RC31" != 0 ] && has "$OUT31" "NOT restoring release group" && has_line "$OUT31" "      PP_UPGRADE_BASELINE=$F31"'
# "Take that one domain down" (docs/MIGRATION-COMPAT.md): the roll's own
# backup of that single vhost copied back, the file the roll wrote kept
# under a name the script ignores.
V31="$B31/vhosts/kitchenerchronicle"
BAK31=$(ls "$V31".bak.* | head -1)
cp "$V31" "$BAK31.new" && cp "$BAK31" "$V31"
front_get prairiedispatch.test
ok "the Chronicle alone answers the gate's 503; the Dispatch is untouched" '[ "$(gates kitchenerchronicle.test)" = 1 ] && on_old "$V31" && [ "$HTTP_CODE" = 200 ] && [ "$HTTP_FROM" = "prairiepost-$LOSSY12-shared" ]'
# A roll WITHOUT a recovery baseline leaves a domain that was taken down
# exactly where the operator put it.
OUT31B=$(roll "$B31" 2>&1); RC31B=$?
show "$(sed -n '/^== Capture every domain/,/^== Fetch the release/p' <<< "$OUT31B")"
show "$(sed -n '/^== Verify every domain/,$p' <<< "$OUT31B")"
ok "an ordinary roll to HEAD moves the other papers and the hub" 'on_new "$B31/vhosts/prairiedispatch" && on_new "$B31/vhosts/dormant" && on_new "$B31/vhosts/civismedia" && has_line "$OUT31B" "PASS prairiedispatch.test -> $PD_T"'
ok "and leaves the Chronicle down: a group of one, put back on its 503" 'has_line "$OUT31B" "FAIL kitchenerchronicle.test answered 200, served 503 before" && [ "$(restore_lines "$OUT31B")" = 1 ] && on_old "$V31" && [ "$(gates kitchenerchronicle.test)" = 1 ]'
N31=$(sed -n 's/^         The record in which the most of these (the newest, if several tie) answered 200 with a title (1 of 1): //p' <<< "$OUT31B" | head -n 1)
ok "it names the domain, and a record in which it was healthy, before changing anything" 'has "$OUT31B" "(\"This site is being updated\"): kitchenerchronicle.test" && [ -f "$N31" ] && grep -qxF "kitchenerchronicle.test|200|$KC_C|$KC_T" "$N31"'
ok "the papers' group, which passed, keeps its cron file, on the new release" '[ -f "$B31/cron/pp-fetch" ] && [ ! -f "$B31/cron/pp-fetch.paused" ] && on_new "$B31/cron/pp-fetch"'
# A recovery baseline that cannot be used stops the roll before it starts.
R31=$(records "$B31")
OUT31X=$(roll "$B31" PP_UPGRADE_BASELINE="$B31/no-such-baseline.txt" 2>&1); RC31X=$?
printf 'kitchenerchronicle.test|200|/assets/css/kitchener.css\n' > "$B31/short.txt"
OUT31Y=$(roll "$B31" PP_UPGRADE_BASELINE="$B31/short.txt" 2>&1); RC31Y=$?
ok "a recovery baseline that is missing or malformed stops the roll before it starts" '[ "$RC31X" != 0 ] && has "$OUT31X" "names a file that cannot be read" && hasnt "$OUT31X" "== Resolve the branch head" && [ "$RC31Y" != 0 ] && has "$OUT31Y" "short.txt), line 1: not" && has "$OUT31Y" "nothing changed" && hasnt "$OUT31Y" "== Resolve the branch head"'
ok "and nothing changed: no vhost backup, no baseline record, the Chronicle still down" 'on_old "$V31" && [ "$(ls "$V31".bak.* | wc -l)" = 3 ] && [ "$(records "$B31")" = "$R31" ] && [ "$(gates kitchenerchronicle.test)" = 1 ]'
# THE RECOVERY ROLL brings the one domain back, verified as itself. The
# baseline here is written BY HAND and names only the Chronicle (its
# stylesheets out of order, as a person would type them): every other
# domain is held to what it answers when the roll starts.
H31="$B31/chronicle-only.txt"
printf '# by hand: what the Chronicle should answer\n%s\n' "kitchenerchronicle.test|200|/assets/css/prairie.css /assets/css/kitchener.css|$KC_T" > "$H31"
OUT31C=$(roll "$B31" PP_UPGRADE_BASELINE="$H31" 2>&1); RC31C=$?
show "$(sed -n '/^== Recovery baseline/,/^== Fetch the release/p' <<< "$OUT31C")"
show "$(sed -n '/^== Verify every domain/,$p' <<< "$OUT31C")"
ok "the recovery roll succeeds, and only the Chronicle's group rolls" '[ "$RC31C" = 0 ] && [ "$(restore_lines "$OUT31C")" = 0 ] && [ "$(grep -c "^PASS " <<< "$OUT31C")" = 1 ] && [ "$(grep -c "already on $HEAD12" <<< "$OUT31C")" = 2 ]'
ok "the domain the file names is held to it; the groups already on the fix are set aside, not verified" 'has_line "$OUT31C" "   kitchenerchronicle.test: held to the RECOVERY baseline: 200 $KC_T [$KC_C]" && has_line "$OUT31C" "   not verified by this roll (already complete on $HEAD12): civismedia.test dormant.test prairiedispatch.test" && has_line "$OUT31C" "  the recovery baseline $H31: kitchenerchronicle.test" && has_line "$OUT31C" "  what it answered when this roll started: none"'
ok "a hand-written file has no stamp, and the roll says its age is unknown" 'has_line "$OUT31C" "   the file carries no stamp (written by hand?): its age is unknown"'
ok "the Chronicle PASSES as itself: its own masthead and stylesheet set" 'has_line "$OUT31C" "PASS kitchenerchronicle.test -> $KC_T [recovery baseline; when this roll started it answered 503]"'
front_get kitchenerchronicle.test
ok "it is back up, from a release directory of its own" '[ "$HTTP_CODE" = 200 ] && [ "$HTTP_TITLE" = "$KC_T" ] && [ "$HTTP_CSS" = "$KC_C" ] && [ "$HTTP_FROM" = "prairiepost-$HEAD12-kitchenerchronicle" ]'
front_stop

echo
echo "== scenario 32: PRECEDENCE — finishing a stopped roll whose saved baseline was taken while a paper was failing"
B32=$(mktemp -d)
build_http_box "$B32"
front_start "$B32"
OUT32=$(roll "$B32" PP_UPGRADE_SHA="$LOSSY_SHA" PP_RELEASE_TARBALL="$LOSSY_TARBALL" 2>&1); RC32=$?
F32=$(record_in "$B32/www/prairiepost-$LOSSY12-shared")
ok "roll 1 (no template tree) holds the papers' group; its record has the Chronicle healthy" '[ "$RC32" != 0 ] && [ -f "$B32/www/prairiepost-$LOSSY12-shared/.pp-roll-held" ] && grep -qxF "kitchenerchronicle.test|200|$KC_C|$KC_T" "$F32"'
# Roll 2 is the fix, stopped by a signal after the reload: a STOPPED roll
# whose saved baseline was taken while the Chronicle served without its
# stylesheet.
OUT32B=$(roll "$B32" PP_UPGRADE_FIXTURE_DIE=after-reload 2>&1); RC32B=$?
S32="$B32/www/prairiepost-$HEAD12-shared"
ok "roll 2 stops after the reload; the baseline it saved is the failing answer" '[ "$RC32B" = 143 ] && on_new "$B32/vhosts/kitchenerchronicle" && ! marked_done "$S32" && [ ! -f "$S32/.pp-roll-held" ] && grep -qxF "kitchenerchronicle.test|200|/assets/css/prairie.css |$KC_T" "$S32/.pp-roll-baseline"'
front_get kitchenerchronicle.test
ok "while the Chronicle is in fact served right, from the fix" '[ "$HTTP_CODE" = 200 ] && [ "$HTTP_CSS" = "$KC_C" ] && [ "$HTTP_FROM" = "prairiepost-$HEAD12-shared" ]'
# Finished as it stands, the roll is judged by what roll 2 saved.
OUT32C=$(roll "$B32" 2>&1); RC32C=$?
show "$(sed -n '/^== Verify every domain/,$p' <<< "$OUT32C")"
ok "finishing as it stands holds the Chronicle to that failure: the repair FAILS" 'has "$OUT32C" "no run finished that roll" && has "$OUT32C" "held to the baseline the run that started this roll saved" && has_line "$OUT32C" "FAIL kitchenerchronicle.test stylesheet set changed — was [/assets/css/prairie.css ] now [$KC_C]"'
ok "and the group is HELD (a finishing run puts nothing back), marked HELD" '[ "$RC32C" != 0 ] && has "$OUT32C" "NOT restoring release group" && [ -f "$S32/.pp-roll-held" ] && ! marked_done "$S32" && on_new "$B32/vhosts/kitchenerchronicle"'
ok "its output names the baseline each domain was held to: the one the first run saved" 'has "$OUT32C" "== Which baseline each domain was held to" && has "$OUT32C" "  the baseline the run that started this roll saved (" && has_line "$OUT32C" "  what it answered when this roll started: none"'
# Finished with the held roll's record named, the named file wins.
OUT32D=$(roll "$B32" PP_UPGRADE_BASELINE="$F32" 2>&1); RC32D=$?
show "$(sed -n '/^== Recovery baseline/,/^== Fetch the release/p' <<< "$OUT32D")"
show "$(sed -n '/^== Verify every domain/,$p' <<< "$OUT32D")"
ok "the named file wins: the Chronicle is held to it, NOT to the baseline the first run saved" 'has "$OUT32D" "no run finished that roll" && has_line "$OUT32D" "   kitchenerchronicle.test: held to the RECOVERY baseline: 200 $KC_T [$KC_C]" && has_line "$OUT32D" "      and NOT to the baseline the run that started this roll saved: 200 $KC_T [/assets/css/prairie.css ]"'
ok "where the two agree, the output says so" 'has_line "$OUT32D" "   prairiedispatch.test: held to the RECOVERY baseline: 200 $PD_T [$PD_C] — which is also the baseline the run that started this roll saved"'
ok "the finishing step keeps the named records, and says so" 'has_line "$OUT32D" "   prairiepost-$HEAD12-shared: held to PP_UPGRADE_BASELINE, NOT to the baseline the run that started this roll saved, because the named file records them: dormant.test kitchenerchronicle.test prairiedispatch.test" && hasnt "$OUT32D" "(0 domains,"'
ok "the repair PASSES; the roll completes and clears the HELD mark" '[ "$RC32D" = 0 ] && [ "$(grep -c "^FAIL " <<< "$OUT32D")" = 0 ] && has_line "$OUT32D" "PASS kitchenerchronicle.test -> $KC_T [recovery baseline, which is what it answered when this roll started]" && marked_done "$S32" && [ ! -f "$S32/.pp-roll-held" ]'
ok "and it names the baseline each domain was held to" 'has_line "$OUT32D" "  the recovery baseline $F32: dormant.test kitchenerchronicle.test prairiedispatch.test" && has_line "$OUT32D" "  what it answered when this roll started: none"'
front_stop

echo
echo "== scenario 33: PRECEDENCE — replacing a stopped roll under PP_UPGRADE_ABANDON, with a recovery baseline named"
B33=$(mktemp -d)
build_http_box "$B33"
front_start "$B33"
OUT33=$(roll "$B33" PP_UPGRADE_SHA="$LOSSY_SHA" PP_RELEASE_TARBALL="$LOSSY_TARBALL" 2>&1); RC33=$?
F33=$(record_in "$B33/www/prairiepost-$LOSSY12-shared")
OUT33B=$(roll "$B33" PP_UPGRADE_FIXTURE_DIE=after-reload 2>&1); RC33B=$?
ok "a held roll, then a roll stopped after the reload with the failing answer saved" '[ "$RC33" != 0 ] && [ "$RC33B" = 143 ] && grep -qxF "kitchenerchronicle.test|200|$KC_C|$KC_T" "$F33" && grep -qxF "kitchenerchronicle.test|200|/assets/css/prairie.css |$KC_T" "$B33/www/prairiepost-$HEAD12-shared/.pp-roll-baseline"'
# The named file records ONE domain: the Chronicle's line from the held
# roll's record. Every other domain is held as it would be without it.
H33="$B33/chronicle-only.txt"
{ echo "# the Chronicle's line, from the held roll's record"; grep '^kitchenerchronicle.test|' "$F33"; } > "$H33"
OUT33C=$(roll "$B33" PP_UPGRADE_SHA="$FIXSHA" PP_RELEASE_TARBALL="$FIXTGZ" PP_UPGRADE_ABANDON="$HEADSHA" PP_UPGRADE_BASELINE="$H33" 2>&1); RC33C=$?
show "$(sed -n '/^== Capture every domain/,/^== Fetch the release/p' <<< "$OUT33C")"
show "$(sed -n '/^== Verify every domain/,$p' <<< "$OUT33C")"
ok "the stopped roll is abandoned; for the domain it records, the named file wins over its saved baseline" 'has "$OUT33C" "ABANDONED by PP_UPGRADE_ABANDON" && has_line "$OUT33C" "   kitchenerchronicle.test: held to the RECOVERY baseline: 200 $KC_T [$KC_C]" && has_line "$OUT33C" "      and NOT to the baseline the abandoned roll saved: 200 $KC_T [/assets/css/prairie.css ]"'
ok "every domain it does not record is held to the abandoned roll's baseline" 'has_line "$OUT33C" "   civismedia.test: NOT in that file — held to the baseline the abandoned roll saved: 200 $HUB_T [$HUB_C]" && has_line "$OUT33C" "   prairiedispatch.test: NOT in that file — held to the baseline the abandoned roll saved: 200 $PD_T [$PD_C]" && has_line "$OUT33C" "   dormant.test: NOT in that file — held to the baseline the abandoned roll saved: 503"'
ok "the fix passes every domain, and both groups complete" '[ "$RC33C" = 0 ] && [ "$(grep -c "^FAIL " <<< "$OUT33C")" = 0 ] && has_line "$OUT33C" "PASS kitchenerchronicle.test -> $KC_T [recovery baseline, which is what it answered when this roll started]" && on_fix "$B33/vhosts/kitchenerchronicle" && marked_done "$B33/www/prairiepost-$FIX12-shared" && marked_done "$B33/www/prairiepost-$FIX12-civismedia"'
ok "it names the baseline each domain was held to, domain by domain" 'has_line "$OUT33C" "  the recovery baseline $H33: kitchenerchronicle.test" && has "$OUT33C" "  the baseline the abandoned roll saved (" && has "$OUT33C" "): civismedia.test dormant.test prairiedispatch.test" && has_line "$OUT33C" "  what it answered when this roll started: none"'
ok "and the fix's own baseline says which is which, in its header" 'grep -qxF "# kitchenerchronicle.test: held by this roll to PP_UPGRADE_BASELINE ($H33), not to what it answered when the roll started" "$B33/www/prairiepost-$FIX12-shared/.pp-roll-baseline" && grep -qxF "# prairiedispatch.test: held by this roll to the baseline of the abandoned roll ($B33/www/prairiepost-$HEAD12-shared/.pp-roll-baseline), not to what it answered when the roll started" "$B33/www/prairiepost-$FIX12-shared/.pp-roll-baseline" && grep -qxF "kitchenerchronicle.test|200|$KC_C|$KC_T" "$B33/www/prairiepost-$FIX12-shared/.pp-roll-baseline"'
front_stop

for B in "${BOXES[@]}"; do
  git worktree remove --force "$B/www/prairiepost-oldsha11-shared" 2>/dev/null
  rm -rf "$B"
done
git worktree prune 2>/dev/null
rm -rf "$SEEDS"
rm -f "$TARBALL" "$HTTP_BODY" "$HTTP_HEAD" "$FIXTGZ" "$LOSSY_TARBALL"

echo
if [ "$FAILS" = "1" ]; then
  echo "RELEASE FLOW BROKEN."
  exit 1
fi
echo "All release-flow scenarios correct."
