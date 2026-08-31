<?php
/**
 * Western Wire — launch package.
 * Loaded once by `PP_SITE=westernwire php tools/seed-launch.php`.
 *
 * Western Wire is the network's aggregator: most of its posts are wire links
 * whose headlines point to the outlet that reported them. The demonstration
 * wire items below link to launch stories on the network's own papers (the
 * Pacific Post, Kelowna Current, Kermode Chronicle, Edmonton Echo, Grande
 * Prairie Gazette, Prairie Dispatch), so every outbound link resolves to a
 * live page. The original stories are launch content in the Wire's own voice.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$img = fn (string $file) => '/assets/sites/westernwire/img/' . $file;

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['westernwire.ca', 'www.westernwire.ca'],

    // Shared network desks are reused (news, politics, business, sports,
    // opinion); the Wire adds only what the network lacks.
    'desks' => [
        ['name' => 'Energy & Resources', 'slug' => 'energy',  'color' => '#245A63', 'description' => 'Oil, gas, potash, hydro and the grid — tracked across four provinces and the newsrooms that cover them daily.'],
        ['name' => 'Culture',            'slug' => 'culture', 'color' => '#245A63', 'description' => 'What the West makes, watches, and gathers for — from the coast to the lakehead.'],
        // Sports exists on the production network (another paper seeded it);
        // listed here so the pack stands alone — created only if missing.
        ['name' => 'Sports',             'slug' => 'sports',  'color' => '#245A63', 'description' => 'From the coast to the lakehead: the teams the West fills its rinks and grounds for.'],
    ],

    'settings' => [
        // The byline every Hermes filing carries here. Without it the
        // server falls back to the generic 'Automated report'.
        'automated_byline'   => 'Western Wire Newsroom',
        'site_title'         => 'Western Wire',
        'tagline'            => 'The West, on one wire',
        'meta_description'   => 'A news aggregator for Western Canada — the day\'s reporting from Vancouver to Winnipeg, gathered on one page and credited to the newsrooms that filed it.',
        'footer_line'        => 'Aggregated from newsrooms across British Columbia, Alberta, Saskatchewan and Manitoba. Every headline links to the outlet that reported it.',
        'contact_email'      => 'tips@westernwire.ca',
        'newsletter_heading' => 'The 6 a.m. Wire',
        'newsletter_copy'    => 'Six stories from four provinces, in your inbox before the coffee\'s done.',
        'regions'            => json_encode([
            'bc'           => 'British Columbia',
            'alberta'      => 'Alberta',
            'saskatchewan' => 'Saskatchewan',
            'manitoba'     => 'Manitoba',
        ]),
    ],

    // The wire pool: one working feed per major outlet, keyed by province.
    // Feeds shared with sister papers are matched by URL and skipped.
    'sources' => [
        ['CBC British Columbia', 'https://www.cbc.ca/webfeed/rss/rss-canada-britishcolumbia', 'bc'],
        ['Global BC',            'https://globalnews.ca/bc/feed/',                            'bc'],
        ['The Tyee',             'https://thetyee.ca/rss2.xml',                               'bc'],
        ['CBC Calgary',          'https://www.cbc.ca/webfeed/rss/rss-canada-calgary',         'alberta'],
        ['CBC Edmonton',         'https://www.cbc.ca/webfeed/rss/rss-canada-edmonton',        'alberta'],
        ['Global Calgary',       'https://globalnews.ca/calgary/feed/',                       'alberta'],
        ['Global Edmonton',      'https://globalnews.ca/edmonton/feed/',                      'alberta'],
        ['CBC Saskatchewan',     'https://www.cbc.ca/webfeed/rss/rss-canada-saskatchewan',    'saskatchewan'],
        ['CBC Saskatoon',        'https://www.cbc.ca/webfeed/rss/rss-canada-saskatoon',       'saskatchewan'],
        ['Global Regina',        'https://globalnews.ca/regina/feed/',                        'saskatchewan'],
        ['CBC Manitoba',         'https://www.cbc.ca/webfeed/rss/rss-canada-manitoba',        'manitoba'],
        ['Global Winnipeg',      'https://globalnews.ca/winnipeg/feed/',                      'manitoba'],
    ],

    'stories' => [

        /* ---------------------------------------------- the lead wire item --- */
        [
            'type' => 'link', 'source_name' => 'The Edmonton Echo',
            'source_url' => 'https://edmontonecho.com/story/two-hours-on-curbside-parking-and-council-finally-says-what-downtown-is-for',
            'title' => 'Two hours on curbside parking, and council finally says what downtown is for',
            'slug' => 'wire-two-hours-on-curbside-parking-and-council-finally-says-what-downtown-is-for',
            'desk' => 'politics', 'region' => 'alberta',
            'lede' => 'The pilot prices a parking spot at what the space is worth. The debate priced everything else.',
            'image' => $img('photo-01.svg'),
            'image_credit' => 'The Edmonton Echo',
            'featured' => 1, 'placement' => 'hero', 'views' => 212, 'published' => $ago('-1 hour'),
            'tags' => 'transit, city council',
        ],

        /* --------------------------------------------------- the wire, BC --- */
        [
            'type' => 'link', 'source_name' => 'The Pacific Post',
            'source_url' => 'https://thepacificpost.com/story/interior-wildfire-smoke-pushes-metro-vancouver-to-its-worst-august-air-rating-since-2023',
            'title' => 'Interior wildfire smoke pushes Metro Vancouver to its worst August air rating since 2023',
            'slug' => 'wire-interior-wildfire-smoke-pushes-metro-vancouver-to-its-worst-august-air-rating-since-2023',
            'desk' => 'news', 'region' => 'bc',
            'lede' => 'Environment Canada issued an advisory for the Lower Mainland on Sunday night, with fine-particulate readings four times the provincial objective.',
            'image' => $img('photo-07.svg'),
            'image_credit' => 'The Pacific Post',
            'views' => 164, 'published' => $ago('-90 minutes'),
            'tags' => 'wildfire',
        ],
        [
            'type' => 'link', 'source_name' => 'The Pacific Post',
            'source_url' => 'https://thepacificpost.com/story/port-of-vancouver-clears-its-grain-backlog-after-a-three-week-rail-repair',
            'title' => 'Port of Vancouver clears its grain backlog after a three-week rail repair',
            'slug' => 'wire-port-of-vancouver-clears-its-grain-backlog-after-a-three-week-rail-repair',
            'desk' => 'business', 'region' => 'bc',
            'lede' => 'Twenty-nine vessels were waiting at anchor at the peak. The last of them loads this week.',
            'image' => $img('photo-08.svg'),
            'image_credit' => 'The Pacific Post',
            'views' => 82, 'published' => $ago('-3 hours'),
            'tags' => 'shipping, grain',
        ],
        [
            'type' => 'link', 'source_name' => 'The Pacific Post',
            'source_url' => 'https://thepacificpost.com/story/province-tables-tighter-short-term-rental-rules-before-the-fall-session',
            'title' => 'Province tables tighter short-term rental rules before the fall session',
            'slug' => 'wire-province-tables-tighter-short-term-rental-rules-before-the-fall-session',
            'desk' => 'politics', 'region' => 'bc',
            'lede' => 'The registry gets teeth, the platforms get liability, and the resort-town exemptions get one more review.',
            'views' => 77, 'published' => $ago('-7 hours'),
            'tags' => 'housing',
        ],
        [
            'type' => 'link', 'source_name' => 'The Pacific Post',
            'source_url' => 'https://thepacificpost.com/story/the-mariners-sign-a-homegrown-keeper-out-of-the-burnaby-academy',
            'title' => 'The Mariners sign a homegrown keeper out of the Burnaby academy',
            'slug' => 'wire-the-mariners-sign-a-homegrown-keeper-out-of-the-burnaby-academy',
            'desk' => 'sports', 'region' => 'bc',
            'lede' => 'Seventeen years old, raised eight blocks from the training ground, and handed a three-year deal the club calls a statement.',
            'views' => 58, 'published' => $ago('-10 hours'),
            'tags' => 'soccer',
        ],
        [
            'type' => 'link', 'source_name' => 'The Pacific Post',
            'source_url' => 'https://thepacificpost.com/story/the-pne-s-last-summer-on-the-old-hastings-park-midway',
            'title' => 'The PNE\'s last summer on the old Hastings Park midway',
            'slug' => 'wire-the-pne-s-last-summer-on-the-old-hastings-park-midway',
            'desk' => 'culture', 'region' => 'bc',
            'lede' => 'The amphitheatre is built, the midway moves next spring, and the wooden coaster — as required by law, sentiment and engineering — stays exactly where it is.',
            'views' => 69, 'published' => $ago('-12 hours'),
            'tags' => 'fairs',
        ],
        [
            'type' => 'link', 'source_name' => 'Kelowna Current',
            'source_url' => 'https://kelownacurrent.ca/story/a-new-era-of-water-planning-is-arriving-in-the-interior',
            'title' => 'A new era of water planning is arriving in the Interior',
            'slug' => 'wire-a-new-era-of-water-planning-is-arriving-in-the-interior',
            'desk' => 'energy', 'region' => 'bc',
            'lede' => 'The basin\'s licences now exceed its dry-year supply. The plans being drafted this year decide who blinks first.',
            'views' => 44, 'published' => $ago('-14 hours'),
            'tags' => 'water',
        ],
        [
            'type' => 'link', 'source_name' => 'Kermode Chronicle',
            'source_url' => 'https://kermodechronicle.ca/story/kitimat-s-second-lng-train-clears-environmental-review',
            'title' => 'Kitimat\'s second LNG train clears environmental review',
            'slug' => 'wire-kitimat-s-second-lng-train-clears-environmental-review',
            'desk' => 'energy', 'region' => 'bc',
            'lede' => 'The certificate carries forty-one conditions, and the two that matter most are about electricity.',
            'views' => 91, 'published' => $ago('-15 hours'),
            'tags' => 'lng, grid',
        ],
        [
            'type' => 'link', 'source_name' => 'The Pacific Post',
            'source_url' => 'https://thepacificpost.com/story/tunnelling-ends-on-the-broadway-subway-three-months-ahead-of-schedule',
            'title' => 'Tunnelling ends on the Broadway subway, three months ahead of schedule',
            'slug' => 'wire-tunnelling-ends-on-the-broadway-subway-three-months-ahead-of-schedule',
            'desk' => 'news', 'region' => 'bc',
            'lede' => 'Crews broke through at Arbutus on Friday afternoon. Track laying begins in September, and the province is still promising trains in 2027.',
            'image' => $img('photo-02.svg'),
            'image_credit' => 'The Pacific Post',
            'views' => 148, 'published' => $ago('-2 hours'),
            'tags' => 'transit',
        ],
        [
            'type' => 'link', 'source_name' => 'The Pacific Post',
            'source_url' => 'https://thepacificpost.com/story/fraser-sockeye-return-is-the-strongest-in-twelve-years-dfo-says',
            'title' => 'Fraser sockeye return is the strongest in twelve years, DFO says',
            'slug' => 'wire-fraser-sockeye-return-is-the-strongest-in-twelve-years-dfo-says',
            'desk' => 'news', 'region' => 'bc',
            'lede' => 'Test fisheries near Mission counted 4.6 million fish through July. Biologists credit cool spring flows and four years of restricted openings.',
            'image' => $img('photo-03.svg'),
            'image_credit' => 'The Pacific Post',
            'views' => 96, 'published' => $ago('-5 hours'),
            'tags' => 'fisheries',
        ],
        [
            'type' => 'link', 'source_name' => 'Kermode Chronicle',
            'source_url' => 'https://kermodechronicle.ca/story/province-defers-logging-on-2-100-hectares-in-the-nass-valley',
            'title' => 'Province defers logging on 2,100 hectares in the Nass Valley',
            'slug' => 'wire-province-defers-logging-on-2-100-hectares-in-the-nass-valley',
            'desk' => 'energy', 'region' => 'bc',
            'lede' => 'The deferral covers three cutblocks approved in 2019, and lands the question of long-term protection with a joint planning table that has not met since March.',
            'views' => 61, 'published' => $ago('-8 hours'),
            'tags' => 'forestry',
        ],
        [
            'type' => 'link', 'source_name' => 'Kelowna Current',
            'source_url' => 'https://kelownacurrent.ca/story/the-valley-s-next-decade-will-be-decided-by-what-it-builds-now',
            'title' => 'The valley\'s next decade will be decided by what it builds now',
            'slug' => 'wire-the-valley-s-next-decade-will-be-decided-by-what-it-builds-now',
            'desk' => 'business', 'region' => 'bc',
            'lede' => 'Housing, water, transportation and public space are converging into one defining regional question: how should the Okanagan grow?',
            'views' => 54, 'published' => $ago('-11 hours'),
            'tags' => 'housing',
        ],

        /* ---------------------------------------------- the wire, Alberta --- */
        [
            'type' => 'link', 'source_name' => 'The Edmonton Echo',
            'source_url' => 'https://edmontonecho.com/story/the-crosstown-bus-map-gets-its-first-redraw-in-a-decade',
            'title' => 'The crosstown bus map gets its first redraw in a decade',
            'slug' => 'wire-the-crosstown-bus-map-gets-its-first-redraw-in-a-decade',
            'desk' => 'news', 'region' => 'alberta',
            'lede' => 'Fewer routes, straighter lines, and a promise that the ones that remain will actually come.',
            'views' => 88, 'published' => $ago('-4 hours'),
            'tags' => 'transit',
        ],
        [
            'type' => 'link', 'source_name' => 'The Prairie Dispatch',
            'source_url' => 'https://prairiedispatch.ca/story/canola-contracts-move-early-as-growers-watch-a-dry-june',
            'title' => 'Canola contracts move early as growers watch a dry June',
            'slug' => 'wire-canola-contracts-move-early-as-growers-watch-a-dry-june',
            'desk' => 'business', 'region' => 'alberta',
            'lede' => 'Elevators north of the river are signing at levels last seen in 2023, and nobody wants to be the last one to price.',
            'image' => $img('photo-04.svg'),
            'image_credit' => 'The Prairie Dispatch',
            'views' => 121, 'published' => $ago('-6 hours'),
            'tags' => 'agriculture, canola',
        ],
        [
            'type' => 'link', 'source_name' => 'Grande Prairie Gazette',
            'source_url' => 'https://grandeprairiegazette.ca/story/aurora-season-opens-and-the-county-s-dark-sky-pullouts-draw-their-first-full-night',
            'title' => 'Aurora season opens, and the county\'s dark-sky pullouts draw their first full night',
            'slug' => 'wire-aurora-season-opens-and-the-county-s-dark-sky-pullouts-draw-their-first-full-night',
            'desk' => 'culture', 'region' => 'alberta',
            'lede' => 'A coronal mass ejection pushed the borealis far enough south to watch from a downtown parking lot. The county built four places to see it properly.',
            'image' => $img('photo-06.svg'),
            'image_credit' => 'Grande Prairie Gazette',
            'views' => 73, 'published' => $ago('-13 hours'),
            'tags' => 'tourism',
        ],

        [
            'type' => 'link', 'source_name' => 'The Edmonton Echo',
            'source_url' => 'https://edmontonecho.com/story/transit-operating-grants-get-a-new-formula-and-the-cities-do-the-math',
            'title' => 'Transit operating grants get a new formula, and the cities do the math',
            'slug' => 'wire-transit-operating-grants-get-a-new-formula-and-the-cities-do-the-math',
            'desk' => 'politics', 'region' => 'alberta',
            'lede' => 'The new money follows ridership instead of population. For a city whose trains are full, that changes the ask.',
            'views' => 63, 'published' => $ago('-5 hours'),
            'tags' => 'transit',
        ],
        [
            'type' => 'link', 'source_name' => 'Grande Prairie Gazette',
            'source_url' => 'https://grandeprairiegazette.ca/story/montney-permits-climb-as-two-operators-add-rigs-near-wembley',
            'title' => 'Montney permits climb as two operators add rigs near Wembley',
            'slug' => 'wire-montney-permits-climb-as-two-operators-add-rigs-near-wembley',
            'desk' => 'energy', 'region' => 'alberta',
            'lede' => 'Nineteen new licences west of the city this quarter, the most since 2022 — and this round comes with water plans attached.',
            'views' => 71, 'published' => $ago('-7 hours 30 minutes'),
            'tags' => 'natural gas',
        ],
        [
            'type' => 'link', 'source_name' => 'Grande Prairie Gazette',
            'source_url' => 'https://grandeprairiegazette.ca/story/canola-comes-off-two-weeks-early-and-the-elevators-adjust-on-the-fly',
            'title' => 'Canola comes off two weeks early, and the elevators adjust on the fly',
            'slug' => 'wire-canola-comes-off-two-weeks-early-and-the-elevators-adjust-on-the-fly',
            'desk' => 'business', 'region' => 'alberta',
            'lede' => 'A dry July moved the whole county\'s timeline up. The crop is lighter than hoped, drier than feared, and moving fast.',
            'views' => 52, 'published' => $ago('-17 hours'),
            'tags' => 'agriculture, canola',
        ],
        [
            'type' => 'link', 'source_name' => 'The Edmonton Echo',
            'source_url' => 'https://edmontonecho.com/story/minor-hockey-s-ice-bill-rises-eight-per-cent-and-the-leagues-redraw-the-map',
            'title' => 'Minor hockey\'s ice bill rises eight per cent, and the leagues redraw the map',
            'slug' => 'wire-minor-hockey-s-ice-bill-rises-eight-per-cent-and-the-leagues-redraw-the-map',
            'desk' => 'sports', 'region' => 'alberta',
            'lede' => 'The rate covers the refrigeration bill. The scramble is over who skates at 6 a.m. and who gets the Saturday slots.',
            'views' => 47, 'published' => $ago('-12 hours 30 minutes'),
            'tags' => 'hockey',
        ],
        [
            'type' => 'link', 'source_name' => 'The Edmonton Echo',
            'source_url' => 'https://edmontonecho.com/story/a-hundred-seats-on-118-avenue-the-alberta-avenue-playhouse-books-a-full-season',
            'title' => 'A hundred seats on 118 Avenue: the Alberta Avenue playhouse books a full season',
            'slug' => 'wire-a-hundred-seats-on-118-avenue-the-alberta-avenue-playhouse-books-a-full-season',
            'desk' => 'culture', 'region' => 'alberta',
            'lede' => 'Five companies, forty weeks, and a box office that fits in a cash tin. The neighbourhood theatre model, working.',
            'views' => 39, 'published' => $ago('-18 hours'),
            'tags' => 'theatre',
        ],

        /* ------------------------- original reporting, in the Wire's voice --- */
        [
            'title' => 'Early harvest across the southwest, and the first yield reports beat the gloom',
            'desk' => 'business', 'region' => 'saskatchewan', 'byline' => 'Western Wire staff', 'dateline' => 'Swift Current',
            'lede' => 'Durum is coming off two weeks ahead of normal, and the early weighed loads are running above the July estimates that had the trade braced.',
            'views' => 41, 'published' => $ago('-10 hours 30 minutes'),
            'tags' => 'agriculture, durum',
            'body' => $p(
                'SWIFT CURRENT — The first durum of the season crossed scales in the southwest this week, two weeks ahead of the ten-year normal, and the early weights are the story: loads grading better and yielding higher than the July crop report that had the trade braced for a short year.',
                'Two weeks of heat finished the crop fast, which cuts both ways — thinner kernels in the driest corners, but harvest weather that lets operators take it off dry and skip the aeration bill.',
                'Elevator companies moved first: harvest-delivery premiums in the southwest widened this week, a sign the line companies want bushels early for fall vessel positions out of Vancouver and Thunder Bay.',
                'The wire will carry the provincial crop reports as the newsrooms on this beat file them — credited, and linked at the headline.'
            ),
        ],
        [
            'title' => 'Churchill loads its first grain of the season three weeks early',
            'desk' => 'business', 'region' => 'manitoba', 'byline' => 'Western Wire staff', 'dateline' => 'Churchill',
            'lede' => 'An early ice-out on Hudson Bay opened the port in the first week of August, and the railway says the line held through spring melt for a third straight year.',
            'image' => $img('photo-09.svg'), 'image_caption' => 'The season\'s first laker at the Churchill terminal.', 'image_credit' => 'Illustration for Western Wire',
            'views' => 38, 'published' => $ago('-19 hours'),
            'tags' => 'shipping, grain',
            'body' => $p(
                'CHURCHILL — The first grain vessel of the season began loading at the Port of Churchill this week, three weeks earlier than last year, after an early ice-out on Hudson Bay opened the shipping lane in the first week of August.',
                'The earlier window matters because Churchill\'s season is the shortest of any Canadian grain port; every added week changes the arithmetic for shippers weighing the northern route against the congested corridors to Vancouver.',
                'The railway\'s owners say the line took spring melt without a washout for a third consecutive year — the operational answer to the question that shut the route entirely in 2017.',
                'Provincial trade officials call the port\'s season a test case for northern export capacity; the newsrooms closest to the file will carry the tonnage numbers as they land, and the wire will point at them.'
            ),
        ],
        [
            'title' => 'Potash on the water: what a reopened Baltic route means for Saskatchewan\'s mines',
            'desk' => 'energy', 'region' => 'saskatchewan', 'byline' => 'Western Wire staff', 'dateline' => 'Saskatoon',
            'lede' => 'Shipping through the Baltic resumed this month after two years of rerouting. The first contracts priced off the shorter route land in September.',
            'image' => $img('photo-05.svg'), 'image_caption' => 'Rail cars staged below a headframe in the potash belt.', 'image_credit' => 'Illustration for Western Wire',
            'views' => 67, 'published' => $ago('-9 hours'),
            'tags' => 'potash',
            'body' => $p(
                'SASKATOON — The first potash cargoes priced off a reopened Baltic shipping route load next month, and the arithmetic matters more in Saskatchewan than almost anywhere else: the province supplies roughly a third of the world\'s traded potash, and for two years every tonne bound for Europe has carried the cost of going the long way around.',
                'Producers spent those two years quietly rebuilding the other half of the map — more volume through Vancouver and Thunder Bay, more term contracts in Brazil and Southeast Asia. The question the reopened route poses is whether the European tonnes come back at the old netbacks, or whether the rerouted book has become the book.',
                'Analysts who cover the sector split on timing but not direction: shorter routes compress freight, compressed freight firms the mine gate price, and a firmer mine gate price lands — eventually — in royalty revenue the provincial budget has twice revised downward.',
                'The wire will follow the story through the fall as the newsrooms on this beat file. Every headline on Western Wire links to the outlet that reported it.'
            ),
        ],
        [
            'title' => 'Manitoba\'s flood maps are twenty years old, and this is the year that shows',
            'desk' => 'politics', 'region' => 'manitoba', 'byline' => 'Western Wire staff', 'dateline' => 'Winnipeg',
            'lede' => 'The province budgets for the flood it has mapped. The last two springs arrived off the map entirely.',
            'views' => 49, 'published' => $ago('-16 hours'),
            'tags' => 'flooding',
            'body' => $p(
                'WINNIPEG — Manitoba\'s designated flood areas were last comprehensively mapped in the mid-2000s, and the gap between that map and the last two springs has become the quiet through-line of this year\'s infrastructure debate.',
                'The mapping matters because nearly everything downstream of it is mechanical: where the province requires flood-proofing, which municipalities qualify for mitigation grants, what the Crown insurer prices, and where a buyer\'s lawyer tells them not to buy. A map that is twenty years old does all of that work with twenty-year-old assumptions about where the water goes.',
                'A provincial review promised new hydraulic modelling for the Red and the Assiniboine by 2028. Municipal leaders along both rivers have asked for an interim designation this year, arguing that two springs of off-map flooding are themselves a dataset.',
                'Western Wire will carry the reporting from the newsrooms closest to it as the file moves — credited, and linked at the headline.'
            ),
        ],
        [
            'title' => 'How Western Wire works: every headline links to the newsroom that reported it',
            'desk' => 'news', 'byline' => 'The Western Wire desk',
            'lede' => 'One page for the West. The reporting stays where it was filed — we point at it, credit it, and send you there.',
            'views' => 35, 'published' => $ago('-20 hours'),
            'tags' => '',
            'body' => $p(
                'Western Wire is a news aggregator for Western Canada: the day\'s reporting from Vancouver to Winnipeg, gathered on one page. Most of what you see here is a wire link — a headline, a summary, and a credit. Click it and you land on the outlet that did the work, because that is where the work should be read.',
                'The desk assigns each item a region and a beat, so you can follow British Columbia, Alberta, Saskatchewan or Manitoba on their own pages, or a single topic across all four. Alongside the wire, Western Wire files original pieces where a story crosses provincial lines and no single newsroom holds the whole picture.',
                'We credit by name, we link at the headline, and we cache nothing but a card image. If you run a newsroom in the West and want your reporting on the wire — or off it — write to the desk at tips@westernwire.ca.',
                'The 6 a.m. Wire, our daily email, compiles the previous day\'s best six items the same way: credited, and linked to the source.'
            ),
        ],
    ],
];
