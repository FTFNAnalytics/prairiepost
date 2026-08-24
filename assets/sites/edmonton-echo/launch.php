<?php
/**
 * The Edmonton Echo — launch content package.
 * Loaded once by `PP_SITE=edmonton-echo php tools/seed-launch.php`.
 * Everything here is launch demonstration content in the paper's voice;
 * the newsroom replaces it with real reporting.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$img = fn (string $file) => '/assets/sites/edmonton-echo/img/' . $file;

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['edmontonecho.com'],

    /* --- Desks the Echo adds to the network (created only if missing) ----- */
    'desks' => [
        ['name' => 'Sports',  'slug' => 'sports',  'color' => '#1D5C8C', 'description' => 'The rinks, the diamonds, and who pays for the ice.'],
        ['name' => 'Culture', 'slug' => 'culture', 'color' => '#7A4E9E', 'description' => 'Stages, galleries, festivals, and the rooms that hold them.'],
    ],

    /* --- Settings (written only over untouched defaults) ------------------ */
    'settings' => [
        'site_title'       => 'The Edmonton Echo',
        'tagline'          => 'Edmonton, first thing',
        'meta_description' => 'An independent daily for Edmonton: city hall, courts, transit, the neighbourhoods and the games — reported plainly, delivered before the day starts.',
        'footer_line'      => 'An independent daily for Edmonton: city hall, courts, transit, the neighbourhoods and the games — reported plainly, delivered before the day starts.',
        'contact_email'    => 'tips@edmontonecho.com',
        'regions'          => json_encode([
            'edmonton' => 'Edmonton',
            'alberta'  => 'Alberta',
            'canada'   => 'Canada',
        ]),
        'weather_today'    => json_encode([
            'temp' => '24°C', 'hi' => '27°', 'lo' => '13°',
            'line' => 'Hazy sun, wind light out of the southeast. Smoke possible by evening.',
            'fact_label' => 'Air quality', 'fact' => '4 — Moderate',
        ]),
        'traffic_items'    => json_encode([
            ['Yellowhead closures at 97 Street', '#'],
            ['Groat Road single lane at the bridge', '#'],
            ['LRT replacement buses after 10 p.m.', '#'],
        ]),
        'events_items'     => json_encode([
            ['Downtown farmers\' market, Saturday', '#'],
            ['City council meets Tuesday, 9:30 a.m.', '#'],
            ['Community league registration opens', '#'],
        ]),
    ],

    /* --- Wire sources (added only if the URL is new to the network) ------- */
    'sources' => [
        ['St. Albert Gazette',   'https://www.stalbertgazette.com/rss',   'edmonton'],
        ['Taproot Edmonton',     'https://www.taprootedmonton.ca/feed',   'edmonton'],
        ['Daily Hive Edmonton',  'https://dailyhive.com/feed/edmonton',   'edmonton'],
    ],

    /* --- Launch stories ---------------------------------------------------- */
    'stories' => [
        [
            'title' => 'Two hours on curbside parking, and council finally says what downtown is for',
            'desk' => 'news', 'dateline' => 'City Hall', 'byline' => 'Mara Solberg',
            'lede' => 'The pilot prices a parking spot at what the space is worth. The debate priced everything else.',
            'image' => $img('photo-02.svg'), 'image_caption' => 'City hall at first light, before the Tuesday meeting.', 'image_credit' => 'Staff illustration',
            'featured' => 1, 'placement' => 'hero', 'views' => 128, 'published' => $ago('-2 hours'),
            'tags' => 'city hall, parking, downtown',
            'body' => $p(
                'Council spent two hours Tuesday on a one-year curbside pricing pilot covering eleven blocks, and by the end had said more about what downtown is for than any strategy document has managed in a decade.',
                'The pilot is simple enough: sensors on the meters, prices that drift up when a block is full and down when it empties, capped at double the current rate. The administration\'s case ran on one number — a third of downtown traffic at peak is people circling for a spot that does not exist at the posted price.',
                'The objections were not about parking. They were about who the curb belongs to: the driver stopping for ten minutes, the restaurant that needs the loading zone, the resident paying for a permit, or the bus that could move forty people through the same six metres. "We are not arguing about meters," one councillor said, to the only laugh of the afternoon. "We are arguing about the most contested square metre in the city."',
                'The vote went 9–4. The sensors go in after the September long weekend, and the first price report comes back to committee in the new year — with, the motion specifies, the circling number counted again.'
            ),
        ],
        [
            'title' => 'The crosstown bus map gets its first redraw in a decade',
            'desk' => 'news', 'dateline' => 'Downtown', 'byline' => 'Theo Antchak',
            'lede' => 'Fewer routes, straighter lines, and a promise that the ones that remain will actually come.',
            'image' => $img('photo-04.svg'), 'image_caption' => 'A Capital Line train crossing the river valley at dusk.', 'image_credit' => 'Staff illustration',
            'featured' => 0, 'placement' => 'featured', 'views' => 96, 'published' => $ago('-5 hours'),
            'tags' => 'transit, buses, planning',
            'body' => $p(
                'The draft network map released Thursday takes the city\'s tangle of crosstown bus routes — some tracing streetcar lines that stopped running before the war — and redraws it around a blunt trade: fewer routes, running more often.',
                'Sixteen routes disappear. The ones that remain run on a grid, every fifteen minutes or better through the day, feeding the LRT instead of duplicating it. Planners say nine in ten current riders end up within 400 metres of frequent service; the tenth is why the public sessions will be loud.',
                'The pattern is familiar from other cities, and so is the argument. Coverage advocates say a route you can walk to matters more than a fast one you cannot; frequency advocates answer that a bus every forty minutes is a route in name only. "A map that promises everything hourly," the project lead said, "is a map nobody can use without a spreadsheet."',
                'Open houses run through October, with the final map to council in the spring. The fifteen-minute promise, staff confirmed, is written into the budget ask — which is where the map will actually be decided.'
            ),
        ],
        [
            'title' => 'Transit operating grants get a new formula, and the cities do the math',
            'desk' => 'politics', 'dateline' => 'Legislature', 'byline' => 'Renata Okafor',
            'lede' => 'The new money follows ridership instead of population. For a city whose trains are full, that changes the ask.',
            'image' => $img('photo-03.svg'), 'image_caption' => 'The legislature grounds from the high bank of the river.', 'image_credit' => 'Staff illustration',
            'featured' => 0, 'placement' => 'featured', 'views' => 84, 'published' => $ago('-8 hours'),
            'tags' => 'legislature, transit, provincial budget',
            'body' => $p(
                'The province posted the new transit operating grant formula Friday afternoon — the traditional hour for numbers someone hopes will not be read closely — and city finance staff spent the weekend reading it closely.',
                'The old grant divided the pot by population. The new one weights boardings at forty per cent, which rewards systems people actually ride and quietly penalizes ones built for ribbon cuttings. Edmonton\'s early arithmetic lands about $18 million a year better off, phased over three years.',
                'The catch is in the definitions. Boardings are counted from fare data, and the formula is silent on transfers — a rider who takes a feeder bus to the train counts twice in one reading of the document and once in another. On a network being redrawn around feeder buses, the difference is not academic.',
                'Two city administrators, who asked not to be named because the file is before council, offered the same summary in different words: the formula is better, and better arrives in year three. Until then it is a promise, and promises do not run at fifteen-minute frequency.'
            ),
        ],
        [
            'title' => 'Heat through Thursday, then smoke — who gets it first and what changes',
            'desk' => 'weather', 'dateline' => '', 'byline' => 'Echo staff',
            'lede' => 'Three more days near 27, an air quality statement waiting in the wings, and a cooldown that arrives with the weekend.',
            'image' => $img('photo-01.svg'), 'image_caption' => 'Downtown from the south bank, in the haze that means August.', 'image_credit' => 'Staff illustration',
            'featured' => 0, 'views' => 52, 'published' => $ago('-11 hours'),
            'tags' => 'weather, smoke, forecast',
            'body' => $p(
                'The pattern holding over the city is the August standard: highs near 27 through Thursday, overnight lows around 13, and a light southeast flow that keeps the afternoons pleasant and the evenings uncertain.',
                'The uncertainty is smoke. Fires burning northwest of the province have been sending plumes south all week, and the models split on whether Thursday evening\'s wind shift drags the haze over the city or slides it east of the Henday. The air quality index sits at 4 — moderate — with a special statement likely if the northern track wins.',
                'The practical version: morning runs and evening games are fine through Wednesday; Thursday evening plans should have an indoor option. Windows-open sleeping weather ends whenever the smoke arrives, and returns behind the front.',
                'That front comes through Saturday with a high of 19, a few millimetres of rain, and air that has been somewhere clean. The forecast block on our front page updates through the day; the full forecast link carries the hour-by-hour.'
            ),
        ],
        [
            'title' => 'A quarter of the avenue\'s empty storefronts filled this year. The leases explain why',
            'desk' => 'business', 'dateline' => 'Downtown', 'byline' => 'Callum Pryce',
            'lede' => 'Shorter terms, shared spaces, and landlords who stopped waiting for a bank branch to come back.',
            'image' => $img('photo-06.svg'), 'image_caption' => 'New awnings on the avenue, with the crane count holding at three.', 'image_credit' => 'Staff illustration',
            'featured' => 0, 'placement' => 'featured', 'views' => 71, 'published' => $ago('-1 day 3 hours'),
            'tags' => 'downtown, retail, storefronts',
            'body' => $p(
                'The vacancy report that crossed desks this week carries a number downtown has not seen in six years: a quarter of the avenue\'s empty storefronts signed tenants since January. The interesting part is not the number. It is the leases.',
                'The standard downtown retail lease — ten years, personal guarantees, rent set to what the block earned in 2014 — is quietly disappearing. What replaced it, in the deals that actually closed: two-year terms with renewal options, percentage rent in year one, and in four cases a landlord splitting one dead bank branch into three narrow bays.',
                'The tenants tell the same story from the other side. A bakery that outgrew a farmers\' market stall, a climbing shop, a repair café, an accounting collective that wanted a street door instead of a tower floor. None of them could have signed the old lease. All of them signed the new one.',
                'One property manager, asked what changed, gave the answer that explains most of it: "The owners stopped pricing the space they used to have and started pricing the street they actually have." The street, for the first time in a while, is pricing up.'
            ),
        ],
        [
            'title' => 'Minor hockey\'s ice bill rises eight per cent, and the leagues redraw the map',
            'desk' => 'sports', 'dateline' => 'Castle Downs', 'byline' => 'Jordan Belcourt',
            'lede' => 'The rate covers the refrigeration bill. The scramble is over who skates at 6 a.m. and who gets the Saturday slots.',
            'image' => $img('photo-05.svg'), 'image_caption' => 'First ice of the season at the twin rinks.', 'image_credit' => 'Staff illustration',
            'featured' => 0, 'views' => 63, 'published' => $ago('-1 day 8 hours'),
            'tags' => 'minor hockey, rinks, ice time',
            'body' => $p(
                'The city\'s arena rate letter went out to the minor hockey associations this week: eight per cent more an hour, effective with fall ice. Nobody disputes the reason — the refrigeration plants run on electricity, and electricity costs what it costs. The dispute is over everything downstream.',
                'Eight per cent on an hour of ice is eleven dollars. Across a season, for an association running forty teams, it is the difference between holding registration fees and adding sixty dollars a family — and the associations have spent the week choosing between those, publicly and not always gracefully.',
                'The quieter fight is the schedule. Prime ice — weekday evenings, Saturday mornings — is allocated on a formula that predates two new leagues and one amalgamation, and the rate increase has every association re-reading it. The north-side leagues want the formula reopened; the ones holding grandfathered slots have discovered a deep respect for tradition.',
                'The city\'s recreation branch, asked whether the formula will be reviewed, said the review is "ongoing," which every hockey parent in the city correctly understands to mean the 6 a.m. practices will be decided the way they always are: at a table, in a rink lobby, in October.'
            ),
        ],
        [
            'title' => 'The Ritchie hardware store changes hands, and keeps the key-cutting bench',
            'desk' => 'business', 'dateline' => 'Ritchie', 'byline' => 'Callum Pryce',
            'lede' => 'After thirty-four years, the Kolodziejs are selling to the couple who ran the paint counter. The dog stays too.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 58, 'published' => $ago('-2 days 2 hours'),
            'tags' => 'ritchie, main street, retail',
            'body' => $p(
                'The sign taped to the till Monday was eleven words long: same store, same hours, new owners as of October 1. After thirty-four years, Stan and Halina Kolodziej are selling Ritchie Hardware to Devon and Priya Mistry, who have run the paint counter for nine of them.',
                'The store is the last of its kind south of the river between the two big-box postal codes — the place that stocks the one washer, cuts the one key, and knows which furnace filter half the neighbourhood\'s houses take because half the neighbourhood\'s houses were built the same year.',
                'The Mistrys are financing the purchase through a vendor take-back, which is how these sales happen when the building is worth more than the business and the seller cares which one survives. "The bank wanted to talk about the land," Stan said Tuesday, restocking the wall of drawer pulls. "Devon wanted to talk about the key machine. That settled it."',
                'The transition plan, as posted beside the till: Stan works Saturdays through the winter, Halina keeps the books until the spring, and Wrench — the shop dog, fourteen, opinionated about couriers — remains in his chair by the window under an arrangement all parties describe as non-negotiable.'
            ),
        ],
        [
            'title' => 'Twelve leagues, one Zamboni: the schedule that keeps community ice alive',
            'desk' => 'community', 'dateline' => 'Westglen', 'byline' => 'Dee Cardinal',
            'lede' => 'The machine does a hundred and forty flood shifts a winter, towed between rinks on a schedule with its own constitution.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 47, 'published' => $ago('-2 days 7 hours'),
            'tags' => 'community leagues, outdoor rinks, volunteers',
            'body' => $p(
                'The document that keeps twelve community league rinks open every winter is four pages long, lives in a shared drive, and is referred to by everyone involved as the constitution. It is the flood schedule for one Zamboni, bought jointly in 2016, towed between neighbourhoods behind a 2009 pickup that is also jointly owned and separately argued about.',
                'The machine does about a hundred and forty flood shifts a winter. The constitution allocates them by a formula weighing rink size, boards or no boards, and — the clause that took three years of annual general meetings — whether a league actually staffed its shack the previous season. Ice for the leagues that show up; the leagues that show up, in turn, produce the ice.',
                'The system\'s founder, who stepped back last year after a decade as scheduler, described the job as "one-third logistics, two-thirds diplomacy, and every January somebody cries." Her successor inherited a laminated map, a group chat with 213 members, and the veto.',
                'This winter\'s draft schedule posts to the leagues Thursday. Amendments, per the constitution, require a seconder, a two-thirds vote, and — per tradition nobody can date but everybody enforces — a tray of squares brought to the meeting by the league proposing the change.'
            ),
        ],
        [
            'title' => 'A hundred seats on 118 Avenue: the Alberta Avenue playhouse books a full season',
            'desk' => 'culture', 'dateline' => 'Alberta Avenue', 'byline' => 'Noor Haddad',
            'lede' => 'Five companies, forty weeks, and a box office that fits in a cash tin. The neighbourhood theatre model, working.',
            'image' => $img('photo-07.svg'), 'image_caption' => 'The house lights up on the avenue.', 'image_credit' => 'Staff illustration',
            'featured' => 0, 'views' => 39, 'published' => $ago('-3 days 4 hours'),
            'tags' => 'theatre, alberta avenue, arts',
            'body' => $p(
                'The hundred-seat theatre in the old lodge hall on 118 Avenue announced its season Tuesday, and the announcement itself is the story: forty weeks booked, five resident companies, and for the first time since the room reopened, nothing dark between September and June.',
                'The model is deliberately small. The companies share the space, the light grid, and a technical director paid jointly through an arrangement the five artistic directors negotiate over one long dinner each spring. Rent is a percentage of the door, which means an empty week costs a company nothing and a full one funds the next.',
                'It is the inverse of the festival economy the city\'s theatre scene is famous for — eleven months of quiet around one loud fortnight. "The festival taught everyone here to produce a show for four hundred dollars," the venue\'s manager said. "We are just giving that skill somewhere to live the rest of the year."',
                'The season opens the last Friday of September with a new play set, the program notes say, "within walking distance of this chair." Tickets are twenty dollars, cash tin and card reader both accepted, and the lobby coffee remains, by long-standing house policy, free.'
            ),
        ],
        [
            'title' => 'The curb is the most contested square metre in the city',
            'desk' => 'opinion', 'dateline' => '', 'byline' => 'The editorial board',
            'lede' => 'Parking, patios, bike lanes, buses, loading zones: every fight is the same fight. Council finally had it honestly.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 31, 'published' => $ago('-3 days 9 hours'),
            'tags' => 'editorial, downtown, streets',
            'body' => $p(
                'Council spent two hours this week on eleven blocks of curbside parking, and the temptation is to file that under municipal tedium. Resist it. The curb argument is the whole city argument, conducted six metres at a time.',
                'Every claim on a downtown street is reasonable on its own. The driver wants ten minutes near the door. The restaurant wants its patio and its 11 a.m. delivery. The resident wants the permit spot her taxes imply. The bus wants a clear lane, because it is carrying more people than the forty parked cars beside it. The curb cannot honour all of these at once, and for decades the city\'s answer has been to pretend otherwise and let the parking enforcement office adjudicate the contradiction.',
                'Pricing the curb is not a war on anyone. It is the city admitting the space is scarce and valuable — which everyone fighting over it already believes, or they would not be fighting.',
                'The pilot will produce a number every previous debate has lacked: what a block of curb is actually worth, in dollars, by hour. We would like the same honesty applied to the rest of the right-of-way, and we suspect the residents of the eleven blocks — circling less, walking a little more — will get there first.'
            ),
        ],
    ],
];
