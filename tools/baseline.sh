#!/usr/bin/env bash
#
# The render baseline: every public page type on every paper, against a
# freshly seeded full-network database, compared between two trees.
#
#   tools/baseline.sh              snapshot this tree only (smoke: no 5xx)
#   tools/baseline.sh REF          snapshot this tree AND git ref REF from
#                                  the same database, then diff. Nonzero
#                                  exit on any real difference.
#
# This harness has caught three shipped-invisible defects; committing it
# is Phase 0.1. Both trees render against the SAME database within the
# same run, so content is identical by construction and any diff is code.
#
set -uo pipefail
cd "$(dirname "$0")/.."
ROOT=$(pwd)
REF="${1:-}"

WORK=$(mktemp -d)
PORT=$((8300 + RANDOM % 90))
cleanup() {
  pgrep -f "php -S 127.0.0.1:$PORT" | while read -r pid; do kill "$pid" 2>/dev/null; done
  [ -n "$REF" ] && git worktree remove --force "$WORK/ref" 2>/dev/null
  if [ -n "${PP_BASELINE_KEEP:-}" ]; then echo "kept: $WORK"; else rm -rf "$WORK"; fi
}
trap cleanup EXIT

echo "== Seeding the full network once (both trees read the same file)"
bash "$ROOT/tools/seed-all.sh" "$WORK/net.sqlite" >/dev/null || { echo "seed-all failed — run it directly for the detail"; exit 1; }

cat > "$WORK/config.php" <<PHP
<?php
return ['db' => ['driver' => 'sqlite', 'sqlite_path' => '$WORK/net.sqlite'],
        'site_slug' => 'prairiedispatch', 'hub_slug' => 'civismedia',
        'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];
PHP

# The page list per site: front, one story, one desk, search, feed,
# sitemap. Derived from the database so a new paper joins automatically.
PAGES=$(PP_CONFIG="$WORK/config.php" php -r '
require "app/bootstrap.php";
$pdo = db();
foreach ($pdo->query("SELECT id, slug FROM sites ORDER BY id") as $site) {
    $h = $pdo->prepare("SELECT hostname FROM domains WHERE site_slug = ? ORDER BY LENGTH(hostname), hostname LIMIT 1");
    $h->execute([$site["slug"]]);
    $host = (string) ($h->fetchColumn() ?: "");
    if ($host === "") continue;
    $s = $pdo->prepare("SELECT p.slug FROM posts p JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = ? WHERE p.status = ? ORDER BY p.id LIMIT 1");
    $s->execute([(int) $site["id"], "published"]);
    $story = (string) ($s->fetchColumn() ?: "");
    $d = $pdo->prepare("SELECT c.slug FROM categories c JOIN posts p ON p.category_id = c.id JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = ? LIMIT 1");
    $d->execute([(int) $site["id"]]);
    $desk = (string) ($d->fetchColumn() ?: "");
    echo $site["slug"], "\t", $host, "\t/\n";
    if ($story !== "") echo $site["slug"], "\t", $host, "\t/story/", $story, "\n";
    if ($desk !== "")  echo $site["slug"], "\t", $host, "\t/desk/", $desk, "\n";
    echo $site["slug"], "\t", $host, "\t/search?q=council\n";
    echo $site["slug"], "\t", $host, "\t/feed/\n";
    echo $site["slug"], "\t", $host, "\t/sitemap.xml\n";
}')
[ -n "$PAGES" ] || { echo "no pages derived — is the seed empty?"; exit 1; }
echo "   $(echo "$PAGES" | wc -l) pages across $(echo "$PAGES" | cut -f1 | sort -u | wc -l) sites"

snapshot() { # tree_dir out_dir
  local tree="$1" out="$2" fails=0
  mkdir -p "$out"
  (cd "$tree" && PP_CONFIG="$WORK/config.php" php -S 127.0.0.1:$PORT router.php >"$out/.server.log" 2>&1 &)
  sleep 1.5
  while IFS=$(printf '\t') read -r slug host path; do
    local file="$out/${slug}$(echo "$path" | tr '/?&=' '____').html"
    local code
    code=$(curl -s -m 20 -H "Host: $host" -o "$file" -w '%{http_code}' "http://127.0.0.1:$PORT$path")
    if [ "$code" -ge 500 ] || [ "$code" = "000" ]; then
      echo "   FAIL $host$path -> $code"
      fails=1
    fi
    # Volatile lines that are clock, not code: the chrome's live date
    # banner, relative time labels, and the feeds' build stamp (now()-
    # valued, so two renders seconds apart always differ there).
    sed -i -E 's/[A-Z]+DAY, [A-Z]+ [0-9]{1,2}, [0-9]{4}//; s/\b(Today|Yesterday)\b//; s|<lastBuildDate>[^<]*</lastBuildDate>|<lastBuildDate/>|' "$file"
  done <<< "$PAGES"
  pgrep -f "php -S 127.0.0.1:$PORT" | while read -r pid; do kill "$pid" 2>/dev/null; done
  sleep 0.5
  return $fails
}

echo "== Rendering this tree"
snapshot "$ROOT" "$WORK/now" || { echo "FATAL: this tree serves errors — fix before comparing"; exit 1; }

if [ -z "$REF" ]; then
  echo "Smoke pass: every page rendered without a server error."
  exit 0
fi

echo "== Rendering $REF"
git worktree add --detach "$WORK/ref" "$REF" >/dev/null 2>&1 || { echo "cannot check out $REF"; exit 1; }
# The ref tree may predate PP_CONFIG support, so it gets the throwaway
# config as a real file — the worktree is disposable, so this is safe,
# and it makes any historical ref comparable.
cp "$WORK/config.php" "$WORK/ref/config.php"
snapshot "$WORK/ref" "$WORK/base" || { echo "note: $REF itself serves errors; differences below include them"; }

echo "== Comparing"
if diff -qr "$WORK/base" "$WORK/now" --exclude='.server.log' > "$WORK/diff.txt"; then
  echo "Byte-identical against $REF across every page."
  exit 0
fi
echo "RENDERED OUTPUT CHANGED against $REF:"
sed 's/^/   /' "$WORK/diff.txt" | head -40
echo
echo "If this PR deliberately changes rendered output, say so with [render]"
echo "in the PR title; otherwise this is a regression."
exit 1
