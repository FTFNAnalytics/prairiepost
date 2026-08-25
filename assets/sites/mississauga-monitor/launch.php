<?php
/**
 * The Mississauga Monitor — launch pack.
 * Seeded once by `PP_SITE=mississauga-monitor php tools/seed-launch.php`.
 *
 * DEMONSTRATION CONTENT. These seventeen stories exist to show the
 * design — the navy hero, the ward briefs, the card grid, the live
 * desk — and their copy comes from the design canvas itself. They are
 * illustrative, not journalism, and the newsroom replaces them.
 *
 * Every slug is explicit and prefixed `mm-`: posts.slug is UNIQUE
 * across the whole shared database and the seeder fails loudly on a
 * collision. Desks city-hall, transit, communities, business and
 * environment already exist network-wide and are reused; development,
 * courts, peel-region and live are this paper's additions.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$q = fn (string $quote, string $who) => '<blockquote><p>' . $quote . '</p><cite>' . $who . '</cite></blockquote>';
$img = fn (string $f) => '/assets/sites/mississauga-monitor/img/' . $f;

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['mississaugamonitor.com', 'www.mississaugamonitor.com'],

    'desks' => [
        ['name' => 'City Hall',   'slug' => 'city-hall',   'color' => '#006BB6', 'description' => 'Council, committees, the budget and the people who run the city. We sit through every meeting so you do not have to.'],
        ['name' => 'Transit',     'slug' => 'transit',     'color' => '#006BB6', 'description' => 'MiWay, the Hurontario LRT, GO and every way this city moves.'],
        ['name' => 'Development', 'slug' => 'development', 'color' => '#006BB6', 'description' => 'What gets built, where, and who decided.'],
        ['name' => 'Courts',      'slug' => 'courts',      'color' => '#006BB6', 'description' => 'The Brampton courthouse and the cases that shape Peel.'],
        ['name' => 'Business & Markets', 'slug' => 'business', 'color' => '#006BB6', 'description' => 'Employers, storefronts and the local economy.'],
        ['name' => 'Peel Region', 'slug' => 'peel-region', 'color' => '#006BB6', 'description' => 'The region, its services and its two other cities.'],
        ['name' => 'Communities', 'slug' => 'communities', 'color' => '#006BB6', 'description' => 'Neighbourhoods, events and the people of Mississauga.'],
        ['name' => 'Environment', 'slug' => 'environment', 'color' => '#00B862', 'description' => 'The lakefront, the creeks and the canopy.'],
        ['name' => 'Live',        'slug' => 'live',        'color' => '#F5821F', 'description' => 'Live coverage from council chambers, courtrooms and scenes across the city. We cover them live.'],
    ],

    'settings' => [
        'site_title'         => 'The Mississauga Monitor',
        'tagline'            => 'Local news. Trusted source.',
        'meta_description'   => 'Local news for Mississauga and the GTA — city hall, transit, development and the communities of Peel, reported independently.',
        'footer_line'        => 'Covering Mississauga & the Greater Toronto Area.',
        'contact_email'      => 'tips@mississaugamonitor.com',
        'newsletter_heading' => 'Newsletter signup',
        'newsletter_copy'    => 'Get the latest local news delivered to your inbox, every weekday morning.',
        'breaking_label'     => 'Multi-vehicle crash on Highway 401 eastbound causes major delays in Mississauga',
        'breaking_url'       => '/story/mm-401-crash-eastbound-delays',
        // Hermes: the live desk publishes on filing, clearly labelled.
        'wire_desks'         => 'live',
        'automated_byline'   => 'Monitor Newsroom Automation',
    ],

    'sources' => [
        ['CBC Toronto', 'https://www.cbc.ca/webfeed/rss/rss-canada-toronto', 'gta'],
        ['CityNews Toronto', 'https://toronto.citynews.ca/feed/', 'gta'],
    ],

    'stories' => [

        /* ------------------------------------------------------------- hero --- */
        [
            'title' => 'Mississauga unveils new waterfront vision for Lakeview',
            'slug' => 'mm-waterfront-vision-lakeview',
            'desk' => 'development', 'byline' => 'Farida Haque', 'dateline' => 'Lakeview',
            'lede' => 'A bold plan aims to transform the shoreline with more parks, housing and public space for generations to come.',
            'image' => $img('waterfront.svg'),
            'image_caption' => 'The Lakeview shoreline, looking west toward the city centre.',
            'image_credit' => 'Monitor illustration',
            'views' => 4200, 'published' => $ago('-4 hours'), 'featured' => 1, 'placement' => 'hero',
            'tags' => 'waterfront, lakeview, planning',
            'body' => $p(
                'The plan tabled Monday covers two kilometres of shoreline and thirty years of construction: a continuous waterfront trail, three new parks, and housing for twenty thousand people on land the coal plant once occupied.',
                'What council actually approved this week is smaller — the trail alignment and the first park — but the vote commits the city to the framework, and the framework is the story.',
                'The debate now moves to density. The plan assumes towers at the GO corridor and townhouses at the water, and both ends of that assumption have organized opposition.'
            ),
        ],

        /* --------------------------------------------------------- breaking --- */
        [
            'title' => 'Multi-vehicle crash on Highway 401 eastbound causes major delays in Mississauga',
            'slug' => 'mm-401-crash-eastbound-delays',
            'desk' => 'peel-region', 'byline' => 'Monitor Staff', 'dateline' => '',
            'lede' => 'The collectors are closed between Mississauga Road and Hurontario. OPP report no life-threatening injuries.',
            'views' => 6100, 'published' => $ago('-2 hours'),
            'tags' => 'highway 401, traffic',
            'body' => $p(
                'Four vehicles collided in the eastbound collectors shortly after 6 a.m., closing two lanes through the morning peak.',
                'OPP expect the lanes to reopen by early afternoon. Delays extend back to the 403 interchange.',
                'This story will be updated.'
            ),
        ],

        /* --------------------------------------------------- featured story --- */
        [
            'title' => 'Hurontario LRT crews will close the Dundas crossing for six weeks',
            'slug' => 'mm-lrt-dundas-crossing-closure',
            'desk' => 'transit', 'byline' => 'Amrita Sandhu', 'dateline' => 'Cooksville',
            'lede' => 'Metrolinx says 11,000 daily MiWay riders will be detoured onto Dundas Street buses through September as track work reaches the city centre.',
            'image' => $img('lrt.svg'),
            'image_caption' => 'The Hurontario line under construction at the Dundas crossing.',
            'image_credit' => 'Monitor illustration',
            'placement' => 'featured',
            'views' => 3300, 'published' => $ago('-6 hours'),
            'tags' => 'hurontario lrt, miway, metrolinx',
            'body' => $p(
                'The closure begins Monday and is the longest single disruption of the project so far. Metrolinx says it is also the last one at a major intersection.',
                'MiWay will detour four routes onto Dundas Street replacement buses, adding an estimated nine minutes to the average trip through Cooksville.',
                'The agency\'s latest schedule still shows revenue service in late 2027 — a date the city\'s own transit staff described to council in June as "ambitious".'
            ),
        ],

        /* -------------------------------------------------------- city hall --- */
        [
            'title' => 'Budget 2027: the 4.2% ask, line by line',
            'slug' => 'mm-budget-2027-line-by-line',
            'desk' => 'city-hall', 'byline' => 'Grace Lim', 'dateline' => '',
            'lede' => 'Transit takes the largest share of the proposed increase, followed by winter maintenance and a new stormwater reserve. We read all 412 pages.',
            'views' => 2900, 'published' => $ago('-3 hours'),
            'tags' => 'budget, taxes, council',
            'body' => $p(
                'The proposed all-in increase on the residential bill is 4.2 per cent — about $196 for the average detached home.',
                'A third of it is transit: the MiWay envelope grows to hold two new all-night routes and the operating reserve for the LRT\'s first year.',
                'Winter maintenance, which ran 34 per cent over budget last season, takes the next share, and the remainder seeds a stormwater reserve the auditor has recommended since 2023.'
            )
            . $q('We are not going to tax our way out of a housing shortage. Every dollar we add to the levy is a dollar that does not go into a unit.', 'Councillor Priya Nair, Ward 7, at budget committee'),
        ],
        [
            'title' => 'Your stormwater charge is going up. Here is what the new bill looks like.',
            'slug' => 'mm-stormwater-charge-2027',
            'desk' => 'city-hall', 'byline' => 'Grace Lim', 'dateline' => '',
            'lede' => 'A detached home on a 50-foot lot pays $8.40 more a year starting in January.',
            'views' => 1400, 'published' => $ago('-1 day'),
            'tags' => 'stormwater, budget',
            'body' => $p(
                'The charge is billed by roof and pavement area, not property value, so the increase lands hardest on the city\'s biggest parking lots — which is the point.',
                'The new revenue funds the reserve created in this budget, earmarked for the culverts the 2024 storm exposed as undersized.'
            ),
        ],
        [
            'title' => "Mississauga's lobbyist registry has 41 new entries this year. Most are developers.",
            'slug' => 'mm-lobbyist-registry-41-entries',
            'desk' => 'city-hall', 'byline' => 'Daniel Okonkwo', 'dateline' => 'Ward-wide',
            'lede' => 'We matched every registration against the applications now before council.',
            'views' => 1900, 'published' => $ago('-2 days'),
            'tags' => 'lobbying, transparency, development',
            'body' => $p(
                'Thirty-one of the forty-one registrations name a planning file as the subject matter, and nineteen of those files come to committee this fall.',
                'The registry is self-reported and unaudited. The clerk\'s office confirmed no registration has ever been refused.'
            ),
        ],
        [
            'title' => 'Council will vote on ward boundaries in October. Malton stands to lose a seat.',
            'slug' => 'mm-ward-boundaries-october-vote',
            'desk' => 'city-hall', 'byline' => 'Grace Lim', 'dateline' => 'Ward 5',
            'lede' => 'Three of the four consultant options split the community between wards.',
            'views' => 1100, 'published' => $ago('-5 days'),
            'tags' => 'wards, elections, malton',
            'body' => $p(
                'The review is driven by growth in the city centre, where one councillor now represents nearly twice the residents of the smallest ward.',
                'Every rebalancing option pulls a boundary through the northeast, and three of the four run it through Malton — the community with the city\'s longest-standing complaint about representation.'
            ),
        ],

        /* ------------------------------------------------------ development --- */
        [
            'title' => 'Council backs 38 storeys at Rathburn and Kariya, over staff objections',
            'slug' => 'mm-38-storeys-rathburn-kariya',
            'desk' => 'development', 'byline' => 'Daniel Okonkwo', 'dateline' => 'Ward 7',
            'lede' => 'Planners asked for 29. Six councillors said the shadow study was the only thing standing between the site and 340 rental units — and voted the tower up.',
            'image' => $img('tower.svg'),
            'image_caption' => 'The surface lot at Rathburn Road East and Kariya Drive, approved Monday for a 38-storey rental tower.',
            'image_credit' => 'Monitor illustration',
            'views' => 2500, 'published' => $ago('-8 hours'),
            'tags' => 'development, ward 7, housing',
            'body' => $p(
                'The tower at the corner of Rathburn Road East and Kariya Drive will rise nine storeys higher than city planners recommended, after council voted 8-3 on Monday to approve the application as submitted.',
                'Staff had asked the developer to cut the building to 29 storeys, citing shadow impacts on the community park immediately north of the site. The applicant declined, and brought a revised shadow study showing the park would lose direct sun for 40 minutes on the spring equinox.'
            )
            . $q('Forty minutes of shade in March is not a reason to build 90 fewer homes in a city that is short 12,000 of them.', 'Councillor Priya Nair, Ward 7, before the vote')
            . $p(
                'The Ward 1, 2 and 11 members opposed the motion, saying the decision sets aside the district plan council itself adopted eighteen months ago.'
            ),
        ],

        /* ---------------------------------------------------------- transit --- */
        [
            'title' => 'MiWay adds a second all-night route on Derry Road',
            'slug' => 'mm-miway-all-night-derry',
            'desk' => 'transit', 'byline' => 'Amrita Sandhu', 'dateline' => '',
            'lede' => 'The 24-hour network doubles to two routes, connecting the airport employment lands to Malton and the city centre.',
            'views' => 900, 'published' => $ago('-2 days 4 hours'),
            'tags' => 'miway, malton',
            'body' => $p(
                'The route follows Derry from the airport lands to Westwood Square, on 40-minute headways overnight.',
                'The first all-night route, on Hurontario, carried three times its ridership projection in its first year — most of it shift workers.'
            ),
        ],
        [
            'title' => 'The GO fare change nobody announced, and what it does to a Milton commute',
            'slug' => 'mm-go-fare-change-milton',
            'desk' => 'transit', 'byline' => 'Amrita Sandhu', 'dateline' => '',
            'lede' => 'A quiet zone-boundary adjustment adds 90 cents each way for riders boarding at Meadowvale.',
            'views' => 1300, 'published' => $ago('-6 days'),
            'tags' => 'go transit, fares',
            'body' => $p(
                'The change appeared in a fare table updated in July, with no release and no board item. Metrolinx confirmed it when we asked.',
                'For a five-day commuter it is about $450 a year — roughly the cost of the discounted monthly pass the agency retired in 2024.'
            ),
        ],

        /* ------------------------------------------------------------ courts --- */
        [
            'title' => 'Peel courthouse backlog hits 14 months for a two-day trial',
            'slug' => 'mm-peel-courthouse-backlog',
            'desk' => 'courts', 'byline' => 'Farida Haque', 'dateline' => 'Brampton',
            'lede' => 'The region\'s only courthouse is scheduling civil trials into late 2027, the longest wait in the province.',
            'views' => 800, 'published' => $ago('-3 days'),
            'tags' => 'courts, peel',
            'body' => $p(
                'The backlog is worst on the civil side, where a two-day trial booked today gets a date fourteen months out.',
                'The attorney general\'s office says two additional courtrooms open next year. The bar association points out the same promise was made in 2023.'
            ),
        ],

        /* ---------------------------------------------------------- business --- */
        [
            'title' => 'Two Sheridan spin-offs take the last space on Kennedy Road',
            'slug' => 'mm-sheridan-spinoffs-kennedy-road',
            'desk' => 'business', 'byline' => 'Daniel Okonkwo', 'dateline' => '',
            'lede' => 'Lease rates in the northeast employment lands are up 31 per cent.',
            'views' => 700, 'published' => $ago('-1 day 6 hours'),
            'tags' => 'business, employment lands',
            'body' => $p(
                'The two companies — one in battery testing, one in food-safety robotics — take the final units in a strip that sat a third empty in 2023.',
                'The vacancy story has flipped in eighteen months: the planning department now lists conversion pressure, not emptiness, as the district\'s risk.'
            ),
        ],

        /* -------------------------------------------------------- peel region --- */
        [
            'title' => 'New express bus service launches in Brampton',
            'slug' => 'mm-brampton-express-bus',
            'desk' => 'peel-region', 'byline' => 'Amrita Sandhu', 'dateline' => 'Brampton',
            'lede' => 'Metrolinx introduces improved connections across Peel Region.',
            'views' => 600, 'published' => $ago('-10 hours'),
            'tags' => 'brampton, transit',
            'body' => $p(
                'The route links Bramalea GO to the airport employment lands, on headways that finally make the transfer from MiWay\'s Malton routes practical.',
                'It is the first of four regional express corridors in the province\'s Peel service plan; the other three have no funding attached yet.'
            ),
        ],

        /* ------------------------------------------------------- environment --- */
        [
            'title' => 'Credit Valley tree canopy grows for the first time since 2016',
            'slug' => 'mm-credit-valley-canopy-grows',
            'desk' => 'environment', 'byline' => 'Farida Haque', 'dateline' => '',
            'lede' => 'The conservation authority\'s ten-year survey shows canopy cover up 1.2 points, driven by the floodplain plantings after the 2018 storm.',
            'views' => 1000, 'published' => $ago('-12 hours'),
            'tags' => 'trees, credit river, climate',
            'body' => $p(
                'The gain is concentrated along the river valley, where the authority has planted since 2019. Street canopy in the older neighbourhoods is still shrinking.',
                'The city\'s forestry budget doubles under the 2027 proposal — one of the quieter lines in the 4.2 per cent.'
            ),
        ],
        [
            'title' => 'New park opens on the Etobicoke Creek trail',
            'slug' => 'mm-etobicoke-creek-park-opens',
            'desk' => 'environment', 'byline' => 'Monitor Staff', 'dateline' => '',
            'lede' => 'Nine hectares of restored floodplain, and a bridge two cities argued over.',
            'image' => $img('creek.svg'),
            'image_caption' => 'The new crossing over Etobicoke Creek, which took Mississauga and Toronto eleven years to agree on.',
            'image_credit' => 'Monitor illustration',
            'views' => 850, 'published' => $ago('-2 days 8 hours'),
            'tags' => 'parks, etobicoke creek',
            'body' => $p(
                'The park completes the trail link between Burnhamthorpe and the lake, and the bridge — split down the municipal boundary — took longer to negotiate than to build.',
                'The floodplain restoration is doing its job early: the July storm pooled where the engineers drew it, and nowhere else.'
            ),
        ],

        /* ------------------------------------------------------- communities --- */
        [
            'title' => 'Free summer concerts return to Celebration Square',
            'slug' => 'mm-celebration-square-concerts',
            'desk' => 'communities', 'byline' => 'Monitor Staff', 'dateline' => 'City Centre',
            'lede' => 'Eleven weekends of free programming, from Bollywood nights to the symphony under the screen.',
            'views' => 500, 'published' => $ago('-3 days 6 hours'),
            'tags' => 'events, celebration square',
            'body' => $p(
                'The season opens Friday with the Filipino heritage festival and closes Labour Day weekend with the fireworks the square skipped last year for construction.',
                'Every event is free; the city\'s culture division says attendance last summer topped 400,000 across the season.'
            ),
        ],
        [
            'title' => "Malton's newcomer clinic is booked to November",
            'slug' => 'mm-malton-newcomer-clinic',
            'desk' => 'communities', 'byline' => 'Farida Haque', 'dateline' => 'Ward 5',
            'lede' => 'The volunteer-run health clinic has a four-month waitlist, and its lease runs out in January.',
            'views' => 1200, 'published' => $ago('-1 day 2 hours'),
            'tags' => 'malton, health, newcomers',
            'body' => $p(
                'The clinic sees patients without OHIP coverage — most of them newly arrived — and demand has tripled since 2024.',
                'Eleven residents are registered to ask council to fund the lease directly at Thursday\'s budget delegations.'
            ),
        ],

        /* -------------------------------------------------------------- live --- */
        [
            'title' => 'Live: budget committee, day two',
            'slug' => 'mm-live-budget-committee-day-two',
            'desk' => 'live', 'byline' => 'Grace Lim and Amrita Sandhu', 'dateline' => '300 City Centre Drive',
            'lede' => 'Councillors debate a 4.2% tax increase as the transit budget carries 8-3. Follow the vote here.',
            'views' => 5200, 'published' => $ago('-1 hour'),
            'tags' => 'live, budget, council',
            'body' => $p(
                '11:42 a.m. — Transit budget carries, 8-3. The MiWay operating envelope passes with the two new all-night routes intact.',
                '11:05 a.m. — Nair: "We are not going to tax our way out of a housing shortage." The Ward 7 councillor will bring an amendment this afternoon to hold the increase at 3.6 per cent.',
                '10:15 a.m. — Staff table a $19M shortfall, most of it winter maintenance, which ran 34 per cent over budget last season.',
                '9:12 a.m. — Public delegations open with the Malton clinic; the first three speakers ask council to fund its lease directly.'
            ),
        ],
    ],
];
