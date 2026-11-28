# Taking citadelcourier.ca live — deployment runbook (BRANDED, not launch-ready)

Citadel Courier (slug `citadel-courier`, template `citadel`) covers
Halifax and Nova Scotia. Named for the Halifax Citadel, the star-shaped fort on the hill above the harbour.

It was built 28 Nov 2026 as a foundation on Toronto Telegraph's design,
and the owner's brand package was applied the same day: its colours, its
type (Newsreader and Inter), its fort mark and its app icon. The launch
pack still has zero stories. **Do not launch it from this state.**
Before any launch order:

1. **The domain is registered.** `citadelcourier.ca` was unregistered on
   28 Nov 2026 (RDAP), and so was citadelcourier.com. The owner registers
   it and points it at the VPS. If the brand package names a different
   hostname, that is a question for the owner, not a correction to apply.
2. **Done 28 Nov: the owner's brand package is applied.** What it
   changed, and the choices the package left open, are in
   `docs/brand-packages.md` (Citadel Courier):
   - the README's six colours in the role block of
     `assets/css/citadel.css` and in `palette.json` (`palette`, `desks`,
     `admin`), and its type as two font roles: Newsreader for titles and
     the wordmark, Inter for body and interface;
   - the primary logo's fort traced into `mark.svg` and
     `mark-reversed.svg`, the app icon's lighthouse into `favicon.svg`;
     the icons redrawn with `php tools/render-icons.php citadel-courier`;
     a new `og-default.png` in the network's card layout;
   - the lockup in `app/views/chrome/citadel-head.php` and `-foot.php`
     (the wordmark is live text), and a breaking strip in the guide's
     Courier Red;
   - the tagline "Halifax • Nova Scotia" and the footer line "News that
     matters, from where you live.", both from the package's homepage
     mock-up, in this paper's `launch.php`. The newsletter name is
     still a placeholder.
3. **The brand build writes the six-story inaugural edition** into
   `launch.php`: launch notes about the paper itself, true by
   construction, never invented local news (CLAUDE.md, debt item 1).
4. **The owner confirms the footprint.** The package's README says
   "Halifax and Nova Scotia", and the build follows it. How the Courier
   and The Easterly, which covers all four Atlantic provinces, divide
   Nova Scotia is still open.

When those are done, this runbook is revised to launch form, and the
launch is the standard config-edit-free flow:
- DNS, checked from the box;
- `make-vhost.sh` with the bare php-fpm socket path, both address
  families, no `default_server`;
- certbot for both hostnames, with `listen 443 ssl` surviving on both
  families;
- `PP_SITE=citadel-courier php tools/seed-launch.php`;
- verify the served bytes, pinning every request to the box with
  `curl --resolve`.

## Pre-seed expectation table (foundation state)

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Body class `t-citadel`, `/assets/css/citadel.css`, the fort (`mark.svg`) | Release tree | Present before the seed |
| The wordmark "Citadel Courier" as text (`<span class="wm">`), set in Newsreader | Release tree (the chrome partials) | Present, mixed case as served and as painted |
| Title "Citadel Courier", tagline "Halifax • Nova Scotia" | This pack's `settings` | Absent until the seed: the tagline reads the network default before it. The served bytes are mixed case; CSS paints the masthead's copy in capitals (lesson 10) |
| The breaking strip (`.cc-brk`), labelled "Breaking" in the served HTML and painted in capitals | Settings `breaking_label`, `breaking_url` and `breaking_until`, read only through `pp_breaking_live()` | Absent before and after the seed: no banner is set at launch |
| Nav: Halifax, City Hall, Province, Business, Sports, Opinion | Shared `categories`, `chrome.nav` and `desk_labels` | Every desk already exists network-wide. Read the real list with `categories_all()` from `$REL`, and assert no absences |
| Weather (Halifax, ECCC `ns-19`) | `palette.json` + the hub's weather job | Shows once the job has a reading (within half an hour), never before |
| Stories | This pack | None until the brand build writes the inaugural edition |
| `noindex` on every page | `indexing_enabled`, default off | Present before and after the seed. Opening the paper is a newsroom decision, made with the Search engines button in Settings |
