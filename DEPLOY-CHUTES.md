# Taking chutesquebec.ca live — deployment runbook (BRANDED, not launch-ready)

Les Chutes (slug `chutes`, template `chutes`) covers
Quebec City and eastern Quebec, as the owner's brand package states it. Named for Montmorency Falls, 83 m high. Plural on purpose: "La Chute" sounds like Lachute.

It was built 28 Nov 2026 as a foundation on Toronto Telegraph's design,
and the owner's brand package was applied the same day: its colours, its
type (Newsreader and Inter), its waterfall mark and its app icon. The
launch pack still has zero stories. **Do not launch it from this
state.** Before any launch order:

1. **The domain is registered.** `chutesquebec.ca` was unregistered on
   28 Nov 2026 (RDAP), and so was chutesquebec.com, as was leschutes.com, the exact masthead. The owner registers
   it and points it at the VPS. If the brand package names a different
   hostname, that is a question for the owner, not a correction to apply.
   The package's brand guide prints leschutes.com in its footer: that
   question is open (`docs/brand-packages.md`).
2. **Done 28 Nov: the owner's brand package is applied.** What it
   changed, and the choices the package left open, are in
   `docs/brand-packages.md` (Les Chutes):
   - the README's five colours in the role block of
     `assets/css/chutes.css` and in `palette.json` (`palette`, `desks`,
     `admin`), and its type as two font roles: Newsreader for titles and
     the wordmark, Inter for body and interface;
   - the "logo principal" waterfall traced into `mark.svg` and
     `mark-reversed.svg`, the app icon into `favicon.svg`; the icons
     redrawn with `php tools/render-icons.php chutes`; a new
     `og-default.png` in the network's card layout;
   - the lockup in `app/views/chrome/chutes-head.php` and `-foot.php`
     (the wordmark is live text), and a breaking strip in the
     package's gold;
   - the tagline "L’actualité de Québec et de l’Est-du-Québec" and the
     README's footprint in this paper's `launch.php`, with a third wire
     region, Est-du-Québec. The newsletter name is still a
     placeholder.
3. **The brand build writes the six-story inaugural edition** into
   `launch.php`: launch notes about the paper itself, true by
   construction, never invented local news (CLAUDE.md, debt item 1).
4. **The owner confirms the footprint.** The package's README says
   "Québec et l'est du Québec", and the build follows it. Whether Lévis
   across the river counts as local is still open.

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
| Body class `t-chutes`, `/assets/css/chutes.css`, the waterfall (`mark.svg`) | Release tree | Present before the seed |
| The wordmark "Les Chutes" as text (`<span class="wm">`), set in Newsreader | Release tree (the chrome partials) | Present, mixed case as served and as painted |
| French chrome: "Accueil", "Chercher", "S’abonner", dates such as "samedi 28 novembre 2026" | `chrome.lang: fr` + `app/lang/fr.php` | Present before the seed |
| Title "Les Chutes", tagline "L’actualité de Québec et de l’Est-du-Québec" | This pack's `settings` | Absent until the seed: the tagline reads the network default before it |
| The breaking strip (`.ch-brk`), labelled "Dernière heure" in the served HTML and painted in capitals | Settings `breaking_label`, `breaking_url` and `breaking_until`, read only through `pp_breaking_live()` | Absent before and after the seed: no banner is set at launch |
| Nav: Actualités, Politique, Économie, Culture, Sports, Idées | Shared `categories`, `chrome.nav` and `desk_labels` | Every desk already exists network-wide. Read the real list with `categories_all()` from `$REL`, and assert no absences |
| Weather (Québec, ECCC `qc-133`) | `palette.json` + the hub's weather job | Shows once the job has a reading (within half an hour), never before |
| Stories | This pack | None until the brand build writes the inaugural edition |
| `noindex` on every page | `indexing_enabled`, default off | Present before and after the seed. Opening the paper is a newsroom decision, made with the Search engines button in Settings |
