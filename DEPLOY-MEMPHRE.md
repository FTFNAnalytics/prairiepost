# Taking memphre.ca live — deployment runbook (BRANDED, not launch-ready)

Le Memphré (slug `memphre`, template `memphre`) covers
Sherbrooke and the Eastern Townships (Estrie), Magog and Lake Memphrémagog among them, as the owner's brand guidelines state it. Named for Memphré, the lake's legendary serpent.

It was built 28 Nov 2026 as a foundation on Toronto Telegraph's design,
and the owner's brand guidelines were applied the same day: their four
colours, their type (Playfair Display and Source Sans 3) and their lake
serpent. The launch pack still has zero stories. **Do not launch it
from this state.** Before any launch order:

1. **The domain is registered.** `memphre.ca` was unregistered on
   28 Nov 2026 (RDAP), and so were lememphre.com and lememphre.ca. The owner registers
   it and points it at the VPS. If the brand package names a different
   hostname, that is a question for the owner, not a correction to apply.
   The guidelines and draft site give their addresses at lememphre.ca:
   that question is open (`docs/brand-packages.md`).
2. **Done 28 Nov: the owner's brand guidelines are applied.** What they
   changed, and the questions they left open, are in
   `docs/brand-packages.md` (Le Memphré):
   - the four colours in the role block of `assets/css/memphre.css` and
     in `palette.json` (`palette`, `desks`, `admin`, and `theme`, Dark
     Blue), with Teal deepened to AA wherever it sets text; the type as
     two font roles, Playfair Display (declared in the sheet itself) and
     Source Sans 3;
   - the serpent traced from the logo into `mark.svg` (the guidelines'
     blue-to-teal gradient), `mark-reversed.svg` and `favicon.svg` (on
     Dark Blue), and the logo's three-line wave into `waves.svg`; the
     icons redrawn with `php tools/render-icons.php memphre`; a new
     `og-default.png` in the network's card layout;
   - the lockup in `app/views/chrome/memphre-head.php` and `-foot.php`
     (the wordmark is live text), and a breaking strip in Teal;
   - the package's lines in French in this paper's `launch.php`, its
     footprint, a Sherbrooke wire region (the Sherbrooke Record, 10 items
     on 28 Nov), and the weather moved to Sherbrooke (ECCC `qc-136`).
     The newsletter name is still a placeholder.
3. **The brand build writes the six-story inaugural edition** into
   `launch.php`: launch notes about the paper itself, true by
   construction, never invented local news (CLAUDE.md, debt item 1).
4. **The owner confirms the language.** The guidelines and the draft
   site are written in English; the foundation, and this build's chrome
   and words, are French. If the paper publishes in English, its chrome
   moves to `chrome.lang: en` and its words to the package's own.
5. **The owner confirms the footprint.** The guidelines say Sherbrooke
   and the Eastern Townships, which the build follows. L'Estrien, named
   but not built, was to be Estrie's regional paper: whether it is still
   wanted is open.

When those are done, this runbook is revised to launch form, and the
launch is the standard config-edit-free flow:
- DNS, checked from the box;
- `make-vhost.sh` with the bare php-fpm socket path, both address
  families, no `default_server`;
- certbot for both hostnames, with `listen 443 ssl` surviving on both
  families;
- `PP_SITE=memphre php tools/seed-launch.php`;
- verify the served bytes, pinning every request to the box with
  `curl --resolve`.

## Pre-seed expectation table (foundation state)

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Body class `t-memphre`, `/assets/css/memphre.css`, the serpent (`mark.svg`) and the wave (`waves.svg`) | Release tree | Present before the seed |
| The wordmark "Le Memphré" as text (`<span class="wm">`), set in Playfair Display | Release tree (the chrome partials) | Present, mixed case as served and as painted |
| French chrome: "Accueil", "Chercher", "S’abonner", dates such as "samedi 28 novembre 2026" | `chrome.lang: fr` + `app/lang/fr.php` | Present before the seed |
| Title "Le Memphré", tagline "L’actualité de Sherbrooke et des Cantons-de-l’Est" | This pack's `settings` | Absent until the seed: the tagline reads the network default before it |
| The breaking strip (`.me-brk`), labelled "Dernière heure" in the served HTML and painted in capitals | Settings `breaking_label`, `breaking_url` and `breaking_until`, read only through `pp_breaking_live()` | Absent before and after the seed: no banner is set at launch |
| Nav: Actualités, Économie, Environnement, Culture, Sports, Idées | Shared `categories`, `chrome.nav` and `desk_labels` | Every desk already exists network-wide. Read the real list with `categories_all()` from `$REL`, and assert no absences |
| Weather (Sherbrooke, ECCC `qc-136`) | `palette.json` + the hub's weather job | Shows once the job has a reading (within half an hour), never before |
| Stories | This pack | None until the brand build writes the inaugural edition |
| `noindex` on every page | `indexing_enabled`, default off | Present before and after the seed. Opening the paper is a newsroom decision, made with the Search engines button in Settings |
