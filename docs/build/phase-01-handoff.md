# Phase 1 handoff — security and reliable verification

For the independent auditor. Original finding IDs (F01, F02, F05, F06,
F11) are used throughout so coverage can be traced.

## A. Exact revision

- Repository: https://github.com/FTFNAnalytics/prairiepost
- Verified release branch: `claude/master-dashboard-control-room-nr3mp4`
  (confirmed as the string hardcoded in `tools/vps/upgrade-papers.sh`;
  CLAUDE.md documents it; production was deployed from its HEAD on
  2026-09-03)
- Base SHA: `3328704483e063c2fb99e658d416224457c02f57` — this is both
  the audited commit and the release-branch HEAD at the time of this
  work, so no audited finding had been fixed by newer work; nothing was
  reset.
- Working branch: `claude/western-wire-aggregator-iw8pth` (restarted
  from the base SHA; its previous unique commits were pre-squash
  duplicates of content already merged)
- Implementation commit: `e5c5fe0`; head = the branch tip carrying this
  document (the commit after `e5c5fe0`)
- PR: none opened (not requested). The branch is pushed for audit.
- Uncommitted work at start: none (clean tree after fetch/reset).

## B. Implemented changes

### F01 — stored XSS (body HTML + inline JSON-LD)

Previous behavior: `sanitize_html()` was `strip_tags` + three regexes;
`jav&#x61;script:` URLs passed through verbatim and the rendered link
executed on click. `json_encode(..., JSON_UNESCAPED_SLASHES)` in the
JSON-LD block let a headline containing `</script>` terminate the
element and execute on load. Both reproduced before fixing.

Implemented:
- `sanitize_html()` (app/helpers.php) delegates to HTML Purifier via
  `pp_purifier()` (app/security.php): parser-based, allowlisted
  elements/attributes, `URI.AllowedSchemes` http/https/mailto only,
  CSS limited to five properties, figure/figcaption/img-loading taught
  explicitly. Runs at save AND render, so pre-fix stored content is safe
  the moment this code serves it, before any cleanup.
- `pp_json_for_html()` (app/security.php): `JSON_HEX_TAG|HEX_AMP|
  HEX_APOS|HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE`, inert fallback on
  encode failure. Applied to the JSON-LD sinks (app/views/ui.php,
  app/views/front-civis.php) and the six inline `var X =` assignments in
  admin/social.php (previously safe only by accidental slash-escaping).
  Context sweep found no other stored-content-into-script sinks; the two
  copy-link `<script>` blocks in article templates are static.
- Cleanup: `tools/sanitize-content.php` — dry-run default, `--apply`,
  bounded batches (`--batch`), restart (`--start-id`), pre-rewrite
  revision snapshot per story (reason `sanitize`), drop/large-change
  flags for editorial review, idempotent.
- `admin/revision.php` restore re-sanitizes the revision body on the way
  back in (history rows may predate the parser-based sanitizer).

Files/functions: app/security.php (new), app/helpers.php
`sanitize_html`, app/views/ui.php `page_header`,
app/views/front-civis.php, admin/social.php, tools/sanitize-content.php
(new). Tests: tests/sanitize.test.php, tests/cleanup.test.php,
tools/xss-browser-check.mjs.

Limitations: Purifier normalizes markup (void tags become `<img />`,
style gains trailing `;`) — a render-class change, declared. `<figure>`
content model is registered as Flow; exotic legacy embeds (iframe,
object) are dropped by design and the cleanup tool flags stories that
lose tags.

### F02 — author approval bypass (revision restore and friends)

Previous behavior: `admin/revision.php` let an author restore any
revision of their own story regardless of status — restoring over the
editor-published live text (reproduced by the audit workflow). The
ordinary edit form also let an author rewrite + demote their own
published story (status silently forced to `draft`), and
`admin/autosave.php` checked state then wrote unguarded (TOCTOU).

Implemented:
- `pp_post_write_denied(array $user, array $post, string $action)`
  (app/helpers.php): THE policy. Editors/admins pass; authors need
  ownership AND a persisted status of draft/in_review. Called by
  post-edit (POST), autosave, revision restore, and posts.php delete
  (delete now also blocks authors on `scheduled`, not just published).
- `pp_guarded_post_update(int $id, array $fields, ?array $requireStatus)`
  (app/models.php): one atomic `UPDATE … WHERE id = ? AND status IN (…)`
  for authors' writes; editors pass `null` (unconditional). Dependent
  writes (tags, site mapping, snapshots, agent queue) run only after a
  write that landed.
- Alternate write paths audited: network-posts.php, agents.php,
  link-post.php are `require_editor()`-gated; ai-draft.php inserts
  status `draft` only; ingest.php inserts drafts (or wire-desk publish,
  which is the documented intended behavior and unchanged).

Tests: tests/authz.test.php — real HTTP against the dev server with
four accounts; the role/action/state matrix (edit/autosave/restore/
delete × draft/in_review/published/scheduled × own/other), CSRF forgery,
draft public invisibility, the stale-save races (author edit and
autosave landing after an editor publish — both land nowhere), and the
atomic guard across two live database connections.

Limitations: on MySQL an identical-content re-save reports 0 affected
rows and would show the conflict message (Postgres/SQLite count matched
rows; production is Postgres). Race tests are deterministic stale-
sequence + two-connection DB assertions, not a scheduler-level
interleaving harness.

### F05 — unsafe outbound requests (SSRF)

Previous behavior: `http_get()` followed redirects blindly with no
destination policy and unbounded buffering; `pp_url_is_public()` checked
A records only, at the initial URL only, with a validate-then-connect
gap; link previews (`pp_fetch_link_meta`) had no address policy at all.

Implemented: app/transport.php (new).
- `pp_http_get()`: http/https only, no URL credentials, A+AAAA
  resolution, explicit blocklist (loopback, RFC1918, CGNAT, link-local
  incl. 169.254.169.254, ULA, multicast, reserved, documentation, NAT64
  and v4-mapped embeddings judged as their embedded IPv4). Mixed
  public/private DNS answers reject the whole host — the documented
  policy; there is no fallback to an unvalidated address. Connections
  pin to the validated addresses via `CURLOPT_RESOLVE` (Host header, SNI
  and certificate verification unaffected); redirects are never followed
  by curl — each hop's absolute Location is re-validated, re-resolved
  and re-pinned, capped at 5 hops; bodies stream through a size cap
  applied AFTER decompression; overall + connect timeouts; the proxy
  environment is explicitly disabled (a proxy would resolve names itself
  and silently undo the pinning).
- `http_get()` (app/helpers.php) delegates to `pp_http_get()`, which
  migrates every untrusted caller at once: feed fetches (app/fetch.php),
  link previews, wire image caching, AI page drafting (app/ai.php:157),
  ingest images (ingest.php, with an 8 MB streamed cap), source-repair
  tools. `pp_url_is_public()` now rides the same policy. Fixed-origin
  authenticated clients (Anthropic in app/ai.php's own curl, Google in
  app/google.php) remain distinct by design; their credentials never
  enter the untrusted transport.
- `pp_store_image_bytes()` additionally requires the bytes to DECODE as
  an image (`getimagesizefromstring`), not just sniff as one.

Tests: tests/transport.test.php — 26 policy vectors across notations
(v4-mapped, NAT64, decimal literal via resolver), URL validation, mixed
DNS, and LIVE behavior against a loopback fixture through CLI-only test
hooks (`pp_http_test_hooks`, refuses to arm outside `PHP_SAPI === 'cli'`
+ `PP_TRANSPORT_TEST=1`; no production-reachable switch exists): pinned
fetches, relative/cross-host redirects, public→private and →metadata
redirect blocks, scheme-change redirect block, loops, DNS rebinding
between hops (resolver call-count asserted), streamed and decompressed
size caps, timeouts, HTTP errors, https against non-TLS failing closed,
and RSS/image consumers still working end to end.

Limitations: a full TLS-with-bad-certificate server isn't in the
fixture (php -S can't serve TLS); the fail-closed path is exercised via
https-to-plain-port. IPv6 literal-in-URL fetching is policy-tested but
not live-fetched (fixture binds v4 loopback).

### First-administrator setup (audit addendum)

Previous behavior: `admin/login.php` created the founding administrator
for WHOEVER visited first when `users_count() === 0`.

Implemented: the public form path is gone — first-run login shows a
pointer to the server console and refuses POSTs. `tools/setup-admin.php`
(new, CLI-only): passphrase from stdin (hidden at a TTY, pipeable), 10+
chars, atomic under a table lock (pgsql `LOCK TABLE`, sqlite `BEGIN
IMMEDIATE`, mysql `GET_LOCK`), refuses whenever any account exists, and
points at `tools/reset-password.php` (unchanged) for recovery.

Tests: tests/setup.test.php — visitor POST creates nothing; short
passphrase refused; exactly-one under two SIMULTANEOUS processes;
second run refused; founded account signs in; reset-password still runs.

### F06 — indexing controls and demo content

Previous behavior: no robots controls anywhere; unfinished papers were
indexable the moment DNS pointed at the box.

Implemented:
- `indexing_enabled` per-site setting; `pp_indexing_enabled()` treats a
  MISSING value as disabled. Every HTML surface renders
  `<meta name="robots" content="noindex, nofollow">` when disabled (both
  head emitters: ui.php `page_header` — newsletter archive included —
  and front-civis.php); feeds, sitemap and social cards send
  `X-Robots-Tag: noindex, nofollow` via `pp_robots_header()`. No
  robots.txt Disallow is used, so crawlers can always fetch and read the
  directive. The switch is a checkbox in admin/settings.php
  (`require_admin`), writes to the current site's row only; the seeder's
  defaults ship it `'0'`; no launch pack overrides it.
- `tools/demo-inventory.php` (new, read-only): matches launch-pack
  DECLARED slugs (provenance, not title-guessing) against the database;
  per-paper demo/agent/human counts, `--csv` per-story rows. Deletes
  nothing; Phase 5 owns cleanup.

Tests: tests/indexing.test.php — default noindex across page types and
non-HTML surfaces, a bare site row (no settings at all) still noindex,
enabling one site opens ONLY that site (asserted on the neighbour),
settings page auth, seeder default.

OPERATOR CONSEQUENCE (deliberate): on deployment every live paper
becomes noindex until an admin ticks "Search engines may index this
paper" per site. This phase does not enable indexing anywhere.

### Internal paths (audit addendum)

Previous behavior: only `app/`, `data/` and `config.php` were denied;
`/tools/*.php`, `/cron/*.php`, `/tests/`, `.git/`, `vendor/`, runbook
markdown and DB dumps were servable (tools are CLI-guarded, but the
paths were still reachable).

Implemented, in all three supported server configurations:
- `router.php` (dev server): percent-decoded judgment, traversal/NUL/
  backslash refusal, deny app|data|tools|tests|vendor|docs, dotfiles
  (ACME excepted), composer files, router.php itself,
  `.sqlite/.sql/.sh/.md/.bak/.dist/.lock`, and PHP execution under
  uploads.
- `.htaccess` (Apache): the same tree/dotfile/extension rules as
  RewriteRules + the FilesMatch deny extended to composer files.
- `tools/vps/vhost.template` (nginx): the same location denies, with the
  ACME prefix carved out.
- `cron/` stays web-reachable BY DESIGN: every cron script self-guards
  with the site's `cron_secret` (constant-time compare, 403) —
  web-cron support for hosts without a crontab. Verified in tests.

Tests: tests/paths.test.php — 25 denied paths (including `.git/config`
against the real repo), 9 encoded/traversal variants asserted to leak no
source, cron-403, and 10 legitimate routes/assets still answering,
through the dev server. Apache/nginx execution was NOT available in this
environment — see G.

### F11 — CI shell injection and the [render] waiver

Previous behavior: `${{ github.event.pull_request.title }}` was
interpolated directly into a `run:` script (shell injection by PR
title), and `[render]` in the title waived ANY baseline failure — seed
failures and 500s included.

Implemented:
- `.github/workflows/php-ci.yml`: title and base ref reach the shell
  only as env vars (`PR_TITLE`, `BASE_REF`), quoted; permissions stay
  `contents: read`; the render diff uploads as an artifact
  (`render-baseline`, 14 days) for reviewer inspection; palette JSON
  validation added as its own mandatory step.
- `tools/baseline.sh`: exits are a classification — 0 identical, 10
  render-difference-only, 2 seed failure, 3 THIS tree fails smoke, 4
  invalid/broken comparison ref, 5 no pages. The smoke contract per page
  is now HTTP 200 + non-empty body + the front page carrying its own
  site title (a blank 200 or wrong masthead is a failure, never a
  "render change"). Both trees run against one seeded database, so no
  cross-schema fixture mismatch arises; if schemas ever diverge that
  run fails as class 3/4 rather than passing as a visual change.
- `tools/render-gate.sh`: `[render]` (matched as data in a `case`) may
  excuse exit 10 ONLY; every other class fails regardless.

Tests: tests/rendergate.test.php (fast — every rc × declaration combo,
hostile titles with `$(…)`, backticks and quote-breaking asserted to
execute nothing via a marker file) and tools/test-baseline-classes.sh
(slow, real renders in disposable worktrees: identical → 0, template
attribute change → 10, induced 500 → 3, broken launch pack → 2, invalid
ref → 4). Results in F below.

## C. Reproduction and test instructions

Environment used: Linux, PHP 8.4.19 CLI with pdo_sqlite, pdo_pgsql,
curl, dom/libxml (HTML Purifier), mbstring, intl, gd, posix, pcntl;
SQLite for all tests. CI installs `php-cli php-sqlite3 php-mbstring
php-xml php-curl php-gd`. No config.php is required — every test writes
a disposable config and passes it via `PP_CONFIG` (honored only by the
cli/cli-server SAPIs, verified at app/bootstrap.php:14).

From a fresh clone of the branch:

    php -v                                     # 8.1+ (developed on 8.4)
    find . -name '*.php' -not -path './data/*' -not -path './vendor/*' \
      -print0 | xargs -0 -n1 php -l            # syntax: all clean
    php tests/run.php                          # 13 suites — all PASS
    bash tools/seed-all.sh /tmp/net.sqlite     # 17 packs, zero skips
    bash tools/baseline.sh                     # smoke: ~100 pages, exit 0
    bash tools/test-baseline-classes.sh        # slow: 5 classifications
    for f in assets/sites/*/palette.json; do php -r \
      'exit(json_decode(file_get_contents($argv[1]),true)===null?1:0);' "$f"; done

Browser XSS check (needs Chromium + `npm i playwright-core`): plant the
two fixtures as tests/cleanup.test.php does (raw INSERT of the encoded
link body and the `</script>` headline into a disposable DB), serve with
`PP_CONFIG=… php -S 127.0.0.1:8471 router.php`, then

    PP_CHROMIUM=/path/to/chrome node tools/xss-browser-check.mjs \
      http://127.0.0.1:8471 /story/<slug>

Expected: 8 `ok` lines (no execution on load/click/keyboard; JSON-LD
parses with the hostile headline as data). Actual: 8/8 ok, exit 0.

All suite results (actual, at the implementation commit): authz, cidr,
cleanup, imagestore, indexing, media, paths, rendergate, revisions,
sanitize, setup, throttle, transport — **all 13 PASS**; seed-all clean;
baseline smoke exit 0.

## D. Dependency and deployment packaging

- Sanitizer: **ezyang/htmlpurifier v4.19.0**, pinned exactly in
  composer.json + composer.lock (upstream tag commit
  `b287d2a16aceffbf6e0295559b39662612b77fcf`).
- Obtained via `composer require ezyang/htmlpurifier:4.19.0`. The dist
  endpoint was unreachable from this build environment, so composer
  installed from source; the package's VCS metadata and development
  directories (tests, docs, benchmarks, smoketests, maintenance, art,
  extras, plugins, configdoc) were removed before commit — `library/`,
  LICENSE, VERSION, README, CREDITS and composer.json are intact and
  byte-identical to the upstream tag. `composer install` from the lock
  restores the full package when upgrading.
- Reaching the immutable release: **vendor/ is committed** (2.4 MB), so
  the release tarball `upgrade-papers.sh` extracts already contains it —
  no build step, no network at deploy time, which matches the
  release-from-tarball model exactly.
- Loading: `pp_purifier()` requires the package's own
  `HTMLPurifier.auto.php` directly; the composer autoloader is not
  relied on at runtime.
- Runtime: Purifier caches serialized definitions under
  `data/cache/htmlpurifier` (gitignored; created at first use; falls
  back to no-cache when unwritable). Requires ext-dom (php-xml) — in CI
  and standard PHP installs; **operator note**: the production PHP-FPM
  pool must have `dom` enabled (stock Debian/Ubuntu php-xml).

## E. Data cleanup

- Dry run: `php tools/sanitize-content.php`
- Example (synthetic database: 12 seeded stories + 1 planted raw
  pre-fix body):

      DRY RUN — nothing is written. Add --apply to rewrite.
      would rewrite #13 Audit script marker </script><script>document.titl body -60 byte(s)

      scanned 13 post(s), would rewrite 1, 0 flagged for editorial review; last id processed: 13

  Note the 12 legitimate stories are byte-stable through the sanitizer —
  no over-rewriting.
- Affected fields: `posts.body` only (title/lede/etc. are output-escaped
  everywhere; revision bodies are sanitized at render and on restore).
- Recovery: each rewrite snapshots the pre-rewrite story into
  `post_revisions` (reason `sanitize`) FIRST; restore via the story's
  History panel.
- Batches of 200 (`--batch`), id-ordered, `--start-id` resume; the
  UPDATE is conditional on the body still matching what was read.
  Verification: re-run without `--apply` → must report 0.
- Migration required: **none** (no schema change in this phase; schema
  stays 19).
- NOT run against production (per instructions). Operator step, post-
  deploy, at the operator's choosing: dry-run, review flagged rows,
  `--apply`.

## F. Security evidence

- **F01 before**: `sanitize_html()` returned the encoded-javascript link
  verbatim; the JSON-LD block rendered
  `…"headline":"…</script><script>document.title=123456789</script>"…`.
  **After**: link renders as `<a>Audit sanitizer link</a>` (no href);
  JSON-LD renders `<\/script>…` and `JSON.parse` round-trips
  the headline. Chromium: title never becomes `123456789`, no execution
  on load, click, or keyboard (8/8 assertions).
- **F02 matrix** (tests/authz.test.php, all enforced): author on own
  draft/in_review — edit/autosave/restore/delete allowed; author on own
  published/scheduled — ALL refused with the live row asserted
  unchanged; author on another's story — refused regardless of state;
  editor/admin — full workflow incl. restore over published; author
  cannot reach published/scheduled through the status field; forged
  CSRF → 403, no change; drafts 404 publicly. Races: stale author edit
  and stale autosave after an editor publish both land nowhere;
  `pp_guarded_post_update` refuses across two live connections after a
  concurrent state change, and the editor path still writes.
- **F05 matrix** (tests/transport.test.php): 26 address vectors, 6 URL
  refusals, mixed-DNS whole-host rejection, direct/redirected access to
  private, metadata and non-http destinations blocked, rebinding caught
  with per-hop re-resolution asserted, 4 MB body and 2 MB-decompressed
  gzip both aborted at a 1 MB cap, timeout, 404, https fail-closed,
  RSS + PNG consumers intact. No credentials exist on this transport to
  leak; provider clients keep their own fixed-origin transports.
- **Setup**: visitor POST on a zero-user install creates nothing; two
  simultaneous CLI provisioners → exactly one founder (asserted count
  and exit codes).
- **Path/server matrix**: dev-server router fully exercised (34 denied
  paths/variants, no source bytes leaked, legit routes green).
  Apache/.htaccess and nginx template carry the same rules;
  configuration committed, execution NOT verified here (see G).
- **Indexing matrix**: noindex meta on /, search, corrections, about,
  newsletter archive; X-Robots-Tag on feed + sitemap; bare-settings site
  noindex; per-site enable flips only its own site (neighbour asserted
  both before and after).
- **CI classification**: gate tests — every failure class ×
  [render] declared/undeclared behaves per spec; hostile titles execute
  nothing (marker file asserted absent). Real-render classification runs
  (tools/test-baseline-classes.sh) — results recorded below.

Real-render classification results (actual, at `577f340`):

    ok   identical tree     -> exit 0
    ok   render difference  -> exit 10
    ok   induced HTTP 500   -> exit 3
    ok   broken launch pack -> exit 2
    ok   invalid ref        -> exit 4
    All classifications correct.

Characterization diff of THIS work against the audited base
(`tools/baseline.sh 3328704…` → exit 10, a pure render class; feeds and
sitemaps byte-identical). The entire per-page difference is three
intended causes, verified on a sampled story page:

1. `+ <meta name="robots" content="noindex, nofollow">` — F06 default.
2. The JSON-LD block now escapes `/` and `'` (`\/`, `'`) —
   `pp_json_for_html`'s HEX flags; the decoded data is identical.
3. Stored-body entities normalize to their UTF-8 characters
   (`&ldquo;` → `“`) — Purifier's canonical output; the text is
   identical.

Because of this, the PR that lands this phase on the release branch
must carry `[render]` in its title.

## G. External checks and unresolved issues

Passed locally: everything in C and F above.

NOT RUN, with reasons and procedures:
- **Apache and nginx execution of the path rules.** Neither server is
  installed in this environment. Procedure: on a staging box, generate a
  vhost with `tools/vps/make-vhost.sh`, then replay the denied-path list
  from tests/paths.test.php with curl against nginx (expect 403/404,
  no bytes of source), and the same against an Apache host with
  AllowOverride enabled. The dev-server router — same rule set — is
  fully tested.
- **PostgreSQL-backed run of the new suites.** The suites are
  SQLite-backed by design (disposable). The changed SQL is
  driver-portable (one conditional UPDATE, one LOCK TABLE statement
  gated by driver). Procedure: point a disposable `PP_CONFIG` at a
  scratch Postgres schema and re-run tests/authz.test.php,
  tests/setup.test.php.
- **Live-TLS bad-certificate case** (see F05 limitations).
- **Production anything**: deployment, credential changes, live
  cleanup, indexing enablement — all explicitly out of scope and not
  done. Local/CI verification complete; staging NOT verified;
  production NOT deployed.

Operator/administrator actions queued:
1. **GitHub default branch is still the obsolete
   `claude/prairie-post-news-site-hiffgl`** (verified via
   `git remote show origin` on 2026-09-09). Repository admin: Settings →
   Branches → switch default to
   `claude/master-dashboard-control-room-nr3mp4`. No API/tool in this
   session can do it, and branch deletion is refused by the git proxy.
2. After this phase merges to the release branch and deploys: run the
   content cleanup dry-run, then `--apply` (E); tick indexing per
   launched paper when editorially ready (F06 note); confirm the FPM
   pool has ext-dom.
3. Any future PR that intentionally changes rendered bytes must carry
   `[render]` in its title — including the PR that merges THIS work
   (noindex meta + Purifier markup normalization change most pages).

Known risks: Purifier adds ~10–30 ms to first-render per request until
its definition cache warms (then ~1–3 ms); stored markup normalization
means the first `--apply` cleanup will rewrite many rows harmlessly
(dry-run first, as designed — on seeded content it rewrites nothing);
MySQL identical-save edge in B/F02 (production is Postgres).

## H. Summary for the owner

Every finding from the audit is closed in code on branch
`claude/western-wire-aggregator-iw8pth` (audit the branch tip; the
implementation commit is `e5c5fe0` over base `3328704`): stored stories
can no longer execute script anywhere they render; authors can no longer
change published work by any path, including mid-publish races; the
server will not fetch its own insides no matter what URL or redirect it
is handed; a stranger can no longer become your first administrator;
every paper is invisible to search engines until you switch it on, per
paper; and a PR title can no longer run code in CI or wave real failures
through. All 13 test suites, the full 17-paper seed, the ~100-page
render smoke and a real-Chromium XSS check pass. Not verified here:
Apache/nginx enforcement (dev-server equivalent is tested), Postgres-
backed test runs, and anything on the live box — nothing was deployed.
Two things only you can do: flip GitHub's default branch to the release
branch, and — after deploy — run the content cleanup and turn indexing
on for the papers you consider ready.
