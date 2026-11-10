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
# "Serve afterwards what it served before" cannot mend a domain that was
# already failing when the roll started: its repair is a change, and
# reads as a failure. So every roll SAVES the baseline it captured, and a
# later roll can be told to hold domains to one of those files instead of
# to what they answer when it starts:
#     PP_UPGRADE_BASELINE=/var/lib/prairiepost-upgrade/baseline-<stamp>.txt
# Nothing reads a saved baseline unless that variable names it — a roll
# without it behaves exactly as before — and the output says, for every
# domain, which baseline it was held to. docs/MIGRATION-COMPAT.md, "The
# recovery baseline".
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

# Where each roll saves the baseline it captured (one small text file per
# roll, written before anything changes, read by nothing), and the one an
# operator may name to hold THIS roll to instead of to what the domains
# answer when it starts. PP_UPGRADE_BASELINE_DIR is for the fixture.
BASELINE_DIR="${PP_UPGRADE_BASELINE_DIR:-/var/lib/prairiepost-upgrade}"
RECOVERY_FILE="${PP_UPGRADE_BASELINE:-}"
declare -A REC_CODE=()    # the named baseline's records: domain -> status code,
declare -A REC_TITLE=()   #   -> <title> (kept only for a 200),
declare -A REC_CSS=()     #   -> stylesheet set (likewise)
REC_SAYS=()               # the file's own opening comment lines, printed where it is applied
BAD="PP_UPGRADE_BASELINE ($RECOVERY_FILE)"
if [ -n "$RECOVERY_FILE" ]; then
  [ "$PROBES" = "1" ] || fail "PP_UPGRADE_BASELINE is set but this fixture has no HTTP stand-in to probe — nothing changed"
  [ -f "$RECOVERY_FILE" ] && [ -r "$RECOVERY_FILE" ] || fail "PP_UPGRADE_BASELINE names a file that cannot be read: $RECOVERY_FILE — nothing changed"
  # Read and checked HERE, before the roll resolves, probes or writes
  # anything, so that "nothing changed" is true of every refusal below.
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
  code=$(front_page "$1" -o /tmp/up_t.html -w "%{http_code}")
  title=$(grep -o "<title>[^<]*</title>" /tmp/up_t.html | head -1)
  printf '%s|%s' "$code" "$title"
}

front_css() { # domain -> prints the sorted set of /assets/css/*.css the front page links
  front_page "$1" \
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
      if [ "$code" = "503" ] && grep -q "This site is being updated" /tmp/up_t.html 2>/dev/null; then
        GATE_BEFORE[$DOMAIN]=1
      fi
    fi
  done
done

# ---------------------------------------------------------------------
# The recovery baseline.
#
# The capture above is what every domain is held to: "serve afterwards
# what it served before". That is the right rule for a healthy box and
# the wrong one for a domain that was ALREADY failing when this roll
# started — answering the gate's 503 because its vhost names a release
# built for another schema version, or held on a release that lost its
# stylesheet. There the repair is a change, the guard reads it as a
# failure, and the group goes back where it came from.
#
# The script cannot know what such a domain SHOULD answer. An earlier
# roll did capture it, while the domain was healthy. So:
#   - every roll saves its capture to $BASELINE_DIR, before anything
#     changes. The file is a record. Nothing reads it on its own;
#   - PP_UPGRADE_BASELINE=<one of those files> holds each domain the
#     file names to the file's record instead: status code, <title> and
#     stylesheet set, the same three things and the same comparison. A
#     domain the file does not name is held to what it answers now.
# Only the reference changes. What a failing group gets — restored, or
# held after a rise — is exactly what it gets on any other roll.
# ---------------------------------------------------------------------
answer_words() { # status code, title, stylesheet set -> that answer on one line
  if [ "$1" = "200" ] && [ -n "$2" ]; then
    printf '200 %s [%s]' "$2" "$3"
  else
    printf '%s' "${1:-no answer}"
  fi
}
all_domains() { # every domain behind a prairiepost vhost, one per line, sorted
  local old domain
  for old in "${!DIR_DOMAINS[@]}"; do
    for domain in ${DIR_DOMAINS[$old]}; do echo "$domain"; done
  done | LC_ALL=C sort
}
suggest_baseline() { # domains... -> names the newest EARLIER saved baseline in which any of them answered 200 with a title
  local files=() f d n i
  for f in "$BASELINE_DIR"/baseline-*.txt; do
    [ -f "$f" ] && [ "$f" != "$BASELINE_FILE" ] && files+=("$f")
  done
  for ((i = ${#files[@]} - 1; i >= 0; i--)); do
    n=0
    for d in "$@"; do
      grep -q "^$d|200|[^|]*|<title>" "${files[$i]}" 2>/dev/null && n=$((n + 1))
    done
    if [ "$n" -gt 0 ]; then
      echo "         The newest of them in which these answered 200 with a title ($n of $#): ${files[$i]}"
      return 0
    fi
  done
  echo "         None of them records a 200 with a title for these domains."
}

if [ -n "$RECOVERY_FILE" ]; then
  # Still before anything is written: a file that names none of this
  # box's domains is the wrong file.
  named=0
  for DOMAIN in $(all_domains); do
    [ -n "${REC_CODE[$DOMAIN]:-}" ] && named=$((named + 1))
  done
  [ "$named" -gt 0 ] || fail "$BAD names none of this box's domains — nothing changed"
fi

BASELINE_FILE=""   # where this roll's own capture is saved
if [ "$PROBES" = "1" ]; then
  BASELINE_FILE="$BASELINE_DIR/baseline-$STAMP.txt"
  # A saved baseline is never overwritten: it may be the only record of
  # what a domain answered while it was healthy.
  [ -e "$BASELINE_FILE" ] && BASELINE_FILE="$BASELINE_DIR/baseline-$STAMP-$$.txt"
  if mkdir -p "$BASELINE_DIR" 2>/dev/null && {
      echo "# prairiepost upgrade baseline: what each domain's front page answered BEFORE the roll that wrote this file changed anything."
      echo "# written $(date -u +%Y-%m-%dT%H:%M:%SZ) (stamp $STAMP) by upgrade-papers.sh, rolling to $SHA"
      echo "# one line per domain:  domain|status code|stylesheet set|<title>   (the title and the stylesheet set are kept only for a 200)"
      for DOMAIN in $(all_domains); do
        printf '%s|%s|%s|%s\n' "$DOMAIN" "${CODE_BEFORE[$DOMAIN]:-}" "${CSS_BEFORE[$DOMAIN]:-}" "${TITLE_BEFORE[$DOMAIN]:-}"
      done
    } 2>/dev/null > "$BASELINE_FILE"; then
    echo "   saved: $BASELINE_FILE (this roll's baseline, as captured above; nothing reads it unless a later roll is told to)"
  else
    echo "   WARNING: this roll's baseline could not be saved to $BASELINE_FILE. The roll goes on: the file is only a record for a later recovery roll."
    BASELINE_FILE=""
  fi
fi

declare -A BASE_SRC=()   # domain -> "recovery" when it is held to the named file and not to its own answer
declare -A RB_TAG=()     # domain -> the words its PASS/FAIL line carries when it is
RECOVERY_HELD=""         # the domains held to the recovery baseline
RECOVERY_UNLISTED=""     # the domains the file does not name: held to what they answered at the start
if [ -n "$RECOVERY_FILE" ]; then
  say "Recovery baseline (PP_UPGRADE_BASELINE): hold domains to $RECOVERY_FILE"
  for line in "${REC_SAYS[@]}"; do echo "   the file says: $line"; done
  differs=0
  for DOMAIN in $(all_domains); do
    live="$(answer_words "${CODE_BEFORE[$DOMAIN]:-}" "${TITLE_BEFORE[$DOMAIN]:-}" "${CSS_BEFORE[$DOMAIN]:-}")"
    if [ -z "${REC_CODE[$DOMAIN]:-}" ]; then
      RECOVERY_UNLISTED="$RECOVERY_UNLISTED $DOMAIN"
      echo "   $DOMAIN: NOT in that file — held to what it answers now: $live"
      continue
    fi
    rec="$(answer_words "${REC_CODE[$DOMAIN]}" "${REC_TITLE[$DOMAIN]}" "${REC_CSS[$DOMAIN]}")"
    BASE_SRC[$DOMAIN]="recovery"
    RECOVERY_HELD="$RECOVERY_HELD $DOMAIN"
    if [ "$rec" = "$live" ]; then
      RB_TAG[$DOMAIN]=" [recovery baseline, which is what it answered when this roll started]"
      echo "   $DOMAIN: held to the RECOVERY baseline: $rec — which is what it answers now"
    else
      differs=$((differs + 1))
      RB_TAG[$DOMAIN]=" [recovery baseline; when this roll started it answered $live]"
      echo "   $DOMAIN: held to the RECOVERY baseline: $rec"
      echo "      and NOT to what it answers now: $live"
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
    [ -n "${CODE_BEFORE[$r_domain]:-}" ] || echo "   (the file also names $r_domain, which no prairiepost vhost here serves: ignored)"
  done
  echo "   Held to the recovery baseline:$RECOVERY_HELD ($differs of them to something other than what they answer now)."
  echo "   Held to what they answer now:${RECOVERY_UNLISTED:- none}."
fi

# Said once, before anything changes: a domain on the gate's 503 that is
# NOT covered by a recovery baseline cannot be mended by this roll.
GATE_HELD=""
for DOMAIN in $(all_domains); do
  if [ -n "${GATE_BEFORE[$DOMAIN]:-}" ] && [ -z "${BASE_SRC[$DOMAIN]:-}" ]; then
    GATE_HELD="$GATE_HELD $DOMAIN"
  fi
done
if [ -n "$GATE_HELD" ]; then
  echo "   NOTE: the 503 from these is the readiness gate's own page (\"This site is being updated\"):$GATE_HELD"
  echo "         The release answering for them does not match the stored schema version."
  echo "         This roll holds each of them to that 503, as it holds every domain to what it answered at the start:"
  echo "         200 afterwards will read as a FAIL for them."
  echo "         If 200 is what they should answer, name a baseline saved when they did: PP_UPGRADE_BASELINE=<file>"
  echo "         (docs/MIGRATION-COMPAT.md, \"The recovery baseline\"). Saved baselines are in $BASELINE_DIR."
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
      echo "WARNING: the stored schema version was not readable both before and after the failed step (before: ${SCHEMA_BEFORE[$IDENT]:-not readable}, after: ${SCHEMA_AFTER[$IDENT]:-not readable})." >&2
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
  [ "$PROBES" = "1" ] || return 0
  echo "A roll holds each domain to what it answered when the roll STARTED. If a domain marked FAIL was"
  echo "already failing then, its FAIL may be the repair itself. A roll can be held to a baseline saved"
  echo "while the domain was healthy instead: PP_UPGRADE_BASELINE=<file> on the same roll"
  echo "(docs/MIGRATION-COMPAT.md, \"The recovery baseline\"). Saved baselines are in $BASELINE_DIR."
  if [ -n "$RECOVERY_FILE" ]; then
    echo "This roll was held to: $RECOVERY_FILE"
  elif [ -n "$BASELINE_FILE" ]; then
    echo "This roll's own, taken before it changed anything: $BASELINE_FILE"
  fi
}
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
    # A domain held to the recovery baseline says so on its own line,
    # with what it answered when this roll started. For every other
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
    RESTORED=1
  fi
done
if [ "$RESTORED" = "1" ]; then
  [ "$FIXTURE" = "1" ] || { nginx -t && systemctl reload nginx; }
  # Restored groups got their OLD cron files back from .bak above; clear
  # any paused copies so nothing runs twice.
  for cf in "${PAUSED[@]}"; do rm -f "$cf.paused"; done
  echo "WARN: at least one release group was restored — send this output back for a look."
  recovery_pointer
else
  say "Resume scheduled writers"
  resume_crons
fi
if [ -n "$RECOVERY_FILE" ]; then
  say "Which baseline each domain was held to"
  echo "  the recovery baseline $RECOVERY_FILE:${RECOVERY_HELD:- none}"
  echo "  what it answered when this roll started:${RECOVERY_UNLISTED:- none}"
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
  NEXT_BASELINE="${RECOVERY_FILE:-$BASELINE_FILE}"
  if [ -n "$NEXT_BASELINE" ]; then
    echo "The roll that fixes a FAIL above has to be told what that domain SHOULD answer. Left alone it takes"
    echo "the failing answer as its baseline, reads the fix as a failure and puts it back. The baseline this"
    echo "roll held the domains to is on disk, so the next roll can be held to it as well:"
    echo "  PP_UPGRADE_BASELINE=$NEXT_BASELINE"
    echo "It is the right file only if every domain in it was healthy when it was taken"
    echo "(docs/MIGRATION-COMPAT.md, \"The recovery baseline\")."
  fi
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
