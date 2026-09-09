#!/usr/bin/env bash
#
# Prove tools/baseline.sh classifies real failures with the exit codes the
# CI gate relies on. SLOW (each scenario seeds and renders the full
# network) — run it when baseline.sh or the gate changes, not per commit:
#
#   bash tools/test-baseline-classes.sh
#
# Scenarios, each in a disposable worktree of HEAD compared against HEAD:
#   identical tree            -> 0
#   template attribute change -> 10  (a render difference, nothing else)
#   induced HTTP 500          -> 3   (smoke failure, [render] can't excuse)
#   broken launch pack        -> 2   (seed failure)
#   invalid comparison ref    -> 4
#
set -uo pipefail
cd "$(dirname "$0")/.."
ROOT=$(pwd)
HEADSHA=$(git rev-parse HEAD)
FAILS=0

run_case() { # name expected_rc mutate_fn ref
  local name="$1" want="$2" mutate="$3" ref="${4:-$HEADSHA}"
  local wt
  wt=$(mktemp -d)
  git worktree add --detach "$wt" "$HEADSHA" >/dev/null 2>&1 || { echo "worktree failed"; exit 1; }
  "$mutate" "$wt"
  local rc=0
  (cd "$wt" && bash tools/baseline.sh "$ref" >/dev/null 2>&1) || rc=$?
  if [ "$rc" = "$want" ]; then
    echo "ok   $name -> exit $rc"
  else
    echo "FAIL $name -> exit $rc, wanted $want"
    FAILS=1
  fi
  git worktree remove --force "$wt" >/dev/null 2>&1
}

no_change() { :; }
break_migration() {
  # A head tree one schema version ahead whose new step THROWS: the base
  # fixture seeds fine, the head copy's migration fails → class 6. Real
  # divergence, no injection switches.
  sed -i "s/define('PP_SCHEMA_VERSION', [0-9]*);/define('PP_SCHEMA_VERSION', 99);/" "$1/app/bootstrap.php"
  php -r '
    $f = $argv[1] . "/app/db.php";
    $s = file_get_contents($f);
    $step = "        99 => function (PDO \$pdo, string \$driver): void {\n            throw new RuntimeException(\"induced migration failure for classification test\");\n        },\n\n    ];";
    $pos = strrpos($s, "    ];");
    file_put_contents($f, substr($s, 0, $pos) . $step . substr($s, $pos + strlen("    ];")));' "$1"
  grep -q 'induced migration failure' "$1/app/db.php" || { echo "FATAL: migration mutation did not land"; exit 1; }
}
content_eating_migration() {
  # A head tree whose new step silently DELETES content: preparation
  # "succeeds" but the fixtures stop being equivalent → class 7.
  sed -i "s/define('PP_SCHEMA_VERSION', [0-9]*);/define('PP_SCHEMA_VERSION', 99);/" "$1/app/bootstrap.php"
  php -r '
    $f = $argv[1] . "/app/db.php";
    $s = file_get_contents($f);
    $step = "        99 => function (PDO \$pdo, string \$driver): void {\n            \$pdo->exec(\"DELETE FROM posts WHERE id IN (SELECT id FROM posts LIMIT 1)\");\n            \$pdo->exec(\"UPDATE settings SET svalue = \x2799\x27 WHERE site_id = 0 AND skey = \x27schema_version\x27\");\n        },\n\n    ];";
    $pos = strrpos($s, "    ];");
    file_put_contents($f, substr($s, 0, $pos) . $step . substr($s, $pos + strlen("    ];")));' "$1"
  grep -q 'DELETE FROM posts WHERE id IN' "$1/app/db.php" || { echo "FATAL: divergence mutation did not land"; exit 1; }
}
render_change() { # a real, visible-but-harmless markup change on the article page
  sed -i 's/<article class="article wrap">/<article class="article wrap" data-baseline-probe="1">/' "$1/article.php"
  grep -q 'baseline-probe' "$1/article.php" || { echo "FATAL: render mutation did not land — fix the anchor before trusting this harness"; exit 1; }
}
induce_500() {
  printf '<?php throw new RuntimeException("induced 500 for classification test");\n' > "$1/article.php"
}
break_seed() {
  printf '<?php throw new RuntimeException("induced pack failure");\n' > "$1/assets/sites/pickering-post/launch.php"
}

echo "== baseline.sh classification (each line seeds + renders the network; minutes, not seconds)"
run_case "identical tree"        0  no_change
run_case "render difference"     10 render_change
run_case "induced HTTP 500"      3  induce_500
run_case "broken launch pack"    2  break_seed
run_case "invalid ref"           4  no_change "refs/heads/does-not-exist-$$"
run_case "migration failure"     6  break_migration
run_case "content-eating step"   7  content_eating_migration

if [ "$FAILS" = "1" ]; then
  echo "CLASSIFICATION BROKEN — the CI gate cannot be trusted until this passes."
  exit 1
fi
echo "All classifications correct."
