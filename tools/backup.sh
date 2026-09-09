#!/usr/bin/env bash
#
# The network backup job (F07) — one COMPLETE, verifiable recovery set per
# run, private from the first byte, truthful about every failure.
#
#   PP_BACKUP_DEST=/var/backups/civis bash tools/backup.sh
#
# Everything else is discovered or optional:
#   PP_BACKUP_VHOSTS_DIR   enabled-vhost dir (default /etc/nginx/sites-enabled)
#   PP_BACKUP_CRON_DIR     cron dir to archive (default /etc/cron.d)
#   PP_BACKUP_HUB_NAME     enabled-vhost basename carrying the hub (default civismedia)
#   PP_BACKUP_KEEP_DAILY   complete sets kept (default 7)
#   PP_BACKUP_KEEP_WEEKLY  weekly (Sunday) sets kept on top (default 4)
#   PP_OFFSITE_CMD         command run with the ENCRYPTED set archive path;
#                          its failure fails the run when PP_OFFSITE_REQUIRED=1,
#                          otherwise the state records off-site as unverified
#   PP_BACKUP_KEY_FILE     key file for off-site encryption (required when
#                          PP_OFFSITE_CMD is set; read via -pass file:, never argv)
#
# What one set contains (all under sets/<set-id>/, mode 700/600):
#   db.dump                    pg_dump -Fc of the app schema (or the sqlite
#                              file, VACUUM'd, on sqlite installs — fixtures)
#   config/<release>/…         config.php AND app/config.site.php per active
#                              release root, copied VERBATIM (host-dependent
#                              logic preserved as code, never evaluated flat)
#   uploads.tar.gz             the shared uploads tree
#   vhosts.tar.gz, cron.tar.gz server mappings and job definitions
#   releases.txt               active release roots + their embedded SHAs
#   manifest.json              set id, schema version, sanitized db identity,
#                              per-file sha256/bytes/mode, component states,
#                              DECLARED exclusions (TLS certs, nginx globals,
#                              OS packages — external recovery dependencies)
#
# The set is staged under .staging-<id> and published by one rename only
# after EVERY required component validates (tar -t, pg_restore --list,
# checksums). state.json (public, secret-free — the ops dashboard reads
# it) says ok:true only for a published complete set. Retention prunes
# whole complete sets, never components, and never the last verified set.
#
set -uo pipefail
umask 077

DEST="${PP_BACKUP_DEST:?PP_BACKUP_DEST is required}"
VHOSTS_DIR="${PP_BACKUP_VHOSTS_DIR:-/etc/nginx/sites-enabled}"
CRON_DIR="${PP_BACKUP_CRON_DIR:-/etc/cron.d}"
HUB_NAME="${PP_BACKUP_HUB_NAME:-civismedia}"
KEEP_DAILY="${PP_BACKUP_KEEP_DAILY:-7}"
KEEP_WEEKLY="${PP_BACKUP_KEEP_WEEKLY:-4}"
REPO_ROOT=$(cd "$(dirname "$0")/.." && pwd)

SET_ID="$(date +%Y%m%d-%H%M%S)-$(head -c4 /dev/urandom | od -An -tx1 | tr -d ' \n')"
STAGE="$DEST/.staging-$SET_ID"
STATE="$DEST/state.json"
declare -A COMPONENT   # name -> ok|failed:<reason>

fail_run() { # reason
  local reason="$1"
  php -r '
    $components = json_decode($argv[3], true) ?: new stdClass();
    echo json_encode([
        "ok" => false, "set_id" => $argv[2],
        "finished_at" => date("Y-m-d H:i:s"), "finished_epoch" => time(),
        "reason" => $argv[1], "components" => $components,
        "offsite" => "not attempted",
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
  ' "$reason" "$SET_ID" "$(components_json)" > "$STATE.tmp" && chmod 644 "$STATE.tmp" && mv "$STATE.tmp" "$STATE"
  rm -rf "$STAGE"
  echo "BACKUP FAILED: $reason" >&2
  echo "The last verified set (if any) was NOT touched." >&2
  exit 1
}
components_json() {
  local tmp
  tmp=$(mktemp)
  for k in "${!COMPONENT[@]}"; do
    printf '%s=%s\n' "$k" "${COMPONENT[$k]}" >> "$tmp"
  done
  # Values may carry error text with quotes/newlines-adjacent characters —
  # JSON encoding happens in PHP, never by string concatenation.
  php -r '
    $out = [];
    foreach (file($argv[1], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        [$k, $v] = array_pad(explode("=", $line, 2), 2, "");
        $out[$k] = $v;
    }
    echo json_encode($out);
  ' "$tmp"
  rm -f "$tmp"
}

mkdir -p "$DEST/sets" || { echo "cannot create $DEST" >&2; exit 1; }

# Overlap guard: one run at a time, stale locks (dead pid) reclaimed.
LOCK="$DEST/.lock"
if ! mkdir "$LOCK" 2>/dev/null; then
  HOLDER=$(cat "$LOCK/pid" 2>/dev/null || echo "")
  if [ -n "$HOLDER" ] && kill -0 "$HOLDER" 2>/dev/null; then
    echo "another backup run (pid $HOLDER) is in progress — refusing to overlap" >&2
    exit 1
  fi
  rm -rf "$LOCK"; mkdir "$LOCK" || exit 1
fi
echo $$ > "$LOCK/pid"
trap 'rm -rf "$LOCK"' EXIT

mkdir -p "$STAGE/config"
chmod 700 "$STAGE"

echo "== Backup set $SET_ID"

# --- Discover active releases from the enabled vhosts ----------------------
declare -A ROOTS=()
HUB_CFG=""
for link in "$VHOSTS_DIR"/*; do
  [ -e "$link" ] || continue
  base=$(basename "$link")
  case "$base" in cies|README|default|*.bak*) continue ;; esac
  VH=$(readlink -f "$link") || continue
  [ -f "$VH" ] || continue
  # Strip inline comments BEFORE reading the root (lesson: commented roots).
  ROOT=$(sed 's/#.*//' "$VH" | grep -Eo 'root[[:space:]]+/[^;[:space:]]+' | head -1 | awk '{print $2}')
  [ -n "$ROOT" ] && [ -d "$ROOT" ] || continue
  ROOTS["$ROOT"]=1
  [ "$base" = "$HUB_NAME" ] && HUB_CFG="$ROOT/config.php"
done
[ "${#ROOTS[@]}" -gt 0 ] || fail_run "no active release roots discovered in $VHOSTS_DIR"
[ -n "$HUB_CFG" ] && [ -f "$HUB_CFG" ] || fail_run "hub config not found (vhost '$HUB_NAME')"

# --- Config dependencies: BOTH layers, per release, verbatim ---------------
: > "$STAGE/releases.txt"
for ROOT in "${!ROOTS[@]}"; do
  rel=$(basename "$ROOT")
  mkdir -p "$STAGE/config/$rel"
  if [ ! -f "$ROOT/config.php" ]; then
    COMPONENT[config]="failed:missing $ROOT/config.php"
    fail_run "required config missing: $ROOT/config.php"
  fi
  cp -p "$ROOT/config.php" "$STAGE/config/$rel/config.php"
  # The wrapper's real configuration — THE audit gap (F07): a restore from
  # the old backup had wrappers pointing at a file nobody saved.
  if [ -f "$ROOT/app/config.site.php" ]; then
    cp -p "$ROOT/app/config.site.php" "$STAGE/config/$rel/config.site.php"
  elif grep -q "config.site.php" "$ROOT/config.php" 2>/dev/null; then
    COMPONENT[config]="failed:wrapper without config.site.php in $ROOT"
    fail_run "config.php in $ROOT is a wrapper but app/config.site.php is missing"
  fi
  echo "$ROOT" >> "$STAGE/releases.txt"
done
COMPONENT[config]="ok"

# --- Database dump ----------------------------------------------------------
DRIVER=$(php -r '$c = require $argv[1]; echo $c["db"]["driver"] ?? "";' "$HUB_CFG" 2>/dev/null)
SCHEMA_VERSION=""
DB_IDENTITY=""
if [ "$DRIVER" = "pgsql" ]; then
  # Credentials flow through a 0600 pgpass file — never argv, never logs.
  read -r PGH PGP PGN PGU SCHEMA <<< "$(php -r '
    $c = require $argv[1]; $p = $c["db"]["pgsql"];
    echo ($p["host"] ?? "") . " " . (int)($p["port"] ?? 5432) . " " . ($p["name"] ?? "postgres") . " " . ($p["user"] ?? "") . " " . ($p["schema"] ?? "prairiedispatch");' "$HUB_CFG")"
  PASSFILE="$STAGE/.pgpass"
  php -r '$c = require $argv[1]; $p = $c["db"]["pgsql"];
    file_put_contents($argv[2], sprintf("%s:%d:%s:%s:%s\n", $p["host"], (int)($p["port"] ?? 5432), $p["name"] ?? "postgres", $p["user"], str_replace([":","\\"],["\\:","\\\\"], (string) $p["pass"])));' "$HUB_CFG" "$PASSFILE"
  chmod 600 "$PASSFILE"
  DB_IDENTITY="pgsql $PGH:$PGP db=$PGN schema=$SCHEMA"
  if ! PGPASSFILE="$PASSFILE" pg_dump -h "$PGH" -p "$PGP" -U "$PGU" -d "$PGN" --schema="$SCHEMA" -Fc -f "$STAGE/db.dump" 2> "$STAGE/.dump.err"; then
    COMPONENT[database]="failed:pg_dump"
    fail_run "pg_dump failed: $(head -c 300 "$STAGE/.dump.err" | tr '\n' ' ')"
  fi
  SCHEMA_VERSION=$(PGPASSFILE="$PASSFILE" psql -h "$PGH" -p "$PGP" -U "$PGU" -d "$PGN" -t -A -c "SELECT svalue FROM \"$SCHEMA\".settings WHERE site_id = 0 AND skey = 'schema_version'" 2>/dev/null || echo "")
  rm -f "$PASSFILE"
elif [ "$DRIVER" = "sqlite" ]; then
  SQPATH=$(php -r '$c = require $argv[1]; echo $c["db"]["sqlite_path"] ?? "";' "$HUB_CFG")
  [ -f "$SQPATH" ] || { COMPONENT[database]="failed:missing sqlite file"; fail_run "sqlite database missing: $SQPATH"; }
  php -r '$p = new PDO("sqlite:" . $argv[1]); $p->exec("VACUUM INTO " . $p->quote($argv[2]));' "$SQPATH" "$STAGE/db.dump" \
    || { COMPONENT[database]="failed:sqlite copy"; fail_run "sqlite VACUUM INTO failed"; }
  DB_IDENTITY="sqlite $(basename "$SQPATH")"
  SCHEMA_VERSION=$(php -r '$p = new PDO("sqlite:" . $argv[1]); echo $p->query("SELECT svalue FROM settings WHERE site_id = 0 AND skey = \"schema_version\"")->fetchColumn();' "$STAGE/db.dump" 2>/dev/null || echo "")
else
  COMPONENT[database]="failed:driver $DRIVER"
  fail_run "unsupported database driver '$DRIVER' for backup"
fi
COMPONENT[database]="ok"
echo "   database: $DB_IDENTITY (schema version ${SCHEMA_VERSION:-unknown})"

# --- Uploads (required) ------------------------------------------------------
UPLOADS=""
for ROOT in "${!ROOTS[@]}"; do
  if [ -L "$ROOT/uploads" ]; then
    UPLOADS=$(readlink -f "$ROOT/uploads")
    break
  elif [ -d "$ROOT/uploads" ]; then
    UPLOADS="$ROOT/uploads"
    break
  fi
done
[ -n "$UPLOADS" ] && [ -d "$UPLOADS" ] || { COMPONENT[uploads]="failed:not found"; fail_run "shared uploads directory not found from any release root"; }
tar -C "$(dirname "$UPLOADS")" -czf "$STAGE/uploads.tar.gz" "$(basename "$UPLOADS")" \
  || { COMPONENT[uploads]="failed:tar"; fail_run "uploads archive failed"; }
COMPONENT[uploads]="ok"

# --- Vhosts + cron (required; failures are FAILURES, not shrugs) -------------
tar -C "$VHOSTS_DIR" --dereference -czf "$STAGE/vhosts.tar.gz" . \
  || { COMPONENT[vhosts]="failed:tar"; fail_run "vhost archive failed"; }
COMPONENT[vhosts]="ok"
if [ -d "$CRON_DIR" ]; then
  tar -C "$CRON_DIR" -czf "$STAGE/cron.tar.gz" . \
    || { COMPONENT[cron]="failed:tar"; fail_run "cron archive failed"; }
  COMPONENT[cron]="ok"
else
  COMPONENT[cron]="failed:missing dir"
  fail_run "cron directory $CRON_DIR does not exist"
fi

# --- Validate every artifact BEFORE calling anything a backup ----------------
for t in uploads.tar.gz vhosts.tar.gz cron.tar.gz; do
  tar -tzf "$STAGE/$t" >/dev/null 2>&1 || fail_run "$t does not read back as a valid archive"
done
if [ "$DRIVER" = "pgsql" ]; then
  pg_restore --list "$STAGE/db.dump" >/dev/null 2>&1 || fail_run "db.dump does not read back with pg_restore --list"
else
  php -r '$p = new PDO("sqlite:" . $argv[1]); $p->query("SELECT COUNT(*) FROM sites")->fetchColumn();' "$STAGE/db.dump" >/dev/null 2>&1 \
    || fail_run "db.dump does not read back as a database"
fi

# --- Logical counts, for the restore drill to verify against -----------------
if [ "$DRIVER" = "pgsql" ]; then
  COUNTS=$(PGPASSFILE="$STAGE/.pgpass.gone" php -r '
    $c = require $argv[1]; $p = $c["db"]["pgsql"];
    $pdo = new PDO(sprintf("pgsql:host=%s;port=%d;dbname=%s;sslmode=%s", $p["host"], (int)($p["port"] ?? 5432), $p["name"] ?? "postgres", $p["sslmode"] ?? "require"), $p["user"], $p["pass"]);
    $pdo->exec("SET search_path TO \"" . ($p["schema"] ?? "prairiedispatch") . "\"");
    $out = [];
    foreach (["sites", "posts", "users", "domains", "post_revisions", "subscribers"] as $t) {
        $out[$t] = (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    }
    echo json_encode($out);' "$HUB_CFG" 2>/dev/null || echo '{}')
else
  COUNTS=$(php -r '
    $pdo = new PDO("sqlite:" . $argv[1]);
    $out = [];
    foreach (["sites", "posts", "users", "domains", "post_revisions", "subscribers"] as $t) {
        $out[$t] = (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    }
    echo json_encode($out);' "$STAGE/db.dump" 2>/dev/null || echo '{}')
fi

# --- Manifest (confidential: lives inside the 700 set, not in state.json) ----
PP_MANIFEST_COUNTS="$COUNTS" php -r '
$stage = $argv[1];
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || str_starts_with($f->getFilename(), ".")) continue;
    if ($f->getFilename() === "manifest.json") continue; // cannot hash itself mid-write
    $rel = substr($f->getPathname(), strlen($stage) + 1);
    $files[$rel] = [
        "bytes" => $f->getSize(),
        "sha256" => hash_file("sha256", $f->getPathname()),
        "mode" => substr(sprintf("%o", $f->getPerms()), -4),
    ];
}
ksort($files);
echo json_encode([
    "set_id" => $argv[2],
    "created_at" => date("Y-m-d H:i:s"),
    "schema_version" => $argv[3] !== "" ? (int) $argv[3] : null,
    "database" => $argv[4],
    "releases" => array_values(array_filter(array_map("trim", file($stage . "/releases.txt")))),
    "counts" => json_decode((string) getenv("PP_MANIFEST_COUNTS"), true) ?: new stdClass(),
    "files" => $files,
    "excluded_external_dependencies" => [
        "TLS certificates (/etc/letsencrypt) — reissued via certbot on recovery",
        "nginx global configuration (/etc/nginx/nginx.conf, snippets) — from the distro + runbooks",
        "OS packages and PHP extensions (php-fpm pool with pdo_pgsql, dom, intl, gd) — from the runbooks",
        "application code — recover the release by SHA from releases.txt (git, immutable history)",
        "database server role/grant provisioning — docs/build/phase-02-handoff.md privileges section",
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP), "\n";
' "$STAGE" "$SET_ID" "$SCHEMA_VERSION" "$DB_IDENTITY" > "$STAGE/manifest.json" || fail_run "manifest generation failed"
COMPONENT[manifest]="ok"

# --- Publish atomically -------------------------------------------------------
mv "$STAGE" "$DEST/sets/$SET_ID" || fail_run "publish rename failed"
echo "   published: $DEST/sets/$SET_ID"

# --- Off-site (optional, but never silently) ----------------------------------
OFFSITE_STATUS="not configured — off-site recovery UNVERIFIED"
if [ -n "${PP_OFFSITE_CMD:-}" ]; then
  [ -n "${PP_BACKUP_KEY_FILE:-}" ] && [ -f "${PP_BACKUP_KEY_FILE:-}" ] \
    || fail_run "PP_OFFSITE_CMD is set but PP_BACKUP_KEY_FILE is missing — off-site copies must be encrypted"
  ENC="$DEST/sets/$SET_ID.tar.enc"
  if tar -C "$DEST/sets" -cf - "$SET_ID" \
      | openssl enc -aes-256-cbc -pbkdf2 -salt -pass "file:$PP_BACKUP_KEY_FILE" -out "$ENC" 2>/dev/null \
      && $PP_OFFSITE_CMD "$ENC"; then
    OFFSITE_STATUS="transferred (encrypted); restore REHEARSAL still separate"
    COMPONENT[offsite]="ok"
  else
    rm -f "$ENC"
    COMPONENT[offsite]="failed"
    if [ "${PP_OFFSITE_REQUIRED:-0}" = "1" ]; then
      fail_run "required off-site transfer failed"
    fi
    OFFSITE_STATUS="FAILED — local set retained; off-site recovery UNVERIFIED"
  fi
fi

# --- Retention: whole complete sets, newest first, never the last one ---------
mapfile -t ALLSETS < <(ls -1 "$DEST/sets" | grep -E '^[0-9]{8}-[0-9]{6}-[0-9a-f]{8}$' | sort -r)
KEEP=()
DAILY_KEPT=0
WEEKLY_KEPT=0
for s in "${ALLSETS[@]}"; do
  day="${s:0:8}"
  dow=$(date -d "$day" +%u 2>/dev/null || echo 1)
  if [ "$DAILY_KEPT" -lt "$KEEP_DAILY" ]; then
    KEEP+=("$s"); DAILY_KEPT=$((DAILY_KEPT + 1))
  elif [ "$dow" = "7" ] && [ "$WEEKLY_KEPT" -lt "$KEEP_WEEKLY" ]; then
    KEEP+=("$s"); WEEKLY_KEPT=$((WEEKLY_KEPT + 1))
  fi
done
for s in "${ALLSETS[@]}"; do
  keep=0
  for k in "${KEEP[@]}"; do [ "$s" = "$k" ] && keep=1; done
  if [ "$keep" = "0" ]; then
    rm -rf "$DEST/sets/$s" "$DEST/sets/$s.tar.enc"
    echo "   pruned set $s (whole set: db + config + uploads together)"
  fi
done

# --- Public state (no secrets, no paths beyond the set id) --------------------
DBBYTES=$(stat -c%s "$DEST/sets/$SET_ID/db.dump")
UPBYTES=$(stat -c%s "$DEST/sets/$SET_ID/uploads.tar.gz")
php -r '
echo json_encode([
    "ok" => true, "set_id" => $argv[1],
    "finished_at" => date("Y-m-d H:i:s"), "finished_epoch" => time(),
    "reason" => "", "db_bytes" => (int) $argv[2], "uploads_bytes" => (int) $argv[3],
    "schema_version" => $argv[4] !== "" ? (int) $argv[4] : null,
    "components" => json_decode($argv[5], true),
    "offsite" => $argv[6],
    "restore_rehearsal" => "separate — tools/restore-drill.sh; ok here means a LOCAL set published",
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
' "$SET_ID" "$DBBYTES" "$UPBYTES" "$SCHEMA_VERSION" "$(components_json)" "$OFFSITE_STATUS" > "$STATE.tmp" \
  && chmod 644 "$STATE.tmp" && mv "$STATE.tmp" "$STATE"

echo "BACKUP OK: set $SET_ID complete and published."
