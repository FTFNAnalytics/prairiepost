#!/bin/bash
# Generate an nginx server block from vhost.template.
#
#   bash tools/vps/make-vhost.sh <release-root> <php-socket> <domain> [domain...]
#     > /etc/nginx/sites-available/<name>
#
# The block name is the first domain with dots stripped of its www prefix.
# TLS is certbot's job afterwards; the generated block is plain port 80,
# both address families, so certbot mirrors both when it adds 443 — the
# single-family listen asymmetry that once served the wrong certificate
# cannot be reproduced from this template.
set -euo pipefail

if [ $# -lt 3 ]; then
  echo "usage: $0 <release-root> <php-socket> <domain> [domain...]" >&2
  exit 64
fi

ROOT=$1; SOCKET=$2; shift 2
NAMES="$*"
NAME=$(echo "$1" | sed 's/^www\.//')

TEMPLATE="$(dirname "$0")/vhost.template"
[ -f "$TEMPLATE" ] || { echo "vhost.template not found beside this script" >&2; exit 1; }

sed -e "s|__VHOST_NAME__|$NAME|" \
    -e "s|__SERVER_NAMES__|$NAMES|" \
    -e "s|__RELEASE_ROOT__|$ROOT|" \
    -e "s|__PHP_SOCKET__|$SOCKET|" \
    "$TEMPLATE"
