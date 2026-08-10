<?php
/**
 * The Pacific Post — launch package.
 * Loaded once by `PP_SITE=pacific-post php tools/seed-launch.php`.
 * Identity, rails, wire sources, and launch stories with commissioned art;
 * the demonstration stories are launch content in the paper's voice, meant
 * to be replaced by real reporting.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$img = fn (string $file) => '/assets/sites/pacific-post/img/' . $file;

return [

    'desks' => [
        ['name' => 'BC News',     'slug' => 'bc-news',     'color' => '#1E5631', 'description' => 'The province beyond the bridges, from the Island to the Kootenays.'],
        ['name' => 'Environment', 'slug' => 'environment', 'color' => '#1C5342', 'description' => 'The land, the water, and the decisions that reach them.'],
        ['name' => 'Culture',     'slug' => 'culture',     'color' => '#7A4E9E', 'description' => 'Stages, galleries, festivals, and the rooms that hold them.'],
        ['name' => 'Sports',      'slug' => 'sports',      'color' => '#1D5C8C', 'description' => 'The rinks, the diamonds, and who pays for the ice.'],
    ],

    'settings' => [
        'site_title'         => 'The Pacific Post',
        'tagline'            => 'Your source for B.C. news',
        'meta_description'   => 'A Greater Vancouver daily: city hall, transit, housing, the province and the coast — reported plainly, from the region that never stops arguing about all four.',
        'footer_line'        => 'Published on the unceded territories of the Musqueam, Squamish and Tsleil-Waututh Nations.',
        'weather_line'       => 'Vancouver, B.C.',
        'contact_email'      => 'tips@thepacificpost.com',
        'newsletter_heading' => 'The Morning Ferry',
        'newsletter_copy'    => 'Everything Greater Vancouver needs before 8 a.m., five days a week.',
        'regions'            => json_encode([
            'vancouver' => 'Greater Vancouver',
            'bc'        => 'British Columbia',
            'canada'    => 'Canada',
        ]),
    ],

    'sources' => [
        ['Daily Hive Vancouver',  'https://dailyhive.com/feed/vancouver',       'vancouver'],
        ['Vancouver Is Awesome',  'https://www.vancouverisawesome.com/rss',     'vancouver'],
        ['North Shore News',      'https://www.nsnews.com/rss',                 'vancouver'],
        ['Global BC',             'https://globalnews.ca/bc/feed/',             'bc'],
    ],

    'stories' => [
        [
            'title' => 'Tunnelling ends on the Broadway subway, three months ahead of schedule',
            'desk' => 'news', 'dateline' => 'Arbutus', 'byline' => 'Devon Ma',
            'lede' => 'Crews broke through at Arbutus on Friday afternoon. Track laying begins in September, and the province is still promising trains in 2027.',
            'image' => $img('photo-01.svg'), 'image_caption' => 'The breakthrough at Arbutus, Friday afternoon.', 'image_credit' => 'Illustration for the Post',
            'featured' => 1, 'placement' => 'hero', 'views' => 150, 'published' => $ago('-2 hours'),
            'tags' => 'Broadway subway, transit, TransLink',
            'body' => $p(
                'The boring machine surfaced at Arbutus on Friday afternoon, three months ahead of the schedule it was handed and eleven years after this city started arguing about whether the line beneath it should exist. Both facts drew applause from the small crowd of engineers allowed to watch.',
                'The breakthrough closes the project\'s riskiest chapter — five and a half kilometres of twin tunnels under one of the busiest corridors in the country, threaded past building foundations, a century of undocumented utilities, and one very documented sinkhole that made three news cycles in 2024.',
                'What remains is the part that historically goes wrong quietly rather than dramatically: track, systems, signalling, and station fit-out, the phases where transit projects lose their schedules a month at a time without a single photogenic setback. The project office says its 2027 opening date survives the tunnel phase with margin to spare. It said so carefully.',
                'The corridor, meanwhile, gets its street back in stages — Great Northern Way first, the cut-and-cover blocks last. For the businesses that survived four years behind hoarding, the project\'s community office has one more program to announce: a marketing fund, arriving, with no apparent irony, exactly when the disruption ends.'
            ),
        ],
        [
            'title' => 'Fraser sockeye return is the strongest in twelve years, DFO says',
            'desk' => 'environment', 'dateline' => 'Mission', 'byline' => 'Marisol Chen',
            'lede' => 'Test fisheries near Mission counted 4.6 million fish through July. Biologists credit cool spring flows and four years of restricted openings.',
            'image' => $img('photo-02.svg'), 'image_caption' => 'Sockeye holding below the Mission test fishery.', 'image_credit' => 'Illustration for the Post',
            'featured' => 0, 'placement' => 'featured', 'views' => 118, 'published' => $ago('-5 hours'),
            'tags' => 'Fraser sockeye, fisheries, salmon',
            'body' => $p(
                'MISSION — For the first time since 2014, the daily counts coming off the lower Fraser have given fisheries staff something they rarely get to report: a number larger than the one they forecast. Through the end of July, test fisheries near Mission had counted 4.6 million sockeye, roughly forty per cent above the pre-season estimate.',
                'The department attributes the return to cool spring flows in the upper watershed and to four consecutive years of restricted commercial openings — the two levers, one borrowed and one paid for, that salmon management actually has. Neither, the department cautions, is under anyone\'s control going forward: the openings can be held, the weather cannot.',
                '"A single strong return is a year, not a trend," said a stock assessment biologist with the department\'s Pacific region. "What we are watching is whether the four-year cycles that used to be legible are legible again."',
                'The immediate question is harvest. The strong count has the commercial fleet asking for the first meaningful opening in half a decade, and the department weighing how much of an unexpected surplus a recovering run can spare. Its answer, expected within the week, will say more about the next decade of this river than the count itself.'
            ),
        ],
        [
            'title' => 'Burnaby moves to legalize six-storey wood-frame housing along Hastings',
            'desk' => 'news', 'dateline' => 'Burnaby', 'byline' => 'Priya Rangan',
            'lede' => 'Council votes Tuesday on a rezoning that would cover 41 blocks between Boundary and Kensington.',
            'image' => $img('photo-03.svg'), 'image_caption' => 'Mid-rise construction along Hastings, Burnaby.', 'image_credit' => 'Illustration for the Post',
            'featured' => 0, 'placement' => 'featured', 'views' => 96, 'published' => $ago('-9 hours'),
            'tags' => 'housing, Burnaby, rezoning',
            'body' => $p(
                'BURNABY — The rezoning that goes to council Tuesday is, by the numbers, the largest single housing decision this city has taken in a generation: forty-one blocks of Hastings pre-approved for six-storey wood-frame apartments, no site-by-site public hearings required.',
                'The staff report is blunt about the intent. Site-by-site rezoning adds a year and roughly $70,000 a door to exactly the buildings the region says it wants; pre-zoning the corridor removes both, and moves the argument from every project to one evening — Tuesday\'s.',
                'The predictable coalitions have formed. The neighbourhood associations east of Kensington want the height stepped down mid-corridor; the builders want parking minimums cut further than staff proposed; and the merchants, whose buildings mostly become redevelopment sites under the plan, are asking the pointed question about what happens to a barber shop\'s lease when its block gets an appraisal bump.',
                'What nobody at the table disputes is the arithmetic that brought the corridor here: a frequent bus route, a flat grade, aging one-storey retail, and a housing target the city cannot hit one public hearing at a time. Council\'s vote is scheduled last on Tuesday\'s agenda, which everyone involved recognizes as a hope, not a plan.'
            ),
        ],
        [
            'title' => 'Port of Vancouver clears its grain backlog after a three-week rail repair',
            'desk' => 'business', 'dateline' => 'Vancouver', 'byline' => 'Callum Reyes',
            'lede' => 'Twenty-nine vessels were waiting at anchor at the peak. The last of them loads this week.',
            'image' => $img('photo-04.svg'), 'image_caption' => 'The grain terminal at first light, backlog clearing.', 'image_credit' => 'Illustration for the Post',
            'featured' => 0, 'views' => 74, 'published' => $ago('-1 day 2 hours'),
            'tags' => 'port, grain, rail',
            'body' => $p(
                'The lineup of grain vessels that has defined English Bay\'s horizon since mid-July is dissolving. The port authority confirmed Monday that the last of the twenty-nine ships waiting at the peak of the backlog will load this week, closing out the disruption that began with a washout on the North Shore rail cut.',
                'The three-week repair was, by railway standards, fast. The backlog it created was not small: at anchor rates of roughly $25,000 a day per vessel, the delay bill lands somewhere north of $12 million, most of it ultimately netted out of the prices paid to prairie growers — the invisible export tax that every interruption on this corridor levies a thousand kilometres inland.',
                'The episode has given fresh momentum to the terminal operators\' longest-running ask: redundancy on the North Shore cut, where a single track pinched between cliff and tide carries a measurable share of the country\'s exports. The railway\'s position is that redundancy exists — via a second crossing and a longer route — which the terminals translate as "via Kamloops."',
                'The bay, for its part, returns to its usual population of bulkers this week. The regulars who walk the seawall and count ships — a larger constituency than either the port or the railway suspects — will notice.'
            ),
        ],
        [
            'title' => 'Province tables tighter short-term rental rules before the fall session',
            'desk' => 'politics', 'dateline' => 'Victoria', 'byline' => 'Hana Okafor',
            'lede' => 'The registry gets teeth, the platforms get liability, and the resort-town exemptions get one more review.',
            'image' => $img('photo-05.svg'), 'image_caption' => 'The legislature, between sessions.', 'image_credit' => 'Illustration for the Post',
            'featured' => 0, 'views' => 66, 'published' => $ago('-1 day 7 hours'),
            'tags' => 'short-term rentals, housing policy, legislature',
            'body' => $p(
                'VICTORIA — The short-term rental amendments tabled Thursday do three things, and the order matters: they connect the provincial registry to platform data on a nightly feed, they make the platforms — not just the hosts — liable for listings without a registration number, and they schedule the resort-community exemptions for a review that the ministry has twice previously scheduled and twice previously postponed.',
                'The first two close the loop the original legislation left open. The registry has been able to see illegal listings since it launched; what it could not do was make them disappear without a complaint file and an enforcement officer. Platform liability inverts the workload: a listing without a valid number simply does not publish.',
                'Early platform data suggests the stakes. In the communities where the principal-residence requirement already applies, listings are down by roughly a third since 2024 — and long-term rental vacancy, the number the policy exists to move, has crept up in eight of the eleven markets the ministry tracks. Causation is contested, correlation is not.',
                'The exemption review is the political live wire. The resort municipalities argue their economies are the exemption; the mayors of the towns beside them argue their workforces are its cost. The review reports after the municipal elections, which both sides noticed before they finished reading the sentence.'
            ),
        ],
        [
            'title' => "The PNE's last summer on the old Hastings Park midway",
            'desk' => 'culture', 'dateline' => 'Hastings Park', 'byline' => 'June Castillo',
            'lede' => 'The amphitheatre is built, the midway moves next spring, and the wooden coaster — as required by law, sentiment and engineering — stays exactly where it is.',
            'image' => $img('photo-06.svg'), 'image_caption' => 'The midway at dusk, last summer in the old footprint.', 'image_credit' => 'Illustration for the Post',
            'featured' => 0, 'views' => 71, 'published' => $ago('-1 day 11 hours'),
            'tags' => 'PNE, Hastings Park, fair',
            'body' => $p(
                'The fair that opens Saturday is the last one on the midway\'s old footprint. When the gates close on Labour Day, the rides come down as they always do — and next spring they go back up two hundred metres east, on the realigned grounds the park\'s master plan has been promising since before some of the ride operators were born.',
                'The move is the midway\'s part of a larger reshuffle: the new amphitheatre opened this summer, the sanctuary ponds expand into the old parking, and the fair\'s long, awkward coexistence with a residential neighbourhood gets renegotiated with better fences and a sound engineer\'s fingerprints on the site plan.',
                'The wooden coaster stays. It was never going anywhere — heritage designation, engineering reality and public sentiment forming, for once, a unanimous committee. The realigned midway is designed around it, which the fair\'s president describes as "planning a city around a cathedral, if the cathedral rattled."',
                'For this final summer the fair is leaning into the occasion: the archives on display in the Forum, ride tokens printed with the old map, and — the detail that will fill everyone\'s cameras — the midway\'s original 1958 arch, found in a maintenance yard in 2019, restored and standing at the gate it first stood at, for one more season of photographs.'
            ),
        ],
        [
            'title' => 'The Mariners sign a homegrown keeper out of the Burnaby academy',
            'desk' => 'sports', 'dateline' => 'Burnaby', 'byline' => 'Tomas Aldana',
            'lede' => 'Seventeen years old, raised eight blocks from the training ground, and handed a three-year deal the club calls a statement.',
            'image' => $img('photo-07.svg'), 'image_caption' => 'Keeper training at the Burnaby academy grounds.', 'image_credit' => 'Illustration for the Post',
            'featured' => 0, 'views' => 63, 'published' => $ago('-2 days 3 hours'),
            'tags' => 'Mariners, academy, soccer',
            'body' => $p(
                'BURNABY — The Mariners signed goalkeeper Sam Ohaegbu to a three-year professional contract Monday, and the club would like the geography noted: seventeen years old, product of the Burnaby academy, raised eight blocks from the training ground he has cycled to since he was eleven.',
                'The signing is the first keeper the academy has graduated to a professional deal, and the position makes it a particular statement. Clubs buy keepers; developing one takes a decade of patience and a goalkeeping department that resists the temptation to fix what isn\'t broken. Ohaegbu\'s coaches describe a classic late-bloomer arc — cut from two provincial pools, kept by the academy "because the training staff kept losing arguments about him."',
                'He will not start Saturday. The pathway is deliberate: third keeper this season, a loan to the reserve side likely in the spring, and first-team minutes when the performance staff — not the marketing department, the club was careful to specify — say so.',
                'The club\'s sporting director, asked what the signing means beyond one roster spot, gave the answer academies exist to make true: "Every kid at our under-12 session tonight knows his name. That is the entire business case, and it showed up eight years early."'
            ),
        ],
        [
            'title' => 'Interior wildfire smoke pushes Metro Vancouver to its worst August air rating since 2023',
            'desk' => 'environment', 'dateline' => '', 'byline' => 'Marisol Chen',
            'lede' => 'Environment Canada issued an advisory for the Lower Mainland on Sunday night, with fine-particulate readings four times the provincial objective.',
            'image' => $img('photo-08.svg'), 'image_caption' => 'Smoke haze over the North Shore, Sunday evening.', 'image_credit' => 'Illustration for the Post',
            'featured' => 0, 'views' => 58, 'published' => $ago('-2 days 8 hours'),
            'tags' => 'air quality, wildfire smoke, advisory',
            'body' => $p(
                'The haze that flattened the North Shore mountains into a grey suggestion on Sunday carried the season\'s first air quality advisory with it: fine-particulate readings across the Lower Mainland at four times the provincial objective, the region\'s worst August rating since the smoke summer of 2023.',
                'The source is the Interior fire complexes, whose plumes rode a northeasterly flow down the Fraser Canyon and pooled against the mountains the way the airshed\'s geography guarantees. The marine inflow that usually rinses the basin is forecast to return Wednesday; until then the advisory\'s guidance applies — indoors for the sensitive groups, strenuous exertion postponed for everyone.',
                'The readings put the region\'s slow adaptation on display. School districts now carry filtration budgets that did not exist five years ago, the health authorities publish clean-air-centre maps the way they once published cooling centres, and the region\'s runners have collectively learned what the AQHI scale means before breakfast.',
                'The forecast\'s honest note is the seasonal one: the pattern that delivered this plume is ordinary August synoptics, and the fires feeding it are burning in a forest that has more August left. Wednesday\'s wind clears the basin. It does not close the source.'
            ),
        ],
        [
            'title' => 'Squamish asks the province to reopen the Sound\'s LNG air-quality review',
            'desk' => 'bc-news', 'dateline' => 'Squamish', 'byline' => 'Devon Ma',
            'lede' => 'Council\'s letter cites three years of monitoring data the original certificate never saw.',
            'image' => $img('photo-09.svg'), 'image_caption' => 'The Sound from the waterfront, terminal lights across the water.', 'image_credit' => 'Illustration for the Post',
            'featured' => 0, 'views' => 49, 'published' => $ago('-3 days 2 hours'),
            'tags' => 'Howe Sound, LNG, air quality',
            'body' => $p(
                'SQUAMISH — Council voted 5–2 Tuesday to formally ask the province to reopen the air-quality conditions on the Sound\'s LNG facility, arguing that three years of monitoring data now exist that the original environmental certificate was written without.',
                'The letter is narrower than the debate that produced it. It does not ask to revisit the facility\'s approval — a fight this council watched a previous council lose — but to update the certificate\'s air-quality conditions against the actual monitoring record: the inversion-day readings, the marine traffic contribution, and the airshed model that the intervening years have tested.',
                'The district\'s own air-quality technician, presenting the data, was careful with it: most readings sit inside the certificate\'s limits, several inversion-day peaks do not, and the question of attribution — terminal, highway, tugs, woodstoves — is exactly what a reopened review would be for.',
                'The province has thirty days to respond and three available answers: reopen, decline, or the middle path of an amended monitoring order, which observers of this file consider the likely landing. The council\'s letter, its drafters note, was written to make the middle path easy to take.'
            ),
        ],
        [
            'title' => 'Surrey adds 62 portables as enrolment outruns the district\'s build plan',
            'desk' => 'news', 'dateline' => 'Surrey', 'byline' => 'Priya Rangan',
            'lede' => 'The district opens the year with more students in portables than some districts have students.',
            'image' => $img('photo-10.svg'), 'image_caption' => 'The portable rows behind a Surrey elementary, September-ready.', 'image_credit' => 'Illustration for the Post',
            'featured' => 0, 'views' => 44, 'published' => $ago('-3 days 7 hours'),
            'tags' => 'Surrey schools, enrolment, portables',
            'body' => $p(
                'SURREY — The district confirmed its September logistics this week: sixty-two additional portables across nineteen schools, bringing the fleet past four hundred — which means Surrey now teaches more students in portables than several B.C. districts teach in total.',
                'The arithmetic is not mysterious. The district adds a secondary school\'s worth of students every year; a new school takes six years from capital approval to opening day; and the capital approvals, whatever their pace, are chasing subdivisions that were approved faster. The portables are the difference between those clocks, rendered in plywood.',
                'The district\'s capital plan does its arguing with a single exhibit: the enrolment projection against the funded construction schedule, two lines that do not meet within the plan\'s horizon. Three schools open in the next eighteen months; the projection consumes their capacity before the last one cuts its ribbon.',
                'The board\'s chair, presenting the portable count, declined the traditional adjectives. "Crisis implies surprise," she said. "There is nothing surprising here. It is a funding formula meeting a growth rate, every September, in public."'
            ),
        ],
        [
            'title' => 'The Broadway line will only work if the buses above it change too',
            'desk' => 'opinion', 'dateline' => '', 'byline' => 'Hana Okafor',
            'lede' => 'A subway is not a transit plan. It is the spine of one.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 39, 'published' => $ago('-3 days 11 hours'),
            'tags' => 'opinion, transit, Broadway subway',
            'body' => $p(
                'The tunnel is dug, the schedule is beaten, and the region is entitled to a week of feeling good about a megaproject that behaved. Then it should look up from the tunnel to the street, because the next decision is quieter and arguably larger: what happens to the buses.',
                'The 99 B-Line — the workhorse the subway replaces — is only the most famous route in a web that was designed around its absence. The corridor\'s riders do not appear at stations by teleportation; they arrive on the north-south routes, whose frequencies were set in an era when Broadway was where buses went to be stuck.',
                'The temptation, already visible in the service plans, is to bank the subway\'s capacity as savings — trim the parallel service, hold the feeders flat, and let the new line absorb the region\'s growth on its own. That is how cities turn a generational investment into a full train that people drive to.',
                'The alternative costs money but not much of it, by megaproject standards: pour the B-Line\'s service hours into the feeder grid, and let the subway do what spines do — carry what the limbs deliver. The tunnel took eleven years of argument. The bus map will take one budget season, and it will decide what the tunnel was for.'
            ),
        ],
        [
            'title' => 'A record salmon return is not the same as a recovered river',
            'desk' => 'opinion', 'dateline' => '', 'byline' => 'Jonah Redcrow',
            'lede' => 'Celebrate the count. Read the footnotes.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 33, 'published' => $ago('-4 days 4 hours'),
            'tags' => 'opinion, salmon, Fraser River',
            'body' => $p(
                'The Fraser gave this province a gift last month: 4.6 million sockeye and counting, the strongest return in twelve years, numbers that let a fisheries biologist smile in public. The gift deserves its celebration. It also deserves its footnotes, because a good year and a recovered river are different things, and the difference is where the next decade\'s decisions live.',
                'The footnotes are not obscure. This return rode a cool, wet spring — the kind of water year the climate record says to expect less often. It followed four years of closures whose economic cost fell on a fleet that cannot absorb many more. And it arrives in a river whose August temperatures now cross the lethal threshold for salmon in most years, a fact one strong cohort does not amend.',
                'The risk of a good year is what it licenses. There will be pressure — commercial, political, recreational — to treat the count as an all-clear: reopen fully, defer the habitat spending, declare the closures to have worked and therefore to be finished. Closures that worked are an argument for the toolkit, not for emptying it.',
                'The river has offered a data point, not a verdict. The generous reading is that restraint, given one good break from the weather, still works — that the river answers when spoken to carefully. The province should take the win exactly as offered: once, gratefully, and with the footnotes attached.'
            ),
        ],
        [
            'title' => "Metro Vancouver's water bill is a governance problem, not a plumbing one",
            'desk' => 'opinion', 'dateline' => '', 'byline' => 'The editorial board',
            'lede' => 'The pipes are fine. The org chart is leaking.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 28, 'published' => $ago('-4 days 9 hours'),
            'tags' => 'editorial, Metro Vancouver, water',
            'body' => $p(
                'Your water bill went up nine per cent this year, and if you missed the decision being made, you are in excellent company: it was made by a board no one elected to make it, at a meeting no one attended, on the recommendation of a body that answers, on a good day, to itself.',
                'This is not a scandal. The projects behind the increase — the treatment plant, the seismic mains, the reservoir program — are real, necessary and, by the standards of water infrastructure, competently run. The scandal-shaped thing is structural: a regional federation that spends billions through a board of council appointees, each accountable to a city council, none accountable for the whole.',
                'The model had a logic when the region\'s shared plumbing was modest. It has not been modest for a generation. The board now makes some of the largest infrastructure decisions in the province behind a governance arrangement that political scientists politely call indirect and residents accurately call invisible.',
                'The fixes are known and unexciting: direct election of the regional board, or provincial legislation that at least forces its budgets through the same public scrutiny a city\'s must survive. Every year the region declines to choose, the bills rise anyway — competently, necessarily, and without a single voter able to say she saw it happen.'
            ),
        ],
    ],
];
