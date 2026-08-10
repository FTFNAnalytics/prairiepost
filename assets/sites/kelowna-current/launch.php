<?php
/**
 * Kelowna Current — launch package.
 * Loaded once by `PP_SITE=kelowna-current php tools/seed-launch.php`.
 * Identity, rails, wire sources, and launch stories with commissioned art;
 * the demonstration stories are launch content in the paper's voice, meant
 * to be replaced by real reporting.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$img = fn (string $file) => '/assets/sites/kelowna-current/img/' . $file;

return [

    'desks' => [
        ['name' => 'Okanagan', 'slug' => 'okanagan', 'color' => '#1C6F8A', 'description' => 'Home base: the valley, its cities, and the systems they share.'],
        ['name' => 'BC News',  'slug' => 'bc-news',  'color' => '#1E5631', 'description' => 'The province beyond the bridges, from the Island to the Kootenays.'],
        ['name' => 'Economy',  'slug' => 'economy',  'color' => '#8B641B', 'description' => 'Work, investment, and the money that moves the province.'],
        ['name' => 'Housing',  'slug' => 'housing',  'color' => '#1C6F8A', 'description' => 'Supply, policy, and the places people can and cannot afford to live.'],
        ['name' => 'Climate',  'slug' => 'climate',  'color' => '#365B46', 'description' => 'Snowpack, river temperature, fire weather — the numbers underneath everything.'],
        ['name' => 'Culture',  'slug' => 'culture',  'color' => '#7A4E9E', 'description' => 'Stages, galleries, festivals, and the rooms that hold them.'],
    ],

    'settings' => [
        'site_title'         => 'Kelowna Current',
        'tagline'            => 'From the Okanagan. Across British Columbia.',
        'meta_description'   => 'Independent B.C. journalism with an Okanagan point of view. Clear reporting, regional intelligence and a wider view of the province.',
        'footer_line'        => 'Independent B.C. journalism with an Okanagan point of view. Clear reporting, regional intelligence and a wider view of the province.',
        'weather_line'       => 'Kelowna · 24°C · Clear',
        'contact_email'      => 'tips@kelownacurrent.ca',
        'newsletter_heading' => 'The Morning Current',
        'newsletter_copy'    => 'A concise weekday briefing from Kelowna and across British Columbia — what happened, why it matters, and what to watch next.',
        'breaking_label'     => 'Morning briefing: the five B.C. stories shaping the day',
        'breaking_url'       => '/newsletter/',
        'regions'            => json_encode([
            'okanagan' => 'Okanagan',
            'bc'       => 'British Columbia',
            'canada'   => 'Canada',
        ]),
    ],

    'sources' => [
        ['Kelowna Capital News',     'https://www.kelownacapnews.com/feed',        'okanagan'],
        ['Vernon Morning Star',      'https://www.vernonmorningstar.com/feed',     'okanagan'],
        ['Penticton Western News',   'https://www.pentictonwesternnews.com/feed',  'okanagan'],
        ['Global Okanagan',          'https://globalnews.ca/okanagan/feed/',       'okanagan'],
    ],

    'stories' => [
        [
            'title' => "The valley's next decade will be decided by what it builds now",
            'desk' => 'okanagan', 'dateline' => 'Kelowna', 'byline' => 'Kelowna Current staff',
            'lede' => 'Housing, water, transportation and public space are converging into one defining regional question: how should the Okanagan grow?',
            'image' => $img('photo-01.svg'), 'image_caption' => 'The valley at evening, from the west bench.', 'image_credit' => 'Illustration for the Current',
            'featured' => 1, 'placement' => 'hero', 'views' => 160, 'published' => $ago('-2 hours'),
            'tags' => 'growth, housing, Okanagan',
            'body' => $p(
                'Four files that usually live in four separate council agendas — housing targets, water licensing, transit expansion and parkland acquisition — landed on Okanagan decision-makers\' desks in the same six-week stretch this summer. Treated separately, each is manageable. Read together, they are a single question: what should this valley look like in 2036, and who decides?',
                'The arithmetic that forces the question is not controversial. The region adds a mid-sized town\'s worth of people every three years. The lake system that supplies it is fully allocated in dry years. The highway that connects it operates at capacity every summer Friday. And the land that could absorb growth cheaply — flat, dry, unirrigated — is exactly the land the agricultural reserve exists to protect.',
                'What is controversial is sequencing. Build the housing first and the infrastructure debt compounds; build the infrastructure first and the housing critics call it sprawl-enabling; wait for a regional plan and the market builds the interim on its own terms. Every Okanagan council is currently choosing among those three errors, mostly alone.',
                'This special report opens a series. Over the coming months the Current will follow the growth file wherever it actually gets decided — council chambers, water boards, the agricultural land commission, the legislature — on the theory that a valley that can see the whole board plays it better. The next decade is being designed now. The design reviews are open to the public.'
            ),
        ],
        [
            'title' => 'Three decisions that will define the fall session',
            'desk' => 'politics', 'dateline' => 'Victoria', 'byline' => 'Renata Oduya',
            'lede' => 'A housing-target review, a water act amendment, and a health staffing formula — each arrives with a deadline attached.',
            'image' => $img('photo-02.svg'), 'image_caption' => 'The legislature, before the session.', 'image_credit' => 'Illustration for the Current',
            'featured' => 0, 'placement' => 'featured', 'views' => 118, 'published' => $ago('-5 hours'),
            'tags' => 'legislature, fall session, policy',
            'body' => $p(
                'The order paper for the fall session is long, but three items on it will do most of the deciding — and all three carry deadlines that make deferral, the legislature\'s favourite outcome, unusually expensive.',
                'The first is the housing-target review: the two-year-old system of municipal targets comes up for its scheduled evaluation, and the government must either put numbers behind its enforcement threats or admit the targets are advisory. Twelve municipalities, four of them in the Interior, are currently behind their curves.',
                'The second is the water act amendment, which decides how the province allocates water in basins that are already fully subscribed — a category that now includes most of the Okanagan. The current rules resolve scarcity by seniority of licence; the amendment would let regional boards weigh use. Every irrigation district and every growing city has a position, and they are not the same position.',
                'The third is the health staffing formula, the funding mechanism behind every rural emergency-room closure notice of the past two years. The proposed replacement funds teams rather than positions. Whether that sentence means anything will be visible in the schedules of about forty B.C. hospitals by spring. The session opens the first week of October. The Current will read the fine print so you can read the consequences.'
            ),
        ],
        [
            'title' => 'Why smaller B.C. cities are rewriting their growth plans',
            'desk' => 'housing', 'dateline' => '', 'byline' => 'Devon Tran',
            'lede' => 'The provincial density rules were written for big-city transit corridors. The cities redrawing themselves fastest are the ones without any.',
            'image' => $img('photo-03.svg'), 'image_caption' => 'Infill going up beside postwar blocks.', 'image_credit' => 'Illustration for the Current',
            'featured' => 0, 'placement' => 'featured', 'views' => 96, 'published' => $ago('-8 hours'),
            'tags' => 'housing, growth plans, municipalities',
            'body' => $p(
                'The provincial housing legislation of the past three years was drafted with a picture in its head: a transit station, a tower, a big-city planning department to argue about the difference. The more interesting story is happening in the cities the picture left out.',
                'Vernon, Penticton, Kamloops, Courtenay, Nelson — mid-sized cities with no rapid transit and small planning staffs — are rewriting their official community plans this year, and several have quietly gone further than the legislation requires. The reason is arithmetic, not ideology: a city of forty thousand that permits four units on any lot has effectively re-zoned itself in one bylaw, without a tower debate, because nobody proposes towers there anyway.',
                'The pattern the plans share is a bet on what planners call gentle density and residents call fourplexes-and-coach-houses: capacity added inside existing neighbourhoods, on existing pipes, at a scale existing streets can absorb. The bet\'s weakness is also shared: the infrastructure that was sized for one house per lot, particularly water and sewer trunks, and the provincial capital programs that still fund upgrades as if growth were optional.',
                'The rewritten plans land at public hearings through the winter. The hearings will be long, and — on the evidence of the first three — better tempered than the big-city versions. It is easier to argue about a fourplex when everyone in the room has seen one.'
            ),
        ],
        [
            'title' => 'A new era of water planning is arriving in the Interior',
            'desk' => 'climate', 'dateline' => 'Okanagan Basin', 'byline' => 'Mara Castellain',
            'lede' => 'The basin\'s licences now exceed its dry-year supply. The plans being drafted this year decide who blinks first.',
            'image' => $img('photo-04.svg'), 'image_caption' => 'The lake, the intakes, and the hills that feed them.', 'image_credit' => 'Illustration for the Current',
            'featured' => 0, 'placement' => 'featured', 'views' => 84, 'published' => $ago('-11 hours'),
            'tags' => 'water, Okanagan Basin, planning',
            'body' => $p(
                'The number that reorganizes everything arrived without ceremony, in a technical appendix: the Okanagan basin\'s licensed water allocations now exceed its modelled dry-year supply. Not its average supply — the basin still balances in a normal year — but the dry year that the climate record says to expect roughly one year in five.',
                'The gap is not a crisis; it is a queue. Water law resolves scarcity by licence seniority, which means in a dry year the systems that blink first are the newest — which in this valley means the newest subdivisions and the newest orchein blocks, not the oldest. The people who bought last carry the risk they were never shown.',
                'The response now underway is the least glamorous work in public administration: basin-scale drought plans, drafted jointly by the water utilities, the irrigation districts, the province and the Okanagan Nation Alliance, that decide the curtailment order in advance — trading the seniority queue for a negotiated one, with agriculture, ecosystems and households each holding defined positions.',
                'The plans go to their boards this winter. They are long, technical and unlikely to trend. They are also, in a fully allocated basin, the closest thing the valley has to a constitution — and unlike most constitutions, this one is being written while everyone can still afford to be reasonable.'
            ),
        ],
        [
            'title' => "What the province's new investment map reveals",
            'desk' => 'economy', 'dateline' => '', 'byline' => 'Josef Aylward',
            'lede' => 'Plotted on a map, the capital projects tell a different story than the press releases: the money is moving inland.',
            'image' => $img('photo-05.svg'), 'image_caption' => 'The capital plan, by region and by year.', 'image_credit' => 'Illustration for the Current',
            'featured' => 0, 'views' => 71, 'published' => $ago('-14 hours'),
            'tags' => 'economy, capital plan, investment',
            'body' => $p(
                'The province publishes its major-projects inventory quarterly, as a spreadsheet, which is a reliable way to keep it out of the news. The Current plotted the past five years of it on a map. The map says something the individual announcements never quite do: the centre of gravity of B.C. capital investment is moving inland.',
                'The coastal megaprojects still dominate the totals — they always will, a single LNG train outweighs a decade of mid-sized work. But strip the top five projects out and look at the volume underneath: hospitals, campuses, transmission, water systems, food processing. The Interior\'s share of that layer has grown every year since 2021, and last year it passed the Lower Mainland\'s for the first time in the inventory\'s history.',
                'The drivers are unromantic. Interior land is cheaper, Interior growth rates are higher, and two decades of deferred infrastructure in fast-growing valleys — this one prominently included — are coming due at once. The investment map is, in large part, a map of overdue maintenance meeting population curves.',
                'What the map cannot show is the constraint every inland project now cites in its risk register: labour, housing for labour, and the water and power connections that used to be the easy part. The money is moving inland faster than the capacity to absorb it — which is either next year\'s problem or, read correctly, this year\'s warning.'
            ),
        ],
        [
            'title' => 'A corridor decision moves from study to public debate',
            'desk' => 'bc-news', 'dateline' => '', 'byline' => 'Current staff',
            'lede' => 'After four years of technical work, the Highway 97 corridor options go public — all three of them expensive, none of them neutral.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 58, 'published' => $ago('-20 hours'),
            'tags' => 'transportation, Highway 97, corridor',
            'body' => $p(
                'The corridor study that has absorbed four years and several million dollars of quiet technical work goes public next month, and with it the decision it was designed to frame: what to do about the stretch of Highway 97 that everyone in the valley can name without being told which stretch.',
                'The study presents three options — widen in place, bypass on the bench, or hold the alignment and move the demand onto transit — priced within sight of each other and different in every other respect. Widening buys a decade at the cost of every frontage business on the strip. The bypass buys permanence at the cost of the bench\'s farmland and a decade of construction. The transit option costs least in concrete and most in political nerve.',
                'The public sessions run through the fall, and the ministry\'s framing document is honest about what they are for: not to choose the engineering, which is settled, but to measure what the region will tolerate. Corridor decisions in B.C. are cancelled by opposition more often than by cost.',
                'The Current will publish the full options analysis, annotated, when the sessions open — along with the map every corridor debate needs and rarely gets: who owns the land along each alignment, and since when.'
            ),
        ],
        [
            'title' => 'Interior communities make the case for local fibre',
            'desk' => 'bc-news', 'dateline' => '', 'byline' => 'Current staff',
            'lede' => 'Six mills have closed in three years. The towns around them want the logs that remain processed where they fall.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 47, 'published' => $ago('-24 hours'),
            'tags' => 'forestry, fibre, Interior',
            'body' => $p(
                'The delegation that met the forests minister this week carried a one-page ask from eleven Interior communities: when the annual cut shrinks, let the remaining logs be processed close to where they fall, rather than trucked to whichever surviving supermill bid highest.',
                'The context is three years of closures — six mills, four of them the only major employer in their town — driven by a fibre supply that fire, beetle and old-growth deferrals have cut faster than anyone\'s business plan assumed. The logs that remain are the object of a quiet bidding war, and the communities losing it are the small ones.',
                'The policy on the table is fibre-proximity weighting in timber sales: points, not mandates, for bids that process within a defined radius. Its supporters call it keeping public wood connected to public benefit. Its critics — including, carefully, the major licensees — call it a subsidy for inefficiency dressed as regional policy.',
                'Both descriptions are accurate, which is why the file is hard. The minister promised an answer before the spring sales. The eleven towns, whose mills cannot wait through many more sales cycles, heard the timeline and asked, politely, for a faster one.'
            ),
        ],
        [
            'title' => 'The rural recruitment model getting a second look',
            'desk' => 'bc-news', 'dateline' => '', 'byline' => 'Current staff',
            'lede' => 'Pay a team, not a position: the staffing experiment that kept three small ERs open is up for provincial adoption.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 39, 'published' => $ago('-28 hours'),
            'tags' => 'health, rural, staffing',
            'body' => $p(
                'Three small-town emergency rooms that spent 2023 posting closure notices spent the past year fully staffed, and the experiment that did it — contracting a physician team collectively rather than recruiting doctors one posting at a time — is now on the health ministry\'s desk for provincial adoption.',
                'The model\'s insight is social rather than financial. Rural recruitment fails, its designers argue, because it asks one physician at a time to accept professional isolation. The team contract hires four to six doctors as a group, guarantees the schedule that makes coverage shared rather than heroic, and lets the group govern its own rotation. Two of the three pilot teams recruited themselves — colleagues recruiting colleagues — which no incentive payment has ever managed.',
                'The costs are real: team contracts price above the sum of the positions they replace, and the doctors\' own association is split between members who want the model everywhere and members who read it as the end of fee-for-service by instalments.',
                'The ministry\'s evaluation, due mid-winter, will decide whether three towns were an anomaly or a template. The forty-odd communities currently running their ERs on locums and luck have already decided, and are lining up to be next.'
            ),
        ],
        [
            'title' => 'Grid planning becomes a kitchen-table issue',
            'desk' => 'economy', 'dateline' => '', 'byline' => 'Josef Aylward',
            'lede' => 'Heat pumps, EVs and new industry are arriving faster than substations. The utility\'s ten-year plan is suddenly everyone\'s business.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 33, 'published' => $ago('-32 hours'),
            'tags' => 'energy, grid, electrification',
            'body' => $p(
                'The document was designed to be unreadable — a utility\'s ten-year capital plan, filed with a regulator, in the format regulators love and citizens ignore. This year it has a fan base, because buried in its load forecasts is the answer to a question arriving at kitchen tables across the province: when you electrify the house, will the grid be there?',
                'The forecast\'s honest answer is: mostly, eventually, unevenly. Provincial generation is adequate for the decade; the pinch is distribution — the substations and feeders that turn a strong grid into a usable one. The plan\'s own maps show constrained zones in exactly the places growth is fastest, several of them in this valley, where new connections above a modest size already queue.',
                'The queue is where households meet industry. A subdivision\'s heat pumps, a packing plant\'s expansion and a charging depot draw on the same feeder, and the plan\'s allocation rules — first come, first served, confidential — were written for an era when nobody was coming.',
                'The regulator\'s hearings open in the new year, and for once the intervenor list includes municipalities, farm groups and school districts alongside the usual industrial counsel. The grid was infrastructure\'s quietest file for fifty years. The quiet, on the evidence of the filing room, is over.'
            ),
        ],
        [
            'title' => 'The regional stories that disappear when every issue is treated as local',
            'desk' => 'okanagan', 'dateline' => 'Kelowna', 'byline' => 'Kelowna Current staff',
            'lede' => 'A new reporting desk follows the shared systems connecting communities from Vernon to Osoyoos.',
            'image' => $img('photo-06.svg'), 'image_caption' => 'One valley, many councils.', 'image_credit' => 'Illustration for the Current',
            'featured' => 0, 'views' => 64, 'published' => $ago('-44 hours'),
            'tags' => 'Okanagan, regional desk, systems',
            'body' => $p(
                'A commuter who lives in Lake Country, works in Kelowna and plays hockey in Vernon crosses three municipal jurisdictions, two regional districts and one watershed board before dinner — and is served by news coverage that treats each as a separate universe. The Current\'s Okanagan desk exists to cover the valley the way its residents actually live in it.',
                'The gap is structural, not a failure of effort. Municipal reporting follows councils, because councils meet, vote and quote well. But the valley\'s defining systems — the lake, the aquifer, the highway, the transit network, the airshed, the housing market — are regional, governed by boards that meet quarterly in rooms without cameras, making decisions no single council controls and no single-town story can explain.',
                'The desk\'s first files follow directly: the water arithmetic that this week\'s reporting opened; the transit governance question that six councils are separately not solving; and the quiet regional housing numbers, in which every municipality individually hits its target while the valley collectively misses.',
                'The premise is simple enough to state and hard enough to sustain: Kelowna is home base, not the whole map. The desk will be judged by how often the dateline is somewhere else.'
            ),
        ],
        [
            'title' => 'Where infrastructure and community planning meet',
            'desk' => 'bc-news', 'dateline' => 'Prince George', 'byline' => 'Current staff',
            'lede' => 'A dispatch from the province\'s fastest-changing resource corridor.',
            'image' => $img('photo-07.svg'), 'image_caption' => 'The northern corridor at dusk.', 'image_credit' => 'Illustration for the Current',
            'featured' => 0, 'views' => 42, 'published' => $ago('-48 hours'),
            'tags' => 'Northern B.C., infrastructure, planning',
            'body' => $p(
                'PRINCE GEORGE — The maps on the wall of the regional planning office tell the corridor\'s story in layers: the transmission line under construction, the highway upgrades behind it, the subdivision applications behind those, and — in the newest layer, added this year — the schools and clinics that the first three layers eventually require.',
                'The north has hosted resource booms before, and the towns along the corridor carry the architecture of each: the instant neighbourhoods of the seventies, the empty storefronts of the busts between. What planners here say is different this time is sequencing — an attempt, imperfect and underfunded, to build the community infrastructure alongside the industrial kind rather than a decade after it.',
                'The attempt shows in small decisions. A workforce camp approved only with a transition plan to permanent housing. A water treatment plant sized for the town the projections describe, not the town that exists. A school site banked beside a subdivision that is still gravel.',
                'Whether the sequencing holds depends on budgets set far from here, which is the corridor\'s oldest story. But the planning rooms of the north are, for the moment, doing something the south could study: treating growth as a thing to be designed, not merely absorbed.'
            ),
        ],
        [
            'title' => 'Housing pressure reaches beyond the biggest cities',
            'desk' => 'housing', 'dateline' => 'Courtenay', 'byline' => 'Devon Tran',
            'lede' => 'Why mid-sized Island communities are asking different questions about supply.',
            'image' => $img('photo-08.svg'), 'image_caption' => 'The mid-Island coast, where the pressure arrived quietly.', 'image_credit' => 'Illustration for the Current',
            'featured' => 0, 'views' => 36, 'published' => $ago('-52 hours'),
            'tags' => 'Vancouver Island, housing, supply',
            'body' => $p(
                'COURTENAY — The housing pressure that defined Victoria a decade ago has moved up-Island on schedule, and the mid-sized communities absorbing it are asking a question the big cities skipped: supply of what, for whom?',
                'The Comox Valley\'s numbers sketch the pattern. Prices driven by in-migration — retirees, remote workers, and Vancouver equity arriving by ferry — rather than local wages; a rental vacancy rate below one per cent; and a construction industry building, rationally, for the buyers who exist rather than the workers who serve them.',
                'The different question shows up in the tools. Where the metros debated towers, the Island\'s mid-sized councils are regulating tenure: residency-linked incentives, non-market land trusts seeded with municipal property, and — the sharpest instrument — zoning that permits the fourth unit only if one is secured rental. The legal footing for some of it is untested, which the councils acknowledge with a shrug that says: so test it.',
                'What the Island communities have that the metros lacked is timing. The pressure arrived here with a decade\'s warning and a library of other cities\' mistakes. The next two years of council votes will show whether warning is the same thing as preparation.'
            ),
        ],
        [
            'title' => 'The decisions beneath the announcement',
            'desk' => 'politics', 'dateline' => 'Victoria', 'byline' => 'Renata Oduya',
            'lede' => 'A weekly guide to what changed, who gains, who pays and what comes next.',
            'image' => $img('photo-09.svg'), 'image_caption' => 'The buildings where the fine print lives.', 'image_credit' => 'Illustration for the Current',
            'featured' => 0, 'views' => 55, 'published' => $ago('-56 hours'),
            'tags' => 'politics, accountability, analysis',
            'body' => $p(
                'Every provincial announcement is two documents. The first is the news release: the number, the ribbon, the quote. The second — the order-in-council, the service plan amendment, the funding letter — is where the actual decision lives, and it is published later, quieter, and in a format designed to discourage company. This column reads the second document.',
                'The habit pays immediately. This week\'s example: a childcare expansion announced at $180 million turns out, in the funding letters, to be $180 million over five years, back-loaded, with year one carrying eleven per cent of the total — a real commitment, but a 2029 commitment wearing a 2026 press release.',
                'The point is not gotcha. Governments of every stripe announce this way, because announcements are free and appropriations are not. The point is that citizens, councils and school boards plan against the first document and are then surprised by the second, and the surprise is avoidable with an hour\'s reading.',
                'The column runs weekly, and its format is fixed: what changed, who gains, who pays, and what to watch next. Send the announcements that deserve the treatment to the tips line. There is never a shortage; there is only a queue.'
            ),
        ],
        [
            'title' => 'Reading the fine print in a major provincial commitment',
            'desk' => 'politics', 'dateline' => '', 'byline' => 'Renata Oduya',
            'lede' => 'The hospital pledge is real. The schedule underneath it is doing the heavy lifting.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 29, 'published' => $ago('-68 hours'),
            'tags' => 'accountability, capital plan, health',
            'body' => $p(
                'The commitment is genuine: the Interior hospital expansion is named in the capital plan, costed, and assigned a delivery agency. The reading that matters is in the schedule columns, where the project\'s spending curve tells a different story than its announcement did.',
                'Of the headline figure, four per cent lands in the plan\'s first year — design money. The construction majority sits in years four and five, beyond both the plan\'s firm horizon and, as it happens, the next fixed election date. None of this is improper. All of it is informative.',
                'The pattern to watch is the one B.C. capital plans have repeated for twenty years: projects migrate rightward one year per year until either a business case locks them (the tender is the true commitment) or a fiscal update quietly rebases them.',
                'The milestone that converts this pledge from probable to certain is the procurement notice, expected — per the delivery agency\'s own board minutes — within eight months. Put it in your calendar. This column will have it in ours.'
            ),
        ],
        [
            'title' => 'Why the next round of local budgets will look different',
            'desk' => 'politics', 'dateline' => '', 'byline' => 'Current staff',
            'lede' => 'Insurance, RCMP contracts and asset renewal are eating the room that used to absorb quiet years.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 24, 'published' => $ago('-72 hours'),
            'tags' => 'municipalities, budgets, taxes',
            'body' => $p(
                'The budget presentations beginning in council chambers across B.C. this month share a slide, more or less: the base budget — the cost of doing exactly what the city did last year — is up between six and nine per cent, before a single new service is discussed.',
                'Three lines drive it everywhere. Insurance premiums for municipal assets have roughly doubled in five years, with wildfire and flood exposure repricing whole regions at once. The federal RCMP contract settlement continues to flow through local policing bills. And the asset-renewal gap — the pipes and roads bought in the seventies, all aging on the same schedule — has moved from the appendix to the levy in city after city.',
                'What makes the round politically volatile is that none of the drivers buys anything visible. A six per cent increase that opens a rink is defensible; a six per cent increase that keeps yesterday running is a harder speech, and councils facing election next fall know it.',
                'The escape valves are few and mostly provincial: growth that pays its own way, infrastructure grants, or new revenue tools that every UBCM convention requests and no government has granted. Absent those, the different-looking budgets will look the same everywhere — thinner, slower, and honest in a way ratepayers may not enjoy.'
            ),
        ],
        [
            'title' => 'The five institutions quietly shaping land-use policy',
            'desk' => 'politics', 'dateline' => '', 'byline' => 'Mara Castellain',
            'lede' => 'An analysis of the boards and commissions that decide more about B.C. land than the legislature does.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 21, 'published' => $ago('-76 hours'),
            'tags' => 'analysis, land use, institutions',
            'body' => $p(
                'Ask where B.C. land policy is made and most answers name the legislature. Follow the decisions instead of the debates, and five quieter institutions do more of the shaping: the Agricultural Land Commission, the water comptroller\'s office, the regional district boards, the environmental assessment office, and — newest and least mapped — the modern-treaty and consent-based governments whose land-use plans now carry statutory weight across growing portions of the province.',
                'Each was designed to insulate a class of decision from short-term politics, and each succeeds — which is the feature and the critique in one sentence. Their hearings are public but unattended; their reasons are published but unread; their appointments, which decide everything, pass without coverage.',
                'The analysis that follows profiles each institution the same way: what it actually controls, how its members are chosen, where its decisions have surprised the governments that appointed it, and the one file on its docket this year that deserves a public gallery.',
                'The series\' premise is the Current\'s standing one: power that is boring is still power, and the boring kind compounds. The first profile — the land commission, deciding this winter how firmly the reserve holds against a housing emergency — runs next week.'
            ),
        ],
        [
            'title' => 'How a B.C. bill becomes law — and where influence enters',
            'desk' => 'opinion', 'dateline' => '', 'byline' => 'The editorial board',
            'lede' => 'An explainer, and an argument: the stages nobody watches are the stages that matter.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 18, 'published' => $ago('-80 hours'),
            'tags' => 'explainer, legislature, influence',
            'body' => $p(
                'The civics-class version has four steps: introduction, debate, committee, royal assent. The working version has seven, and the three the civics class omits — the drafting instructions, the regulation-writing, and the implementation guidance — are where most of the influence enters, because they are the stages with no gallery.',
                'By the time a bill is introduced, its architecture is settled; debate amends at the margins. The consequential lobbying happened months earlier, on the drafting instructions, in meetings the lobbyist registry records only as topics. And the consequential discretion comes months later, in regulations that fill in every number the bill left as a blank — thresholds, fees, timelines, exemptions — signed by cabinet without a vote.',
                'This is not a scandal; it is a design, and it has real virtues. But a public that watches only the debate stage is watching the theatre after the casting is done, and wondering why its attendance changes so little.',
                'The fix is attention, structurally applied: publish drafting consultations, open regulation-making to comment periods with teeth, and — the press\'s own assignment, which this paper accepts — cover the registry and the order-in-council list with the diligence currently reserved for question period. The stages nobody watches would improve remarkably quickly if somebody watched.'
            ),
        ],
        [
            'title' => 'The growers adapting tradition to a changing valley',
            'desk' => 'culture', 'dateline' => 'Naramata Bench', 'byline' => 'Iris Kobau',
            'lede' => 'A seasonal series from orchards, vineyards and farms across the Okanagan.',
            'image' => $img('photo-10.svg'), 'image_caption' => 'The bench in first light, harvest three weeks early.', 'image_credit' => 'Illustration for the Current',
            'featured' => 0, 'views' => 51, 'published' => $ago('-92 hours'),
            'tags' => 'Field Notes, growers, orchards',
            'body' => $p(
                'The Rieslings came off the bench three weeks early this year, which the winemaker in question — third generation, trained in Geisenheim, back home by choice — marks in her ledger without comment, because the ledger has said some version of it for a decade.',
                'Field Notes, the series this dispatch opens, will spend a year with a dozen Okanagan growing families as they adapt: the orchardists grafting to varieties their parents considered exotic; the vineyard rethinking rootstock for heat the textbooks call Mediterranean; the vegetable farm whose season now starts under row cover in February and ends in a November that no longer reliably freezes.',
                'The adaptations are technical, but the series\' interest is cultural. Farming families hold land through change by revising practice while conserving identity — a negotiation, conducted at kitchen tables, about which parts of tradition are the point and which parts were only ever method.',
                'The series runs monthly with the seasons. It begins where the valley\'s year does: in the pruning weeks, when next year\'s crop is decided with hand shears, one deliberate cut at a time.'
            ),
        ],
        [
            'title' => 'Learning to see Okanagan Lake as shared infrastructure',
            'desk' => 'culture', 'dateline' => '', 'byline' => 'Josef Aylward',
            'lede' => 'An essay on the body of water that is reservoir, highway, habitat, backyard and myth at once.',
            'image' => $img('photo-11.svg'), 'image_caption' => 'The lake at evening, working.', 'image_credit' => 'Illustration for the Current',
            'featured' => 0, 'views' => 44, 'published' => $ago('-96 hours'),
            'tags' => 'On the Lake, essay, water',
            'body' => $p(
                'The lake photographs as leisure — the postcard is genuinely earned — and the postcard is the least of what it does. Okanagan Lake is the valley\'s reservoir, its thermostat, its sewage receiver of last resort, its fish habitat, its property-value engine and its oldest transportation corridor, performing all six roles simultaneously for half a million people who mostly picture it as a beach.',
                'Infrastructure is the unromantic word, and this essay argues for it precisely because it is unromantic. Things called scenery get admired; things called infrastructure get budgets, monitoring, maintenance schedules and governance. The lake has admirers to spare and, on the evidence of its foreshore politics, governance spread across so many bodies that responsibility functions as a rumour.',
                'The syilx nations, whose relationship with the lake is older than the postcard by several thousand years, have been making a version of this argument in their own vocabulary for generations: that the lake is a relation with obligations running both ways, not a backdrop.',
                'Seeing the lake as shared infrastructure does not diminish an evening swim; it dignifies it — the way a well-kept water system dignifies a glass of water. The valley\'s best asset deserves the compliment of being taken seriously. Admiration is free. Stewardship has a budget line.'
            ),
        ],
        [
            'title' => 'Inside the studios building a new Interior aesthetic',
            'desk' => 'culture', 'dateline' => 'Vernon', 'byline' => 'Iris Kobau',
            'lede' => 'A photo essay from the workshops where the region is deciding what it looks like.',
            'image' => $img('photo-12.svg'), 'image_caption' => 'Studio light, north Okanagan.', 'image_credit' => 'Illustration for the Current',
            'featured' => 0, 'views' => 31, 'published' => $ago('-100 hours'),
            'tags' => 'arts, studios, Interior',
            'body' => $p(
                'The studios are in fruit-packing sheds, decommissioned churches and one former highway diner, which is the first thing the work has in common: it is made in buildings the valley\'s previous economies left behind.',
                'The second thing is harder to name, and naming it is what this photo essay attempts. The painters, ceramicists, weavers and furniture-makers working across the Interior share no school or manifesto, but the work rhymes — dry-country palettes, materials used plainly, an insistence on function that reads as almost ethical. Curators reaching for a label have started saying "Interior aesthetic" and wincing, which suggests the label is early rather than wrong.',
                'What the studios notably lack is the anxiety of the periphery. A generation of Interior artists assumed serious work required leaving for the coast; the artists in these frames mostly came back, or never left, or moved here on purpose — trading market proximity for space, material access and a subject they can see from the studio door.',
                'The essay runs as photographs with working notes rather than criticism, on the theory that an aesthetic being born deserves documentation before evaluation. The frames begin, as the work does, with the light.'
            ),
        ],
    ],
];
