#!/usr/bin/env bash
#
# Set up nightly backups for the Civis Media network — run once, as root.
#
#   curl -fsSL <raw url> | bash
#
# Installs:
#   /usr/local/bin/civis-backup.sh   the nightly job (root, 700)
#   /etc/cron.d/civis-backup         03:17 daily
#   /var/backups/civis/{daily,weekly}  rotation: 7 daily + 4 weekly
#   /var/backups/civis/state.json    what the hub dashboard reads (no secrets)
#
# What gets backed up, every night:
#   - the shared Postgres database (pg_dump of the app's schema, custom format)
#   - /var/www/prairiepost-shared-uploads (every photo on all nine sites)
#   - box state: vhosts, cron files, each release's config.php (mode 600 tar)
#
# The database password is read from the hub's generated config at RUN time
# and handed to pg_dump via the environment — it is never written into this
# script, the job script, argv, or any log. Only the hub's config is ever
# evaluated, grep-guarded for the literal civismedia slug, same as every
# other tool here.
#
# Off-site copies: if an executable exists at /etc/civis-backup-offsite it
# is called after a successful backup with the night's files as arguments —
# drop in a two-line rclone/rsync script whenever an off-site target exists.
# Local rotation runs either way.
#
set -uo pipefail
fail() { echo "FATAL: $*" >&2; exit 1; }

[ "$(id -u)" = "0" ] || fail "run as root"

say() { echo; echo "== $* =="; }

say "Resolving the hub's release and config"
VH=$(readlink -f /etc/nginx/sites-enabled/civismedia 2>/dev/null) || fail "no civismedia vhost enabled"
ROOT=$(grep -Eo 'root[[:space:]]+/var/www/prairiepost-[A-Za-z0-9._-]+' "$VH" | head -1 | awk '{print $2}')
[ -n "$ROOT" ] && [ -d "$ROOT" ] || fail "couldn't resolve the hub release from the vhost"
CFG="$ROOT/config.php"
[ -f "$CFG" ] || fail "no config.php at $ROOT"
grep -q "'site_slug' => 'civismedia'" "$CFG" || fail "$CFG isn't the generated hub config — not touching it"
echo "hub config: $CFG"

DRIVER=$(php -r '$c = require $argv[1]; echo $c["db"]["driver"] ?? "";' "$CFG")
[ "$DRIVER" = "pgsql" ] || fail "db driver is '$DRIVER' — this backup job is built for the shared Postgres database"

say "Checking pg_dump against the server's Postgres version"
SERVER_MAJOR=$(php -r '
$c = require $argv[1]; $p = $c["db"]["pgsql"];
try {
  $pdo = new PDO(sprintf("pgsql:host=%s;port=%d;dbname=%s;sslmode=%s", $p["host"], (int)($p["port"] ?? 5432), $p["name"] ?? "postgres", $p["sslmode"] ?? "require"), $p["user"], $p["pass"], [PDO::ATTR_TIMEOUT => 10]);
  echo (int) explode(".", (string) $pdo->query("SHOW server_version")->fetchColumn())[0];
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
' "$CFG") || fail "couldn't reach the database to read its version"
echo "server: Postgres $SERVER_MAJOR"

CLIENT_MAJOR=0
command -v pg_dump >/dev/null && CLIENT_MAJOR=$(pg_dump --version | grep -Eo '[0-9]+' | head -1)
if [ "${CLIENT_MAJOR:-0}" -lt "$SERVER_MAJOR" ]; then
  echo "pg_dump ${CLIENT_MAJOR:-absent} < server $SERVER_MAJOR — installing postgresql-client-$SERVER_MAJOR"
  export DEBIAN_FRONTEND=noninteractive
  apt-get install -y "postgresql-client-$SERVER_MAJOR" 2>/dev/null || {
    echo "not in Ubuntu's repos — adding the PostgreSQL apt repository (pgdg)"
    apt-get install -y curl ca-certificates gnupg lsb-release >/dev/null
    install -d /usr/share/postgresql-common/pgdg
    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc
    echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" \
      > /etc/apt/sources.list.d/pgdg.list
    apt-get update -qq
    apt-get install -y "postgresql-client-$SERVER_MAJOR" || fail "couldn't install postgresql-client-$SERVER_MAJOR"
  }
fi
# Prefer the exact-major binary if the default is older.
PGDUMP=$(command -v "/usr/lib/postgresql/$SERVER_MAJOR/bin/pg_dump" || command -v pg_dump)
echo "using: $PGDUMP ($("$PGDUMP" --version))"

say "Installing the nightly job"
install -d -m 755 /var/backups/civis /var/backups/civis/daily /var/backups/civis/weekly /var/log/civis

cat > /usr/local/bin/civis-backup.sh <<'EOS'
#!/usr/bin/env bash
# Civis Media nightly backup — installed by setup-backups.sh. Runs as root.
set -uo pipefail
DIR=/var/backups/civis
STAMP=$(date +%F)
STATE_TMP=$(mktemp)
finish() { # ok reason db_bytes up_bytes
  # finished_epoch is what readers should trust — the box's clock and the
  # app's timezone (America/Edmonton) don't have to agree for it to work.
  printf '{"ok": %s, "finished_at": "%s", "finished_epoch": %s, "reason": "%s", "db_bytes": %s, "uploads_bytes": %s}\n' \
    "$1" "$(date '+%F %T')" "$(date +%s)" "$2" "${3:-0}" "${4:-0}" > "$STATE_TMP"
  chmod 644 "$STATE_TMP" && mv "$STATE_TMP" "$DIR/state.json"
  [ "$1" = "true" ] || exit 1
}

VH=$(readlink -f /etc/nginx/sites-enabled/civismedia 2>/dev/null) || finish false "no civismedia vhost"
ROOT=$(grep -Eo 'root[[:space:]]+/var/www/prairiepost-[A-Za-z0-9._-]+' "$VH" | head -1 | awk '{print $2}')
CFG="$ROOT/config.php"
[ -f "$CFG" ] || finish false "no hub config at $ROOT"
grep -q "'site_slug' => 'civismedia'" "$CFG" || finish false "config guard failed"

# Read connection fields; the password rides the environment into pg_dump only.
IFS=$'\t' read -r PGH PGP PGDB PGU PGPASS PGSCHEMA < <(php -r '
$c = require $argv[1]; $p = $c["db"]["pgsql"];
echo implode("\t", [$p["host"], (string)($p["port"] ?? 5432), $p["name"] ?? "postgres", $p["user"], $p["pass"], $p["schema"] ?? "prairiedispatch"]), "\n";
' "$CFG") || finish false "couldn't read db config"
[ -n "${PGH:-}" ] && [ -n "${PGU:-}" ] || finish false "db config came back empty"

PGDUMP_BIN=%%PGDUMP%%
DBFILE="$DIR/daily/db-$STAMP.dump"
if ! PGPASSWORD="$PGPASS" "$PGDUMP_BIN" -h "$PGH" -p "$PGP" -U "$PGU" -d "$PGDB" \
     --schema="$PGSCHEMA" -Fc --no-owner -f "$DBFILE" 2>>/var/log/civis/backup.log; then
  finish false "pg_dump failed — see backup.log"
fi
chmod 600 "$DBFILE"
DB_BYTES=$(stat -c%s "$DBFILE")

UP_BYTES=0
if [ -d /var/www/prairiepost-shared-uploads ]; then
  UPFILE="$DIR/daily/uploads-$STAMP.tar.gz"
  tar -czf "$UPFILE" -C /var/www prairiepost-shared-uploads 2>>/var/log/civis/backup.log \
    || finish false "uploads tar failed" "$DB_BYTES"
  UP_BYTES=$(stat -c%s "$UPFILE")
fi

# Box state: vhosts, crons, configs. Credential-bearing → 600.
BOXFILE="$DIR/daily/box-$STAMP.tar.gz"
tar -czf "$BOXFILE" /etc/nginx/sites-available /etc/nginx/snippets /etc/nginx/conf.d /etc/cron.d \
    /var/www/prairiepost-*/config.php 2>/dev/null || true
chmod 600 "$BOXFILE" 2>/dev/null || true

# Sundays graduate to the weekly shelf.
if [ "$(date +%u)" = "7" ]; then
  cp -f "$DBFILE" "$DIR/weekly/" 2>/dev/null || true
  [ -n "${UPFILE:-}" ] && cp -f "$UPFILE" "$DIR/weekly/" 2>/dev/null || true
fi

# Rotation: 7 nights on the daily shelf, 4 Sundays on the weekly one.
find "$DIR/daily"  -type f -mtime +7  -delete 2>/dev/null || true
find "$DIR/weekly" -type f -mtime +28 -delete 2>/dev/null || true

# Off-site hook: anything executable at this path gets the night's files.
if [ -x /etc/civis-backup-offsite ]; then
  /etc/civis-backup-offsite "$DBFILE" "${UPFILE:-}" "$BOXFILE" >>/var/log/civis/backup.log 2>&1 \
    || echo "WARN offsite hook failed $(date '+%F %T')" >> /var/log/civis/backup.log
fi

finish true "" "$DB_BYTES" "$UP_BYTES"
EOS
sed -i "s|%%PGDUMP%%|$PGDUMP|" /usr/local/bin/civis-backup.sh
chmod 700 /usr/local/bin/civis-backup.sh
echo "installed /usr/local/bin/civis-backup.sh"

cat > /etc/cron.d/civis-backup <<'CRON'
# Civis Media nightly backup — installed by setup-backups.sh
17 3 * * * root /usr/local/bin/civis-backup.sh >> /var/log/civis/backup.log 2>&1
CRON
chmod 644 /etc/cron.d/civis-backup
echo "installed /etc/cron.d/civis-backup (03:17 nightly)"

say "Running the first backup now (also proves the credentials work)"
if /usr/local/bin/civis-backup.sh; then
  echo "PASS — tonight and every night at 03:17 from here on"
  ls -lh /var/backups/civis/daily/ | tail -4
  cat /var/backups/civis/state.json
else
  echo "FAILED — read /var/log/civis/backup.log, fix, re-run /usr/local/bin/civis-backup.sh"
  exit 1
fi

echo
echo "Next: rehearse the restore once —"
echo "  curl -fsSL <raw url of restore-drill.sh> | bash"
echo "A backup that's never been restored is a hope, not a backup."
