# Roadmap — the platform plan and where it stands

## Current state · 24 Nov 2026

**Live:** 27 sites, which is 26 papers and the CivisMedia hub. All of
them serve release `feb2b1f4f6eb` at schema version 25 (the VPS agent's
report of 23 Nov). Steeltown Standard and Toronto Telegraph are in that
count; their launch report hasn't reached the repository. One foundation
paper, `terminal-city-times`, waits for its brand package. Twelve more
mastheads are named in `NETWORK-EXPANSION.md`; none is registered or
built.

**In flight:** the roll to `7cb82fb` (the Stories list's bulk status
change, and each paper's newsroom wearing that paper's branding). It is
code-only from `feb2b1f4f6eb`, with no migration. The brief is
`docs/briefs/roll-7cb82fb9c20a.md` (revision 2) and its report is
awaited.

**Built, not rolled:** photo metadata stripping (9.1 below). It reaches
production with the roll after `7cb82fb`.

**The platform phases below (0–3):** Phases 2 and 3 are done. Phase 0
is done except the GitHub default branch, which is the owner's switch.
Phase 1 is done except converting the 14 hand-written nginx blocks and
the catch-all server, both of which have moved to Phase 9. Phase 3's
ingest path has kept growing since it closed:

- agent ingest v2: media upload and hub-minted keys (27 Sep);
- the publish-on-image lane (28 Sep);
- story lookup (21 Nov);
- the read lane and the two edit lanes, for drafts and published
  stories (22–23 Nov).

The contract is `docs/api-ingest.md`.

**The hub's phases (1–8)** are in `PLAN-CIVIS.md`, with their runbooks
in `DEPLOY-CIVIS.md` §9–14. Phase 8 (resilience) built the backups, the
five-minute watch, revisions, session lifecycle and the edge kit. The
hub's own Roadmap page (database-backed, edited in the admin) is the
newsroom's working copy of that plan; this file is the repository's.

| Phase | Status, 24 Nov |
| --- | --- |
| 0 Safety nets | 0.1, 0.2, 0.4 done. 0.3 waits on the owner. |
| 1 Launch machinery | 1.1, 1.4, 1.5 done. 1.2 is done for new blocks; the 14 legacy blocks and 1.3 are now 9.2. |
| 2 Template registry | Done. 2.3 is in the backlog by owner decision. |
| 3 Hermes ingest | Done, and extended (above). |
| Hub 1–8 | See `PLAN-CIVIS.md` and `DEPLOY-CIVIS.md`. |
| **9 Exposure and upkeep** | **Next. 9.1 built 24 Nov; 9.2–9.5 open.** |

---

## Phase 9 — Exposure and upkeep (next)

It is numbered after the hub plan's Phase 8, the last numbered phase on
either plan. Every item below was confirmed open against the code on
24 Nov, not taken from older notes.

**9.1 — Strip embedded metadata from every stored image.** *Built
24 Nov, not rolled.* Until now every path stored image bytes exactly as
they arrived:

- the newsroom's uploads, including ads and profile photos;
- `POST /api/ingest-media`;
- image URLs fetched by `POST /api/ingest`;
- wire images cached from a pasted link.

So a phone photo published its GPS position, its camera and the time it
was taken. For a newsroom that is a source-protection failure.
`app/imagemeta.php` rewrites JPEG, PNG, WebP and GIF containers
losslessly, copying the compressed image data byte for byte. It keeps
only what a picture needs to look right: the colour profile, the JFIF
and Adobe markers, the animation and looping blocks, and a non-default
orientation, kept on its own. A file it cannot walk end to end is
refused, never stored unexamined.

C2PA Content Credentials are removed too. A manifest can embed the same
EXIF and creator assertions, and once anything else is removed it would
no longer validate. Disclosure that a graphic was generated belongs in
the visible credit line.

What remains after the roll is cleaning the uploads already on disk.
`tools/strip-image-metadata.php` runs a dry run first and `--apply`
second; it is lossless, atomic per file, keeps each file's owner and
mode, and is safe to re-run. It needs a VPS brief run from the live
release. The backup sets taken before the cleanup still hold the
originals until they age out (7 daily + 4 weekly, about four weeks); the
owner decides whether to purge them sooner.

*Acceptance:* a GPS-tagged photo sent through the admin upload and
through `/api/ingest-media` is stored with no location, camera or time
(PHP's own EXIF reader finds none), decodes to identical pixels, and
still displays upright. A truncated photo is refused on both paths. The
cleanup's dry run changes nothing, and a second `--apply` finds nothing.
All of this is in `tests/imagemeta.test.php` and
`tests/imagemeta-http.test.php`. The production cleanup's report is
filed.

**9.2 — Convert the 14 legacy nginx blocks, and add the catch-all.**
This is 1.2's remainder and 1.3, which was never built.

- Every block takes `tools/vps/vhost.template`'s shape and routes
  through `router.php`. Every API route added since September has needed
  a hand-written pass over those 14 blocks (the 7cb82fb roll carries
  another one).
- An explicit `default_server` on 80 and 443, both address families,
  answers unknown hostnames with `444` behind a self-signed certificate.
  Without one, nginx falls back to the first block bound to each socket.
  That is how Turtle Island served Brampton's certificate.
- The same pass audits every block for both-family `listen` pairs.

Two cautions. The box hosts foreign tenants (the Institute and Calgary
Dispatch), whose blocks must be left alone, and any tenant's broken
block fails the global `nginx -t`. The runbooks currently say "do not
add `default_server`"; the brief for this item replaces that rule
rather than breaking it quietly.

*Acceptance:* a route added to `router.php` alone serves on every paper.
`curl` against an unknown hostname gets 444 on both families, never a
sister paper's certificate. Every paper keeps its masthead and
stylesheet through the conversion; the upgrader's guard runs it.

**9.3 — Prune old release directories.** Every roll leaves full copies
under `/var/www/prairiepost-<sha12>-<label>`, and nothing removes them.
Eventually the disk fills and all 27 sites fail together. The pruner:

- keeps each group's live release and a small number of predecessors;
- never touches a directory any enabled nginx block or cron file
  references;
- never touches roots outside `/var/www/prairiepost-*`.

Since migrations are forward-only past steps 22 and 23
(`docs/MIGRATION-COMPAT.md`), a release older than the stored schema
cannot serve as a fallback anyway.

*Acceptance:* after a roll, at most N release directories remain per
group, the live ones intact, and a dry run lists exactly what it would
remove.

**9.4 — Move the SMTP password out of the database.** Today
`smtp_pass` is a `settings` row written from the Newsletter page and
read by `app/mailer.php`. The AI and Google keys already live in the
server-only config. The password moves there too, the admin field
becomes "set on the server", and a migration clears the stored rows.

*Acceptance:* no `smtp_pass` value exists in the database or in its
backups; mail still sends.

**9.5 — An editor's publish clears the image hold.** The story editor
publishes without clearing `awaiting_image` (the bulk status change
already clears it). A story an editor published by hand and later moved
back to draft therefore re-enters the image agent's queue, and
`/api/ingest-publish` can publish it again over the editor's decision.

*Acceptance:* a story published from the editor never appears in
`GET /api/ingest-queue` afterwards, whatever its later status.

## Waiting on the owner (no code needed)

1. **Agent key grants for the editing API.** It is live, but no key
   carries the read, edit-drafts or update-published grants until you
   name which keys get them. Granting one is a revoke and re-mint.
2. **Surrey's test residue.** Delete draft #315 and story #316 in
   Surrey's admin (never by SQL); #316 is publicly visible. Two test PNGs
   sit under `/uploads/2026/09/`.
3. **An off-site destination for backups.** They run nightly and are
   verified, but they sit on the same server as everything else. The
   hook is `/etc/civis-backup-offsite`.
4. **The ops alert email,** in the hub's Settings → Security. Until it
   is set, the five-minute watch can only show problems on the
   dashboard, not email them.
5. **The GitHub default branch** (0.3): repoint it to
   `claude/master-dashboard-control-room-nr3mp4`.

## Policy decisions before more code

- **AI-assistance disclosure.** The `ai_disclosure` setting is blank
  (off) by default and no launch pack sets it. Decide whether readers
  see a note on stories drafted with the desk's help.
- **A privacy page and reader consent,** before analytics or search
  indexing are switched on for any paper.
- **A tamper-evident audit log.** Today's audit rows are ordinary
  database rows, which anyone with write access to the database can
  change. Decide whether they should be hash-chained or shipped off the
  box.

---

## The hardening plan as written (papers 14 and 15 shipped against it)

Each phase ends with an acceptance test that either passes or the phase
is not done. The ordering rule: safety nets before surgery, launch
machinery before launches, template surgery under baseline protection,
and the ingest pipeline before any Hermes agent holds a token.

---

## Phase 0 — Safety nets (one session)

> **Status, 24 Nov:** 0.1, 0.2 and 0.4 done; 0.3 is the owner's switch
> (above). CI runs on every PR to the release branch:
>
> - lint;
> - the generated schema check;
> - brand palettes validate;
> - the suite on SQLite and on PostgreSQL;
> - the release-flow fixture;
> - `seed-all.sh` with zero skipped lines;
> - the render baseline against the base branch (`tools/render-gate.sh`).
>
> 0.4 shipped as the per-site `indexing_enabled` setting, default off.
> Until an admin enables it, every page carries a noindex meta, and
> feeds, sitemaps and social cards carry an `X-Robots-Tag` header. None
> had been enabled at the last check. `robots.txt` deliberately stays
> `Allow`, because a crawler that is disallowed never sees the noindex.

The render-diff harness has caught three shipped-invisible defects
(stray newline on twelve papers, wrong-layer EN DIRECT check, Pickering
phantom font). It lives in `/tmp` and runs when someone remembers. That
ends here.

**0.1 — Commit the harness.** `tools/baseline.sh` renders every public
page type on every paper against a database seeded from all launch
packs, twice for the base tree, and reports only real differences
(clock ticks and tag-tie ordering are noise, not signal). A companion
`tools/seed-all.sh` builds the N-paper test database from scratch in
launch order.

**0.2 — CI on every PR to the release branch.** GitHub Actions:
`php -l` over every PHP file; every `assets/sites/*/palette.json`
parses; `tools/seed-all.sh` completes with **zero `skipped` lines**
(this catches network-wide slug collisions and desk dependencies at PR
time, where they are a red X, instead of at deploy time, where they are
a hard stop on a live server); `tools/baseline.sh` shows zero real
differences against the base branch unless the PR declares it changes
rendered output.

**0.3 — Repoint the GitHub default branch** to
`claude/master-dashboard-control-room-nr3mp4`. The trunk that is
visible must be the trunk that ships. Then delete the stale merged
feature branches.

**0.4 — Stop indexing invented content.** Thirteen mastheads of
plausible fake news about real cities, indexable, is the largest
standing risk on this list. Add a `noindex` robots meta emitted by the
shared chrome whenever the new setting `indexing_enabled` is not `'1'`,
and flip `robots.txt` to match. The flag is per-site, so each paper
starts indexing the day its newsroom replaces the demonstration pack —
one setting, no deploy. **Owner decision recorded here:** default is
noindex-until-real-editorial.

*Acceptance:* a PR that introduces a deliberate slug collision fails
CI; a PR that changes nothing renders byte-identical in CI; every live
paper serves the noindex meta until its flag is flipped.

## Phase 1 — Launch machinery (the step that has caused every stop)

> **Status, 24 Nov:** 1.1, 1.4 and 1.5 done.
>
> - **1.1:** the `domains` table resolves the tenant ahead of the
>   config, and every launch since August has been config-edit-free.
>   Whether the live config's own host arms have been retired has not
>   been confirmed on the server.
> - **1.4:** `upgrade-papers.sh` records each domain's status, title
>   *and* front-page stylesheet set before a roll, and rolls the group
>   back if any of them changes. That is the template-loss check.
> - **1.2:** the template is committed and every block created since is
>   generated from it (13 of 27). The 14 hand-written blocks were never
>   converted.
> - **1.3:** the catch-all was never built.
>
> 1.2's remainder and 1.3 are Phase 9.2.
>
> *(Aug 24 status, for the record: 1.1 resolved all 22 hostnames from
> the database; 1.2's template was committed; 1.3 was recorded as done.
> That 1.3 entry was wrong. No catch-all exists in the template, the
> deploy scripts or any runbook, and the runbooks forbid adding
> `default_server`.)*

Three of the last four deployments hard-stopped at least once, and
every stop but one traces to the same two designs: a hand-edited
guarded config file, and routes that live in three places.

**1.1 — Tenant mapping moves into the database.** New `domains` table:
`hostname (unique) → site slug`, plus a per-site `canonical_url`
setting. Bootstrap resolves the tenant from the request host via the
table, falling back to the existing `app/config.site.php` match arms so
the cutover is riskless; launch packs gain a `domains` entry and the
seeder writes it. After all thirteen papers' rows exist and serve, the
match arms are retired and the config file holds credentials only —
which makes the action-guarded step of every future launch *disappear*.
Launching a paper becomes: DNS, nginx, cert, seed.

**1.2 — Nginx becomes a front controller.** Every paper block reduces
to the same shape: TLS lines, root at the release, deny locations, and
`try_files $uri /router.php$is_args$args`. Routes then live in
`router.php` alone — the "three homes" defect class (`/region/` 404ing
only in production) cannot recur. A canonical block template is
committed at `tools/vps/vhost.template` and new blocks are generated
from it, not copied from a sibling by hand.

**1.3 — Explicit `default_server` catch-all** on 80 and 443, both
address families, returning `444` with a self-signed certificate. An
unknown hostname or a missing `listen` line then fails loudly and
obviously instead of serving Brampton's certificate to half the
internet. This retires the Turtle Island failure class permanently.
All thirteen existing blocks are audited for both-family listen pairs
in the same pass.

**1.4 — `upgrade-papers.sh` learns to see template loss.** Its
rollback guard gains one assertion per bespoke paper: the served front
page contains that paper's stylesheet name. The `<title>` check alone
passes on a paper whose entire template tree is missing, because titles
come from the database.

**1.5 — Slug scoping decision.** `posts.slug` stays globally unique
for now (changing the constraint under 13 live papers is riskier than
the disease), but the seeder stops failing silently: a skipped story
becomes a non-zero exit and a loud line, and Phase 3's ingest API
allocates slugs server-side with automatic suffixing. Revisit a
per-site constraint only if the newsroom-facing admin ever hits it.

*Acceptance:* a test hostname added only via seeder resolves to its
paper with zero config-file edits; `curl` against an unknown hostname
gets 444, not a sister paper's certificate; a release built with a
bespoke paper's CSS deleted rolls back automatically.

## Phase 2 — Template registry (the deferred refactor, done safely)

> **Status, Aug 24 (final):** 2.1, 2.2 and 2.4 done — all twelve papers'
> chrome in partials, ui.php name-free, the six phantom-font stacks
> renamed on faulty sandbox evidence — CORRECTED Aug 25: all families
> now vendored and declared in fonts.css, six stacks restored to Source
> Serif 4, zero font-CDN loads network-wide — and the operational
> close-out with them: `tools/make-paper.php` scaffolds a complete
> files-only paper (palette, pack skeleton with domains, css, three
> views, two chrome partials; refuses to overwrite), so builds 14 and 15
> start in the compliance-gate shape by construction.
> **2.3 (accessibility levelling) is delayed by owner decision, Aug 24**
> — moved to the standing backlog as a declared-diff pass with
> screenshot review. Phase 2 is otherwise closed.

`ui.php` carries 30+ per-paper branches across three dispatch chains;
the Torch and the Standard conflicted in all four shared files; a blank
line in the footer chain nearly shipped a stray newline to twelve live
papers. The two failed extraction attempts taught the method: **never
again as one big-bang parse — one paper per PR, CI baseline green after
each.**

**2.1 — Convention dispatch.** `index.php`, `article.php`,
`section.php` require `app/views/front-{template}.php` (etc.) when the
file exists — the if-chains are deleted once every paper's files
follow the convention (they already do; only the chains remain).

**2.2 — Chrome partials.** Each paper's masthead and footer move,
verbatim, from `ui.php` into `app/views/chrome/{template}-head.php` /
`-foot.php`, one paper per PR, with the shared default as the
fallthrough. After the last one, `ui.php` contains no paper names.
End state: **a new paper adds files and touches nothing shared** —
palette, CSS, three templates, two chrome partials, a launch pack.

**2.3 — Accessibility levelling, in the same pass.** As each paper's
chrome moves: skip link, visible focus states, `aria-current` on
active nav, one contrast check of its palette tokens. The older papers
predate these; moving their chrome is the moment to level them.

**2.4 — The fonts decision, decided.** Six papers name Source Serif 4
without declaring it and have always rendered Georgia — which their
pages were approved with. **Recommendation: declare nothing; rename
the six stacks to lead with Georgia honestly.** Changing six live
papers' typography in one release to match a name nobody has ever seen
rendered is the wrong direction. Either way the stack must stop lying;
owner may override toward declaring the face instead.

*Acceptance:* zero real baseline differences after every single PR in
the phase; `grep -c "template') ===" app/views/ui.php` returns 0 at
the end; a scaffold paper (`tools/make-brand.py` output) reaches a
rendering front page with no shared-file edits.

## Phase 3 — The Hermes ingest pipeline (before any agent files a story)

> **Status, Aug 24:** built and verified locally — endpoint, scoped
> revocable tokens (tools/make-agent.php), server-side slugs, dedup by
> external_id, draft gate with the wire-desk exception, provenance box
> rendered on all six article templates, French labels, rate limit.
> The contract for agent authors is docs/HERMES-INGEST.md. Finalized
> repo-side: the admin posts list badges agent-filed stories, and the
> Bleuet Blanc pack ships Hermes-ready (wire_desks 'le-fil', the
> automated byline in French). **Deployed Aug 24:** release
> 3c2335fdbf53 on schema 16, the endpoint verified fail-closed
> network-wide from on-box and off-box, a live draft filing passing
> creation/invisibility/idempotency, and token hermes-quebec issued to
> /root/hermes-tokens/ (never printed). Phase 3 is closed; the ingest
> path opens for real the day the first Hermes agent receives the token.
>
> **Since then (24 Nov):** ingest v2 (27 Sep) put key minting on the
> hub's API-keys page and added `/api/ingest-media`. The publish-on-image
> lane followed on 28 Sep. Story lookup (`/api/ingest/lookup`) went live
> 21 Nov. The read lane (`GET /api/ingest/stories`) and the two edit lanes
> (`PATCH /api/ingest/drafts/{ref}` and `POST /api/ingest/update`) went
> live 22–23 Nov, each behind its own grant. The current contract is
> `docs/api-ingest.md`; `docs/HERMES-INGEST.md` is the original.

Agents tracking databases and public sources will feed the papers. The
write path they use decides whether the network stays trustworthy. No
Hermes agent receives a token until this phase is done.

**3.1 — Ingest API.** `POST /api/ingest`: per-agent bearer tokens
(hashed at rest), scoped to specific sites and desks, revocable
individually — revocation is the kill switch. Payload: title, desk,
lede, body, tags, dateline, `sources[]` (URL + retrieved-at), optional
image reference. Rate-limited per token.

**3.2 — Server-side slug allocation and dedup.** The API generates the
slug and auto-suffixes on collision — the silent-skip failure mode
must not exist on this path. Dedup at ingest by source URL and content
hash: three agents watching the same court filing file it once.

**3.3 — Everything lands as a draft.** Agent-filed stories enter
`status = pending` in a review queue in the admin — list, diff against
any prior version, approve / reject. A human click is the publish gate
for anything wearing a byline. The single exception: wire/`le fil`
style desks may auto-publish, clearly labelled as live feeds.

**3.4 — Provenance is first-class and visible.** Which agent, which
sources, retrieved when — stored per story and rendered as a sources
box on the article. **Byline convention, decided now:** automated
stories carry an explicit automated-report treatment, not a
human-sounding name and not the house "La rédaction". This is both the
ethical disclosure and the debugging trail.

*Acceptance:* an agent token scoped to paper A cannot write to paper
B; a duplicate filing dedupes; a revoked token fails closed; a
published agent story shows its sources box and automated byline.

## The compliance gate for every new paper

Written for papers 14 and 15; every paper since has been built against
it, and the next one is too. A new paper build is accepted only when
all of the following hold:

1. **Files only.** The build adds `assets/sites/<slug>/` (palette,
   launch pack, art), `assets/css/<template>.css`, three view
   templates, two chrome partials. `git diff` against shared files is
   empty.
2. **Every font named is a font declared** — its stylesheet carries the
   `@font-face` for every family in its stacks, and CDP
   (`CSS.getPlatformFontsForNode`) confirms the face actually paints.
3. **The launch pack carries its own world:** every desk its stories
   use, explicit collision-proof slugs, its `domains` entry, and
   `indexing_enabled` unset (noindex) until real editorial.
4. **CI green** on the PR: lint, palettes, seed-all with zero skips,
   baseline with zero real differences on the existing papers.
5. **Runbook verify items name served bytes,** each traced to the step
   and the layer that produces it (CLAUDE.md lessons 1 and 10), and the
   nginx block comes from `vhost.template`.
6. **Launch is config-edit-free:** DNS, generated nginx block, cert,
   seed. If the deployment brief contains a config-file edit step, the
   phase-1 work isn't finished — stop and finish it first.
7. **The newsroom wears the paper** (since 23 Nov): the palette's
   `admin` block is chosen from the rendered masthead, and
   `tests/admin-brand.test.php` passes for it.

## Standing backlog (not gating, tracked)

- Newsletter infrastructure per paper: the owner's mail setup
  (mailboxes, SMTP identity, SPF, DKIM, a test send). No paper has its
  newsletter enabled.
- `NewsArticle` JSON-LD and per-story OG images once indexing turns on.
- Desk pagination and related stories before agent volume arrives.
- Analytics beyond the `views` column.
- Replacing the older demonstration packs with real editorial, paper
  by paper. The newer papers' inaugural editions are about the papers
  themselves and true by construction. The older packs still carry
  invented editorial about real cities, kept out of search only by
  noindex.
- Accessibility levelling (2.3), as a declared-diff pass with
  screenshot review.
- Slug renames with redirects. No redirect infrastructure exists, so
  the editing API never changes a URL.
