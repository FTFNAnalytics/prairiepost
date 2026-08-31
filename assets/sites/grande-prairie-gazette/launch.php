<?php
/**
 * The Grande Prairie Gazette — launch package.
 * Loaded once by `PP_SITE=grande-prairie-gazette php tools/seed-launch.php`.
 * Identity, rails, wire sources, and launch stories with commissioned art;
 * the demonstration stories are launch content in the paper's voice, meant
 * to be replaced by real reporting.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$img = fn (string $file) => '/assets/sites/grande-prairie-gazette/img/' . $file;

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['www.grandeprairiegazette.ca'],

    'desks' => [
        ['name' => 'Energy', 'slug' => 'energy', 'color' => '#1E3A6E', 'description' => 'Montney gas, the rigs, the royalties, and the towns they pay for.'],
        ['name' => 'Sports', 'slug' => 'sports', 'color' => '#1D5C8C', 'description' => 'The rinks, the diamonds, and who pays for the ice.'],
    ],

    'settings' => [
        // The byline every Hermes filing carries here. Without it the
        // server falls back to the generic 'Automated report'.
        'automated_byline'   => 'Gazette Newsroom Automation',
        'site_title'         => 'Grande Prairie Gazette',
        'tagline'            => "Peace Country's daily",
        'meta_description'   => 'Independent local reporting for Grande Prairie, the County and the Peace Country: council, courts, energy, agriculture and the games.',
        'footer_line'        => 'Independent local reporting for Grande Prairie, the County and the Peace Country.',
        'weather_line'       => 'Grande Prairie, AB · 21°C, clear',
        'contact_email'      => 'tips@grandeprairiegazette.ca',
        'newsletter_heading' => 'The Morning Aurora',
        'newsletter_copy'    => 'Everything that happened in Grande Prairie, in your inbox by 6 a.m. Free, five days a week.',
        'regions'            => json_encode([
            'peace'   => 'Peace Country',
            'alberta' => 'Alberta',
            'canada'  => 'Canada',
        ]),
        'events_items'       => json_encode([
            ['Bear Creek Folk Festival opens', '#', 'Aug 13', 'Muskoseepi Park · 5 p.m.'],
            ['Council committee: transit review', '#', 'Aug 15', 'City Hall · 9 a.m.'],
            ["Farmers' market, last summer date", '#', 'Aug 17', 'Montrose Cultural Centre · 9 a.m.'],
        ]),
    ],

    'sources' => [
        ['EverythingGP',          'https://everythinggp.com/feed/',                     'peace'],
        ['My Grande Prairie Now', 'https://www.mygrandeprairienow.com/feed/',           'peace'],
        ['Fairview Post',         'https://www.fairviewpost.com/category/news/feed',    'peace'],
    ],

    'stories' => [
        [
            'title' => 'Aurora season opens, and the county\'s dark-sky pullouts draw their first full night',
            'desk' => 'news', 'dateline' => 'Clairmont', 'byline' => 'Lena Marchuk',
            'lede' => 'A coronal mass ejection pushed the borealis far enough south to watch from a downtown parking lot. The county built four places to see it properly.',
            'image' => $img('photo-01.svg'), 'image_caption' => 'The fence line at the Clairmont pullout, a little after midnight.', 'image_credit' => 'Staff illustration',
            'featured' => 1, 'placement' => 'hero', 'views' => 132, 'published' => $ago('-2 hours'),
            'tags' => 'aurora, dark sky, county',
            'body' => $p(
                'The first serious geomagnetic storm of the season arrived Monday night, and by 11 p.m. the county\'s four dark-sky pullouts — gravelled, signed, and until this week mostly theoretical — held more vehicles than the arena on a Friday.',
                'The pullouts were a line item almost nobody argued about two budgets ago: a few loads of gravel, a fence, and signage on roads the county already maintained, at spots far enough from town that the sky goes properly black. The bet was that people who already park on approaches to watch the lights might as well do it somewhere with sightlines and no traffic.',
                'Monday suggested the bet lands. The northern curtain was visible from town — parking-lot visible, phone-camera visible — but the crowd drove out anyway, because ten minutes of highway is the difference between seeing the aurora and standing inside it. "In town you watch it," said one photographer set up along the fence line, who has chased the lights across three winters. "Out here it comes down around you."',
                'The forecast gives the storm two more nights, strongest between 10 p.m. and 2 a.m. The county asks watchers to park inside the fence, keep headlights down on arrival, and pack out what they bring — the pullouts have no bins, on purpose, and so far, no litter either.'
            ),
        ],
        [
            'title' => 'Four hours on the downtown plan, and the argument that mattered was lighting',
            'desk' => 'news', 'dateline' => 'City Hall', 'byline' => 'Dane Okafor',
            'lede' => 'Two more patrols got the headlines. The $1.4 million for lighting along 100 Avenue got the debate.',
            'image' => $img('photo-02.svg'), 'image_caption' => 'The avenue at dusk, where the new standards go in first.', 'image_credit' => 'Staff illustration',
            'featured' => 0, 'placement' => 'featured', 'views' => 104, 'published' => $ago('-5 hours'),
            'tags' => 'city hall, downtown, safety',
            'body' => $p(
                'Council passed the downtown safety plan Tuesday after four hours, and the vote that counted was not about patrols. The two added peace-officer shifts sailed through in twenty minutes. The $1.4 million for lighting along 100 Avenue took the rest of the night.',
                'The case for the lighting came from the plan\'s own data: the avenue\'s incident map and its lighting map are nearly the same picture. Where the old standards throw pools of orange forty metres apart, the calls cluster; the two blocks relit during the utility work in 2023 went quiet and stayed quiet.',
                'The case against was cost, and one councillor\'s sharper point — that a city can light an avenue brilliantly and only move a problem to the next dark block. The administration\'s answer was blunt: "Then we will light the next block. Concrete and conduit are the cheapest officers we will ever hire."',
                'The vote went 7–2. The first standards go in between 98 and 102 Street after the ground opens in spring, full-cutoff fixtures throughout — a specification the astronomy crowd asked for and, in a town that just built dark-sky pullouts, one nobody at the table argued with.'
            ),
        ],
        [
            'title' => 'The Bear Creek trail now runs to the north end, lit underpass and all',
            'desk' => 'news', 'dateline' => 'Bear Creek', 'byline' => 'Carly Beaulieu',
            'lede' => 'Three kilometres of new pavement close the gap that made the north end a driving destination. The underpass at 68 Avenue means nobody crosses four lanes to get there.',
            'image' => $img('photo-03.svg'), 'image_caption' => 'The new stretch north of the underpass, first week open.', 'image_credit' => 'Staff illustration',
            'featured' => 0, 'placement' => 'featured', 'views' => 88, 'published' => $ago('-9 hours'),
            'tags' => 'trails, bear creek, parks',
            'body' => $p(
                'The barricades came off the Bear Creek trail extension Thursday morning, and by Thursday evening the counter buried in the first kilometre had already logged more traffic than the old dead-end saw in a week.',
                'The extension is three kilometres of paved path along the creek\'s east bank, and it fixes the gap that has annoyed this city for a decade: a trail system famous enough to show up in relocation brochures that quietly stopped a forty-minute walk short of the north end. Residents up there could see the valley. Reaching it meant a car.',
                'The piece that took the money — and the two construction seasons — is the lit underpass at 68 Avenue. The cheap version of this project crossed at grade with a beg button. The built version goes under, wide enough for a grader to clear in winter, with lighting on the same full-cutoff standard the city now uses valley-wide.',
                'What remains is the connection every trail opening produces: the goat paths already forming between the pavement and three cul-de-sacs that can see it. Parks staff say the desire lines will be mapped in the fall and the obvious ones formalized next year — which, as any trail walker knows, is the system working exactly as it should.'
            ),
        ],
        [
            'title' => 'Montney permits climb as two operators add rigs near Wembley',
            'desk' => 'energy', 'dateline' => 'Wembley', 'byline' => 'Mattis Friesen',
            'lede' => 'Nineteen new licences west of the city this quarter, the most since 2022 — and this round comes with water plans attached.',
            'image' => $img('photo-04.svg'), 'image_caption' => 'A rig on the Wembley flats, first light.', 'image_credit' => 'Staff illustration',
            'featured' => 0, 'placement' => 'featured', 'views' => 76, 'published' => $ago('-13 hours'),
            'tags' => 'montney, drilling, wembley',
            'body' => $p(
                'The regulator\'s quarterly licence list landed this week with a number the service sector has been waiting on: nineteen new well licences in the Montney fairway west of the city, the strongest quarter since 2022, with two operators each moving an additional rig into the Wembley area before freeze-up.',
                'The pace shows up in town before it shows up in production data. The hotels along the bypass are back to weekday no-vacancy, the machine shops are hiring winter crews in September, and the county\'s road-use agreements — the paperwork that decides who fixes a haul route in March — are being signed at a clip the public works office calls "2018 with better manners."',
                'The better manners are in the licences themselves. Most of this round\'s applications came in with water-sourcing plans that lean on stored dugout and effluent supply rather than fresh river draws — partly regulation, partly memory. The dry years are recent enough that nobody wants to be the operator explaining a river intake in a drought summer.',
                'The county\'s reeve, asked what nineteen licences mean for the budget, did the arithmetic out loud: linear assessment follows the wells by about two years. "The rigs are welcome," she said. "The taxes are welcomer, and they run on a delay. We plan on the delay."'
            ),
        ],
        [
            'title' => 'Canola comes off two weeks early, and the elevators adjust on the fly',
            'desk' => 'agriculture', 'dateline' => 'County of Grande Prairie', 'byline' => 'Mattis Friesen',
            'lede' => 'A dry July moved the whole county\'s timeline up. The crop is lighter than hoped, drier than feared, and moving fast.',
            'image' => $img('photo-05.svg'), 'image_caption' => 'Swathing east of the city, two weeks ahead of the average.', 'image_credit' => 'Staff illustration',
            'featured' => 0, 'views' => 64, 'published' => $ago('-1 day 2 hours'),
            'tags' => 'canola, harvest, county',
            'body' => $p(
                'The swathers were running county-wide by the weekend, a full two weeks ahead of the long-term average, and the first canola deliveries crossed the scales Monday — dry enough to bin straight off the field, which is the one mercy of the summer that produced them.',
                'July did the deciding. Twenty-two millimetres of rain for the month pushed the crop through flowering in a hurry, and what the heat took from yield it returned in moisture content: samples so far are testing dry, saving growers the drying charges that ate into the last two wet-fall harvests.',
                'The elevators have flipped their schedules to match. Both county facilities moved their extended hours up by two weeks, and the line company\'s early-delivery contracts — usually a September conversation — were being amended by phone this week. Basis has narrowed accordingly; grain buyers like a crop they can load before the railways get busy.',
                'The yield picture will not be honest until the combines finish, but the coffee-row consensus is "average, and glad of it." As one grower put it at the scale Monday: "Two years ago I hauled tough grain in November. This year I\'ll be done by Labour Day. I know which problem I prefer."'
            ),
        ],
        [
            'title' => 'The junior club opens camp with 41 on the roster and one goalie question',
            'desk' => 'sports', 'dateline' => 'Grande Prairie', 'byline' => 'Travis Goudreau',
            'lede' => 'Two forwards signed out of the spring draft, a defence that returns almost whole, and a crease that belongs to whoever takes it.',
            'image' => $img('photo-06.svg'), 'image_caption' => 'Camp week: first skate under the banners.', 'image_credit' => 'Staff illustration',
            'featured' => 0, 'views' => 71, 'published' => $ago('-1 day 7 hours'),
            'tags' => 'junior hockey, camp, rink',
            'body' => $p(
                'The Wapiti Kings opened main camp Tuesday morning with 41 names on the board, and the shape of the season was visible by the second drill: the defence is back, the forwards are faster, and the crease is an open competition that everyone in the building is too polite to call a controversy.',
                'The blue line returns five of six regulars, which in this league — where a good defenceman is usually a departing defenceman — counts as a windfall. Up front, the two forwards signed out of the spring draft both skated with the veterans\' group by Wednesday, a promotion the coaching staff insists means nothing and every parent in the stands correctly read as meaning quite a lot.',
                'The goaltending is the honest question. Last year\'s starter aged out; the two candidates are a nineteen-year-old with junior experience and a sixteen-year-old the scouts have been quietly excited about since bantam. The coach\'s official position is that games will decide it. The unofficial schedule has them alternating through the exhibition slate, which is the same answer with dates attached.',
                'Camp runs through the weekend, with the intrasquad game Saturday at 5 — free admission, donations to the food bank at the door. The regular season opens at home the second Friday of September, and the club would like the town to know the new jerseys have, at long last, been delivered.'
            ),
        ],
        [
            'title' => 'The budget\'s health capital line, read from four hundred kilometres north',
            'desk' => 'politics', 'dateline' => 'Legislature', 'byline' => 'Joelle Tremblay',
            'lede' => 'The provincial capital plan names the regional hospital\'s expansion in year three. The region has learned to read year three carefully.',
            'image' => $img('photo-09.svg'), 'image_caption' => 'The legislature at dusk. Year three lives somewhere inside.', 'image_credit' => 'Staff illustration',
            'featured' => 0, 'views' => 55, 'published' => $ago('-1 day 11 hours'),
            'tags' => 'legislature, health care, capital plan',
            'body' => $p(
                'The provincial capital plan tabled Thursday carries a line the region has waited three budgets to see: the regional hospital\'s surgical expansion, named, costed, and scheduled — in year three of a three-year plan.',
                'Year three is the budget\'s most flexible neighbourhood. Year-one projects have contracts; year-two projects have design work; year-three projects have intentions, and intentions have been known to slide rightward a year at a time, indefinitely. The region\'s health advocates spent Thursday saying versions of the same sentence: pleased to be named, waiting for the tender.',
                'The arithmetic behind the ask has not changed. The hospital serves a catchment the size of a small province, its surgical wait-lists run past the provincial average in four of six tracked procedures, and the alternative to expansion is the status quo: patients driving five hours south for day surgery and hoteling on their own dime.',
                'The local MLAs, both government members, called the line item a commitment. The opposition health critic called it a press release with a decimal point. Both descriptions fit; the difference between them is a tender document, and the region will know which it got by this time next year.'
            ),
        ],
        [
            'title' => '$125,000 and nineteen months: how the pancake circuit funded two counsellors',
            'desk' => 'community', 'dateline' => 'Grande Prairie', 'byline' => 'Carly Beaulieu',
            'lede' => 'The Trumpeter Club set out to fund youth counselling without a gala, a naming right, or a single silent auction. It worked.',
            'image' => $img('photo-08.svg'), 'image_caption' => 'Breakfast forty-one of forty-one, cash tin at the door.', 'image_credit' => 'Staff illustration',
            'featured' => 0, 'views' => 47, 'published' => $ago('-2 days 3 hours'),
            'tags' => 'fundraising, youth, mental health',
            'body' => $p(
                'The cheque presented Tuesday at the Northbank youth clinic was for $125,000, and the club that raised it would like the record to show there was no gala involved. No gala, no naming rights, no silent auction of donated weekends at somebody\'s lake place. Pancakes, mostly.',
                'The Trumpeter Club — twenty-two members, average age the club declines to publish — set the target nineteen months ago after the clinic\'s waiting list for youth counselling passed ninety days. The money funds two counsellor positions for two years, which the clinic says converts the list from a queue into an appointment.',
                'The method was volume: forty-one pancake breakfasts, the concession at every home game the rink would give them, and a standing arrangement with three farm families who donate a steer a year and let the club keep the auction proceeds. The single biggest line item was $9,200 from one February breakfast that happened to coincide with a rig crew\'s days off.',
                'The clinic\'s director, accepting the cheque, noted the club had declined to have the counselling room named after it. The club president\'s answer is already local legend: "The room is for the kids. Put their art on the door. We\'re just the pancakes."'
            ),
        ],
        [
            'title' => 'FireSmart crews thin the creek corridor before the dry weeks arrive',
            'desk' => 'news', 'dateline' => 'County of Grande Prairie', 'byline' => 'Lena Marchuk',
            'lede' => 'Sixty hectares of brush and deadfall come out of the valley this month — the unglamorous work that decides how a bad day goes.',
            'image' => $img('photo-07.svg'), 'image_caption' => 'Limbing to head height along the corridor, skidder standing by.', 'image_credit' => 'Staff illustration',
            'featured' => 0, 'views' => 39, 'published' => $ago('-2 days 8 hours'),
            'tags' => 'firesmart, wildfire, creek corridor',
            'body' => $p(
                'The chainsaws started in the creek corridor Monday, and for the next month the valley\'s walkers will share the trails with crews doing the least photogenic work in wildfire management: cutting brush, limbing spruce to head height, and dragging two decades of deadfall out of the draws that run up toward the neighbourhoods.',
                'The corridor is the city\'s FireSmart priority for a plain reason — it is a wick. A creek valley full of cured grass and ladder fuel connects the open country south of town to backyards in four subdivisions, and fire behaviour models treat it accordingly. The sixty hectares in this year\'s program are the sections the models like least.',
                'The work is jointly funded through the provincial FireSmart program, with the city and county splitting the rest — an arrangement renewed annually with less argument each year. The springs of 2023 and 2024, when smoke sat on the town for weeks and two counties west of here evacuated, settled the debate about whether the money is worth it.',
                'Residents will notice the difference more than they might expect: the corridor will look opened-up, almost park-like, where the crews have been. The deadfall goes to the burn pile at the transfer station this winter. The trails stay open throughout, with short detours posted — and the crews ask walkers to keep dogs leashed near the work, because a skidder and a loose retriever disagree about right of way.'
            ),
        ],
        [
            'title' => 'Clear nights, harvest days: the week ahead, with an aurora watch attached',
            'desk' => 'weather', 'dateline' => '', 'byline' => 'Gazette staff',
            'lede' => 'Highs near 22, overnight lows flirting with 4, and two more nights of geomagnetic activity worth losing sleep over.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 42, 'published' => $ago('-18 hours'),
            'tags' => 'weather, forecast, aurora',
            'body' => $p(
                'The ridge holding over the Peace settles in for the week: highs near 22 through Friday, wind light out of the northwest, and overnight lows sliding toward 4 by Thursday — sweater weather at the rink, swather weather everywhere else.',
                'For harvest, the pattern is close to ideal. Humidity drops off sharply after noon each day, giving combines a long dry window into the evening, and the overnight dews are light enough that most crews will be rolling again by late morning. The one caution is Friday, when a weak trough brushes the region: current guidance keeps the rain north of the river, but "north of the river" is a forecast, not a fence.',
                'The aurora watch continues. The storm that lit Monday night carries two more nights of elevated activity, strongest between 10 p.m. and 2 a.m., and the clear sky that is drying the canola will do the same for the view. The county\'s dark-sky pullouts are open; dress for the 4-degree part of the forecast, not the 22.',
                'Frost stays out of the picture through the weekend for the city and the immediate county. The low-lying country toward the lake should watch Sunday night — the models disagree by two degrees, and two degrees is the whole question.'
            ),
        ],
        [
            'title' => 'The best light show in the county asks only that we turn ours off',
            'desk' => 'opinion', 'dateline' => '', 'byline' => 'The editorial board',
            'lede' => 'The city is spending $1.4 million to light an avenue and the county built four places to escape light entirely. Both are right, and the fixture spec is why.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 30, 'published' => $ago('-3 days 5 hours'),
            'tags' => 'editorial, dark sky, lighting',
            'body' => $p(
                'This week the city voted to spend $1.4 million lighting 100 Avenue, and several hundred residents drove out of town to stand in the dark on purpose. It would be easy to present these as opposing impulses. They are the same impulse, competently expressed.',
                'What downtown needs and what the night sky needs turn out to be the same thing: light aimed where people are, and nowhere else. The full-cutoff fixtures in the avenue plan put their lumens on the sidewalk instead of into the sky — which is why the astronomy club endorsed a streetlight budget, a sentence we did not expect to write this year.',
                'The dark-sky pullouts are the other half of the same good habit. They cost almost nothing, they are already full on storm nights, and they exist because the county understood that darkness, this far north, is an amenity — as much a public asset as a rink or a trail, and cheaper to maintain than either.',
                'The lesson generalizes, and the town that just watched the aurora from a fence line already knows it: light is like any other public spending. The question is never simply how much. It is whether it lands where it is needed — and whether, past the last streetlight, we have kept somewhere dark enough to see what the north is for.'
            ),
        ],
    ],
];
