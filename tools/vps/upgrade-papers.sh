#!/usr/bin/env bash
#
# Upgrade every paper — and the hub — on the network box to the branch's
# current release. The hub is a site row in the shared database running the
# same code, never a fork, so it rolls forward with the papers.
#
# v2 — rewritten after preflight caught a tenant-freezing bug in v1. The
# papers run on host-mapped config.php files whose site_slug resolves per
# request; this version NEVER evaluates a production config. The original
# file is copied VERBATIM into the new release as app/config.site.php
# (dynamic host mapping and all), and a small wrapper config.php
# requires it and adds hub_slug only when absent — at request time, after
# the host mapping has run. Release directories that serve several vhosts
# are upgraded once and all their vhosts repointed together.
#
# Verification is tenant-aware: every domain's front-page <title> is
# captured BEFORE any change, and after the reload each domain must serve
# 200 with the SAME title — a paper answering with another paper's
# masthead fails and its whole release group (vhosts AND cron files)
# rolls back together.
#
# That rollback exists only while the stored schema version is the one
# the OLD release was built for. Every release since Phase 2 refuses to
# serve any other version, so once the migration step has RAISED it, a
# group pointed back at its old directory answers 503 on every domain.
# The script reads the stored version before and after the step; when it
# rose, nothing is put back — a failure is held on the new release and
# reported, and the closing text offers no repoint. The rules, and what
# recovery is then, are in docs/MIGRATION-COMPAT.md.
#
# One roll at a time (a lock), and a stopped roll is FINISHED by running
# the script again at the same release. Each roll leaves three small
# files in the release directory it builds: .pp-roll-baseline (what every
# domain answered before the roll changed anything), .pp-roll-before (the
# stored schema version it found, and which run found it) and, once nginx
# has reloaded onto the release and its domains passed, .pp-roll-complete.
# A run that finds the vhosts naming a release with the first file and
# not the last finishes that roll: migrate if pending, test, reload, and
# verify every domain against the SAVED baseline, never against the 503
# it may be answering now; such a run puts nothing back and offers no
# rollback. A roll that ran to the end and HELD a group leaves
# .pp-roll-held instead of .pp-roll-complete: a run at that commit
# verifies the group again, and a roll of another commit (the fix) is
# allowed. Only a roll that was STOPPED — a baseline and neither mark —
# blocks another commit, unless PP_UPGRADE_ABANDON names it. Output also
# goes to a log file on the box; HUP and PIPE are ignored by the script
# and its logger (a dropped connection must not stop a roll or its log),
# INT and TERM stop it — except between the migration step and the
# reload, where nothing does.
#
# "Serve afterwards what it served before" cannot mend a domain that was
# already failing when the roll started: its repair is a change, and
# reads as a failure. So every roll also keeps a RECORD of its saved
# baseline, .pp-roll-baseline.<stamp> beside .pp-roll-baseline, which no
# later roll removes, and a roll can be told to hold domains to one of
# those records instead of to what they answer when it starts:
#     PP_UPGRADE_BASELINE=/var/www/prairiepost-<sha>-<label>/.pp-roll-baseline.<stamp>
# Nothing reads a record unless that variable names it — a roll without
# it behaves exactly as before — and the output says, for every domain,
# which baseline it was held to. A domain the named file records is held
# to that record even in a roll this run finishes or one it replaces
# under PP_UPGRADE_ABANDON: the precedence is set out where the file is
# applied, and in docs/MIGRATION-COMPAT.md, "The recovery baseline".
#
# Also creates the network's shared /uploads directory, merged from every
# release and symlinked in, so campaign creatives and syndicated images
# resolve on every domain.
#
# Run as root:  curl -fsSL <raw url> | bash
#
set -uo pipefail

# Fixture overrides (tools/test-release-flow.sh exercises this script
# against a synthetic box; production values are the defaults):
EN_DIR="${PP_UPGRADE_VHOSTS_DIR:-/etc/nginx/sites-enabled}"
CRON_DIR="${PP_UPGRADE_CRON_DIR:-/etc/cron.d}"
WWW="${PP_UPGRADE_WWW:-/var/www}"
FIXTURE="${PP_UPGRADE_FIXTURE:-0}"
# A fixture has no nginx, so its configuration test is skipped. It can be
# made to FAIL instead — PP_UPGRADE_FIXTURE_NGINX_FAIL=pre (the test
# before the migration) or =post (the one after it) — and is read only
# when PP_UPGRADE_FIXTURE=1.
FIXTURE_NGINX_FAIL=""
[ "$FIXTURE" = "1" ] && FIXTURE_NGINX_FAIL="${PP_UPGRADE_FIXTURE_NGINX_FAIL:-}"
# A fixture has no TLS stack either, so by default it takes no baseline
# and verifies with a CLI boot of each new release. Given
# PP_UPGRADE_FIXTURE_HTTP_PORT — a loopback port where the fixture answers
# plain HTTP for every domain, from whichever release directory that
# domain's vhost file names at that moment — it runs the real thing
# instead: the baseline capture and the per-domain comparison below, with
# only the probe's transport changed. Read only when PP_UPGRADE_FIXTURE=1.
FIXTURE_HTTP_PORT=""
[ "$FIXTURE" = "1" ] && FIXTURE_HTTP_PORT="${PP_UPGRADE_FIXTURE_HTTP_PORT:-}"
case "$FIXTURE_HTTP_PORT" in *[!0-9]*) echo "FATAL: PP_UPGRADE_FIXTURE_HTTP_PORT is not a port number" >&2; exit 1 ;; esac
PROBES=1   # 0 only in a fixture with no HTTP stand-in: no domain can be probed
[ "$FIXTURE" = "1" ] && [ -z "$FIXTURE_HTTP_PORT" ] && PROBES=0
# Two more fixture-only knobs, read the same way. PP_UPGRADE_FIXTURE_STATUS_FAIL=N
# makes the first N reads of the stored version AFTER the migration step
# return nothing (a runner that cannot answer). PP_UPGRADE_FIXTURE_DIE=<point>
# sends this run SIGTERM at that point (after-pause, after-reload), to
# exercise the interruption handling; <point>:GROUP-HUP or <point>:GROUP-INT
# sends that signal to the run's whole process group instead, as a
# terminal hangup or a Ctrl-C does (the run must lead its own group:
# start it under setsid).
STATUS_FAILS_LEFT=0
[ "$FIXTURE" = "1" ] && STATUS_FAILS_LEFT="${PP_UPGRADE_FIXTURE_STATUS_FAIL:-0}"
fixture_die() { # point -> a fixture may ask for a signal here
  [ "$FIXTURE" = "1" ] || return 0
  local want="${PP_UPGRADE_FIXTURE_DIE:-}" pg sig
  case "$want" in
    "$1")
      echo "   (fixture: SIGTERM to this run at '$1')"
      kill -TERM $$
      sleep 2 ;;   # the trap runs when this command ends
    "$1:GROUP-HUP"|"$1:GROUP-INT")
      sig="${want#*:GROUP-}"
      pg=$(ps -o pgid= -p $$ | tr -d ' ')
      # Never signal a group this run does not lead: it would be the
      # caller's own, the fixture's or CI's.
      [ "$pg" = "$$" ] || fail "fixture: PP_UPGRADE_FIXTURE_DIE=$want needs this run to lead its own process group — start it under setsid"
      echo "   (fixture: SIG$sig to this run's whole process group at '$1', as a terminal sends it)"
      kill -"$sig" -- "-$pg"
      sleep 2 ;;
  esac
}

REPO="FTFNAnalytics/prairiepost"
# The branch whose head becomes the new release. Override with PP_BRANCH to
# pin a deploy to a specific line — e.g. to add one paper on top of exactly
# what production already runs, without also shipping unreleased work from
# the control-room branch's head. The default is unchanged.
BRANCH="${PP_BRANCH:-claude/master-dashboard-control-room-nr3mp4}"
SHARED_UP="$WWW/prairiepost-shared-uploads"
STAMP=$(date +%s)

PHASE="starting"
say()  { PHASE="$*"; echo; echo "== $*"; }
fail() { echo "FATAL: $*" >&2; exit 1; }

# --- Two edits to live server blocks (PLAN.md 9.2 and 9.10) ---------------
# Both are idempotent by marker, insert only beside the block's PHP handler
# (`location ~ \.php$`), so certbot's port-80 redirect servers are never
# touched, and use the php-fpm socket the block already passes PHP to.
# They run inside the vhost loop below, so the roll's nginx test, its
# masthead and stylesheet guard and its rollback all cover them.

# The php-fpm socket a block passes PHP to ('' when it has none).
pp_block_socket() { grep -oE 'fastcgi_pass[[:space:]]+unix:[^;[:space:]]+' "$1" | head -1 | sed -E 's/.*unix://'; }

# Insert lines (from stdin) before each PHP handler in a block, in place.
pp_before_php_handler() { # $1 vhost file
  local add; add=$(cat)
  awk -v add="$add" '
    /^[[:space:]]*location[[:space:]]+~[[:space:]]+\\\.php\$[[:space:]]*\{/ {
      match($0, /^[[:space:]]*/); ind = substr($0, 1, RLENGTH)
      n = split(add, lines, "\n")
      for (i = 1; i <= n; i++) print ind lines[i]
    }
    { print }' "$1" > "$1.pp-tmp" && cat "$1.pp-tmp" > "$1"; rm -f "$1.pp-tmp"
}

# A hand-written block routes only the paths written into it, so every
# route added since (/about, /tips, the /api lanes) needed a hand pass over
# each one, and /about never had one. Give it the generated blocks' front
# controller: its `location /` fallback becomes router.php, behind the
# same internal-only exact location. Its rewrite lines stay and go on
# serving the routes they already serve. -> 0 when the file changed.
pp_front_controller() { # $1 vhost file
  local sock
  grep -q 'pp-front-controller' "$1" && return 1
  grep -q 'location = /router.php' "$1" && return 1
  grep -qE 'try_files[[:space:]]+\$uri[[:space:]]+\$uri/[[:space:]]+=404;' "$1" || return 1
  sock=$(pp_block_socket "$1"); [ -n "$sock" ] || return 1
  pp_before_php_handler "$1" <<EOF
# pp-front-controller: paths no rewrite names go to router.php, as in generated blocks.
location = /router.php {
    internal;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    fastcgi_pass unix:$sock;
}
EOF
  sed -i -E 's#try_files([[:space:]]+)\$uri([[:space:]]+)\$uri/([[:space:]]+)=404;#try_files\1$uri\2$uri/\3/router.php$is_args$args;#' "$1"
  return 0
}

# The tips form is never logged: an access log would pair each sender's IP
# with the time of a tip the database stores without one. The location
# passes straight to router.php, with no internal redirect, so its
# `access_log off` is the one that applies. -> 0 when the file changed.
pp_tips_unlogged() { # $1 vhost file
  local sock
  grep -q 'pp-tips-unlogged' "$1" && return 1
  sock=$(pp_block_socket "$1"); [ -n "$sock" ] || return 1
  grep -qE '^[[:space:]]*location[[:space:]]+~[[:space:]]+\\\.php\$' "$1" || return 1
  pp_before_php_handler "$1" <<EOF
# pp-tips-unlogged: the tips form is never in the access log (PLAN.md 9.10).
location ~ ^/tips/?\$ {
    access_log off;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME \$document_root/router.php;
    fastcgi_pass unix:$sock;
}
EOF
  return 0
}

if [ "$FIXTURE" != "1" ]; then
  [ "$(id -u)" = "0" ] || fail "run as root"
  command -v nginx >/dev/null || fail "nginx not found"
fi

# One roll at a time. Two runs at once would each take .bak copies of the
# other's rewrites and pause each other's cron files.
LOCK="${PP_UPGRADE_LOCK:-/run/lock/prairiepost-upgrade.lock}"
command -v flock >/dev/null || fail "flock (util-linux) is not installed — the roll needs it to run one at a time"
exec 9>"$LOCK" || fail "cannot open the lock file $LOCK"
flock -n 9 || fail "another upgrade-papers.sh run holds $LOCK — one roll at a time (fuser $LOCK names it)"

# Everything this run prints also goes to a log file on the box, and the
# run does not stop when the terminal goes: the script runs as
# `curl | bash` over SSH, where a dropped connection is a HUP to the whole
# foreground process group (with a terminal) or a PIPE at the next line
# printed (without one). Both are ignored — BEFORE the logger starts, so
# it inherits that — and the logger also ignores INT and TERM: a Ctrl-C
# is a deliberate stop of the roll, and the handler's report of what it
# put back has to reach the log. tee -p keeps writing the file when the
# terminal or pipe it copies to is gone. INT and TERM are handled below.
trap '' HUP PIPE
LOG_DIR="${PP_UPGRADE_LOG_DIR:-/var/log/prairiepost-upgrade}"
LOG=""
if mkdir -p "$LOG_DIR" 2>/dev/null && touch "$LOG_DIR/roll-$STAMP.log" 2>/dev/null; then
  LOG="$LOG_DIR/roll-$STAMP.log"
  exec > >(trap '' INT TERM HUP PIPE; exec tee -i -p -a "$LOG") 2>&1
  echo "log: $LOG"
else
  echo "WARNING: no log file ($LOG_DIR is not writable); output goes to this terminal only" >&2
fi

# State the interruption handler reports.
declare -a PAUSED=()           # cron files this run renamed to .paused
declare -a ALL_VHOST_BACKUPS=()
declare -a ALL_CRON_BACKUPS=()
declare -A SCHEMA_DIR=()       # identity -> the release directory its version is read through
declare -A SCHEMA_NOW=()       # identity -> what this run read before its own migration step
MIGRATION_STARTED=0            # set just before the first --apply
MIGRATION_RAN=0                # set when the migration loop is through
RELOADED=0                     # set the moment systemctl reload nginx succeeds
FINISHING=0                    # set when any group is a roll an earlier run started
declare -A ABANDONED=()        # OLD dir -> 1 when it is the release of a stopped roll PP_UPGRADE_ABANDON names
declare -A FIRST_STAMP=()      # OLD dir -> the stamp of the run that started the roll this run finishes
declare -A HOLD_REASON=()      # OLD dir -> why a held group was not put back

# Temporary files go on every exit, not only at the end of a full run.
TMP=""
PROBE_BODY=""
cleanup_tmp() {
  [ -n "$TMP" ] && [ -d "$TMP" ] && rm -rf -- "$TMP"
  [ -n "$PROBE_BODY" ] && rm -f -- "$PROBE_BODY"
  return 0
}
trap cleanup_tmp EXIT
PROBE_BODY=$(mktemp)
front_page() { # domain, curl options... -> the probe itself: one GET of that domain's front page
  local domain="$1"; shift
  if [ -n "$FIXTURE_HTTP_PORT" ]; then
    # Fixture stand-in: the same request in clear, at the fixture's port.
    curl -s -m 15 "$@" --resolve "$domain:$FIXTURE_HTTP_PORT:127.0.0.1" "http://$domain:$FIXTURE_HTTP_PORT/"
    return
  fi
  curl -sk -m 15 "$@" --resolve "$domain:443:127.0.0.1" "https://$domain/"
}

front_title() { # domain -> prints "<code>|<title>"
  local code title
  code=$(front_page "$1" -o "$PROBE_BODY" -w "%{http_code}")
  title=$(grep -o "<title>[^<]*</title>" "$PROBE_BODY" | head -1)
  printf '%s|%s' "$code" "$title"
}

front_css() { # domain -> prints the sorted set of /assets/css/*.css the front page links
  front_page "$1" \
    | grep -o '/assets/css/[a-z0-9._-]*\.css' | sort -u | tr '\n' ' '
}

# ---------------------------------------------------------------------
# Is the old release still a fallback? One fact decides it: whether the
# migration step RAISES the stored schema version. A release since
# Phase 2 serves only the version it was built for (db() in
# app/bootstrap.php), so after a rise the old directories cannot serve
# this database and every "put it back" below would be an outage, not a
# rollback. The stored version is therefore read before the step and
# again after it — by the candidate's own runner, whose --status never
# writes — and kept per logical schema:
#   same     both readings agree: the old release is a real fallback and
#            everything below behaves as it always has;
#   rose     the step raised it: nothing is put back on the old release;
#   unknown  a reading was missing: treated as a rise wherever the new
#            release is known to be ready, because "it did not change"
#            is then a guess.
# ---------------------------------------------------------------------
declare -A DIR_IDENT=()      # OLD dir  -> logical schema identity
declare -A SCHEMA_STATE=()   # identity -> what --status called the schema before the step (ready, behind, ...)
declare -A SCHEMA_BEFORE=()  # identity -> stored version before the step ("" = not readable)
declare -A SCHEMA_AFTER=()   # identity -> stored version after it
declare -A SCHEMA_CHANGE=()  # identity -> same | rose | unknown
declare -A SCHEMA_LABEL=()   # identity -> the release directory it was read through
declare -a HELD_GROUPS=()    # OLD dirs that failed verification and were NOT put back
NOT_A_FALLBACK="The old release was built for the earlier version. A release since Phase 2 refuses every schema version but its own, so each paper pointed back at it answers 503 and its cron jobs exit before any write"

db_identity() { # release dir -> prints driver+endpoint+database+schema, or nothing
  (cd "$1" && php -r '$c = require "config.php"; $d = $c["db"] ?? []; $x = $d["driver"] ?? "sqlite";
    if ($x === "pgsql") { $p = $d["pgsql"]; echo "pgsql|", $p["host"] ?? "", "|", $p["port"] ?? 5432, "|", $p["name"] ?? "", "|", $p["schema"] ?? "prairiedispatch"; }
    elseif ($x === "mysql") { $m = $d["mysql"]; echo "mysql|", $m["socket"] ?? ($m["host"] ?? ""), "|", $m["name"] ?? ""; }
    else { echo "sqlite|", $d["sqlite_path"] ?? ""; }' 2>/dev/null)
}
schema_stored() { # release dir -> prints "<state> <stored version> <built-for version>", or nothing
  (cd "$1" && php tools/migrate.php --status 2>/dev/null) \
    | sed -n 's/^state:[[:space:]]*\([a-z]*\) (stored version \([0-9a-z]*\), target \([0-9]*\)).*/\1 \2 \3/p'
}
schema_stored_after() { # release dir [pre] -> sets STATUS_OUT like schema_stored prints, retried: a runner that cannot answer once is not a changed schema
  local attempt
  STATUS_OUT=""
  for attempt in 1 2 3; do
    if [ "${2:-}" != "pre" ] && [ "$STATUS_FAILS_LEFT" -gt 0 ]; then   # the fixture knob fails post-step reads only
      STATUS_FAILS_LEFT=$((STATUS_FAILS_LEFT - 1))
      echo "   (fixture: post-step --status read made to fail)"
    else
      STATUS_OUT="$(schema_stored "$1")"
    fi
    [ -n "$STATUS_OUT" ] && return
    [ "$attempt" = 3 ] || sleep 1
  done
}
schema_after() { # identity, release dir, exit code of --apply -> records the stored version now and what happened to it
  local id="$1" rc="${3:-1}" state version target before
  schema_stored_after "$2"
  read -r state version target <<< "$STATUS_OUT"
  before="${SCHEMA_BEFORE[$id]:-}"
  SCHEMA_AFTER[$id]="${version:-}"
  case "${version:-}" in
    ''|*[!0-9]*)
      # Nothing was pending, the step exited clean, and the version was
      # numeric going in: a no-op cannot have moved it. Only then is a
      # missing reading "unchanged"; otherwise it is unknown.
      if [ "${SCHEMA_STATE[$id]:-}" = "ready" ] && [ "$rc" = 0 ] && [ -n "$before" ] && [ "$before" -eq "$before" ] 2>/dev/null; then
        SCHEMA_AFTER[$id]="$before"
        SCHEMA_CHANGE[$id]="same"
        echo "   stored schema version not read back after the step; taken as unchanged ($before) because nothing was pending and the step exited 0"
      else
        SCHEMA_CHANGE[$id]="unknown"
      fi
      return ;;
  esac
  case "$before" in
    none)        SCHEMA_CHANGE[$id]="rose"; return ;;   # there was no schema; now there is one
    ''|*[!0-9]*) SCHEMA_CHANGE[$id]="unknown"; return ;;
  esac
  if   [ "$version" -gt "$before" ]; then SCHEMA_CHANGE[$id]="rose"
  elif [ "$version" -eq "$before" ]; then SCHEMA_CHANGE[$id]="same"
  else SCHEMA_CHANGE[$id]="unknown"
  fi
}
schema_note() { # identity -> one clause: what happened to its stored version
  local id="$1"
  case "${SCHEMA_CHANGE[$id]:-}" in
    rose)    echo "the stored schema version ROSE from ${SCHEMA_BEFORE[$id]:-none} to ${SCHEMA_AFTER[$id]:-?} in this roll" ;;
    unknown) echo "the stored schema version could not be confirmed unchanged (before: ${SCHEMA_BEFORE[$id]:-not readable}, after: ${SCHEMA_AFTER[$id]:-not readable}) and is treated as risen" ;;
    *)       echo "the stored schema version is unchanged at ${SCHEMA_AFTER[$id]:-?}" ;;
  esac
}
schema_moved_lines() { # one line per schema this roll moved (rose, or unknown)
  local id
  for id in "${!SCHEMA_CHANGE[@]}"; do
    [ "${SCHEMA_CHANGE[$id]}" = "same" ] && continue
    echo "  $(schema_note "$id") (the schema behind ${SCHEMA_LABEL[$id]:-?})."
  done
}
schema_moved() { # true when any schema rose, or may have
  local id
  for id in "${!SCHEMA_CHANGE[@]}"; do
    [ "${SCHEMA_CHANGE[$id]}" = "same" ] || return 0
  done
  return 1
}
any_schema_moved() { # true when this run's .bak copies are no way back: a schema moved, or the run finished or replaced an earlier roll
  schema_moved && return 0
  [ "$FINISHING" = 1 ] && return 0
  [ "${#ABANDONED[@]}" -gt 0 ] && return 0
  return 1
}
not_a_fallback_line() { # the reason the old release is no fallback — said only when a schema did move
  if schema_moved; then echo "  $NOT_A_FALLBACK."; fi
  return 0
}
any_schema_rose() { # true when a rise was actually read
  local id
  for id in "${!SCHEMA_CHANGE[@]}"; do
    [ "${SCHEMA_CHANGE[$id]}" = "rose" ] && return 0
  done
  return 1
}
group_change() { # OLD dir -> same | rose | unknown | finishing | abandoned, for the schema that group serves from
  local id="${DIR_IDENT[$1]:-}" c
  c="same"
  [ -n "$id" ] && c="${SCHEMA_CHANGE[$id]:-same}"
  if [ "$c" = "same" ] && [ -n "${UNFINISHED[$1]:-}" ]; then c="finishing"; fi
  if [ "$c" = "same" ] && [ -n "${ABANDONED[$1]:-}" ]; then c="abandoned"; fi
  echo "$c"
}
first_stamps() { # the stamps of the runs whose rolls this run finished
  local o
  for o in "${!FIRST_STAMP[@]}"; do echo "${FIRST_STAMP[$o]}"; done | sort -u | tr '\n' ' ' | sed 's/ $//'
}
finishing_lines() { # what is true of a run that finishes a roll an earlier run started, or replaces an abandoned one
  local o
  if [ "$FINISHING" = 1 ]; then
    echo "  This run finished a roll an earlier run started${FIRST_STAMP[*]:+ (stamp $(first_stamps))}: the vhost and cron files already"
    echo "  named the new release when it began, so its own .bak.$STAMP copies are of those files and put nothing back."
    if schema_moved; then
      echo "  The earlier run's copies name the release before it, which cannot serve a schema that has moved."
    else
      echo "  Neither run raised the schema, so the earlier run's copies, <vhost>.bak.<that stamp> and"
      echo "  <cronfile>.bak.<that stamp>, are a true way back for a group of it: copy them over each of the"
      echo "  group's vhost and cron files, then nginx -t && systemctl reload nginx."
    fi
  fi
  if [ "${#ABANDONED[@]}" -gt 0 ]; then
    echo "  This run replaced the release of a roll abandoned with PP_UPGRADE_ABANDON ($(for o in "${!ABANDONED[@]}"; do basename "$o"; done | sort | tr '\n' ' ' | sed 's/ $//')). No run"
    echo "  verified that release, so this run's .bak.$STAMP copies, which name it, are no way back either."
  fi
  return 0
}
nginx_test() { # the configuration test; a fixture reaches this only to fail it
  if [ "$FIXTURE" = "1" ]; then
    echo "   (fixture: nginx test forced to fail)"
    return 1
  fi
  nginx -t
}
on_signal() { # INT or TERM mid-roll: undo what can be undone, say where things stand, stop
  local sig="$1" cf id
  trap '' INT TERM HUP PIPE   # a second signal must not stop this handler half-way
  {
    echo
    echo "INTERRUPTED by SIG$sig during: $PHASE"
    if [ "$MIGRATION_STARTED" = 0 ] && [ "$FINISHING" = 0 ] && [ "${#ALL_VHOST_BACKUPS[@]}" -gt 0 ]; then
      # Before the migration the .bak copies are true pre-run files and
      # nginx still runs what it ran: put them back, so no reload from
      # elsewhere can load the new release against the old schema.
      for VH in "${ALL_VHOST_BACKUPS[@]}"; do cp "$VH.bak.$STAMP" "$VH"; done
      for cf in "${PAUSED[@]}"; do rm -f "$cf.paused"; done
      for cf in "${ALL_CRON_BACKUPS[@]}"; do cp "$cf.bak.$STAMP" "$cf"; done
      echo "   every vhost and cron file restored to what it named before this run; the schema was not touched; nginx was not reloaded: nothing changed"
      echo "   Run this script again when ready; it starts over."
    else
      for cf in "${PAUSED[@]}"; do
        [ -f "$cf.paused" ] && mv "$cf.paused" "$cf" && echo "   resumed: $(basename "$cf")"
      done
      if [ "${#ALL_VHOST_BACKUPS[@]}" -gt 0 ]; then
        echo "   vhost and cron files: name the new release (this run's .bak.$STAMP copies are beside them)"
      else
        echo "   vhost and cron files: untouched by this run"
      fi
      if [ "$MIGRATION_STARTED" = 1 ] && [ "${#SCHEMA_CHANGE[@]}" -eq 0 ]; then
        # Stopped with the step in flight and its result unrecorded: ask.
        for id in "${!SCHEMA_DIR[@]}"; do
          schema_after "$id" "${SCHEMA_DIR[$id]}" 1
        done
      fi
      if any_schema_moved; then
        schema_moved_lines
        finishing_lines
        not_a_fallback_line
        if schema_moved && [ "$RELOADED" != 1 ]; then echo "   So until a run finishes, every paper is answering 503."; fi
      elif [ "$MIGRATION_RAN" = 1 ]; then
        echo "   schema: the migration step ran and the stored version did not change"
      elif [ "$MIGRATION_STARTED" = 1 ]; then
        echo "   schema: the migration step was in flight; its result could not be read — the version may have risen, and the papers may be down until a run finishes"
      else
        echo "   schema: the migration step had not run"
      fi
      if [ "$RELOADED" = 1 ]; then
        echo "   nginx: reloaded onto the new release (verification may not have run)"
      else
        echo "   nginx: NOT reloaded — still running what it ran before this roll"
      fi
      echo "Run this script again at the same release. A release the vhosts already name but no run finished is"
      echo "finished, not skipped: migrate if pending, test, reload, verify against the baseline that run saved."
    fi
  } >&2
  case "$sig" in INT) exit 130 ;; *) exit 143 ;; esac
}
arm_traps() { trap 'on_signal INT' INT; trap 'on_signal TERM' TERM; }
arm_traps

# A recovery baseline an operator names (PP_UPGRADE_BASELINE): read and
# checked HERE, before the roll resolves, probes or writes anything, so
# that "nothing changed" is true of every refusal below.
RECOVERY_FILE="${PP_UPGRADE_BASELINE:-}"
declare -A REC_CODE=()    # the named baseline's records: domain -> status code,
declare -A REC_TITLE=()   #   -> <title> (kept only for a 200),
declare -A REC_CSS=()     #   -> stylesheet set (likewise)
REC_SAYS=()               # the file's own opening comment lines, printed where it is applied
BAD="PP_UPGRADE_BASELINE ($RECOVERY_FILE)"
if [ -n "$RECOVERY_FILE" ]; then
  [ "$PROBES" = "1" ] || fail "PP_UPGRADE_BASELINE is set but this fixture has no HTTP stand-in to probe — nothing changed"
  [ -f "$RECOVERY_FILE" ] && [ -r "$RECOVERY_FILE" ] || fail "PP_UPGRADE_BASELINE names a file that cannot be read: $RECOVERY_FILE — nothing changed"
  lineno=0
  while IFS= read -r line || [ -n "$line" ]; do
    lineno=$((lineno + 1))
    line="${line%$'\r'}"
    case "$line" in
      '#'*) [ "$lineno" -le 2 ] && REC_SAYS+=("${line#\# }")
            continue ;;
      '')   continue ;;
      *'|'*'|'*'|'*) : ;;
      *)    fail "$BAD, line $lineno: not 'domain|status code|stylesheet set|<title>' — nothing changed" ;;
    esac
    IFS='|' read -r r_domain r_code r_css r_title <<< "$line"
    case "$r_domain" in ''|*[!A-Za-z0-9.-]*) fail "$BAD, line $lineno: '$r_domain' is not a domain — nothing changed" ;; esac
    case "$r_code" in [0-9][0-9][0-9]) : ;; *) fail "$BAD, line $lineno ($r_domain): status code '$r_code' is not three digits — nothing changed" ;; esac
    case "$r_css" in *[!a-z0-9._/\ -]*) fail "$BAD, line $lineno ($r_domain): the stylesheet set is not a list of /assets/css/ paths — nothing changed" ;; esac
    case "$r_title" in ''|'<title>'*'</title>') : ;; *) fail "$BAD, line $lineno ($r_domain): the title is neither empty nor a whole <title> element — nothing changed" ;; esac
    [ -z "${REC_CODE[$r_domain]:-}" ] || fail "$BAD, line $lineno: $r_domain is named twice — nothing changed"
    # As the capture keeps them: a title and a stylesheet set only for a
    # 200, the set sorted the way front_css sorts it.
    if [ "$r_code" != "200" ]; then r_css=""; r_title=""; fi
    [ -n "$r_css" ] && r_css="$(printf '%s\n' $r_css | sort -u | tr '\n' ' ')"
    REC_CODE[$r_domain]="$r_code"
    REC_TITLE[$r_domain]="$r_title"
    REC_CSS[$r_domain]="$r_css"
  done < "$RECOVERY_FILE"
  [ "${#REC_CODE[@]}" -gt 0 ] || fail "$BAD holds no record — nothing changed"
fi

say "Resolve the branch head"
PIN=""
if [ -n "${PP_UPGRADE_SHA:-}" ]; then
  # Pinned (fixtures, or a pre-verified deploy): the full SHA from the
  # brief. It names the release directory AND the tarball fetched below.
  PIN="$PP_UPGRADE_SHA"
  case "$PIN" in
    *[!0-9a-f]*|?????????????????????????????????????????*) fail "PP_UPGRADE_SHA must be a lowercase hex commit id, 12 to 40 characters (the full 40 from the brief): got '$PIN'" ;;
  esac
  [ "${#PIN}" -ge 12 ] || fail "PP_UPGRADE_SHA must be a lowercase hex commit id, 12 to 40 characters (the full 40 from the brief): got '$PIN'"
  SHA="${PIN:0:12}"
else
  SHA=$(curl -fsSL "https://api.github.com/repos/$REPO/branches/${BRANCH//\//%2F}" \
        | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo substr($d["commit"]["sha"]??"",0,12);')
fi
[ -n "$SHA" ] || fail "couldn't resolve the branch head"
# A roll that was stopped and cannot be finished at its own commit (its
# migration needs a code change) blocks every other commit — see the
# mapping step. PP_UPGRADE_ABANDON=<that roll's commit id> lets this roll
# replace it. Nothing else reads it.
ABANDON="${PP_UPGRADE_ABANDON:-}"
if [ -n "$ABANDON" ]; then
  case "$ABANDON" in
    *[!0-9a-f]*|?????????????????????????????????????????*) fail "PP_UPGRADE_ABANDON must be the lowercase hex commit id of the stopped roll, 12 to 40 characters: got '$ABANDON' — nothing changed" ;;
  esac
  [ "${#ABANDON}" -ge 12 ] || fail "PP_UPGRADE_ABANDON must be the lowercase hex commit id of the stopped roll, 12 to 40 characters: got '$ABANDON' — nothing changed"
  [ "${ABANDON:0:12}" != "$SHA" ] || fail "PP_UPGRADE_ABANDON names the commit this run is rolling ($SHA): a stopped roll at this commit is finished, not abandoned — run again without it. Nothing changed"
fi
if [ -n "$PIN" ] && [ -n "${PP_RELEASE_TARBALL:-}" ]; then
  echo "release: $SHA (pinned by PP_UPGRADE_SHA; the pre-staged tarball is checked against it once extracted)"
elif [ -n "$PIN" ]; then
  echo "release: $SHA (pinned by PP_UPGRADE_SHA; the tarball is fetched for this commit, not for the branch head)"
else
  echo "release: $SHA"
fi

say "Map the papers behind nginx (a release dir may serve several vhosts)"
declare -A DIR_VHOSTS=()   # OLD dir -> "vhostbase vhostbase..."
declare -A DIR_DOMAINS=()  # OLD dir -> "domain domain..."
for link in "$EN_DIR"/*; do
  base=$(basename "$link")
  # cies is the Institute — a different application. The hub IS a paper's
  # release (one site row, same code), so it rolls forward with them.
  # .bak.* and .paused files are this script's own artifacts from earlier
  # runs — never vhosts.
  case "$base" in cies|README|default|*.bak.*|*.paused) continue ;; esac
  VH=$(readlink -f "$link")
  [ -f "$VH" ] || continue
  OLD=$(grep -Eo "root[[:space:]]+$WWW/prairiepost-[A-Za-z0-9._-]+" "$VH" | head -1 | awk '{print $2}')
  [ -n "$OLD" ] && [ -d "$OLD" ] || { echo "-- $base: no prairiepost root, skipped"; continue; }
  [ -f "$OLD/config.php" ] || { echo "-- $base: no config.php at $OLD, skipped"; continue; }
  if [ -f "$OLD/.pp-roll-baseline" ] && [ ! -f "$OLD/.pp-roll-complete" ] && [ ! -f "$OLD/.pp-roll-held" ]; then
    # A roll started into this directory and was STOPPED: no run finished
    # it, and none ended by holding it. Only a run at that same commit can
    # finish it; anything else would roll over a box nobody verified.
    # (A HELD release — a roll that ran to the end and kept a failing
    # group on the new release — is a release like any other here: a roll
    # of the fix replaces it.)
    case "$OLD" in
      "$WWW/prairiepost-$SHA-"*) ;;
      *) if [ -n "$ABANDON" ] && [ "${OLD#"$WWW/prairiepost-${ABANDON:0:12}-"}" != "$OLD" ]; then
           ABANDONED[$OLD]=1
         else
           [ -n "$ABANDON" ] && echo "   PP_UPGRADE_ABANDON names ${ABANDON:0:12}, which is not the commit of $OLD" >&2
           fail "$base names $OLD, a release a roll started into and no run finished — nothing changed. Finish that roll first: run this script again pinned to its commit (PP_UPGRADE_SHA, the one in the directory name), and only then roll to $SHA. If that roll cannot be finished at its own commit (its migration needs a code change), name it to be abandoned: PP_UPGRADE_ABANDON=<its full commit id> — docs/MIGRATION-COMPAT.md, \"A stopped roll that cannot be finished\""
         fi ;;
    esac
  fi
  DOMAIN=$(grep -Eo 'server_name[[:space:]]+[^;]+' "$VH" | head -1 | awk '{print $2}')
  [ -n "$DOMAIN" ] || { echo "-- $base: no server_name, skipped"; continue; }
  DIR_VHOSTS[$OLD]="${DIR_VHOSTS[$OLD]:-} $base"
  DIR_DOMAINS[$OLD]="${DIR_DOMAINS[$OLD]:-} $DOMAIN"
  echo "-- $base ($DOMAIN) serves from $OLD"
done
[ "${#DIR_VHOSTS[@]}" -gt 0 ] || fail "no papers found"
if [ -n "$ABANDON" ] && [ "${#ABANDONED[@]}" -eq 0 ]; then
  fail "PP_UPGRADE_ABANDON names ${ABANDON:0:12}, but no vhost names a stopped roll of that commit (a release with a saved baseline and neither a completion nor a held mark) — nothing changed. Unset it"
fi
for OLD in "${!ABANDONED[@]}"; do
  echo "   $(basename "$OLD"): the release of a stopped roll, ABANDONED by PP_UPGRADE_ABANDON — this roll replaces it, holds its domains to the baseline that roll saved, and never puts them back on it"
done

# ---------------------------------------------------------------------
# Which baseline each domain is held to. Without PP_UPGRADE_BASELINE:
#   - a domain of a roll this run FINISHES: the baseline the run that
#     started that roll saved (.pp-roll-baseline in its release);
#   - a domain of a group replacing an ABANDONED roll: the baseline the
#     abandoned roll saved;
#   - any other domain: what it answers as this roll starts.
# With it, the named file WINS, domain by domain: a domain it records is
# held to that record in all three cases; a domain it does not record is
# held exactly as above. The rule is the operator's explicit word over
# the script's own default. A saved baseline is a capture, and a capture
# taken while a domain was already failing holds it to the failure: a
# roll stopped after starting from such a box could never be verified
# healthy at its own commit if its own baseline won.
# ---------------------------------------------------------------------
declare -A DOMAIN_OLD=()   # domain -> the release directory its vhost names
declare -A BASE_SRC=()     # domain -> recovery | finishing | abandoned (unset: its own answer at the start)
declare -A BASE_FILE=()    # domain -> the file behind BASE_SRC
declare -A RB_TAG=()       # domain -> the words its PASS/FAIL line carries when it is held to the named file
declare -A RECORD=()       # OLD dir -> the record of the baseline this run saved into its new release
for OLD in "${!DIR_DOMAINS[@]}"; do
  for D in ${DIR_DOMAINS[$OLD]}; do DOMAIN_OLD[$D]="$OLD"; done
done
all_domains() { # every domain behind a prairiepost vhost, one per line, sorted
  printf '%s\n' "${!DOMAIN_OLD[@]}" | LC_ALL=C sort
}
sorted_words() { # words... -> " a b c", sorted (nothing for none)
  [ "$#" -gt 0 ] || return 0
  printf ' %s' $(printf '%s\n' "$@" | LC_ALL=C sort)
}
answer_words() { # status code, title, stylesheet set -> that answer on one line
  if [ "$1" = "200" ] && [ -n "$2" ]; then
    printf '200 %s [%s]' "$2" "$3"
  else
    printf '%s' "${1:-no answer}"
  fi
}
will_finish() { # OLD dir -> true when this run will finish a roll into it (the test the upgrade loop makes)
  case "$1" in "$WWW/prairiepost-$SHA-"*) [ ! -f "$1/.pp-roll-complete" ] ;; *) return 1 ;; esac
}
will_skip() { # OLD dir -> true when its group is already complete on this release, so this run verifies none of its domains
  case "$1" in "$WWW/prairiepost-$SHA-"*) [ -f "$1/.pp-roll-complete" ] ;; *) return 1 ;; esac
}
age_words() { # seconds -> how long ago, in words
  local s="$1"
  if   [ "$s" -lt 0 ];     then echo "$((-s)) seconds in the future"
  elif [ "$s" -ge 86400 ]; then echo "$((s / 86400)) days $(((s % 86400) / 3600)) hours ago"
  elif [ "$s" -ge 3600 ];  then echo "$((s / 3600)) hours $(((s % 3600) / 60)) minutes ago"
  elif [ "$s" -ge 60 ];    then echo "$((s / 60)) minutes ago"
  else echo "$s seconds ago"
  fi
}
saved_record() { # baseline file, domain -> sets SR_CODE SR_CSS SR_TITLE from that file's line for the domain
  local d c css t
  SR_CODE=""; SR_CSS=""; SR_TITLE=""
  [ -f "$1" ] || return 1
  while IFS='|' read -r d c css t; do
    [ "$d" = "$2" ] || continue
    SR_CODE="$c"; SR_CSS="$css"; SR_TITLE="$t"
    return 0
  done < "$1"
  return 1
}
record_of() { # release dir -> prints the RECORD of the baseline saved in it (or that baseline itself), or nothing
  local s
  [ -f "$1/.pp-roll-baseline" ] || return 0
  s=$(sed -n 's/^# written .*(stamp \([0-9][0-9]*\)).*/\1/p' "$1/.pp-roll-baseline" | head -1)
  if [ -n "$s" ] && [ -f "$1/.pp-roll-baseline.$s" ]; then echo "$1/.pp-roll-baseline.$s"; else echo "$1/.pp-roll-baseline"; fi
}
suggest_baseline() { # domains... -> names the newest saved record in which any of them answered 200 with a title
  local f s n d best="" cands=()
  mapfile -t cands < <(for f in "$WWW"/prairiepost-*/.pp-roll-baseline.*; do
      [ -f "$f" ] || continue
      s="${f##*.pp-roll-baseline.}"; s="${s%%-*}"
      case "$s" in ''|*[!0-9]*) continue ;; esac
      printf '%s %s\n' "$s" "$f"
    done | sort -rn -k1,1)
  local best_n=0
  for f in "${cands[@]}"; do   # newest first, so a tie keeps the newer record
    f="${f#* }"
    n=0
    for d in "$@"; do
      grep -q "^$d|200|[^|]*|<title>" "$f" 2>/dev/null && n=$((n + 1))
    done
    if [ "$n" -gt "$best_n" ]; then best="$f"; best_n="$n"; fi
    [ "$best_n" -lt "$#" ] || break
  done
  if [ -n "$best" ]; then
    echo "         The record in which the most of these (the newest, if several tie) answered 200 with a title ($best_n of $#): $best"
  else
    echo "         None of them records a 200 with a title for these domains."
  fi
}
if [ -n "$RECOVERY_FILE" ]; then
  # Still before anything is probed or written: a file that names none of
  # this box's domains is the wrong file.
  named=0
  for D in $(all_domains); do
    [ -n "${REC_CODE[$D]:-}" ] && named=$((named + 1))
  done
  [ "$named" -gt 0 ] || fail "$BAD names none of this box's domains — nothing changed"
fi

say "Capture every domain's title BEFORE any change (the tenant baseline)"
declare -A TITLE_BEFORE=()
declare -A CSS_BEFORE=()
declare -A CODE_BEFORE=()
declare -A GATE_BEFORE=()   # domain -> 1 when its answer was the readiness gate's own 503 page
if [ "$PROBES" = "0" ]; then
  echo "   (fixture: no TLS stack — the post-migration CLI smoke stands in)"
elif [ "$FIXTURE" = "1" ]; then
  echo "   (fixture: probing plain HTTP on 127.0.0.1:$FIXTURE_HTTP_PORT in place of TLS on 443)"
fi
for OLD in "${!DIR_DOMAINS[@]}"; do
  [ "$PROBES" = "0" ] && break
  for DOMAIN in ${DIR_DOMAINS[$OLD]}; do
    IFS='|' read -r code title <<< "$(front_title "$DOMAIN")"
    CODE_BEFORE[$DOMAIN]="$code"
    if [ "$code" = "200" ] && [ -n "$title" ]; then
      TITLE_BEFORE[$DOMAIN]="$title"
      CSS_BEFORE[$DOMAIN]="$(front_css "$DOMAIN")"
      echo "   $DOMAIN -> $title [${CSS_BEFORE[$DOMAIN]}]"
    else
      TITLE_BEFORE[$DOMAIN]=""
      # The rule is "serve afterwards what it served before", not "serve
      # 200": the hub's front page is not a paper's front page, and a
      # domain that redirected before must still redirect, not suddenly
      # answer 200. curl is not following redirects here on purpose.
      echo "   $DOMAIN answered $code with no title — it will be held to $code afterwards"
      # The readiness gate's own page (pp_db_unavailable in
      # app/bootstrap.php, the "being updated" reason) is only NOTED: the
      # domain is held to its 503 like any other answer. A tenant with no
      # site row gets the same page with other words and is not marked.
      if [ "$code" = "503" ] && grep -q "This site is being updated" "$PROBE_BODY" 2>/dev/null; then
        GATE_BEFORE[$DOMAIN]=1
      fi
    fi
  done
done
# What each domain answered as this roll started, in words, before any
# saved baseline is laid over the capture (the output quotes it).
declare -A LIVE_WORDS=()
for DOMAIN in "${!CODE_BEFORE[@]}"; do
  LIVE_WORDS[$DOMAIN]="$(answer_words "${CODE_BEFORE[$DOMAIN]:-}" "${TITLE_BEFORE[$DOMAIN]:-}" "${CSS_BEFORE[$DOMAIN]:-}")"
done
# The domains of an abandoned roll are held to what that roll saved
# before it changed anything, not to what they answer now (after a rise,
# the gate's 503).
for OLD in "${!ABANDONED[@]}"; do
  BF="$OLD/.pp-roll-baseline"
  n=0
  while IFS='|' read -r D C CSS T; do
    case "$D" in ''|'#'*) continue ;; esac
    case " ${DIR_DOMAINS[$OLD]} " in *" $D "*) ;; *) continue ;; esac
    CODE_BEFORE[$D]="$C"; CSS_BEFORE[$D]="$CSS"; TITLE_BEFORE[$D]="$T"
    BASE_SRC[$D]="abandoned"; BASE_FILE[$D]="$BF"
    n=$((n + 1))
  done < "$BF"
  echo "   $(basename "$OLD"): its domains are held to the baseline the abandoned roll saved ($n domains, $BF)"
done

# The recovery baseline, if one is named: each domain it records is held
# to that record — over a finishing roll's or an abandoned roll's own
# baseline as well (the precedence above). Only the reference changes;
# what a failing group gets is what it gets on any other roll.
RECOVERY_HELD=""         # the domains held to the named file
RECOVERY_UNLISTED=""     # the domains it does not record
if [ -n "$RECOVERY_FILE" ]; then
  say "Recovery baseline (PP_UPGRADE_BASELINE): hold domains to $RECOVERY_FILE"
  for line in "${REC_SAYS[@]}"; do echo "   the file says: $line"; done
  # How old the record is, and whether rolls have run since: a variable
  # left exported after a recovery holds every later roll to this file.
  REC_STAMP=$(sed -n 's/^# written .*(stamp \([0-9][0-9]*\)).*/\1/p' "$RECOVERY_FILE" | head -1)
  if [ -n "$REC_STAMP" ]; then
    echo "   taken at stamp $REC_STAMP ($(date -u -d "@$REC_STAMP" +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || echo '?'), $(age_words $((STAMP - REC_STAMP))))"
    later=$(for f in "$WWW"/prairiepost-*/.pp-roll-baseline.*; do
        [ -f "$f" ] || continue
        st="${f##*.pp-roll-baseline.}"; st="${st%%-*}"
        case "$st" in ''|*[!0-9]*) continue ;; esac
        [ "$st" -gt "$REC_STAMP" ] && echo "$st"
      done | sort -un)
    if [ -n "$later" ]; then
      newest=$(tail -n 1 <<< "$later")
      echo "   NOTE: records kept by later rolls on this box: $(wc -l <<< "$later") (the newest at stamp $newest, $(date -u -d "@$newest" +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || echo '?'))."
      echo "         If PP_UPGRADE_BASELINE was left set from an earlier recovery, unset it: it holds every roll to this file."
    fi
  else
    echo "   the file carries no stamp (written by hand?): its age is unknown"
  fi
  differs=0
  SKIPPED=""   # domains of groups already complete on this release: this run verifies none of them
  for DOMAIN in $(all_domains); do
    old="${DOMAIN_OLD[$DOMAIN]}"
    if will_skip "$old"; then SKIPPED="$SKIPPED $DOMAIN"; continue; fi
    live="${LIVE_WORDS[$DOMAIN]:-no answer}"
    # What the domain would be held to without the named file.
    if [ "${BASE_SRC[$DOMAIN]:-}" = "abandoned" ]; then
      def="$(answer_words "${CODE_BEFORE[$DOMAIN]:-}" "${TITLE_BEFORE[$DOMAIN]:-}" "${CSS_BEFORE[$DOMAIN]:-}")"
      def_what="the baseline the abandoned roll saved"; def_same="which is also the baseline the abandoned roll saved"
    elif will_finish "$old" && saved_record "$old/.pp-roll-baseline" "$DOMAIN"; then
      def="$(answer_words "$SR_CODE" "$SR_TITLE" "$SR_CSS")"
      def_what="the baseline the run that started this roll saved"; def_same="which is also the baseline the run that started this roll saved"
    else
      def="$live"
      def_what="what it answers now"; def_same="which is what it answers now"
    fi
    if [ -z "${REC_CODE[$DOMAIN]:-}" ]; then
      RECOVERY_UNLISTED="$RECOVERY_UNLISTED $DOMAIN"
      echo "   $DOMAIN: NOT in that file — held to $def_what: $def"
      continue
    fi
    rec="$(answer_words "${REC_CODE[$DOMAIN]}" "${REC_TITLE[$DOMAIN]}" "${REC_CSS[$DOMAIN]}")"
    BASE_SRC[$DOMAIN]="recovery"; BASE_FILE[$DOMAIN]="$RECOVERY_FILE"
    RECOVERY_HELD="$RECOVERY_HELD $DOMAIN"
    if [ "$rec" = "$live" ]; then
      RB_TAG[$DOMAIN]=" [recovery baseline, which is what it answered when this roll started]"
    else
      RB_TAG[$DOMAIN]=" [recovery baseline; when this roll started it answered $live]"
    fi
    if [ "$rec" = "$def" ]; then
      echo "   $DOMAIN: held to the RECOVERY baseline: $rec — $def_same"
    else
      differs=$((differs + 1))
      echo "   $DOMAIN: held to the RECOVERY baseline: $rec"
      echo "      and NOT to $def_what: $def"
    fi
    if [ "${REC_CODE[$DOMAIN]}" = "200" ] && [ -z "${REC_TITLE[$DOMAIN]}" ]; then
      echo "      (the record has no title: any 200 passes for this domain, as it does for a domain captured without one)"
    elif [ "${REC_CODE[$DOMAIN]}" = "200" ] && [ -z "${REC_CSS[$DOMAIN]}" ]; then
      echo "      (the record has no stylesheet set: the set is not compared for this domain)"
    fi
    CODE_BEFORE[$DOMAIN]="${REC_CODE[$DOMAIN]}"
    TITLE_BEFORE[$DOMAIN]="${REC_TITLE[$DOMAIN]}"
    CSS_BEFORE[$DOMAIN]="${REC_CSS[$DOMAIN]}"
  done
  for r_domain in "${!REC_CODE[@]}"; do
    [ -n "${DOMAIN_OLD[$r_domain]:-}" ] || echo "   (the file also names $r_domain, which no prairiepost vhost here serves: ignored)"
  done
  [ -z "$SKIPPED" ] || echo "   not verified by this roll (already complete on $SHA):$SKIPPED"
  echo "   Held to the recovery baseline:${RECOVERY_HELD:- none} ($differs of them to something other than they would be held to without it)."
  echo "   Held as they would be without it:${RECOVERY_UNLISTED:- none}."
fi

# Said once, before anything changes: a domain answering the gate's 503
# that nothing else holds (no named file, no finishing or abandoned
# roll's baseline) is held to that 503 — its repair will read as a FAIL.
GATE_HELD=""
for DOMAIN in $(all_domains); do
  [ -n "${GATE_BEFORE[$DOMAIN]:-}" ] || continue
  [ -n "${BASE_SRC[$DOMAIN]:-}" ] && continue
  will_finish "${DOMAIN_OLD[$DOMAIN]}" && continue
  GATE_HELD="$GATE_HELD $DOMAIN"
done
if [ -n "$GATE_HELD" ]; then
  echo "   NOTE: the 503 from these is the readiness gate's own page (\"This site is being updated\"):$GATE_HELD"
  echo "         The release answering for them does not match the stored schema version."
  echo "         This roll holds each of them to that 503, as it holds every domain to what it answered at the start:"
  echo "         200 afterwards will read as a FAIL for them."
  echo "         If 200 is what they should answer, name a baseline saved when they did: PP_UPGRADE_BASELINE=<file>"
  echo "         (docs/MIGRATION-COMPAT.md, \"The recovery baseline\"). Each roll keeps one, .pp-roll-baseline.<stamp>, in the release it built."
  suggest_baseline $GATE_HELD
fi

say "Fetch the release template once"
TMP=$(mktemp -d)
# Three ways in, because the upgrade must not depend on one CDN host:
#   1. PP_RELEASE_TARBALL=/path.tgz — a pre-staged tarball (an operator can
#      download it anywhere github is reachable and scp it to the box);
#   2. the codeload tarball (the normal path);
#   3. a shallow git clone of the branch from github.com — codeload and
#      github.com are different edges, and one has been unreachable from
#      this box while the other answered.
if [ -n "${PP_RELEASE_TARBALL:-}" ]; then
  [ -f "$PP_RELEASE_TARBALL" ] || fail "PP_RELEASE_TARBALL points at nothing"
  cp "$PP_RELEASE_TARBALL" "$TMP/rel.tgz"
  echo "   using pre-staged tarball: $PP_RELEASE_TARBALL"
elif curl -fsSL --connect-timeout 25 "https://codeload.github.com/$REPO/tar.gz/${PIN:-refs/heads/$BRANCH}" -o "$TMP/rel.tgz"; then
  :
else
  # The clone can only follow the branch head. Pinned to another commit,
  # it is checked against the pin below and refused if they differ: a
  # pinned roll that cannot reach codeload needs PP_RELEASE_TARBALL.
  echo "   codeload unreachable — falling back to a shallow git clone"
  git clone --depth 1 --branch "$BRANCH" "https://github.com/$REPO.git" "$TMP/clone" \
    || fail "tarball download failed AND git clone fallback failed"
  # Verify the clone's head is the branch head the API reported.
  CLONE_SHA=$(git -C "$TMP/clone" rev-parse HEAD)
  case "$CLONE_SHA" in
    "$SHA"*|"${SHA:0:12}"*) : ;;
    *) [ "${CLONE_SHA:0:12}" = "${SHA:0:12}" ] || fail "clone head $CLONE_SHA is not the API-reported head $SHA" ;;
  esac
  rm -rf "$TMP/clone/.git"
  mv "$TMP/clone" "$TMP/prairiepost-$BRANCH-clone"
fi
if [ -f "$TMP/rel.tgz" ]; then
  tar -xzf "$TMP/rel.tgz" -C "$TMP" || fail "tarball didn't extract"
fi
TPL=$(find "$TMP" -maxdepth 1 -mindepth 1 -type d | head -1)
[ -f "$TPL/app/bootstrap.php" ] || fail "extracted tree doesn't look like the app"
if [ -n "$PIN" ]; then
  # A tarball for a commit extracts to prairiepost-<that commit>; one for
  # a branch head, or a tree someone staged by hand, does not.
  case "$(basename "$TPL")" in
    *"$SHA"*) echo "   extracted tree: $(basename "$TPL") — it is the pinned commit" ;;
    *) fail "the extracted tree ($(basename "$TPL")) is not the pinned commit $SHA — fetch the tarball for that commit (https://codeload.github.com/$REPO/tar.gz/$PIN) and pass it as PP_RELEASE_TARBALL" ;;
  esac
fi

say "Preflight: candidate provenance, runtime, vendored code, recovery point"
# The candidate must carry its own migration runner and the pinned
# sanitizer — a release without them cannot serve or be prepared.
[ -f "$TPL/tools/migrate.php" ] || fail "candidate has no tools/migrate.php — refusing a release that depends on request-time migration"
[ -f "$TPL/vendor/ezyang/htmlpurifier/library/HTMLPurifier.php" ] || fail "candidate is missing the vendored sanitizer"
# Ask PHP itself, never `php -m | grep -q`: under pipefail, grep -q
# exiting on its match can hand php a SIGPIPE and fail the pipeline for
# an extension that IS loaded — nondeterministically, which burned a
# production roll twice on 24 Sep 2026 (dom on one attempt, curl on the
# retry) before the two-failure stop caught it.
for ext in dom curl mbstring; do
  php -r 'exit(extension_loaded($argv[1]) ? 0 : 1);' "$ext" \
    || fail "PHP extension '$ext' missing on this box (check the FPM pool too)"
done
# A verified recovery point before anything changes: last night's backup
# state must be ok and fresh. PP_UPGRADE_SKIP_BACKUP_CHECK=1 overrides,
# loudly, for a first install that has no backups yet.
BSTATE="${PP_BACKUP_DEST:-/var/backups/civis}/state.json"
if [ "${PP_UPGRADE_SKIP_BACKUP_CHECK:-0}" = "1" ]; then
  echo "   WARNING: recovery-point check SKIPPED by operator flag"
elif [ -f "$BSTATE" ] && php -r '$s = json_decode(file_get_contents($argv[1]), true); exit(($s["ok"] ?? false) === true && (time() - (int)($s["finished_epoch"] ?? 0)) < 26*3600 ? 0 : 1);' "$BSTATE"; then
  echo "   recovery point: $(php -r '$s = json_decode(file_get_contents($argv[1]), true); echo $s["set_id"] ?? "?";' "$BSTATE") (fresh, ok)"
else
  fail "no fresh verified backup set ($BSTATE) — make one first (tools/backup.sh); a release without a recovery point is a bet, not a deploy"
fi

say "Build the shared uploads directory (merging every release's images)"
mkdir -p "$SHARED_UP"
for d in "$WWW"/prairiepost-*/; do
  d=${d%/}
  [ "$d" = "$SHARED_UP" ] && continue
  if [ -d "$d/uploads" ] && [ ! -L "$d/uploads" ]; then
    cp -an "$d/uploads/." "$SHARED_UP/" 2>/dev/null
  fi
done
chown -R www-data:www-data "$SHARED_UP"
chmod 2775 "$SHARED_UP"
echo "shared uploads: $(du -sh "$SHARED_UP" 2>/dev/null | cut -f1) at $SHARED_UP"

say "Upgrade each release directory"
# A roll stopped after its pause pass leaves cron files .paused with no
# active copy. Those come back here, whatever release they name: an
# earlier run paused them, no run resumed them, and nothing else will.
# Brought back here, before the cron files are read, they are rewritten,
# paused and resumed like any other.
for pf in "$CRON_DIR"/*.paused; do
  [ -f "$pf" ] || continue
  cf="${pf%.paused}"
  [ -f "$cf" ] && continue
  mv "$pf" "$cf" && echo "   $(basename "$cf") was left paused by an earlier, stopped roll — active again (it names $(grep -Eo 'prairiepost-[A-Za-z0-9._-]+' "$cf" | head -1))"
done
declare -A DIR_NEW=()
declare -A DIR_CRONS=()
declare -A UNFINISHED=()   # OLD dir -> 1 when the vhosts already named this release but no roll finished it
write_baseline_file() { # new dir, old dir — what every domain answered before this roll, in the release it builds
  local rec
  {
    echo "# prairiepost upgrade baseline: what each domain's front page answered BEFORE the roll that wrote this file changed anything."
    echo "# written $(date -u +%Y-%m-%dT%H:%M:%SZ) (stamp $STAMP) by upgrade-papers.sh, rolling $2 to $SHA"
    echo "# one line per domain:  domain|status code|stylesheet set|<title>   (the title and the stylesheet set are kept only for a 200)"
    # A domain this roll holds to another baseline carries that record,
    # because it is what a run finishing this roll must hold it to — and
    # the file says so, so that nobody reads it as that domain's answer.
    for D in $(all_domains); do
      case "${BASE_SRC[$D]:-}" in
        recovery)  echo "# $D: held by this roll to PP_UPGRADE_BASELINE (${BASE_FILE[$D]}), not to what it answered when the roll started" ;;
        abandoned) echo "# $D: held by this roll to the baseline of the abandoned roll (${BASE_FILE[$D]}), not to what it answered when the roll started" ;;
      esac
    done
    for D in "${!CODE_BEFORE[@]}"; do
      printf '%s|%s|%s|%s\n' "$D" "${CODE_BEFORE[$D]:-}" "${CSS_BEFORE[$D]:-}" "${TITLE_BEFORE[$D]:-}"
    done | LC_ALL=C sort
  } > "$1/.pp-roll-baseline"
  # The RECORD: the same file under the roll's stamp. A later roll into
  # this directory replaces .pp-roll-baseline; nothing removes the record,
  # and nothing reads it unless a roll is told to (PP_UPGRADE_BASELINE).
  rec="$1/.pp-roll-baseline.$STAMP"
  [ -e "$rec" ] && rec="$1/.pp-roll-baseline.$STAMP-$$"
  if cp "$1/.pp-roll-baseline" "$rec" 2>/dev/null; then
    RECORD[$2]="$rec"
    echo "   baseline saved: $rec (a record: nothing reads it unless a later roll is told to)"
  else
    echo "   WARNING: the record of this roll's baseline could not be written ($rec). The roll goes on; $1/.pp-roll-baseline is in place."
  fi
}
for OLD in "${!DIR_VHOSTS[@]}"; do
  vhosts=(${DIR_VHOSTS[$OLD]})
  if [ "${#vhosts[@]}" -eq 1 ]; then LABEL="${vhosts[0]}"; else LABEL="shared"; fi
  case "$OLD" in
    # A vhost that already names a directory for this commit keeps it: a
    # run stopped inside this loop must not split a shared group into a
    # directory per paper on the next run.
    "$WWW/prairiepost-$SHA-"*) NEW="$OLD" ;;
    *) NEW="$WWW/prairiepost-$SHA-$LABEL" ;;
  esac
  if [ "$OLD" = "$NEW" ]; then
    if [ -f "$NEW/.pp-roll-complete" ]; then
      echo "-- ${vhosts[*]}: already on $SHA"
      continue
    fi
    # The vhosts name this release but no run wrote its completion mark:
    # a roll was interrupted (or stopped at a failed nginx test, a
    # part-finished migration, or a failed verification) after rewriting
    # the files. Finish it — migrate whatever is pending, test, reload,
    # verify against the baseline that roll saved — instead of reporting
    # nothing to do.
    echo "-- ${vhosts[*]}: already on $SHA, but no run finished that roll (no $NEW/.pp-roll-complete) — finishing it"
    UNFINISHED[$OLD]=1
    FINISHING=1
  else
    echo "-- ${vhosts[*]}: $OLD -> $NEW"
    if [ -f "$OLD/.pp-roll-held" ]; then
      echo "   $OLD ended HELD ($(head -1 "$OLD/.pp-roll-held" 2>/dev/null)): this roll replaces it. Its domains are held to what they answer as this roll starts — docs/MIGRATION-COMPAT.md, \"What a second roll can and cannot do\""
      if [ -n "$RECOVERY_FILE" ]; then
        echo "   (except where PP_UPGRADE_BASELINE records them — above)"
      elif [ -n "$(record_of "$OLD")" ]; then
        echo "   To hold them instead to what they answered before the held roll changed anything: PP_UPGRADE_BASELINE=$(record_of "$OLD")"
      fi
    fi
    if [ -d "$NEW" ]; then
      # Left by an earlier roll into this commit (so a roll back to it).
      # Whole, because a tree is only ever renamed into place. Its marks
      # are that roll's; this roll must earn its own.
      rm -f "$NEW/.pp-roll-complete" "$NEW/.pp-roll-held" "$NEW/.pp-roll-baseline" "$NEW/.pp-roll-before"
      echo "   $NEW exists from an earlier roll: its completion mark is cleared; this roll must finish on its own"
    else
      # Copy under a temporary name and rename, so the directory either
      # exists whole or not at all — a run stopped inside cp leaves no
      # half tree for the next run to build on.
      rm -rf "$NEW.tmp."*
      if cp -a "$TPL" "$NEW.tmp.$STAMP" && mv "$NEW.tmp.$STAMP" "$NEW"; then :; else
        rm -rf "$NEW.tmp.$STAMP"
        echo "   copy failed, skipped"
        continue
      fi
    fi
    write_baseline_file "$NEW" "$OLD"
  fi

  # The original config, VERBATIM — host mapping and every other live
  # decision intact. If a previous run of this script already wrapped it,
  # the true source is app/config.site.php; carry that forward instead.
  # (A roll being finished has both files in place already.)
  if [ -z "${UNFINISHED[$OLD]:-}" ]; then
  SRC="$OLD/config.php"
  [ -f "$OLD/app/config.site.php" ] && SRC="$OLD/app/config.site.php"
  cp "$SRC" "$NEW/app/config.site.php" || { echo "   config copy failed, skipped"; continue; }
  cat > "$NEW/config.php" <<'WRAP'
<?php
// Wrapper written by upgrade-papers.sh. The site's real configuration is
// app/config.site.php, copied verbatim from the previous release — its
// host mapping runs per request exactly as before. Only the control-room
// key is added here, and only when the config doesn't set it itself.
$c = require __DIR__ . '/app/config.site.php';
if (is_array($c) && !isset($c['hub_slug'])) {
    $c['hub_slug'] = 'civismedia';
}
return $c;
WRAP
  fi
  # Lint only — the config is never executed outside a real request.
  php -l "$NEW/config.php" >/dev/null || { echo "   wrapper doesn't lint, skipped"; continue; }
  php -l "$NEW/app/config.site.php" >/dev/null || { echo "   copied config doesn't lint, skipped"; continue; }

  rm -rf "$NEW/uploads"
  ln -s "$SHARED_UP" "$NEW/uploads"
  chown -R root:www-data "$NEW"
  chmod -R g+rX "$NEW"
  chown -h www-data:www-data "$NEW/uploads"
  chown -R www-data:www-data "$NEW/data"
  chmod 2775 "$NEW/data"
  chmod 640 "$NEW/config.php" "$NEW/app/config.site.php"

  ok=1
  for base in "${vhosts[@]}"; do
    VH=$(readlink -f "$EN_DIR/$base")
    cp "$VH" "$VH.bak.$STAMP"
    ALL_VHOST_BACKUPS+=("$VH")
    sed -i "s|root[[:space:]]\+$OLD;|root $NEW;|" "$VH"
    grep -q "root $NEW;" "$VH" || { cp "$VH.bak.$STAMP" "$VH"; echo "   $base: vhost rewrite failed, restored"; ok=0; }
    # Existing blocks predate the Phase 1/2 path protections: a template
    # only guards NEW sites, so live blocks get the shared deny snippet
    # injected right after their root line (idempotent by marker).
    SNIPPET_DIR="${PP_UPGRADE_SNIPPET_DIR:-/etc/nginx/snippets}"
    if [ -f "$TPL/tools/vps/pp-deny.conf" ] && ! grep -qF -e 'tools|tests' -e 'prairiepost-deny' "$VH"; then
      mkdir -p "$SNIPPET_DIR"
      cp "$TPL/tools/vps/pp-deny.conf" "$SNIPPET_DIR/prairiepost-deny.conf"
      sed -i "s|root $NEW;|root $NEW;\n    include $SNIPPET_DIR/prairiepost-deny.conf;|" "$VH"
      echo "   $base: legacy block — shared deny snippet injected"
    fi
    pp_front_controller "$VH" && echo "   $base: legacy block — paths no rewrite names now reach router.php"
    pp_tips_unlogged "$VH" && echo "   $base: /tips kept out of the access log"
  done
  [ "$ok" = "1" ] || continue

  for cf in "$CRON_DIR"/*; do
    [ -f "$cf" ] || continue
    # cron.d ignores files with a dot in the name, so a dotted basename
    # is never an active job — it is a .bak.* or .paused artifact from
    # an earlier run and must not be rewritten or paused again.
    case "$(basename "$cf")" in *.*) continue ;; esac
    if grep -q "$OLD" "$cf"; then
      cp "$cf" "$cf.bak.$STAMP"
      ALL_CRON_BACKUPS+=("$cf")
      sed -i "s|$OLD|$NEW|g" "$cf"
      DIR_CRONS[$OLD]="${DIR_CRONS[$OLD]:-} $cf"
      echo "   cron updated: $(basename "$cf")"
    fi
  done

  DIR_NEW[$OLD]="$NEW"
done

[ "${#DIR_NEW[@]}" -gt 0 ] || { echo; echo "Nothing to upgrade."; exit 0; }

# Before the migration, the .bak.$STAMP copies are true pre-roll files
# and putting them back changes nothing — unless this run is finishing a
# roll an earlier run started, in which case they are copies of files
# that already named the new release, and there is nothing to put back.
restore_or_explain() { # -> 0 after a real restore; 1 when there was nothing to restore (and says so)
  if [ "$FINISHING" = 1 ]; then
    echo "   nothing to put back: this run was finishing a roll an earlier run started, so the vhost and cron files already named the new release when it began. nginx runs what it ran. If the earlier run had raised the schema, every paper answers 503 until a run finishes." >&2
    return 1
  fi
  for VH in "${ALL_VHOST_BACKUPS[@]}"; do cp "$VH.bak.$STAMP" "$VH"; done
  for cf in "${PAUSED[@]}"; do rm -f "$cf.paused"; done
  for cf in "${ALL_CRON_BACKUPS[@]}"; do cp "$cf.bak.$STAMP" "$cf"; done
  return 0
}

say "Read each schema's stored version BEFORE the migration step"
PENDING=0
for OLD in "${!DIR_NEW[@]}"; do
  NEWDIR="${DIR_NEW[$OLD]}"
  IDENT=$(db_identity "$NEWDIR")
  if [ -z "$IDENT" ]; then
    # Stop here, while nothing has been paused or migrated and the
    # restore is a true one — not in the migration loop, after another
    # group's schema may already have moved.
    if restore_or_explain; then
      fail "cannot read the database identity from $NEWDIR/config.php (its config.site.php does not evaluate under the CLI) — every vhost and cron file restored, the schema was not touched, nothing changed"
    fi
    fail "cannot read the database identity from $NEWDIR/config.php (its config.site.php does not evaluate under the CLI) — nothing was put back (above); fix the config and run again"
  fi
  DIR_IDENT[$OLD]="$IDENT"
  if [ -z "${SCHEMA_LABEL[$IDENT]:-}" ]; then
    # One read per logical schema, however many groups share it.
    SCHEMA_LABEL[$IDENT]="$(basename "$NEWDIR")"
    SCHEMA_DIR[$IDENT]="$NEWDIR"
    schema_stored_after "$NEWDIR" pre   # the retrying reader; one failed read is not a changed schema
    read -r S_STATE S_VERSION S_TARGET <<< "$STATUS_OUT"
    SCHEMA_STATE[$IDENT]="${S_STATE:-}"
    SCHEMA_BEFORE[$IDENT]="${S_VERSION:-}"
    SCHEMA_NOW[$IDENT]="${S_VERSION:-}"
    if [ "${S_STATE:-}" = "ready" ]; then
      echo "   the schema behind $(basename "$NEWDIR"): stored version $S_VERSION — nothing pending"
    elif [ -n "${S_STATE:-}" ]; then
      PENDING=1
      echo "   the schema behind $(basename "$NEWDIR"): $S_STATE — stored version $S_VERSION, this release is built for $S_TARGET"
    else
      PENDING=1
      echo "   the schema behind $(basename "$NEWDIR"): stored version NOT READABLE (php tools/migrate.php --status gave no state line)"
    fi
  fi
  # A roll this run finishes, or one it replaces as abandoned, recorded
  # the version it found. That is the "before" this roll is judged
  # against, not what is stored now: the step may have run in between.
  BEFORE_FILE=""; WHO=""
  if [ -n "${UNFINISHED[$OLD]:-}" ] && [ -f "$NEWDIR/.pp-roll-before" ]; then
    BEFORE_FILE="$NEWDIR/.pp-roll-before"; WHO="the run that started this roll"
  elif [ -n "${ABANDONED[$OLD]:-}" ] && [ -f "$OLD/.pp-roll-before" ]; then
    BEFORE_FILE="$OLD/.pp-roll-before"; WHO="the abandoned roll into $(basename "$OLD")"
  fi
  if [ -n "$BEFORE_FILE" ]; then
    SAVED_BEFORE=$(sed -n 's/^version_before=//p' "$BEFORE_FILE" | head -1)
    SAVED_BY=$(sed -n 's/^stamp=//p' "$BEFORE_FILE" | head -1)
    [ -n "${UNFINISHED[$OLD]:-}" ] && [ -n "$SAVED_BY" ] && FIRST_STAMP[$OLD]="$SAVED_BY"
    case "$SAVED_BEFORE" in
      ''|*[!0-9a-z]*) ;;
      *) SCHEMA_BEFORE[$IDENT]="$SAVED_BEFORE"
         echo "   $(basename "$NEWDIR"): $WHO (stamp ${SAVED_BY:-?}) found stored version $SAVED_BEFORE; it is ${SCHEMA_NOW[$IDENT]:-not readable} now" ;;
    esac
  fi
done
# Every release this run builds records the version its roll started
# from — after the loop, so a group sharing a schema with a finished or
# abandoned roll records where THAT roll started.
for OLD in "${!DIR_NEW[@]}"; do
  [ -n "${UNFINISHED[$OLD]:-}" ] && [ -f "${DIR_NEW[$OLD]}/.pp-roll-before" ] && continue
  id="${DIR_IDENT[$OLD]}"
  printf 'version_before=%s\nstate_before=%s\nstamp=%s\nold=%s\n' "${SCHEMA_BEFORE[$id]:-}" "${SCHEMA_STATE[$id]:-}" "$STAMP" "$OLD" > "${DIR_NEW[$OLD]}/.pp-roll-before"
done

if [ "$PENDING" = "1" ]; then
  say "nginx config test BEFORE the migration (the step is about to change the schema)"
  # The vhost files already name the new release; nginx has not reloaded.
  # A configuration that will not load has to stop the roll HERE, while
  # the old release is still a real fallback. After the step it is not.
  if [ "$FIXTURE" = "1" ] && [ "$FIXTURE_NGINX_FAIL" != "pre" ]; then
    echo "   (fixture: nginx test skipped)"
  elif ! nginx_test; then
    if restore_or_explain; then
      nginx_test
      fail "nginx test failed BEFORE the schema migration — every vhost and cron file restored, the schema was not touched, nothing changed"
    fi
    fail "nginx test failed BEFORE the schema migration — nothing was put back (above). Fix the error printed above and run again at the same release"
  fi
fi

say "Quiesce scheduled writers, then migrate each logical schema ONCE"
# The cron files were rewritten to the new release above but nginx has not
# reloaded: old code still serves. Pausing the cron files (cron.d ignores
# dotted names) stops scheduled writers for the window; any web-cron
# request against the NEW code meets the readiness gate and exits before
# business writes. OLD-code in-flight writers are covered by the
# compatibility contract instead: see docs/MIGRATION-COMPAT.md — a
# migration that old code cannot safely write through requires the
# full-stop procedure there, not this script.

for OLD in "${!DIR_CRONS[@]}"; do
  for cf in ${DIR_CRONS[$OLD]:-}; do
    if [ -f "$cf" ]; then
      mv "$cf" "$cf.paused"
      PAUSED+=("$cf")
      echo "   paused: $(basename "$cf")"
    fi
  done
done
fixture_die after-pause
# From here to the reload nothing stops the roll: a stop between the step
# that raises the version and the reload is every paper on 503 until a
# run finishes, and the probes are local, so finishing is always the
# better outcome. INT and TERM are ignored, not deferred.
trap '' INT TERM
resume_crons() {
  for cf in "${PAUSED[@]}"; do
    [ -f "$cf.paused" ] && mv "$cf.paused" "$cf" && echo "   resumed: $(basename "$cf")"
  done
}

# One migration per LOGICAL database/schema, however many release groups
# share it — identity is the configured driver+endpoint+database+schema,
# and the runner's own per-schema lock makes an accidental second run a
# clean no-op rather than a hazard.
declare -A MIGRATED=()
for OLD in "${!DIR_NEW[@]}"; do
  NEWDIR="${DIR_NEW[$OLD]}"
  IDENT=$(db_identity "$NEWDIR")
  [ -n "$IDENT" ] || { resume_crons; fail "cannot read the database identity from $NEWDIR/config.php"; }
  if [ -n "${MIGRATED[$IDENT]:-}" ]; then
    echo "   schema already migrated this run ($(basename "$NEWDIR") shares it)"
    continue
  fi
  echo "   migrating the schema behind $(basename "$NEWDIR")"
  MIGRATION_STARTED=1
  if ! (cd "$NEWDIR" && php tools/migrate.php --apply); then
    schema_after "$IDENT" "$NEWDIR" 1
    if any_schema_rose; then
      # A step committed before the failure (or an earlier schema was
      # migrated in this run). The old release cannot serve what is
      # stored now, so "roll everything back" would be untrue: nothing
      # is put back, and the message says what state the box is in.
      echo "MIGRATION FAILED — after the stored schema version had already risen; nothing was reloaded." >&2
      schema_moved_lines >&2
      finishing_lines >&2
      not_a_fallback_line >&2
      echo "  So nothing is put back: the vhost and cron files stay on the new release." >&2
      resume_crons >&2
      fail "the schema migration failed part-way and the old release is not a fallback. Until the migration is finished neither release can serve: the schema is ahead of the old one and not yet complete for the new one. From the new release, (cd $NEWDIR && php tools/migrate.php --status) names the obstacle and the repair: once it is out of the way, --apply reruns the failed step on PostgreSQL and SQLite, and a schema --status calls partial needs --apply --resume-partial. Then run this roll again at the same release — it finishes what this run started (test, reload, verify, resume). Do NOT copy the .bak.$STAMP files back (docs/MIGRATION-COMPAT.md)."
    fi
    if [ "${SCHEMA_CHANGE[$IDENT]:-unknown}" != "same" ]; then
      echo "WARNING: the stored schema version was not readable both before and after the failed step (before: ${SCHEMA_BEFORE[$IDENT]:-not readable}, after: ${SCHEMA_AFTER[$IDENT]:-not readable})." >&2
      echo "         What follows assumes it did not change. If it rose, the old release answers 503: check with (cd $NEWDIR && php tools/migrate.php --status)." >&2
    fi
    if [ "$FINISHING" = 1 ]; then
      echo "MIGRATION FAILED — in a run that was finishing a roll an earlier run started; nothing was reloaded." >&2
      restore_or_explain >&2 || true
      resume_crons >&2
      fail "the schema migration failed. Diagnose with: (cd $NEWDIR && php tools/migrate.php --status); when it is out of the way, run this script again at the same release"
    fi
    echo "MIGRATION FAILED — rolling every vhost and cron file back; nothing was reloaded." >&2
    for VH in "${ALL_VHOST_BACKUPS[@]}"; do cp "$VH.bak.$STAMP" "$VH"; done
    for cf in "${PAUSED[@]}"; do rm -f "$cf.paused"; done
    for cf in "${ALL_CRON_BACKUPS[@]}"; do cp "$cf.bak.$STAMP" "$cf"; done
    fail "the schema migration failed; the box still serves the old release. Diagnose with: (cd $NEWDIR && php tools/migrate.php --status)"
  fi
  MIGRATED[$IDENT]=1
  schema_after "$IDENT" "$NEWDIR" 0
  case "${SCHEMA_CHANGE[$IDENT]}" in
    same) echo "   stored schema version after the step: ${SCHEMA_AFTER[$IDENT]} (unchanged)" ;;
    rose) echo "   stored schema version after the step: ${SCHEMA_AFTER[$IDENT]} — ROSE from ${SCHEMA_BEFORE[$IDENT]:-none}. The old release is no longer a fallback for this schema." ;;
    *)    echo "   WARNING: $(schema_note "$IDENT") — the old release is not offered as a fallback." ;;
  esac
done
MIGRATION_RAN=1

say "nginx config test + reload"
if [ "$FIXTURE" = "1" ] && [ "$FIXTURE_NGINX_FAIL" != "post" ]; then
  echo "   (fixture: nginx test/reload skipped)"
elif ! nginx_test; then
  if any_schema_moved; then
    # nginx is still running its previous configuration — the old
    # release — and that release cannot serve the schema as it now
    # stands. Putting the files back would only make the disk agree with
    # an outage; the way out is to load the new release.
    echo "nginx test FAILED — after the schema migration. Nothing is put back:" >&2
    schema_moved_lines >&2
    finishing_lines >&2
    not_a_fallback_line >&2
    echo "  nginx is still running its previous configuration (the old release), so that is what the papers get until it loads the new one." >&2
    echo "  The vhost and cron files stay on the new release." >&2
    say "Resume scheduled writers (on the new release — the only one this schema can run)"
    resume_crons
    fail "nginx test failed AFTER the migration changed the schema — NOT restored, and the old release is not a fallback. Fix the nginx error printed above, then run this roll again at the same release: it finishes what this run started (test, reload, verify). Do NOT copy the .bak.$STAMP files back (docs/MIGRATION-COMPAT.md)."
  fi
  for VH in "${ALL_VHOST_BACKUPS[@]}"; do cp "$VH.bak.$STAMP" "$VH"; done
  for cf in "${ALL_CRON_BACKUPS[@]}"; do cp "$cf.bak.$STAMP" "$cf"; done
  # The pause pass renamed the (already rewritten) active files to
  # FILE.paused; the originals are back from .bak above, so the dotted
  # copies are pure residue — remove them or they linger forever.
  for cf in "${PAUSED[@]}"; do rm -f "$cf.paused"; done
  nginx_test
  fail "nginx test failed — every vhost and cron file restored, nothing changed"
fi
if [ "$FIXTURE" != "1" ]; then
  if systemctl reload nginx; then
    RELOADED=1
  else
    # The configuration passed its test, so this is nginx or systemd, not
    # the vhosts. The files stay as they are — they are right — and a
    # rerun finishes the roll once nginx can reload.
    say "Resume scheduled writers (the reload failed; the new release is ready for them)"
    resume_crons
    fail "systemctl reload nginx failed after a successful nginx -t. nginx still runs the old release; the vhost and cron files name the new one and are left so. Look at: systemctl status nginx; journalctl -u nginx. Then run this roll again at the same release — it finishes (test, reload, verify). Do not copy the .bak.$STAMP files back$(any_schema_moved && echo ': the old release cannot serve this schema')."
  fi
fi
[ "$FIXTURE" = "1" ] && RELOADED=1   # a fixture has nothing to reload
arm_traps   # the stretch that must not be interrupted ends with the reload
fixture_die after-reload
[ "$FIXTURE" = "1" ] || sleep 2

say "Verify every domain serves 200 AND its own masthead (the baseline title)"
RESTORED=0
declare -A RESTORED_GROUPS=()
hold_group() { # OLD dir — the group failed verification and putting it back is no way back: do NOT put it back
  local old="$1" cf c reason
  c="$(group_change "$old")"
  case "$c" in
    rose|unknown)
      if [ -n "${UNFINISHED[$old]:-}" ]; then reason="this run finished a roll an earlier run started, and $(schema_note "${DIR_IDENT[$old]:-}")"
      elif [ -n "${ABANDONED[$old]:-}" ]; then reason="it replaced an abandoned roll, and $(schema_note "${DIR_IDENT[$old]:-}")"
      else reason="$(schema_note "${DIR_IDENT[$old]:-}")"
      fi
      echo "     NOT restoring release group ($old): $reason."
      echo "     $NOT_A_FALLBACK —"
      echo "     putting this group back would take down EVERY domain it serves (${DIR_DOMAINS[$old]# }), not only the one that failed."
      reason="$reason; the release before it cannot serve the schema, so putting the group back is 503 on every domain it serves" ;;
    finishing)
      reason="this run finished a roll an earlier run started (stamp ${FIRST_STAMP[$old]:-?}), so its own copies name this same release. Neither run raised the schema, so the release before that roll can still serve it: to put the group back, copy <vhost>.bak.${FIRST_STAMP[$old]:-<that stamp>} over each of its vhosts and <cronfile>.bak.${FIRST_STAMP[$old]:-<that stamp>} over each of its cron files, then nginx -t && systemctl reload nginx"
      echo "     NOT restoring release group ($old): $reason." ;;
    abandoned)
      reason="it was rolled from $(basename "$old"), the release of a roll abandoned with PP_UPGRADE_ABANDON; no run verified that release, so it is no fallback"
      echo "     NOT restoring release group ($old): $reason." ;;
  esac
  HOLD_REASON[$old]="$reason"
  echo "     It stays on ${DIR_NEW[$old]}. Each FAIL above is what that domain is serving now, and goes on serving."
  # Its scheduled writers resume here, on the new release: no other
  # release can run them against this schema, and a cron file left
  # .paused is skipped by every later roll (cron.d and this script both
  # ignore dotted names), so it would stay off for good.
  for cf in ${DIR_CRONS[$old]:-}; do
    [ -f "$cf.paused" ] && mv "$cf.paused" "$cf" && echo "     resumed: $(basename "$cf") (on the new release)"
  done
  HELD_GROUPS+=("$old")
}
OLD_SAYS=""
old_ready() { # OLD dir -> false only when that release SAYS it is not built for the version stored now; sets $OLD_SAYS
  local state version target
  read -r state version target <<< "$(schema_stored "$1")"
  OLD_SAYS=""
  # No state line (a release from before the runner) is not an answer,
  # and nothing is claimed from it.
  [ -n "${state:-}" ] && [ "$state" != "ready" ] || return 0
  OLD_SAYS="its own runner says $state: stored version $version, built for $target"
  return 1
}
recovery_pointer() { # after a failed verification: what a later roll can be told
  local o r
  [ "$PROBES" = "1" ] || return 0
  echo "A roll holds each domain to what it answered when the roll STARTED. If a domain marked FAIL was"
  echo "already failing then, its FAIL may be the repair itself. A roll can be held to a baseline saved"
  echo "while the domain was healthy instead: PP_UPGRADE_BASELINE=<file> on the same roll"
  echo "(docs/MIGRATION-COMPAT.md, \"The recovery baseline\"). Each roll keeps one, .pp-roll-baseline.<stamp>, in the release it built."
  [ -z "$RECOVERY_FILE" ] || echo "This roll was held to: $RECOVERY_FILE"
  # Never this roll's own record: a group is restored because a domain
  # failed against what this roll captured, so that record holds the
  # domain to the failure and cannot mend it. The candidate is the record
  # of the release the group went back to, kept by the roll that built it
  # before that roll changed anything — for a held release, the held
  # roll's, the one the replacement line named. A release with no record:
  # no line.
  for o in "${!RESTORED_GROUPS[@]}"; do
    r="$(record_of "$o")"
    [ -n "$r" ] && [ "$r" != "$RECOVERY_FILE" ] && echo "The record of $(basename "$o"), where this group went back, kept before the roll into it changed anything: $r"
  done | sort -u
}
held_to_summary() { # which baseline each verified domain was held to — printed when any was held to something other than its own answer
  local o d rec="" fin="" ab="" own="" ff="" af=""
  [ "$PROBES" = "1" ] || return 0
  for o in "${!DIR_NEW[@]}"; do
    for d in ${DIR_DOMAINS[$o]}; do
      case "${BASE_SRC[$d]:-}" in
        recovery)  rec="$rec $d" ;;
        finishing) fin="$fin $d"; ff="$ff ${BASE_FILE[$d]}" ;;
        abandoned) ab="$ab $d";   af="$af ${BASE_FILE[$d]}" ;;
        *)         own="$own $d" ;;
      esac
    done
  done
  [ -n "$rec$fin$ab" ] || return 0
  say "Which baseline each domain was held to"
  [ -n "$rec" ] && echo "  the recovery baseline $RECOVERY_FILE:$(sorted_words $rec)"
  [ -n "$fin" ] && echo "  the baseline the run that started this roll saved ($(sorted_words $(printf '%s\n' $ff | sort -u) | sed 's/^ //')):$(sorted_words $fin)"
  [ -n "$ab" ]  && echo "  the baseline the abandoned roll saved ($(sorted_words $(printf '%s\n' $af | sort -u) | sed 's/^ //')):$(sorted_words $ab)"
  echo "  what it answered when this roll started:$( [ -n "$own" ] && sorted_words $own || echo ' none')"
}
# A run finishing an earlier roll must not hold the domains to what they
# answered when IT started — after a rise that is the gate's 503, and
# after a stop during verification it is the new release itself, wrong
# masthead included. It holds them to what the run that started the roll
# saved before anything changed.
# A domain PP_UPGRADE_BASELINE records keeps that record here too (the
# precedence where the file is applied).
for OLD in "${!UNFINISHED[@]}"; do
  BF="${DIR_NEW[$OLD]}/.pp-roll-baseline"
  rec_kept=""
  if [ -f "$BF" ]; then
    n=0
    while IFS='|' read -r D C CSS T; do
      case "$D" in ''|'#'*) continue ;; esac
      if [ "${BASE_SRC[$D]:-}" = "recovery" ]; then rec_kept="$rec_kept $D"; continue; fi
      CODE_BEFORE[$D]="$C"; CSS_BEFORE[$D]="$CSS"; TITLE_BEFORE[$D]="$T"
      BASE_SRC[$D]="finishing"; BASE_FILE[$D]="$BF"
      n=$((n + 1))
    done < "$BF"
    if [ "$n" -gt 0 ] || [ -z "$rec_kept" ]; then
      echo "   $(basename "${DIR_NEW[$OLD]}"): its domains are held to the baseline the run that started this roll saved ($n domains, $BF), not to what they answered when this run began"
    fi
  else
    echo "   WARNING: $(basename "${DIR_NEW[$OLD]}") has no saved baseline ($BF); its domains are held to answering 200, and their mastheads cannot be checked"
    for DOMAIN in ${DIR_DOMAINS[$OLD]}; do
      if [ "${BASE_SRC[$DOMAIN]:-}" = "recovery" ]; then rec_kept="$rec_kept $DOMAIN"; continue; fi
      CODE_BEFORE[$DOMAIN]="200"; TITLE_BEFORE[$DOMAIN]=""; CSS_BEFORE[$DOMAIN]=""
    done
  fi
  [ -z "$rec_kept" ] || echo "   $(basename "${DIR_NEW[$OLD]}"): held to PP_UPGRADE_BASELINE, NOT to the baseline the run that started this roll saved, because the named file records them:$rec_kept"
done
for OLD in "${!DIR_NEW[@]}"; do
  group_ok=1
  DOMAINS_TO_CHECK="${DIR_DOMAINS[$OLD]}"
  if [ "$PROBES" = "0" ]; then
    # No TLS stack in a fixture, and no HTTP stand-in given: the
    # post-migration smoke is a CLI boot of the candidate against its real
    # config — the readiness gate plus the tenant resolution ARE the
    # candidate behaving.
    if SLUG=$( (cd "${DIR_NEW[$OLD]}" && php -r 'require "app/bootstrap.php"; db(); echo current_site()["slug"];') 2>&1 ); then
      echo "PASS (fixture) $(basename "${DIR_NEW[$OLD]}") boots ready as tenant '$SLUG'"
    else
      echo "FAIL (fixture) $(basename "${DIR_NEW[$OLD]}"): $SLUG"
      group_ok=0
    fi
    # No domain can be probed in a fixture; what happens to a group that
    # failed is the same code a real box runs, below.
    DOMAINS_TO_CHECK=""
  fi
  for DOMAIN in $DOMAINS_TO_CHECK; do
    IFS='|' read -r code title <<< "$(front_title "$DOMAIN")"
    expected="${TITLE_BEFORE[$DOMAIN]}"
    want_code="${CODE_BEFORE[$DOMAIN]:-200}"
    # A domain held to the named recovery baseline says so on its own
    # line, with what it answered when this roll started. For every other
    # domain $rb is empty and each line is word for word what it was.
    rb="${RB_TAG[$DOMAIN]:-}"
    if [ "$code" != "$want_code" ]; then
      if [ -n "$rb" ]; then
        echo "FAIL $DOMAIN answered $code, the recovery baseline says $want_code$rb"
      else
        echo "FAIL $DOMAIN answered $code, served $want_code before"
      fi
      group_ok=0
    elif [ "$code" != "200" ]; then
      if [ -n "$rb" ]; then
        echo "PASS $DOMAIN -> $code$rb"
      else
        echo "PASS $DOMAIN -> $code (unchanged)"
      fi
    elif [ -n "$expected" ] && [ "$title" != "$expected" ]; then
      echo "FAIL $DOMAIN wrong tenant — got $title, expected $expected$rb"
      group_ok=0
    else
      css_now="$(front_css "$DOMAIN")"
      css_was="${CSS_BEFORE[$DOMAIN]:-}"
      if [ -n "$css_was" ] && [ "$css_now" != "$css_was" ]; then
        # The database supplies the title, so a release missing this paper's
        # template tree still answers 200 with the right masthead. The
        # stylesheet set is served from the release itself — losing it is
        # template loss, and template loss rolls back like a wrong tenant.
        if [ -n "$rb" ]; then
          echo "FAIL $DOMAIN stylesheet set is not the recovery baseline's — baseline [$css_was] now [$css_now]$rb"
        else
          echo "FAIL $DOMAIN stylesheet set changed — was [$css_was] now [$css_now]"
        fi
        group_ok=0
      else
        missing=""
        for f in $css_now; do
          [ -f "${DIR_NEW[$OLD]}$f" ] || missing="$missing $f"
        done
        if [ -n "$missing" ]; then
          echo "FAIL $DOMAIN references stylesheets absent from the new release:$missing$rb"
          group_ok=0
        else
          echo "PASS $DOMAIN -> ${title:-200}$rb"
        fi
      fi
    fi
  done
  if [ "$group_ok" = "0" ] && [ "$(group_change "$OLD")" != "same" ]; then
    hold_group "$OLD"
  elif [ "$group_ok" = "0" ]; then
    echo "     restoring release group ($OLD): vhosts and cron files"
    for base in ${DIR_VHOSTS[$OLD]}; do
      VH=$(readlink -f "$EN_DIR/$base")
      cp "$VH.bak.$STAMP" "$VH"
    done
    for cf in ${DIR_CRONS[$OLD]:-}; do
      cp "$cf.bak.$STAMP" "$cf"
    done
    # Restoring is "back where it came from", and this roll did not move
    # the schema — but the group may have come INTO the roll on a release
    # built for another version (its vhosts copied back after a rise).
    # That release is asked, and its answer printed: the restore is then
    # a return to the gate's 503, which is where the roll found the group.
    old_ready "$OLD" || echo "     NOTE: $OLD is not built for the stored schema version (${OLD_SAYS}). Back on it, every domain of this group answers the readiness gate's 503."
    RESTORED_GROUPS[$OLD]=1
    RESTORED=1
  fi
done
if [ "$RESTORED" = "1" ]; then
  if [ "$FIXTURE" != "1" ]; then
    { nginx -t && systemctl reload nginx; } \
      || echo "WARN: the restore did not load into nginx (see above). The restored group's vhost files name its old release, but nginx may still run the new one: nginx -t && systemctl reload nginx by hand." >&2
  fi
  # Restored groups got their OLD cron files back from .bak above, so
  # their .paused copies are residue. The groups that PASSED got nothing
  # back — their files are still the paused rewrites — and resume here,
  # on the new release they now serve from.
  for cf in "${PAUSED[@]}"; do
    if [ -f "$cf" ]; then
      rm -f "$cf.paused"
    elif [ -f "$cf.paused" ]; then
      mv "$cf.paused" "$cf" && echo "   resumed: $(basename "$cf")"
    fi
  done
  echo "WARN: at least one release group was restored — send this output back for a look."
  recovery_pointer
else
  say "Resume scheduled writers"
  resume_crons
fi
held_to_summary

say "Fallback: the hub's uploads, if it was skipped above (no config.php)"
HUBVH=$(readlink -f "$EN_DIR/civismedia" 2>/dev/null || true)
if [ -n "$HUBVH" ] && [ -f "$HUBVH" ]; then
  HUBROOT=$(grep -Eo "root[[:space:]]+$WWW/prairiepost-[A-Za-z0-9._-]+" "$HUBVH" | head -1 | awk '{print $2}')
  if [ -n "$HUBROOT" ] && [ -d "$HUBROOT/uploads" ] && [ ! -L "$HUBROOT/uploads" ]; then
    cp -an "$HUBROOT/uploads/." "$SHARED_UP/" 2>/dev/null
    rm -rf "$HUBROOT/uploads"
    ln -s "$SHARED_UP" "$HUBROOT/uploads"
    chown -h www-data:www-data "$HUBROOT/uploads"
    echo "hub uploads now shared"
  else
    echo "hub uploads already shared (or hub root not found)"
  fi
fi

# Every group that passed is marked done inside its release, so a later
# run can tell "already on this release" from "named by the vhosts but
# never reloaded and verified". A held group gets no mark: a run told to
# try again verifies it again instead of reporting nothing to do.
# A held group gets .pp-roll-held: the roll ran to the end, it was not
# stopped, so it does not block a roll of another commit (the fix).
for OLD in "${!DIR_NEW[@]}"; do
  [ -n "${RESTORED_GROUPS[$OLD]:-}" ] && continue
  held=0
  for H in "${HELD_GROUPS[@]}"; do [ "$H" = "$OLD" ] && held=1; done
  if [ "$held" = 1 ]; then
    printf 'held by upgrade-papers.sh run %s (%s): verification failed and the group was kept on this release\n' \
      "$STAMP" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "${DIR_NEW[$OLD]}/.pp-roll-held"
    continue
  fi
  rm -f "${DIR_NEW[$OLD]}/.pp-roll-held"
  printf 'upgrade-papers.sh run %s (%s): nginx reloaded onto this release and every domain of it passed verification\n' \
    "$STAMP" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "${DIR_NEW[$OLD]}/.pp-roll-complete"
done

if [ "${#HELD_GROUPS[@]}" -gt 0 ]; then
  say "NOT done — verification failed, and a group is held on the new release"
  echo "Held on the new release, NOT restored:"
  for OLD in "${HELD_GROUPS[@]}"; do
    echo "  ${DIR_NEW[$OLD]}  (vhosts: ${DIR_VHOSTS[$OLD]# }; domains: ${DIR_DOMAINS[$OLD]# })"
    echo "     why: ${HOLD_REASON[$OLD]:-?}."
  done
  echo "What is true now: those groups answer from the new release. A domain marked FAIL above is"
  echo "serving exactly what its FAIL line says, in public, until someone acts. Domains marked PASS"
  echo "are fine. Scheduled writers are running again, on the new release."
  echo "From here — docs/MIGRATION-COMPAT.md, \"When a roll that raised the schema version fails\":"
  echo "  - a run at this same release verifies these groups again;"
  echo "  - a roll of another commit (the fix) is not refused. It holds each domain to what it answers"
  echo "    when THAT roll starts, so a domain still failing then reads its repair as a failure and its"
  echo "    group goes back to this release (or stays held, if the fix raises the schema again). Take"
  echo "    that one domain down first and the rest of its group moves to the fix without it."
  NEXT_BASELINE="$RECOVERY_FILE"
  if [ -z "$NEXT_BASELINE" ]; then
    for OLD in "${HELD_GROUPS[@]}"; do
      NEXT_BASELINE="${RECORD[$OLD]:-$(record_of "${DIR_NEW[$OLD]}")}"
      [ -n "$NEXT_BASELINE" ] && break
    done
  fi
  if [ -n "$NEXT_BASELINE" ] && [ "$PROBES" = "1" ]; then
    echo "  - to MEND a domain marked FAIL, the roll that carries the fix has to be told what that domain"
    echo "    SHOULD answer. The baseline this roll held the domains to is on disk, so it can be held to it too:"
    echo "      PP_UPGRADE_BASELINE=$NEXT_BASELINE"
    echo "    It is the right file only if every domain in it was healthy when it was taken"
    echo "    (docs/MIGRATION-COMPAT.md, \"The recovery baseline\")."
  fi
  echo "Send this output back."
  exit 1
fi

say "Done"
if schema_moved; then
  echo "Old release directories stay on disk, but they are NOT a rollback for this roll:"
  schema_moved_lines
  finishing_lines
  not_a_fallback_line
  echo "  The .bak.$STAMP files beside each vhost and cron file are a record of what was there."
  echo "  Copying them back takes those papers down; it does not undo the roll."
  echo "If something is wrong, recovery goes forward: a new roll pinned to a commit built for the"
  echo "stored version, or the operator's database restore. Both are in docs/MIGRATION-COMPAT.md."
  exit 0
fi
if any_schema_moved; then
  echo "Old release directories stay on disk. The schema did not move, but this run's .bak.$STAMP copies"
  echo "are no way back:"
  finishing_lines
  exit 0
fi
# This roll did not move the schema. That makes an old directory a
# rollback only if it is built for the version that is stored. A group
# that came INTO this roll from a release built for another version — the
# box a recovery baseline is for — has nothing behind it: its .bak files
# name the release that was answering 503. Each old release is asked.
NOT_BUILT=()
for OLD in "${!DIR_NEW[@]}"; do
  old_ready "$OLD" || NOT_BUILT+=("$OLD — $OLD_SAYS (vhosts: ${DIR_VHOSTS[$OLD]# })")
done
if [ "${#NOT_BUILT[@]}" -gt 0 ]; then
  echo "Old release directories stay on disk, but these are NOT a rollback. They are not built for the"
  echo "stored schema version, and a vhost pointed at one answers 503:"
  for line in "${NOT_BUILT[@]}"; do echo "  $line"; done
  echo "  Do NOT copy the .bak.$STAMP files of those vhosts back."
  [ "${#NOT_BUILT[@]}" -lt "${#DIR_NEW[@]}" ] || exit 0
  echo "For the other release groups:"
fi
echo "Old release directories stay on disk. Rollback for any release group:"
echo "  cp <vhost>.bak.$STAMP <vhost>   (each of its vhosts)"
echo "  cp <cronfile>.bak.$STAMP <cronfile>   (each of its cron files)"
echo "  nginx -t && systemctl reload nginx"
