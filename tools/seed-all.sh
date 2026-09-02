#!/usr/bin/env bash
#
# Build the full-network test database from scratch, every launch pack in
# production launch order, and FAIL on any skipped line — a skip means a
# network-wide slug collision or a desk dependency, which is a red X at
# PR time or a hard stop on a live server; there is no third reading.
#
#   tools/seed-all.sh [TARGET.sqlite]     default: data/test.sqlite
#
# Runs against a throwaway config pointing at the target file; the repo's
# own config.php is never touched. Safe to run any number of times — the
# target is deleted and rebuilt.
#
set -uo pipefail
cd "$(dirname "$0")/.."
ROOT=$(pwd)

TARGET="${1:-$ROOT/data/test.sqlite}"
case "$TARGET" in /*) : ;; *) TARGET="$ROOT/$TARGET" ;; esac

# Launch order matters: desks are shared network-wide and the first pack
# to name one owns its description, so a fresh database must apply packs
# in the order production did. New papers append at the end.
ORDER="prairiedispatch grande-prairie-gazette edmonton-echo kermode-chronicle
pacific-post kelowna-current brampton-bulletin westernwire civismedia
tri-cities-torch sudbury-standard turtle-island-times pickering-post
bleuet-blanc mississauga-monitor kitchener-chronicle london-lookout"

# Any pack directory not in the order list runs after them, alphabetically,
# so adding a paper cannot silently fall out of this harness.
EXTRA=""
for d in "$ROOT"/assets/sites/*/; do
  slug=$(basename "$d")
  case " $(echo $ORDER) " in *" $slug "*) : ;; *) EXTRA="$EXTRA $slug" ;; esac
done
[ -n "$EXTRA" ] && echo "note: packs outside the launch-order list, appended:$EXTRA"

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$(dirname "$TARGET")"
rm -f "$TARGET"

cat > "$WORK/config.php" <<PHP
<?php
return ['db' => ['driver' => 'sqlite', 'sqlite_path' => '$TARGET'],
        'site_slug' => 'prairiedispatch', 'hub_slug' => 'civismedia',
        'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];
PHP

FAILED=0
for slug in $ORDER $EXTRA; do
  [ -d "$ROOT/assets/sites/$slug" ] || { echo "-- $slug: no pack directory, skipped from the loop"; continue; }
  OUT=$(PP_CONFIG="$WORK/config.php" PP_SITE="$slug" php "$ROOT/tools/seed-launch.php" 2>&1) || { echo "$OUT"; echo "FAIL $slug: seeder exited nonzero"; FAILED=1; continue; }
  BAD=$(echo "$OUT" | grep -iE "skipped|conflict|error|fatal" || true)
  if [ -n "$BAD" ]; then
    echo "FAIL $slug:"; echo "$BAD" | sed 's/^/    /'
    FAILED=1
  else
    echo "ok   $slug ($(echo "$OUT" | grep -c '^  ') detail line(s)))"
  fi
done

if [ "$FAILED" = "1" ]; then
  echo; echo "The network database did NOT build cleanly. A skipped line means a"
  echo "slug collision or a missing desk — fix the pack, not the check."
  exit 1
fi
echo; echo "Full network seeded cleanly into $TARGET"
