<?php
/**
 * Tri Cities Torch — launch package.
 * Loaded once by `PP_SITE=tri-cities-torch php tools/seed-launch.php`.
 *
 * Identity, the five sections the design package names, Tri-Cities wire
 * feeds, and eighteen launch stories with commissioned art in the brand's
 * illustration style. The stories are launch content in the paper's voice,
 * meant to be replaced by real reporting.
 *
 * Every desk the stories use is listed in 'desks' below, so the pack stands
 * on its own — the seeder creates only what the shared database is missing.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$img = fn (string $file) => '/assets/sites/tri-cities-torch/img/' . $file;

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['tricitiestorch.ca', 'www.tricitiestorch.ca'],

    // Shared network desks. Each is created only if missing; the Torch
    // renames "News" to "Local News" and "Business & Markets" to "Business"
    // for itself through desk_labels in palette.json.
    'desks' => [
        ['name' => 'News',      'slug' => 'news',      'color' => '#2B1A52', 'description' => 'Council, courts, and what happened overnight across the three cities.'],
        ['name' => 'Community', 'slug' => 'community', 'color' => '#2F7D46', 'description' => 'Neighbourhood associations, volunteers, festivals and the people keeping the three cities running.'],
        ['name' => 'Politics',  'slug' => 'politics',  'color' => '#2B1A52', 'description' => 'City halls, the school board, the region and Victoria — and what each decides for the Tri-Cities.'],
        ['name' => 'Business',  'slug' => 'business',  'color' => '#1A5FB4', 'description' => 'Development, main streets, and the businesses that open and close along them.'],
        ['name' => 'Sports',    'slug' => 'sports',    'color' => '#1A5FB4', 'description' => 'From the rec-centre rinks and the rowing club to the leagues the Tri-Cities feed.'],
        ['name' => 'Opinion',   'slug' => 'opinion',   'color' => '#2B1A52', 'description' => 'Columns and the editorial board. Signed, and ready to be argued with.'],
    ],

    'settings' => [
        // The byline every Hermes filing carries here. Without it the
        // server falls back to the generic 'Automated report'.
        'automated_byline'   => 'Torch Newsroom Automation',
        'site_title'         => 'Tri Cities Torch',
        'tagline'            => 'Coquitlam · Port Coquitlam · Port Moody',
        'meta_description'   => 'Independent local journalism for Coquitlam, Port Coquitlam and Port Moody, with Anmore and Belcarra — city halls, transit, housing, and the neighbourhoods between them.',
        'footer_line'        => 'Independent local journalism serving Coquitlam, Port Coquitlam and Port Moody, with Anmore and Belcarra.',
        'contact_email'      => 'tips@tricitiestorch.ca',
        'newsletter_heading' => 'The Torch, every weekday at 6am',
        'newsletter_copy'    => 'What happened in Coquitlam, Port Coquitlam and Port Moody while you slept.',
        'breaking_label'     => 'Riverview lands rezoning heads to public hearing',
        'breaking_url'       => '/story/riverview-lands-rezoning-heads-to-public-hearing',
        'weather_line'       => 'Coquitlam · 19°C · Cloud breaking by noon',
        'regions'            => json_encode([
            'tri-cities' => 'Tri-Cities',
            'metro'      => 'Metro Vancouver',
            'bc'         => 'British Columbia',
        ]),
    ],

    // Verified fetching and parsing at build time. The Tri-City News, the
    // paper's nearest neighbour, publishes no discoverable feed — add it by
    // hand under Newsroom → Sources if the newsroom obtains a working URL.
    'sources' => [
        ['CityNews Vancouver',   'https://vancouver.citynews.ca/feed/',                        'metro'],
        ['Daily Hive Vancouver', 'https://dailyhive.com/feed/vancouver',                       'metro'],
        ['CBC British Columbia', 'https://www.cbc.ca/webfeed/rss/rss-canada-britishcolumbia',  'bc'],
        ['Global BC',            'https://globalnews.ca/bc/feed/',                             'bc'],
        ['The Tyee',             'https://thetyee.ca/rss2.xml',                                'bc'],
        ['Vancouver Sun',        'https://vancouversun.com/feed',                              'metro'],
    ],

    'stories' => [

        /* ----------------------------------------------------- the lead --- */
        [
            'title' => 'Riverview lands rezoning heads to public hearing',
            'desk' => 'news', 'dateline' => 'Coquitlam', 'byline' => 'Dana Whitfield',
            'lede' => 'Council will hear from residents on the 244-hectare site for the first time since the province transferred title in March.',
            'image' => $img('photo-01.svg'),
            'image_caption' => 'The Riverview lands seen from the Lougheed Highway.',
            'image_credit' => 'Torch file photo',
            'featured' => 1, 'placement' => 'hero', 'views' => 284, 'published' => $ago('-3 hours'),
            'tags' => 'riverview, housing, city hall',
            'body' => $p(
                'The rezoning application covering the Riverview lands goes to a public hearing next month, the first time residents will speak to the plan since the province transferred title to the city in March after a decade of negotiation.',
                'The measure passed second reading on a five-to-four vote after two hours of submissions, with the mayor breaking a tie. Staff will return in the fall with a servicing plan for the eastern parcel — the piece closest to the Lougheed, and the piece with the most water and sewer work attached to it.',
                'Opponents argued the density figures had not been circulated with enough notice. Three delegations asked for a deferral, which council rejected without debate.',
                '<blockquote>We have waited eleven years for a plan. Another six months changes nothing on the ground.<cite>Councillor R. Mahal, Ward 3</cite></blockquote>',
                'What is actually before council is narrower than the argument around it. The application rezones roughly a third of the site; the heritage core, including the buildings the province listed in 2019, is untouched by this bylaw and will be dealt with separately.',
                'The hearing is scheduled for three evenings beginning 9 September at the Evergreen Cultural Centre. Written submissions close at noon on the sixth.'
            ),
        ],

        /* ------------------------------------------ the illustrated row --- */
        [
            'title' => 'Rocky Point pier reopens after nine-month rebuild',
            'desk' => 'news', 'dateline' => 'Port Moody', 'byline' => 'Alec Reyes',
            'lede' => 'Two lanes of the parking lot stay closed through the weekend while crews finish the deck surfacing.',
            'image' => $img('photo-02.svg'),
            'image_caption' => 'The rebuilt pier at Rocky Point Park.',
            'image_credit' => 'Alec Reyes / Torch',
            'views' => 196, 'published' => $ago('-6 hours'),
            'tags' => 'rocky point, parks',
            'body' => $p(
                'PORT MOODY — The pier at Rocky Point Park opened to the public Friday morning, nine months after the city closed it on an engineer\'s report that gave the pilings two years at the outside.',
                'The rebuild replaced forty-one pilings, the full deck and the rail, and moved the accessible ramp to the west side so it no longer crosses the busiest part of the walkway. The final cost came in at $4.1 million, about $260,000 over the tendered price — the overrun almost entirely in the marine work.',
                'Two lanes of the parking lot stay closed through the weekend while crews finish surfacing, and the city is asking visitors to use the Murray Street lot until Monday.',
                'For the paddling clubs that have launched from the beach beside the pier all summer, the reopening matters mostly for what comes back with it: the float, which returns in the spring.'
            ),
        ],
        [
            'title' => 'Indian Arm ferry pilot extended to October',
            'desk' => 'news', 'dateline' => 'Belcarra', 'byline' => 'Priya Sandhu',
            'lede' => 'Ridership held above the threshold TransLink set in June, and the village wants the winter schedule studied next.',
            'image' => $img('photo-06.svg'),
            'image_caption' => 'Indian Arm from the Belcarra side.',
            'image_credit' => 'Torch file photo',
            'views' => 142, 'published' => $ago('-9 hours'),
            'tags' => 'ferry, transit, belcarra',
            'body' => $p(
                'BELCARRA — The passenger ferry pilot connecting the village to Port Moody will run through October after ridership held above the threshold TransLink set when the service began in June.',
                'The boat has carried an average of 214 passengers a day since the long weekend, against a break-even the authority put at 180. Weekend loads are close to capacity; Tuesdays and Wednesdays are the thin days, and the operator has been running the smaller vessel on both.',
                'Council\'s ask now is a study of a winter schedule — three sailings a day rather than nine — which the village argues is the only way to know whether the route works as transportation rather than as a summer attraction.',
                'TransLink has not committed to the study. A decision on continuing past October is expected at the September board meeting.'
            ),
        ],
        [
            'title' => 'Downtown tower approvals hit a ten-year high',
            'desk' => 'business', 'dateline' => 'Coquitlam', 'byline' => 'Marta Cheng',
            'lede' => 'Eleven towers cleared council this year, all within eight hundred metres of a SkyTrain station.',
            'image' => $img('photo-03.svg'),
            'image_caption' => 'Towers over Coquitlam Town Centre.',
            'image_credit' => 'Marta Cheng / Torch',
            'views' => 168, 'published' => $ago('-13 hours'),
            'tags' => 'development, housing, town centre',
            'body' => $p(
                'COQUITLAM — Eleven residential towers have cleared council this year, the most since 2016, and every one of them sits within eight hundred metres of a SkyTrain station.',
                'The concentration is not an accident. The city\'s transit-oriented development areas, redrawn after the provincial housing legislation of 2024, allow heights along the Evergreen Line that would need a site-specific rezoning anywhere else in the city — and developers have gone where the process is shortest.',
                'What the approvals do not yet show is completions. Of the eleven, two have building permits; the rest are somewhere between a development permit and a financing package, and at least three are widely understood in the industry to be waiting on interest rates before breaking ground.',
                'The city\'s own projections assume roughly 60 per cent of approved units are built within five years. On that arithmetic the eleven towers become about 2,400 homes by 2031, against a housing target of 4,100.'
            ),
        ],
        [
            'title' => 'Grand Prix returns to Shaughnessy Street',
            'desk' => 'community', 'dateline' => 'Port Coquitlam', 'byline' => 'Jordan Falk',
            'lede' => 'The criterium closes the downtown block for a fourth year, with a junior race added on the Saturday morning.',
            'image' => $img('photo-07.svg'),
            'image_caption' => 'Shaughnessy Street closed for the weekend.',
            'image_credit' => 'Jordan Falk / Torch',
            'views' => 121, 'published' => $ago('-18 hours'),
            'tags' => 'cycling, festivals, downtown',
            'body' => $p(
                'PORT COQUITLAM — The Grand Prix criterium returns to Shaughnessy Street for a fourth year on 23 August, with the downtown block closed from Friday evening through Sunday afternoon.',
                'The addition this year is a junior race on the Saturday morning, run on a shortened course with the corner at McAllister neutralised — a change the organisers asked for after two crashes at that corner in the 2025 event.',
                'The business association reports the closure now pays for itself; the Saturday of the race weekend is the single busiest retail day of the year on the street, ahead of both the Christmas market and Canada Day.',
                'Parking restrictions go up Thursday night. The detour for the 159 runs via Kingsway and Mary Hill.'
            ),
        ],

        /* --------------------------------------------- the two-up row ----- */
        [
            'title' => 'Burke Mountain catchment redrawn for September',
            'desk' => 'politics', 'dateline' => '', 'byline' => 'Dana Whitfield',
            'lede' => 'Roughly 400 families are affected by the trustees\' vote, most of them moving to the new middle school.',
            'views' => 134, 'published' => $ago('-22 hours'),
            'tags' => 'schools, burke mountain',
            'body' => $p(
                'School District 43 trustees voted Tuesday to redraw the Burke Mountain catchment for September, moving roughly 400 families — most to the middle school that opens on Princeton this fall.',
                'The redraw had been deferred twice. The district\'s own enrolment figures show the two elementary schools on the mountain running at 118 and 124 per cent of capacity, with eleven portables between them.',
                'The board heard from twenty-two delegations across two evenings. The objection that carried the most weight was not about distance but about siblings: under the first draft, families with children in different grades could have been split across two schools. The version that passed grandfathers siblings through to the end of the 2027 school year.',
                'Transportation for the new catchment will be confirmed in August, and the district has committed to publishing walk routes before the first day.'
            ),
        ],
        [
            'title' => 'Volunteers wanted for the Coquitlam River cleanup',
            'desk' => 'community', 'dateline' => '', 'byline' => 'Priya Sandhu',
            'lede' => 'Saturday, 9am, from the Lions Park boat launch. Gloves and bags provided.',
            'image' => $img('photo-04.svg'),
            'image_caption' => 'The Coquitlam River above Lions Park.',
            'image_credit' => 'Torch file photo',
            'views' => 88, 'published' => $ago('-1 day 3 hours'),
            'tags' => 'volunteers, rivers, environment',
            'body' => $p(
                'The watershed society\'s late-summer cleanup runs Saturday from 9am, meeting at the Lions Park boat launch. Gloves, bags and grabbers are provided; the society asks volunteers to bring boots they do not mind soaking.',
                'Last year\'s cleanup pulled 1.4 tonnes out of a four-kilometre stretch, about a third of it from the two hundred metres immediately below the Lougheed bridge.',
                'The society is also looking for people willing to take a monthly stretch through the winter — the work that actually keeps the bank clear, and the work that is hardest to staff.'
            ),
        ],

        /* ------------------------------------------------- the briefs ----- */
        [
            'title' => 'Evergreen Line ridership passes pre-2020 levels',
            'desk' => 'news', 'dateline' => '', 'byline' => 'Alec Reyes',
            'lede' => 'Weekday boardings at Lafarge Lake–Douglas are up nine per cent on 2019, the first station on the line to clear the mark.',
            'image' => $img('photo-05.svg'),
            'image_caption' => 'A train on the elevated guideway above Barnet Highway.',
            'image_credit' => 'Torch file photo',
            'views' => 96, 'published' => $ago('-1 day 8 hours'),
            'tags' => 'transit, evergreen line',
            'body' => $p(
                'Weekday boardings at Lafarge Lake–Douglas are running nine per cent above 2019, making it the first Evergreen Line station to clear its pre-pandemic mark.',
                'TransLink attributes most of the gain to the towers that have completed within walking distance of the station since 2021 — roughly 1,900 units, nearly all of them rental or strata apartments rather than townhouses.',
                'The line as a whole remains about four per cent below 2019, held down by Burquitlam and Moody Centre, where midday travel has not recovered.'
            ),
        ],
        [
            'title' => 'Como Lake water quality report released',
            'desk' => 'news', 'dateline' => 'Coquitlam', 'byline' => 'Marta Cheng',
            'lede' => 'Phosphorus is down for a third year, but the report flags the inflow culvert as the remaining problem.',
            'image' => $img('photo-08.svg'),
            'image_caption' => 'Como Lake on a still morning.',
            'image_credit' => 'Marta Cheng / Torch',
            'views' => 74, 'published' => $ago('-1 day 14 hours'),
            'tags' => 'como lake, environment, water',
            'body' => $p(
                'The city\'s annual water quality report for Como Lake shows total phosphorus down for a third consecutive year, to 21 micrograms per litre from a 2019 peak of 38.',
                'The aeration system installed in 2021 gets most of the credit. What the report flags is the inflow culvert at the north end, where stormwater from about sixty hectares of road and roof enters the lake essentially untreated.',
                'Staff recommend a treatment forebay at the culvert mouth, priced at $1.6 million, for consideration in the 2027 capital plan.'
            ),
        ],
        [
            'title' => 'Brewers\' Row adds a sixth taproom',
            'desk' => 'business', 'dateline' => '', 'byline' => 'Jordan Falk',
            'lede' => 'The Murray Street strip that started with one brewery in 2013 now anchors the city\'s busiest evening block.',
            'image' => $img('photo-10.svg'),
            'image_caption' => 'A taproom on Murray Street.',
            'image_credit' => 'Jordan Falk / Torch',
            'views' => 82, 'published' => $ago('-2 days 2 hours'),
            'tags' => 'brewers row, main street',
            'body' => $p(
                'A sixth taproom opens on Murray Street this month, in the bay that held a tile wholesaler until last spring.',
                'The strip that began with a single brewery in 2013 is now the busiest evening block in Port Moody by foot traffic, and the city\'s own counts put Friday and Saturday pedestrian volumes there ahead of Newport Village.',
                'The pressure the association keeps raising is parking, and the answer council keeps giving is the shuttle — which runs to the end of September, and then does not.'
            ),
        ],
        [
            'title' => 'Centennial rowers take the provincial eights',
            'desk' => 'sports', 'dateline' => '', 'byline' => 'Alec Reyes',
            'lede' => 'The senior boat led from the six-hundred and finished clear, the club\'s first provincial title since 2018.',
            'image' => $img('photo-09.svg'),
            'image_caption' => 'The senior eight on the water at dawn.',
            'image_credit' => 'Alec Reyes / Torch',
            'views' => 118, 'published' => $ago('-2 days 7 hours'),
            'tags' => 'rowing, schools',
            'body' => $p(
                'Centennial\'s senior eight won the provincial championship on Sunday, leading from the six-hundred-metre mark and finishing about a length and a half clear of Shawnigan.',
                'It is the club\'s first provincial title in the event since 2018, and it comes from a boat with five athletes in their first season at the senior level.',
                'The crew races at the national championships in Ontario at the end of the month.'
            ),
        ],
        [
            'title' => 'Port Moody budget adds two firefighters',
            'desk' => 'politics', 'dateline' => '', 'byline' => 'Dana Whitfield',
            'lede' => 'The positions were the only line council added to the draft, and they cost a quarter of the tax increase.',
            'views' => 64, 'published' => $ago('-2 days 13 hours'),
            'tags' => 'budget, fire, port moody',
            'body' => $p(
                'PORT MOODY — Council added two firefighter positions to the draft 2027 budget on Tuesday, the only addition it made to the staff plan.',
                'The two positions cost about $290,000 a year fully loaded, which is roughly a quarter of the proposed tax increase of 4.9 per cent.',
                'The case for them was response time on the second simultaneous call — the situation where the department currently relies on mutual aid from Coquitlam, and where the chief\'s figures showed an eleven-minute average over the last two years.'
            ),
        ],

        /* ----------------------------------------- the section river ------ */
        [
            'title' => 'Golden Spike Days adds a third stage',
            'desk' => 'community', 'dateline' => 'Port Moody', 'byline' => 'Priya Sandhu',
            'lede' => 'The festival grows again, and the organisers say the limit now is washrooms, not programming.',
            'views' => 58, 'published' => $ago('-3 days 4 hours'),
            'tags' => 'festivals, golden spike days',
            'body' => $p(
                'PORT MOODY — Golden Spike Days adds a third stage this year, in the grass south of the pavilion, programmed mostly with local acts on the Saturday and Sunday afternoons.',
                'The society says attendance has grown every year since 2022 and that the practical constraint now is not programming or budget but washrooms and parking — both of which cap the site at about nine thousand people at once.',
                'The festival runs the long weekend, and the shuttle from Moody Centre station runs every twenty minutes.'
            ),
        ],
        [
            'title' => 'Library expands Sunday hours to Leigh Square',
            'desk' => 'community', 'dateline' => 'Port Coquitlam', 'byline' => 'Jordan Falk',
            'lede' => 'The branch opens Sundays from October, paid for by a reallocation rather than new money.',
            'views' => 47, 'published' => $ago('-3 days 11 hours'),
            'tags' => 'library, leigh square',
            'body' => $p(
                'PORT COQUITLAM — The library board voted to open the Leigh Square branch on Sundays from October, noon to five.',
                'The hours are paid for by reallocating from weekday evenings, which the board\'s own usage figures show as the quietest block in the week — a trade that drew objection from two trustees who argued evening hours matter most to shift workers.',
                'The change is set to be reviewed after six months against door counts and program registrations.'
            ),
        ],
        [
            'title' => 'Village hall opens its archive to the public',
            'desk' => 'community', 'dateline' => 'Belcarra', 'byline' => 'Priya Sandhu',
            'lede' => 'Sixty years of council minutes, now searchable, and a volunteer who spent two winters scanning them.',
            'views' => 41, 'published' => $ago('-4 days 5 hours'),
            'tags' => 'archives, belcarra, heritage',
            'body' => $p(
                'BELCARRA — Sixty years of village council minutes went online this week, scanned and indexed by a volunteer who spent two winters at it and who asked not to be named in this story.',
                'The archive runs from incorporation in 1979 back through the improvement district that preceded it, and includes the correspondence file on the park transfer — the document trail that residents have been asking the village to publish for the better part of a decade.',
                'The collection is searchable from the village website. Paper originals stay at the hall, by appointment.'
            ),
        ],
        [
            'title' => 'Town Centre night market draws record crowds',
            'desk' => 'community', 'dateline' => 'Coquitlam', 'byline' => 'Marta Cheng',
            'lede' => 'Organisers add a second night from September after three sell-out weekends in a row.',
            'views' => 63, 'published' => $ago('-4 days 12 hours'),
            'tags' => 'night market, town centre',
            'body' => $p(
                'COQUITLAM — The Town Centre night market adds a second night from September, after three consecutive weekends that organisers describe as at capacity for the site.',
                'The market has run Friday evenings on the plaza since June with about forty vendors. The second night, Saturdays, will run with the same footprint and roughly a dozen additional food stalls.',
                'The city has asked for a revised traffic plan before the September start, mostly around the loading window on Pinetree.'
            ),
        ],
        [
            'title' => 'Trail crews finish the Buntzen connector',
            'desk' => 'community', 'dateline' => 'Anmore', 'byline' => 'Alec Reyes',
            'lede' => 'Eleven kilometres now link to the Diez Vistas, and the last bridge went in on Thursday.',
            'image' => $img('photo-06.svg'),
            'image_caption' => 'The ridge above Buntzen Lake.',
            'image_credit' => 'Torch file photo',
            'views' => 72, 'published' => $ago('-5 days 6 hours'),
            'tags' => 'trails, anmore, hiking',
            'body' => $p(
                'ANMORE — The last bridge on the Buntzen connector went in Thursday, completing eleven kilometres of trail that link the village trail network to the Diez Vistas route.',
                'The work was done over three seasons, most of it by volunteers, with the two significant bridges built by a contractor on a grant from the regional district.',
                'The connector is rated intermediate, with about 340 metres of gain over its length. Crews ask that it be left alone through the first heavy rains while the new tread settles.'
            ),
        ],

        /* --------------------------------------------------- the column --- */
        [
            'title' => 'The Riverview argument is really about who gets to stay',
            'desk' => 'opinion', 'dateline' => '', 'byline' => 'The editorial board',
            'lede' => 'Density is the language council is using. Tenure is the thing the hearing is actually about.',
            'views' => 91, 'published' => $ago('-5 days 14 hours'),
            'tags' => 'editorial, riverview, housing',
            'body' => $p(
                'The hearing on the Riverview lands will be conducted in the language of density: floor space ratios, unit counts, height in storeys. That is the language the Local Government Act gives councils, and it is a poor fit for what the room will actually be arguing about.',
                'The question underneath is tenure. A thousand units of market strata and a thousand units with rent controls attached produce identical numbers in a staff report and entirely different towns a decade later. Only one of them keeps the people who work in the hospital that is being rebuilt next door.',
                'Council has the tools to ask for the second kind. Inclusionary policy, density bonusing, land it already owns — the province handed this city an unusual amount of leverage in March, and leverage expires the moment a bylaw is adopted.',
                'Three evenings of submissions is a serious hearing, and the board welcomes it. We would only ask that someone at the table say the quiet part: this is not a debate about how tall. It is a debate about who gets to stay.'
            ),
        ],
    ],
];
