#!/usr/bin/env bash
#
# Restore a backup set into a FRESH, EXPLICITLY NAMED target and verify it
# (F07). Replaces the old tools/vps/restore-drill.sh, whose staging was
# world-readable, whose pg_restore errors were shrugged off, and whose
# "verification" accepted posts >= 0.
#
#   PP_RESTORE_SET=/var/backups/civis/sets/<id> \
#   PP_RESTORE_TARGET_DIR=/tmp/drill-<id> \
#   [PP_RESTORE_PG_HOST=… PP_RESTORE_PG_PORT=… PP_RESTORE_PG_DB=… \
#    PP_RESTORE_PG_USER=… PP_RESTORE_PG_PASSFILE=…] \
#   [PP_RESTORE_RESULTS=/path/results.json] \
#   bash tools/restore-drill.sh
#
# Rules it enforces:
#   - The manifest is verified FIRST: every listed file present with its
#     exact sha256 and byte count. A corrupt or incomplete set never
#     restores.
#   - Archive members are validated before extraction: no absolute paths,
#     no '..', no links pointing outside the archive root.
#   - Targets must be explicit and FRESH: the target dir must not exist
#     (or be empty); the application schema (read from the manifest, never
#     guessed from the server) must not already exist in the target
#     database. Production is therefore refused by construction — its
#     schema exists.
#   - Database credentials go through a 0600 passfile, never argv.
#   - pg_restore failure IS failure.
#   - Verification is exact: restored logical counts must EQUAL the
#     manifest's recorded counts, the schema version must match, and a
#     sequence must hand out a fresh id.
#   - Cleanup removes only what this drill created, and only on refusal
#     before anything was written; a completed target is left for
#     inspection.
#
# Everything is private from creation (umask 077). Results are written as
# JSON to PP_RESTORE_RESULTS when set.
#
set -uo pipefail
umask 077

SET="${PP_RESTORE_SET:?PP_RESTORE_SET is required}"
TARGET="${PP_RESTORE_TARGET_DIR:?PP_RESTORE_TARGET_DIR is required}"
RESULTS="${PP_RESTORE_RESULTS:-}"

declare -A CHECK
finish() { # ok(0/1) message
  local okall="$1" msg="$2"
  if [ -n "$RESULTS" ]; then
    local tmp
    tmp=$(mktemp)
    for k in "${!CHECK[@]}"; do printf '%s=%s\n' "$k" "${CHECK[$k]}" >> "$tmp"; done
    php -r '
      $checks = [];
      foreach (file($argv[1], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
          [$k, $v] = array_pad(explode("=", $l, 2), 2, "");
          $checks[$k] = $v;
      }
      ksort($checks);
      echo json_encode(["ok" => $argv[2] === "0", "message" => $argv[3],
          "finished_at" => date("Y-m-d H:i:s"), "checks" => $checks],
          JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    ' "$tmp" "$okall" "$msg" > "$RESULTS"
    rm -f "$tmp"
  fi
  if [ "$okall" = "0" ]; then
    echo "DRILL OK: $msg"
    exit 0
  fi
  echo "DRILL FAILED: $msg" >&2
  exit 1
}
refuse() { CHECK[refusal]="$1"; finish 1 "REFUSED: $1"; }

[ -d "$SET" ] || refuse "backup set not found: $SET"
[ -f "$SET/manifest.json" ] || refuse "the set has no manifest — not a complete backup"

# --- 1. Manifest integrity: everything present, byte- and hash-exact --------
if ! php -r '
  $set = $argv[1];
  $man = json_decode((string) file_get_contents("$set/manifest.json"), true);
  if (!$man || empty($man["files"])) { fwrite(STDERR, "manifest unreadable\n"); exit(1); }
  foreach ($man["files"] as $rel => $meta) {
      if (str_contains($rel, "..") || str_starts_with($rel, "/")) { fwrite(STDERR, "manifest path refused: $rel\n"); exit(1); }
      $p = "$set/$rel";
      if (!is_file($p)) { fwrite(STDERR, "missing from set: $rel\n"); exit(1); }
      if (filesize($p) !== (int) $meta["bytes"]) { fwrite(STDERR, "size mismatch (truncated?): $rel\n"); exit(1); }
      if (hash_file("sha256", $p) !== $meta["sha256"]) { fwrite(STDERR, "checksum mismatch: $rel\n"); exit(1); }
  }
' "$SET" 2> /tmp/.drill-manifest-err-$$; then
  reason=$(cat /tmp/.drill-manifest-err-$$; rm -f /tmp/.drill-manifest-err-$$)
  refuse "manifest verification failed: $reason"
fi
rm -f /tmp/.drill-manifest-err-$$
CHECK[manifest]="ok"

# --- 2. Archive member validation BEFORE extraction ---------------------------
for t in "$SET"/*.tar.gz; do
  [ -f "$t" ] || continue
  if ! php -r '
    exec("tar -tvzf " . escapeshellarg($argv[1]) . " 2>/dev/null", $lines, $rc);
    if ($rc !== 0) { fwrite(STDERR, "unreadable archive\n"); exit(1); }
    foreach ($lines as $l) {
        if (!preg_match("/^\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+(.*)$/", $l, $m)) continue;
        $name = $m[1];
        $link = null;
        if (str_contains($name, " -> ")) { [$name, $link] = explode(" -> ", $name, 2); }
        if (str_starts_with($name, "/") || str_contains($name, "..")) { fwrite(STDERR, "member refused: $name\n"); exit(1); }
        if ($link !== null && (str_starts_with($link, "/") || str_contains($link, ".."))) {
            fwrite(STDERR, "link target refused: $name -> $link\n"); exit(1);
        }
    }
  ' "$t" 2> /tmp/.drill-tar-err-$$; then
    reason=$(cat /tmp/.drill-tar-err-$$; rm -f /tmp/.drill-tar-err-$$)
    refuse "archive validation failed for $(basename "$t"): $reason"
  fi
done
rm -f /tmp/.drill-tar-err-$$
CHECK[archives]="ok"

# --- 3. Fresh-target refusal ---------------------------------------------------
if [ -e "$TARGET" ] && [ -n "$(ls -A "$TARGET" 2>/dev/null)" ]; then
  refuse "target dir exists and is not empty: $TARGET"
fi

SCHEMA=$(php -r '$m = json_decode(file_get_contents($argv[1] . "/manifest.json"), true); preg_match("/schema=(\S+)/", (string) ($m["database"] ?? ""), $x); echo $x[1] ?? "";' "$SET")
DBKIND=$(php -r '$m = json_decode(file_get_contents($argv[1] . "/manifest.json"), true); echo explode(" ", (string) ($m["database"] ?? ""))[0];' "$SET")

PGARGS=()
if [ "$DBKIND" = "pgsql" ]; then
  : "${PP_RESTORE_PG_HOST:?pgsql set: PP_RESTORE_PG_HOST is required (targets are explicit, never inherited)}"
  : "${PP_RESTORE_PG_DB:?pgsql set: PP_RESTORE_PG_DB is required}"
  : "${PP_RESTORE_PG_USER:?pgsql set: PP_RESTORE_PG_USER is required}"
  [ -n "$SCHEMA" ] || refuse "manifest carries no schema identity"
  PGARGS=(-h "$PP_RESTORE_PG_HOST" -p "${PP_RESTORE_PG_PORT:-5432}" -U "$PP_RESTORE_PG_USER" -d "$PP_RESTORE_PG_DB")
  export PGPASSFILE="${PP_RESTORE_PG_PASSFILE:-/dev/null}"
  EXISTS=$(psql "${PGARGS[@]}" -t -A -c "SELECT COUNT(*) FROM pg_namespace WHERE nspname = '$SCHEMA'" 2>/dev/null || echo "ERR")
  [ "$EXISTS" = "ERR" ] && refuse "cannot reach the target database (explicit target params required)"
  [ "$EXISTS" = "0" ] || refuse "schema '$SCHEMA' already exists in target database '$PP_RESTORE_PG_DB' — a drill restores into FRESH space only (this is what protects production)"
fi
CHECK[target_fresh]="ok"

# --- 4. Extract into the fresh target ------------------------------------------
mkdir -p "$TARGET"
chmod 700 "$TARGET"
cp -p "$SET/db.dump" "$TARGET/db.dump"
cp -rp "$SET/config" "$TARGET/config"
cp -p "$SET/manifest.json" "$TARGET/manifest.json"
mkdir -p "$TARGET/uploads" "$TARGET/vhosts" "$TARGET/cron"
tar -C "$TARGET/uploads" --strip-components=1 -xzf "$SET/uploads.tar.gz" || finish 1 "uploads extraction failed"
tar -C "$TARGET/vhosts" -xzf "$SET/vhosts.tar.gz" || finish 1 "vhosts extraction failed"
tar -C "$TARGET/cron" -xzf "$SET/cron.tar.gz" || finish 1 "cron extraction failed"
CHECK[extract]="ok"

# --- 5. Database restore (errors are ERRORS) -----------------------------------
if [ "$DBKIND" = "pgsql" ]; then
  if ! pg_restore "${PGARGS[@]}" --no-owner --exit-on-error "$TARGET/db.dump" 2> "$TARGET/.restore.err"; then
    finish 1 "pg_restore failed: $(head -c 300 "$TARGET/.restore.err" | tr '\n' ' ')"
  fi
  QDB() { psql "${PGARGS[@]}" -t -A -c "$1"; }
else
  QDB() { php -r '$p = new PDO("sqlite:" . $argv[1]); echo $p->query($argv[2])->fetchColumn();' "$TARGET/db.dump" "$1"; }
  SCHEMA=""
fi
CHECK[db_restore]="ok"

# --- 6. Exact verification against the manifest --------------------------------
PREFIX=""
[ -n "$SCHEMA" ] && PREFIX="\"$SCHEMA\"."
MISMATCH=""
for t in sites posts users domains post_revisions subscribers; do
  want=$(php -r '$m = json_decode(file_get_contents($argv[1] . "/manifest.json"), true); echo $m["counts"][$argv[2]] ?? "?";' "$SET" "$t")
  got=$(QDB "SELECT COUNT(*) FROM ${PREFIX}${t}" 2>/dev/null || echo "ERR")
  CHECK["count_$t"]="want=$want got=$got"
  [ "$want" = "$got" ] || MISMATCH="$MISMATCH $t($want!=$got)"
done
[ -z "$MISMATCH" ] || finish 1 "restored counts do not match the manifest:$MISMATCH"

WANTVER=$(php -r '$m = json_decode(file_get_contents($argv[1] . "/manifest.json"), true); echo $m["schema_version"] ?? "?";' "$SET")
GOTVER=$(QDB "SELECT svalue FROM ${PREFIX}settings WHERE site_id = 0 AND skey = 'schema_version'" 2>/dev/null || echo "ERR")
CHECK[schema_version]="want=$WANTVER got=$GOTVER"
[ "$WANTVER" = "$GOTVER" ] || finish 1 "schema version mismatch (want $WANTVER, got $GOTVER)"

# Sequences hand out fresh ids after restore.
if [ "$DBKIND" = "pgsql" ]; then
  NEWID=$(psql "${PGARGS[@]}" -t -A -c "INSERT INTO ${PREFIX}subscribers (email, status, site_id, created_at) VALUES ('drill-probe@test.invalid', 'pending', 1, now()) RETURNING id" 2> "$TARGET/.seq.err" || echo "ERR")
  [ "$NEWID" != "ERR" ] && [ -n "$NEWID" ] || finish 1 "sequence probe failed: $(head -c 200 "$TARGET/.seq.err" 2>/dev/null)"
  psql "${PGARGS[@]}" -q -c "DELETE FROM ${PREFIX}subscribers WHERE email = 'drill-probe@test.invalid'"
  CHECK[sequences]="ok (probe id $NEWID)"
else
  NEWID=$(php -r '$p = new PDO("sqlite:" . $argv[1]); $p->exec("INSERT INTO subscribers (email, status, site_id, created_at) VALUES (\"drill-probe@test.invalid\", \"pending\", 1, datetime())"); echo $p->lastInsertId(); $p->exec("DELETE FROM subscribers WHERE email = \"drill-probe@test.invalid\"");' "$TARGET/db.dump" 2>/dev/null || echo "ERR")
  [ "$NEWID" != "ERR" ] || finish 1 "sequence probe failed"
  CHECK[sequences]="ok (probe id $NEWID)"
fi

# Uploads content matches the manifest's archive (extraction already
# checksum-verified via the archive file itself; spot-check a file exists).
UPCOUNT=$(find "$TARGET/uploads" -type f | wc -l)
CHECK[uploads_files]="$UPCOUNT file(s)"
[ "$UPCOUNT" -ge 1 ] || finish 1 "restored uploads are empty"

# Config layers restored for every release the set knew.
CFGOK=1
while read -r rel; do
  base=$(basename "$rel")
  [ -f "$TARGET/config/$base/config.php" ] || CFGOK=0
done < "$SET/releases.txt"
[ "$CFGOK" = "1" ] || finish 1 "restored config layers incomplete"
CHECK[config_layers]="ok"

finish 0 "restored set $(basename "$SET") into $TARGET$([ -n "$SCHEMA" ] && echo " and schema $SCHEMA of $PP_RESTORE_PG_DB")"
