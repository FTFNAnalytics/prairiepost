#!/usr/bin/env bash
#
# The upgrader's two nginx edits, against the block shapes that are
# actually on the box.
#
# Five hand-written blocks nest their first `location ~ \.php$` inside
# `location ^~ /admin/`, and also have a top-level `location /` whose
# try_files 404s. The 388f1bd roll inserted `location = /router.php`
# in front of that nested handler. nginx refused the file
# ("location \"/router.php\" is outside location \"/admin/\"") and
# rolled back before the schema step. The same edit rewrote the admin
# fallback. These fixtures are those five files, plus portagepress
# (a generated block that already has the front controller) and two
# synthetic shapes the release-flow fixture uses.
#
#   bash tools/test-nginx-edits.sh
#
set -uo pipefail
ROOT=$(cd "$(dirname "$0")/.." && pwd)
PP_UPGRADE_LIB_ONLY=1
# shellcheck disable=SC1091
source "$ROOT/tools/vps/upgrade-papers.sh"

fail=0
ok() { # cond, label
  if eval "$1"; then
    printf 'PASS %s\n' "$2"
  else
    printf 'FAIL %s\n' "$2"
    fail=$((fail + 1))
  fi
}

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT
FIX=$ROOT/tools/vps/fixtures/nginx

# Where a `location = /router.php` sits: 1 means a direct child of server.
# Prints "depth N" per occurrence, one per line.
router_depths() {
  awk '
    function delta(s,    i, c, n, q) {
      n = 0; q = 0
      for (i = 1; i <= length(s); i++) {
        c = substr(s, i, 1)
        if (c == "\"" && !q) q = 1
        else if (c == "\"" && q) q = 0
        else if (c == "#" && !q) break
        else if (!q && c == "{") n++
        else if (!q && c == "}") n--
      }
      return n
    }
    BEGIN { depth = 0 }
    {
      if ($0 ~ /^[[:space:]]*location[[:space:]]+=[[:space:]]*\/router\.php[[:space:]]*\{/) print depth
      depth += delta($0)
      if (depth < 0) depth = 0
    }' "$1"
}

# nginx -t cannot load the live cert paths. Drop them; the location
# grammar is what this is checking.
nginx_ok() { # file
  local conf=$WORK/nginx.conf site=$WORK/site.conf
  # nginx takes the directory of -c as its prefix, so the relative
  # includes these blocks use (snippets/, fastcgi_params) have to live
  # there. The certificates do not: -t never speaks TLS.
  ln -sfn /etc/nginx/fastcgi.conf "$WORK/fastcgi.conf"
  ln -sfn /etc/nginx/fastcgi_params "$WORK/fastcgi_params"
  mkdir -p "$WORK/snippets"
  ln -sfn /etc/nginx/snippets/fastcgi-php.conf "$WORK/snippets/fastcgi-php.conf"
  # One listen per server: the live files name 80 and 443, and both
  # families, and this user cannot bind 443. The grammar check does not
  # care which port it is.
  awk '
    /^[[:space:]]*server[[:space:]]*\{/ { seen = 0 }
    /^[[:space:]]*listen[[:space:]]/ {
      if (seen++) next
      print "    listen 127.0.0.1:8088;"
      next
    }
    /^[[:space:]]*ssl_certificate/ { next }
    /^[[:space:]]*ssl_certificate_key/ { next }
    /ssl_dhparam/ { next }
    /options-ssl-nginx/ { next }
    /prairiepost-deny\.conf/ { next }
    /access_log[[:space:]]+\// { next }
    /error_log[[:space:]]+\// { next }
    { print }
  ' "$1" > "$site"
  mkdir -p "$WORK/body" "$WORK/proxy" "$WORK/fastcgi" "$WORK/uwsgi" "$WORK/scgi"
  cat > "$conf" <<EOF
pid $WORK/nginx.pid;
error_log $WORK/error.log;
events {}
http {
  access_log $WORK/access.log;
  error_log $WORK/error.log;
  client_body_temp_path $WORK/body;
  proxy_temp_path $WORK/proxy;
  fastcgi_temp_path $WORK/fastcgi;
  uwsgi_temp_path $WORK/uwsgi;
  scgi_temp_path $WORK/scgi;
  include $site;
}
EOF
  nginx -t -c "$conf" >/dev/null 2>"$WORK/nginx.err"
}

apply_both() { # file -> the editors' exit codes are not the test
  pp_front_controller "$1" || true
  pp_tips_unlogged "$1" || true
  pp_icon_routes "$1" || true
}

# Exact icon locations, one each, and only at server level. None of them
# names /assets or /uploads, so a real file there still hits the static regex.
icon_exact_ok() { # file
  local path depth
  for path in /favicon.ico /favicon.svg /apple-touch-icon.png /apple-touch-icon-precomposed.png \
              /icon-16.png /icon-32.png /icon-48.png /icon-180.png /icon-192.png /icon-512.png \
              /site.webmanifest; do
    depth=$(awk -v p="$path" '
      function delta(s,    i, c, n, q) {
        n = 0; q = 0
        for (i = 1; i <= length(s); i++) {
          c = substr(s, i, 1)
          if (c == "\"" && !q) q = 1
          else if (c == "\"" && q) q = 0
          else if (c == "#" && !q) break
          else if (!q && c == "{") n++
          else if (!q && c == "}") n--
        }
        return n
      }
      BEGIN { depth = 0; n = 0 }
      {
        if ($0 ~ "^[[:space:]]*location[[:space:]]+=[[:space:]]*" p "[[:space:]]*\\{") {
          if (depth == 1) n++
          else n += 100
        }
        depth += delta($0)
        if (depth < 0) depth = 0
      }
      END { print n }
    ' "$1")
    [ "$depth" = 1 ] || return 1
  done
  ! grep -q 'location = /assets' "$1" && ! grep -q 'location = /uploads' "$1"
}

# --- The five nested blocks ------------------------------------------------

for name in edmontonecho grandeprairiegazette kermodechronicle prairiedispatch thepacificpost; do
  src=$(echo "$FIX"/${name}_*.conf)
  cp "$src" "$WORK/$name.conf"
  apply_both "$WORK/$name.conf"
  depths=$(router_depths "$WORK/$name.conf" | tr '\n' ' ')
  ok '[ "$depths" = "1 " ]' "$name: location = /router.php is a child of server, once (depths: ${depths:-none})"
  # The admin fallback stays a 404. The front's does not.
  admin=$(awk '
    /^[[:space:]]*location[[:space:]]+\^~[[:space:]]+\/admin\// { a=1 }
    a && /try_files/ { print; exit }
  ' "$WORK/$name.conf")
  front=$(awk '
    /^[[:space:]]*location[[:space:]]+\/[[:space:]]*\{/ { f=1 }
    f && /try_files/ { print; exit }
  ' "$WORK/$name.conf")
  ok 'grep -q "=404" <<< "$admin" && ! grep -q router.php <<< "$admin"' "$name: the /admin/ fallback is still =404"
  ok 'grep -q "try_files \$uri \$uri/ /router.php\$is_args\$args;" <<< "$front"' "$name: location / falls through to router.php"
  ok '[ "$(grep -c pp-tips-unlogged "$WORK/$name.conf")" = 1 ]' "$name: /tips is unlogged once"
  ok '! awk "/location \\^~ \\/admin\\//,/^    }/" "$WORK/$name.conf" | grep -q "location = /router.php\\|pp-tips-unlogged"' \
    "$name: neither new location landed inside /admin/"
  before=$(cat "$WORK/$name.conf")
  apply_both "$WORK/$name.conf"
  ok '[ "$before" = "$(cat "$WORK/$name.conf")" ]' "$name: a second run changes nothing"
  static=$(grep -cF 'location ~* \.(?:svg|png|jpe?g|gif|webp|ico|woff2?|ttf)$' "$WORK/$name.conf" || true)
  ok '[ "$static" = 1 ]' "$name: the static-file regex is still there for /assets and /uploads"
  ok 'icon_exact_ok "$WORK/$name.conf"' "$name: every icon and manifest URL is an exact server-level location"
  ok 'nginx_ok "$WORK/$name.conf"' "$name: nginx -t accepts the edited block"
  if ! nginx_ok "$WORK/$name.conf"; then
    sed 's/^/    /' "$WORK/nginx.err"
  fi
done

# --- A generated block already has the front controller --------------------

cp "$FIX"/portagepress_*.conf "$WORK/portage.conf"
orig=$(cat "$WORK/portage.conf")
pp_front_controller "$WORK/portage.conf"; rc=$?
ok '[ $rc != 0 ] && [ "$orig" = "$(cat "$WORK/portage.conf")" ]' "portage: the front-controller edit leaves a generated block untouched"
apply_both "$WORK/portage.conf"
ok '[ "$(grep -c pp-tips-unlogged "$WORK/portage.conf")" = 1 ]' "portage: /tips is added once, beside its own PHP handler"
ok '[ "$(grep -c "location = /router.php" "$WORK/portage.conf")" = 1 ]' "portage: still one router.php location"
ok 'icon_exact_ok "$WORK/portage.conf"' "portage: the generated block gets the same exact icon locations"
ok 'nginx_ok "$WORK/portage.conf"' "portage: nginx -t accepts it"

# --- The shapes the release-flow fixture builds ----------------------------

printf 'server {\n  root /var/www/old;\n  server_name kitchenerchronicle.test;\n  location ~ \\.php$ {\n    fastcgi_pass unix:/run/php/php8.3-fpm.sock;\n  }\n  location / { try_files $uri $uri/ =404; }\n}\n' > "$WORK/kitchener.conf"
apply_both "$WORK/kitchener.conf"
ok '[ "$(grep -c pp-front-controller "$WORK/kitchener.conf")" = 1 ] && grep -qF "try_files \$uri \$uri/ /router.php\$is_args\$args;" "$WORK/kitchener.conf" && ! grep -qF "=404;" "$WORK/kitchener.conf"' \
  "a flat legacy block's only fallback becomes router.php"
ok '[ "$(grep -c pp-tips-unlogged "$WORK/kitchener.conf")" = 1 ]' "a flat legacy block keeps /tips out of the access log, once"
ok 'icon_exact_ok "$WORK/kitchener.conf"' "a flat legacy block gets the exact icon locations"
ok 'nginx_ok "$WORK/kitchener.conf"' "a flat legacy block passes nginx -t"

printf 'server {\n  root /var/www/old;\n  server_name civismedia.test;\n}\n' > "$WORK/civis.conf"
civ=$(cat "$WORK/civis.conf")
apply_both "$WORK/civis.conf"
ok '[ "$civ" = "$(cat "$WORK/civis.conf")" ]' "a block with no PHP handler is left alone"
ok '! grep -q pp-icon-routes "$WORK/civis.conf"' "a block with no PHP handler gains no icon locations"

echo
if [ "$fail" -eq 0 ]; then
  echo "all nginx-edit checks passed"
  exit 0
fi
echo "$fail check(s) FAILED"
exit 1
