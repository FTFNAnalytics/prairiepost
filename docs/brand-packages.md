# Brand packages: what each new paper needs from the owner

**Status (28 Nov 2026).** Five papers from the expansion slate
(`NETWORK-EXPANSION.md`) are built as unbranded foundations on Toronto
Telegraph's design: the same layout, type and page furniture, in a
placeholder slate colour scheme with a monogram roundel for a mark. Each
has a launch pack with its domains, desks and wire feeds, and no stories.
Terminal City Times has been a foundation since 22 Sep, in its own
heritage-broadsheet design.

| Paper | Slug and template | Covers | Domain | State |
| --- | --- | --- | --- | --- |
| Citadel Courier | `citadel-courier`, `citadel` | Halifax and Nova Scotia | citadelcourier.ca | Foundation; domain unregistered |
| The Easterly | `easterly`, `easterly` | Atlantic Canada, region-wide | easterly.ca | Foundation; domain unregistered |
| Le Memphré | `memphre`, `memphre` | Magog and Lake Memphrémagog (French) | memphre.ca | Foundation; domain unregistered |
| Les Chutes | `chutes`, `chutes` | Quebec City (French) | chutesquebec.ca | Foundation; domain unregistered |
| Borealis Bulletin | `borealis-bulletin`, `borealis` | Yukon, NWT and Nunavut | borealisbulletin.ca | Foundation; domain unregistered |
| Terminal City Times | `terminal-city-times`, `terminalcity` | Vancouver | terminalcitytimes.ca | Foundation; domain registered 22 Sep |

"Unregistered" is from the registries (RDAP) on 28 Nov 2026. Nothing is
held until it is bought.

## What a brand package carries

1. **Colours, as hex values, by role.** The Telegraph design uses three
   brand colours; everything else can stay as it is.

   | Role | Where it shows | Telegraph's |
   | --- | --- | --- |
   | Primary | Masthead rule, links, section chips, the Subscribe button, the lead-story panel, the footer band | Leafs Navy `#00205B` |
   | Deep | The thin utility strip above the masthead | Argos Oxford `#0C2340` |
   | Secondary | The second line of the wordmark, hover colour, the kicker over the lead story, the Read button | Argos Cambridge `#5F8FB1` |

   Optional: a page colour other than white, a body-text colour other
   than charcoal `#1A1A1A`, and a line colour other than silver.

   Two pairs must stay readable, and the brand build checks them before
   anything ships. White text sits on the primary colour. The secondary
   colour sits on the primary one as small text. A dark primary with a
   mid-tone secondary, as Telegraph has, satisfies both.
2. **The mark.** An SVG if you have one, or a large PNG we can trace.
   Send it in two versions: for light backgrounds, and for the dark
   footer. A square app icon too, if it differs. From these we draw the
   favicon set and the 1200×630 share card. Send your own share card if
   you have one.
3. **The wordmark.** Either Telegraph's setting, the name in two lines
   of Archivo Narrow capitals (CITADEL over COURIER), or a logotype you
   supply.
4. **Typography (optional).** Telegraph's Archivo, Archivo Narrow and
   Source Serif 4 unless you name other faces. Any new face must be
   openly licensed so we can host it ourselves: the network loads no
   fonts from a CDN.
5. **Words.** In French for Les Chutes and Le Memphré:
   - the masthead name exactly as it should print, with or without
     "The" and with its accents;
   - the tagline;
   - the newsletter's name;
   - the footer line.
6. **Sections.** Keep the placeholder desks below, or name your own.
7. **Decisions only you can make,** per paper, in the next section.

Optional extras: social handles, an analytics container (wait for the
privacy-page decision in PLAN.md), and the About page text. Without
About text, the brand build drafts one from the launch notes for you to
approve.

## Per paper: placeholders and open decisions

**Citadel Courier.**
- Placeholders:
  - tagline "Halifax, from the hill to the harbour";
  - newsletter "The Noon Gun", for the Citadel's daily noon gun;
  - desks Halifax, City Hall, Province, Business, Sports, Opinion.
- Weather and clock: Halifax.
- Decide: the footprint. Is it Halifax Regional Municipality, or all of
  Nova Scotia? The working assumption is Halifax, with The Easterly
  carrying the region. Also decide whether to hold citadelcourier.com.

**The Easterly.**
- Placeholders:
  - tagline "Atlantic Canada, on the east wind";
  - newsletter "The Morning Tide";
  - desks News, Politics, Business, Environment, Culture, Sports,
    Opinion.
- Weather: none, as with Western Wire, since it has no home city.
- Clock: Atlantic time.
- Decide:
  - whether Atlantic time suits, given Newfoundland runs half an hour
    ahead;
  - whether it does its own reporting, or links the Atlantic papers'
    stories the way Western Wire links the West's;
  - whether to hold theeasterly.com.

**Le Memphré.**
- Placeholders:
  - tagline "Magog et le lac Memphrémagog";
  - newsletter "L’infolettre du Memphré";
  - desks Actualités, Économie, Environnement, Culture, Sports, Idées.
- Weather and clock: Magog, which has its own Environment Canada page.
- Decide the footprint:
  - Magog and the towns around the lake, or all of Estrie?
  - Is L'Estrien, named but not built, to be the Estrie paper above it?

**Les Chutes.**
- Placeholders:
  - tagline "La capitale, de la falaise au fleuve";
  - newsletter "L’infolettre des Chutes";
  - desks Actualités, Politique, Économie, Culture, Sports, Idées.
- Weather and clock: Québec.
- Decide:
  - the city, or the whole Capitale-Nationale;
  - whether Lévis counts as local;
  - whether to also hold leschutes.com, the exact masthead, which was
    free on 28 Nov.

**Borealis Bulletin.**
- Placeholders:
  - tagline "News from north of sixty";
  - newsletter "First Light";
  - desks News, Politics, Resources, Environment, Culture, Sports,
    Opinion.
- Decide the home city, which sets the weather and the clock:
  - **Whitehorse** is the placeholder.
  - **Iqaluit** works as is.
  - **Yellowknife** needs a one-line fix first. PHP lists its zone only
    as an alias, so `app/weather.php` must point the Northwest
    Territories at America/Edmonton.

  The North's audience is about half Indigenous. The slate chose sky and
  land over Inuit or Dene symbols, and the mark should hold to that.

**Terminal City Times.** The domain is registered. It needs its brand
package, and one decision: keep its own heritage-broadsheet foundation,
or move it to the Telegraph layout like the others.

## From package to launch

Each package becomes one brand build, as a pull request:
1. The colours go into the role block at the top of the paper's
   stylesheet and into its `palette.json`.
2. The mark goes into its three SVGs, and the icons are redrawn.
3. The wordmark goes into its two chrome partials.
4. The words go into its launch pack.
5. The build writes the paper's six-story inaugural edition: launch
   notes about the paper itself, true by construction.

The build also revises the paper's `DEPLOY-<NAME>.md` to launch form.
Then the VPS agent gets a launch brief: DNS, the generated nginx block,
the certificate, and the seed. Each paper opens to search engines only
when its newsroom presses Open in Settings.
