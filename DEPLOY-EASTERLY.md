# Taking easterly.ca live — deployment runbook (BRANDED, not launch-ready)

The Easterly (slug `easterly`, template `easterly`) covers
Atlantic Canada, region-wide. An east wind off the Atlantic: the region-wide paper, the way Western Wire covers the West.

It was built 28 Nov 2026 as a foundation on Toronto Telegraph's design,
and the owner's brand package was applied the same day: its colours, the
E. mark and the logotype. The launch pack still has zero stories. **Do
not launch it from this state.** Before any launch order:

1. **The domain is registered.** `easterly.ca` was unregistered on
   28 Nov 2026 (RDAP), and so was theeasterly.com and theeasterly.ca. The owner registers
   it and points it at the VPS. If the brand package names a different
   hostname, that is a question for the owner, not a correction to apply.
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
3. **The brand build writes the six-story inaugural edition** into
   `launch.php`: launch notes about the paper itself, true by
   construction, never invented local news (CLAUDE.md, debt item 1).
4. **The owner confirms the footprint:** all four Atlantic provinces on Atlantic time (America/Halifax), though Newfoundland's clock runs half an hour ahead; and whether it links its Atlantic sister papers' stories the way Western Wire does the West's.

When those are done, this runbook is revised to launch form, and the
launch is the standard config-edit-free flow:
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
| Stories | This pack | None until the brand build writes the inaugural edition |
| `noindex` on every page | `indexing_enabled`, default off | Present before and after the seed. Opening the paper is a newsroom decision, made with the Search engines button in Settings |
