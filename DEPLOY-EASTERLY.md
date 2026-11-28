# Taking easterly.ca live — deployment runbook (BRANDED; launch waits on the domain)

The Easterly (slug `easterly`, template `easterly`) covers
Atlantic Canada, region-wide, with its own reporting. An east wind off the Atlantic.

It was built 28 Nov 2026 as a foundation on Toronto Telegraph's design,
and the owner's brand package was applied the same day: its colours, the
E. mark and the logotype. Its seven-story inaugural edition is written.
**Do not launch it until step 1 holds.** Before any launch order:

1. **The domain is registered (the owner's step, under way).**
   `easterly.ca` was unregistered on 28 Nov 2026 (RDAP), and so were
   theeasterly.com and theeasterly.ca. The owner said on 28 Nov that they
   are registering it. They will point it at the VPS. A launch brief
   checks DNS from the box first and stops if `easterly.ca` and
   `www.easterly.ca` don't both resolve to it.
2. **Done 28 Nov: the owner's brand package is applied.** What it
   changed is in `docs/brand-packages.md` (The Easterly):
   - the package's colours in the role block of
     `assets/css/easterly.css` and in `palette.json` (`palette`,
     `desks`, `admin`);
   - the E. mark and the logotype, traced into `mark.svg`,
     `mark-reversed.svg`, `wordmark.svg`, `wordmark-reversed.svg`,
     `lockup.svg` and `favicon.svg`; the icons redrawn with
     `php tools/render-icons.php easterly`; `og-default.png` made from
     the package's lockup;
   - the lockup in `app/views/chrome/easterly-head.php` and `-foot.php`,
     and a breaking strip in the package's breaking-news red;
   - the tagline "News from Atlantic Canada", the lockup's line, in this
     paper's `launch.php`. The newsletter name and footer line are
     still placeholders.
3. **Done 28 Nov: the inaugural edition is written,** seven launch notes
   in `launch.php`, one per desk. The editorial board's opening piece is
   on Opinion, and each other desk has a method note. All are about the
   paper itself and true by construction, with no events, figures or
   human bylines (CLAUDE.md, debt item 1). Every link points at `/tips`
   on the paper itself. Each note is dated when the seeder runs, so the
   edition carries the launch date.
4. **Done 28 Nov: the owner's answers.**
   - Atlantic time (America/Halifax) for all four provinces. The "How The
     Easterly works" note tells readers that Newfoundland time is half an
     hour later.
   - The Easterly does its own reporting, rather than linking sister
     papers' stories the way Western Wire does.

Once step 1 holds, the launch is the standard config-edit-free flow:
- DNS, checked from the box;
- `make-vhost.sh` with the bare php-fpm socket path, both address
  families, no `default_server`;
- certbot for both hostnames, with `listen 443 ssl` surviving on both
  families;
- `PP_SITE=easterly php tools/seed-launch.php`;
- verify the served bytes, pinning every request to the box with
  `curl --resolve`.

## Pre-seed expectation table (foundation state)

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Body class `t-easterly`, `/assets/css/easterly.css`, the E. mark (`mark.svg`) and the logotype (`wordmark.svg`) | Release tree | Present before the seed |
| The logotype is an image: `<img class="wm">` with `alt=""` inside the masthead link, whose `aria-label` carries the site title | Release tree (the chrome partials) | Present. The words "The Easterly" are not text in the masthead, so match the `aria-label`, never a text node |
| Title "The Easterly", tagline "News from Atlantic Canada" | This pack's `settings` | Absent until the seed: the tagline reads the network default before it. The served bytes are mixed case; CSS paints the masthead's copy in capitals (lesson 10) |
| The breaking strip (`.ea-brk`) | Settings `breaking_label`, `breaking_url` and `breaking_until`, read only through `pp_breaking_live()` | Absent before and after the seed: no banner is set at launch |
| Nav: News, Politics, Business, Environment, Culture, Sports, Opinion | Shared `categories`, `chrome.nav` and `desk_labels` | Every desk already exists network-wide. Read the real list with `categories_all()` from `$REL`, and assert no absences |
| No weather box | `palette.json` names no city: a region-wide paper | Absent before and after, by design |
| Stories: seven launch notes, the editorial "Four provinces, one paper" leading the front in the no-art lead panel | This pack's `stories` | Absent until the seed. After it, every desk page has one story and the front has no empty state |
| `noindex` on every page | `indexing_enabled`, default off | Present before and after the seed. Opening the paper is a newsroom decision, made with the Search engines button in Settings |
