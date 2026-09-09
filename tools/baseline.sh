#!/usr/bin/env bash
#
# The render baseline: every public page type on every paper, compared
# between two trees — each tree against its OWN fixture. The base fixture
# is seeded by the comparison ref's own tooling; the head fixture is an
# exact copy of it that ONLY the head tree's migration runner then
# prepares. Schema changes between the trees therefore cannot poison the
# comparison, and neither tree can touch the other's database.
#
#   tools/baseline.sh              smoke this tree only (own fixture)
#   tools/baseline.sh REF          seed base fixture with REF's tooling,
#                                  copy → head fixture, migrate the copy,
#                                  render both, byte-diff.
#
# Exit codes are a CLASSIFICATION — tools/render-gate.sh relies on them,
# and a declared [render] may excuse ONLY exit 10:
#
#   0   pages identical (or smoke-only run passed)
#   10  rendered output differs — nothing else wrong
#   2   THIS tree's fixture seed/preparation failed
#   3   THIS tree fails the smoke contract (5xx, wrong masthead, empty page)
#   4   comparison-ref infrastructure: can't check out, predates the
#       harness, ITS seed failed, or ITS render fails the smoke contract
#   5   no pages could be derived from the seeded database
#   6   the head tree's migration of the copied fixture FAILED
#   7   fixture divergence: the migrated copy's logical content no longer
#       matches the base fixture, or a tree wrote into the other's fixture
#
# The smoke contract per page: HTTP 200, a non-empty body, and the front
# page carrying its own site's exact title.
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

mkconfig() { # sqlite-file out-file
  cat > "$2" <<PHP
<?php
return ['db' => ['driver' => 'sqlite', 'sqlite_path' => '$1'],
        'site_slug' => 'prairiedispatch', 'hub_slug' => 'civismedia',
        'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];
PHP
}
mkconfig "$WORK/head.sqlite" "$WORK/config-now.php"
mkconfig "$WORK/base.sqlite" "$WORK/config-base.php"

db_counts() { # sqlite-file -> "version|sites|posts|domains"
  php -r '$p = new PDO("sqlite:" . $argv[1]);
    $v = $p->query("SELECT svalue FROM settings WHERE site_id = 0 AND skey = \"schema_version\"")->fetchColumn();
    $s = $p->query("SELECT COUNT(*) FROM sites")->fetchColumn();
    $o = $p->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    $d = $p->query("SELECT COUNT(*) FROM domains")->fetchColumn();
    echo "$v|$s|$o|$d";' "$1" 2>/dev/null || echo "?|?|?|?"
}

if [ -n "$REF" ]; then
  echo "== Checking out $REF"
  git worktree add --detach "$WORK/ref" "$REF" >/dev/null 2>&1 || { echo "cannot check out $REF"; exit 4; }
  [ -f "$WORK/ref/tools/seed-all.sh" ] || { echo "$REF predates the seed harness — not comparable"; exit 4; }

  echo "== Seeding the BASE fixture with $REF's own tooling"
  ( cd "$WORK/ref" && bash tools/seed-all.sh "$WORK/base.sqlite" >/dev/null 2>&1 ) \
    || { echo "the comparison ref's seed failed — comparison infrastructure, not a render change"; exit 4; }
  BASE_BEFORE=$(db_counts "$WORK/base.sqlite")
  echo "   base fixture: version|sites|posts|domains = $BASE_BEFORE"

  echo "== Copying base -> head fixture (VACUUM INTO; all writers closed)"
  php -r '$p = new PDO("sqlite:" . $argv[1]); $p->exec("VACUUM INTO " . $p->quote($argv[2]));' \
      "$WORK/base.sqlite" "$WORK/head.sqlite" \
    || { echo "fixture copy failed"; exit 2; }

  echo "== Migrating ONLY the head fixture with this tree's runner"
  if ! PP_CONFIG="$WORK/config-now.php" php "$ROOT/tools/migrate.php" --apply > "$WORK/migrate.log" 2>&1; then
    cat "$WORK/migrate.log"
    echo "HEAD FIXTURE MIGRATION FAILED — no declaration excuses this."
    exit 6
  fi
  HEAD_AFTER=$(db_counts "$WORK/head.sqlite")
  echo "   head fixture: version|sites|posts|domains = $HEAD_AFTER"
  # Semantic equivalence: migration prepares, it must not create or lose
  # content. Versions may differ; the logical counts may not.
  if [ "${BASE_BEFORE#*|}" != "${HEAD_AFTER#*|}" ]; then
    echo "FIXTURE DIVERGENCE: migration changed logical content ($BASE_BEFORE -> $HEAD_AFTER)"
    exit 7
  fi
else
  echo "== Seeding this tree's fixture"
  bash "$ROOT/tools/seed-all.sh" "$WORK/head.sqlite" >/dev/null \
    || { echo "seed-all failed — run it directly for the detail"; exit 2; }
fi

# The page list per site: front, one real story (wire LINK posts answer
# /story/… with a 302 by design and are not smoke material), one desk,
# search, feed, sitemap. Derived from the HEAD fixture; the slugs exist
# identically in the base fixture, which is its unmigrated twin.
# Column 4 carries the site's own title — the smoke's masthead assertion.
PAGES=$(PP_CONFIG="$WORK/config-now.php" php -r '
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
    $s = $pdo->prepare("SELECT p.slug FROM posts p JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = ? WHERE p.status = ? AND COALESCE(p.post_type, ?) != ? ORDER BY p.id LIMIT 1");
    $s->execute([(int) $site["id"], "published", "story", "link"]);
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

snapshot() { # tree_dir out_dir config_file
  local tree="$1" out="$2" cfg="$3" fails=0
  mkdir -p "$out"
  (cd "$tree" && PP_CONFIG="$cfg" php -S 127.0.0.1:$PORT router.php >"$out/.server.log" 2>&1 &)
  sleep 1.5
  while IFS=$(printf '\t') read -r slug host path expect; do
    local file="$out/${slug}$(echo "$path" | tr '/?&=' '____').html"
    local code
    code=$(curl -s -m 20 -H "Host: $host" -o "$file" -w '%{http_code}' "http://127.0.0.1:$PORT$path")
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
  [ -f "$WORK/migrate.log" ] && cp "$WORK/migrate.log" "$PP_BASELINE_OUT/migrate.log"
}

echo "== Rendering this tree (own fixture, own server, own config)"
if ! snapshot "$ROOT" "$WORK/now" "$WORK/config-now.php"; then
  publish_artifacts
  echo "FATAL: this tree fails the smoke contract — fix before comparing"
  exit 3
fi

if [ -z "$REF" ]; then
  echo "Smoke pass: every page answered 200 with its own masthead."
  exit 0
fi

echo "== Rendering $REF (base fixture, its own server and config)"
# The ref tree may predate PP_CONFIG support, so it gets the base config as
# a real file — the worktree is disposable, so this is safe.
cp "$WORK/config-base.php" "$WORK/ref/config.php"
if ! snapshot "$WORK/ref" "$WORK/base" "$WORK/config-base.php"; then
  publish_artifacts
  echo "The comparison ref $REF fails the smoke contract itself — the diff"
  echo "would be meaningless. Comparison infrastructure, not a render change."
  exit 4
fi

# Isolation proof: rendering the ref must not have touched ITS fixture's
# logical state, and nothing may have leaked into the other tree's file.
BASE_AFTER=$(db_counts "$WORK/base.sqlite")
if [ "$BASE_AFTER" != "$BASE_BEFORE" ]; then
  echo "FIXTURE DIVERGENCE: the base fixture changed during rendering ($BASE_BEFORE -> $BASE_AFTER)"
  exit 7
fi
HEAD_NOW=$(db_counts "$WORK/head.sqlite")
if [ "$HEAD_NOW" != "$HEAD_AFTER" ]; then
  echo "FIXTURE DIVERGENCE: the head fixture changed during rendering ($HEAD_AFTER -> $HEAD_NOW)"
  exit 7
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
