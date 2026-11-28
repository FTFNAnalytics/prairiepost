# Taking chutesquebec.ca live — deployment runbook (FOUNDATION, not launch-ready)

Les Chutes (slug `chutes`, template `chutes`) covers
Quebec City. Named for Montmorency Falls, 83 m high. Plural on purpose: "La Chute" sounds like Lachute.

It is a FOUNDATION, built 28 Nov 2026 on Toronto Telegraph's design:
the same layout and type, with placeholder slate colours, a placeholder
monogram roundel and a launch pack with zero stories. **Do not launch it
from this state.** Before any launch order:

1. **The domain is registered.** `chutesquebec.ca` was unregistered on
   28 Nov 2026 (RDAP), and so was chutesquebec.com, as was leschutes.com, the exact masthead. The owner registers
   it and points it at the VPS. If the brand package names a different
   hostname, that is a question for the owner, not a correction to apply.
2. **The owner's brand package lands, and a brand build applies it.**
   `docs/brand-packages.md` lists what the package carries. The build:
   - sets the role colours at the top of `assets/css/chutes.css` and
     in `palette.json` (`palette`, `desks`, `admin`);
   - draws the mark into `mark.svg`, `mark-reversed.svg` and
     `favicon.svg`, deletes `og-default.png` unless the package brings
     its own card, and runs `php tools/render-icons.php chutes`;
   - sets the wordmark in `app/views/chrome/chutes-head.php` and
     `-foot.php`;
   - replaces the placeholder tagline and newsletter name in this
     paper's `launch.php`.
3. **The brand build writes the six-story inaugural edition** into
   `launch.php`: launch notes about the paper itself, true by
   construction, never invented local news (CLAUDE.md, debt item 1).
4. **The owner confirms the footprint:** the city of Québec, or the whole Capitale-Nationale region, and whether Lévis across the river counts as local.

When those are done, this runbook is revised to launch form, and the
launch is the standard config-edit-free flow:
- DNS, checked from the box;
- `make-vhost.sh` with the bare php-fpm socket path, both address
  families, no `default_server`;
- certbot for both hostnames, with `listen 443 ssl` surviving on both
  families;
- `PP_SITE=chutes php tools/seed-launch.php`;
- verify the served bytes, pinning every request to the box with
  `curl --resolve`.

## Pre-seed expectation table (foundation state)

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Body class `t-chutes`, `/assets/css/chutes.css`, the placeholder roundel | Release tree | Present before the seed |
| Wordmark lines "Les" over "Chutes" | Release tree (the chrome partials) | Present. The served bytes are mixed case; CSS paints them uppercase (lesson 10) |
| French chrome: "Accueil", "Chercher", "S’abonner", dates such as "samedi 28 novembre 2026" | `chrome.lang: fr` + `app/lang/fr.php` | Present before the seed |
| Title "Les Chutes", tagline "La capitale, de la falaise au fleuve" | This pack's `settings` | Absent until the seed: the tagline reads the network default before it |
| Nav: Actualités, Politique, Économie, Culture, Sports, Idées | Shared `categories`, `chrome.nav` and `desk_labels` | Every desk already exists network-wide. Read the real list with `categories_all()` from `$REL`, and assert no absences |
| Weather (Québec, ECCC `qc-133`) | `palette.json` + the hub's weather job | Shows once the job has a reading (within half an hour), never before |
| Stories | This pack | None until the brand build writes the inaugural edition |
| `noindex` on every page | `indexing_enabled`, default off | Present before and after the seed. Opening the paper is a newsroom decision, made with the Search engines button in Settings |
