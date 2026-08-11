#!/usr/bin/env bash
# Civis Media — read-only VPS discovery. Prints NO passwords/tokens.
set +e; echo "===== CIVIS DISCOVERY $(date -u) ====="
echo "--- host ---"; hostname; lsb_release -d 2>/dev/null; echo
echo "--- web server + php ---"; nginx -v 2>&1; php -v 2>&1 | head -1
ls /run/php/*.sock 2>/dev/null; echo
echo "--- php extensions (need: pdo_pgsql mbstring simplexml curl fileinfo gd) ---"
php -m 2>/dev/null | grep -iE '^(pdo_pgsql|mbstring|simplexml|curl|fileinfo|gd|openssl)$' | sort | tr '\n' ' '; echo; echo
echo "--- release dir(s): where the app lives ---"
for d in /var/www/current /var/www/html /var/www/* /home/*/public_html /srv/*; do
  [ -f "$d/router.php" ] && [ -f "$d/app/bootstrap.php" ] && echo "APP ROOT: $d"
done 2>/dev/null | sort -u; echo
APP=$(for d in /var/www/current /var/www/* /home/*/public_html /srv/*; do
  [ -f "$d/router.php" ] && [ -f "$d/app/bootstrap.php" ] && echo "$d" && break; done 2>/dev/null)
echo "USING APP ROOT: ${APP:-NOT FOUND}"; echo
if [ -n "$APP" ]; then
  echo "--- git state ---"; git -C "$APP" remote -v 2>&1 | head -2
  git -C "$APP" branch --show-current 2>&1; git -C "$APP" log --oneline -1 2>&1
  git -C "$APP" status --short 2>&1 | head -10; echo
  echo "--- config.php host mapping (secrets filtered out) ---"
  if [ -f "$APP/config.php" ]; then
    grep -nE "site_slug|hub_slug|str_contains|HTTP_HOST|'driver'|'schema'|default =>" "$APP/config.php" \
      | grep -viE "pass|password|token|secret|key'|user'" | head -40
  else echo "NO config.php at app root"; fi; echo
  echo "--- is uploads/ shared or per-site? ---"; ls -la "$APP/uploads" 2>&1 | head -3; echo
fi
echo "--- nginx: enabled sites ---"; ls /etc/nginx/sites-enabled/ 2>/dev/null; echo
echo "--- nginx: the civismedia + one paper server_name/root/fastcgi ---"
grep -rEl "civismedia" /etc/nginx/ 2>/dev/null | while read f; do echo "### $f"; grep -nE "server_name|root |fastcgi_pass|ssl_certificate |rewrite |include" "$f" | head -25; done
echo "### (a paper for comparison)"; grep -rEl "edmontonecho|prairiedispatch" /etc/nginx/sites-enabled/ 2>/dev/null | head -1 | while read f; do echo "### $f"; grep -nE "server_name|root |fastcgi_pass|rewrite " "$f" | head -20; done; echo
echo "--- TLS certs present ---"; certbot certificates 2>/dev/null | grep -E "Certificate Name|Domains|Expiry" | head -40
ls /etc/letsencrypt/live/ 2>/dev/null; echo
echo "--- cron: any PP_SITE / fetch-news jobs ---"; crontab -l 2>/dev/null | grep -iE "fetch-news|PP_SITE|cron/" | head
ls /etc/cron.d/ 2>/dev/null | head; echo
echo "===== END DISCOVERY ====="
