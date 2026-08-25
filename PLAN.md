# Hardening plan — before papers 14 and 15

Thirteen papers are live. Before the final two launch, the platform gets
four phases of work, in order. Each phase ends with an acceptance test
that either passes or the phase is not done; papers 14 and 15 are built
only after the **compliance gate** at the end of this document, and are
built *to* it.

The ordering rule: safety nets before surgery, launch machinery before
launches, template surgery under baseline protection, and the ingest
pipeline before any Hermes agent holds a token.

---

## Phase 0 — Safety nets (one session)

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

> **Status, Aug 24:** live on the server. 1.1 resolves all 22 hostnames
> from the database (config arms still present, retirement pending);
> 1.2's template is committed, conversion of existing blocks pending;
> 1.3 and 1.4 done; 1.5 done. Papers 14–15 can launch config-edit-free.

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

## The compliance gate for papers 14 and 15

A new paper build is accepted only when all of the following hold —
this is the definition of done the next build is written against:

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

## Standing backlog (not gating, tracked)

Newsletter infrastructure per paper (owner mail setup: mailboxes, SMTP
identity, SPF, DKIM, test send); `NewsArticle` JSON-LD and per-story
OG images once indexing turns on; desk pagination and related-stories
before agent volume arrives; analytics beyond the `views` column;
replacing demonstration packs with real editorial, paper by paper.
