<?php
/**
 * The Kitchener Chronicle — launch pack.
 * Seeded once by `PP_SITE=kitchener-chronicle php tools/seed-launch.php`.
 *
 * DEMONSTRATION CONTENT. These nineteen stories exist to show the
 * design — the lead with its dek and sketch, the Ontario band, the
 * dated section rows — and their copy comes from the design canvas
 * itself. They are illustrative, not journalism; the newsroom replaces
 * them.
 *
 * Every slug is explicit and prefixed `kc-`: posts.slug is UNIQUE
 * across the whole shared database and the seeder fails loudly on a
 * collision. Desks local-news, politics, business, sports and culture
 * already exist network-wide and are reused (this paper labels
 * local-news "Local" in its palette.json); `ontario` is this paper's
 * one addition, in the design's Ontario green.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$q = fn (string $quote, string $who) => '<blockquote><p>' . $quote . '</p><cite>' . $who . '</cite></blockquote>';
$img = fn (string $f) => '/assets/sites/kitchener-chronicle/img/' . $f;

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['kitchenerchronicle.com', 'www.kitchenerchronicle.com'],

    'desks' => [
        ['name' => 'Local News', 'slug' => 'local-news', 'color' => '#16264D', 'description' => 'Council, the waterfront, the schools, and what happened overnight.'],
        ['name' => 'Politics',   'slug' => 'politics',   'color' => '#16264D', 'description' => 'Regional council, four city halls, and what Queen\'s Park does to all of them.'],
        ['name' => 'Business & Markets', 'slug' => 'business', 'color' => '#16264D', 'description' => 'Employers, storefronts and the local economy.'],
        ['name' => 'Sports',     'slug' => 'sports',     'color' => '#16264D', 'description' => 'The Aud, the diamonds, and everything the region plays.'],
        ['name' => 'Culture',    'slug' => 'culture',    'color' => '#16264D', 'description' => 'Stages, galleries, festivals, and the rooms that hold them.'],
        ['name' => 'Ontario',    'slug' => 'ontario',    'color' => '#1D5138', 'description' => 'Queen\'s Park, the province, and what both decide for Waterloo Region.'],
    ],

    'settings' => [
        'site_title'         => 'The Kitchener Chronicle',
        'tagline'            => 'Kitchener · Waterloo · Ontario',
        'meta_description'   => 'A regional daily of record for Kitchener–Waterloo and Ontario — council, transit, housing and the legislature, reported independently since 1909.',
        'footer_line'        => 'Published daily in Kitchener, Ontario, on the Haldimand Tract. Independent since 1909.',
        'contact_email'      => 'tips@kitchenerchronicle.com',
        'newsletter_heading' => 'The Morning Chronicle',
        'newsletter_copy'    => 'The region in six items, by 6 a.m., every weekday.',
        'weather_line'       => '21°|Cloudy, showers after 8 p.m.',
        'regions'            => json_encode([
            'waterloo' => 'Waterloo Region',
            'ontario'  => 'Ontario',
            'canada'   => 'Canada',
        ]),
        // Hermes-ready when the owner orders a token: drafts only — this
        // paper declares no wire desk.
        'automated_byline'   => 'Chronicle Newsroom',
    ],

    'sources' => [
        ['CBC Kitchener-Waterloo', 'https://www.cbc.ca/webfeed/rss/rss-canada-kitchenerwaterloo', 'waterloo'],
        // CBC Toronto is `gta`, claimed by the Bulletin's pack — one URL
        // fills one bucket, so listing it for `ontario` did nothing.
        ['The London Free Press', 'https://www.lfpress.com/feed', 'ontario'],
    ],

    'stories' => [

        /* ------------------------------------------------------------- hero --- */
        [
            'title' => 'Region approves $1.9-billion plan for a second ION line',
            'slug' => 'kc-ion-stage-two-approved',
            'desk' => 'local-news', 'byline' => 'Marta Iqbal, Transit reporter', 'dateline' => 'Regional headquarters',
            'lede' => 'Council\'s 12–4 vote sends the Cambridge extension to Queen\'s Park, where a funding decision is expected before the legislature rises in December.',
            'image' => $img('skyline.svg'),
            'image_caption' => 'The proposed alignment runs from Fairway Station south along the Grand River.',
            'image_credit' => 'Chronicle graphics',
            'views' => 5200, 'published' => $ago('-3 hours'), 'featured' => 1, 'placement' => 'hero',
            'tags' => 'ion, transit, cambridge',
            'body' => $p(
                'Regional council voted 12–4 on Tuesday night to approve the second stage of the ION light rail line, committing the Region of Waterloo to a $1.9-billion project that will not carry a passenger before 2034.',
                'The vote came after four hours of delegations, most of them in favour. What the room agreed on was the route. What it did not agree on was the money.',
                'Under the funding split approved with the plan, the region carries 40 per cent of the capital cost, with the province and the federal government each covering 30. Neither has committed. Regional chair Karen Bhatt said she expects a provincial answer &ldquo;in this session, not the next one.&rdquo;'
            ) . $q(
                'We have designed this thing twice already. What we have not done is pay for it.',
                'Coun. Priya Ellison, Cambridge'
            ) . $p(
                'The four dissenting votes came from three Cambridge councillors and one from Woolwich. Their objection was the debt schedule, which under current projections takes the region past its self-imposed borrowing ceiling in 2031.',
                'Staff argue the ceiling is a policy, not a law, and that the alternative — deferring construction to the next decade — adds an estimated $600 million in escalation.',
                'Construction on the first section, between Fairway and Preston, would begin in 2029 if provincial money arrives on the schedule staff have assumed. A decision on the maintenance facility site has been deferred to the autumn.'
            ),
        ],

        /* ------------------------------------------------- featured pair ------ */
        [
            'title' => 'Waterloo\'s rental vacancy rate falls to 1.2 per cent, the lowest since 2019',
            'slug' => 'kc-rental-vacancy-lowest-since-2019',
            'image' => $img('rowhouses.svg'),
            'image_caption' => 'Completions fell by a third while the region added 4,100 residents.',
            'image_credit' => 'Chronicle graphics',
            'desk' => 'local-news', 'byline' => 'Devon Marsh', 'dateline' => 'Waterloo',
            'lede' => 'Student demand is only part of it, CMHC says. Completions in the city fell by a third year over year while the region added 4,100 residents.',
            'views' => 2900, 'published' => $ago('-5 hours'), 'placement' => 'featured',
            'tags' => 'housing, rental, cmhc',
            'body' => $p(
                'The fall survey puts the citywide vacancy rate at 1.2 per cent, down from 1.9 a year ago and the tightest market the agency has measured here since 2019.',
                'The squeeze is not where the towers are. Vacancy near the universities held at 2.4 per cent; in the family-sized stock west of Westmount it fell below one.',
                'Average asking rent for a two-bedroom crossed $2,100 for the first time. The region\'s housing dashboard counts 4,600 purpose-built rentals under construction, most of them a year or more from occupancy.'
            ),
        ],
        [
            'title' => 'Provincial housing bill would override Kitchener\'s own zoning reforms, planners warn',
            'slug' => 'kc-bill-34-overrides-zoning-reforms',
            'image' => $img('legislature.svg'),
            'image_caption' => 'Queen\'s Park, where Bill 34 was tabled last week.',
            'image_credit' => 'Chronicle graphics',
            'desk' => 'ontario', 'byline' => 'Alice Renwick', 'dateline' => 'Queen\'s Park',
            'lede' => 'The city spent two years legalising fourplexes citywide. Bill 34 would replace that framework with a provincial standard by spring.',
            'views' => 2600, 'published' => $ago('-7 hours'), 'placement' => 'featured',
            'tags' => 'bill 34, zoning, housing',
            'body' => $p(
                'Kitchener legalised fourplexes on every residential lot in 2024, ahead of every other Ontario city its size. Bill 34, tabled last week, would replace municipal frameworks like it with a single provincial standard — and the standard is narrower.',
                'City planners told council\'s planning committee the bill would remove the city\'s parking-minimum exemptions and its height permissions on transit corridors, both of which go further than the province proposes.',
                'The ministry says the bill sets a floor, not a ceiling. The city\'s solicitor reads section 12 differently, and so does the association representing Ontario\'s planners.'
            ),
        ],

        /* --------------------------------------------------------- the trio --- */
        [
            'title' => 'A Waterloo chipmaker\'s quiet bet on defence contracts',
            'slug' => 'kc-chipmaker-defence-contracts',
            'image' => $img('corridor.svg'),
            'image_caption' => 'The Northfield Drive fab, quietly expanding.',
            'image_credit' => 'Chronicle graphics',
            'desk' => 'business', 'byline' => 'Nadia Brunner', 'dateline' => 'Waterloo',
            'lede' => 'Four of its six largest customers are now governments.',
            'views' => 1800, 'published' => $ago('-9 hours'),
            'tags' => 'technology, defence',
            'body' => $p(
                'The company does not use the word defence in its investor materials. Its filings do: four of its six largest customers are now governments or prime contractors, up from one in 2023.',
                'The pivot tracks a hiring pattern — forty new positions this year requiring security clearances — and a quiet expansion of the Northfield Drive fab.',
                'Analysts who cover the company say the margin story is obvious. The question employees raise, in forums the company monitors, is what the company is now for.'
            ),
        ],
        [
            'title' => 'Rangers open at the Aud with a new captain and an old problem',
            'slug' => 'kc-rangers-open-at-the-aud',
            'image' => $img('arena.svg'),
            'image_caption' => 'The Aud, entering its seventy-fifth season.',
            'image_credit' => 'Chronicle graphics',
            'desk' => 'sports', 'byline' => 'Sam Okafor', 'dateline' => 'The Aud',
            'lede' => 'The blue line lost three regulars to graduation.',
            'views' => 1500, 'published' => $ago('-11 hours'),
            'tags' => 'rangers, ohl',
            'body' => $p(
                'The Rangers open the season Friday with a nineteen-year-old captain, a rookie goaltender, and a defence corps that lost three of its top four to graduation and the import draft.',
                'The coaching staff spent camp auditioning overagers for the right side. None separated himself, which is why Friday\'s lineup card matters more than most openers.',
                'The Aud, for its part, enters its seventy-fifth season with a new score clock and the same organist.'
            ),
        ],
        [
            'title' => 'Twelve hours at the Kitchener Market, from first bread to closing bell',
            'slug' => 'kc-twelve-hours-kitchener-market',
            'desk' => 'culture', 'byline' => 'Ellen Vogt', 'dateline' => 'Kitchener Market',
            'lede' => 'Ninety vendors, one loading dock, and a Saturday.',
            'image' => $img('market.svg'),
            'image_caption' => 'The market hall on a Saturday, ninety vendors deep.',
            'image_credit' => 'Chronicle graphics',
            'views' => 1400, 'published' => $ago('-13 hours'),
            'tags' => 'market, food',
            'body' => $p(
                'The first bread comes off the truck at 4:40 a.m., and the man carrying it has done so every Saturday since 1998. The Chronicle spent a full market day on the floor, the dock and the mezzanine.',
                'Ninety vendors share one loading dock, a spreadsheet, and an etiquette that nobody has ever written down. The unwritten rules are the story.',
                'By the 2 p.m. bell the hall has served eleven thousand people — a number the city measures by door counters and the vendors measure in sold-out tables.'
            ),
        ],

        /* ----------------------------------------------------- Ontario band --- */
        [
            'title' => 'Two-way, all-day GO service to Kitchener slips again, to 2029',
            'slug' => 'kc-two-way-all-day-go-2029',
            'image' => $img('iontrain.svg'),
            'image_caption' => 'The corridor\'s trains, still one-way at peak.',
            'image_credit' => 'Chronicle graphics',
            'desk' => 'ontario', 'byline' => 'Alice Renwick', 'dateline' => 'Queen\'s Park',
            'lede' => 'Metrolinx blames a freight-corridor agreement it has been negotiating since 2018. Local MPPs say they were told in June the date would hold.',
            'views' => 2200, 'published' => $ago('-16 hours'),
            'tags' => 'go transit, metrolinx',
            'body' => $p(
                'The two-way, all-day GO service the corridor has been promised since 2016 has slipped again, to 2029, according to a Metrolinx capital update released Friday afternoon.',
                'The agency attributes the delay to the freight-corridor agreement with CN it has been negotiating since 2018. The agreement covers the same eleven kilometres it covered in 2018.',
                'Both local MPPs say they were told in June the 2027 date would hold. Neither was told before the update was published.'
            ),
        ],
        [
            'title' => 'Grand River flood mapping redrawn for the first time since 1997',
            'slug' => 'kc-grand-river-flood-mapping-redrawn',
            'desk' => 'ontario', 'byline' => 'Devon Marsh', 'dateline' => 'Cambridge',
            'lede' => 'The new maps add 1,900 properties to the regulated floodplain, most of them in Cambridge and Bridgeport.',
            'image' => $img('river.svg'),
            'image_caption' => 'The Grand below the rail bridge, where the new mapping moves the line.',
            'image_credit' => 'Chronicle graphics',
            'views' => 1900, 'published' => $ago('-1 day'),
            'tags' => 'grand river, flooding',
            'body' => $p(
                'The conservation authority\'s new flood mapping — the first full redraw since 1997 — adds 1,900 properties to the regulated floodplain, and the letters landed in mailboxes this week.',
                'The mapping reflects two decades of upstream development and a design storm recalibrated for a wetter climate. It is not a prediction; it is a boundary with legal force.',
                'For most affected owners the change means permits, not prohibition. For a strip of Bridgeport it means the addition they planned is no longer approvable.'
            ),
        ],
        [
            'title' => 'Ontario\'s family-doctor shortage reaches Waterloo Region\'s suburbs',
            'slug' => 'kc-family-doctor-shortage-suburbs',
            'desk' => 'ontario', 'byline' => 'Chronicle Staff', 'dateline' => 'Waterloo Region',
            'lede' => 'One in five residents of the townships is now without a family physician, the health team\'s new count shows.',
            'views' => 1700, 'published' => $ago('-1 day 4 hours'),
            'tags' => 'health, physicians',
            'body' => $p(
                'The Ontario health team\'s new attachment count puts one in five township residents without a family physician, a figure that was one in nine when the count began in 2022.',
                'The shortage has moved outward: the cities held roughly steady while Wellesley, Wilmot and Woolwich absorbed the retirements of four practices with no successors.',
                'The health team\'s recruitment fund has landed three physicians this year. The four retiring practices carried eleven thousand patients between them.'
            ),
        ],

        /* -------------------------------------------------- the Latest rail --- */
        [
            'title' => 'LRT single-fare cap holds at $3.50 through 2027',
            'slug' => 'kc-lrt-fare-cap-holds',
            'desk' => 'local-news', 'byline' => 'Marta Iqbal, Transit reporter', 'dateline' => 'Regional headquarters',
            'lede' => 'Grand River Transit\'s budget freezes the single fare for a third year, paid for by a parking levy that passed without debate.',
            'views' => 1100, 'published' => $ago('-2 hours'),
            'tags' => 'grt, fares',
            'body' => $p(
                'The single fare stays at $3.50 through 2027 under the transit budget approved Wednesday, the third consecutive freeze.',
                'The freeze is financed by the downtown parking levy, which passed in the same meeting without a single delegation — a sentence nobody at regional headquarters expected to write.',
                'Monthly passes rise two dollars.'
            ),
        ],
        [
            'title' => 'WRDSB trustees defer the boundary review to October',
            'slug' => 'kc-wrdsb-boundary-review-deferred',
            'desk' => 'local-news', 'byline' => 'Chronicle Staff', 'dateline' => 'Education Centre',
            'lede' => 'The review that would move 2,300 students waits for the fall enrolment count.',
            'views' => 900, 'published' => $ago('-4 hours'),
            'tags' => 'wrdsb, schools',
            'body' => $p(
                'Trustees voted Monday to defer the attendance-boundary review to October, after the fall enrolment count is final.',
                'The review\'s draft options would move up to 2,300 students across eleven schools, most in the southwest, where three schools sit above 120 per cent of capacity.',
                'The deferral moves any change to September 2027 at the earliest.'
            ),
        ],
        [
            'title' => 'Two charged after a fire at a vacant Victoria Street plant',
            'slug' => 'kc-victoria-street-plant-fire-charges',
            'desk' => 'local-news', 'byline' => 'Chronicle Staff', 'dateline' => 'Victoria Street',
            'lede' => 'The former tannery burned for six hours on Sunday. Police say the building had been entered through a delivery door.',
            'views' => 1300, 'published' => $ago('-6 hours'),
            'tags' => 'fire, police',
            'body' => $p(
                'Two people face arson charges after Sunday\'s six-hour fire in the vacant tannery on Victoria Street, the third fire in the building in two years.',
                'The building has been on the city\'s vacant-property registry since 2021 and on its demolition-by-neglect watchlist since March.',
                'The owner, a numbered company, has not responded to the city\'s last four orders. The fire has now done part of what the orders asked.'
            ),
        ],
        [
            'title' => 'Conestoga College cuts 42 program sections for winter',
            'slug' => 'kc-conestoga-cuts-42-sections',
            'desk' => 'local-news', 'byline' => 'Devon Marsh', 'dateline' => 'Doon',
            'lede' => 'The cuts land hardest in business diplomas, where international enrolment fell by half.',
            'views' => 800, 'published' => $ago('-8 hours'),
            'tags' => 'conestoga, enrolment',
            'body' => $p(
                'The college will run 42 fewer program sections in January, the registrar\'s office confirmed, with business diplomas absorbing more than half the reduction.',
                'International enrolment in those programs fell by half after the federal permit cap, and the college\'s domestic intake did not fill the gap.',
                'No layoff notices accompany the cuts; the college says the reduction is managed through sections taught by contract faculty whose terms end in December.'
            ),
        ],

        /* ---------------------------------------------------- most read etc --- */
        [
            'title' => 'The landlord who owns 380 units in Kitchener — and no phone number',
            'slug' => 'kc-landlord-380-units-no-phone',
            'desk' => 'local-news', 'byline' => 'Devon Marsh', 'dateline' => 'Kitchener',
            'lede' => 'Tenants in eleven buildings share one maintenance inbox, one numbered company, and a growing case file at the Landlord and Tenant Board.',
            'views' => 8200, 'published' => $ago('-2 days'),
            'tags' => 'housing, tenants',
            'body' => $p(
                'The Chronicle traced eleven Kitchener buildings — 380 units — to a single numbered company whose registered address is a mailbox in Vaughan and whose only tenant contact is an inbox that answers with a ticket number.',
                'The company\'s file at the Landlord and Tenant Board runs to sixty-one applications, forty-eight of them filed by the company itself.',
                'City bylaw has an enforcement file too. What it does not have, staff concede, is a person.'
            ),
        ],
        [
            'title' => 'Where the region\'s $30-million surplus actually went',
            'slug' => 'kc-where-the-surplus-went',
            'desk' => 'politics', 'byline' => 'Alice Renwick', 'dateline' => 'Regional headquarters',
            'lede' => 'Half to reserves, a quarter to the transit stabilisation fund, and the rest to a list nobody debated.',
            'image' => $img('tower.svg'),
            'image_caption' => 'City Hall\'s clock tower, which has watched better budgets and worse.',
            'image_credit' => 'Chronicle graphics',
            'views' => 4100, 'published' => $ago('-2 days 6 hours'),
            'tags' => 'budget, surplus',
            'body' => $p(
                'The 2025 surplus closed at $30.4 million, and the allocation report that distributes it passed on consent — which means the interesting half of the story is the list nobody debated.',
                'Half goes to the tax stabilisation reserve, a quarter to transit. The remaining eight million funds fourteen items, from courthouse security cameras to a consultant\'s review of the consultant-review process.',
                'The Chronicle annotated all fourteen. Three had previously been voted down as budget items.'
            ),
        ],
        [
            'title' => 'A tenant-run co-op buys its own building on Weber Street',
            'slug' => 'kc-coop-buys-weber-street-building',
            'desk' => 'business', 'byline' => 'Nadia Brunner', 'dateline' => 'Weber Street',
            'lede' => 'Forty-one households outbid two investment funds with a land trust, a credit union, and eleven months of meetings.',
            'views' => 3600, 'published' => $ago('-3 days'),
            'tags' => 'co-op, housing',
            'body' => $p(
                'The forty-one households of 468 Weber Street closed Friday on the purchase of their own building, the first tenant conversion in the region under the new federal acquisition fund.',
                'Their offer was not the highest. It was the only one the seller — a retiring landlord who bought the building in 1989 — was willing to carry financing for.',
                'The co-op\'s first act as owner was to fix the intercom. The second was to cap its own rents at cost.'
            ),
        ],
        [
            'title' => 'Oktoberfest\'s new organisers on cutting the parade route',
            'slug' => 'kc-oktoberfest-parade-route',
            'desk' => 'culture', 'byline' => 'Ellen Vogt', 'dateline' => 'Kitchener',
            'lede' => 'The Thanksgiving parade survives, but two kilometres shorter and an hour earlier, and the festival\'s new board says both changes are about the same thing: volunteers.',
            'views' => 2800, 'published' => $ago('-3 days 8 hours'),
            'tags' => 'oktoberfest, festivals',
            'body' => $p(
                'The Thanksgiving parade will run two kilometres shorter this year and step off an hour earlier, the festival\'s new board announced, and both changes trace to the same line in the annual report: marshal volunteers, down 40 per cent since 2019.',
                'The route now ends at the market instead of the auditorium, which the board frames as a destination and the auditorium\'s neighbourhood frames as a loss.',
                'The festival\'s beer halls are unaffected, a sentence the board asked us to print high in the story.'
            ),
        ],
        [
            'title' => 'Cambridge mayor asks for a seat on the ION oversight committee',
            'slug' => 'kc-cambridge-seat-ion-oversight',
            'desk' => 'politics', 'byline' => 'Marta Iqbal, Transit reporter', 'dateline' => 'Cambridge',
            'lede' => 'The committee that will watch $1.9 billion has no member from the city the line is being built to reach.',
            'views' => 1200, 'published' => $ago('-4 days'),
            'tags' => 'ion, cambridge, oversight',
            'body' => $p(
                'The oversight committee struck alongside Tuesday\'s ION vote has five members, none of them from Cambridge — the city the $1.9-billion extension exists to reach.',
                'The mayor\'s letter, released Thursday, calls the omission &ldquo;an oversight in the literal sense&rdquo; and asks for the committee\'s terms to be amended before its first meeting.',
                'The regional chair\'s office says the terms were drafted before the vote and will be revisited. The first meeting is in three weeks.'
            ),
        ],
        [
            'title' => 'Region\'s paramedic service posts its worst response times on record',
            'slug' => 'kc-paramedic-response-times-record',
            'desk' => 'politics', 'byline' => 'Chronicle Staff', 'dateline' => 'Regional headquarters',
            'lede' => 'Code-4 response crossed eleven minutes for the first time, and the service\'s own report names the hospital offload queue as the cause.',
            'views' => 1600, 'published' => $ago('-5 days'),
            'tags' => 'paramedics, health',
            'body' => $p(
                'The quarterly report tabled Wednesday puts median code-4 response at 11:04 — the first time the region\'s paramedic service has crossed eleven minutes since the measure began.',
                'The report\'s own diagnosis is the offload queue: crews spent the equivalent of nine ambulances\' annual capacity waiting at emergency departments to transfer patients.',
                'The service\'s ask is not more ambulances. It is a dedicated offload nursing team at the region\'s busiest hospital, at a tenth of the cost.'
            ),
        ],
    ],
];
