# Taking borealisbulletin.ca live — deployment runbook (BRANDED; launch waits on the domain)

Borealis Bulletin (slug `borealis-bulletin`, template `borealis`) covers
the North: Yukon, the Northwest Territories and Nunavut, with Inuit
Nunangat, the provincial Norths and the circumpolar Arctic beyond them.
Its home is Yellowknife. The name is the northern lights, visible across
all three territories; "boreal" also names the forest.

It was built 28 Nov 2026 as a foundation on Toronto Telegraph's design,
and the owner's brand package was applied the same day. The package
shipped its own stylesheet, templates, chrome, palette and launch pack,
written against this repository, and the build kept them with the
changes `docs/brand-packages.md` lists. Its six-story inaugural edition
is written. **Do not launch it until step 1 holds.** Before any launch
order:

1. **The domain is registered (the owner's step).** `borealisbulletin.ca`
   was unregistered on 28 Nov 2026 (RDAP), and so was
   borealisbulletin.com. The owner registers it and points it at the VPS
   with A records only: the network publishes no AAAA record until
   PLAN.md 9.2's audit of both address families (the package's own
   runbook asks for A and AAAA). A launch brief checks DNS from the box
   first, and stops if `borealisbulletin.ca` and `www.borealisbulletin.ca`
   don't both resolve to it.
   - The package names borealisbulletin.com beside the .ca. It joins the
     pack's `domains` only once it is registered and pointed at the VPS,
     because the DNS gate checks every hostname listed there.
   - The package's runbook also asks for trademark clearance on the name
     before launch. That is the owner's call.
2. **Done 28 Nov: the owner's brand package is applied.** What it changed
   is in `docs/brand-packages.md` (Borealis Bulletin):
   - the package's colours, declared once in `assets/css/borealis.css`
     and in `palette.json` (`palette`, `desks`, `admin`, and `theme`,
     Polar Midnight); its type, Archivo and Archivo Narrow over Source
     Serif 4 with IBM Plex Mono, each declared in the sheet (lesson 9);
   - the Polar Compass & Aurora Roundel, delivered as vectors:
     `mark.svg`, `mark-reversed.svg` and `favicon.svg`. The icons are
     redrawn with `php tools/render-icons.php borealis-bulletin`, and
     `og-default.png` is the package's own share card;
   - the stacked wordmark as live text in
     `app/views/chrome/borealis-head.php` and `-foot.php`, the corridor
     bar naming the footprint, and the network's breaking strip in
     Lichen Copper;
   - the package's desks: Territories (`local-news`), Arctic & North
     (`arctic-north`, new network-wide), Land & Climate (`environment`),
     Economy (`business`), Communities and Opinion.
3. **Done 28 Nov: the inaugural edition is written,** six launch notes in
   `launch.php`, one per desk. The editorial board's opening piece is on
   Opinion, and each other desk has a method or service note. All are
   about the paper itself and true by construction, with no events,
   figures or human bylines (CLAUDE.md, debt item 1). They link only to
   `/tips` and `/corrections` on the paper itself. Each note is dated when
   the seeder runs, so the edition carries the launch date.
4. **Done 28 Nov: the home city is Yellowknife,** the package's choice:
   weather from ECCC `nt-24`, and the clock `America/Edmonton`.
   `app/weather.php` maps the Northwest Territories to that zone, because
   PHP lists America/Yellowknife only as its alias. The times printed are
   the same either way.

Once step 1 holds, the launch is the standard config-edit-free flow:
- DNS, checked from the box;
- `make-vhost.sh` with the bare php-fpm socket path, both address
  families, no `default_server`;
- certbot for both hostnames, with `listen 443 ssl` surviving on both
  families;
- `PP_SITE=borealis-bulletin php tools/seed-launch.php`;
- verify the served bytes, pinning every request to the box with
  `curl --resolve`.

## Pre-seed expectation table

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Body class `t-borealis`, `/assets/css/borealis.css`, the roundel (`mark.svg`) | Release tree | Present before the seed |
| The wordmark: the text nodes "Borealis" and "Bulletin" in `.bo-name`, beside the roundel | Release tree (the chrome partials) | Present. The served bytes are mixed case; CSS paints them in capitals (lesson 10) |
| The utility strip's "60°N+", and the corridor bar: "Yukon · Northwest Territories · Nunavut · Inuit Nunangat · Circumpolar North" and "867 & Above the 60th Parallel" | Release tree (the head partial) | Present before the seed. The corridor bar is served mixed case and painted in capitals, and hidden below 621 px |
| News tips in the utility strip and the footer, linking `/tips` | Release tree (the chrome partials, unguarded) | Present before the seed |
| Title "Borealis Bulletin", tagline "Above the 60th. On the record." | This pack's `settings` | Absent until the seed: the tagline reads the network default before it |
| The breaking strip (`.bo-brk`) | Settings `breaking_label`, `breaking_url` and `breaking_until`, read only through `pp_breaking_live()` | Absent before and after the seed: no banner is set at launch |
| Nav, in the order `chrome.nav` sets: Territories, Arctic & North, Land & Climate, Economy, Communities, Opinion | Shared `categories`, `chrome.nav` and `desk_labels` | The nav shows each of the six that `categories_all()` returns. Five exist network-wide; `arctic-north` is created by this seed. Read the real list from `$REL`, and assert no absences |
| Weather (Yellowknife, ECCC `nt-24`), with its "62°27′N" tag | `palette.json` + the hub's weather job | Shows once the job has a reading (within half an hour), never before |
| Stories: six launch notes, the editorial "Above the 60th. On the record." leading the front in the no-art lead panel | This pack's `stories` | Absent until the seed. After it, every desk page has one story and the front has no empty state |
| `noindex` on every page | `indexing_enabled`, default off | Present before and after the seed. Opening the paper is a newsroom decision, made with the Search engines button in Settings |
