<?php
/**
 * The Pickering Post — launch package.
 * Loaded once by `PP_SITE=pickering-post php tools/seed-launch.php`.
 *
 * DEMO CONTENT. These sixteen pieces exist to showcase the design, and they
 * are shaped to the front page's slots: one hero with a photograph, four Top
 * Stories, three Events, a Community Spotlight, and enough left over to fill
 * the Latest rail. One Breaking story wears the magenta slug, one Opinion
 * piece the outlined slug, one Obituary the neutral one, so all three
 * treatments are visible. Illustrative, not reported.
 *
 * Every story carries an explicit `pk-` slug. `posts.slug` is UNIQUE across
 * the whole shared database and the seeder silently skips a story whose slug
 * is taken, so nothing here is left to chance.
 *
 * All eight desks are listed, so the pack stands on its own — the seeder
 * creates only what the shared database is missing and reuses by slug
 * anything a sister paper already seeded.
 *
 * Safe to re-run: existing stories are skipped, and settings the newsroom
 * has edited are left alone.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$q = fn (string $quote, string $who) => '<blockquote><p>' . $quote . '</p><cite>' . $who . '</cite></blockquote>';
$h = fn (string $head) => '<h2>' . $head . '</h2>';
$img = fn (string $file) => '/assets/sites/pickering-post/img/' . $file;

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['pickeringpost.ca', 'www.pickeringpost.ca'],

    'desks' => [
        ['name' => 'Local News',  'slug' => 'local-news',  'color' => '#0088B0', 'description' => 'Council, the waterfront, the schools, and what happened overnight.'],
        ['name' => 'Community',   'slug' => 'community',   'color' => '#0088B0', 'description' => 'The people, clubs and volunteers who make the place work.'],
        ['name' => 'Events',      'slug' => 'events',      'color' => '#0088B0', 'description' => "What's on this week, from the band shell to the farmers' market."],
        ['name' => 'Sports',      'slug' => 'sports',      'color' => '#0088B0', 'description' => 'Minor hockey, the high schools, and the clubs on the lake.'],
        ['name' => 'Business',    'slug' => 'business',    'color' => '#0088B0', 'description' => 'Main streets, industrial parks, and the people hiring.'],
        ['name' => 'Opinion',     'slug' => 'opinion',     'color' => '#0088B0', 'description' => 'Argument and comment, clearly marked as such.'],
        ['name' => 'Obituaries',  'slug' => 'obituaries',  'color' => '#605D5D', 'description' => 'Lives remembered, printed as families send them.'],
        ['name' => 'Breaking',    'slug' => 'breaking',    'color' => '#D6006C', 'description' => 'Stories that are still moving.'],
    ],

    'settings' => [
        // The byline every Hermes filing carries here. Without it the
        // server falls back to the generic 'Automated report'.
        'automated_byline'   => 'Pickering Post Newsroom',
        'site_title'         => 'The Pickering Post',
        'tagline'            => "Durham Region's daily",
        'meta_description'   => 'Local news for Pickering and Durham Region: council, the waterfront, community, events, sports and business.',
        'footer_line'        => "Durham Region's daily",
        'contact_email'      => 'newsroom@pickeringpost.ca',
        'newsletter_heading' => 'The morning email',
        'newsletter_copy'    => 'Six stories from Pickering in your inbox by seven. Free, and short enough for the GO train.',
        'breaking_label'     => '',
        'breaking_url'       => '',
        // No outlet publishes a Pickering-only feed; Durham Radio News and
        // The Oshawa Express cover the city inside `durham`. A subscribed
        // bucket nothing can feed reads as a quiet wire forever, so the
        // paper subscribes only to buckets that can actually fill.
        'regions'            => json_encode([
            'durham'    => 'Durham Region',
            'ontario'   => 'Ontario',
        ]),
    ],

    'sources' => [
        // One URL fills one bucket: sources are matched by URL and
        // news_items.url_hash is globally unique, so the CBC Toronto and
        // CBC Canada feeds this pack once listed here did nothing — other
        // packs had already claimed them for `gta` and `canada`. Durham
        // needs feeds of its own, and now has them.
        ['Durham Radio News',  'https://www.durhamradionews.com/feed', 'durham'],
        ['The Oshawa Express', 'https://www.oshawaexpress.ca/feed/',   'durham'],
        ['The Trillium',       'https://www.thetrillium.ca/rss',       'ontario'],
    ],

    'stories' => [

        /* ---------------------------------------------------------- hero --- */
        [
            'title' => 'Kingston Road tower goes back for a fourth look',
            'slug' => 'pk-kingston-road-tower-fourth-look',
            'desk' => 'local-news', 'byline' => 'P. Nadeau', 'dateline' => 'PICKERING',
            'lede' => 'The density plan cleared committee in June. Two ward councillors now want the parking count and the shadow study reopened before the fall vote.',
            'image' => $img('waterfront.svg'),
            'image_caption' => 'The bay from the west spit, where the corridor study begins.',
            'image_credit' => 'Pickering Post',
            'featured' => 1, 'placement' => 'hero', 'views' => 3120, 'published' => $ago('-2 hours'),
            'tags' => 'council, development, kingston road',
            'body' => $p(
                'The density plan cleared committee in June with little discussion. It comes back to council on the fourteenth carrying two new questions, and both of them are about arithmetic.'
            )
            . $h('What changed since June')
            . $p(
                'The parking count in the original submission assumed a ratio the city has not applied to a building of this height since 2019. The shadow study was modelled on a nine-storey massing; the application is now eleven.',
                'Neither point was raised at committee. Both were raised by residents afterwards, in writing, and both were confirmed by staff as accurate.',
                'The ratio matters because it decides how much of the site goes to cars. At the 2019 figure the building needs roughly forty more spaces than the drawings show, and there is nowhere on the parcel to put them without losing the ground-floor units that made the proposal attractive in the first place.'
            )
            . $q('Nobody here is against the building. We are against approving a number that we already know is wrong.', 'A ward councillor, at committee')
            . $p(
                'Staff have recommended approval twice. Neither report addressed the ratio directly, and the applicant has not been asked to remodel the shadows.',
                'Council sits on the fourteenth. The question in front of it is narrow: send it back, or approve it as counted.',
                'The applicant has been co-operative throughout and has not objected to remodelling. What the applicant has objected to is the timeline: a deferral pushes the decision past the fall, and past the fall means past the construction window.',
                'That is a real cost and it is worth naming. It is also the cost of having asked the question in June rather than in August, which is when residents asked it, because the report that would have prompted it in June did not contain the ratio at all.',
                'Council sits at seven. The item is fourth on the agenda.'
            )
            . $p(
                'This is the fourth time the file has come back, and the four are not equivalent. The first return was for a traffic study. The second was for the affordable-unit count, which was resolved. The third was procedural — a notice period missed by two days.',
                'This one is different because nobody disputes the facts. Staff confirmed the ratio. The applicant has not contested it. What is being weighed is whether an acknowledged error is worth a season.',
                'The building itself has broad support on council. It replaces a parking lot, it puts a hundred and forty units on a transit corridor, and the ground-floor commercial is the kind the ward has been asking for since the last plan. None of that is in question either.',
                'Which is what makes the vote awkward. There is no side of the room that wants the project dead, and there is no side of the room that can explain why the number in the report is the number in the report.',
            ),
        ],

        /* --------------------------------------------------- top stories --- */
        [
            'title' => "Frenchman's Bay dredging pushed to spring after permit delay",
            'slug' => 'pk-frenchmans-bay-dredging-pushed-to-spring',
            'desk' => 'local-news', 'byline' => 'P. Nadeau', 'dateline' => '',
            'lede' => 'The sediment survey came back late, and boaters face a second season of shallow moorings.',
            'image' => $img('shoreline.svg'),
            'image_caption' => 'The lakeshore east of the bay.',
            'image_credit' => 'Pickering Post',
            'views' => 1440, 'published' => $ago('-5 hours'),
            'tags' => 'waterfront, harbour',
            'body' => $p(
                'The survey was commissioned in March and returned in July. The permit window it was meant to support closed in June.',
                'The harbour authority has begun telling members to plan for a second shallow season. For the deepest-draft boats at the west end that means hauling out early, again.',
                'The city says the work is funded and scheduled. Funded and scheduled is what it was last year.',
                'The delay is procedural rather than financial. The sediment survey has to be current for the permit application, the application has to clear a federal window, and the window closed while the survey was still with the consultant.',
                'The harbour authority has asked the city to commission next year\'s survey in November rather than March. The city has said it will consider it, which is the answer it gave last November.'
            )
            . $p(
                'The bay silts at a rate that has been measured since the nineties and has not changed much. What has changed is the permitting: work that used to be authorised locally now needs a federal sign-off that runs on a fixed annual window, and missing the window costs a year rather than a month.',
                'For the marina that means a second season of turning away deeper boats. For the sailing club it means the waiting list gets longer, which is a story elsewhere in this issue.',
            ),
        ],
        [
            'title' => 'Two more Liverpool Road runs added for the school year',
            'slug' => 'pk-two-more-liverpool-road-runs',
            'desk' => 'local-news', 'byline' => 'R. Sandhu', 'dateline' => '',
            'lede' => 'Service starts September 2, with the last northbound bus twenty minutes later than last year.',
            'image' => $img('go-station.svg'),
            'image_caption' => 'The platform at the morning peak.',
            'image_credit' => 'Pickering Post',
            'views' => 1120, 'published' => $ago('-8 hours'),
            'tags' => 'transit, schools',
            'body' => $p(
                'Two runs, both northbound, both in the window that matters: the second bell at Dunbarton and the last one at Pine Ridge.',
                'The later evening bus is the change riders asked for at the spring meetings. It is twenty minutes, and twenty minutes is the difference between catching it and calling someone.',
                'The service change is funded for the school year and reviewed in June.',
                'Ridership on the corridor is up about a fifth since the fare change, and almost all of that growth is students. The two added runs are a response to a pattern rather than a pilot, which is why they are funded for the year rather than the term.',
                'The riders who came to the spring meetings asked for three things: the later evening bus, a Saturday service, and a shelter at the north end. They got the first.'
            )
            . $p(
                'Durham Region Transit has been adding service on the corridor incrementally for three years rather than in a single restructuring, which is cheaper and slower and means the map still looks like it did in 2019 with more buses on it.',
                'The Saturday service the riders asked for is not funded. Staff have said it would require a route change rather than added hours, and route changes go through a separate review that starts in the winter.',
            ),
        ],
        [
            'title' => 'Waterfront trail extension survives the budget line',
            'slug' => 'pk-waterfront-trail-survives-budget',
            'desk' => 'local-news', 'byline' => 'the newsroom', 'dateline' => '',
            'lede' => 'It was on the list to defer. It came off the list on Tuesday night, and the reason was the underpass.',
            'image' => $img('trail.svg'),
            'image_caption' => 'The lit underpass, the week it opened.',
            'image_credit' => 'Pickering Post',
            'views' => 870, 'published' => $ago('-11 hours'),
            'tags' => 'trails, budget',
            'body' => $p(
                'The extension was one of six items staff flagged for deferral. Five of them were deferred.',
                'What kept this one alive was the underpass at the east end: deferring the trail would have left a lit, finished tunnel connecting nothing to nothing for two more years.',
                'Construction begins in the spring.',
                'The underpass was built first because it was the expensive part and because the grant that paid for it expired. That sequencing is normal and it is also how you end up with infrastructure that connects nothing: the hard piece goes in when the money exists, and the easy piece waits for a budget cycle that keeps deferring it.',
                'Three of the five items that were deferred are trails.'
            )
            . $p(
                'The trail network has been assembled this way for twenty years: a stretch at a time, whenever a grant or a development agreement pays for one. It is why the map has four gaps and why closing any one of them is disproportionately valuable.',
                'This extension closes the third of the four. The remaining gap is the hardest — it crosses private land — and nothing in this budget touches it.',
            ),
        ],
        [
            'title' => 'Fire crews clear the Brock Road industrial blaze',
            'slug' => 'pk-brock-road-industrial-blaze',
            'desk' => 'breaking', 'byline' => 'the newsroom', 'dateline' => 'BROCK ROAD',
            'lede' => 'No injuries. The building is a loss, and Brock Road was closed northbound for six hours.',
            'image' => $img('industrial.svg'),
            'image_caption' => 'Crews on scene shortly after five.',
            'image_credit' => 'Pickering Post',
'views' => 4300, 'published' => $ago('-90 minutes'),
            'tags' => 'fire, brock road',
            'body' => $p(
                'Crews were called shortly after four. The building was fully involved on arrival and the response went to a second alarm within twenty minutes.',
                'No injuries have been reported. Two neighbouring units were evacuated as a precaution and released before ten.',
                'The cause is under investigation. This story will be updated.',
                'Brock Road reopened northbound shortly after ten. The fire department has asked people to avoid the immediate area while crews remain on scene for hot spots.',
                'The unit was occupied by a light manufacturer. The company has not yet commented.'
            ),
        ],

        /* -------------------------------------------------------- events --- */
        [
            'title' => 'Esplanade Park summer concert, Thursday at seven',
            'slug' => 'pk-esplanade-park-summer-concert',
            'desk' => 'events', 'byline' => 'the newsroom', 'dateline' => '',
            'lede' => 'Free, bring a chair. The band shell moves indoors if the forecast turns.',
            'image' => $img('band-shell.svg'),
            'image_caption' => 'The band shell, last Thursday.',
            'image_credit' => 'Pickering Post',
            'views' => 640, 'published' => $ago('-1 day'),
            'tags' => 'music, park',
            'body' => $p(
                'Doors, such as they are, at six. The programme runs about ninety minutes with a break.',
                'If the forecast turns the whole thing moves to the rec complex, and the call is made by three on the day.'
            ),
        ],
        [
            'title' => "Farmers' market, Saturday from eight at the Nautical Village",
            'slug' => 'pk-farmers-market-nautical-village',
            'desk' => 'events', 'byline' => 'the newsroom', 'dateline' => '',
            'lede' => 'Twenty-eight vendors this week, plus the corn stand that sells out by ten.',
            'image' => $img('market.svg'),
            'image_caption' => 'The Saturday market, mid-morning.',
            'image_credit' => 'Pickering Post',
            'views' => 810, 'published' => $ago('-1 day 3 hours'),
            'tags' => 'market, food',
            'body' => $p(
                'Twenty-eight vendors, four of them new this season, and the usual queue at the corn stand from about nine.',
                'Parking is at the west lot. The bay path is the faster way in on a Saturday and always has been.'
            ),
        ],
        [
            'title' => 'Minor hockey registration closes Friday',
            'slug' => 'pk-minor-hockey-registration-closes',
            'desk' => 'events', 'byline' => 'the newsroom', 'dateline' => '',
            'lede' => 'The association added two U11 teams this year and is still short on coaches.',
            'views' => 520, 'published' => $ago('-1 day 7 hours'),
            'tags' => 'hockey, minor sport',
            'body' => $p(
                'Registration closes Friday at midnight and the association has said it will not extend it a third time.',
                'Two new U11 teams are going in, which is good news and creates the problem in the headline: both need a coach and an assistant, and neither has one.'
            ),
        ],

        /* --------------------------------------------- community spotlight --- */
        [
            'title' => 'Forty years of driving the Whitevale library van',
            'slug' => 'pk-forty-years-whitevale-library-van',
            'desk' => 'community', 'byline' => 'R. Sandhu', 'dateline' => 'WHITEVALE',
            'lede' => 'He knows which farm gate to honk at and which child is waiting for the next Wimpy Kid. On his last run this month, eleven families came out to the road.',
            'image' => $img('library-van.svg'),
            'image_caption' => 'The van on the Fifth Concession, second-last run.',
            'image_credit' => 'Pickering Post',
            'views' => 2980, 'published' => $ago('-2 days'),
            'tags' => 'library, whitevale, people',
            'body' => $p(
                'The route has not changed much in forty years. The stops have: three farms became subdivisions, one subdivision became a stop of its own, and the school moved once.',
                'What has not changed is the honk. Two short at the gate, and whoever is waiting comes down the drive.'
            )
            . $q('People think it is about the books. It is a bit about the books. Mostly you are the only person some of these houses see on a Tuesday.', 'On the route, forty years')
            . $p(
                'The van keeps running. The library has hired a replacement and he has been riding along since June, learning which gates get the honk.',
                'The van itself is on its third body and second engine. The route sheet is the same laminated card, annotated in four colours of pen, and it is being handed over rather than retyped.',
                'On the second-last run a family flagged it down half a kilometre before the stop, which is not allowed and has never once been refused.'
            )
            . $p(
                'The route was designed in 1985 for a township that had eleven working farms on it. It has three now. The stops that replaced them are subdivisions where the van parks at a corner and a dozen children arrive at once, which is a different job and one he says he likes less and does anyway.',
                'The library measures the route by circulation, and by that measure it has been marginal for a decade. It has survived four budget reviews. Each time, the deputation that saves it is made up of people from the road.',
            ),
        ],
        [
            'title' => 'The rink volunteers who have flooded the same pad since 1998',
            'slug' => 'pk-rink-volunteers-since-1998',
            'desk' => 'community', 'byline' => 'R. Sandhu', 'dateline' => '',
            'lede' => 'Four of them, most nights from December, and a hose they have repaired more times than they will admit.',
            'views' => 690, 'published' => $ago('-4 days'),
            'tags' => 'volunteers, rink',
            'body' => $p(
                'The pad is city land and the ice is not a city program. It never has been.',
                'Four people flood it, most nights, from the first hard freeze until it goes. The city supplies the water and, since a council motion two years ago, the boards.',
                'They have been asked several times whether they want it made official. They have said no every time, on the grounds that official comes with a schedule.',
                'The pad has been flooded every winter but one since 1998. The exception was 2012, when the hose finally gave out in January and the replacement took three weeks to arrive.',
                'Two of the four have children who learned to skate on it. One of those children now helps with the flooding.'
            )
            . $p(
                'The city has offered twice to take the pad on. Both times the offer came with a maintenance schedule, a liability review and a start date, and both times the four said no on the same grounds: the ice goes in when it is cold enough, not when a calendar says so.',
                'It is a small argument about a small pad and it is also the whole difference between a service and a neighbourhood.',
            ),
        ],

        /* -------------------------------------------------------- sports --- */
        [
            'title' => 'Dunbarton takes the regional final on a shootout',
            'slug' => 'pk-dunbarton-takes-regional-final',
            'desk' => 'sports', 'byline' => 'the newsroom', 'dateline' => '',
            'lede' => 'Nine rounds, two posts, and a goaltender who had not played a shootout all season.',
            'views' => 1580, 'published' => $ago('-16 hours'),
            'tags' => 'hockey, high school',
            'body' => $p(
                'Regulation finished level and so did the extra period. The shootout went nine rounds.',
                'The winner came off the far post and in. The save that set it up came two shooters earlier, on a glove hand that the goaltender said afterwards she had "mostly guessed with".',
                'It is the school\'s first regional title since 2011 and the first for this group, which loses four players to graduation in June.',
                'The provincial round begins in three weeks.'
            )
            . $p(
                'The team finished fourth in its division and was not expected to be here. It won the quarter-final in overtime and the semi on a goal with nineteen seconds left, which by the standards of this run was comfortable.',
            ),
        ],
        [
            'title' => 'The sailing club is out of moorings and out of patience',
            'slug' => 'pk-sailing-club-out-of-moorings',
            'desk' => 'sports', 'byline' => 'P. Nadeau', 'dateline' => '',
            'lede' => 'The waiting list is at sixty-one. The dredging that would open the shallow end is the story two pages back.',
            'views' => 470, 'published' => $ago('-3 days'),
            'tags' => 'sailing, harbour',
            'body' => $p(
                'Sixty-one names, up from thirty-eight two seasons ago, and the club has stopped quoting a wait in years.',
                'The shallow end could take a dozen boats if it were dredged. It has not been dredged, for the reasons set out elsewhere in this issue.'
            ),
        ],

        /* ------------------------------------------------------ business --- */
        [
            'title' => 'Two farm stands open early on the Fifth Concession',
            'slug' => 'pk-two-farm-stands-open-early',
            'desk' => 'business', 'byline' => 'the newsroom', 'dateline' => '',
            'lede' => 'The corn is three weeks ahead, and both stands say they will run out before Labour Day.',
            'views' => 720, 'published' => $ago('-22 hours'),
            'tags' => 'farms, food',
            'body' => $p(
                'A warm May and a wet June put the corn three weeks in front of a normal year.',
                'Both stands opened in the first week of August. Both expect to be finished before the long weekend, which has not happened in either of their memories.'
            )
            . $p(
                'An early corn crop is not straightforwardly good news. It compresses the selling season, it collides with the wholesale market\'s schedule, and it means the roadside stands are finished before the traffic that pays for them arrives on the long weekend.',
                'Both stands said they would rather have a normal August.',
            ),
        ],
        [
            'title' => 'A vacant unit on the main street finds a tenant after two years',
            'slug' => 'pk-vacant-unit-finds-tenant',
            'desk' => 'business', 'byline' => 'the newsroom', 'dateline' => '',
            'lede' => 'It has been a bank, a phone shop and empty. In October it becomes a bakery.',
            'views' => 540, 'published' => $ago('-5 days'),
            'tags' => 'main street, retail',
            'body' => $p(
                'The unit has been empty since the phone shop left in 2024. Before that it was a bank for thirty-one years, which is why it has a vault.',
                'The bakery is keeping the vault. The owners say it is the coolest room in the building and they intend to prove a use for it.'
            )
            . $p(
                'The main street has four vacancies, down from seven at this time last year. The improvement is real and it is also small enough that a single tenancy moves the number.',
                'The bakery is the second food business to take a long-empty unit this year, which the downtown association has started describing as a trend and which is, at two, not yet one.',
            ),
        ],

        /* ------------------------------------------------------- opinion --- */
        [
            'title' => 'The parking count is the whole argument, and council knows it',
            'slug' => 'pk-parking-count-is-the-argument',
            'desk' => 'opinion', 'byline' => 'the editorial board', 'dateline' => '',
            'lede' => 'A number that everyone agrees is wrong should not survive a vote because reopening it is inconvenient.',
            'image' => $img('council.svg'),
            'image_caption' => 'The chamber, before a Tuesday meeting.',
            'image_credit' => 'Pickering Post',
'views' => 1260, 'published' => $ago('-7 hours'),
            'tags' => 'council, development, comment',
            'body' => $p(
                'Staff confirmed the ratio is out of date. The applicant has not disputed it. Two councillors have asked for it to be recalculated. None of that is in question.',
                'What is in question is whether a plan can clear a vote carrying a figure its own authors would not defend, on the grounds that fixing it costs a meeting.'
            )
            . $q('Send it back. One meeting is cheaper than eleven storeys of being wrong.', 'The editorial board')
            . $p(
                'This paper has no view on whether the tower should be built. It has a firm view on approving numbers nobody believes.',
                'The argument against deferral is that it costs the applicant a construction season. That is true and it is not trivial. It is also the predictable consequence of a staff report that omitted the figure, and the applicant is not the party that omitted it.',
                'If the ratio is defensible, defend it in the report and vote. If it is not, the vote is being asked to ratify something the room already knows is wrong, and no construction schedule is worth that precedent.'
            )
            . $p(
                'There is a version of this council that would defer, fix the number, and vote in November with everyone\'s dignity intact. There is another version that approves it on the fourteenth and spends two years explaining the parking to people who live beside it.',
                'The second version is faster and it is the one the schedule rewards. That is precisely why it should be resisted: a schedule is not a reason, and the residents who found the error did the work the report was supposed to do.',
            ),
        ],

        /* ---------------------------------------------------- obituaries --- */
        [
            'title' => 'Margaret Ellen Ferrier, 1938–2026',
            'slug' => 'pk-margaret-ellen-ferrier',
            'desk' => 'obituaries', 'byline' => 'submitted', 'dateline' => '',
            'lede' => 'Teacher, choir director, and for nineteen years the voice that read the fair results over the public address.',
            'views' => 1890, 'published' => $ago('-2 days 8 hours'),
            'tags' => 'obituary',
            'body' => $p(
                'Margaret taught at three schools in the township and directed the choir at the united church for twenty-six years.',
                'For nineteen of those years she read the fair results over the public address on the Saturday, in a voice that several generations can still hear.',
                'A service will be held at the church. In lieu of flowers, the family asks for donations to the library.',
                'She is survived by two daughters, a son, seven grandchildren, and a great many former students who have been writing to this paper since Tuesday.'
            )
            . $p(
                'She retired from teaching in 1998 and from the choir in 2016, and told the church she would keep reading the fair results as long as they kept asking. They kept asking until last year.',
            ),
        ],
    ],
];
