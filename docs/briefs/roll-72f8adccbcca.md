# Brief — roll the network to 72f8adccbcca, then clean the stored images

> **Superseded on 25 Nov 2026, and again on 26 Nov: run `docs/briefs/roll-acb508aa4fd0.md`.**
> Don't start this brief. The new one carries this one's code and its
> image cleanup (its C3), and works from whichever release production is
> on, including this one's.

**For: the VPS agent** (root SSH; server work only). Written 24 Nov 2026.

You are rolling every prairiepost release group (the papers and the
CivisMedia hub) to commit **`72f8adccbcca4bca12aefb937d4d21cbdfc62e8e`**
with `tools/vps/upgrade-papers.sh`. Then you remove the embedded metadata
(GPS location, camera, time) from the images already stored in the shared
uploads, with the new release's own tool, and verify the whole network.
The upgrader does the roll itself: it backs up and repoints vhosts and
cron, runs the shared schema's migration step once (here it finds nothing
to do), checks every masthead and rolls back what it can. Your job is the
checks around it, the image cleanup, and an exact report.

## What this roll ships

Production should be on `7cb82fb9c20a` at schema 25. That roll's report
hasn't reached the repository, but from outside on 24 Nov every domain
served `/assets/js/bulk.js`, which only that release carries, and every
domain answered `401` on the three ingest routes its routes pass added
and to a 1.5 MB body. This commit is 11 commits ahead of it:

| PR | What it brings | Schema |
| --- | --- | --- |
| #91 | every image is stored without its embedded metadata; `tools/strip-image-metadata.php` cleans the ones already stored | — |
| #90 | publishing or scheduling a story from the story editor clears its publish-on-image flag | — |
| #88, #89 | documents only | — |

Nothing between the two commits touches the migrations, the schema
definition or `tools/vps/upgrade-papers.sh`. So this is a **code-only
roll**: the stored version stays 25, nothing migrates, and the upgrader
is byte-identical to the last roll's. No PR adds a route or touches nginx,
so there is **no routes pass** this time. If a domain fails its check, the
upgrader puts its group back on the old release by itself.

## Ground rules

- **Server work only.** You hold no ingest token. Don't file, edit or
  publish stories, and don't use any token stored on the box. Don't mint,
  revoke or re-mint ingest keys.
- **No manual database edits.** Don't run `tools/migrate.php --apply`
  yourself. The upgrader runs the migration step.
- **No config edits.** Leave `config.php` and `app/config.site.php`
  untouched in every release.
- **Discover server facts; don't take them from this brief.** Paths,
  counts and release names below are what was true when it was written,
  given so you can recognise a match.
- **Foreign tenants are out of scope.** That's the Institute
  (`/var/www/cies-*`) and Calgary Dispatch (`/srv/calgarydispatch/`).
  Never require anything of them. One shared fact applies: a broken block
  anywhere fails the global `nginx -t`.
- **Check through this box, never through DNS.** `thepacificpost.com`
  currently has a stray second A record pointing at a Hostinger server, so
  a plain `curl https://thepacificpost.com/` fails about half the time from
  anywhere. Every HTTP check below connects to this box directly, the way
  the upgrader's own probes do, and still verifies each certificate
  against its hostname. Fixing the record is the owner's job at the
  registrar; you only report it (A8).
- **Hard stops:** a verification item that fails twice; any `nginx -t`
  failure; the upgrader exiting non-zero or printing `FATAL`; any STOP
  below. On a stop, leave the box as it is, report exactly, and wait.

Work in `/root/roll-72f8adccbcca/` (`mkdir -m 700`). Save every output
you're asked to report there as you go. Define these three functions once
in your shell; Steps A and D reuse them.

    get() {  # $1: host; the rest: curl's own arguments. Connects to this box, never to DNS.
      local h="$1"; shift
      curl -s -m 20 --resolve "$h:443:127.0.0.1" "$@"
    }

    snap() {  # $1: output file — block, host, status, title, stylesheets
      : > "$1"
      for f in /etc/nginx/sites-enabled/*; do
        vh=$(readlink -f "$f"); [ -f "$vh" ] || continue
        root=$(sed 's/#.*//' "$vh" | grep -Eo 'root[[:space:]]+/var/www/prairiepost-[A-Za-z0-9._-]+' | head -1 | awk '{print $2}')
        [ -n "$root" ] || continue
        host=$(sed 's/#.*//' "$vh" | grep -Eo 'server_name[[:space:]]+[^;]+' | head -1 | awk '{print $2}')
        code=$(get "$host" -o /tmp/pp-front.html -w '%{http_code}' "https://$host/")
        meta=$(php -r '$d = new DOMDocument(); @$d->loadHTMLFile($argv[1]); $t = $d->getElementsByTagName("title")->item(0); $css = []; foreach ($d->getElementsByTagName("link") as $l) { if (preg_match("/\\bstylesheet\\b/i", $l->getAttribute("rel"))) { $css[] = $l->getAttribute("href"); } } sort($css); echo trim($t ? $t->textContent : ""), "\t", implode(" ", $css);' /tmp/pp-front.html)
        printf '%s\t%s\t%s\t%s\n' "$(basename "$f")" "$host" "$code" "$meta" >> "$1"
      done
    }

    apis() {  # $1: output file — each domain's answer to the eight ingest routes and to a 1.5 MB body
      local out="$1" block H code rest m p r line
      : > "$out"
      head -c 1572864 /dev/zero > /tmp/pp-1m5.bin
      while IFS=$'\t' read -r block H code rest; do
        if [ "$code" = 301 ]; then  # a redirect alias: check the host it forwards to
          H=$(get "$H" -o /dev/null -w '%{redirect_url}' "https://$H/" | sed -E 's#^https?://([^/:]+).*#\1#')
        fi
        line="$block"$'\t'"$H"
        for r in "POST /api/ingest" "POST /api/ingest/lookup" "POST /api/ingest/update" \
                 "GET /api/ingest/stories" "PATCH /api/ingest/drafts/roll-check" \
                 "POST /api/ingest-media" "GET /api/ingest-queue" "POST /api/ingest-publish"; do
          read -r m p <<< "$r"
          line="$line"$'\t'"$m $p $(get "$H" -X "$m" -o /dev/null -w '%{http_code}' "https://$H$p")"
        done
        line="$line"$'\t'"1.5MB $(get "$H" -X POST -H 'Content-Type: application/octet-stream' \
          --data-binary @/tmp/pp-1m5.bin -o /dev/null -w '%{http_code}' "https://$H/api/ingest-media")"
        printf '%s\n' "$line" >> "$out"
      done < /root/roll-72f8adccbcca/before.tsv
    }

## Step A — Before anything changes (read-only; report before Step B)

A1. **No other brief is mid-run.** If you hold any brief you have not
    finished, STOP and say so. Report how many prairiepost blocks are
    enabled; there were 27 when this was written. The upgrader rolls
    whatever blocks exist.

A2. **No roll is running.** `fuser /run/lock/prairiepost-upgrade.lock`
    must name no process. If one holds it, STOP.

A3. **Releases in service.** For every file in `/etc/nginx/sites-enabled/`:
    `readlink -f` it, strip inline comments, and read its `root`. Report
    each distinct `/var/www/prairiepost-*` root and the blocks that serve
    it. Expect two groups, `-shared` (the papers) and `-civismedia` (the
    hub), both on `prairiepost-7cb82fb9c20a-*`. **`$REL` is the papers'
    release root.**

    This roll must only move production **forward**. For each distinct
    release commit (the 12 characters in the directory name), ask GitHub
    whether the pin descends from it:

        curl -fsSL "https://api.github.com/repos/FTFNAnalytics/prairiepost/compare/<those 12 characters>...72f8adccbcca4bca12aefb937d4d21cbdfc62e8e" \
          | php -r '$c = json_decode(stream_get_contents(STDIN), true); echo ($c["status"] ?? "?"), " ahead_by=", ($c["ahead_by"] ?? "?"), " behind_by=", ($c["behind_by"] ?? "?"), "\n";'

    Expect `ahead … behind_by=0`. From `7cb82fb9c20a` that reads
    `ahead ahead_by=11 behind_by=0`. Another release the pin descends from
    is acceptable; report it. If it reads `identical`, production is
    already on this commit: STOP and say so. Anything else (`behind`,
    `diverged`, an error, or a directory name that isn't a commit): STOP.

A4. **No stopped roll.** In each root from A3: if `.pp-roll-baseline`
    exists and neither `.pp-roll-complete` nor `.pp-roll-held` does, a roll
    stopped there. STOP and report. Finishing that one is a decision for
    the owner.

A5. **The pin, and the upgrader at the pin.** The commit must be an
    ancestor of (or equal to) the release branch's head:

        PIN=72f8adccbcca4bca12aefb937d4d21cbdfc62e8e
        HEAD=$(curl -fsSL https://api.github.com/repos/FTFNAnalytics/prairiepost/branches/claude%2Fmaster-dashboard-control-room-nr3mp4 \
          | php -r 'echo json_decode(stream_get_contents(STDIN), true)["commit"]["sha"] ?? "";')
        curl -fsSL "https://api.github.com/repos/FTFNAnalytics/prairiepost/compare/$PIN...$HEAD" \
          | php -r '$c = json_decode(stream_get_contents(STDIN), true); echo ($c["status"] ?? "?"), " behind_by=", ($c["behind_by"] ?? "?"), "\n";'

    Expect `ahead behind_by=0` or `identical behind_by=0`. Anything else:
    STOP. Then fetch the upgrader **at the pin**, and check its hash:

        curl -fsSL -o /root/roll-72f8adccbcca/upgrade-papers.sh \
          "https://raw.githubusercontent.com/FTFNAnalytics/prairiepost/$PIN/tools/vps/upgrade-papers.sh"
        sha256sum /root/roll-72f8adccbcca/upgrade-papers.sh

    It must be
    `273b747fc4e43b1360222934705f17506470b020f24c16355e6f5d8aec6964bf`,
    the same file as the last roll's. A mismatch: STOP.

A6. **Schema ready.** `cd "$REL" && php tools/migrate.php --status`.
    Report it verbatim. Expect `state:   ready (stored version 25, target 25)`.
    The pin's code is built for 25 too, so nothing will migrate. Any state
    other than `ready`, or a stored version other than 25: STOP.

A7. **A fresh recovery point.** The upgrader refuses to run without one,
    and Step C relies on it: the set's `uploads.tar.gz` is the copy of
    every original image that Step C rewrites. Check it:

        php -r '$s = json_decode(file_get_contents($argv[1]), true); exit(($s["ok"] ?? false) === true && (time() - (int)($s["finished_epoch"] ?? 0)) < 26*3600 ? 0 : 1);' /var/backups/civis/state.json && echo FRESH || echo STALE

    If STALE, run `PP_BACKUP_DEST=/var/backups/civis bash "$REL/tools/backup.sh"`
    and check again. If the backup fails or is still STALE, STOP. Report
    the set id from `state.json` and `ls -l /var/backups/civis/sets/<set id>/uploads.tar.gz`.

A8. **Baseline, and what DNS says.** Record every enabled prairiepost
    block's first `server_name`, the HTTPS front page's status, its
    `<title>` and the sorted list of stylesheets it links:

        snap /root/roll-72f8adccbcca/before.tsv

    Expect `200` with the paper's own masthead in the title, or `301` for
    a redirect alias such as `grandeprairiegazette.ca`'s apex. Anything
    else: STOP. Don't roll onto a box that is already unhealthy.

    Then, for information only (not a gate), what public DNS returns for
    each host:

        cut -f2 /root/roll-72f8adccbcca/before.tsv | while read -r h; do
          printf '%s\t%s\n' "$h" "$(getent ahosts "$h" | awk '{print $1}' | sort -u | tr '\n' ' ')"
        done > /root/roll-72f8adccbcca/dns.tsv

    Report every host whose addresses include one that isn't this box's
    (`hostname -I`). On 24 Nov that was `thepacificpost.com`, which also
    listed `217.21.85.231`.

A9. **The ingest routes and the upload limit, before.** These are the
    last roll's routes pass and body limit, which this roll must not
    disturb:

        apis /root/roll-72f8adccbcca/apis-before.tsv

    Expect every route and the 1.5 MB body to answer `401` on every
    domain. That's how all 27 answered from outside on 24 Nov. Report any
    other value. A non-`401` here predates this roll: report it, don't fix
    it, and carry on.

A10. **What Step C needs.** All three must hold, or STOP:
    - `command -v runuser` prints a path. Step C runs the tool as the
      uploads' owner rather than as root.
    - `stat -c '%U:%G %a' /var/www/prairiepost-shared-uploads` reads
      `www-data:www-data 2775`. The upgrader sets exactly that on every
      roll.
    - `php -r 'exit(extension_loaded("fileinfo") ? 0 : 1);' && echo OK`
      prints `OK`.

## Step B — The roll

    cd /root/roll-72f8adccbcca && PP_UPGRADE_SHA=72f8adccbcca4bca12aefb937d4d21cbdfc62e8e bash ./upgrade-papers.sh

What you should see, in order:

- Every enabled prairiepost block is mapped (27 when this was written).
  Foreign tenants are skipped.
- The titles are captured.
- `extracted tree … it is the pinned commit`.
- The preflight passes: `recovery point: <set id> (fresh, ok)`.
- `shared uploads: <size> at /var/www/prairiepost-shared-uploads`.
- New directories `/var/www/prairiepost-72f8adccbcca-shared` and
  `-civismedia` are built.
- The stored version is read as 25, and the migration step finds nothing
  to do: `stored schema version after the step: 25 (unchanged)`. The
  scheduled jobs are paused and resumed around that step, as on every
  roll.
- nginx is tested and reloaded.
- Every domain is verified with 200 and its own title.
- `Done`, followed by the rollback lines for each release group. Those
  are valid here, because the schema did not move.

The script writes its whole output to
`/var/log/prairiepost-upgrade/roll-<stamp>.log`, and a dropped SSH
connection does not stop it. If your session drops, reconnect and read
the log; never start a second run while A2's lock is held. If the run was
**interrupted** before `Done`, run the identical command once more: the
upgrader finishes a stopped roll at its own commit. If it **fails**
(`FATAL`, or a domain failing verification), STOP and report. The
upgrader puts a failed group back by itself. Don't repoint anything
yourself; that decision comes back here with your report.

## Step C — Remove the embedded metadata from the stored images

Until this release, every upload path stored images exactly as they
arrived, so a published photo also published where and when it was
taken, and on what device. From this release on, every new image is
cleaned before it is stored. This step cleans the ones already there.

The tool rewrites only each file's container. The compressed image data
is copied byte for byte, so every picture looks exactly as it did. It
keeps each file's name, owner, group and mode, and it replaces each file
atomically: it writes a temporary file in the same directory, then
renames it over the original. A file it can't parse is left exactly as it
is and listed as `SKIP`. Run it as `www-data`, which owns every file
there, never as root, so nothing it writes needs its owner changed.

`$NEW` is `/var/www/prairiepost-72f8adccbcca-shared`, and `$HUBNEW` is the
hub's new root (`-civismedia`). Take both from the enabled blocks after
`Done`, as in A3.

C1. **One directory.** `UP=$(readlink -f "$NEW/uploads")`, and
    `readlink -f "$HUBNEW/uploads"` must print the same path. Expect
    `/var/www/prairiepost-shared-uploads`. Two different directories:
    STOP.

C2. **Before: every image's owner, group and mode.**

        find "$UP" -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.webp' -o -iname '*.gif' \) \
          -printf '%P\t%u:%g\t%m\n' | sort > /root/roll-72f8adccbcca/uploads-before.tsv
        wc -l < /root/roll-72f8adccbcca/uploads-before.tsv

C3. **Dry run.** It reads every image and changes nothing:

        cd "$NEW" && runuser -u www-data -- php tools/strip-image-metadata.php \
          > /root/roll-72f8adccbcca/strip-dry.txt; echo "exit $?"

    Expect `exit 0`. The output has one `WOULD <path>: <what it carries>`
    line per file that carries metadata, a `SKIP` line per file the tool
    can't parse, and then a summary:

        <n> image(s) in /var/www/prairiepost-shared-uploads: <m> carried embedded metadata (<g> with a GPS location).
        Dry run: nothing changed. Run again with --apply to clean them.

    Report the summary lines, the number of `SKIP` lines, and how often
    each kind of metadata appears:

        grep '^WOULD' /root/roll-72f8adccbcca/strip-dry.txt | sed 's/^[^:]*: //' \
          | tr ',' '\n' | sed 's/^ *//' | sort | uniq -c | sort -rn

    `exit 1` means a file couldn't be read even by its owner: STOP and
    report the `FAIL` lines. If `m` is 0, there is nothing to clean: skip
    C4 and C5, and run C6.

C4. **Apply.**

        cd "$NEW" && runuser -u www-data -- php tools/strip-image-metadata.php --apply \
          > /root/roll-72f8adccbcca/strip-apply.txt; echo "exit $?"

    Expect `exit 0` and `Cleaned <m>.`, with the same `m` as C3. A
    difference is possible if an image arrived in between: new arrivals
    are already clean, so they never add to it. Report the difference, but
    it isn't a stop. A `FAIL` line means that file could not be rewritten,
    and its original is untouched. On `exit 1`, run C4 once more: the tool
    skips everything it has already cleaned. If `FAIL` lines remain, STOP
    and report them.

C5. **Again, to prove it's done.**

        cd "$NEW" && runuser -u www-data -- php tools/strip-image-metadata.php \
          > /root/roll-72f8adccbcca/strip-after.txt; echo "exit $?"

    Expect `exit 0`, and a summary that reads `<n> image(s) in
    /var/www/prairiepost-shared-uploads: 0 carried embedded metadata.`
    with the same `SKIP` lines as C3. Anything still listed as `WOULD`:
    STOP.

C6. **Nothing else changed.**
    - Repeat C2 into `uploads-after.tsv`, then
      `diff /root/roll-72f8adccbcca/uploads-before.tsv /root/roll-72f8adccbcca/uploads-after.tsv`.
      Lines starting `>` alone are images uploaded since C2, which is
      fine. Any line starting `<`, meaning a file missing or with a
      different owner, group or mode: STOP.
    - `find "$UP" -name '.pp-strip-*'` must print nothing. Those would be
      leftover temporary files.
    - **The cleaned files are what readers get.** Take the first three
      `CLEAN` lines from `strip-apply.txt`, preferring lines that mention
      a GPS location. Fetch each one through any paper's domain (they
      all serve the same directory), with `H` the host of the first `200`
      row in `before.tsv`:

          grep '^CLEAN' /root/roll-72f8adccbcca/strip-apply.txt | grep 'GPS' | head -3 \
            | sed 's/^CLEAN \([^:]*\):.*/\1/' > /root/roll-72f8adccbcca/sample.txt
          [ -s /root/roll-72f8adccbcca/sample.txt ] || grep '^CLEAN' /root/roll-72f8adccbcca/strip-apply.txt | head -3 \
            | sed 's/^CLEAN \([^:]*\):.*/\1/' > /root/roll-72f8adccbcca/sample.txt
          while read -r rel; do
            printf '%s %s ' "$rel" "$(get "$H" -o /tmp/pp-img -w '%{http_code} %{content_type}' "https://$H/uploads/$rel")"
            cmp -s /tmp/pp-img "$UP/$rel" && echo SAME || echo DIFFERENT
          done < /root/roll-72f8adccbcca/sample.txt

      Expect `200`, an `image/…` content type and `SAME` for each.

The backup set from A7 still holds every original, and so do the older
sets, until they age out (7 daily and 4 weekly). Don't delete, move or
edit any backup set: that's the owner's decision. If anything in C goes
wrong, restoring files from that set is a decision that comes back here
too, so STOP and report.

## Step D — Verification (two-failure hard stop)

D1. **The upgrader finished.** Its log ends with `Done`, and every domain
    passed its masthead check. Report the log path.

D2. **Schema.** `cd "$NEW" && php tools/migrate.php --status` must show
    `state:   ready (stored version 25, target 25)`.

D3. **Everything points at the new release.** Every enabled prairiepost
    block's root names `/var/www/prairiepost-72f8adccbcca-<label>`. Every
    file in `/etc/cron.d` that names a prairiepost release names the new
    one, and none is left `.paused`.

D4. **Front pages unchanged.** Run `snap /root/roll-72f8adccbcca/after.tsv`
    and `diff` it against `before.tsv`. Every domain must show the same
    status, the same title and the same stylesheets. Nothing in this roll
    changes a front page.

D5. **Ingest routes and the upload limit unchanged.** Run
    `apis /root/roll-72f8adccbcca/apis-after.tsv` and `diff` it against
    `apis-before.tsv`. They must be identical. A value that was `401` before
    and isn't now is this roll's failure. A gap that A9 already showed is
    not: report it.

D6. **The new code is in place.** These files are new in this release, or
    changed in it:

        for r in "$NEW" "$HUBNEW"; do
          printf '%s imagemeta=%s tool=%s helpers=%s fetch=%s post-edit=%s\n' "$r" \
            "$(test -f "$r/app/imagemeta.php" && echo yes || echo no)" \
            "$(test -f "$r/tools/strip-image-metadata.php" && echo yes || echo no)" \
            "$(grep -c 'pp_image_clean' "$r/app/helpers.php")" \
            "$(grep -c 'pp_image_clean' "$r/app/fetch.php")" \
            "$(grep -cF "\$fields['awaiting_image'] = 0;" "$r/admin/post-edit.php")"
        done

    Expect `imagemeta=yes tool=yes helpers=3 fetch=1 post-edit=1` for both
    roots. In the old release those read `no no 0 0 0`. The upload paths
    themselves are proven by the next real upload. Step C has already run
    the same code over every stored image, on this box's PHP.

D7. **The owner's analytics tag survives.** Kermode Chronicle carries the
    Google Analytics tag the owner pasted into its Settings on 24 Nov. It
    lives in the database, and nothing in this roll touches it:

        get kermodechronicle.ca "https://kermodechronicle.ca/" | grep -c 'G-RTEJHC0DFB'

    Expect `2`, one for the script's address and one for its `config`
    line.

## If something fails

- **The upgrader fails:** the schema doesn't move in this roll, so the
  upgrader puts the failing group back on its old release by itself.
  Report its output and STOP. Don't run Step C on the old release: the
  tool isn't in it.
- **The stored version is anything but 25 after the roll:** that is not
  this roll's doing. STOP and report it at once, with `migrate.php --status`
  from both releases.
- **Step C fails:** STOP where it says to. Every original is in the A7
  backup set, and restoring is the owner's decision.
- **D fails:** STOP and report.

## Out of scope

- Minting, revoking or re-minting ingest keys, and any use of a stored
  token.
- `thepacificpost.com`'s stray DNS record: the owner fixes it at the
  registrar.
- Any backup set, old or new: don't delete or change one.
- The images in the release tree (`assets/`). Step C cleans the shared
  uploads only.
- Terminal City, and any launch.
- Indexing, newsletters, deleting old release directories, and the test
  residue on Surrey (#315, #316).
- Anything in a foreign tenant's vhost, root or cron.

## Report

1. **Step A:** A1–A10 results. Include:
   - the releases in service and the pin check output;
   - the upgrader's sha256 and `migrate.php --status` verbatim;
   - the backup set id and its `uploads.tar.gz` size;
   - `before.tsv` and the DNS lines A8 asks for;
   - `apis-before.tsv`, or just "all 401" if that's what it shows.
2. **Step B:** include:
   - the exact command and the log path;
   - the upgrader's lines for the groups and directories it built, and
     the shared uploads line;
   - the stored version before and after;
   - each domain's verification line, and `Done`.
3. **Step C:** include:
   - the uploads path and C2's count;
   - C3's exit, its summary lines, the count of each kind of metadata,
     and the `SKIP` count;
   - C4's exit, the `Cleaned` line and any `FAIL` lines;
   - C5's summary;
   - C6's diff result, the temporary-file check and the three fetches.
4. **Step D:** a table of item, result and evidence for D1–D7, with the
   per-domain tables for D4 and D5.
5. **The last roll's report.** If you have the 7cb82fb run's report,
   attach it: it never reached the repository.
6. **Anything unexpected,** and anything you chose not to do and why.
