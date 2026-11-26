# Brief — roll the network to 6e3cfab33b32: each paper's own address, weather, the whiteboard, and a router on every block

> **Superseded on 26 Nov 2026 by `docs/briefs/roll-acb508aa4fd0.md`.**
> Don't start this brief. The new one rolls everything this one does,
> plus #102, the ingest hourly limits a full news round needs. It works
> from whichever release production is on, including this one's.

**For: the VPS agent** (root SSH; server work only). Written 25 Nov 2026.

**This brief supersedes `docs/briefs/roll-1458b09870e0.md`.** Run this
one instead. If that one already ran, this one carries on from it.

You are rolling every prairiepost release group (the papers and the
CivisMedia hub) to commit **`6e3cfab33b323a7facc93e96eb268eecc27b46cc`** with
`tools/vps/upgrade-papers.sh`. The upgrader does the roll: it backs up and
repoints vhosts and cron, migrates the shared schema once, checks every
masthead, and puts back what it can. This roll differs from the last
completed one in four ways:

- **Thirteen papers start printing their own address.** Today they print
  `prairiedispatch.ca` in every canonical link, share link, sitemap and
  feed. The fix is in the code (#101). **No config file is edited.**
- **It raises the schema from 25 to 26**, unless the superseded brief
  already did. Once step 26 commits, the old release is no longer a
  fallback (rule 1 of `docs/MIGRATION-COMPAT.md`). Recovery is forward
  only.
- **The upgrader changes the hand-written nginx blocks,** unless the
  superseded brief already did. It gives each a `router.php` fallback,
  so routes that 404 there today start working, and it keeps `/tips` out
  of every block's access log.
- **There is server work after the roll:** a cron line for the new
  weather job and its first run, and the stored-image cleanup, each only
  if it hasn't been done.

Your job is the checks around the roll, that work, and an exact report.

## What this roll ships

Production should be at schema 25 on `72f8adccbcca` or `7cb82fb9c20a`, or
at schema 26 on `1458b09870e0` if the superseded brief ran. None of
those reports has reached the repository, and A3 tells you which. This
commit is 8 commits ahead of `1458b09870e0`, 22 ahead of `72f8adccbcca`
and 33 ahead of `7cb82fb9c20a`:

| PR | What it brings | Schema | Server work |
| --- | --- | --- | --- |
| #101 | **each paper prints its own address**, not the flagship's (PLAN.md 10.0) | — | none |
| #91, #90 | images stored without their embedded metadata, and an editor's publish clears the image hold | — | C3 cleans the images already stored, if nothing has |
| #94 | live weather from Environment and Climate Change Canada, read by a new job on the hub | — | a cron line (C1) and a first run (C2) |
| #95 | each paper's private **whiteboard** in its newsroom, and a public **tips form** at `/tips` that pins reader tips to it | **26**: the `whiteboard_notes` table and the `tips_form_key` setting | none; the upgrader migrates |
| #96 | breaking banners, traffic, events and prices that expire; London's illustrative panels removed | — | — |
| #97 | each paper prints times on its own clock | — | the box's time-zone data must know 2026's changes (A12) |
| #98 | the upgrader gives hand-written blocks a `router.php` fallback and every block an unlogged `/tips` | — | done by the upgrader |
| #92, #93, #99, #100 | documents only | — | — |

If production is on `1458b09870e0`, only #101 is new code. The rest are
already live, and the steps that would redo them find their work done.

**What #101 changes.** A paper launched config-edit-free has no arm in
the tenant config. That is Kitchener, London and Mississauga, and every
launch since Surrey. It finds its masthead through the `domains` table,
but its base URL came only from the config, whose default is the
flagship's. `site_url()` now keeps the configured URL unless its host
belongs to another paper. Then it uses the paper's own host: its
`sites.domain`, else the shortest hostname `domains` maps to it. A
configured URL naming the paper itself is kept exactly, so the other
fourteen sites print what they print today. Titles, stylesheets and
mastheads don't change; the address does. Checked from outside on
25 Nov, the thirteen papers that name `prairiedispatch.ca` today are
Bison, Brandon, Burrard, Cariboo, Kitchener, London, Mississauga,
Portage, Red River, Rideau, Steeltown, Surrey and Toronto.

**What #98 changed in the upgrader.** Inside its loop over vhosts, after
it repoints a block's `root`, it makes two edits:

- **The router fallback.** A block whose `location /` falls back with
  `try_files $uri $uri/ =404` now falls back to `router.php`, behind an
  internal-only `location = /router.php`, the same shape generated blocks
  use. Its rewrite lines stay. This is what makes `/about` (which 404s on
  11 papers and the hub today) and `/tips` work on the hand-written
  blocks, with no routes pass.
- **The unlogged tips location.** Every block gets
  `location ~ ^/tips/?$ { access_log off; … }`, passed straight to
  `router.php`. An access log would otherwise pair each tip sender's IP
  with the time of a tip the database stores without one.

Both edits are guarded by a marker comment, so a block that already has
them is left alone and prints nothing. Both go just above the block's PHP
handler (`location ~ \.php$`), so certbot's plain-HTTP redirect servers,
which have no PHP handler, are never touched. Both use the php-fpm socket
the block already names, and a block without one is left alone. Because
they run inside the vhost loop, the upgrader's `nginx -t`, its masthead
and stylesheet guard, and its rollback cover them. #101 doesn't change
the upgrader, so it is the file the superseded brief named (A5).

## Ground rules

- **Server work only.** You hold no ingest token. Don't file, edit or
  publish stories, and don't use any token stored on the box. Don't mint,
  revoke or re-mint ingest keys.
- **No manual database edits.** Don't run `tools/migrate.php --apply`
  yourself; the upgrader runs the migration. **Never send a real tip**
  through `/tips`. The one POST below carries no token, and the form
  refuses it before writing anything.
- **No config edits.** Leave `config.php` and `app/config.site.php`
  untouched in every release. That includes the addresses: #101 fixes
  them in code, so don't add a tenant arm or a `site_url` to any config.
  The only files you change by hand are the hub's cron file (C1), and the
  `tzdata` package if A12 calls for it.
- **Discover server facts; don't take them from this brief.** Paths,
  counts and release names below are what was true when it was written,
  given so you can recognise a match.
- **Foreign tenants are out of scope.** That's the Institute
  (`/var/www/cies-*`) and Calgary Dispatch (`/srv/calgarydispatch/`).
  Never require anything of them. One shared fact applies: a broken block
  anywhere fails the global `nginx -t`.
- **Check through this box, never through DNS.** Every HTTP check below
  connects here directly, as the upgrader's own probes do, and still
  verifies each certificate against its hostname.
- **Hard stops:** a verification item that fails twice; any `nginx -t`
  failure; the upgrader exiting non-zero or printing `FATAL`; any STOP
  below. On a stop, leave the box as it is, report exactly, and wait.

Work in `/root/roll-6e3cfab33b32/` (`mkdir -m 700`). Save every output you're
asked to report there as you go. Define these functions once in your
shell; Steps A and D reuse them.

    W=/root/roll-6e3cfab33b32

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

    canon() {  # $1: host from before.tsv, $2: its front-page status — the host that serves pages
      if [ "$2" = 301 ]; then  # a redirect alias: the host it forwards to
        get "$1" -o /dev/null -w '%{redirect_url}' "https://$1/" | sed -E 's#^https?://([^/:]+).*#\1#'
      else
        echo "$1"
      fi
    }

    apis() {  # $1: output file — each domain's answer to the eight ingest routes and to a 1.5 MB body
      local out="$1" block H code rest m p r line
      : > "$out"
      head -c 1572864 /dev/zero > /tmp/pp-1m5.bin
      while IFS=$'\t' read -r block H code rest; do
        H=$(canon "$H" "$code")
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
      done < "$W/before.tsv"
    }

    routes() {  # $1: output file — each domain's answer on /about, /tips, and a tip POSTed with no token
      local out="$1" block H code rest
      : > "$out"
      while IFS=$'\t' read -r block H code rest; do
        H=$(canon "$H" "$code")
        printf '%s\t%s\tabout %s\ttips %s\tpost %s\n' "$block" "$H" \
          "$(get "$H" -o /dev/null -w '%{http_code}' "https://$H/about")" \
          "$(get "$H" -o /dev/null -w '%{http_code}%{redirect_url}' "https://$H/tips")" \
          "$(get "$H" -X POST --data 'tip=roll+check+with+no+token' -o /dev/null -w '%{http_code}' "https://$H/tips")" >> "$out"
      done < "$W/before.tsv"
    }

    addr() {  # $1: output file — the host each domain names as its own: canonical, og:url, sitemap, feed
      local out="$1" block H code rest
      : > "$out"
      while IFS=$'\t' read -r block H code rest; do
        H=$(canon "$H" "$code")
        get "$H" -o /tmp/pp-addr-front.html "https://$H/"
        get "$H" -o /tmp/pp-addr-sitemap.xml "https://$H/sitemap.xml"
        get "$H" -o /tmp/pp-addr-feed.xml "https://$H/feed/"
        php -r '
          $host = fn ($u) => strtolower((string) parse_url(trim($u), PHP_URL_HOST));
          $d = new DOMDocument(); @$d->loadHTMLFile($argv[2]);
          $c = $o = "-";
          foreach ($d->getElementsByTagName("link") as $l) { if (strtolower($l->getAttribute("rel")) === "canonical") { $c = $host($l->getAttribute("href")); } }
          foreach ($d->getElementsByTagName("meta") as $m) { if ($m->getAttribute("property") === "og:url") { $o = $host($m->getAttribute("content")); } }
          preg_match_all("#<loc>([^<]+)</loc>#", (string) @file_get_contents($argv[3]), $s);
          $f = (string) @file_get_contents($argv[4]);
          preg_match("#<channel>\\s*<title>[^<]*</title>\\s*<link>([^<]+)</link>#", $f, $ch);
          preg_match_all("#<guid isPermaLink=\"true\">([^<]+)</guid>#", $f, $g);
          $set = fn (array $l) => $l ? implode(",", array_unique(array_map($host, $l))) : "-";
          printf("%s\tcanonical=%s\tog=%s\tsitemap=%s\tfeed=%s\t(%d urls, %d items)\n", $argv[1], $c, $o, $set($s[1]),
            $set(array_merge(isset($ch[1]) ? [$ch[1]] : [], $g[1])), count($s[1]), count($g[1]));
        ' "$H" /tmp/pp-addr-front.html /tmp/pp-addr-sitemap.xml /tmp/pp-addr-feed.xml >> "$out"
      done < "$W/before.tsv"
    }

    own() {  # $1: an addr() file — "own" when all four places name the domain itself, else "FOREIGN"
      awk -F'\t' '{ bad = 0; for (i = 2; i <= 5; i++) { split($i, kv, "="); n = split(kv[2], hs, ","); for (j = 1; j <= n; j++) if (hs[j] != $1) bad = 1 } print (bad ? "FOREIGN" : "own") "\t" $1 }' "$1"
    }

The feed's address is its channel link and each item's `guid`. An
item's `<link>` may rightly name another outlet, because a wire item
links its source.

## Step A — Before anything changes (read-only, except A12; report before Step B)

A1. **No other brief is mid-run.** If you hold any brief you have not
    finished, STOP and say so. That includes the superseded
    `roll-1458b09870e0` brief: if you started it and didn't finish, STOP
    and report where it stopped. Report how many prairiepost blocks are
    enabled; there were 27 when this was written. The upgrader rolls
    whatever blocks exist.

A2. **No roll is running.** `fuser /run/lock/prairiepost-upgrade.lock`
    must name no process. If one holds it, STOP.

A3. **Releases in service.** For every file in `/etc/nginx/sites-enabled/`:
    `readlink -f` it, strip inline comments, and read its `root`. Report
    each distinct `/var/www/prairiepost-*` root and the blocks that serve
    it. Expect two groups, `-shared` (the papers) and `-civismedia` (the
    hub), on `prairiepost-1458b09870e0-*`, `prairiepost-72f8adccbcca-*` or
    `prairiepost-7cb82fb9c20a-*`. **`$REL` is the papers' release root, and
    `$HUBREL` the hub's.**

    This roll must only move production **forward**. For each distinct
    release commit (the 12 characters in the directory name), ask GitHub
    whether the pin descends from it:

        curl -fsSL "https://api.github.com/repos/FTFNAnalytics/prairiepost/compare/<those 12 characters>...6e3cfab33b323a7facc93e96eb268eecc27b46cc" \
          | php -r '$c = json_decode(stream_get_contents(STDIN), true); echo ($c["status"] ?? "?"), " ahead_by=", ($c["ahead_by"] ?? "?"), " behind_by=", ($c["behind_by"] ?? "?"), "\n";'

    Expect `ahead … behind_by=0`: `ahead_by=8` from `1458b09870e0`,
    `ahead_by=22` from `72f8adccbcca`, or `ahead_by=33` from
    `7cb82fb9c20a`. Another release the pin descends from is acceptable;
    report it. If it reads `identical`, production is already on this
    commit: STOP and say so. Anything else (`behind`, `diverged`, an
    error, or a directory name that isn't a commit): STOP.

    Say plainly which release you found. On `7cb82fb9c20a`, neither
    earlier brief ran: this roll carries their code, and C3 does the image
    cleanup. On `1458b09870e0`, the superseded brief ran: the roll needs
    no migration and makes no block edits, and C1 and C3 find their work
    done.

A4. **No stopped roll.** In each root from A3: if `.pp-roll-baseline`
    exists and neither `.pp-roll-complete` nor `.pp-roll-held` does, a roll
    stopped there. STOP and report. Finishing that one is a decision for
    the owner.

A5. **The pin, and the upgrader at the pin.** The commit must be an
    ancestor of (or equal to) the release branch's head. It becomes one
    when #101 is merged, so until then this check STOPs you, by design:

        PIN=6e3cfab33b323a7facc93e96eb268eecc27b46cc
        HEAD=$(curl -fsSL https://api.github.com/repos/FTFNAnalytics/prairiepost/branches/claude%2Fmaster-dashboard-control-room-nr3mp4 \
          | php -r 'echo json_decode(stream_get_contents(STDIN), true)["commit"]["sha"] ?? "";')
        curl -fsSL "https://api.github.com/repos/FTFNAnalytics/prairiepost/compare/$PIN...$HEAD" \
          | php -r '$c = json_decode(stream_get_contents(STDIN), true); echo ($c["status"] ?? "?"), " behind_by=", ($c["behind_by"] ?? "?"), "\n";'

    Expect `ahead behind_by=0` or `identical behind_by=0`. Anything else:
    STOP, and say #101 may not be merged yet. Then fetch the upgrader
    **at the pin**, and check its hash:

        curl -fsSL -o "$W/upgrade-papers.sh" \
          "https://raw.githubusercontent.com/FTFNAnalytics/prairiepost/$PIN/tools/vps/upgrade-papers.sh"
        sha256sum "$W/upgrade-papers.sh"

    It must be
    `d28307e3c31c185362c773d6329d3f640f39fad2d6dc69fe3999b4387203a96b`,
    the same file the superseded brief named. A mismatch: STOP.

A6. **Schema ready.** `cd "$REL" && php tools/migrate.php --status`.
    Report it verbatim. Expect `state:   ready (stored version 25, target 25)`
    on `72f8adccbcca` or `7cb82fb9c20a`, and
    `state:   ready (stored version 26, target 26)` on `1458b09870e0`. The
    pin's code is built for 26, so from 25 the roll runs step 26. Any state
    other than `ready`, or any other version: STOP.

A7. **A fresh recovery point.** The upgrader refuses to run without one.
    From 25 this roll is forward only, so the set is also the only way
    back, and C3 relies on its `uploads.tar.gz` if it cleans images:

        php -r '$s = json_decode(file_get_contents($argv[1]), true); exit(($s["ok"] ?? false) === true && (time() - (int)($s["finished_epoch"] ?? 0)) < 26*3600 ? 0 : 1);' /var/backups/civis/state.json && echo FRESH || echo STALE

    If STALE, run `PP_BACKUP_DEST=/var/backups/civis bash "$REL/tools/backup.sh"`
    and check again. If the backup fails or is still STALE, STOP. Report
    the set id from `state.json` and `ls -l /var/backups/civis/sets/<set id>/`.

A8. **Baseline, and what DNS says.** Record every enabled prairiepost
    block's first `server_name`, the HTTPS front page's status, its
    `<title>` and the sorted list of stylesheets it links:

        snap "$W/before.tsv"

    Expect `200` with the paper's own masthead in the title, or `301` for
    a redirect alias such as `grandeprairiegazette.ca`'s apex. Anything
    else: STOP. Don't roll onto a box that is already unhealthy.

    Then, for information only (not a gate), what public DNS returns for
    each host:

        cut -f2 "$W/before.tsv" | while read -r h; do
          printf '%s\t%s\n' "$h" "$(getent ahosts "$h" | awk '{print $1}' | sort -u | tr '\n' ' ')"
        done > "$W/dns.tsv"

    Report every host whose addresses include one that isn't this box's
    (`hostname -I`). Expect none.

A9. **Each paper's address, before.** This is what #101 changes:

        addr "$W/addr-before.tsv"; own "$W/addr-before.tsv"

    What you will see, and why:

    | Domains | Each place names | Why |
    | --- | --- | --- |
    | `bisonbulletin.ca`, `brandonbulletin.com`, `burrardbrief.ca`, `cariboocompass.ca`, `kitchenerchronicle.com`, `londonlookout.com`, `mississaugamonitor.com`, `portagepress.ca`, `redriverregister.ca`, `rideaureview.ca`, `steeltownstandard.ca`, `surreystandard.ca`, `torontotelegraph.ca` | `prairiedispatch.ca` (`FOREIGN`) | launched config-edit-free; the old code takes the config's default address |
    | every other domain, the hub and `www.grandeprairiegazette.ca` included | the domain itself (`own`) | its tenant config names it |

    This is the state the same check found from outside on 25 Nov. It is
    information, not a gate. Report both lists, and any domain that
    differs from the table.

A10. **The ingest routes and the upload limit, before.** Run
    `apis "$W/apis-before.tsv"`. Expect every route and the 1.5 MB body to
    answer `401` on every domain. Report any other value. A non-`401` here
    predates this roll: report it, don't fix it, and carry on.

A11. **The routes and the block edits, before.** Run
    `routes "$W/routes-before.tsv"`, then count each block's edits:

        blocks() {  # $1: output file — block, =404 fallbacks, PHP handlers, router markers, tips markers
          for f in /etc/nginx/sites-enabled/*; do
            vh=$(readlink -f "$f"); grep -q '/var/www/prairiepost-' "$vh" || continue
            printf '%s\t404=%s\tphp=%s\trouter=%s\ttips=%s\n' "$(basename "$f")" \
              "$(grep -cE 'try_files[[:space:]]+\$uri[[:space:]]+\$uri/[[:space:]]+=404;' "$vh")" \
              "$(grep -cE '^[[:space:]]*location[[:space:]]+~[[:space:]]+\\\.php\$' "$vh")" \
              "$(grep -c pp-front-controller "$vh")" "$(grep -c pp-tips-unlogged "$vh")"
          done > "$1"
        }
        blocks "$W/blocks-before.tsv"

    What you will see depends on the release A3 found:

    | Release | `blocks-before.tsv` | `routes-before.tsv` |
    | --- | --- | --- |
    | `72f8adccbcca` or `7cb82fb9c20a` | `404=1` on the hand-written blocks (14 when this was written), `404=0` on the generated ones; `php=1` and `router=0 tips=0` everywhere | `about 404` on the hand-written blocks, `about 200` on the generated ones; `tips 404` and `post 404` everywhere, because `/tips` doesn't exist yet |
    | `1458b09870e0` | `404=0` everywhere; `tips` equal to `php` on every block; `router` equal to `php` on the blocks that were hand-written | `about 200`, `tips 200` and `post 422` on every paper; on the hub, `tips 302` to its `#contact` |

    This is information, not a gate. Report both files.

A12. **The box's clock data.** #97 prints each paper's times on its own
    clock, so the time-zone database must know that British Columbia,
    Alberta and Manitoba stopped changing clocks in 2026 (tzdata 2026e or
    newer). Ask PHP for 22:00 UTC on 24 Nov 2026 in Vancouver, Edmonton
    and Winnipeg:

        php -r 'echo timezone_version_get(), " | "; foreach (["Vancouver","Edmonton","Winnipeg"] as $c) echo (new DateTime("2026-11-24 22:00 UTC"))->setTimezone(new DateTimeZone("America/$c"))->format("G "); echo "\n";'

    Expect `0.system | 15 16 17`. `0.system` means PHP reads the system's
    tzdata. If the hours read anything else, update that one package and
    reload PHP:

        apt-get install -y --only-upgrade tzdata
        systemctl reload "$(systemctl list-units --type=service --no-legend | grep -oE 'php[0-9.]+-fpm\.service' | head -1)"

    Then run the check again. If it still doesn't read `15 16 17`, or if
    PHP doesn't report `0.system` (its own bundled database, which apt
    can't update), STOP. With old data, the roll would print every
    British Columbia time an hour early. Report the `tzdata` version
    before and after (`dpkg-query -W tzdata`).

A13. **The weather source is reachable.** The new job reads Environment
    and Climate Change Canada's public API over HTTPS:

        curl -sS -m 20 -o /dev/null -w '%{http_code}\n' 'https://api.weather.gc.ca/collections/citypageweather-realtime/items/mb-38?f=json'

    Expect `200`. Anything else isn't a stop, because the weather widgets
    show nothing without a fresh reading and nothing else depends on
    them. Report it, and expect C2 to fail the same way.

A14. **The hub's cron file.** Show it with `cat /etc/cron.d/civismedia`.
    Every job line names `$HUBREL` and no other release. Expect monitor,
    agents, analytics and watch. Expect no `weather` line unless
    production is on `1458b09870e0`, in which case the superseded brief's
    C1 added one; C1 here leaves it. `/var/log/civis` must exist, since
    the other jobs log there. If the file is missing, or names another
    release: STOP.

A15. **What C3 needs,** if it turns out to have work. All three must
    hold, or STOP:
    - `command -v runuser` prints a path;
    - `stat -c '%U:%G %a' /var/www/prairiepost-shared-uploads` reads
      `www-data:www-data 2775`;
    - `php -r 'exit(extension_loaded("fileinfo") ? 0 : 1);' && echo OK`
      prints `OK`.

## Step B — The roll

    cd "$W" && PP_UPGRADE_SHA=6e3cfab33b323a7facc93e96eb268eecc27b46cc bash ./upgrade-papers.sh

What you should see, in order:

- Every enabled prairiepost block is mapped (27 when this was written).
  Foreign tenants are skipped.
- The titles are captured.
- `extracted tree … it is the pinned commit`.
- The preflight passes: `recovery point: <set id> (fresh, ok)`.
- `shared uploads: <size> at /var/www/prairiepost-shared-uploads`.
- New directories `/var/www/prairiepost-6e3cfab33b32-shared` and
  `-civismedia` are built.
- **From `72f8adccbcca` or `7cb82fb9c20a`:**
  - for each hand-written block from A11:
    `<block>: legacy block — paths no rewrite names now reach router.php`;
  - for every block: `<block>: /tips kept out of the access log`;
  - the stored version is read as 25, and the migration runs step 26:
    `stored schema version after the step: 26 — ROSE from 25. The old release is no longer a fallback for this schema.`
    The scheduled jobs are paused around that step and resumed after.
    Every paper answers 503 from the moment step 26 commits until nginx
    reloads, which takes seconds. That is the readiness gate on the old
    code, by design.
- **From `1458b09870e0`:** no block edit lines, because every block
  already carries both markers, and
  `stored schema version after the step: 26 (unchanged)`.
- nginx is tested and reloaded.
- Every domain is verified against the saved baseline, with 200 and its
  own title.
- `Done`. From 25 the version rose, so the closing text offers **no**
  repoint: pointing a group back at its old directory would make every
  paper answer 503. From 26 it may offer one. Don't use it unless a D
  check fails and the owner says so.

The script writes its whole output to
`/var/log/prairiepost-upgrade/roll-<stamp>.log`, and a dropped SSH
connection does not stop it. If your session drops, reconnect and read
the log; never start a second run while A2's lock is held. If the run was
**interrupted** before `Done`, run the identical command once more: the
upgrader finishes a stopped roll at its own commit, and its block edits
won't repeat. If it **fails** (`FATAL`, or a domain failing
verification), STOP and report. Before step 26 commits, the upgrader puts
a failed group back by itself. After it commits, nothing goes back, and
the upgrader says what is serving. Don't repoint anything yourself;
recovery comes back here with your report.

## Step C — After the roll

`$NEW` is the papers' new root, `/var/www/prairiepost-6e3cfab33b32-shared`,
and `$HUBNEW` the hub's (`-civismedia`). Take both from the enabled
blocks after `Done`, as in A3.

C1. **The weather job's cron line.** The upgrader repoints cron lines but
    never adds one, so the new job needs a line in the hub's file. Every
    job line there must now name `$HUBNEW` and no other release:

        CF=/etc/cron.d/civismedia
        grep -oE 'cd /var/www/prairiepost-[^ ]+' "$CF" | awk '{print $2}' | sort -u

    That must print exactly `$HUBNEW`. Anything else: STOP. Then, only if
    the file has no weather line yet:

        if ! grep -q 'run.php weather' "$CF"; then
          cp -p "$CF" "$W/civismedia.cron.before"
          [ -z "$(tail -c1 "$CF")" ] || echo >> "$CF"
          printf '%s\n' "7,37 * * * * www-data cd $HUBNEW && PP_SITE=civismedia php cron/run.php weather >> /var/log/civis/weather.log 2>&1" >> "$CF"
        fi
        cat "$CF"; stat -c '%U:%G %a' "$CF"

    Expect the old lines unchanged and exactly one weather line naming
    `$HUBNEW` (added now, or added by the superseded brief and repointed
    by the upgrader), and the file still `root:root 644`. That is the line
    `tools/vps/deploy-civis.sh` writes. The job runs at :07 and :37 past
    each hour.

C2. **Its run, now.** Run it once as cron will, without waiting:

        cd "$HUBNEW" && runuser -u www-data -- env PP_SITE=civismedia php cron/run.php weather; echo "exit $?"

    Expect one `ok    <paper>: <place> <temperature>…` line per live paper
    that reports weather (18 when this was written), at most a few `WARN`
    lines for a city ECCC didn't answer, then
    `<n> city(ies) refreshed, <f> failed.` and `exit 0`. Papers sharing a
    city (three use Winnipeg's) share one fetch. If every city fails, you
    get an `ERROR` line and `exit 1`; A13 usually says why. That isn't a
    stop: report it. The weather widgets simply stay hidden until a run
    succeeds.

C3. **The stored images,** if no earlier brief cleaned them. The tool is
    in every release from `72f8adccbcca` on. Ask it, without changing
    anything:

        UP=$(readlink -f "$NEW/uploads")
        cd "$NEW" && runuser -u www-data -- php tools/strip-image-metadata.php > "$W/strip-dry.txt"; echo "exit $?"
        tail -3 "$W/strip-dry.txt"

    `readlink -f "$HUBNEW/uploads"` must print the same `$UP` (expect
    `/var/www/prairiepost-shared-uploads`); two different directories:
    STOP. `exit 1` means a file couldn't be read even by its owner: STOP
    and report the `FAIL` lines. The summary reads
    `<n> image(s) in /var/www/prairiepost-shared-uploads: <m> carried embedded metadata …`.
    If `m` is 0, the images are already clean: report the summary and go
    on to Step D. If `m` is more than 0, clean them in the order below,
    and report each:

    1. Record every image's owner, group and mode into
       `uploads-before.tsv`:

           find "$UP" -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.webp' -o -iname '*.gif' \) -printf '%P\t%u:%g\t%m\n' | sort

    2. Apply, as `www-data`, into `strip-apply.txt`:

           runuser -u www-data -- php tools/strip-image-metadata.php --apply

       Expect `exit 0` and `Cleaned <m>.`. On `exit 1`, run it once more;
       `FAIL` lines that remain: STOP.

    3. Dry-run again, into `strip-after.txt`. It must report
       `0 carried embedded metadata`.

    4. Run the same `find` into `uploads-after.tsv`, and `diff` the two
       lists. No line may start with `<`.

    5. `find "$UP" -name '.pp-strip-*'` must print nothing.

    6. Fetch three `CLEAN` files through a paper's domain. Each must be
       `200`, an `image/…` type, and `SAME` under `cmp` with the file
       on disk.

    The A7 set holds every original. Don't delete, move or edit any
    backup set.

## Step D — Verification (two-failure hard stop)

D1. **The upgrader finished.** Its log ends with `Done`, and every domain
    passed its masthead check. Report the log path.

D2. **Schema.** `cd "$NEW" && php tools/migrate.php --status` must show
    `state:   ready (stored version 26, target 26)`.

D3. **Everything points at the new release.** Every enabled prairiepost
    block's root names `/var/www/prairiepost-6e3cfab33b32-<label>`. Every
    file in `/etc/cron.d` that names a prairiepost release names the new
    one, none is left `.paused`, and `/etc/cron.d/civismedia` has exactly
    one weather line.

D4. **Every paper names itself.** This is #101's check:

        addr "$W/addr-after.tsv"; own "$W/addr-after.tsv"

    Every line must read `own`: on every domain, the front page's
    canonical link, its `og:url`, every sitemap URL and the feed all name
    the domain itself (`www.grandeprairiegazette.ca` for Grande Prairie,
    whose bare domain forwards there). Then `diff` it against
    `addr-before.tsv`. The thirteen `FOREIGN` lines from A9 now name
    their own host, and every other line is unchanged apart from its
    counts. A line that was `own` before and isn't now is this roll's
    failure.

    Then ask the code the same question from the command line, as cron
    and the newsletter do (information, not a gate):

        cd "$NEW" && for s in bison-bulletin brandon-bulletin burrard-brief cariboo-compass kitchener-chronicle london-lookout mississauga-monitor portage-press red-river-register rideau-review steeltown-standard surrey-standard toronto-telegraph prairiedispatch; do
          printf '%-22s ' "$s"; PP_SITE=$s php -r 'require "app/bootstrap.php"; echo site_url(), "\n";'
        done

    What you will see depends on what the server's config gives a run with
    no hostname, which this brief can't know:

    | The config gives a command-line run | Each line reads | Why |
    | --- | --- | --- |
    | the flagship's address, as it gives the web | the paper's own `https://` address, the host its `addr-after.tsv` line names, and `https://prairiedispatch.ca` for the flagship alone | #101 replaces another paper's address with the paper's own |
    | no address at all | `http://localhost` on every line | an empty `site_url` means "the request's host", and there is no request |

    Report which. Neither is a failure of this roll. No scheduled job
    sends an address anywhere today: the watch job's alert email has no
    recipient set, and the newsletters aren't sending.

D5. **Front pages unchanged.** Run `snap "$W/after.tsv"` and `diff` it
    against `before.tsv`. Every domain must show the same status, the same
    title and the same stylesheets. No PR here changes a front page's
    title or stylesheet links. The weather and the clocks change only the
    page's body, and #101 only the addresses.

D6. **Ingest routes and the upload limit unchanged.** Run
    `apis "$W/apis-after.tsv"` and `diff` it against `apis-before.tsv`.
    They must be identical. A value that was `401` before and isn't now
    is this roll's failure. A gap that A10 already showed is not: report
    it.

D7. **The new routes answer everywhere.** Run `routes "$W/routes-after.tsv"`.
    Expect, on every paper:
    - `about 200`;
    - `tips 200`;
    - `post 422`, which is the form refusing a tip with no token, before
      it writes anything.

    On the hub, expect `about 200`, `tips 302https://<hub host>/#contact`
    (it has no tips line, so `/tips` goes to its contact form) and
    `post 302`. Any `404` or `5xx` is a failure.

D8. **The tips form stays out of the access logs.** For each domain, make
    one request to `/tips` and one to the front page, each carrying its
    own random marker. Then look for both markers in nginx's logs:

        while IFS=$'\t' read -r block H code rest; do
          H=$(canon "$H" "$code"); t=$(openssl rand -hex 8); f=$(openssl rand -hex 8)
          get "$H" -o /dev/null "https://$H/tips?probe=$t"
          get "$H" -o /dev/null "https://$H/?probe=$f"
          printf '%s\t%s\t%s\n' "$H" "$t" "$f"
        done < "$W/before.tsv" > "$W/probes.tsv"
        sleep 2
        while IFS=$'\t' read -r H t f; do
          printf '%s\ttips-logged=%s\tfront-logged=%s\n' "$H" \
            "$(grep -rlF "probe=$t" /var/log/nginx/ 2>/dev/null | wc -l)" \
            "$(grep -rlF "probe=$f" /var/log/nginx/ 2>/dev/null | wc -l)"
        done < "$W/probes.tsv" > "$W/probes-found.tsv"
        cat "$W/probes-found.tsv"

    Expect `tips-logged=0` and `front-logged=1` (or more) on every domain.
    `front-logged=0` means that block logs somewhere other than
    `/var/log/nginx/`. Report it as "can't confirm" for that domain; it
    isn't a failure. `tips-logged` above 0 is a failure.

D9. **The block edits, counted.** Run `blocks "$W/blocks-after.tsv"` and
    compare it with `blocks-before.tsv`. Expect `404=0` everywhere: no
    `=404` fallback is left in any block that had one. Every block has
    `tips` equal to its `php` count. The blocks that were hand-written
    also have `router` equal to their `php` count, and the generated ones
    have `router=0`. (Each edit goes beside each PHP handler, so a file
    with two serving `server`s has two of each.) From `1458b09870e0`,
    `blocks-after.tsv` equals `blocks-before.tsv`. Then `nginx -t` once
    more: it must pass.

D10. **The weather is on the fronts.** For each paper whose C2 line said
    `ok`:

        get "$H" "https://$H/" | grep -c 'weather.gc.ca'

    Expect at least `1`. That is the link every reading carries to its
    forecast. On the papers C2 didn't list, and on the hub, expect `0`.
    A reading only shows while it is under three hours old, so run this
    within the hour after C2.

D11. **Each paper's clock.** Ask four papers which clock they print on:

        cd "$NEW" && for s in kermode-chronicle portage-press edmonton-echo kitchener-chronicle; do
          printf '%-22s ' "$s"; PP_SITE=$s php -r 'require "app/bootstrap.php"; echo pp_paper_zone()->getName(), " ", pp_date("G:i"), "\n";'
        done

    Expect `America/Vancouver`, `America/Winnipeg`, `America/Edmonton` and
    `America/Toronto`, each with that city's current time.

D12. **The new code is in place,** in both roots:

        for r in "$NEW" "$HUBNEW"; do
          printf '%s' "$r"
          for f in app/weather.php cron/weather.php app/whiteboard.php tips.php admin/whiteboard.php app/freshness.php; do
            printf ' %s=%s' "$f" "$(test -f "$r/$f" && echo yes || echo no)"
          done
          printf ' address-fix=%s' "$(grep -c 'function pp_site_url_resolve' "$r/app/bootstrap.php")"
          printf ' schema=%s\n' "$(grep -oE "PP_SCHEMA_VERSION', [0-9]+" "$r/app/bootstrap.php" | grep -oE '[0-9]+$')"
        done

    Expect every file `yes`, `address-fix=1` and `schema=26` for both
    roots.

D13. **The newsroom's whiteboard exists, behind sign-in.**

        get kermodechronicle.ca -o /dev/null -w '%{http_code} %{redirect_url}\n' https://kermodechronicle.ca/admin/whiteboard.php

    Expect `302` to `https://kermodechronicle.ca/admin/login.php`.

D14. **The owner's analytics tag survives.**

        get kermodechronicle.ca "https://kermodechronicle.ca/" | grep -c 'G-RTEJHC0DFB'

    Expect `2`.

## If something fails

- **The upgrader fails before step 26 commits** (or from 26, before
  `Done`): it puts the failing group back on its old release by itself.
  Report its output and STOP.
- **It fails after step 26 commits:** nothing goes back. The old release
  answers 503 at schema 26. The upgrader says what each domain is
  serving; report that and STOP. The recovery is forward: a fixed commit
  and a new roll, or the owner's decision to restore the A7 set
  (`docs/MIGRATION-COMPAT.md`, rule 2).
- **C1 or C2 fails:** the roll stands; report and carry on to D. A
  missing weather line only means no weather. Put the cron file back from
  `civismedia.cron.before` if your edit went wrong.
- **C3 fails:** STOP where it says to. Every original is in the A7 set,
  and restoring is the owner's decision.
- **D fails:** STOP and report. Don't edit a config file to make D4 pass;
  a domain still naming another paper's host comes back here with its
  `addr-after.tsv` line and the D4 command-line output.

## Out of scope

- Any edit to `config.php` or `app/config.site.php`, in any release.
- Minting, revoking or re-minting ingest keys, and any use of a stored
  token.
- Sending a tip through `/tips`, or anything else that writes content.
- Any backup set, old or new: don't delete or change one.
- System packages other than `tzdata` (A12).
- Terminal City, Western Wire, Bleuet Blanc and Turtle Island's launches,
  and any other launch.
- Indexing, newsletters, deleting old release directories, the catch-all
  `default_server`, and the test residue on Surrey (#315, #316).
- Anything in a foreign tenant's vhost, root or cron.

## Report

1. **Step A:** A1–A15 results. Include:
   - the releases in service, the pin check output, and which earlier
     briefs had run;
   - the upgrader's sha256 and `migrate.php --status` verbatim;
   - the backup set id;
   - `before.tsv` and the DNS lines A8 asks for;
   - `addr-before.tsv` with its `own`/`FOREIGN` list;
   - `apis-before.tsv` (or "all 401");
   - `blocks-before.tsv` and `routes-before.tsv`;
   - A12's output before and after any `tzdata` update, with the package
     versions;
   - A13's status, and the hub's cron file.
2. **Step B:** include:
   - the exact command and the log path;
   - the groups and directories built;
   - every block edit line (or that there were none);
   - the stored version before and after;
   - each domain's verification line, and `Done`.
3. **Step C:** the cron file after C1; C2's full output and exit; C3's
   summary, and the evidence for its six steps if it had work.
4. **Step D:** a table of item, result and evidence for D1–D14, with the
   per-domain tables for D4, D5, D6, D7, D8 and D10.
5. **The earlier rolls' reports.** If you hold the report of the
   `7cb82fb`, `72f8adc` or `1458b09` run, attach it: none reached the
   repository.
6. **Anything unexpected,** and anything you chose not to do and why.
