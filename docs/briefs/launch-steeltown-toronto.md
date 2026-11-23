# Brief — launch Steeltown Standard and Toronto Telegraph

**For: the VPS agent** (root SSH; server work only). Written 23 Nov 2026.

You are taking two brand-complete papers live on the network's VPS:
**Steeltown Standard** (`steeltownstandard.ca`, slug `steeltown-standard`,
template `steeltown`) and **Toronto Telegraph** (`torontotelegraph.ca`,
slug `toronto-telegraph`, template `telegraph`). This is **launch-only
work**: it runs from the release the enabled nginx blocks already serve.
It needs no pin and no release roll.

Each paper's runbook in the release, `DEPLOY-STEELTOWN.md` and
`DEPLOY-TORONTO.md`, is the procedure. This brief adds a production-state
check before them, two amendments inside them, and a regression check
after them. Where the brief and a runbook differ, the brief wins.

## Ground rules

- Server work only. You hold no ingest token, and you file, edit and
  publish no stories.
- **No manual database edits.** The seeder writes everything.
- **No config edits.** Tenant resolution is database-first, and the seeder
  writes each paper's hostnames. Leave `config.php` and
  `app/config.site.php` untouched in every release.
- Touch no other paper's nginx block, cron file or config. The only nginx
  changes in this brief are the two new blocks it creates.
- Don't run `tools/vps/upgrade-papers.sh` or `tools/migrate.php --apply`.
  Rolls and migrations are not part of this brief.
- Foreign tenants (the Institute under `/var/www/cies-*`, Calgary Dispatch
  under `/srv/calgarydispatch/`) are out of scope. Never require anything
  of them. One shared fact applies, though: a broken block anywhere fails
  the global `nginx -t`.
- **Discover server facts; don't take them from this brief.** Paths,
  sockets, releases and addresses below are what was true when it was
  written, given so you can recognise a match.
- **Hard stops:** a verification item that fails twice; any `nginx -t`
  failure; any STOP condition below. On a stop, leave the box as it is
  (except as stated), report exactly, and wait.

## Step A — Production state (read-only; report before changing anything)

A1. **No roll is running.** `fuser /run/lock/prairiepost-upgrade.lock`
    must name no process. If one holds it, STOP.

A2. **Releases in service.** For every enabled block in
    `/etc/nginx/sites-enabled/`, strip inline comments, `readlink -f` the
    file, and read its `root`. Report each distinct prairiepost release
    root and how many blocks serve it. **`$REL` is the papers' release
    root: the one `rideaureview.ca`'s block serves.** That block is a
    generated block, the same kind this brief creates.

A3. **The 22 Nov roll.** A brief issued 22 Nov rolled commit `835f90b`
    (release name `prairiepost-835f90b85d2a-<label>`), and no report came
    back. Before it, production was `prairiepost-4ff557d2b737-<label>`.
    Report which release is in service, by name. Either is fine for this
    launch: both contain both papers' brand builds.

A4. **No stopped roll.** In every root from A2: if `.pp-roll-baseline`
    exists and neither `.pp-roll-complete` nor `.pp-roll-held` does, a roll
    stopped there. STOP and report. Only a run at that commit can finish
    it.

A5. **Schema ready.** `cd "$REL" && php tools/migrate.php --status`. It
    must report the schema ready for `$REL`'s code. If it reports pending
    steps, STOP and report. Migrations belong to rolls.

A6. **A fresh recovery point.** The seed writes to the shared database, so
    a verified backup from the last 26 hours must exist. This is the
    upgrader's own read-only check:

        php -r '$s = json_decode(file_get_contents($argv[1]), true); exit(($s["ok"] ?? false) === true && (time() - (int)($s["finished_epoch"] ?? 0)) < 26*3600 ? 0 : 1);' /var/backups/civis/state.json && echo FRESH || echo STALE

    If STALE, run `PP_BACKUP_DEST=/var/backups/civis bash "$REL/tools/backup.sh"`,
    then check again. If the backup fails or is still STALE, STOP.

A7. **Baseline.** For every enabled prairiepost block, take its first
    `server_name` and record the HTTPS front page's status code and
    `<title>`. Expect 200 with the paper's own masthead, or a 301 for a
    redirect alias such as `grandeprairiegazette.ca`'s apex. Anything else:
    STOP. Don't launch onto a box that is already unhealthy. You will
    compare against this list in Step D.

## Step B — Steeltown Standard

Run `DEPLOY-STEELTOWN.md` from `$REL`, Steps 0 to 6, with these
amendments:

- **Step 1, DNS:** with `dig @1.1.1.1`, confirm `steeltownstandard.ca` and
  `www.steeltownstandard.ca` resolve to this box's public IPv4, the
  address the live papers resolve to. On 23 Nov, checked from outside,
  both pointed at 168.231.74.70, and no paper publishes an AAAA record.
  Confirm it; don't assume it.
- **Step 2, the block:** `$SOCKET` is the bare path after `unix:` in
  `rideaureview.ca`'s `fastcgi_pass`. Generate the block with
  `$REL/tools/vps/make-vhost.sh` exactly as the runbook shows. **Then,
  before `nginx -t`, add the upload-size directive.** `$REL`'s template
  predates it, so without it nginx refuses any request body over 1 MB,
  newsroom photo uploads included. Insert these lines directly after the
  generated `index index.php;` line, and change nothing else in the file:

        # Request bodies up to 10 MB: nginx's own default is 1 MB, which
        # refused a 1.6 MB agent upload with an HTML 413 before PHP ever saw
        # it. The app caps an image at 8 MB and answers anything larger with a
        # JSON 413; PHP's limits are raised to match by the release's .user.ini.
        client_max_body_size 10m;

  These are the lines the current `tools/vps/vhost.template` carries.
  Then `nginx -t`, and reload. If `nginx -t` fails, remove the new
  `sites-enabled` link, run `nginx -t` again to confirm the box is back to
  green, reload, and STOP.
- **Step 3, TLS:** as written. Confirm `listen 443 ssl` and
  `listen [::]:443 ssl` are both present, and that
  `client_max_body_size 10m;` is still in the file after certbot rewrites
  it.
- **Step 4, the seed:** as written. Expect "site created" for
  `steeltown-standard`, the pack's settings, and six story lines with
  slugs prefixed `steeltown-`. Report the output verbatim.
- **Step 5, verification:** items 1–8 as written, plus:
  9. **The upload limit is live.** A 1.5 MB body with no token must reach
     the app and be refused there with a 401, not at nginx with a 413:

         head -c 1572864 /dev/zero | curl -s -o /dev/null -w '%{http_code}\n' \
           -X POST -H 'Content-Type: application/octet-stream' \
           --data-binary @- https://steeltownstandard.ca/api/ingest-media

     Expect `401`. A `413` means the directive isn't in the block that
     serves HTTPS. Note that PHP's own 2 MB per-file limit stays until the
     next roll brings the release's `.user.ini`, as on every paper. That
     is expected and not part of this check.
- **Step 6, close-out:** as written.

If Step B hard-stops, roll back only this paper: remove its
`sites-enabled` link, `nginx -t`, reload. Leave the seeded rows in place
(no manual database edits). Report, and **don't start Step C**.

## Step C — Toronto Telegraph

Only after Step B has fully passed. Run `DEPLOY-TORONTO.md` from `$REL`,
Steps 0 to 6, with the same amendments as Step B, substituting:

- Hostnames: `torontotelegraph.ca` and `www.torontotelegraph.ca`. The
  registration is the `.ca`. The owner's package mockup shows a `.com`;
  don't "correct" it.
- Seed: `PP_SITE=toronto-telegraph`. Six stories, slugs prefixed
  `telegraph-`. All five desks already exist network-wide.
- Verification item 9: the same request against
  `https://torontotelegraph.ca/api/ingest-media`.

The same rollback applies if it hard-stops.

## Step D — Regression across the network

Repeat A7 for every block from the baseline. Every domain must return
the same status code and the same `<title>` as before. A difference is a
hard stop: report it at once. The two new blocks are the only change, so
removing their `sites-enabled` links and reloading reverts them.

## Out of scope

- Terminal City Times. Its DNS doesn't point at the VPS, and it waits for
  its brand package.
- Adding `client_max_body_size` to any existing block. The next roll's
  routes pass covers those.
- Minting ingest keys, enabling indexing, newsletters, and any roll or
  migration.

## Report

1. Step A: A1–A7 results, including the release in service (A3),
   `migrate.php --status` verbatim, the backup check result, and the
   baseline table.
2. For each paper: `$REL`, `$SOCKET`, the generated block's path, a diff
   of the inserted directive, `nginx -t` output at each step, the certbot
   result and certificate expiry, the seed output verbatim, the Step 5
   verification table (item, result, evidence), and item 9's status code.
3. Step D: the regression table, before and after.
4. Anything unexpected, including anything you chose not to do and why.
