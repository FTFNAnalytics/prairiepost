#!/usr/bin/env bash
#
# The CI render gate: translate tools/baseline.sh's exit classification
# into a pass/fail, honouring a declared [render] for INTENDED rendered-
# output differences and nothing else.
#
#   bash tools/render-gate.sh <baseline-exit-code>
#
# The PR title arrives ONLY through the PR_TITLE environment variable and
# is only ever compared as data — it is never interpolated into shell
# source, so a title full of quotes, backticks or $(…) is inert text.
#
set -u
rc="${1:?usage: render-gate.sh <baseline-exit-code>}"
title="${PR_TITLE:-}"

declared=0
case "$title" in *"[render]"*) declared=1 ;; esac

case "$rc" in
  0)
    echo "GATE PASS: rendered output is byte-identical to the base branch."
    exit 0
    ;;
  10)
    if [ "$declared" = "1" ]; then
      echo "GATE PASS: rendered output changed and the PR title declares [render]."
      echo "Reviewers: inspect the render-baseline artifact for the actual diff."
      exit 0
    fi
    echo "GATE FAIL: rendered output changed and the PR does not declare it."
    echo "If the change is deliberate, add [render] to the PR title."
    exit 1
    ;;
  2)  echo "GATE FAIL: this tree's fixture seed/preparation failed. [render] cannot excuse this." ; exit 1 ;;
  3)  echo "GATE FAIL: this tree fails the smoke contract (server error, empty page, or wrong masthead). [render] cannot excuse this." ; exit 1 ;;
  4)  echo "GATE FAIL: the comparison ref is invalid or itself broken — fix the comparison, don't declare around it." ; exit 1 ;;
  5)  echo "GATE FAIL: no pages could be derived from the seeded database." ; exit 1 ;;
  6)  echo "GATE FAIL: MIGRATING the head fixture failed — a schema problem, never a visual one. [render] cannot excuse this." ; exit 1 ;;
  7)  echo "GATE FAIL: fixture divergence — the fixtures stopped being equivalent, or a tree wrote into the other's database. [render] cannot excuse this." ; exit 1 ;;
  *)  echo "GATE FAIL: baseline exited $rc (unclassified) — treated as a genuine failure." ; exit 1 ;;
esac
