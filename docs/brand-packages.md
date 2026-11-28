# Brand packages: what each new paper needs from the owner

**Status (28 Nov 2026).** Five papers from the expansion slate
(`NETWORK-EXPANSION.md`) are built as unbranded foundations on Toronto
Telegraph's design: the same layout, type and page furniture, in a
placeholder slate colour scheme with a monogram roundel for a mark. Each
has a launch pack with its domains, desks and wire feeds, and no stories.
Terminal City Times has been a foundation since 22 Sep, in its own
heritage-broadsheet design. All five packages, The Easterly's, Les
Chutes', Citadel Courier's, Le Memphré's and Borealis Bulletin's,
arrived and were applied on 28 Nov (below).

| Paper | Slug and template | Covers | Domain | State |
| --- | --- | --- | --- | --- |
| Citadel Courier | `citadel-courier`, `citadel` | Halifax and Nova Scotia | citadelcourier.ca | Branded 28 Nov; domain unregistered |
| The Easterly | `easterly`, `easterly` | Atlantic Canada, region-wide | easterly.ca | Branded 28 Nov; domain unregistered |
| Le Memphré | `memphre`, `memphre` | Sherbrooke and the Eastern Townships (French) | memphre.ca | Branded 28 Nov; language and domain open |
| Les Chutes | `chutes`, `chutes` | Quebec City and eastern Quebec (French) | chutesquebec.ca | Branded 28 Nov; domain unregistered |
| Borealis Bulletin | `borealis-bulletin`, `borealis` | Yukon, NWT and Nunavut, and the Arctic beyond | borealisbulletin.ca | Branded 28 Nov; launch waits on the domain |
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

**Citadel Courier.** The package arrived on 28 Nov and is applied. It
held a README, two logos, a palette sheet, homepage, article and phone
mock-ups, an app icon, a social banner and a brand guide, all as JPEG
images. The images disagree with each other, so the build follows the
README, the package's only text, and the files it names:
- **Colours:** the six the README and the guide give.

  | Package name | Hex | Where it shows |
  | --- | --- | --- |
  | Nova Scotia Blue (primary) | `#0047B6` | Links, buttons, desk chips, the masthead rule, the fort |
  | Deep Navy | `#003262` | All type; the dark grounds (utility strip, lead panel, subscribe box, footer band) |
  | Pure White | `#FFFFFF` | The page; type on dark grounds |
  | Light Sea Blue | `#A8D5E2` | Kickers and accents on dark grounds; the reversed fort; the icon's lighthouse; a quarter-tint for soft panels |
  | Gold Accent | `#FDD44F` | Sparingly: the current section's underline, the rule over a card without art, the footer's headings. Never text on white (1.4:1) |
  | Crimson (Courier) Red | `#D21034` | The breaking strip only, as the guide's "urgency, alerts" |

- **Type:** the README's first choices: Newsreader for titles and the
  wordmark, Inter for body and interface, the same pair as Les Chutes.
  The guide image sets headings in Inter SemiBold instead. Source
  Serif 4 and Source Sans 3, the README's alternatives, are self-hosted
  too, and would set the Courier apart from Les Chutes.
- **Mark:** the nine images draw nine different marks. The masthead uses
  file 01, the README's "primary logo": the fort, lighthouse and
  newspaper, traced and set in Nova Scotia Blue and Deep Navy (reversed
  in Light Sea Blue). The guide image labels a castle in a shield
  "primary logo" instead, and that shield recurs in three images. The
  icons use file 07, the README's "app icon / favicon": the lighthouse
  on the wall, its body in Light Sea Blue, because the spec's blue on
  Deep Navy is 1.6:1 and disappears at 16 px.
- **Wordmark:** live text in Newsreader, mixed case as the primary logo
  sets it.
- **Tagline:** "Halifax • Nova Scotia", the homepage mock-up's line
  under the wordmark. Footer line: "News that matters, from where you
  live.", its footer. The guide's slogans ("Reliable. Trusted. On Your
  Way.", "Delivering what matters") are a delivery company's, and the
  article mock-up's "Since 1873" is untrue; neither is used.
- **Footprint:** the README's "Halifax and Nova Scotia".
- **Share card:** the package's banner is a template with an empty
  panel, so the card is drawn in the network's layout.
- Still a placeholder: newsletter "The Noon Gun".
- Desks unchanged: Halifax, City Hall, Province, Business, Sports,
  Opinion. The mock-ups show three other sets.
- Weather and clock: Halifax.
- Decide:
  - which mark: the fort, or the guide's shield;
  - the type, the README's or the guide's;
  - how the Courier and The Easterly divide Nova Scotia;
  - whether to hold citadelcourier.com;
  - what the paper reports itself, for the inaugural edition.

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

**Le Memphré.** The package arrived on 28 Nov and is applied. It held
brand guidelines (a Word file marked "Draft Version · November 2026")
and a draft website: five HTML pages, a stylesheet, the logo as a PNG
and two photographs. Unlike the other generated packages it agrees with
itself, with one logo, one palette and one type pairing. Both parts are
written in English, and the slate made Le Memphré a French paper:
- **Colours:** the guidelines' four.

  | Package name | Hex | Where it shows |
  | --- | --- | --- |
  | Primary Blue | `#085492` | Headlines, navigation, the Subscribe button, the numbered latest list, the byline rule |
  | Dark Blue | `#053A6E` | The wordmark; the dark grounds (utility strip, lead panel, subscribe box, footer band) |
  | Teal | `#28A1AC` | Accent bars and rules, the masthead rule, the breaking strip (under ink, 4.7:1), buttons on dark grounds. Never text on white (3.1:1) |
  | Light Teal | `#E6F6F7` | Soft fills (quotes, the empty state); text on dark grounds |

  The guidelines want Teal for links and category labels, but as text it
  fails AA. Text in teal uses `#1A747C`, the same hue deepened to 5.5:1:
  links and desk chips. The draft site's stylesheet gives the rest: ink
  `#1A2A3A`, meta grey `#5A6A7A`, borders `#D0D8E0` and footer links
  `#A0D0E0` (6.9:1 on Dark Blue).
- **Type:** the guidelines' own. Playfair Display sets the wordmark and
  the headlines, and Source Sans 3 the body, navigation and interface.
  Both are self-hosted, and `memphre.css` declares Playfair Display
  itself (lesson 9). The draft site's Google Fonts links are not
  carried over.
- **Mark:** the logo's serpent, traced and filled with the guidelines'
  blue-to-teal gradient, and the three-line wave under the name, traced
  as `waves.svg`. The wordmark is live text. The logo's blue on Dark Blue
  is 1.5:1, so on the footer band and the icons the serpent is reversed,
  Light Teal to Teal. The guidelines say not to recolour the logo.
- **Tagline:** the guidelines' subtitle in French, "L’actualité de
  Sherbrooke et des Cantons-de-l’Est" ("News for Sherbrooke & the
  Eastern Townships"). Their tagline, "Stories from the Heart of the
  Townships", has no French version yet. Footer line: the draft site's
  in French, "L’information indépendante de Sherbrooke et des
  Cantons-de-l’Est."
- **Footprint:** the guidelines' Sherbrooke and the Eastern Townships,
  not the foundation's Magog and the lake. Sherbrooke now sets the
  weather (ECCC `qc-136`) and the copyright line. The desk descriptions
  name the region, and the newsroom's wire pull has a Sherbrooke region
  (the Sherbrooke Record, 10 items on 28 Nov) beside Magog and Estrie.
- **Share card:** drawn in the network's layout, from the masthead lockup.
- **Not used:** the draft site's stories. They read as news about real
  places (a council decision, rabies in wildlife, an air ambulance), and
  a launch carries only notes about the paper (CLAUDE.md, debt item 1).
- Still a placeholder: newsletter "L’infolettre du Memphré".
- Desks unchanged: Actualités, Économie, Environnement, Culture, Sports,
  Idées. The draft site's nav has two sections, Local News and Culture.
- Weather and clock: Sherbrooke, on Eastern time.
- Decide:
  - the language: French, as built, or English, as the package is
    written. English means `chrome.lang: en` and the package's own
    words;
  - the domain: memphre.ca, from the slate, or lememphre.ca, which the
    package's addresses use (brand@ and info@). Both were free on
    28 Nov;
  - the tagline, in whichever language the paper takes;
  - the serpent on dark grounds: reversed as built, the full-colour
    logo on a white panel, or no mark there;
  - whether L'Estrien, slated as Estrie's paper, is still wanted
    (`NETWORK-EXPANSION.md`);
  - what the paper reports itself, for the inaugural edition.

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

**Borealis Bulletin.** The package arrived on 28 Nov and is applied. It
is unlike the other four:
- It held a brand guide and a website preview as HTML pages, design
  tokens, three reference photographs, six vector SVGs, a share card and
  icons.
- It also held code: a stylesheet, templates, chrome partials, a palette,
  a launch pack and a runbook, written against this repository from the
  foundation's own files.
- It agrees with itself, and its marks are real vectors, so nothing was
  traced.

The build kept the package's code after reviewing it like any pull
request: every helper it calls exists, and every font it names is
already self-hosted. What the build changed is listed below.
- **Colours:** the package's, by its names.

  | Package name | Hex | Where it shows |
  | --- | --- | --- |
  | Polar Midnight | `#0B1E2D` | The wordmark's first line; the dark grounds (utility strip, lead panel, subscribe box, footer band); Subscribe; the Arctic & North and Economy chips |
  | Boreal Pine | `#0F5243` | Links, the wordmark's second line, the Territories, Land & Climate and Communities chips, the numbered latest list (9.1:1 on white) |
  | Aurora Emerald | `#2E9E76` | Rules, the current section's underline, the bar over a card without art, buttons under dark type. Never text on white (3.35:1) |
  | Aurora Luminous | `#38B889` | The reversed wordmark and buttons on Polar Midnight |
  | Glacier Ice | `#4C9DB8` | The weather box's rule, the masthead's horizon line, the roundel's second ribbon |
  | Lichen Copper | `#9E4A1E` | Opinion chips, and the breaking strip under white type (6.1:1) |
  | Frost Mist | `#EEF4F5` | The corridor bar, the weather box, quotes, the empty state |
  | Permafrost Slate | `#4C5E6B` | Bylines, datelines, captions (6.7:1 on white) |
  | Drift Silver | `#B7C6CE` | Card borders and rules, never text |

  Every text pairing passes AA. The lowest are the corridor bar (6.05:1)
  and dark type on Aurora buttons (5.4:1).
- **Type:** Telegraph's Archivo, Archivo Narrow and Source Serif 4, plus
  IBM Plex Mono for datelines, chips, kickers and the weather. All four
  were already self-hosted. The sheet declares each itself (lesson 9),
  and CDP confirms each paints as a custom font.
- **Mark:** the Polar Compass & Aurora Roundel. Polaris breaks through a
  60th-parallel compass ring, over two aurora ribbons and the Shield
  horizon. It is sky and land only, as the slate asked, and the package's
  guide forbids Inuit and Dene symbols too. The mark, the reversed mark
  and a simplified favicon are used as delivered.
  - `render-icons` redrew the icons from the favicon, and they match the
    package's PNGs within 2% of pixels.
  - The share card is the package's own.
  - The package's three lockups set the name as SVG text, which an
    `<img>` can't load the brand font for, so the site doesn't use them.
- **Wordmark:** live text, as the package's chrome sets it: "Borealis" in
  Polar Midnight over "Bulletin" in Boreal Pine, Archivo Narrow capitals,
  beside the roundel. It is compact enough that the footer needs no 85%
  rule: it stays inside its column at every width.
- **Words:** the package's. Tagline "Above the 60th. On the record.",
  newsletter "The 60th Parallel Brief", and the footer line
  "Independent northern reporting from Whitehorse, Yellowknife, Iqaluit,
  and every latitude north of 60°."
- **Desks:** the package's six: Territories (`local-news`), Arctic & North
  (`arctic-north`, new network-wide), Land & Climate (`environment`),
  Economy (`business`), Communities and Opinion.
- **Footprint:** wider than the slate's three territories: Inuit
  Nunangat, the provincial Norths and the circumpolar Arctic too. The
  wire pull adds Eye on the Arctic to the foundation's four northern
  feeds.
- **Home city:** Yellowknife, the package's choice. The weather is ECCC
  `nt-24`. The clock is America/Edmonton, through the one-line fix in
  `app/weather.php`, because PHP lists America/Yellowknife only as its
  alias.
- **What the build changed in the package's code:**
  - the class prefix is the foundation's `bo-`, because `bb-` already
    names three other papers' sheets;
  - the dozen tints the sheet set inline are named once at its top;
  - the network's breaking strip, read through `pp_breaking_live()`;
  - the footer's year through `pp_date()`, not bare `date()`;
  - the footer lockup is beside the wordmark again. The column links'
    `display: block` had stacked it, which the package's own comment
    says it shouldn't be;
  - chrome strings go through `pp_t()`;
  - the launch notes: links to `/tips` instead of a mailto to an address
    on an unregistered domain; statements of what each desk covers
    instead of facts about the North; dates set at seed time; and no
    `contact_email`;
  - the runbook is ours, with the package's checks in it. It asks for A
    records only, where the package asked for AAAA too.
- **Not used:** the three reference photographs, except in local
  mock-ups. The guide's and preview's HTML pages, which carry scripts,
  were read as text only.
- Decide:
  - borealisbulletin.com: the package names it beside the .ca. It joins
    the pack once it is registered and pointed at the VPS;
  - trademark clearance on the name, which the package's runbook asks
    for before launch;
  - the footprint's edges. Nunavik is in Quebec, and Nunatsiavut is in
    Labrador, which The Easterly covers as part of Atlantic Canada;
  - the footer line, which says the paper reports from the three
    capitals. Keep it once that is true.

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

### What the third package taught (Citadel Courier, 28 Nov)

- **Check every pair of spec colours the icon will use, not just text.**
  Citadel's spec blue on its spec navy is 1.6:1, and the app icon drawn
  in those two colours vanished at 16 px. The icon took the spec's light
  blue instead, which also matches the reversed mark.
- **A generated guide can carry another business's words.** Citadel's
  guide read "Courier" as a delivery company ("Every delivery. Every
  time.") and its article mock-up invented a founding year. Take words
  only where they fit a newspaper and are true.
- **Measure the footer lockup at every width.** A lockup sized for the
  masthead outgrows the footer's first column, between 981 and about
  1100 px in the four-column footer and everywhere in the two-column
  one. Measure the lockup's images and text, not its link box, which the
  grid clamps. Every branded paper now draws the footer lockup at 85%,
  and below 981 px gives its column a row of its own.

### What the fourth package taught (Le Memphré, 28 Nov)

- **A package can be written in another language than the paper.**
  Le Memphré's guidelines and draft site are English, and the slate made
  it a French paper. Translate the package's lines and ask about the
  language. Don't switch the paper's language on the package's evidence
  alone: the chrome, the dates and the bylines all follow it.
- **A package can move a paper's footprint, and the home city is in
  more places than the tagline.** The guidelines centre Sherbrooke, not
  Magog. Moving it meant the weather city in `palette.json`, the
  copyright line in the footer partial, the desk descriptions, the
  newsletter copy, the meta description, a wire region and the runbook.
  Search the paper's files for the old city's name before calling it
  done.
- **A draft website is a design reference, not content.** Take its
  colours, type and lines. Leave its stories, photographs and email
  addresses until the owner says they are the paper's.
- **Guidelines that forbid recolouring still put the logo on a dark
  ground.** Le Memphré's footer is Dark Blue by the guidelines' own
  rule, and the logo's blue on it is 1.5:1. Build the reversed version
  so the page works, and ask.

### What the fifth package taught (Borealis Bulletin, 28 Nov)

- **A package can ship code. Review it as code, then keep what passes.**
  Borealis's package shipped the paper's stylesheet, templates, chrome
  and launch pack, written from the foundation's own files. The build
  ran the checks any pull request gets:
  - every helper it calls exists, and every font it names is vendored;
  - every text pairing passes AA;
  - no bare `date()`, no mailto, and the breaking strip only through
    `pp_breaking_live()`;
  - a render at seven widths, and the suite on both engines.

  It passed most of them, so the package's design ships largely as
  delivered.
- **Launch notes in a package still have to be true by construction.**
  The package wrote its notes about the paper, as asked. They also
  stated facts about the North and sent readers to a tips address on a
  domain nobody has registered. Read each sentence and ask whether it is
  about the paper or about the world.
- **A designer's runbook can contradict the network's state.** The
  package asked for AAAA records, which the network publishes none of
  until PLAN.md 9.2. Keep the runbook ours, and fold the package's
  checks into it.
- **Every package has hit the footer lockup.** `.xx-foot a { display:
  block }` outranks `.xx-lock`, so the lockup stacks or overflows. Check
  the footer's lockup at every width before calling a build done.
- **Vector marks skip the tracing, but not the font check.** A logo that
  sets the name as SVG text paints in a fallback face inside an `<img>`.
  Use the mark as an image and set the name as live text.
