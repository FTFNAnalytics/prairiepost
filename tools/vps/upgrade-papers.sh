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
# Also creates the network's shared /uploads directory, merged from every
# release and symlinked in, so campaign creatives and syndicated images
# resolve on every domain.
#
# Run as root:  curl -fsSL <raw url> | bash
#
set -uo pipefail

REPO="FTFNAnalytics/prairiepost"
# The branch whose head becomes the new release. Override with PP_BRANCH to
# pin a deploy to a specific line — e.g. to add one paper on top of exactly
# what production already runs, without also shipping unreleased work from
# the control-room branch's head. The default is unchanged.
BRANCH="${PP_BRANCH:-claude/master-dashboard-control-room-nr3mp4}"
SHARED_UP="/var/www/prairiepost-shared-uploads"
STAMP=$(date +%s)

say()  { echo; echo "== $*"; }
fail() { echo "FATAL: $*" >&2; exit 1; }

[ "$(id -u)" = "0" ] || fail "run as root"
command -v nginx >/dev/null || fail "nginx not found"

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
SHA=$(curl -fsSL "https://api.github.com/repos/$REPO/branches/${BRANCH//\//%2F}" \
      | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo substr($d["commit"]["sha"]??"",0,12);')
[ -n "$SHA" ] || fail "couldn't resolve the branch head"
echo "release: $SHA"

say "Map the papers behind nginx (a release dir may serve several vhosts)"
declare -A DIR_VHOSTS=()   # OLD dir -> "vhostbase vhostbase..."
declare -A DIR_DOMAINS=()  # OLD dir -> "domain domain..."
for link in /etc/nginx/sites-enabled/*; do
  base=$(basename "$link")
  # cies is the Institute — a different application. The hub IS a paper's
  # release (one site row, same code), so it rolls forward with them.
  case "$base" in cies|README|default) continue ;; esac
  VH=$(readlink -f "$link")
  [ -f "$VH" ] || continue
  OLD=$(grep -Eo 'root[[:space:]]+/var/www/prairiepost-[A-Za-z0-9._-]+' "$VH" | head -1 | awk '{print $2}')
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
for OLD in "${!DIR_DOMAINS[@]}"; do
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

say "Build the shared uploads directory (merging every release's images)"
mkdir -p "$SHARED_UP"
for d in /var/www/prairiepost-*/; do
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
  NEW="/var/www/prairiepost-$SHA-$LABEL"
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
    VH=$(readlink -f "/etc/nginx/sites-enabled/$base")
    cp "$VH" "$VH.bak.$STAMP"
    ALL_VHOST_BACKUPS+=("$VH")
    sed -i "s|root[[:space:]]\+$OLD;|root $NEW;|" "$VH"
    grep -q "root $NEW;" "$VH" || { cp "$VH.bak.$STAMP" "$VH"; echo "   $base: vhost rewrite failed, restored"; ok=0; }
  done
  [ "$ok" = "1" ] || continue

  for cf in /etc/cron.d/*; do
    [ -f "$cf" ] || continue
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

say "nginx config test + reload"
if ! nginx -t; then
  for VH in "${ALL_VHOST_BACKUPS[@]}"; do cp "$VH.bak.$STAMP" "$VH"; done
  for cf in "${ALL_CRON_BACKUPS[@]}"; do cp "$cf.bak.$STAMP" "$cf"; done
  nginx -t
  fail "nginx test failed — every vhost and cron file restored, nothing changed"
fi
systemctl reload nginx
sleep 2

say "Verify every domain serves 200 AND its own masthead (the baseline title)"
RESTORED=0
for OLD in "${!DIR_NEW[@]}"; do
  group_ok=1
  for DOMAIN in ${DIR_DOMAINS[$OLD]}; do
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
  if [ "$group_ok" = "0" ]; then
    echo "     restoring release group ($OLD): vhosts and cron files"
    for base in ${DIR_VHOSTS[$OLD]}; do
      VH=$(readlink -f "/etc/nginx/sites-enabled/$base")
      cp "$VH.bak.$STAMP" "$VH"
    done
    for cf in ${DIR_CRONS[$OLD]:-}; do
      cp "$cf.bak.$STAMP" "$cf"
    done
    RESTORED=1
  fi
done
if [ "$RESTORED" = "1" ]; then
  nginx -t && systemctl reload nginx
  echo "WARN: at least one release group was restored — send this output back for a look."
fi

say "Fallback: the hub's uploads, if it was skipped above (no config.php)"
HUBVH=$(readlink -f /etc/nginx/sites-enabled/civismedia 2>/dev/null || true)
if [ -n "$HUBVH" ] && [ -f "$HUBVH" ]; then
  HUBROOT=$(grep -Eo 'root[[:space:]]+/var/www/prairiepost-[A-Za-z0-9._-]+' "$HUBVH" | head -1 | awk '{print $2}')
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

say "Done"
echo "Old release directories stay on disk. Rollback for any release group:"
echo "  cp <vhost>.bak.$STAMP <vhost>   (each of its vhosts)"
echo "  cp <cronfile>.bak.$STAMP <cronfile>   (each of its cron files)"
echo "  nginx -t && systemctl reload nginx"
