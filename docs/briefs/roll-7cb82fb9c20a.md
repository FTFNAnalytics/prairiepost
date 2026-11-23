# Brief — roll the network to 7cb82fb9c20a

**For: the VPS agent** (root SSH; server work only). Written 23 Nov 2026.

You are rolling every prairiepost release group (the papers and the
CivisMedia hub) to commit **`7cb82fb9c20aa53c886e80bbe7896bffe3da1bb4`**
with `tools/vps/upgrade-papers.sh`. Then you run a routes pass on the
nginx blocks and verify the whole network. The upgrader does the roll
itself: it backs up and repoints vhosts and cron, migrates the shared
schema once, checks every masthead and rolls back what it can. Your job is
the checks around it, the routes pass it doesn't do, and an exact report.

## What this roll ships

Production was last confirmed on `4ff557d2b737` (21 Nov). A brief for
`835f90b85d2a` went out on 22 Nov and no report came back, so either may
be in service. This commit contains everything since both:

| PR | What it brings | Schema |
| --- | --- | --- |
| #81 (`835f90b`) | the published-story update and story-read lanes for ingest keys | step 24 |
| #82 | the draft-edit lane and the full published-story edit; the 10 MB upload limit (a release-root `.user.ini`, and `client_max_body_size 10m` in `tools/vps/vhost.template`) | step 25 |
| #84 | each paper's newsroom sign-in drawn inside that paper's own masthead | — |
| #86 | the Stories list's bulk status change | — |
| #87 | each paper's admin wears that paper's brand; the hub is unchanged | — |

Steps 24 and 25 are additive columns, no backfill. They are **forward
only**. Once either has applied, a release built for an older version
answers 503 on every paper, so the old release directories stop being a
rollback (`docs/MIGRATION-COMPAT.md`, "The table"). Between the migration
committing and nginx reloading onto the new release, every paper answers
503 for a few seconds. That window is expected.

## Ground rules

- **Server work only.** You hold no ingest token. Don't file, edit or
  publish stories. Don't mint, revoke or re-mint ingest keys: new grants
  for the agents' keys are a separate order from the owner.
- **No manual database edits.** Don't run `tools/migrate.php --apply`
  yourself. The upgrader migrates, exactly once.
- **No config edits.** Leave `config.php` and `app/config.site.php`
  untouched in every release.
- **Discover server facts; don't take them from this brief.** Paths,
  counts and release names below are what was true when it was written,
  given so you can recognise a match.
- **Foreign tenants are out of scope.** That's the Institute (`/var/www/cies-*`)
  and Calgary Dispatch (`/srv/calgarydispatch/`). Never require anything of
  them. One shared fact applies: a broken block anywhere fails the global
  `nginx -t`.
- **Hard stops:** a verification item that fails twice; any `nginx -t`
  failure; the upgrader exiting non-zero or printing `FATAL`; any STOP
  below. On a stop, leave the box as it is (except where this brief says
  how to undo your own step), report exactly, and wait.

Work in `/root/roll-7cb82fb9c20a/` (`mkdir -m 700`). Save every output
you're asked to report there as you go.

## Step A — Before anything changes (read-only; report before Step B)

A1. **No other brief is mid-run.** This roll goes after the Steeltown
    Standard / Toronto Telegraph launch
    (`docs/briefs/launch-steeltown-toronto.md`). If you hold that brief and
    have not finished it, STOP and say so. Report whether
    `steeltownstandard.ca` and `torontotelegraph.ca` have enabled blocks.
    Either answer is fine: the upgrader rolls whatever blocks exist.

A2. **No roll is running.** `fuser /run/lock/prairiepost-upgrade.lock`
    must name no process. If one holds it, STOP.

A3. **Releases in service.** For every file in `/etc/nginx/sites-enabled/`:
    `readlink -f` it, strip inline comments, and read its `root`. Report
    each distinct `/var/www/prairiepost-*` root and the blocks that serve
    it. Expect two groups, `-shared` (the papers) and `-civismedia` (the
    hub), named `prairiepost-4ff557d2b737-*` or `prairiepost-835f90b85d2a-*`.
    **`$REL` is the papers' release root.** Any other release name: report
    it and STOP.

A4. **No stopped roll.** In each root from A3: if `.pp-roll-baseline`
    exists and neither `.pp-roll-complete` nor `.pp-roll-held` does, a roll
    stopped there. STOP and report. The upgrader would refuse this roll
    anyway, and finishing that one is a decision for the owner.

A5. **The pin, and the upgrader at the pin.** The commit must be an
    ancestor of (or equal to) the release branch's head:

        PIN=7cb82fb9c20aa53c886e80bbe7896bffe3da1bb4
        HEAD=$(curl -fsSL https://api.github.com/repos/FTFNAnalytics/prairiepost/branches/claude%2Fmaster-dashboard-control-room-nr3mp4 \
          | php -r 'echo json_decode(stream_get_contents(STDIN), true)["commit"]["sha"] ?? "";')
        curl -fsSL "https://api.github.com/repos/FTFNAnalytics/prairiepost/compare/$PIN...$HEAD" \
          | php -r '$c = json_decode(stream_get_contents(STDIN), true); echo ($c["status"] ?? "?"), " behind_by=", ($c["behind_by"] ?? "?"), "\n";'

    Expect `ahead behind_by=0` or `identical behind_by=0`. Anything else:
    STOP. Then fetch the upgrader **at the pin**, and check its hash:

        curl -fsSL -o /root/roll-7cb82fb9c20a/upgrade-papers.sh \
          "https://raw.githubusercontent.com/FTFNAnalytics/prairiepost/$PIN/tools/vps/upgrade-papers.sh"
        sha256sum /root/roll-7cb82fb9c20a/upgrade-papers.sh

    It must be
    `273b747fc4e43b1360222934705f17506470b020f24c16355e6f5d8aec6964bf`.
    A mismatch: STOP.

A6. **Schema ready.** `cd "$REL" && php tools/migrate.php --status`.
    Report it verbatim. Expect `state:   ready (stored version 23, target 23)`
    on `4ff557d2b737`, or `24, target 24` on `835f90b85d2a`. Any state
    other than `ready`: STOP.

A7. **A fresh recovery point.** The upgrader refuses to run without one.
    Check it the same way:

        php -r '$s = json_decode(file_get_contents($argv[1]), true); exit(($s["ok"] ?? false) === true && (time() - (int)($s["finished_epoch"] ?? 0)) < 26*3600 ? 0 : 1);' /var/backups/civis/state.json && echo FRESH || echo STALE

    If STALE, run `PP_BACKUP_DEST=/var/backups/civis bash "$REL/tools/backup.sh"`
    and check again. If the backup fails or is still STALE, STOP. Report
    the set id from `state.json`.

A8. **Baseline.** For every enabled prairiepost block, record its first
    `server_name`, the HTTPS front page's status code, its `<title>` and
    the sorted list of stylesheets it links. The upgrader compares titles
    only. The stylesheet list is what catches a paper whose templates
    went missing (CLAUDE.md debt item 5). This function is reused in D4:

        snap() {  # $1: output file — block, host, status, title, stylesheets
          : > "$1"
          for f in /etc/nginx/sites-enabled/*; do
            vh=$(readlink -f "$f"); [ -f "$vh" ] || continue
            root=$(sed 's/#.*//' "$vh" | grep -Eo 'root[[:space:]]+/var/www/prairiepost-[A-Za-z0-9._-]+' | head -1 | awk '{print $2}')
            [ -n "$root" ] || continue
            host=$(sed 's/#.*//' "$vh" | grep -Eo 'server_name[[:space:]]+[^;]+' | head -1 | awk '{print $2}')
            code=$(curl -s -m 20 -o /tmp/pp-front.html -w '%{http_code}' "https://$host/")
            meta=$(php -r '$d = new DOMDocument(); @$d->loadHTMLFile($argv[1]); $t = $d->getElementsByTagName("title")->item(0); $css = []; foreach ($d->getElementsByTagName("link") as $l) { if (preg_match("/\\bstylesheet\\b/i", $l->getAttribute("rel"))) { $css[] = $l->getAttribute("href"); } } sort($css); echo trim($t ? $t->textContent : ""), "\t", implode(" ", $css);' /tmp/pp-front.html)
            printf '%s\t%s\t%s\t%s\n' "$(basename "$f")" "$host" "$code" "$meta" >> "$1"
          done
        }
        snap /root/roll-7cb82fb9c20a/before.tsv

    Expect `200` with the paper's own masthead in the title, or `301` for a
    redirect alias such as `grandeprairiegazette.ca`'s apex. Anything else:
    STOP. Don't roll onto a box that is already unhealthy.

## Step B — The roll

    cd /root/roll-7cb82fb9c20a && PP_UPGRADE_SHA=7cb82fb9c20aa53c886e80bbe7896bffe3da1bb4 bash ./upgrade-papers.sh

What you should see, in order:

- Every enabled prairiepost block is mapped (25, or 27 if the launch ran).
  Foreign tenants are skipped.
- The titles are captured.
- `extracted tree … it is the pinned commit`.
- The preflight passes: `recovery point: <set id> (fresh, ok)`.
- New directories `/var/www/prairiepost-7cb82fb9c20a-shared` and
  `-civismedia` are built.
- The stored version is read, the schema migrates **once** (to 25), and
  nginx is tested and reloaded.
- Every domain is verified with 200 and its own title.
- `Done`.

The script writes its whole output to
`/var/log/prairiepost-upgrade/roll-<stamp>.log`, and a dropped SSH
connection does not stop it. If your session drops, reconnect and read
the log; never start a second run while A2's lock is held. If the run was
**interrupted** before `Done`, run the identical command once more: the
upgrader finishes a stopped roll at its own commit. If it **fails**
(`FATAL`, or a domain failing verification), STOP and report. Never copy
`.bak` vhost or cron files back yourself: once the schema has moved, that
takes every paper to 503. The script's closing text states what still
applies.

## Step C — Routes pass and upload limit (after `Done`)

The upgrader never adds routes or body limits to existing blocks.

C1. **Backups first.** Create `/root/routes-pass-7cb82fb9c20a-<UTC stamp>/`
    (mode 700). Copy every file you're about to change (the `readlink -f`
    target) into it before changing it.

C2. **Classify the blocks.** Take every enabled block whose root now names
    `/var/www/prairiepost-7cb82fb9c20a-*`. It is **generated** if it
    contains `try_files $uri /router.php$is_args$args;`, and **legacy**
    otherwise. Report the table. On 23 Nov there were 14 legacy blocks
    and 11 generated (13 if the launch ran).

C3. **Legacy blocks: three routes.** Generated blocks route through
    `router.php` and need nothing. Every legacy block has routed
    `/api/ingest/lookup` since the 21 Nov pass. Directly after that line,
    in the same form and the same context, add each route the block
    doesn't already have. The 22 Nov pass may have added the first two.

    | Path (nginx regex) | Script |
    | --- | --- |
    | `^/api/ingest/update/?$` | `/ingest-update.php` |
    | `^/api/ingest/stories/?$` | `/ingest-stories.php` |
    | `^/api/ingest/drafts/[^/]+/?$` | `/ingest-drafts.php` |

    In the form the legacy blocks use for their other API routes:

        rewrite ^/api/ingest/update/?$         /ingest-update.php    last;
        rewrite ^/api/ingest/stories/?$        /ingest-stories.php   last;
        rewrite ^/api/ingest/drafts/[^/]+/?$   /ingest-drafts.php    last;

    `ingest-drafts.php` reads the draft's reference from the original
    request line, so don't move the reference into a query string. A
    legacy block with no `/api/ingest/lookup` line is a surprise: STOP and
    report it.

C4. **Every prairiepost block: the upload limit.** nginx's default is
    1 MB, and no existing block sets one. In every server block whose
    `root` names `/var/www/prairiepost-7cb82fb9c20a-*` and that has no
    `client_max_body_size`, add these lines directly after the `root`
    line, and change nothing else:

        # Request bodies up to 10 MB: nginx's own default is 1 MB, which
        # refused a 1.6 MB agent upload with an HTML 413 before PHP ever saw
        # it. The app caps an image at 8 MB and answers anything larger with a
        # JSON 413; PHP's limits are raised to match by the release's .user.ini.
        client_max_body_size 10m;

    The Steeltown and Toronto blocks, if present, already carry it. Skip
    them. Leave certbot's port-80 redirect blocks (no `root`) alone.

C5. `nginx -t`. If it passes, `systemctl reload nginx`. If it fails, copy
    every file back from C1's directory, run `nginx -t` again to confirm
    green, reload, and STOP. Undoing C3 and C4 is always safe: they change
    routing only, never the schema.

## Step D — Verification (two-failure hard stop)

`$NEW` is `/var/www/prairiepost-7cb82fb9c20a-shared`. For each domain,
use its first `server_name`. For a redirect alias (a `301` in A8), use
the host its front page redirects to.

D1. **The upgrader finished.** Its log ends with `Done`, and every domain
    passed its masthead check. Report the log path.

D2. **Schema.** `cd "$NEW" && php tools/migrate.php --status` must show
    `state:   ready (stored version 25, target 25)`.

D3. **Everything points at the new release.** Every enabled prairiepost
    block's root names `/var/www/prairiepost-7cb82fb9c20a-<label>`. Every
    file in `/etc/cron.d` that names a prairiepost release names the new
    one, and none is left `.paused`.

D4. **Front pages unchanged.** Run `snap /root/roll-7cb82fb9c20a/after.tsv`
    and `diff` it against `before.tsv`. Every domain must show the same
    status, the same title and the same stylesheets. Nothing in this roll
    changes a front page.

D5. **Every ingest route fails closed.** On every domain, each request
    below must answer `401`, `application/json; charset=utf-8`, with body
    `{"ok":false,"error":"missing bearer token"}`:

        for r in "POST /api/ingest" "POST /api/ingest/lookup" "POST /api/ingest/update" \
                 "GET /api/ingest/stories" "PATCH /api/ingest/drafts/roll-check" \
                 "POST /api/ingest-media" "GET /api/ingest-queue" "POST /api/ingest-publish"; do
          set -- $r
          printf '%s %s %s ' "$1" "$2" "$(curl -s -m 20 -X "$1" -o /tmp/pp-api.txt -w '%{http_code} %{content_type}' "https://$H$2")"
          head -c 60 /tmp/pp-api.txt; echo
        done

    An HTML `404` means a legacy block lacks that route (go back to C3).

D6. **The upload limit is live.** On every domain, this 1.5 MB body with
    no token must reach the app and be refused there with `401`:

        head -c 1572864 /dev/zero | curl -s -o /dev/null -w '%{http_code}\n' -X POST \
          -H 'Content-Type: application/octet-stream' --data-binary @- "https://$H/api/ingest-media"

    An HTML `413` means that block didn't get C4's directive.

D7. **PHP's limits.** This is informational, not a gate. Report
    `cat "$NEW/.user.ini"`; expect `upload_max_filesize = 10M` and
    `post_max_size = 12M`. Also report what the FPM binary says about
    per-directory ini files: run the `php-fpm*` you find under `/usr/sbin`
    with `-i`, and look for `user_ini.filename`, which should be
    `.user.ini`. The real proof is the next agent upload over 2 MB.

D8. **Each paper's sign-in wears the paper (#84).** On every paper domain,
    `GET /admin/login.php` must answer `200`. The served HTML must contain
    `class="pp-signin"`, `/assets/css/signin.css` and
    `<meta name="robots" content="noindex, nofollow">`. On the hub's domain
    it must answer `200` and link `/assets/css/admin.css`: the control
    room's own sign-in, unchanged.

D9. **Each paper's newsroom wears the paper (#87).** This is checked
    without signing in, by rendering the admin header from the release:
    read-only, no session, no writes. For every domain, find its paper
    and render from the release root that serves it:

        slug=$(php "$NEW/tools/resolve-host.php" "$H" | awk '{print $2}')
        cd <that block's root> && PP_SITE="$slug" php -r 'require "app/bootstrap.php"; require "admin/_layout.php"; admin_header("Roll check", "dashboard");' > /tmp/pp-admin.html
        grep -c 'class="nh-mark nh-mark--paper"' /tmp/pp-admin.html

    For every paper: `resolve-host.php` says `db <slug>`, the count is `1`,
    and `/tmp/pp-admin.html` contains `<style>:root{--color-accent:`. For
    the hub (`civismedia`): the count is `0`, and the file contains
    `CivisMedia <span>Control room</span>`.

D10. **The bulk bar's script is served (#86).** On every domain,
    `GET /assets/js/bulk.js` must answer `200`.

## If something fails

- **The upgrader fails before the migration step:** it rolls the group
  back itself, because the old release still matches the schema. Report
  its output.
- **It fails after the schema moved** (the stored version rose past
  A6's): it holds the group on the new release and says so. Nothing goes
  back. Recovery is forward (a fix roll pinned to a new commit) or a
  database restore, which is the owner's decision alone
  (`docs/MIGRATION-COMPAT.md`, rule 2). STOP and report.
- **C or D fails:** undo C from its backups if the failure is in routing,
  as C5 says. Otherwise STOP and report.

## Out of scope

- Minting, revoking or re-minting ingest keys. The owner orders the
  agents' new grants (`--read`, `--edit-drafts`, `--update-published`)
  separately.
- The Steeltown/Toronto launch (its own brief, run before this one), and
  Terminal City.
- Indexing, newsletters, deleting old release directories, and the test
  residue on Surrey (#315, #316).
- Anything in a foreign tenant's vhost, root or cron.

## Report

1. **Step A:** A1–A8 results. Include the releases in service, the pin
   check output, the upgrader's sha256, `migrate.php --status` verbatim,
   the backup set id, and `before.tsv`.
2. **Step B:** the exact command, the log path, and the upgrader's lines
   for the groups and directories it built, the stored version before and
   after, each domain's verification line, and `Done`.
3. **Step C:** the backup directory and the block table (legacy or
   generated, routes added, directive added). Give the `nginx -t` output
   at each run.
4. **Step D:** a table of item, result and evidence for D1–D10, with the
   per-domain tables for D4, D5, D6, D8 and D9.
5. **Anything unexpected,** and anything you chose not to do and why.
