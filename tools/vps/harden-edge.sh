#!/usr/bin/env bash
#
# Apply the Civis edge kit to every prairiepost vhost on the box — run as
# root, safe to re-run any time (idempotent):
#
#   curl -fsSL <raw url> | bash
#
# What it does:
#   1. Writes the shared definitions (limit zones, cache path, maps) and the
#      per-server snippet — same files deploy-civis.sh writes, same content.
#   2. Inserts `include snippets/civis-edge.conf;` into every server block
#      of every vhost whose root is a prairiepost release (hub and papers,
#      HTTP and certbot's TLS blocks alike). Vhosts that already include it
#      are left alone. Unrelated sites on the box are never touched.
#   3. nginx -t; on failure every changed vhost is restored from its backup
#      and nothing reloads.
#
# What the kit gives every site:
#   - security headers (HSTS, nosniff, frame, referrer, permissions)
#   - per-IP request limiting (30 r/s, burst 120 — floods get 429s)
#   - a 60-second microcache for anonymous readers, so a traffic spike is
#     answered from memory instead of PHP+database. Signed-in sessions and
#     admin/cron/api/ad paths always bypass it. The X-PP-Cache response
#     header reads HIT/MISS/BYPASS so you can see it working.
#
set -uo pipefail
fail() { echo "FATAL: $*" >&2; exit 1; }
[ "$(id -u)" = "0" ] || fail "run as root"
command -v nginx >/dev/null || fail "nginx not found"

STAMP=$(date +%s)

echo "== Shared definitions =="
mkdir -p /var/cache/nginx/civis /etc/nginx/snippets
chown www-data:www-data /var/cache/nginx/civis 2>/dev/null || true
cat > /etc/nginx/conf.d/civis-edge-zones.conf <<'ZONES'
# Civis Media edge kit (zones + maps) — written by deploy-civis.sh / harden-edge.sh.
limit_req_zone $binary_remote_addr zone=ppgeneral:10m rate=30r/s;
fastcgi_cache_path /var/cache/nginx/civis levels=1:2 keys_zone=ppcache:32m max_size=256m inactive=10m;
# Never cache for a browser holding a session, nor any admin/cron/api/ad path.
map $http_cookie $pp_skip_sess { default 0; ~*ppsession 1; }
map $request_uri $pp_skip_path { default 0; "~^/(admin|cron|api)([/?.]|$)" 1; "~^/ad([/?.]|$)" 1; }
ZONES
cat > /etc/nginx/snippets/civis-edge.conf <<'EDGE'
# Civis Media edge kit — security headers, per-IP request limiting, and a
# short microcache for anonymous readers (sessions start lazily app-side,
# so public GETs are cookie-free and cacheable). X-PP-Cache says HIT/MISS/
# BYPASS on PHP responses. Written by deploy-civis.sh / harden-edge.sh.
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()" always;
add_header Strict-Transport-Security "max-age=15552000" always;
add_header X-PP-Cache $upstream_cache_status always;

limit_req zone=ppgeneral burst=120 nodelay;
limit_req_status 429;

fastcgi_cache ppcache;
fastcgi_cache_key "$scheme$host$request_uri";
fastcgi_cache_methods GET HEAD;
fastcgi_cache_valid 200 301 60s;
fastcgi_cache_valid 404 10s;
fastcgi_cache_bypass $pp_skip_sess $pp_skip_path;
fastcgi_no_cache $pp_skip_sess $pp_skip_path;
fastcgi_cache_use_stale error timeout updating http_500 http_503;
fastcgi_cache_background_update on;
fastcgi_cache_lock on;
EDGE
echo "written: conf.d/civis-edge-zones.conf, snippets/civis-edge.conf"

echo
echo "== Vhosts =="
CHANGED=()
for VH in /etc/nginx/sites-available/*; do
  [ -f "$VH" ] || continue
  base=$(basename "$VH")
  case "$base" in *.bak.*|default) continue ;; esac
  # Only vhosts serving a prairiepost release — never the box's other sites.
  grep -Eq 'root[[:space:]]+/var/www/prairiepost-' "$VH" || continue
  if grep -q 'snippets/civis-edge.conf' "$VH"; then
    echo "   $base: already has the kit"
    continue
  fi
  cp "$VH" "$VH.bak.$STAMP"
  # One include per server block, right after its server_name line.
  sed -i 's|^\([[:space:]]*\)server_name[[:space:]].*;$|&\n\1include snippets/civis-edge.conf;|' "$VH"
  N=$(grep -c 'snippets/civis-edge.conf' "$VH")
  echo "   $base: kit included in $N server block(s)"
  CHANGED+=("$VH")
done

if [ "${#CHANGED[@]}" = "0" ]; then
  echo "   nothing to change"
fi

echo
echo "== nginx -t =="
if ! nginx -t; then
  for VH in "${CHANGED[@]}"; do
    cp "$VH.bak.$STAMP" "$VH"
    echo "   restored $(basename "$VH")"
  done
  nginx -t
  fail "config test failed — every vhost restored, nothing reloaded"
fi
systemctl reload nginx 2>/dev/null || service nginx reload || service nginx restart || fail "nginx wouldn't reload — investigate before anything else"
echo "reloaded"

echo
echo "== Proof (against this box) =="
for host in civismedia.ca prairiedispatch.ca; do
  [ -e "/etc/nginx/sites-enabled/${host%%.*}"* ] 2>/dev/null || true
  H=$(curl -sk -o /dev/null -D - --resolve "$host:443:127.0.0.1" "https://$host/" 2>/dev/null | tr -d '\r')
  [ -z "$H" ] && continue
  echo "$host:"
  echo "$H" | grep -iE '^(strict-transport|x-content-type|x-frame|x-pp-cache):' | sed 's/^/   /'
  C2=$(curl -sk -o /dev/null -w '%{header_json}' --resolve "$host:443:127.0.0.1" "https://$host/" 2>/dev/null | grep -o '"x-pp-cache":\[[^]]*\]' || true)
  [ -n "$C2" ] && echo "   second hit $C2 (HIT = the microcache is working)"
done
echo
echo "Done. Verify from outside: curl -sI https://prairiedispatch.ca/ | grep -i x-pp-cache"
echo "(two hits in a row: first MISS, second HIT; signed-in pages say BYPASS)"
