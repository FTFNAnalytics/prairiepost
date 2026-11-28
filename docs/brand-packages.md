# Brand packages: what each new paper needs from the owner

**Status (28 Nov 2026).** Five papers from the expansion slate
(`NETWORK-EXPANSION.md`) are built as unbranded foundations on Toronto
Telegraph's design: the same layout, type and page furniture, in a
placeholder slate colour scheme with a monogram roundel for a mark. Each
has a launch pack with its domains, desks and wire feeds, and no stories.
Terminal City Times has been a foundation since 22 Sep, in its own
heritage-broadsheet design. The first two packages, The Easterly's and
Les Chutes', arrived and were applied on 28 Nov (below).

| Paper | Slug and template | Covers | Domain | State |
| --- | --- | --- | --- | --- |
| Citadel Courier | `citadel-courier`, `citadel` | Halifax and Nova Scotia | citadelcourier.ca | Foundation; domain unregistered |
| The Easterly | `easterly`, `easterly` | Atlantic Canada, region-wide | easterly.ca | Branded 28 Nov; domain unregistered |
| Le Memphré | `memphre`, `memphre` | Magog and Lake Memphrémagog (French) | memphre.ca | Foundation; domain unregistered |
| Les Chutes | `chutes`, `chutes` | Quebec City and eastern Quebec (French) | chutesquebec.ca | Branded 28 Nov; domain unregistered |
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

**The Easterly.** The package arrived on 28 Nov and is applied. It
held six colours (`easterly-colours.css`), the E. mark, the logotype and
the lockup, as PNGs.
- Colours, as the package assigns them:

  | Package name | Hex | Where it shows |
  | --- | --- | --- |
  | Sea Fog | `#EEF0EE` | The page ground; type on dark grounds |
  | Salt Spray | `#E2E6E4` | Soft panels; text on dark grounds |
  | North Atlantic | `#17232B` | All type; the dark grounds (utility strip, lead panel, subscribe box, footer band) |
  | Harbour | `#1D6A85` | Links, buttons, desk chips, the masthead rule |
  | Harbour Deep | `#124A5E` | Small accent text and hovers |
  | Lighthouse | `#C0392B` | The breaking strip and the mark's full stop only |
  | Oilskin | `#E6B31E` | Print plates only: never on the site |

  The lockup's caption grey `#5A5F62` is the meta-text colour, and the
  colour sheet's swatch rule `#CCCFCF` the border colour.
- Mark and logotype: traced to SVG. The full stop is a fitted circle.
  The masthead is the package's lockup: the mark, then the logotype
  over the tagline in Source Serif 4, at the lockup's proportions.
- Tagline "News from Atlantic Canada", the lockup's line.
- Still placeholders: newsletter "The Morning Tide", footer line
  "Atlantic Canada, read as one region."
- Desks unchanged: News, Politics, Business, Environment, Culture,
  Sports, Opinion.
- Weather: none, as with Western Wire, since it has no home city.
- The owner's answers, 28 Nov:
  - Atlantic time for all four provinces;
  - its own reporting, not links to sister papers;
  - the owner is registering easterly.ca.
- The inaugural edition is written: seven launch notes, one per desk,
  dated when the seeder runs.
- Still open: whether to hold theeasterly.com, and the newsletter's name
  and footer line.

**Le Memphré.**
- Placeholders:
  - tagline "Magog et le lac Memphrémagog";
  - newsletter "L’infolettre du Memphré";
  - desks Actualités, Économie, Environnement, Culture, Sports, Idées.
- Weather and clock: Magog, which has its own Environment Canada page.
- Decide the footprint:
  - Magog and the towns around the lake, or all of Estrie?
  - Is L'Estrien, named but not built, to be the Estrie paper above it?

**Les Chutes.** The package arrived on 28 Nov and is applied. It held a
README, two logos, a palette sheet, homepage, article and phone mock-ups,
an app icon, a social banner and a brand guide, all as JPEG images.
The images disagree with each other, so the build follows the README,
the package's only text, and the files it names:
- **Colours:** the five the README, palette sheet and guide all give.

  | Package name | Hex | Where it shows |
  | --- | --- | --- |
  | Bleu Québec (principal) | `#325EA8` | Links, buttons, desk chips, the masthead rule, the waterfall |
  | Bleu profond (marine) | `#002B5C` | All type; the dark grounds (utility strip, lead panel, subscribe box, footer band) |
  | Blanc | `#FFFFFF` | The page; type on dark grounds |
  | Bleu clair (cyan d'eau, glace) | `#A8D8EA` | Kickers and accents on dark grounds; the reversed waterfall; a quarter-tint for soft panels |
  | Or (accent) | `#D4AF37` | Sparingly, as the guide asks: the current section's underline, the rule over a card without art, the footer's headings, the breaking strip. Never text on white (2.1:1) |

  The images themselves draw the mark in other blues (`#1C7BD0`, `#0050A0`).
  The traces use the README's.
- **Type:** the README's first choices: Newsreader for titles and the
  wordmark, Inter for body and interface. The guide image names
  Playfair Display and Source Sans 3 instead. All four are self-hosted,
  so either set is a small change.
- **Mark:** the nine images draw nine different waterfalls. The masthead
  uses file 01, the README's "logo principal", traced and set in Bleu
  Québec (reversed in Bleu clair on Bleu profond). The icons use file 07,
  the README's "icône / favicon", without its "LC" letters.
- **Wordmark:** live text in Newsreader, since the README asks only for
  "typographie élégante" and the images set it five ways.
- **Tagline:** "L’actualité de Québec et de l’Est-du-Québec", the
  homepage mock-up's line, which matches the README. The images offer
  four others, among them the guide's "Vivre l'essentiel", a tourism
  line.
- **Footprint:** the README's "Québec et l'est du Québec". The pack's
  descriptions say so, and the newsroom's wire pull has an
  Est-du-Québec region (Radio-Canada's Bas-Saint-Laurent,
  Gaspésie–Îles-de-la-Madeleine and Côte-Nord feeds, 50 items each on
  28 Nov).
- **Share card:** the package's banner is a template with an empty
  panel, so the card is drawn in the network's layout.
- Still a placeholder: newsletter "L’infolettre des Chutes".
- Desks unchanged: Actualités, Politique, Économie, Culture, Sports,
  Idées. The mock-ups show three other sets, one with an Est-du-Québec
  desk.
- Weather and clock: Québec.
- Decide:
  - which waterfall, if not the "logo principal";
  - the type, the README's or the guide's;
  - whether Lévis counts as local, and whether eastern Quebec gets a
    desk of its own;
  - the domain: chutesquebec.ca, or leschutes.com, which the guide
    prints and which was free on 28 Nov;
  - what the paper reports itself and what it links, for the inaugural
    edition.

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

### What the first package taught (The Easterly, 28 Nov)

- **A package can give links their own colour, apart from the dark
  grounds.** The Easterly's links and buttons are Harbour blue, but its
  dark colour is the ink. Telegraph paints both with one primary colour,
  and Harbour Deep on Harbour is 1.6:1, so the check above would fail.
  The Easterly's stylesheet paints its dark grounds with `deep`, the
  accents on them with `on-dark`, and buttons on them with `primary`.
  Check a package's pairs before mapping it, and do the same when its
  secondary colour can't sit on its primary.
- **A wide logotype moves the nav.** Seven desks don't fit beside a
  logotype the width of the Easterly's. Its nav became the masthead's
  second row, a full-width section bar with Subscribe at its end.
- **Tracing:**
  - Isolate each colour layer.
  - Upscale and threshold with `mkbitmap -n -s 4 -3 -t 0.5`.
  - Trace with `potrace -s -u 10 -t 4 -O 0.2 -a 1.0`.
  - Move the result 3/8 px right and 3/8 px up. The 4× upscale
    offsets the trace by that much.
  - Diff a render against the PNG: the Easterly's traces differ in 0.02%
    (mark) and 0.04% (logotype) of pixels.
  - Fit a round element, like the full stop, as an exact circle.
  - Verify a lockup by rendering the masthead at the scale where one CSS
    pixel equals one package pixel, and diffing the two.
- **A package's red is easy to overuse.** Lighthouse is for breaking
  news and the full stop. The Easterly's chrome gained a breaking strip
  for it, read through `pp_breaking_live()`.

### What the second package taught (Les Chutes, 28 Nov)

- **A generated package can disagree with itself.** Les Chutes' images
  drew nine different marks, five taglines and two type pairings. Follow
  the package's own text and the files it names, show the owner every
  variant on one sheet, and ask, rather than averaging the images.
- **Read colours from the spec, not from the pictures.** The images'
  blues were not the README's. Trace the shapes and fill them with the
  specified colours.
- **Smooth a mask from a JPEG before tracing it.** Nearest-colour masks
  of a compressed image stair-step, and potrace keeps every step. A
  1.8 px blur before `mkbitmap` cut the icon's paths from 59 KB to 12 KB
  at 0.15% of pixels changed.
- **Type is a role too.** Les Chutes' stylesheet names its faces once,
  as `--ch-head` and `--ch-ui`. The next package that names other faces
  changes two lines.
Then the VPS agent gets a launch brief: DNS, the generated nginx block,
the certificate, and the seed. Each paper opens to search engines only
when its newsroom presses Open in Settings.
