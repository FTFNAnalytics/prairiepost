#!/usr/bin/env bash
#
# Install nightly backups for the Civis Media network — run once, as root,
# from a RELEASE directory. Thin by design: the actual job is the
# repo-versioned tools/backup.sh in the release tree, so backup logic
# rolls forward with releases instead of living as a hand-frozen copy in
# /usr/local/bin (the Phase-2 replacement for the old generated job).
#
#   bash tools/vps/setup-backups.sh
#
# Installs:
#   /etc/cron.d/civis-backup        03:17 nightly:
#                                   PP_BACKUP_DEST=/var/backups/civis
#                                   bash <THIS RELEASE>/tools/backup.sh
#   /var/backups/civis/             sets/<set-id>/… (0700), state.json (0644)
#
# Off-site: create /etc/civis-backup-offsite (root, 700, executable) and a
# key at /root/civis-backup.key (0600) — the cron line picks both up when
# present at INSTALL time; rerun this installer after adding them. The
# off-site hook receives ONE argument: the encrypted set archive.
#
# What one set contains and how restores work: header of tools/backup.sh
# and tools/restore-drill.sh.
#
set -uo pipefail
fail() { echo "FATAL: $*" >&2; exit 1; }
[ "$(id -u)" = "0" ] || fail "run as root"

HERE=$(cd "$(dirname "$0")/../.." && pwd)
[ -f "$HERE/tools/backup.sh" ] || fail "run me from a release tree carrying tools/backup.sh"

# The release identity check, in the file that carries it (config wrapper
# shape aware — the Phase-1 lesson).
CFG="$HERE/config.php"
GUARD="$CFG"
[ -f "$HERE/app/config.site.php" ] && GUARD="$HERE/app/config.site.php"
[ -f "$GUARD" ] || fail "no configuration found in $HERE — is this a live release?"

DEST=/var/backups/civis
mkdir -p "$DEST/sets"
chmod 750 "$DEST"

ENVLINE="PP_BACKUP_DEST=$DEST"
if [ -x /etc/civis-backup-offsite ] && [ -f /root/civis-backup.key ]; then
  ENVLINE="$ENVLINE PP_BACKUP_KEY_FILE=/root/civis-backup.key PP_OFFSITE_CMD=/etc/civis-backup-offsite"
  echo "off-site hook + key found: transfers will run (encrypted)"
else
  echo "no off-site hook (/etc/civis-backup-offsite + /root/civis-backup.key): local-only;"
  echo "state.json will keep saying off-site recovery is UNVERIFIED until you add them."
fi

cat > /etc/cron.d/civis-backup <<EOF
# Installed by tools/vps/setup-backups.sh from $HERE
17 3 * * * root $ENVLINE bash $HERE/tools/backup.sh >> /var/log/civis/backup.log 2>&1
EOF
chmod 644 /etc/cron.d/civis-backup
mkdir -p /var/log/civis

echo
echo "PASS — nightly at 03:17 from the release at $HERE."
echo "Run one now:  $ENVLINE bash $HERE/tools/backup.sh"
echo "NOTE: upgrade-papers.sh repoints cron files at the new release on every"
echo "roll, so the job follows releases; rerun this installer only to change"
echo "the off-site configuration."
