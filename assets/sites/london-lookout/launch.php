<?php
/**
 * The London Lookout — launch pack.
 * Seeded once by `PP_SITE=london-lookout php tools/seed-launch.php`.
 *
 * DEMONSTRATION CONTENT. These twenty-one stories exist to show the design
 * — the lead with its council tracker, the open-files band, the desk
 * columns, the accountability furniture on an article — and their copy
 * follows the brand book's voice section: what happened, who decided,
 * the document, the vote count, the dollar figure. They are
 * illustrative, not journalism; the newsroom replaces them.
 *
 * Every slug is explicit and prefixed `ll-`: posts.slug is UNIQUE
 * across the whole shared database and the seeder fails loudly on a
 * collision. ALL SIX desks this paper uses already exist network-wide
 * (local-news, city-hall, business, events, sports, opinion), so a
 * correct run prints NO "desk added" lines — the pack lists them anyway
 * so it stands alone against an empty database.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$q = fn (string $quote, string $who) => '<blockquote><p>' . $quote . '</p><cite>' . $who . '</cite></blockquote>';
$img = fn (string $f) => '/assets/sites/london-lookout/img/' . $f;

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['londonlookout.com', 'www.londonlookout.com'],

    'desks' => [
        ['name' => 'Local News', 'slug' => 'local-news', 'color' => '#262A60', 'description' => 'Neighbourhoods, schools, transit and what happened overnight in London.'],
        ['name' => 'City Hall',  'slug' => 'city-hall',  'color' => '#262A60', 'description' => 'Council, committees, the budget and the paperwork behind them. A Lookout reporter is in the chamber every Tuesday night.'],
        ['name' => 'Business & Markets', 'slug' => 'business', 'color' => '#262A60', 'description' => 'Employers, storefronts and the local economy.'],
        ['name' => 'Events',     'slug' => 'events',     'color' => '#262A60', 'description' => 'What is on this week, from the bandshell to the committee room.'],
        ['name' => 'Sports',     'slug' => 'sports',     'color' => '#262A60', 'description' => 'The Knights, the Mustangs, and who pays for the ice.'],
        ['name' => 'Opinion',    'slug' => 'opinion',    'color' => '#262A60', 'description' => 'Columns and letters, always labelled. Editorial positions are the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'The London Lookout',
        'tagline'            => 'Eyes on the Forest City',
        'meta_description'   => 'An independent accountability newsroom for London, Ontario — city hall held to account, and a daily digest of everything else.',
        'footer_line'        => 'Independent accountability reporting in London, Ontario. No paywall, funded by readers.',
        'contact_email'      => 'tips@londonlookout.com',
        'newsletter_heading' => 'The Daily Lookout',
        'newsletter_copy'    => 'One email with what London decided yesterday, what it means for your street, and what is on the agenda today. Four minutes, no filler.',
        'weather_line'       => '18°C|Forest City',
        'regions'            => json_encode([
            'london'  => 'London',
            'ontario' => 'Ontario',
            'canada'  => 'Canada',
        ]),

        /* The council tracker and open-files panels (site canvas §isHome).
           Newsroom-editable in Settings; each panel disappears when blank. */
        'council_meeting'  => '26 Aug meeting',
        'council_tracker'  => json_encode([
            ['state' => 'Deferred', 'item' => 'Wellington bus corridor, phase 3'],
            ['state' => 'Passed',   'item' => 'Vacant unit tax, 1% for 2027'],
            ['state' => 'Passed',   'item' => 'Old East Village heritage overlay'],
            ['state' => 'In camera', 'item' => 'Land acquisition, Ward 4'],
        ]),
        'open_files' => json_encode([
            ['tag' => 'Day 41', 'status' => 'FOI pending', 'title' => 'What did the city pay for the Adelaide land assembly?', 'note' => 'Request filed 17 July. Deadline extended once, to 12 September.'],
            ['tag' => 'Day 12', 'status' => 'Awaiting comment', 'title' => 'Who signed off on the shelter contract extension?', 'note' => 'Four questions sent to the city manager\'s office. None answered.'],
            ['tag' => 'Answered', 'closed' => true, 'status' => 'Closed 22 Aug', 'title' => 'The real cost of the Thames Valley Parkway repairs', 'note' => '$4.1M, up from the $2.6M presented to council in March.'],
        ]),
        'council_agenda' => json_encode([
            ['what' => 'Budget committee', 'when' => 'Tue 2 Sep, 09:30 · Committee room 1'],
            ['what' => 'Planning & environment', 'when' => 'Mon 8 Sep, 16:00 · Chamber'],
            ['what' => 'Council', 'when' => 'Tue 9 Sep, 13:00 · Chamber'],
        ]),

        /* Membership band. Figures are illustrative until the newsroom
           connects real numbers; the panel hides when member_pitch is blank. */
        'member_pitch'     => 'The Lookout is funded by readers in London. Members keep the record open.',
        'member_copy'      => 'Freedom-of-information requests, court transcripts and a reporter in the council chamber every Tuesday night. No paywall — the reporting stays free for everyone in the city.',
        'member_count'     => '1240',
        'member_goal'      => '2000',
        'member_goal_note' => 'Funding at 2,000 members covers a second full-time city hall reporter and the annual FOI budget.',

        /* Hermes-ready when the owner orders a token: drafts only — this
           paper declares no wire desk. The byline names the newsroom's
           automation, never a person (brand §08: we say how we know). */
        'automated_byline' => 'Lookout Newsroom Automation',
    ],

    'sources' => [
        ['CBC London', 'https://www.cbc.ca/webfeed/rss/rss-canada-london', 'london'],
        ['CBC Toronto', 'https://www.cbc.ca/webfeed/rss/rss-canada-toronto', 'ontario'],
    ],

    'stories' => [

        /* ------------------------------------------------------------- hero --- */
        [
            'title' => 'Two years of missed inspections at London\'s largest landlord',
            'slug' => 'll-missed-inspections-adelaide-portfolio',
            'desk' => 'city-hall', 'byline' => 'Marisol Kanter', 'dateline' => 'Housing enforcement',
            'lede' => 'The city says the backlog is a staffing problem. Records obtained by the Lookout show 61 open complaints on one Adelaide Street portfolio, and a file that sat unassigned for eleven months.',
            'image' => $img('documents.svg'),
            'image_caption' => 'The city released 214 property-standards records covering four buildings.',
            'image_credit' => 'The London Lookout',
            'views' => 5400, 'published' => $ago('-4 hours'), 'featured' => 1, 'placement' => 'hero',
            'tags' => 'housing, property standards, foi',
            'body' => $p(
                'A tenant in a 340-unit Adelaide Street North portfolio first reported a failing heating riser in November 2023. The complaint was logged, assigned a file number, and then, according to the city\'s own tracking data, touched again eleven months later.',
                'The Lookout requested every property-standards complaint filed against the portfolio\'s four buildings since January 2023. The city released 214 records. Sixty-one remain open. Nineteen have passed the 180-day mark the city\'s service standard sets for a first inspection.',
                'Asked about the delays, a spokesperson for the enforcement division said vacancies in the inspection team had created a backlog and that files are triaged by severity. The division has three of its seven inspector positions unfilled.'
            ) . $q(
                'Triage is a reasonable answer for a week. It is not an answer for eleven months.',
                'Coun. A. Deshpande, chair of the community and protective services committee'
            ) . $p(
                'Committee minutes from March show the division asked for two additional positions in the 2026 budget. The request was not funded. The same minutes record staff telling councillors the existing complement was &ldquo;sufficient to meet current demand&rdquo;.',
                'The portfolio\'s owner did not respond to four requests for comment over twelve days. A property manager reached by phone said maintenance requests are handled &ldquo;as they come in&rdquo; and declined to answer questions about the open files.',
                'The full dataset behind this story is published alongside it. We will update this file when the enforcement division answers the remaining three questions.'
            ),
        ],

        /* ------------------------------------------------------ the rail three -- */
        [
            'title' => 'Council votes 11–4 to shelve the Wellington bus corridor',
            'slug' => 'll-council-shelves-wellington-corridor',
            'desk' => 'city-hall', 'byline' => 'Marisol Kanter', 'dateline' => 'Council · 26 August',
            'lede' => 'Phase 3 goes back to staff for a fourth review. Two councillors who voted to defer told the Lookout they expect it to return after the 2026 election.',
            'image' => $img('chamber.svg'),
            'image_caption' => 'Councillors debate the corridor motion during a meeting that ran past midnight.',
            'image_credit' => 'The London Lookout',
            'views' => 4800, 'published' => $ago('-8 hours'), 'placement' => 'featured',
            'tags' => 'transit, council, wellington',
            'body' => $p(
                'Council voted 11–4 on Tuesday night to send phase 3 of the Wellington bus corridor back to staff, the fourth review of the same alignment since 2019.',
                'The motion asks for a report on &ldquo;alternative configurations&rdquo; by spring, which two councillors who supported the deferral acknowledged to the Lookout means after the October 2026 municipal election.',
                'The four votes against the deferral came from the three downtown wards and Ward 13. Coun. Deshpande, who voted against, said the city has now spent $1.9 million on consultant reviews of a route that has not moved a metre.'
            ),
        ],
        [
            'title' => 'Dundas Place restaurants say the patio rules cost them a summer',
            'slug' => 'll-dundas-place-patio-rules',
            'desk' => 'business', 'byline' => 'Ellis Bhandari', 'dateline' => 'Dundas Place',
            'lede' => 'Eleven operators applied under the new encroachment bylaw. Four were approved before July, and the season is the season.',
            'views' => 2100, 'published' => $ago('-11 hours'),
            'tags' => 'downtown, business, bylaw',
            'body' => $p(
                'The city rewrote its patio encroachment rules in February, adding a fire-route clearance and a per-square-metre fee. Eleven Dundas Place operators applied. Four were approved before July.',
                'Licensing staff say the applications arrived incomplete and that the review time — a median of 39 days — is within the service standard. Operators say the standard was written for a permit nobody needed in the summer.',
                'The bylaw returns to committee in November for its scheduled first-year review.'
            ),
        ],
        [
            'title' => 'LTC ridership passes pre-2020 levels for the first time',
            'slug' => 'll-ltc-ridership-passes-2019',
            'desk' => 'local-news', 'byline' => 'Dana Okonjo', 'dateline' => 'Transit',
            'lede' => 'The commission counted 1.94 million trips in July, three per cent above the same month in 2019 — and the fleet has eleven fewer buses.',
            'views' => 1700, 'published' => $ago('-14 hours'),
            'tags' => 'ltc, transit, ridership',
            'body' => $p(
                'London Transit counted 1.94 million boardings in July, the first month since the pandemic to pass its 2019 figure for the same period.',
                'The commission credits the university and college terms and the free-transfer window introduced last year. Its own service report notes the gain has come on a fleet eleven buses smaller than in 2019, with the difference absorbed by longer headways on six routes.',
                'The 2027 budget request asks for nine replacement buses and two additional operators.'
            ),
        ],

        /* --------------------------------------------------------- local news --- */
        [
            'title' => 'Boil-water advisory lifted in Byron after four days',
            'slug' => 'll-byron-boil-water-lifted',
            'desk' => 'local-news', 'byline' => 'Dana Okonjo', 'dateline' => 'Byron',
            'lede' => 'The city has not said what caused the pressure loss, and residents are still asking who pays for the bottled water.',
            'image' => $img('street.svg'),
            'image_caption' => 'Crews worked the Commissioners Road main for three nights.',
            'image_credit' => 'The London Lookout',
            'views' => 3900, 'published' => $ago('-1 day'),
            'tags' => 'water, byron, utilities',
            'body' => $p(
                'The advisory covering roughly 1,900 Byron households was lifted Thursday after two consecutive clear sample sets.',
                'The city has not released the cause of the pressure loss that triggered it. A spokesperson said the investigation is &ldquo;ongoing&rdquo; and that a summary will go to the civic works committee in September.',
                'Residents who asked about reimbursement for bottled water were told the city has no compensation program for advisories under seven days. The Lookout has asked how many such advisories the city has issued since 2020.'
            ),
        ],
        [
            'title' => 'Fanshawe adds 400 student beds, and a parking fight',
            'slug' => 'll-fanshawe-400-beds-parking',
            'desk' => 'local-news', 'byline' => 'Dana Okonjo', 'dateline' => 'Ward 4',
            'lede' => 'The residence clears planning committee 6–2. The neighbourhood association says the parking study counted spaces that are already leased.',
            'views' => 1400, 'published' => $ago('-1 day 5 hours'),
            'tags' => 'housing, fanshawe, planning',
            'body' => $p(
                'Planning committee approved a 400-bed student residence on Oxford Street East by a 6–2 vote, sending it to council on 9 September.',
                'The applicant\'s parking study counted 240 nearby spaces as available. The neighbourhood association told the committee that 148 of them are leased to a hospital shuttle operator on a five-year term.',
                'City planning staff said the study met the terms of reference. The terms of reference do not require lease status to be checked.'
            ),
        ],
        [
            'title' => 'Ward 6 gets its crossing after nine years of asking',
            'slug' => 'll-ward-6-crossing-nine-years',
            'desk' => 'local-news', 'byline' => 'Dana Okonjo', 'dateline' => 'Ward 6',
            'lede' => 'The pedestrian signal at Farnham and Cranbrook was first requested in 2017. It will be installed this autumn, at 4.4 times the original estimate.',
            'views' => 1250, 'published' => $ago('-2 days'),
            'tags' => 'roads, safety',
            'body' => $p(
                'A pedestrian signal first requested by the Ward 6 councillor in 2017 will be installed at Farnham and Cranbrook before the end of October.',
                'The 2017 estimate was $86,000. The tendered price is $378,000. Transportation staff attribute the difference to a new traffic-signal standard, utility relocation, and eight years of construction inflation.',
                'Three collisions involving pedestrians have been recorded at the intersection since the request was filed.'
            ),
        ],
        [
            'title' => 'Overnight closures on Highbury start Monday',
            'slug' => 'll-highbury-overnight-closures',
            'desk' => 'local-news', 'byline' => 'Lookout Staff', 'dateline' => 'Roads',
            'lede' => 'Highbury Avenue closes between Oxford and Huron overnight for three weeks of resurfacing. The detour is signed via Clarke Road.',
            'views' => 900, 'published' => $ago('-2 days 8 hours'),
            'tags' => 'roads, closures',
            'body' => $p(
                'Highbury Avenue North will close between Oxford Street East and Huron Street from 9 p.m. to 5 a.m., Monday to Thursday, for three weeks beginning Monday.',
                'The city has signed the detour via Clarke Road. Transit routes 4 and 20 will run their published detour after 9 p.m.',
                'The work is the second phase of a resurfacing contract awarded in April.'
            ),
        ],

        /* ------------------------------------------------------------ events --- */
        [
            'title' => 'Sunfest closing night at Victoria Park',
            'slug' => 'll-sunfest-closing-night',
            'desk' => 'events', 'byline' => 'Lookout Staff', 'dateline' => '29 Aug · 6:00 pm · Free · Victoria Park bandshell',
            'lede' => 'The festival closes with three stages running to 11 p.m. Bandshell seating is first come; the west lot is closed to parking.',
            'views' => 1100, 'published' => $ago('-6 hours'),
            'tags' => 'sunfest, festivals',
            'body' => $p(
                'Sunfest\'s closing night runs three stages to 11 p.m., with the bandshell programme starting at 6.',
                'Victoria Park\'s west lot is closed to parking for the duration. The city is running free shuttles from the Richmond Row garages until midnight.'
            ),
        ],
        [
            'title' => 'Covent Garden night market',
            'slug' => 'll-covent-garden-night-market',
            'desk' => 'events', 'byline' => 'Lookout Staff', 'dateline' => '30 Aug · 5:00 pm · Free entry · King Street',
            'lede' => 'Forty vendors on King Street, weather permitting. The market runs the last Saturday of the month through October.',
            'views' => 700, 'published' => $ago('-1 day 4 hours'),
            'tags' => 'market, downtown',
            'body' => $p(
                'The night market returns to King Street with roughly forty vendors, running 5 to 10 p.m.',
                'King Street closes to traffic between Talbot and Richmond from 3 p.m.'
            ),
        ],
        [
            'title' => 'Budget committee: the 2027 draft is tabled',
            'slug' => 'll-budget-committee-2027-draft',
            'desk' => 'events', 'byline' => 'Lookout Staff', 'dateline' => '2 Sep · 9:30 am · City Hall, committee room 1',
            'lede' => 'The draft operating budget lands in public for the first time. Delegations must register by 4 p.m. the previous business day.',
            'views' => 640, 'published' => $ago('-2 days 4 hours'),
            'tags' => 'budget, council',
            'body' => $p(
                'The 2027 draft operating budget is tabled at budget committee, the first time the document is public.',
                'The Lookout will publish a line-by-line reading the same afternoon. Delegation registration closes at 4 p.m. on the previous business day.'
            ),
        ],
        [
            'title' => 'Grand Theatre season launch',
            'slug' => 'll-grand-theatre-season-launch',
            'desk' => 'events', 'byline' => 'Lookout Staff', 'dateline' => '4 Sep · 7:30 pm · From $29 · Richmond Street',
            'lede' => 'The Grand opens its season with a run that includes two commissioned works from London writers.',
            'views' => 520, 'published' => $ago('-3 days 4 hours'),
            'tags' => 'theatre, culture',
            'body' => $p(
                'The Grand Theatre opens its 2026–27 season with a programme that includes two commissioned works by London writers.',
                'Tickets start at $29, with the theatre\'s pay-what-you-can preview on the second Wednesday.'
            ),
        ],

        /* ---------------------------------------------------------- business --- */
        [
            'title' => 'A third grocery co-op opens on Hamilton Road',
            'slug' => 'll-third-grocery-coop-hamilton-road',
            'desk' => 'business', 'byline' => 'Ellis Bhandari', 'dateline' => 'Hamilton Road',
            'lede' => 'Members put up $180,000. The city\'s own food-access map says the neighbourhood has waited a decade.',
            'image' => $img('street.svg'),
            'image_caption' => 'The co-op took the corner unit that had been vacant since 2021.',
            'image_credit' => 'The London Lookout',
            'views' => 2600, 'published' => $ago('-3 days'),
            'tags' => 'co-op, food, hamilton road',
            'body' => $p(
                'Four hundred and ten members raised $180,000 in shares to open a grocery co-op in a Hamilton Road unit vacant since 2021.',
                'The city\'s food-access mapping has flagged the area as underserved in every update since 2015. A municipal grant covered $40,000 of the fit-out; the rest is member capital and a credit-union loan.',
                'The co-op will open five days a week to start, with a stated goal of seven by spring.'
            ),
        ],
        [
            'title' => 'EV parts supplier confirms 210 jobs in the east end',
            'slug' => 'll-ev-parts-supplier-210-jobs',
            'desk' => 'business', 'byline' => 'Ellis Bhandari', 'dateline' => 'Ward 2',
            'lede' => 'The company has signed a fifteen-year lease. The municipal incentive attached to it has not been made public.',
            'views' => 2200, 'published' => $ago('-4 days'),
            'tags' => 'jobs, manufacturing',
            'body' => $p(
                'A parts supplier to the electric-vehicle sector confirmed 210 positions at a leased plant in the east end, with hiring to begin in the first quarter.',
                'The company signed a fifteen-year lease. The city has confirmed an incentive is attached under its industrial development programme but has not released the amount, citing commercial confidentiality.',
                'The Lookout has filed a request for the agreement.'
            ),
        ],
        [
            'title' => 'Downtown office vacancy holds at 21.4%',
            'slug' => 'll-downtown-office-vacancy-holds',
            'desk' => 'business', 'byline' => 'Ellis Bhandari', 'dateline' => 'Downtown',
            'lede' => 'The rate has not moved in three quarters. Two conversions to residential are in the planning queue.',
            'views' => 1300, 'published' => $ago('-5 days'),
            'tags' => 'downtown, real estate',
            'body' => $p(
                'Downtown office vacancy held at 21.4 per cent in the second quarter, unchanged within a rounding error for three consecutive quarters.',
                'Two buildings on Dundas have applications in for residential conversion. Both are seeking the city\'s conversion grant, which has $2.4 million uncommitted this year.'
            ),
        ],
        [
            'title' => 'Two more Richmond Row storefronts go dark',
            'slug' => 'll-richmond-row-storefronts-dark',
            'desk' => 'business', 'byline' => 'Ellis Bhandari', 'dateline' => 'Richmond Row',
            'lede' => 'Both leases ended rather than failed, the operators say. The block\'s vacancy is now six units of twenty-two.',
            'views' => 1150, 'published' => $ago('-6 days'),
            'tags' => 'retail, downtown',
            'body' => $p(
                'Two Richmond Row retailers closed at the end of August, bringing the block\'s vacancy to six units of twenty-two.',
                'Both operators told the Lookout their leases had ended and were not renewed at the offered rate, rather than the businesses failing.',
                'The business improvement area says foot traffic is up four per cent year over year.'
            ),
        ],

        /* ------------------------------------------------------------ sports --- */
        [
            'title' => 'Knights open camp with three roster spots genuinely open',
            'slug' => 'll-knights-open-camp-roster-spots',
            'desk' => 'sports', 'byline' => 'Tomas Reyner', 'dateline' => 'Budweiser Gardens',
            'lede' => 'Two overage decisions and a goaltending competition will be settled before the home opener.',
            'views' => 2400, 'published' => $ago('-2 days 3 hours'),
            'tags' => 'knights, ohl',
            'body' => $p(
                'The Knights opened camp with two overage decisions unresolved and a genuine goaltending competition, the first in four seasons.',
                'The club carried three returning defencemen. The coaching staff say the last blue-line spot is open on merit.'
            ),
        ],
        [
            'title' => 'Mustangs open at home against Queen\'s',
            'slug' => 'll-mustangs-open-home-queens',
            'desk' => 'sports', 'byline' => 'Tomas Reyner', 'dateline' => 'Western',
            'lede' => 'The season opener sold out in nine days. Kickoff is 1 p.m. Saturday at TD Stadium.',
            'views' => 1500, 'published' => $ago('-3 days 6 hours'),
            'tags' => 'mustangs, football',
            'body' => $p(
                'Western opens its season at home against Queen\'s on Saturday, a game that sold out nine days after tickets went on sale.',
                'The university has added standing-room capacity in the north end.'
            ),
        ],
        [
            'title' => 'City delays the Stoney Creek arena decision again',
            'slug' => 'll-stoney-creek-arena-delayed',
            'desk' => 'sports', 'byline' => 'Tomas Reyner', 'dateline' => 'Stoney Creek',
            'lede' => 'The twin-pad has been in the capital plan since 2018. Staff now say a site decision comes with the 2027 budget.',
            'views' => 980, 'published' => $ago('-7 days'),
            'tags' => 'arena, capital plan',
            'body' => $p(
                'A decision on the Stoney Creek twin-pad arena has been pushed to the 2027 budget cycle, the fourth delay since the project entered the capital plan in 2018.',
                'The estimate has risen from $38 million to $61 million over that period. Minor hockey associations told the Lookout they are renting ice in three municipalities.'
            ),
        ],

        /* ----------------------------------------------------------- opinion --- */
        [
            'title' => 'The corridor vote wasn\'t about buses. It was about who gets to wait.',
            'slug' => 'll-corridor-vote-who-waits',
            'desk' => 'opinion', 'byline' => 'Joelle Wray', 'dateline' => 'Column',
            'lede' => 'A fourth review is not caution. It is a decision to make one group of Londoners carry the delay while another is spared the disruption.',
            'views' => 3100, 'published' => $ago('-1 day 2 hours'),
            'tags' => 'transit, column',
            'body' => $p(
                'There is a version of Tuesday night in which council was being careful. That version requires you to believe that the fourth review of the same eleven kilometres will find something the first three missed.',
                'The people who ride the 2 and the 6 have been told to wait since 2019. The people who were promised the corridor would not touch their street have been told they were heard, four times.',
                'Deferral is not neutral. It allocates the cost of indecision, and it allocates it to the same riders every time.'
            ),
        ],
        [
            'title' => 'I have lived in Old East Village for 40 years. The overlay is not the threat.',
            'slug' => 'll-old-east-village-overlay-letter',
            'desk' => 'opinion', 'byline' => 'Renée Paquette', 'dateline' => 'Letter',
            'lede' => 'A reader responds to our coverage of the heritage overlay vote.',
            'views' => 1600, 'published' => $ago('-2 days 6 hours'),
            'tags' => 'heritage, letter',
            'body' => $p(
                'I have owned a house on Elias Street since 1986. I have watched three waves of people arrive and be told the neighbourhood is about to change beyond recognition.',
                'The overlay does not stop anyone building. It stops the demolition of the four blocks that give this place its shape. If the threat to affordability were heritage rules, we would be the cheapest neighbourhood in the city, and we are not.',
                'The threat is that nothing gets built anywhere else.'
            ),
        ],
    ],
];
