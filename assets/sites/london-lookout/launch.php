<?php
/**
 * London Lookout — launch package.
 * Loaded once by `PP_SITE=london-lookout php tools/seed-launch.php`.
 * Identity, rails, wire sources, and launch stories with commissioned art;
 * the demonstration stories are launch content in the paper's voice, meant
 * to be replaced by real reporting.
 *
 * The pack fills every slot on the lookout front exactly once: hero,
 * four Watch files, four briefs, three Forks cards (art required), a
 * politics lead plus three rows and one opinion, and three Life cards.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$img = fn (string $file) => '/assets/sites/london-lookout/img/' . $file;

return [

    'desks' => [
        // politics and opinion are shared network desks; listed so a fresh
        // database is self-sufficient (the seeder creates them only if missing).
        ['name' => 'London',   'slug' => 'london',   'color' => '#1E4D38', 'description' => 'Home base: the city at the forks — council, neighbourhoods, and the systems underneath them.'],
        ['name' => 'Ontario',  'slug' => 'ontario',  'color' => '#3E5C66', 'description' => 'The province beyond the county line, from Windsor to the Ottawa Valley.'],
        ['name' => 'Politics', 'slug' => 'politics', 'color' => '#A03D2E', 'description' => 'Two chambers and the space between them: city hall and Queen\'s Park.'],
        ['name' => 'Economy',  'slug' => 'economy',  'color' => '#8A6B1F', 'description' => 'Work, wages, plants and paycheques across the southwest corridor.'],
        ['name' => 'Campus',   'slug' => 'campus',   'color' => '#4E6E58', 'description' => 'Two campuses, one city: the university, the college, and the town around them.'],
        ['name' => 'Culture',  'slug' => 'culture',  'color' => '#6E4E8A', 'description' => 'Stages, studios, markets and rinks — the Forest City at ease.'],
        ['name' => 'Opinion',  'slug' => 'opinion',  'color' => '#182420', 'description' => 'Argument, clearly labelled.'],
    ],

    'settings' => [
        'site_title'         => 'London Lookout',
        'tagline'            => 'From the forks of the Thames. Across Ontario.',
        'meta_description'   => 'Independent journalism from London, Ontario. The Lookout keeps watch on city hall, Queen\'s Park and the southwest corridor — following files until they resolve, not until they stop trending.',
        'footer_line'        => 'Independent journalism from London, Ontario — city hall, Queen\'s Park and the southwest corridor, followed until the files resolve.',
        'weather_line'       => 'London · 22°C · Partly cloudy',
        'contact_email'      => 'tips@londonlookout.com',
        'newsletter_heading' => 'The Lookout at Six',
        'newsletter_copy'    => 'One email at 6 a.m., weekdays: what moved in London and Ontario overnight, what\'s on the day\'s order papers, and the files the Lookout is keeping open.',
        'breaking_label'     => 'Morning briefing: the five stories shaping London\'s day',
        'breaking_url'       => '/newsletter/',
        'regions'            => json_encode([
            'london'  => 'London',
            'ontario' => 'Ontario',
            'canada'  => 'Canada',
        ]),
    ],

    'sources' => [
        // Verified 2026-09-02. lfpress.com is Postmedia and intermittently
        // blocks automated fetchers — expect occasional pull errors.
        ['CBC London',         'https://www.cbc.ca/webfeed/rss/rss-canada-london',  'london'],
        ['Global News London', 'https://globalnews.ca/london/feed/',                'london'],
        ['London Free Press',  'https://lfpress.com/feed/',                         'london'],
        ['CBC Toronto',        'https://www.cbc.ca/webfeed/rss/rss-canada-toronto', 'ontario'],
        ['CBC Ottawa',         'https://www.cbc.ca/webfeed/rss/rss-canada-ottawa',  'ontario'],
        ['Global News Toronto','https://globalnews.ca/toronto/feed/',               'ontario'],
        ['CBC Politics',       'https://www.cbc.ca/webfeed/rss/rss-politics',       'canada'],
        ['Global News Canada', 'https://globalnews.ca/canada/feed/',                'canada'],
    ],

    'stories' => [

        /* --- Hero ---------------------------------------------------------- */
        [
            'title' => 'The decade London is deciding right now',
            'desk' => 'london', 'dateline' => 'London', 'byline' => 'London Lookout staff',
            'lede' => 'Transit is under construction, the housing ledger is public, and the river is back on the agenda. Four files that used to move separately are now one question: what kind of city is this becoming?',
            'image' => $img('photo-01.svg'), 'image_caption' => 'The forks at evening, treeline behind the towers.', 'image_credit' => 'Illustration for the Lookout',
            'featured' => 1, 'placement' => 'hero', 'views' => 148, 'published' => $ago('-2 hours'),
            'tags' => 'growth, transit, housing',
            'body' => $p(
                'For most of its history London grew the comfortable way — slowly enough that no single council ever had to decide what the city was becoming, because the answer arrived one subdivision at a time. That era is over. The city now adds people faster than it adds almost anything else, and the files that manage the consequences — rapid transit, housing supply, the downtown\'s next act, the river\'s edge — have all reached their decision points in the same short window.',
                'Read separately, each file is manageable and each has its own committee. Read together, they are a single design problem. The transit corridors decide where density can honestly go; the density decides whether the housing arithmetic works; the housing arithmetic decides who can afford to arrive; and the downtown and the riverfront decide whether the city people arrive into is one they stay for. Sequence them well and each decision makes the next one easier. Sequence them badly and the market will happily sequence them for you.',
                'What makes this moment unusual is not the difficulty of any one choice but the fact that they can no longer be deferred independently. Concrete is being poured on the transit corridors now. The provincial housing targets run on a clock the city does not control. Every year of hesitation on the middle files is a year the edges of the city absorb by default, in the most expensive pattern a municipality can service.',
                'This story opens a standing file, not a series with an end date. The Lookout will follow the growth question wherever it actually gets decided — the council chamber, the committee rooms, the provincial order paper, the boards that never make the front page — on the theory that a city that can see the whole board plays it better. The decade is being drawn now, mostly in public, mostly in documents anyone can read. We intend to read them.'
            ),
        ],

        /* --- The Watch: four standing files (text rail) --------------------- */
        [
            'title' => 'The rapid-transit file: two corridors, one bet on how the city moves',
            'desk' => 'london', 'dateline' => 'London', 'byline' => 'London Lookout staff',
            'lede' => 'The Lookout\'s standing file on the bus rapid transit build: what is under construction, what opens when, and whether the land use follows the lanes.',
            'image' => '', 'featured' => 0, 'views' => 96, 'published' => $ago('-3 hours'),
            'tags' => 'The Watch, transit, infrastructure',
            'body' => $p(
                'This is a Watch file: a story the Lookout keeps open and updates as the facts move, rather than a report that runs once and disappears. The subject is the largest mobility investment in the city\'s modern history — the rapid-transit corridors now under construction — and the question the file exists to answer is simple to state and slow to resolve: will the city change around the lanes, or just repaint them?',
                'The case for the build has always rested on a second-order effect. Dedicated lanes and frequent service are the visible deliverable, but the actual bet is on land use: that reliable transit makes mid-rise housing viable along the corridors, and that the housing makes the service viable in return. Cities that get this loop running stop arguing about transit; cities that don\'t get a nicer bus.',
                'The file therefore tracks two ledgers at once. The construction ledger is the easy one — segments, stations, opening dates, the inevitable utility surprises under century-old streets. The zoning ledger is the one that decides the outcome: what actually gets approved within walking distance of the stations, at what height, on what timeline, against how much appeal.',
                'Entries will be dated and cumulative, so a reader arriving late can scroll to the bottom and know where things stand. That is the point of a lookout: not to be first, but to still be watching when the ribbon-cuttings are over.'
            ),
        ],
        [
            'title' => 'The emergency-room file: the southwest\'s coverage map, watched weekly',
            'desk' => 'ontario', 'dateline' => '', 'byline' => 'London Lookout staff',
            'lede' => 'A standing file on emergency-department coverage across southwestern Ontario — where the gaps open, where the patients go, and what the staffing numbers actually say.',
            'image' => '', 'featured' => 0, 'views' => 88, 'published' => $ago('-4 hours'),
            'tags' => 'The Watch, health, Ontario',
            'body' => $p(
                'London sits at the centre of the southwest\'s hospital system, which means every emergency-room closure notice in the region eventually lands here — as a transfer, a longer wait, or a patient who drove an hour because the nearest department went dark for the weekend. This Watch file tracks the coverage map for the whole region, week by week, because the pattern matters more than any single notice.',
                'The mechanics are unglamorous and decisive. Emergency departments close when a schedule cannot be filled, schedules fail for specific reasons — a physician shortage here, a nursing gap there, an agency contract that lapsed — and the reasons are documented, if rarely assembled in one place. Assembling them in one place is the file\'s job.',
                'The file also follows the money and the fixes: the staffing programs, the incentive schedules, the team-based funding experiments, and whether any of them shows up where it counts, which is in a small hospital\'s published schedule holding steady through a long weekend.',
                'Readers in the counties around London are invited to treat this file as theirs. If your local department posted hours, changed hours, or quietly stopped posting, the tip line at the bottom of the page is the fastest way to make the map more honest.'
            ),
        ],
        [
            'title' => 'The battery-belt file: counting what is poured, hired and actually shipped',
            'desk' => 'economy', 'dateline' => '', 'byline' => 'London Lookout staff',
            'lede' => 'The southwest is carrying one of the country\'s biggest industrial bets. This standing file counts the concrete, the hires and the output — not the announcements.',
            'image' => '', 'featured' => 0, 'views' => 84, 'published' => $ago('-5 hours'),
            'tags' => 'The Watch, manufacturing, economy',
            'body' => $p(
                'The corridor between Windsor and the western edge of the Greater Toronto Area has been promised a manufacturing renaissance built on batteries and the vehicles around them, and a meaningful share of that promise is being poured one county over from London. Announcement coverage has been abundant. This Watch file exists for the part that comes after the announcements.',
                'The file keeps three counts. Concrete: what is physically under construction, at what stage, on whose published schedule. People: postings, hires, apprenticeships and the training pipelines at the college and the university that are supposed to feed them. Output: what has actually shipped, once shipping starts — the only count that ultimately settles the argument.',
                'It also tracks the dependency map, because a battery plant is not a building but a supply chain wearing one. The suppliers who co-locate, the ones who don\'t, the housing and transit pressure in the host towns, and the freight patterns on the 401 and 402 are all part of whether the bet pays.',
                'The register here is deliberately boring. Industrial policy is argued in superlatives and settled in quarterly numbers; the file sides with the numbers, whichever way they point.'
            ),
        ],
        [
            'title' => 'The enrolment file: two campuses budget for a number nobody controls',
            'desk' => 'campus', 'dateline' => 'London', 'byline' => 'London Lookout staff',
            'lede' => 'University and college enrolment now moves on federal policy, international demand and provincial funding formulas — and the city\'s economy moves with it. A standing file.',
            'image' => '', 'featured' => 0, 'views' => 77, 'published' => $ago('-6 hours'),
            'tags' => 'The Watch, campus, economy',
            'body' => $p(
                'Every September the city absorbs tens of thousands of students, and every budget season the institutions that teach them plan around a number that has become genuinely hard to predict. Enrolment — especially international enrolment — now responds to federal permit policy, exchange rates, global demand and provincial funding design, none of which is set in London. This Watch file follows the number and its consequences.',
                'The consequences are civic, not just institutional. Student demand props up entire rental submarkets, staffs a large share of the service economy, and fills the transit system\'s best routes. When the number swings, landlords, employers and the transit commission feel it before the auditors do.',
                'The file tracks what the institutions publish — enrolment reports, budget updates, program suspensions and launches — alongside the upstream policy that moves the number: permit caps, funding-formula reviews, tuition rules. The aim is a single running record of a system usually covered one press release at a time.',
                'It is a file about arithmetic, but the arithmetic has an address. A city with two big campuses does not get to be a spectator to higher-education policy; it is the ground where the policy lands.'
            ),
        ],

        /* --- Ontario in Brief: four numbered text cards --------------------- */
        [
            'title' => 'Queen\'s Park returns with a full order paper and a short runway',
            'desk' => 'ontario', 'dateline' => 'Toronto', 'byline' => 'London Lookout staff',
            'lede' => 'The fall sitting opens with housing, health staffing and municipal finance all queued — and fewer sitting days than the list implies.',
            'image' => '', 'featured' => 0, 'views' => 64, 'published' => $ago('-7 hours'),
            'tags' => 'Queen\'s Park, legislature',
            'body' => $p(
                'The legislature\'s fall sitting opens with an order paper that reads like a municipal wish list: housing measures, health staffing, and the perennial question of how cities are supposed to pay for what the province asks of them. The calendar, as always, is the constraint — the number of actual sitting days between opening and the winter adjournment is smaller than the agenda suggests.',
                'For London the sequencing matters more than the speeches. Bills that move early get committee time and amendments; bills that move late get passed whole or not at all. The items with direct local weight — anything touching municipal finance, transit funding or hospital staffing — are the ones to watch for early committee referral.',
                'The Lookout will read the order paper weekly and note what actually advanced, which is a shorter and more useful list than what was promised. Brief items like this one exist for exactly that: the update, plus the context that turns an update into understanding.'
            ),
        ],
        [
            'title' => 'A quick read on the corridor economy, in four numbers',
            'desk' => 'economy', 'dateline' => '', 'byline' => 'London Lookout staff',
            'lede' => 'Employment, building permits, freight volumes and vacancy — the four dials worth checking before anyone tells you how the region is doing.',
            'image' => '', 'featured' => 0, 'views' => 58, 'published' => $ago('-8 hours'),
            'tags' => 'economy, data',
            'body' => $p(
                'Regional economies get narrated in anecdotes — a plant opening here, a closure there — but four published numbers do most of the real explaining for the London region: the employment rate, residential building permits, freight activity on the 401 corridor, and vacancy in both the rental and industrial markets.',
                'Each number answers a different question. Employment says whether the region\'s bet on health care, education, insurance and manufacturing is holding. Permits say whether the housing pipeline is responding to the growth everyone can see. Freight says whether the corridor\'s logistics economy — the quiet giant of the regional labour market — is accelerating or coasting. Vacancy says who is winning: in rentals, low vacancy means tenants aren\'t; in industrial space, low vacancy means the region\'s pitch to manufacturers is working.',
                'The Lookout will publish this four-dial read regularly, same format every time, so change is visible. A brief is not a substitute for the deeper files — it is the gauge cluster on top of them.'
            ),
        ],
        [
            'title' => 'Council\'s fall agenda, in ninety seconds',
            'desk' => 'london', 'dateline' => 'London', 'byline' => 'London Lookout staff',
            'lede' => 'Budget directions, the zoning follow-through on the transit corridors, and a procurement calendar that quietly decides half of next year.',
            'image' => '', 'featured' => 0, 'views' => 61, 'published' => $ago('-9 hours'),
            'tags' => 'city hall, council',
            'body' => $p(
                'City council\'s fall runs on three tracks. The loudest is budget directions — the early votes that set the boundaries for the numbers debated in the winter, and the stage where a target tax figure hardens from talking point into instruction. By the time the draft budget is public, most of the room to move is already gone; the fall is when it goes.',
                'The second track is land use: the zoning and site-plan follow-through along the rapid-transit corridors, which is where the city\'s growth rhetoric either becomes bylaw or doesn\'t. Individual items will look small — a height here, a parking ratio there — but they aggregate into the answer.',
                'The third track nobody watches: procurement. The contracts advertised between now and December determine what actually gets built and fixed next construction season. The agenda\'s back pages are where next year lives, and the Lookout reads the back pages.'
            ),
        ],
        [
            'title' => 'Four decisions made elsewhere that land on London\'s doorstep',
            'desk' => 'ontario', 'dateline' => '', 'byline' => 'London Lookout staff',
            'lede' => 'Immigration levels, interest rates, provincial funding formulas and a trade file none of us voted on — the outside forces shaping the city\'s year.',
            'image' => '', 'featured' => 0, 'views' => 55, 'published' => $ago('-10 hours'),
            'tags' => 'Ontario, policy',
            'body' => $p(
                'A mid-sized city\'s year is mostly written in other rooms. Federal immigration levels set the region\'s population growth and its campuses\' budgets. The central bank\'s rate path sets the housing market\'s temperature and the cost of every capital project on the city\'s books. Provincial funding formulas — for hospitals, transit, and the municipalities themselves — set what local government can actually attempt. And the continental trade climate sets the order books of the manufacturers the corridor depends on.',
                'None of this is an excuse for local passivity; it is an argument for local literacy. The cities that do well under external shocks are the ones that saw the shock coming and had positioned — pre-zoned land, pre-approved projects, training programs already running when the demand arrived.',
                'This brief is a standing format: when a decision made elsewhere moves one of London\'s dials, it gets a plain-language entry here, with the local consequence up top and the machinery explained underneath.'
            ),
        ],

        /* --- From the Forks: three visual cards ----------------------------- */
        [
            'title' => 'Yellow brick, new frames: the infill decade reaches the old neighbourhoods',
            'desk' => 'london', 'dateline' => 'London', 'byline' => 'London Lookout staff',
            'lede' => 'The gentlest-looking change in the city is also the most contested: what gets built between the yellow-brick houses, and who decides.',
            'image' => $img('photo-03.svg'), 'image_caption' => 'Yellow-brick streets, new frames rising between them.', 'image_credit' => 'Illustration for the Lookout',
            'featured' => 0, 'views' => 72, 'published' => $ago('-12 hours'),
            'tags' => 'housing, neighbourhoods, planning',
            'body' => $p(
                'London\'s signature building material is a soft yellow brick, and its signature planning argument is what may be built beside it. The city\'s growth math no longer works on the edges alone: the provincial targets, the servicing costs and the transit investment all point the next decade of housing inward, into neighbourhoods that have not seen a construction crane in two generations.',
                'The policy machinery for this is already largely in place — additional units permitted as of right, corridor zoning that allows mid-rise, incentives that favour projects near the new transit. What remains is the part no bylaw can settle: the block-by-block negotiation between a city that needs the homes and streets that like themselves as they are.',
                'The honest version of this story refuses both caricatures. Infill opponents are not simply obstructionists; they are often defending real qualities — canopy, scale, the pattern of porches — that bad projects genuinely destroy. Infill advocates are not simply developers\' proxies; they are often the adult children of those same streets, priced out of them. Good infill design is the narrow bridge between the two, and some cities have found it.',
                'The Lookout will cover this the way it covers everything: at the level of the actual decisions. Which applications, which committee nights, which designs got better through the process and which just got smaller. The yellow brick is not going anywhere. The question is what learns to stand beside it.'
            ),
        ],
        [
            'title' => 'One county over, the corridor\'s biggest bet takes shape',
            'desk' => 'economy', 'dateline' => 'St. Thomas', 'byline' => 'London Lookout staff',
            'lede' => 'The battery plant rising south of the city will be judged by payrolls and shipments, not announcements — and London is quietly central to whether it works.',
            'image' => $img('photo-04.svg'), 'image_caption' => 'The plant floor, line running.', 'image_credit' => 'Illustration for the Lookout',
            'featured' => 0, 'views' => 69, 'published' => $ago('-14 hours'),
            'tags' => 'manufacturing, St. Thomas, economy',
            'body' => $p(
                'Drive twenty minutes south and you reach the largest industrial construction site in the region\'s modern history — the battery plant that is supposed to anchor the southwest\'s claim on the continent\'s electric-vehicle supply chain. The announcements were a provincial and federal production. The delivery, it turns out, is substantially a London-region production.',
                'The plant\'s labour shed is the city and its counties. The training pipelines run through the college and the university. The housing its workforce needs is being argued about in half a dozen council chambers, this city\'s included. And the suppliers deciding whether to co-locate are reading the same regional fundamentals — industrial land, power, freight access, workforce — that London\'s own economic development office markets.',
                'Which is why the Lookout treats the plant as a regional file rather than a neighbouring town\'s news. If it succeeds, the payroll and the supplier network will not respect municipal boundaries on the way in. If it stumbles — and giant industrial projects stumble in ordinary, documented ways: schedule slips, technology pivots, demand softness — the same boundaries will not contain the disappointment.',
                'The bet is placed and the concrete is poured. What remains is the long middle of the story, where the announcements stop and the quarterly numbers start. That middle is exactly the part a paper built for kept-open files is for.'
            ),
        ],
        [
            'title' => 'September does what September does: the city absorbs its campuses',
            'desk' => 'campus', 'dateline' => 'London', 'byline' => 'London Lookout staff',
            'lede' => 'Move-in season is the annual reminder that London is a college town at scale — and that the town half of the bargain is renegotiated every year.',
            'image' => $img('photo-05.svg'), 'image_caption' => 'The quad in the first week of term.', 'image_credit' => 'Illustration for the Lookout',
            'featured' => 0, 'views' => 66, 'published' => $ago('-16 hours'),
            'tags' => 'campus, students, city life',
            'body' => $p(
                'Every September the city performs its least-acknowledged piece of infrastructure: the absorption, over about two weeks, of a student population the size of a small city. The lease turnovers, the transit crush, the retail surge, the noise complaints — all of it arrives on schedule, and all of it is treated annually as if it were weather rather than the predictable output of a system the city co-manages.',
                'The town-gown bargain underneath is real and mostly good. The campuses are the region\'s intellectual anchor, two of its largest employers, and the reason its hospital system supports research most mid-sized cities cannot. The students are the rental market\'s floor, the service economy\'s staff, and — the part civic arithmetic keeps missing — the single largest pool of potential permanent residents the city will ever recruit from.',
                'That last point is where the file gets interesting. Every city with a big campus talks about retention; few measure it seriously, and fewer act as if the levers — housing a graduate can afford, first jobs in the fields the campuses actually teach, a downtown worth staying for — were the same levers as everything else on the civic agenda. They are.',
                'So the Lookout will cover September as what it is: not a nuisance season but an annual audit of the bargain. The city\'s job is to be worth staying in. The measurement period runs from move-in to convocation, every year.'
            ),
        ],

        /* --- City Hall & Queen's Park: lead + three rows -------------------- */
        [
            'title' => 'Three files, two chambers: what this fall actually decides',
            'desk' => 'politics', 'dateline' => 'London', 'byline' => 'London Lookout staff',
            'lede' => 'Strip the speeches away and the season comes down to a budget envelope, a housing ledger and a staffing formula — one file per chamber, plus one they share.',
            'image' => $img('photo-02.svg'), 'image_caption' => 'City hall, before the meeting.', 'image_credit' => 'Illustration for the Lookout',
            'featured' => 0, 'placement' => 'featured', 'views' => 91, 'published' => $ago('-18 hours'),
            'tags' => 'city hall, Queen\'s Park, politics',
            'body' => $p(
                'Political seasons generate more agenda than decision, which is why the Lookout starts each one by asking the narrowing question: of everything scheduled, what will actually be decided, by whom, by when? For this fall the honest answer is three files — one at city hall, one at Queen\'s Park, and one stretched between them.',
                'City hall\'s file is the budget envelope. The multi-year budget framework meets its update season, and the early directional votes will set the real boundaries — what the tax figure can be, what gets deferred to make it, which capital projects slide a year. Everything else on the municipal agenda lives inside whatever envelope those votes draw.',
                'Queen\'s Park\'s file is the housing ledger. The province built a system of municipal targets and public scorekeeping; this fall it has to decide what the scorekeeping means — what follows for cities behind their curve, what rewards exist for cities ahead of it, and whether the targets survive contact with the construction economy\'s actual capacity. London\'s posture on its own target makes this more than spectator sport.',
                'The shared file is health staffing, where provincial funding design meets the region\'s hospital schedules. It is the least theatrical of the three and will decide the most. The Lookout\'s coverage will follow the paper, not the podium: order papers, committee referrals, budget directions, and the votes where a season\'s rhetoric is finally priced.'
            ),
        ],
        [
            'title' => 'Budget season opens with one number nobody in the room controls',
            'desk' => 'politics', 'dateline' => 'London', 'byline' => 'London Lookout staff',
            'lede' => 'The assessment base, provincial transfers and contracted wage growth are set before debate begins — which is exactly why the debate needs watching.',
            'image' => '', 'featured' => 0, 'views' => 52, 'published' => $ago('-20 hours'),
            'tags' => 'budget, city hall',
            'body' => $p(
                'Municipal budget debates are staged as if everything were on the table, when most of the table was set months earlier. The assessment base is what it is. Provincial and federal transfers arrive on formulas the council chamber cannot amend. Collective agreements price most of the workforce in advance. What a council actually controls is the margin — and the margin is where a city\'s priorities become legible.',
                'That is the case for watching budget season closely rather than cynically. The service levels that get quietly trimmed, the capital projects that slide a year "for phasing", the reserves drawn down or rebuilt — these are decisions, made by named people, in public, on the record. They are just formatted to be unreadable.',
                'The Lookout\'s budget coverage will do the unfashionable thing and read the documents: the variance reports, the reserve schedules, the capital deferral lists. The speeches will summarize themselves.'
            ),
        ],
        [
            'title' => 'A field guide to the boards that actually run this city',
            'desk' => 'politics', 'dateline' => 'London', 'byline' => 'London Lookout staff',
            'lede' => 'Police services, transit, the library, public health, conservation — a real share of local government happens off the council floor. Here\'s the map.',
            'image' => '', 'featured' => 0, 'views' => 49, 'published' => $ago('-22 hours'),
            'tags' => 'governance, explainer',
            'body' => $p(
                'Ask where the city is governed and everyone points at the council chamber, which is at best half right. A substantial share of local public money and local public power sits with appointed and semi-independent bodies — the police services board, the transit commission, the public library board, the health unit, the conservation authority — each with its own budget, its own agenda cycle, and its own much smaller audience.',
                'The design is deliberate and partly defensible: some functions are kept at arm\'s length from council politics for good historical reasons. But arm\'s length too easily becomes out of sight. Board agendas are published, meetings are open, and decisions with citywide weight — a police budget, a route redesign, a floodplain ruling — routinely pass with no one in the public gallery.',
                'This guide is the Lookout\'s standing map of that territory: what each body controls, who sits on it, how its members are chosen, and when it meets. The file will stay current as appointments turn over. Democracy\'s off-Broadway venues deserve at least one season-ticket holder.'
            ),
        ],
        [
            'title' => 'The city and the province are at the table again. Here\'s what\'s on it.',
            'desk' => 'politics', 'dateline' => '', 'byline' => 'London Lookout staff',
            'lede' => 'Transit funding, housing infrastructure, health capacity: the municipal-provincial negotiation is permanent — only the leverage changes.',
            'image' => '', 'featured' => 0, 'views' => 47, 'published' => $ago('-24 hours'),
            'tags' => 'intergovernmental, Queen\'s Park',
            'body' => $p(
                'Canadian cities live in a permanent negotiation with their provinces, because the constitution gave municipalities responsibilities and provinces the revenue tools. The negotiation never concludes; it just changes tables. This season\'s tables, for London, are transit operating support, infrastructure funding tied to housing delivery, and the capacity of a hospital system that serves a region far larger than the city\'s tax base.',
                'The leverage runs both ways, which coverage tends to miss. The province needs mid-sized cities to hit its housing numbers and host its industrial strategy; the cities need provincial money to make either possible. Each side\'s announcements are best read as bids in that exchange rather than as gifts or grievances.',
                'The Lookout tracks this file by outcome, not communiqué: dollars that actually flowed, agreements with signatures, conditions with deadlines. When the table produces one of those, it will be reported here with the arithmetic attached.'
            ),
        ],

        /* --- Opinion: one row ----------------------------------------------- */
        [
            'title' => 'A lookout is a promise',
            'desk' => 'opinion', 'dateline' => '', 'byline' => 'The editorial board',
            'lede' => 'What this paper is for, in three hundred words the newsroom intends to be held to.',
            'image' => '', 'featured' => 0, 'views' => 58, 'published' => $ago('-26 hours'),
            'tags' => 'editorial, mission',
            'body' => $p(
                'A lookout is a job before it is a place. The person in the tower is not there for the view; they are there because someone has to keep watching after everyone else reasonably goes back to their day. That is the promise in this paper\'s name, and it is a falsifiable one: either the files stay open or they don\'t.',
                'London is well supplied with news in the older sense — events, announcements, the day\'s occurrences competently relayed. What mid-sized cities lose first, as newsrooms thin, is not coverage of events but custody of processes: the budget that takes eight months, the zoning fight that takes three years, the staffing formula that takes a decade to show its results. Processes are where the decisions actually live, and processes are precisely what episodic coverage cannot hold.',
                'So the Lookout\'s architecture is built around the standing file. Stories here are meant to accumulate — dated, cumulative, checkable — until the file resolves, and to say plainly when we got something wrong along the way. The Watch rail on the front page is not a design flourish; it is the promise, rendered in type, every day, in fixed positions where its absence would be noticed.',
                'We are independent, locally operated, and funded by readers and clearly labelled advertising — never by the subjects of the files. Hold us to all of it. The tower is staffed.'
            ),
        ],

        /* --- Forest City Life: three visual cards --------------------------- */
        [
            'title' => 'The houses that hold the city\'s stories open their doors again',
            'desk' => 'culture', 'dateline' => 'London', 'byline' => 'London Lookout staff',
            'lede' => 'Season launches across the city\'s stages are an annual bet on the audience — and the audience has been changing faster than the subscription model.',
            'image' => $img('photo-06.svg'), 'image_caption' => 'House lights down, one spot up.', 'image_credit' => 'Illustration for the Lookout',
            'featured' => 0, 'views' => 44, 'published' => $ago('-30 hours'),
            'tags' => 'theatre, arts, season',
            'body' => $p(
                'Every fall the city\'s stages — the grand old houses downtown, the black boxes, the campus theatres, the halls that moonlight — publish their bets in the form of a season. A season announcement is a financial document wearing an artistic one: each title is a wager on who will buy a ticket in February, made in the spring before.',
                'The wager has gotten harder everywhere, and the reasons are structural rather than local. Subscription — the model that let a theatre sell February in May — has been eroding for a generation, replaced by single-ticket decisions made days out. That shifts risk onto the institution and rewards either the very safe or the genuinely unmissable, thinning the middle where a lot of good work used to live.',
                'What makes London\'s version of this story worth a file is the density of the bet. For a city its size, the stage infrastructure here is unusually deep — professional, community, campus and touring houses within a few kilometres of each other — which means the audience is genuinely shared and the seasons are, whether the venues admit it or not, one portfolio.',
                'The Lookout will cover the portfolio: what was programmed, what sold, what surprised, and what the pattern says about who this city\'s audience is becoming. The lights go down on schedule. The interesting question is who is in the seats.'
            ),
        ],
        [
            'title' => 'The market question: what a public market is actually for',
            'desk' => 'culture', 'dateline' => 'London', 'byline' => 'London Lookout staff',
            'lede' => 'Part grocery, part landlord, part civic living room — the downtown market carries expectations no single business model can satisfy. That\'s the point.',
            'image' => $img('photo-07.svg'), 'image_caption' => 'Market morning, stalls open.', 'image_credit' => 'Illustration for the Lookout',
            'featured' => 0, 'views' => 39, 'published' => $ago('-34 hours'),
            'tags' => 'food, downtown, markets',
            'body' => $p(
                'Every city with a historic public market eventually holds the same argument, and London\'s turn comes around regularly: is the market a business that should pay its way, an incubator that should grow food businesses, or a public amenity that should be judged like a park? The argument recurs because the honest answer is all three, and the three pull in different directions.',
                'Run it purely as real estate and the rents select for whoever can pay, which is rarely the farmer or the first-time food entrepreneur the market\'s mythology celebrates. Run it purely as an incubator and the vacancies between graduations make the hall feel like a hallway. Run it purely as an amenity and the operating subsidy becomes a budget-season target with a bullseye painted by whoever is angriest that year.',
                'The cities that do markets well stop pretending one lens suffices. They fund the amenity share transparently, run the commercial share commercially, and measure the incubator share by graduations into storefronts — three ledgers, honestly kept, instead of one ledger everyone resents.',
                'Downtown\'s next act, whatever it is, needs its living room working. The Lookout will treat the market as what it is: a small institution with an outsized job, deserving of both affection and audited statements.'
            ),
        ],
        [
            'title' => 'A city of music, if it chooses to hear itself',
            'desk' => 'culture', 'dateline' => 'London', 'byline' => 'London Lookout staff',
            'lede' => 'The designation is international; the venues, rehearsal rooms and late-night bylaws are municipal. The distance between the two is the story.',
            'image' => $img('photo-08.svg'), 'image_caption' => 'Load-in, early evening.', 'image_credit' => 'Illustration for the Lookout',
            'featured' => 0, 'views' => 36, 'published' => $ago('-38 hours'),
            'tags' => 'music, venues, culture',
            'body' => $p(
                'London holds an international designation as a city of music, which is the kind of honour that is either a description or a homework assignment depending on what happens next. Designations are earned by heritage and scene — and this city\'s claims on both are real — but they are kept by infrastructure, and music infrastructure is relentlessly municipal.',
                'The load-bearing pieces are unglamorous: small and mid-sized venues that can survive their rent, rehearsal space priced for people who don\'t have label money, noise and licensing bylaws that treat a stage as an asset rather than a complaint generator, transit that runs when shows end. Every city that has watched its scene hollow out lost one of those pieces first and noticed later.',
                'The economics have a civic punchline. Music scenes are among the cheapest cultural infrastructure a city can keep — the capital is mostly private, the labour is mostly love — and among the most expensive to rebuild once gone. The policy asks are correspondingly small: zoning foresight, a licensing regime with a memory, and someone at city hall whose job description includes the word.',
                'The Lookout will keep the file the way the designation deserves: venue by venue, bylaw by bylaw, season by season. A city of music is not something a certificate makes true. It is something a Tuesday night makes true.'
            ),
        ],
    ],
];
