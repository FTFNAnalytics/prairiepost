<?php
/**
 * The Brampton Bulletin — launch package.
 * Loaded once by `PP_SITE=brampton-bulletin php tools/seed-launch.php`.
 * Identity, rails, wire sources, and launch stories with commissioned art;
 * the demonstration stories are launch content in the paper's voice, meant
 * to be replaced by real reporting.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$img = fn (string $file) => '/assets/sites/brampton-bulletin/img/' . $file;

return [

    'desks' => [
        ['name' => 'City Hall',     'slug' => 'city-hall',   'color' => '#0B6E4F', 'description' => 'Council, the budget, and every line item with your name on it.'],
        ['name' => 'Peel & Courts', 'slug' => 'peel-courts', 'color' => '#0B6E4F', 'description' => 'The region, the police board, the courthouse — where Brampton meets Peel.'],
        ['name' => 'Transit',       'slug' => 'transit',     'color' => '#0B6E4F', 'description' => 'The LRT, the Züm, the 410, and everything that moves — or doesn\'t.'],
        ['name' => 'Housing',       'slug' => 'housing',     'color' => '#0B6E4F', 'description' => 'Who gets to live here, what it costs, and what actually gets built.'],
        ['name' => 'Business',      'slug' => 'business',    'color' => '#0B6E4F', 'description' => 'Warehouses, main streets, and the logistics economy under the flight path.'],
        ['name' => 'Sports',        'slug' => 'sports',      'color' => '#0B6E4F', 'description' => 'From the rec-centre rinks to the pro leagues Brampton feeds.'],
        ['name' => 'Opinion',       'slug' => 'opinion',     'color' => '#201E1D', 'description' => 'Columns and argument. Signed, and ready to be argued with.'],
        ['name' => 'GTA',           'slug' => 'gta',         'color' => '#0B6E4F', 'description' => 'Mississauga to Vaughan to Union: the region Brampton commutes through.'],
    ],

    'settings' => [
        'site_title'         => 'The Brampton Bulletin',
        'tagline'            => 'Brampton first. The GTA in full.',
        'meta_description'   => 'Independent local journalism for Brampton and the western GTA — city hall, Peel Region, transit, housing, and the stories the big papers drive past.',
        'footer_line'        => 'Independent local journalism for Brampton and the western GTA — city hall, Peel Region, transit, housing, and the stories the big papers drive past.',
        'weather_line'       => 'Brampton, Ont. · 27°C · Humid, smog advisory',
        'contact_email'      => 'tips@bramptonbulletin.com',
        'newsletter_heading' => 'The Brief',
        'newsletter_copy'    => 'Five things, every weekday at 7 — what happened overnight in Brampton and across the GTA, why it matters, and what to watch at City Hall today.',
        'breaking_label'     => 'Budget 2027 — council debates the DC freeze',
        'breaking_url'       => '/story/council-votes-7-4-to-freeze-development-charges-for-two-years',
        'regions'            => json_encode([
            'brampton' => 'Brampton',
            'gta'      => 'Greater Toronto',
            'ontario'  => 'Ontario',
        ]),
    ],

    'sources' => [
        ['insauga',            'https://www.insauga.com/feed/',                  'brampton'],
        ['Bramptonist',        'https://www.bramptonist.com/feed/',              'brampton'],
        ['Global News Toronto','https://globalnews.ca/toronto/feed/',            'gta'],
        ['CBC Toronto',        'https://www.cbc.ca/webfeed/rss/rss-canada-toronto', 'gta'],
    ],

    'stories' => [

        /* ------------------------------------------------------ the lead --- */
        [
            'title' => 'Council votes 7–4 to freeze development charges for two years',
            'desk' => 'city-hall', 'dateline' => 'Brampton City Hall', 'byline' => 'Nav Sekhon',
            'lede' => 'After a marathon session that ran past 2:40 a.m., council bet that cheaper building fees will restart stalled projects — and left staff to find the missing revenue.',
            'image' => $img('photo-01.svg'), 'image_caption' => 'Council sat past 2:40 a.m. before the freeze carried.', 'image_credit' => 'Illustration for the Bulletin',
            'featured' => 1, 'placement' => 'hero', 'views' => 176, 'published' => $ago('-2 hours'),
            'tags' => 'council, development charges, budget 2027',
            'body' => $p(
                'The vote came at 2:41 a.m., after nine hours of debate, four recesses, and one procedural motion that briefly threatened to kill the whole thing: council voted 7–4 to freeze development charges at current rates for two years, rejecting a staff recommendation to raise them 34 per cent over the same period.',
                'The argument for the freeze is on every second block of the city: framed buildings with no crews on them. Builders told council that charges — the per-unit fees that fund the roads, pipes and rec centres growth requires — have become the margin between projects that proceed and projects that wait. Freeze the fees, the majority reasoned, and the stalled mid-rise pipeline starts moving again, bringing its assessment revenue with it.',
                'The argument against is arithmetic. Staff put the cost of the freeze at roughly $180 million in forgone revenue over the two years, against a capital plan that was already leaning on reserves. That money funds growth infrastructure or it doesn\'t; if it doesn\'t, either the projects wait or the general tax base picks them up. "We are not lowering the cost of growth tonight," the budget chair said before voting no. "We are moving it."',
                'What happens next is the part worth watching. Staff report back in ninety days with options for closing the gap, and the freeze carries a review clause: if housing starts don\'t improve by next fall, council can unwind it. The Bulletin will track both numbers — starts and reserves, quarter by quarter — because this is the rare council decision that comes with its own scoreboard.'
            ),
        ],

        /* -------------------------------------------- the brief, 01 to 05 --- */
        [
            'title' => 'Province orders a fresh study of the LRT tunnel — and the clock starts again',
            'desk' => 'transit', 'dateline' => '', 'byline' => 'Renee Ferraro',
            'lede' => 'The downtown tunnel option for the Hurontario extension gets another review before a route is locked. Downtown businesses get another year of not knowing.',
            'image' => $img('photo-05.svg'), 'image_caption' => 'The Hurontario line ends at the Gateway — for now.', 'image_credit' => 'Illustration for the Bulletin',
            'featured' => 0, 'placement' => 'featured', 'views' => 141, 'published' => $ago('-3 hours'),
            'tags' => 'LRT, downtown, transit',
            'body' => $p(
                'The Hurontario LRT extension into downtown Brampton is getting another study. The province has ordered a fresh review of the tunnelled option before any route is confirmed, adding an estimated twelve to eighteen months to a decision the city thought it had already debated to exhaustion.',
                'The choice hasn\'t changed since the last round: surface tracks up Main Street, cheaper and disruptive to build, or a tunnel under the Etobicoke Creek floodplain and the downtown core, quieter at street level and enormously more expensive. What has changed is the cost gap — early figures put the tunnel at more than triple the surface option — and the province\'s appetite for signing anything before it knows which number it is signing.',
                'For downtown, the study is the story. Property owners along Main have been holding decisions — leases, renovations, sales — through two councils\' worth of route debates, and every restart resets their planning horizon too. The downtown business association\'s position, delivered flatly at the last public meeting: any route, surface or tunnel, is better than another year of neither.',
                'The review is due back at the end of next year. Until then the line ends where it ends, at the Gateway terminal on Steeles, and the last three kilometres to the GO station remain the most studied stretch of not-yet-transit in the region.'
            ),
        ],
        [
            'title' => 'Nine trucks, five yards, one pattern: the tow-truck fires are connected',
            'desk' => 'peel-courts', 'dateline' => 'Peel Region', 'byline' => 'Harjit Deol',
            'lede' => 'Investigators say the arsons that have burned nine tow trucks across the region since spring are linked — and tied to a fight over collision-scene territory.',
            'featured' => 0, 'placement' => 'featured', 'views' => 128, 'published' => $ago('-4 hours'),
            'tags' => 'arson, towing, Peel',
            'body' => $p(
                'The fires have followed a pattern: after midnight, one truck at a time, accelerant, no injuries. Nine tow trucks have burned in five different yards across Peel since April, and investigators confirmed this week what operators have assumed since the third one — the fires are connected, and they are about territory.',
                'The territory in question is the collision scene. Under the current first-come dispatch system, the tow that reaches a crash first gets the job, the storage fees, and the repair referral that follows — a chain of revenue that can turn a single highway collision into thousands of dollars. Where the province has piloted regulated tow zones, the burn rate on trucks has dropped. Peel\'s pilot application is still in the queue.',
                'Operators who spoke to the Bulletin — none of whom would be named, for reasons the nine burned trucks make self-explanatory — describe an industry where legitimate businesses are being squeezed between insurers who suspect every invoice and competitors who negotiate with jerry cans.',
                'The investigation continues, and the regional police board has asked for a report on accelerating the tow-zone application. The Bulletin\'s courts desk will follow both files: the one that ends in charges, and the one that ends in a dispatch system where being first no longer means being flammable.'
            ),
        ],
        [
            'title' => 'ER waits in Brampton hit 9.4 hours — the worst number in a bad provincial quarter',
            'desk' => 'peel-courts', 'dateline' => '', 'byline' => 'Anisa Rahman',
            'lede' => 'The latest provincial figures put Brampton\'s emergency waits at the top of the chart again, in a city still running one full-service hospital per 700,000 people.',
            'featured' => 0, 'placement' => 'featured', 'views' => 119, 'published' => $ago('-5 hours'),
            'tags' => 'health care, ER waits, hospital capacity',
            'body' => $p(
                'The provincial quarterly data landed this week, and Brampton\'s line on the chart is where it usually is: on top. Median time to admission from the city\'s emergency departments hit 9.4 hours last quarter — the longest in the province, in a quarter when the provincial average itself got worse.',
                'The number is a symptom; the arithmetic is the disease. Brampton has grown by roughly a city of Guelph since its last full-service hospital opened, and the beds-per-capita figure that results sits at a fraction of the provincial average. Every winter surge, every long weekend, every smog advisory lands on the same emergency departments, and the queue does what queues do.',
                'The fix everyone agrees on — a second full-service hospital, with an expanded second phase already promised — moves at the speed of provincial capital planning, which is to say it is perpetually five to seven years away. City council has passed motions. The region has passed motions. The motions do not admit patients.',
                'What the quarterly numbers offer is the one thing motions can\'t: a public scoreboard. The Bulletin will publish the ER figure every quarter it is released, in this space, without adjectives. The number is doing the arguing on its own.'
            ),
        ],
        [
            'title' => 'Twelve more buses for the 501: Züm adds capacity as Queen Street crowding tops the complaint file',
            'desk' => 'transit', 'dateline' => '', 'byline' => 'Renee Ferraro',
            'lede' => 'Transit\'s busiest corridor gets its biggest single service boost in years. Riders say it\'s about eighteen months late.',
            'image' => $img('photo-06.svg'), 'image_caption' => 'The morning queue at Queen and Main, before the first artic arrives.', 'image_credit' => 'Illustration for the Bulletin',
            'featured' => 0, 'placement' => 'featured', 'views' => 104, 'published' => $ago('-6 hours'),
            'tags' => 'Züm, bus service, Queen Street',
            'body' => $p(
                'Twelve additional buses are coming to the Züm 501 Queen corridor this fall, the largest single service increase on one route in the system\'s history. Peak frequency drops to a bus roughly every four minutes — which regulars will recognize as the frequency the corridor needed some time ago.',
                'The 501 is the workhorse of a workhorse system. Brampton Transit has posted some of the fastest ridership growth in the country for most of a decade, and Queen Street carries the heaviest share of it: the corridor moves more people per day than some GO rail lines, in buses that riders photograph at crush load and post with captions the Bulletin cannot reprint.',
                'The twelve buses are the easy half of the fix. The hard half is the street itself — Queen\'s bus lanes exist in segments, and every gap returns the 501 to mixed traffic exactly where traffic is worst. The corridor study that would extend the lanes end-to-end is finished; what it needs is a construction budget, and that file now sits in the same capital plan the development-charge freeze just squeezed.',
                'Service changes take effect with the fall board. The Bulletin\'s transit desk will be riding the corridor the first week — front seat, stopwatch, before-and-after — and will print what the four-minute promise looks like at eight in the morning.'
            ),
        ],
        [
            'title' => 'GO fare integration slips again — full rollout now pointed at 2028',
            'desk' => 'transit', 'dateline' => '', 'byline' => 'Bulletin staff',
            'lede' => 'The plan to make a GO trip cost the same as the bus ride to reach it has moved its target date for the third time.',
            'featured' => 0, 'views' => 93, 'published' => $ago('-7 hours'),
            'tags' => 'GO Transit, fares, Metrolinx',
            'body' => $p(
                'The provincial program to fully integrate GO fares with local transit — one tap, one fare, no double payment at the station — has quietly moved its completion target to 2028, the third revision since the program was announced.',
                'For Brampton the stakes are specific. The city\'s commuters make one of the region\'s heaviest transfers between local buses and the Kitchener GO line, and the current discount structure still leaves a gap that adds up, over a commuting year, to hundreds of dollars per rider. Full integration was supposed to close it; the closing keeps moving.',
                'The technical explanation involves fare-system procurement and revenue-sharing formulas among a dozen transit agencies, and the technical explanation has been the same for three years. The practical effect is simpler: the region\'s cheapest congestion fix — making the train cost what the bus costs — remains announced rather than implemented.',
                'The Bulletin has asked the province for the revised schedule\'s milestones and will publish them when they arrive. In the meantime, the double fare survives another fiscal year, and the 407 stays full of people doing the math correctly.'
            ),
        ],

        /* --------------------------------------------------- the three-up --- */
        [
            'title' => 'The 410 widening starts in March. The detours start now.',
            'desk' => 'transit', 'dateline' => '', 'byline' => 'Renee Ferraro',
            'lede' => 'Four years of construction on the city\'s spine begins with lane closures this winter — and a traffic plan that leans hard on streets that are already full.',
            'image' => $img('photo-02.svg'), 'image_caption' => 'The 410 at Queen, where the first barrels go down.', 'image_credit' => 'Illustration for the Bulletin',
            'featured' => 0, 'views' => 88, 'published' => $ago('-8 hours'),
            'tags' => 'Highway 410, construction, traffic',
            'body' => $p(
                'The province has confirmed a March start for the widening of Highway 410 through the middle of the city — one additional lane each way plus rebuilt interchanges, staged over four construction seasons. The first overnight lane closures begin this winter, which means the practical start date is now.',
                'Nobody disputes the need. The 410 is Brampton\'s spine, and it fails on schedule twice a day; the widening has been on the regional wish list since the lanes it adds would have been sufficient. The dispute is about the four years in the middle, when the highway carries construction staging on top of its existing overload.',
                'The traffic management plan routes diverted trips onto Kennedy, Dixie and Bramalea — arterials that, residents pointed out at the public session, do not currently have spare capacity to receive a highway\'s overflow. The province\'s consultants project "manageable delay increases." The people who drive Kennedy at 8 a.m. project otherwise, with more confidence and better local data.',
                'The Bulletin will publish the closure schedule each week for the duration — a small service for a long project. Clip it, or better, check it before you leave. The first weekend closure is the last one anybody will be surprised by.'
            ),
        ],
        [
            'title' => 'A year of legal fourplexes, nine actually built: what\'s stalling gentle density',
            'desk' => 'housing', 'dateline' => '', 'byline' => 'Anisa Rahman',
            'lede' => 'Council legalized fourplexes citywide to real fanfare. The permit data says the hard part was never the zoning.',
            'image' => $img('photo-03.svg'), 'image_caption' => 'One of the nine: a fourplex rising between postwar singles.', 'image_credit' => 'Illustration for the Bulletin',
            'featured' => 0, 'views' => 82, 'published' => $ago('-9 hours'),
            'tags' => 'fourplexes, zoning, housing',
            'body' => $p(
                'A year after council legalized fourplexes on residential lots citywide — a vote that drew provincial praise and a packed gallery — the building department\'s data tells a quieter story: nine have been built. Applications number a few dozen. The zoning revolution is, so far, a rounding error.',
                'The Bulletin walked the file with three small builders to find the stall point, and it isn\'t the bylaw. It\'s everything the bylaw sits on: servicing charges calculated as if each unit were a detached house, a committee-of-adjustment queue for every lot that deviates an inch from the template, and financing — lenders still price a fourplex as a commercial project, which pushes small builders out exactly where small builders are the intended market.',
                'The city\'s own housing staff, to their credit, flagged most of this in the original report; the recommendations that would have addressed it — a pre-approved plan catalogue, a dedicated permit stream, servicing charges by bedroom rather than by unit — were deferred as follow-up work. They remain followed up on by no one.',
                'Nine buildings is not a failure of the idea; it is a measurement of the friction. The zoning was the visible half of the reform. The invisible half is scheduled for a staff report this winter, and the Bulletin will read it against the only metric that matters: the number after nine.'
            ),
        ],
        [
            'title' => 'Vacancy at 0.7%: Airport Road\'s warehouse boom is running out of warehouse',
            'desk' => 'business', 'dateline' => '', 'byline' => 'Terrence Obi',
            'lede' => 'The logistics corridor that powers Brampton\'s job market is effectively full — and the next million square feet is a fight over farmland.',
            'image' => $img('photo-04.svg'), 'image_caption' => 'The corridor at dusk: trucks, tarmac, and the last fields.', 'image_credit' => 'Illustration for the Bulletin',
            'featured' => 0, 'views' => 77, 'published' => $ago('-10 hours'),
            'tags' => 'logistics, warehouses, employment lands',
            'body' => $p(
                'Industrial vacancy along the Airport Road logistics corridor has fallen to 0.7 per cent — a figure that, in commercial real estate terms, means full. Every serviceable large-format building between the 407 and the airport lands is leased, and the waiting list is measured in years.',
                'The corridor is the quiet engine of the local economy: tens of thousands of jobs in warehousing, distribution and light manufacturing, anchored by the airport next door and the two highways that feed it. The boom made Brampton a logistics capital. The 0.7 per cent means the boom has consumed its own runway.',
                'What remains is the contested part. The undeveloped land at the corridor\'s edges is designated employment land in the official plan and working farmland in fact, and both identities have constituencies. Developers want servicing extended and approvals accelerated; a coalition of residents and farm operators wants the boundary held, arguing the city should intensify existing sites — taller warehouses, structured trailer parking — before it converts another field.',
                'Council will face the question directly when the corridor secondary plan returns this spring. The economics say the demand is real; the map says the land is finite; the debate, for once, is exactly what a planning debate should be about. The Bulletin will cover it from both sides of the fence line.'
            ),
        ],

        /* ------------------------------------------------ sports, opinion --- */
        [
            'title' => 'Playoff night downtown: the watch party outgrew the theatre, so it\'s taking the square',
            'desk' => 'sports', 'dateline' => '', 'byline' => 'Marcus Osei',
            'lede' => 'The city\'s basketball club plays its semifinal on the road Friday. Downtown is setting up the big screen anyway.',
            'featured' => 0, 'views' => 71, 'published' => $ago('-12 hours'),
            'tags' => 'basketball, downtown, watch party',
            'body' => $p(
                'The semifinal is a road game, which in this city has stopped mattering. When Brampton\'s basketball club tips off Friday night, the watch party downtown will be bigger than some of the crowds the league drew in its first season — the theatre sold out its screening in a day, so the city is putting the game on the big screen in the square outside too.',
                'The club\'s playoff run has been the summer\'s best civic mood. A roster with a distinctly local spine — three players came up through Brampton youth programs, a fact the announcers mention roughly every quarter — has turned casual downtown foot traffic into a recurring, jersey-wearing occupation of Main Street on game nights.',
                'The basketball case for Friday is real: the club took the season series and has the deeper bench, though the opponent\'s press has flustered them twice. The civic case is already settled. Restaurants along the square are extending patios; the last games added the kind of downtown evening crowd that a decade of revitalization reports promised and never quite delivered.',
                'Doors at the theatre at 6:30, square screen live at 7, tip-off at 7:30. Win and the final starts Tuesday — with home court, and with a downtown that has already demonstrated what it does on basketball nights.'
            ),
        ],
        [
            'title' => 'We keep calling Brampton a suburb. The census stopped agreeing in 2016.',
            'desk' => 'opinion', 'dateline' => '', 'byline' => 'Dev Chatterjee',
            'lede' => 'The ninth-largest city in the country funds itself, staffs itself and gets talked about like a bedroom community. The vocabulary is costing us money.',
            'featured' => 0, 'views' => 68, 'published' => $ago('-13 hours'),
            'tags' => 'column, census, city identity',
            'body' => $p(
                'Somewhere around 2016, by any measure the census keeps, Brampton stopped being a suburb. Ninth-largest city in the country. Larger than Hamilton or Quebec City. A transit system with big-city ridership growth, an economy that moves a startling share of everything Canadians order online, and a median age that makes it one of the youngest big cities in the country. The census noticed. The vocabulary didn\'t.',
                'Words allocate money. "Suburb" is why the hospital formula lags a decade behind the population it serves. "Suburb" is why the LRT stops at the city limit while the study season continues. "Suburb" is why every federal cultural grant program treats a city of three-quarters of a million as a catchment of somewhere else. Nobody builds a courthouse, a campus or a concert hall for a suburb; they build parking for one.',
                'We do it to ourselves, too. Half our civic arguments are conducted in borrowed terms — whether we are becoming "like Toronto," as though the alternative on offer were staying a fruit-belt town of the 1950s. The actual city in front of us — young, global, logistics-rich, chronically under-hospitaled — fits neither costume, and keeps paying for both.',
                'The correction is not a slogan; it is an accounting. Count the beds, the seats, the grants and the rail cars per capita, and send the gap to the two levels of government that have been rounding us down for twenty years. This paper\'s masthead says Brampton first. This column will keep the ledger.'
            ),
        ],

        /* ------------------------------------------------- around the GTA --- */
        [
            'title' => 'Dundas BRT hits its first expropriation fight as corridor buying begins',
            'desk' => 'gta', 'dateline' => 'Mississauga', 'byline' => 'Camille D\'Souza',
            'lede' => 'The busway\'s eastern segments need slices of forty properties. The first holdouts are testing what "fair market" means on a street mid-transformation.',
            'featured' => 0, 'views' => 55, 'published' => $ago('-15 hours'),
            'tags' => 'Dundas BRT, Mississauga, expropriation',
            'body' => $p(
                'Property acquisition for the Dundas bus rapid transit line has reached the contested stage: the project needs partial takings from about forty properties along its eastern Mississauga segments, and the first group of owners has rejected the opening offers, triggering the formal expropriation process.',
                'The dispute is less about whether the busway comes — that argument ended with the funding agreements — than about arithmetic on a street in transition. Owners argue their strips of frontage should be priced against what Dundas is becoming under its new corridor zoning, towers and all; the appraisals price what stands there today. Between those two numbers sits every expropriation hearing ever held.',
                'The project team insists the schedule holds regardless, since hearings can run parallel to early works. Veterans of the region\'s last few transit builds will recognize that sentence and reserve judgment.',
                'For Brampton readers the file is worth watching for its ending: the Dundas BRT is the westernmost link in a bus network that eventually meets our own Queen Street corridor. How fast Mississauga clears its right-of-way is one of the quiet variables in when a crosstown trip through the western GTA stops requiring a car.'
            ),
        ],
        [
            'title' => '35,000 homes, no water plan: Caledon\'s growth math has a missing column',
            'desk' => 'gta', 'dateline' => 'Caledon', 'byline' => 'Camille D\'Souza',
            'lede' => 'The province assigned the numbers. The servicing studies that would make them buildable are years behind the assignment.',
            'featured' => 0, 'views' => 49, 'published' => $ago('-16 hours'),
            'tags' => 'Caledon, growth, servicing',
            'body' => $p(
                'Caledon\'s provincially assigned housing target — tens of thousands of new homes over the planning horizon, most of them in a band along Mayfield Road on Brampton\'s northern border — has a gap that the town\'s own engineering consultants keep flagging: there is, as yet, no approved plan for the water and wastewater capacity the homes would require.',
                'The servicing for that band has to come from somewhere, and every option is a megaproject: extending the lake-based system north through Peel\'s trunk network, or new plants with their own approvals odyssey. The studies that would choose are underway; their completion dates trail the housing targets by years. Assignments first, feasibility later is a provincial tradition, but rarely this legible in one municipality.',
                'The consequence lands partly on Brampton. Mayfield is a shared border; the trunk sewers and arterial roads that would serve Caledon\'s growth band connect through infrastructure Brampton residents also use and Peel ratepayers jointly fund. A servicing plan that lags its housing means either delayed homes or hasty pipes, and the bill for hasty pipes has a way of being shared.',
                'The town council has asked the province to align the target timeline with the servicing studies. The province has acknowledged receipt. The Bulletin will check back when either the alignment or the homes materialize, whichever comes first.'
            ),
        ],
        [
            'title' => 'Peel commuters now make up 31% of Union Station\'s morning peak',
            'desk' => 'gta', 'dateline' => 'Toronto', 'byline' => 'Bulletin staff',
            'lede' => 'New station counts confirm what the platform crowds suggested: the region\'s commuting centre of gravity keeps sliding west.',
            'featured' => 0, 'views' => 44, 'published' => $ago('-17 hours'),
            'tags' => 'Union Station, GO Transit, commuting',
            'body' => $p(
                'Nearly one in three passengers arriving at Union Station in the morning peak now starts the trip in Peel, according to the latest station-count data — 31 per cent, the highest share in the count\'s history and up sharply from the pre-pandemic baseline.',
                'The number quantifies a shift the platforms already showed. Ridership recovery has been strongest on the corridors serving the western 905, where population growth is fastest and where hybrid-work schedules have settled into the three-day midweek pattern that fills trains Tuesday through Thursday. The Kitchener line, Brampton\'s corridor, posted the fastest growth of any line in the count.',
                'The planning implication cuts two ways. It strengthens the case for everything west-facing on the regional list — two-way all-day service on the Kitchener line above all. But it also describes a dependency: a third of the region\'s downtown workforce now funnels through infrastructure with little spare peak capacity, on corridors whose expansion projects are perennially one procurement away.',
                'The full count, with station-by-station tables, is in the regional data release. The Bulletin\'s takeaway for local readers is the trend line: the GTA\'s commuting map is being redrawn from the west, by the people who live here.'
            ),
        ],
        [
            'title' => 'The 427 extension will open early — and dump its traffic onto a road that isn\'t ready',
            'desk' => 'gta', 'dateline' => 'Vaughan', 'byline' => 'Bulletin staff',
            'lede' => 'The highway finishes ahead of schedule. The arterial network it feeds finishes on the original one.',
            'featured' => 0, 'views' => 39, 'published' => $ago('-18 hours'),
            'tags' => 'Highway 427, Vaughan, infrastructure',
            'body' => $p(
                'The northward extension of Highway 427 will open months ahead of schedule, the project consortium confirmed this week — a genuine rarity in Ontario infrastructure that comes with an asterisk the size of Major Mackenzie Drive.',
                'The extension ends at a boundary where the receiving arterial network is still being widened on its original timetable. For the gap period — most of a year — the new highway\'s traffic will decant onto two-lane rural cross-sections through Vaughan\'s employment lands, a configuration the region\'s own traffic modelling describes with the word "constrained," which is modelling for "avoid."',
                'The early opening is still, on balance, good news for the west GTA goods economy: the 427 corridor is the eastern twin of Brampton\'s own logistics spine, and every container that moves up it is one not circling through Peel on the 407 or the 50. Trucking dispatchers are already redrawing routes around the new terminus, asterisk included.',
                'The lesson for the region\'s project boards is the one they keep receiving: highways, arterials and transit each on their own schedule produce openings like this one — early, welcome, and pointed at a bottleneck. Synchrony remains the frontier.'
            ),
        ],

        /* -------------------------------------------------- second rotation --- */
        [
            'title' => 'The consultant line item is up 61% in five years. Council wants to know what it bought.',
            'desk' => 'city-hall', 'dateline' => 'Brampton City Hall', 'byline' => 'Nav Sekhon',
            'lede' => 'A routine audit-committee question turned into a full inventory of external studies — including several that duplicate work the city already owned.',
            'featured' => 0, 'views' => 36, 'published' => $ago('-20 hours'),
            'tags' => 'audit, consultants, city budget',
            'body' => $p(
                'It started as a single question at audit committee — how much does the city spend on external consultants? — and the answer arrived this week as a 40-page inventory: spending on outside studies, reviews and advisory work has grown 61 per cent over five years, well ahead of both inflation and the growth of the city\'s own staff complement.',
                'Some of the growth is defensible and the report says so: provincially mandated studies, specialized engineering the city can\'t keep in-house, peak-load work on the development pipeline. The committee\'s attention went to the other category — strategy refreshes, service reviews and feasibility studies that, in at least six cases the auditors flagged, substantially duplicated studies the city had commissioned within the previous decade and, in two cases, never implemented.',
                'The pattern the auditors describe is procedural: commissioning a study has become the default way to defer a decision, and the shelf where studies go afterward has no owner. Their sharpest recommendation is also the cheapest — a public registry of every commissioned study, its cost, and what was done with it.',
                'Council votes on the registry next month. The Bulletin supports it for professional reasons: we have been assembling roughly that inventory by freedom-of-information request, and the city doing it voluntarily would be both better government and considerably less postage.'
            ),
        ],
        [
            'title' => 'A transit levy, a parks levy, or neither: the budget\'s real fight arrives in the fall',
            'desk' => 'city-hall', 'dateline' => 'Brampton City Hall', 'byline' => 'Nav Sekhon',
            'lede' => 'With development-charge revenue frozen, the money for the capital plan has to come from somewhere. Staff will put two dedicated levies on the table.',
            'featured' => 0, 'views' => 31, 'published' => $ago('-22 hours'),
            'tags' => 'budget 2027, levy, capital plan',
            'body' => $p(
                'The development-charge freeze settled where the capital plan\'s money won\'t come from. The fall budget session will open the harder half of the question, and the Bulletin has confirmed that staff will put two options formally on the table: a dedicated transit infrastructure levy, and a dedicated parks-and-recreation levy, each a separate line on the tax bill with a published project list attached.',
                'The dedicated-levy model has spread through the GTA for a blunt reason: residents distrust general tax increases but will, polling and referenda suggest, pay for a named thing they can watch get built. The transit version would fund the Queen Street bus-lane completion and terminal expansions; the parks version, the rec-centre renewal backlog that every ward meeting hears about first.',
                'The opposition case writes itself, and several councillors are already writing it: a levy is a tax increase wearing a lanyard, and stacking one on top of the assessment growth the DC freeze is supposed to generate asks residents to fund the same growth twice. The freeze\'s champions and the levy\'s champions are, awkwardly, mostly the same councillors.',
                'Deliberations start in October. Between now and then, expect every number in this story to appear in a mailer, a town hall and at least one billboard. The Bulletin will publish the project lists behind both levies as soon as they are tabled, because a named tax deserves a named shopping list.'
            ),
        ],
        [
            'title' => 'The basement question: the city\'s second-unit registry knows about 12,000 apartments. The census implies triple that.',
            'desk' => 'housing', 'dateline' => '', 'byline' => 'Anisa Rahman',
            'lede' => 'Brampton\'s largest source of affordable housing is the one nobody fully counts — and the gap between the registry and reality is where both the safety and the housing policy live.',
            'featured' => 0, 'views' => 27, 'published' => $ago('-24 hours'),
            'tags' => 'second units, basement apartments, rental housing',
            'body' => $p(
                'Brampton\'s registered second-unit count crossed 12,000 this year — the largest legal secondary-suite stock in the region and a number the city cites with justified pride. The census-derived estimate of how many households actually live in second units here implies a figure closer to three times that. The distance between those numbers is the most important unmeasured quantity in the city\'s housing system.',
                'The registered basement apartment is one of the genuine policy successes of the past decade: inspected, fire-separated, legally tenanted, and renting well below anything the open market builds. The unregistered remainder is the same housing without the inspection — and its tenants sit outside every protection that registration triggers, from fire code to the tribunal.',
                'The barrier to registering, landlords told the Bulletin, is rarely ideology and usually arithmetic: retrofit costs to meet code, plus the suspicion — mostly outdated, not entirely — that raising your hand invites reassessment. Cities that have closed the gap paired amnesty windows with retrofit grants; Brampton has run the first without much of the second.',
                'A staff report on a renewed registration push is due this winter. The test the Bulletin will apply is simple: does it make the legal apartment cheaper to create than the illegal one is risky to keep? Until that inequality flips, the registry will keep counting the minority.'
            ),
        ],
    ],
];
