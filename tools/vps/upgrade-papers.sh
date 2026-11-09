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

REPO="FTFNAnalytics/prairiepost"
# The branch whose head becomes the new release. Override with PP_BRANCH to
# pin a deploy to a specific line — e.g. to add one paper on top of exactly
# what production already runs, without also shipping unreleased work from
# the control-room branch's head. The default is unchanged.
BRANCH="${PP_BRANCH:-claude/master-dashboard-control-room-nr3mp4}"
SHARED_UP="$WWW/prairiepost-shared-uploads"
STAMP=$(date +%s)

say()  { echo; echo "== $*"; }
fail() { echo "FATAL: $*" >&2; exit 1; }

if [ "$FIXTURE" != "1" ]; then
  [ "$(id -u)" = "0" ] || fail "run as root"
  command -v nginx >/dev/null || fail "nginx not found"
fi

front_title() { # domain -> prints "<code>|<title>"
  local code title
  code=$(curl -sk -m 15 -o /tmp/up_t.html -w "%{http_code}" --resolve "$1:443:127.0.0.1" "https://$1/")
  title=$(grep -o "<title>[^<]*</title>" /tmp/up_t.html | head -1)
  printf '%s|%s' "$code" "$title"
}

front_css() { # domain -> prints the sorted set of /assets/css/*.css the front page links
  curl -sk -m 15 --resolve "$1:443:127.0.0.1" "https://$1/" \
    | grep -o '/assets/css/[a-z0-9._-]*\.css' | sort -u | tr '\n' ' '
}

say "Resolve the branch head"
if [ -n "${PP_UPGRADE_SHA:-}" ]; then
  SHA="${PP_UPGRADE_SHA:0:12}"   # pinned (fixtures, or a pre-verified deploy)
else
  SHA=$(curl -fsSL "https://api.github.com/repos/$REPO/branches/${BRANCH//\//%2F}" \
        | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo substr($d["commit"]["sha"]??"",0,12);')
fi
[ -n "$SHA" ] || fail "couldn't resolve the branch head"
echo "release: $SHA"

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
  DOMAIN=$(grep -Eo 'server_name[[:space:]]+[^;]+' "$VH" | head -1 | awk '{print $2}')
  [ -n "$DOMAIN" ] || { echo "-- $base: no server_name, skipped"; continue; }
  DIR_VHOSTS[$OLD]="${DIR_VHOSTS[$OLD]:-} $base"
  DIR_DOMAINS[$OLD]="${DIR_DOMAINS[$OLD]:-} $DOMAIN"
  echo "-- $base ($DOMAIN) serves from $OLD"
done
[ "${#DIR_VHOSTS[@]}" -gt 0 ] || fail "no papers found"

say "Capture every domain's title BEFORE any change (the tenant baseline)"
declare -A TITLE_BEFORE=()
declare -A CSS_BEFORE=()
declare -A CODE_BEFORE=()
[ "$FIXTURE" = "1" ] && echo "   (fixture: no TLS stack — the post-migration CLI smoke stands in)"
for OLD in "${!DIR_DOMAINS[@]}"; do
  [ "$FIXTURE" = "1" ] && break
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
    fi
  done
done

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
elif curl -fsSL --connect-timeout 25 "https://codeload.github.com/$REPO/tar.gz/refs/heads/$BRANCH" -o "$TMP/rel.tgz"; then
  :
else
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
declare -A DIR_NEW=()
declare -A DIR_CRONS=()
declare -a ALL_VHOST_BACKUPS=()
declare -a ALL_CRON_BACKUPS=()
for OLD in "${!DIR_VHOSTS[@]}"; do
  vhosts=(${DIR_VHOSTS[$OLD]})
  if [ "${#vhosts[@]}" -eq 1 ]; then LABEL="${vhosts[0]}"; else LABEL="shared"; fi
  NEW="$WWW/prairiepost-$SHA-$LABEL"
  if [ "$OLD" = "$NEW" ]; then
    echo "-- ${vhosts[*]}: already on $SHA"
    continue
  fi
  echo "-- ${vhosts[*]}: $OLD -> $NEW"

  if [ ! -d "$NEW" ]; then
    cp -a "$TPL" "$NEW" || { echo "   copy failed, skipped"; continue; }
  fi

  # The original config, VERBATIM — host mapping and every other live
  # decision intact. If a previous run of this script already wrapped it,
  # the true source is app/config.site.php; carry that forward instead.
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
schema_after() { # identity, release dir -> records the stored version now and what happened to it
  local id="$1" state version target before
  read -r state version target <<< "$(schema_stored "$2")"
  before="${SCHEMA_BEFORE[$id]:-}"
  SCHEMA_AFTER[$id]="${version:-}"
  case "${version:-}" in
    ''|*[!0-9]*) SCHEMA_CHANGE[$id]="unknown"; return ;;
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
any_schema_moved() { # true when any schema rose, or may have
  local id
  for id in "${!SCHEMA_CHANGE[@]}"; do
    [ "${SCHEMA_CHANGE[$id]}" = "same" ] || return 0
  done
  return 1
}
any_schema_rose() { # true when a rise was actually read
  local id
  for id in "${!SCHEMA_CHANGE[@]}"; do
    [ "${SCHEMA_CHANGE[$id]}" = "rose" ] && return 0
  done
  return 1
}
group_change() { # OLD dir -> same | rose | unknown, for the schema that group serves from
  local id="${DIR_IDENT[$1]:-}"
  if [ -z "$id" ]; then echo "same"; return; fi
  echo "${SCHEMA_CHANGE[$id]:-same}"
}
nginx_test() { # the configuration test; a fixture reaches this only to fail it
  if [ "$FIXTURE" = "1" ]; then
    echo "   (fixture: nginx test forced to fail)"
    return 1
  fi
  nginx -t
}

say "Read each schema's stored version BEFORE the migration step"
PENDING=0
for OLD in "${!DIR_NEW[@]}"; do
  NEWDIR="${DIR_NEW[$OLD]}"
  IDENT=$(db_identity "$NEWDIR")
  [ -n "$IDENT" ] || continue   # the migration loop below stops on this, as before
  DIR_IDENT[$OLD]="$IDENT"
  [ -z "${SCHEMA_LABEL[$IDENT]:-}" ] || continue
  SCHEMA_LABEL[$IDENT]="$(basename "$NEWDIR")"
  read -r S_STATE S_VERSION S_TARGET <<< "$(schema_stored "$NEWDIR")"
  SCHEMA_BEFORE[$IDENT]="${S_VERSION:-}"
  if [ "${S_STATE:-}" = "ready" ]; then
    echo "   the schema behind $(basename "$NEWDIR"): stored version $S_VERSION — nothing pending"
  elif [ -n "${S_STATE:-}" ]; then
    PENDING=1
    echo "   the schema behind $(basename "$NEWDIR"): $S_STATE — stored version $S_VERSION, this release is built for $S_TARGET"
  else
    PENDING=1
    echo "   the schema behind $(basename "$NEWDIR"): stored version NOT READABLE (php tools/migrate.php --status gave no state line)"
  fi
done

if [ "$PENDING" = "1" ]; then
  say "nginx config test BEFORE the migration (the step is about to change the schema)"
  # The vhost files already name the new release; nginx has not reloaded.
  # A configuration that will not load has to stop the roll HERE, while
  # the old release is still a real fallback. After the step it is not.
  if [ "$FIXTURE" = "1" ] && [ "$FIXTURE_NGINX_FAIL" != "pre" ]; then
    echo "   (fixture: nginx test skipped)"
  elif ! nginx_test; then
    for VH in "${ALL_VHOST_BACKUPS[@]}"; do cp "$VH.bak.$STAMP" "$VH"; done
    for cf in "${ALL_CRON_BACKUPS[@]}"; do cp "$cf.bak.$STAMP" "$cf"; done
    nginx_test
    fail "nginx test failed BEFORE the schema migration — every vhost and cron file restored, the schema was not touched, nothing changed"
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
PAUSED=()
for OLD in "${!DIR_CRONS[@]}"; do
  for cf in ${DIR_CRONS[$OLD]:-}; do
    if [ -f "$cf" ]; then
      mv "$cf" "$cf.paused"
      PAUSED+=("$cf")
      echo "   paused: $(basename "$cf")"
    fi
  done
done
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
  if ! (cd "$NEWDIR" && php tools/migrate.php --apply); then
    schema_after "$IDENT" "$NEWDIR"
    if any_schema_rose; then
      # A step committed before the failure (or an earlier schema was
      # migrated in this run). The old release cannot serve what is
      # stored now, so "roll everything back" would be untrue: nothing
      # is put back, and the message says what state the box is in.
      echo "MIGRATION FAILED — after the stored schema version had already risen; nothing was reloaded." >&2
      schema_moved_lines >&2
      echo "  $NOT_A_FALLBACK." >&2
      echo "  So nothing is put back: the vhost and cron files stay on the new release." >&2
      resume_crons >&2
      fail "the schema migration failed part-way and the old release is not a fallback. Until the migration is finished neither release can serve: the schema is ahead of the old one and not yet complete for the new one. Finish it from the new release — (cd $NEWDIR && php tools/migrate.php --status) names the obstacle, php tools/migrate.php --apply completes it — then: nginx -t && systemctl reload nginx, and check every domain. Do NOT copy the .bak.$STAMP files back (docs/MIGRATION-COMPAT.md)."
    fi
    if [ "${SCHEMA_CHANGE[$IDENT]:-unknown}" != "same" ]; then
      echo "WARNING: the stored schema version could not be read back after the failure (before: ${SCHEMA_BEFORE[$IDENT]:-not readable}, after: ${SCHEMA_AFTER[$IDENT]:-not readable})." >&2
      echo "         What follows assumes it did not change. If it rose, the old release answers 503: check with (cd $NEWDIR && php tools/migrate.php --status)." >&2
    fi
    echo "MIGRATION FAILED — rolling every vhost and cron file back; nothing was reloaded." >&2
    for VH in "${ALL_VHOST_BACKUPS[@]}"; do cp "$VH.bak.$STAMP" "$VH"; done
    for cf in "${PAUSED[@]}"; do rm -f "$cf.paused"; done
    for cf in "${ALL_CRON_BACKUPS[@]}"; do cp "$cf.bak.$STAMP" "$cf"; done
    fail "the schema migration failed; the box still serves the old release. Diagnose with: (cd $NEWDIR && php tools/migrate.php --status)"
  fi
  MIGRATED[$IDENT]=1
  schema_after "$IDENT" "$NEWDIR"
  case "${SCHEMA_CHANGE[$IDENT]}" in
    same) echo "   stored schema version after the step: ${SCHEMA_AFTER[$IDENT]} (unchanged)" ;;
    rose) echo "   stored schema version after the step: ${SCHEMA_AFTER[$IDENT]} — ROSE from ${SCHEMA_BEFORE[$IDENT]:-none}. The old release is no longer a fallback for this schema." ;;
    *)    echo "   WARNING: $(schema_note "$IDENT") — the old release is not offered as a fallback." ;;
  esac
done

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
    echo "  $NOT_A_FALLBACK." >&2
    echo "  nginx is still running its previous configuration (the old release), so that is what the papers get until it loads the new one." >&2
    echo "  The vhost and cron files stay on the new release." >&2
    say "Resume scheduled writers (on the new release — the only one this schema can run)"
    resume_crons
    fail "nginx test failed AFTER the migration changed the schema — NOT restored, and the old release is not a fallback. Fix the nginx error printed above, then: nginx -t && systemctl reload nginx, and check every domain by hand (this run verified none). Do NOT copy the .bak.$STAMP files back (docs/MIGRATION-COMPAT.md)."
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
  systemctl reload nginx
  sleep 2
fi

say "Verify every domain serves 200 AND its own masthead (the baseline title)"
RESTORED=0
hold_group() { # OLD dir — the group failed verification and its schema moved: do NOT put it back
  local old="$1" cf
  echo "     NOT restoring release group ($old): $(schema_note "${DIR_IDENT[$old]:-}")."
  echo "     $NOT_A_FALLBACK —"
  echo "     putting this group back would take down EVERY domain it serves (${DIR_DOMAINS[$old]# }), not only the one that failed."
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
for OLD in "${!DIR_NEW[@]}"; do
  group_ok=1
  DOMAINS_TO_CHECK="${DIR_DOMAINS[$OLD]}"
  if [ "$FIXTURE" = "1" ]; then
    # No TLS stack in a fixture: the post-migration smoke is a CLI boot of
    # the candidate against its real config — the readiness gate plus the
    # tenant resolution ARE the candidate behaving.
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
    if [ "$code" != "$want_code" ]; then
      echo "FAIL $DOMAIN answered $code, served $want_code before"
      group_ok=0
    elif [ "$code" != "200" ]; then
      echo "PASS $DOMAIN -> $code (unchanged)"
    elif [ -n "$expected" ] && [ "$title" != "$expected" ]; then
      echo "FAIL $DOMAIN wrong tenant — got $title, expected $expected"
      group_ok=0
    else
      css_now="$(front_css "$DOMAIN")"
      css_was="${CSS_BEFORE[$DOMAIN]:-}"
      if [ -n "$css_was" ] && [ "$css_now" != "$css_was" ]; then
        # The database supplies the title, so a release missing this paper's
        # template tree still answers 200 with the right masthead. The
        # stylesheet set is served from the release itself — losing it is
        # template loss, and template loss rolls back like a wrong tenant.
        echo "FAIL $DOMAIN stylesheet set changed — was [$css_was] now [$css_now]"
        group_ok=0
      else
        missing=""
        for f in $css_now; do
          [ -f "${DIR_NEW[$OLD]}$f" ] || missing="$missing $f"
        done
        if [ -n "$missing" ]; then
          echo "FAIL $DOMAIN references stylesheets absent from the new release:$missing"
          group_ok=0
        else
          echo "PASS $DOMAIN -> ${title:-200}"
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
    RESTORED=1
  fi
done
if [ "$RESTORED" = "1" ]; then
  [ "$FIXTURE" = "1" ] || { nginx -t && systemctl reload nginx; }
  # Restored groups got their OLD cron files back from .bak above; clear
  # any paused copies so nothing runs twice.
  for cf in "${PAUSED[@]}"; do rm -f "$cf.paused"; done
  echo "WARN: at least one release group was restored — send this output back for a look."
else
  say "Resume scheduled writers"
  resume_crons
fi

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

if [ "${#HELD_GROUPS[@]}" -gt 0 ]; then
  say "NOT done — verification failed, and there is no release to go back to"
  echo "Held on the new release, NOT restored:"
  for OLD in "${HELD_GROUPS[@]}"; do
    echo "  ${DIR_NEW[$OLD]}  (vhosts: ${DIR_VHOSTS[$OLD]# }; domains: ${DIR_DOMAINS[$OLD]# })"
  done
  schema_moved_lines
  echo "  $NOT_A_FALLBACK."
  echo "What is true now: those groups answer from the new release. A domain marked FAIL above is"
  echo "serving exactly what its FAIL line says, in public, until someone acts. Domains marked PASS"
  echo "are fine. Scheduled writers are running again, on the new release."
  echo "Do NOT copy the .bak.$STAMP files back for a group: that is 503 on every domain it serves."
  echo "Send this output back. The choices from here — leave the domain up while a fix is rolled,"
  echo "or take that one domain down — are in docs/MIGRATION-COMPAT.md, \"When a roll that raised"
  echo "the schema version fails\"."
  exit 1
fi

say "Done"
if any_schema_moved; then
  echo "Old release directories stay on disk, but they are NOT a rollback for this roll:"
  schema_moved_lines
  echo "  $NOT_A_FALLBACK."
  echo "  The .bak.$STAMP files beside each vhost and cron file are a record of what was there."
  echo "  Copying them back takes those papers down; it does not undo the roll."
  echo "If something is wrong, recovery goes forward: a new roll pinned to a commit built for the"
  echo "stored version, or the operator's database restore. Both are in docs/MIGRATION-COMPAT.md."
  exit 0
fi
echo "Old release directories stay on disk. Rollback for any release group:"
echo "  cp <vhost>.bak.$STAMP <vhost>   (each of its vhosts)"
echo "  cp <cronfile>.bak.$STAMP <cronfile>   (each of its cron files)"
echo "  nginx -t && systemctl reload nginx"
