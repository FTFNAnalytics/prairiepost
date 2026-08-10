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

    // Shared network desks are reused (news, politics, business, sports,
    // opinion); the Wire adds only what the network lacks.
    'desks' => [
        ['name' => 'Energy & Resources', 'slug' => 'energy',  'color' => '#245A63', 'description' => 'Oil, gas, potash, hydro and the grid — tracked across four provinces and the newsrooms that cover them daily.'],
        ['name' => 'Culture',            'slug' => 'culture', 'color' => '#245A63', 'description' => 'What the West makes, watches, and gathers for — from the coast to the lakehead.'],
    ],

    'settings' => [
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
            'source_url' => 'https://thepacificpost.com/story/tunnelling-ends-on-the-broadway-subway-three-months-ahead-of-schedule',
            'title' => 'Tunnelling ends on the Broadway subway, three months ahead of schedule',
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
            'desk' => 'energy', 'region' => 'bc',
            'lede' => 'The deferral covers three cutblocks approved in 2019, and lands the question of long-term protection with a joint planning table that has not met since March.',
            'views' => 61, 'published' => $ago('-8 hours'),
            'tags' => 'forestry',
        ],
        [
            'type' => 'link', 'source_name' => 'Kelowna Current',
            'source_url' => 'https://kelownacurrent.ca/story/the-valley-s-next-decade-will-be-decided-by-what-it-builds-now',
            'title' => 'The valley\'s next decade will be decided by what it builds now',
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
            'desk' => 'news', 'region' => 'alberta',
            'lede' => 'Fewer routes, straighter lines, and a promise that the ones that remain will actually come.',
            'views' => 88, 'published' => $ago('-4 hours'),
            'tags' => 'transit',
        ],
        [
            'type' => 'link', 'source_name' => 'The Prairie Dispatch',
            'source_url' => 'https://prairiedispatch.ca/story/canola-contracts-move-early-as-growers-watch-a-dry-june',
            'title' => 'Canola contracts move early as growers watch a dry June',
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
            'desk' => 'culture', 'region' => 'alberta',
            'lede' => 'A coronal mass ejection pushed the borealis far enough south to watch from a downtown parking lot. The county built four places to see it properly.',
            'image' => $img('photo-06.svg'),
            'image_credit' => 'Grande Prairie Gazette',
            'views' => 73, 'published' => $ago('-13 hours'),
            'tags' => 'tourism',
        ],

        /* ------------------------- original reporting, in the Wire's voice --- */
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
