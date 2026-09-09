#!/usr/bin/env bash
#
# The render baseline: every public page type on every paper, against a
# freshly seeded full-network database, compared between two trees.
#
#   tools/baseline.sh              smoke this tree only
#   tools/baseline.sh REF          smoke this tree AND git ref REF from the
#                                  same database, then byte-diff.
#
# Exit codes are a CLASSIFICATION, not a boolean — the CI gate
# (tools/render-gate.sh) relies on them to tell an intended visual change
# from a genuine failure. A declared [render] may excuse ONLY exit 10.
#
#   0   pages identical (or smoke-only run passed)
#   10  rendered output differs — nothing else wrong
#   2   the full-network seed failed
#   3   THIS tree fails the smoke contract (5xx, wrong masthead, empty page)
#   4   the comparison ref can't be checked out, or renders errors itself
#   5   no pages could be derived from the seeded database
#
# The smoke contract per page: HTTP 200, a non-empty body, and the front
# page carrying its own site's exact title — a blank 200 or another
# paper's masthead is a failure, not a render change.
#
# PP_BASELINE_OUT=dir  copy both snapshots and the diff there (CI artifact)
# PP_BASELINE_KEEP=1   keep the work directory for inspection
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
bash "$ROOT/tools/seed-all.sh" "$WORK/net.sqlite" >/dev/null || { echo "seed-all failed — run it directly for the detail"; exit 2; }

cat > "$WORK/config.php" <<PHP
<?php
return ['db' => ['driver' => 'sqlite', 'sqlite_path' => '$WORK/net.sqlite'],
        'site_slug' => 'prairiedispatch', 'hub_slug' => 'civismedia',
        'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];
PHP

# The page list per site: front, one story, one desk, search, feed,
# sitemap. Derived from the database so a new paper joins automatically.
# Column 4 carries the site's own title — the smoke's masthead assertion.
PAGES=$(PP_CONFIG="$WORK/config.php" php -r '
require "app/bootstrap.php";
$pdo = db();
foreach ($pdo->query("SELECT id, slug FROM sites ORDER BY id") as $site) {
    $h = $pdo->prepare("SELECT hostname FROM domains WHERE site_slug = ? ORDER BY LENGTH(hostname), hostname LIMIT 1");
    $h->execute([$site["slug"]]);
    $host = (string) ($h->fetchColumn() ?: "");
    if ($host === "") continue;
    $t = $pdo->prepare("SELECT svalue FROM settings WHERE site_id = ? AND skey = ?");
    $t->execute([(int) $site["id"], "site_title"]);
    $title = (string) ($t->fetchColumn() ?: "");
    $s = $pdo->prepare("SELECT p.slug FROM posts p JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = ? WHERE p.status = ? ORDER BY p.id LIMIT 1");
    $s->execute([(int) $site["id"], "published"]);
    $story = (string) ($s->fetchColumn() ?: "");
    $d = $pdo->prepare("SELECT c.slug FROM categories c JOIN posts p ON p.category_id = c.id JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = ? LIMIT 1");
    $d->execute([(int) $site["id"]]);
    $desk = (string) ($d->fetchColumn() ?: "");
    echo $site["slug"], "\t", $host, "\t/\t", $title, "\n";
    if ($story !== "") echo $site["slug"], "\t", $host, "\t/story/", $story, "\t\n";
    if ($desk !== "")  echo $site["slug"], "\t", $host, "\t/desk/", $desk, "\t\n";
    echo $site["slug"], "\t", $host, "\t/search?q=council\t\n";
    echo $site["slug"], "\t", $host, "\t/feed/\t\n";
    echo $site["slug"], "\t", $host, "\t/sitemap.xml\t\n";
}')
[ -n "$PAGES" ] || { echo "no pages derived — is the seed empty?"; exit 5; }
echo "   $(echo "$PAGES" | wc -l) pages across $(echo "$PAGES" | cut -f1 | sort -u | wc -l) sites"

snapshot() { # tree_dir out_dir
  local tree="$1" out="$2" fails=0
  mkdir -p "$out"
  (cd "$tree" && PP_CONFIG="$WORK/config.php" php -S 127.0.0.1:$PORT router.php >"$out/.server.log" 2>&1 &)
  sleep 1.5
  while IFS=$(printf '\t') read -r slug host path expect; do
    local file="$out/${slug}$(echo "$path" | tr '/?&=' '____').html"
    local code
    code=$(curl -s -m 20 -H "Host: $host" -o "$file" -w '%{http_code}' "http://127.0.0.1:$PORT$path")
    # The smoke contract: 200, non-empty, and the front page wears its own
    # masthead. Anything else is a failure — never a "render difference".
    if [ "$code" != "200" ]; then
      echo "   FAIL $host$path -> HTTP $code"
      fails=1
    elif [ ! -s "$file" ]; then
      echo "   FAIL $host$path -> 200 but an empty body"
      fails=1
    elif [ -n "$expect" ] && ! grep -qF "$expect" "$file"; then
      echo "   FAIL $host$path -> 200 but does not carry its own title ($expect)"
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

publish_artifacts() {
  [ -n "${PP_BASELINE_OUT:-}" ] || return 0
  mkdir -p "$PP_BASELINE_OUT"
  [ -d "$WORK/now" ] && cp -r "$WORK/now" "$PP_BASELINE_OUT/now"
  [ -d "$WORK/base" ] && cp -r "$WORK/base" "$PP_BASELINE_OUT/base"
  [ -f "$WORK/diff.txt" ] && cp "$WORK/diff.txt" "$PP_BASELINE_OUT/diff.txt"
}

echo "== Rendering this tree"
if ! snapshot "$ROOT" "$WORK/now"; then
  publish_artifacts
  echo "FATAL: this tree fails the smoke contract — fix before comparing"
  exit 3
fi

if [ -z "$REF" ]; then
  echo "Smoke pass: every page answered 200 with its own masthead."
  exit 0
fi

echo "== Rendering $REF"
git worktree add --detach "$WORK/ref" "$REF" >/dev/null 2>&1 || { echo "cannot check out $REF"; exit 4; }
# The ref tree may predate PP_CONFIG support, so it gets the throwaway
# config as a real file — the worktree is disposable, so this is safe,
# and it makes any historical ref comparable.
cp "$WORK/config.php" "$WORK/ref/config.php"
if ! snapshot "$WORK/ref" "$WORK/base"; then
  publish_artifacts
  echo "The comparison ref $REF fails the smoke contract itself — the diff"
  echo "would be meaningless. This is comparison infrastructure, not a"
  echo "render change; no declaration excuses it."
  exit 4
fi

echo "== Comparing"
if diff -qr "$WORK/base" "$WORK/now" --exclude='.server.log' > "$WORK/diff.txt"; then
  echo "Byte-identical against $REF across every page."
  exit 0
fi
publish_artifacts
echo "RENDERED OUTPUT CHANGED against $REF:"
sed 's/^/   /' "$WORK/diff.txt" | head -40
echo
echo "If this PR deliberately changes rendered output, declare it with"
echo "[render] in the PR title; the gate then accepts THIS difference only."
exit 10
