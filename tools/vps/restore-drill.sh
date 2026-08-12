#!/usr/bin/env bash
#
# The restore drill — prove last night's backup actually restores.
#
#   curl -fsSL <raw url> | bash            # newest dump
#   bash restore-drill.sh /path/to/db.dump # a specific one
#
# NEVER touches the live database. It restores the dump into a scratch
# database on a LOCAL Postgres server on this box (installed if missing —
# it listens on localhost only, Ubuntu's default), runs assertions a
# newsroom cares about, prints what it found, and drops the scratch
# database again. Run it after setup, and roughly monthly after that.
#
set -uo pipefail
fail() { echo "FATAL: $*" >&2; exit 1; }
[ "$(id -u)" = "0" ] || fail "run as root"

DUMP="${1:-$(ls -1t /var/backups/civis/daily/db-*.dump 2>/dev/null | head -1)}"
[ -n "$DUMP" ] && [ -f "$DUMP" ] || fail "no dump found — run setup-backups.sh first (or pass a dump path)"
echo "drilling with: $DUMP ($(stat -c%s "$DUMP" | numfmt --to=iec 2>/dev/null || stat -c%s "$DUMP"))"

if ! command -v psql >/dev/null || ! id postgres >/dev/null 2>&1; then
  echo "local Postgres server not present — installing (localhost-only, Ubuntu default)"
  export DEBIAN_FRONTEND=noninteractive
  apt-get install -y postgresql >/dev/null || fail "couldn't install postgresql"
fi
systemctl start postgresql 2>/dev/null || service postgresql start 2>/dev/null || true
sudo -u postgres psql -tAc 'SELECT 1' >/dev/null 2>&1 || fail "local Postgres isn't answering"

DRILLDB="civis_drill_$(date +%s)"
# The dump is root-only (600); pg_restore runs as postgres. Stage a copy it
# can read, and remove it again no matter how the drill ends.
STAGED=$(mktemp /tmp/civis-drill-XXXXXX.dump)
cleanup() {
  sudo -u postgres dropdb --if-exists "$DRILLDB" 2>/dev/null || true
  rm -f "$STAGED"
}
trap cleanup EXIT
cp "$DUMP" "$STAGED" && chmod 644 "$STAGED"

echo "restoring into scratch database $DRILLDB (the live database is not involved)"
sudo -u postgres createdb "$DRILLDB" || fail "couldn't create the scratch database"
if ! sudo -u postgres pg_restore --no-owner --no-privileges -d "$DRILLDB" "$STAGED" 2>/tmp/civis-drill.err; then
  # pg_restore returns non-zero on ignorable warnings too — real failures
  # leave the tables missing, which the assertions below catch. Show the
  # first lines either way.
  echo "pg_restore reported issues (may be ignorable):"; head -5 /tmp/civis-drill.err
fi

Q() { sudo -u postgres psql -d "$DRILLDB" -tAc "$1" 2>/dev/null; }
SCHEMA=$(Q "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('public','information_schema') AND schema_name NOT LIKE 'pg_%' LIMIT 1")
[ -n "$SCHEMA" ] || fail "DRILL FAILED — no application schema restored at all"
echo "schema restored: $SCHEMA"

PASS=1
check() { # label value minimum
  if [ -z "$2" ] || [ "$2" -lt "$3" ] 2>/dev/null; then
    echo "FAIL  $1 = ${2:-<none>} (wanted >= $3)"; PASS=0
  else
    echo "ok    $1 = $2"
  fi
}
check "sites"        "$(Q "SELECT COUNT(*) FROM \"$SCHEMA\".sites")" 1
check "users"        "$(Q "SELECT COUNT(*) FROM \"$SCHEMA\".users")" 1
check "posts"        "$(Q "SELECT COUNT(*) FROM \"$SCHEMA\".posts")" 0
check "entities"     "$(Q "SELECT COUNT(*) FROM \"$SCHEMA\".entities")" 0
check "settings"     "$(Q "SELECT COUNT(*) FROM \"$SCHEMA\".settings")" 1
echo "info  schema_version = $(Q "SELECT svalue FROM \"$SCHEMA\".settings WHERE site_id = 0 AND skey = 'schema_version'")"
echo "info  newest story   = $(Q "SELECT title || ' (' || COALESCE(published_at::text, 'unpublished') || ')' FROM \"$SCHEMA\".posts ORDER BY id DESC LIMIT 1")"
echo "info  newest audit   = $(Q "SELECT action || ' by ' || user_name || ' @ ' || created_at FROM \"$SCHEMA\".audit_log ORDER BY id DESC LIMIT 1")"

echo
if [ "$PASS" = "1" ]; then
  echo "DRILL PASSED — this dump restores into a working database."
  echo "(Scratch database $DRILLDB dropped. The live database was never touched.)"
else
  echo "DRILL FAILED — the backup does NOT restore cleanly. Treat this as an outage"
  echo "of the safety net and investigate today: /var/log/civis/backup.log, then"
  echo "re-run /usr/local/bin/civis-backup.sh and drill again."
  exit 1
fi
