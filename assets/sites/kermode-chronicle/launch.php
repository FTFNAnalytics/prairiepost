<?php
/**
 * The Kermode Chronicle — launch package.
 * Loaded once by `PP_SITE=kermode-chronicle php tools/seed-launch.php`.
 * Identity, rails, wire sources, and launch stories with commissioned art;
 * the demonstration stories are launch content in the paper's voice, meant
 * to be replaced by real reporting.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$img = fn (string $file) => '/assets/sites/kermode-chronicle/img/' . $file;

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['kermodechronicle.ca'],

    /* --- Desks the Chronicle adds to the network (created if missing) ----- */
    'desks' => [
        ['name' => 'Environment', 'slug' => 'environment', 'color' => '#1C5342', 'description' => 'The land, the water, and the decisions that reach them.'],
        ['name' => 'Wildlife',    'slug' => 'wildlife',    'color' => '#2F6B4B', 'description' => 'The animals this coast is named for, and the science around them.'],
        ['name' => 'Climate',     'slug' => 'climate',     'color' => '#3F7A66', 'description' => 'Snowpack, river temperature, fire weather — the numbers underneath everything.'],
        ['name' => 'Resources',   'slug' => 'resources',   'color' => '#8A5A17', 'description' => 'Forestry, gas, mines and fish: who takes what, and on whose terms.'],
        ['name' => 'Communities', 'slug' => 'communities', 'color' => '#4A5A51', 'description' => 'Dispatches from the towns, as they arrive.'],
        ['name' => 'Coast',       'slug' => 'coast',       'color' => '#1E5C6E', 'description' => 'The islands, the inlets, and the water between them.'],
        ['name' => 'Interior',    'slug' => 'interior',    'color' => '#7A5A2E', 'description' => 'From the Cariboo to the Okanagan, past the last ferry.'],
        ['name' => 'Culture',     'slug' => 'culture',     'color' => '#7A4E9E', 'description' => 'Stages, galleries, festivals, and the rooms that hold them.'],
    ],

    /* --- Settings (written only over untouched defaults) ------------------ */
    'settings' => [
        'site_title'         => 'Kermode Chronicle',
        'tagline'            => 'Reporting from the coast and the interior',
        'meta_description'   => 'Independent, reader-funded journalism for British Columbia: environment, wildlife, climate, resources, and the communities of the coast and the interior.',
        'footer_line'        => 'Published on the territories of the Lekwungen-speaking peoples.',
        'weather_line'       => 'Victoria, B.C.',
        'contact_email'      => 'tips@kermodechronicle.ca',
        'newsletter_heading' => 'The Coast Report',
        'newsletter_copy'    => 'A weekly letter from the Great Bear Rainforest: what our reporters saw, counted, and could not let go of.',
        'field_notes_text'   => 'One in ten black bears on the central coast carries the white coat. Our reporters keep a running record of confirmed sightings, by watershed.',
        'field_notes_url'    => '/search?q=kermode',
        'regions'            => json_encode([
            'bc'     => 'British Columbia',
            'canada' => 'Canada',
        ]),
    ],

    /* --- Wire sources (added only if the URL is new to the network) ------- */
    'sources' => [
        ['CBC British Columbia',       'https://www.cbc.ca/webfeed/rss/rss-canada-britishcolumbia', 'bc'],
        ['The Narwhal',                'https://thenarwhal.ca/feed/',                               'bc'],
        ['Terrace Standard',           'https://www.terracestandard.com/feed',                      'bc'],
        ['Prince Rupert Northern View','https://www.thenorthernview.com/feed',                      'bc'],
        ['Times Colonist',             'https://www.timescolonist.com/rss',                         'bc'],
    ],

    /* --- Launch stories ---------------------------------------------------- */
    'stories' => [
        [
            'title' => 'Province defers logging on 2,100 hectares in the Nass Valley',
            'desk' => 'environment', 'dateline' => 'Terrace', 'byline' => 'Adele Cruikshank',
            'lede' => 'The deferral covers three cutblocks approved in 2019, and lands the question of long-term protection with a joint planning table that has not met since March.',
            'image' => $img('photo-01.svg'), 'image_caption' => 'Cutblock 41, above the Cranberry River.', 'image_credit' => 'Illustration for the Chronicle',
            'featured' => 1, 'placement' => 'hero', 'views' => 140, 'published' => $ago('-2 hours'),
            'tags' => 'old growth, nass valley, forestry',
            'body' => $p(
                'The province quietly deferred logging Tuesday on 2,100 hectares of old growth in the Nass Valley, pausing three cutblocks that were approved in 2019 and have sat at the centre of the region\'s longest-running forestry argument since.',
                'The deferral is two years, renewable once. The ministry\'s letter — released to licence holders Monday and to everyone else by way of a licence holder — describes it as "an interim measure while long-term planning proceeds." The long-term planning in question belongs to a joint table of provincial staff and area nations that, by its own published schedule, has not met since March.',
                'The stands at issue are the kind the deferral system was invented for: western redcedar in the valley bottoms, some of it modelled at over five hundred years, on terrain gentle enough to log profitably — which is precisely why it is still standing nowhere else. The licensee holds approved permits and has twice postponed road-building on its own initiative, a patience the company\'s regional forester described last year as "not indefinite."',
                'What happens next depends on a meeting nobody has scheduled. The deferral buys the table two years; the table has so far used zero of the twenty-nine months it has already had. "A deferral is not a decision," said one negotiator familiar with the file, who asked not to be named because the file is the reason they have a job. "It is a decision not to decide, with a calendar attached."'
            ),
        ],
        [
            'title' => 'Fraser sockeye forecast halved as river temperatures climb',
            'desk' => 'climate', 'dateline' => 'Mission', 'byline' => 'Priya Naidu',
            'lede' => 'The in-season estimate has been revised twice since June. Test fisheries at Mission are reporting the warmest August water in the record.',
            'image' => $img('photo-02.svg'), 'image_caption' => 'The test fishery reach at Mission, mid-run.', 'image_credit' => 'Illustration for the Chronicle',
            'featured' => 0, 'placement' => 'featured', 'views' => 112, 'published' => $ago('-5 hours'),
            'tags' => 'sockeye, fraser river, salmon',
            'body' => $p(
                'The in-season forecast for the Fraser sockeye run was cut nearly in half Friday, the second downward revision since June, and the reason is not a mystery anyone is bothering to preserve: the river is too warm.',
                'The test fishery at Mission recorded water above nineteen degrees for eleven consecutive days in early August — the warmest stretch in the station\'s record. Past eighteen degrees, migrating sockeye begin burning energy faster than they can spare it; past twenty, pre-spawn mortality climbs steeply enough that managers start subtracting fish from the forecast before anyone catches them.',
                'The panel\'s response so far has been the standard sequence: reduced openings, then no openings, then the harder conversation about whether the late-summer runs should be fished at all in warm years. Two of the four management groups are now below the threshold where any commercial opening is contemplated.',
                'The longer arc is the uncomfortable part. The river\'s August temperature has crossed the stress threshold in seven of the past ten years; the forecast model that once treated warm water as an anomaly now carries it as an assumption. As one biologist on the panel put it: "We used to model the run and adjust for temperature. We now model the temperature and adjust for run."'
            ),
        ],
        [
            'title' => 'Two ministries claim the caribou file, and neither will say who signs',
            'desk' => 'politics', 'dateline' => 'Victoria', 'byline' => 'Sofia Marchand',
            'lede' => 'Recovery spending is announced annually and audited never. A one-line answer in estimates showed why.',
            'image' => $img('photo-10.svg'), 'image_caption' => 'The legislature from the harbour, estimates week.', 'image_credit' => 'Illustration for the Chronicle',
            'featured' => 0, 'views' => 84, 'published' => $ago('-8 hours'),
            'tags' => 'caribou, legislature, accountability',
            'body' => $p(
                'It took a single question in budget estimates Thursday to expose the caribou file\'s quiet structural problem: two ministries both claim it, and neither will say whose signature ends an argument.',
                'The exchange ran four minutes. The critic asked which minister is accountable for the southern mountain herds\' recovery targets. The first minister said the targets are "a shared priority led by our colleagues." The second, an hour later, said the same sentence with the ministries reversed. Hansard now preserves both, eleven pages apart.',
                'The arrangement matters because the money is real — nine figures over a decade, announced in instalments that each ministry counts toward its own totals — while the results are audited by nobody. Herd counts are published by one ministry, habitat decisions signed by the other, and the penning and predator programs float between them on year-to-year agreements.',
                'A former deputy, asked how the file ended up shaped this way, offered the institutional answer: "Files that can only produce bad news are the ones nobody consolidates. Shared accountability is how a government whispers that it expects to fail."'
            ),
        ],
        [
            'title' => "Kitimat's second LNG train clears environmental review",
            'desk' => 'resources', 'dateline' => 'Kitimat', 'byline' => 'Curtis Ng',
            'lede' => 'The certificate carries forty-one conditions, and the two that matter most are about electricity.',
            'image' => $img('photo-03.svg'), 'image_caption' => 'The loading berth at Kitimat, between sailings.', 'image_credit' => 'Illustration for the Chronicle',
            'featured' => 0, 'placement' => 'featured', 'views' => 96, 'published' => $ago('-11 hours'),
            'tags' => 'lng, kitimat, energy',
            'body' => $p(
                'The environmental certificate for the second liquefaction train at Kitimat was issued Thursday, forty-one conditions attached, and the project\'s fate now turns on the two conditions that read least like environmental language: the ones about where its electricity comes from.',
                'The expansion is approved on the basis of grid power — the design that keeps its emissions inside the province\'s cap. The certificate requires an executed supply agreement before construction and gives the utility\'s next capacity call a named role in the schedule, which is the first time a north coast industrial certificate has tied itself so explicitly to a transmission queue.',
                'The catch is the queue itself. The northwest transmission line is already spoken for several times over — mines in the golden triangle, the port\'s electrification, and now this — and the utility\'s own filings put new capacity on the coast years behind the project\'s preferred schedule. Gas-fired backup would fit the timeline and break the emissions math; the certificate, read closely, forbids exactly that resolution.',
                'The company\'s statement welcomed the decision and said nothing about sequencing. The town, which has been through one construction boom and knows the shape of the next one, mostly asked practical questions at Thursday\'s open house: which camp, which road, and whether the airport gets its instrument approach before the flights double again.'
            ),
        ],
        [
            'title' => 'Ministry ends the caribou maternal pen program in the Peace',
            'desk' => 'wildlife', 'dateline' => 'Chetwynd', 'byline' => 'Hannah Stroet',
            'lede' => 'Eight years, ninety-four calves, and a herd that is still shrinking. The biologists say the pen worked; the map around it did not.',
            'image' => $img('photo-04.svg'), 'image_caption' => 'The pen on the ridge above the Sukunka, last season.', 'image_credit' => 'Illustration for the Chronicle',
            'featured' => 0, 'placement' => 'featured', 'views' => 74, 'published' => $ago('-14 hours'),
            'tags' => 'caribou, peace, wildlife management',
            'body' => $p(
                'The maternal penning program in the Peace will not run a ninth season. The ministry confirmed Wednesday that the pen above the Sukunka is being decommissioned, ending an effort that guarded ninety-four calves through their first weeks over eight springs.',
                'By its own narrow measure, the pen worked. Calf survival inside ran three times the wild rate, and the program\'s field crew — a mix of ministry staff and local guides who wintered feed up the mountain by snowcat — hit every animal-welfare benchmark the design set. The herd it served is smaller now than when the first fence post went in.',
                'The arithmetic that closed it is the program\'s own published data: penned calves entered a landscape where the things that kill adult caribou — roads, cutblock edges, the moose-and-wolf dynamics that follow both — were unchanged. "We were topping up a bathtub with the drain open," one biologist on the project said. "You can do that for eight years on dedication. You cannot do it forever on purpose."',
                'The ministry\'s statement says resources will shift to habitat measures, which is the sentence these programs always end on. The crew\'s last trip up the mountain is scheduled for October, to take the fence down before the snow — a task the foreman noted, without editorializing, takes one week of the eight-year effort to undo.'
            ),
        ],
        [
            'title' => 'Ferry electrification slips a year as Island demand rises',
            'desk' => 'communities', 'dateline' => 'Swartz Bay', 'byline' => 'Ellen Sanderson',
            'lede' => 'The first two electric vessels are late, the terminals are not ready for them, and the summer schedule they were bought for keeps growing.',
            'image' => $img('photo-05.svg'), 'image_caption' => 'The berth at Swartz Bay, where the shore power goes in.', 'image_credit' => 'Illustration for the Chronicle',
            'featured' => 0, 'views' => 66, 'published' => $ago('-1 day 2 hours'),
            'tags' => 'ferries, electrification, island',
            'body' => $p(
                'The ferry corporation\'s quarterly update, filed Friday, moves the first two battery-electric vessels a year to the right — and buried in the same tables is the reason the delay stings: traffic on the routes they were bought for grew again, for the fourth year running.',
                'The vessels themselves are the smaller problem. The shipyard is late, which shipyards are; the corporation\'s penalty clauses are doing their patient work. The larger problem is on shore, where the terminal charging infrastructure — transformers, shore gantries, and a substation upgrade that belongs to the utility rather than the ferry corporation — is tracking a full season behind the ships that need it.',
                'The result is the awkward scenario the electrification plan promised to avoid: new vessels arriving with nowhere to plug in, running on their backup generation while the civil works catch up. The corporation\'s update calls this "transitional hybrid operation." The engineers\' union newsletter calls it "a diesel ferry with extra steps."',
                'For the small-route communities watching the program, the schedule matters more than the propulsion. The electric pair frees the vessels they replace for the minor routes, whose own retirements are timed to that hand-me-down. A year\'s slip at the top of the chain arrives, eventually, at the dock of whoever is last in line.'
            ),
        ],
        [
            'title' => 'Wildfire service adds night flying in the Cariboo',
            'desk' => 'interior', 'dateline' => 'Williams Lake', 'byline' => 'Chronicle staff',
            'lede' => 'Two helicopters, night-vision certified, will work the hours when fires lie down and crews used to wait for dawn.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 58, 'published' => $ago('-1 day 6 hours'),
            'tags' => 'wildfire, cariboo, aviation',
            'body' => $p(
                'The wildfire service will fly at night in the Cariboo this season, basing two night-vision-certified helicopters at Williams Lake for the fire zone where the tactic\'s trial ran last summer.',
                'The logic is the diurnal cycle every fire crew knows: fires lie down after dark as temperatures fall and humidity recovers. Those are the best suppression hours of the day, and until now they were spent waiting for legal daylight. The trial put bucket work on fires between midnight and four a.m. and held two of them at sizes the day shift could finish.',
                'Night flying in mountain terrain is not free of cost or risk, which is why the program stayed a trial for two seasons of certification work — terrain databases, illuminated dip sites, and a crew-rest regime that the pilots\' association negotiated line by line. The service\'s aviation lead called the sign-off "the most conservative expansion we have ever done, on purpose."',
                'The Cariboo gets the program first because its fire regime suits it: big diurnal swings, long road access for ground support, and a fire centre with two seasons of the paperwork already done. If the season cooperates — a phrase nobody in Williams Lake says without touching wood — the service intends to certify a second base in the southeast next year.'
            ),
        ],
        [
            'title' => 'A warm winter left the Fraser with a third less snowpack',
            'desk' => 'climate', 'dateline' => '', 'byline' => 'Priya Naidu',
            'lede' => 'Hydrologists say the shortfall will show up first in August flows, and again in the fall return.',
            'image' => $img('photo-06.svg'), 'image_caption' => 'The survey plot at 1,900 metres, where the course ran bare by May.', 'image_credit' => 'Illustration for the Chronicle',
            'featured' => 0, 'views' => 52, 'published' => $ago('-1 day 10 hours'),
            'tags' => 'snowpack, fraser basin, hydrology',
            'body' => $p(
                'The June snow survey confirmed what the ski hills already knew: the Fraser basin came out of winter carrying about two-thirds of its normal snowpack, the fourth-lowest reading in the survey\'s history.',
                'Snowpack is the river system\'s savings account, drawn down through summer to keep flows and temperatures survivable after the rain stops. A third less snow means the drawdown starts earlier and ends sooner — hydrologists expect the deficit to surface first in August flows, exactly the weeks the sockeye are in the river.',
                'The pattern behind the number is the more durable story. Total winter precipitation was close to normal; it simply fell warm. Mid-elevation stations recorded rain in January weeks that were snow in every decade of the record, and the freezing level averaged four hundred metres higher than the survey\'s baseline period.',
                'What can be done with the information is mostly preparation: conservation orders drafted early, fish-water negotiations started in June instead of August, and the reservoir operators on the tributaries holding storage against the low-flow weeks. "We can\'t make it snow," the river forecast centre\'s lead said. "We can stop being surprised on schedule."'
            ),
        ],
        [
            'title' => 'Coastal wolves are back on islands emptied by a decade of culls',
            'desk' => 'wildlife', 'dateline' => 'Bella Bella', 'byline' => 'Tom Reddick',
            'lede' => 'Trail cameras on Aristazabal recorded four adults and two pups this spring, the first litter documented since 2014.',
            'image' => $img('photo-07.svg'), 'image_caption' => 'The beach at the north end of Aristazabal, where the cameras run.', 'image_credit' => 'Illustration for the Chronicle',
            'featured' => 0, 'views' => 61, 'published' => $ago('-2 days 3 hours'),
            'tags' => 'wolves, coast, recovery',
            'body' => $p(
                'The trail cameras on Aristazabal Island recorded what the monitoring crews have waited a decade to see: four adult wolves travelling together this spring, and by June, two pups on the beach at the island\'s north end — the first litter documented there since 2014.',
                'The island\'s wolves were emptied out through the cull years, when removal on the neighbouring watersheds pushed the survivors into gaps that trapping then closed. Coastal wolves are distinct enough — smaller, fish-eating, comfortable swimming channels that would qualify as marine crossings anywhere else — that biologists argued the losses were not replaceable from the mainland population. The cameras spent eight years suggesting they were right.',
                'Recolonization, when it finally came, arrived the way the biologists predicted and could not schedule: a dispersing pair swimming island to island down the archipelago, denning where the deer are and the people are not. Genetic work on scat collected in April places the new animals\' origin two islands north.',
                'The monitoring program — a partnership between area nations\' stewardship offices and a university lab — will keep the cameras running and the location data coarse. "The best thing we can publish about these animals," the program\'s coordinator said, "is a map with very little on it."'
            ),
        ],
        [
            'title' => 'Okanagan orchards pull out apples as harvest arrives three weeks early',
            'desk' => 'interior', 'dateline' => 'Summerland', 'byline' => 'Marla Whitfield',
            'lede' => 'Growers are replanting to cherries and cider varieties, and asking the province to redraw its replant grants.',
            'image' => $img('photo-08.svg'), 'image_caption' => 'Pulled rows above Summerland, cherries going in behind.', 'image_credit' => 'Illustration for the Chronicle',
            'featured' => 0, 'views' => 47, 'published' => $ago('-2 days 8 hours'),
            'tags' => 'orchards, okanagan, agriculture',
            'body' => $p(
                'The excavators are working the benches above Summerland this month, pulling apple blocks that have carried fruit for thirty years, and the harvest that starts three weeks early this season explains why they will not be replaced in kind.',
                'The valley\'s apple economics have been thin for a decade — packing costs against import prices — but heat is what is closing the argument. The varieties the valley built its reputation on colour poorly and soften fast in the new August; the early harvest rescues the crop\'s condition at the cost of its storage life, which is the quality the whole export model was built on.',
                'What goes in behind the apples is a map of the growers\' bets: cherries, which take the heat and ship by air; cider varieties, which wear their imperfections as character; and in the boldest rows, table grapes. The replant program\'s grant tables, growers point out, still pay by a variety list drawn up when the problem was fashion rather than temperature.',
                'The ministry says the replant review is underway and will report before winter. The growers, who plant on a thirty-year horizon and are being asked to guess the climate at the far end of it, have taken to calling the exercise by the name one Summerland orchardist gave it at the association meeting: "underwriting the weather of 2055."'
            ),
        ],
        [
            'title' => 'Coastal patrols log a record season of vessel traffic in Fitz Hugh Sound',
            'desk' => 'coast', 'dateline' => 'Bella Bella', 'byline' => 'Ellen Sanderson',
            'lede' => 'Nine hundred and forty transits between April and July, most of them small cruise operators working without a marine plan.',
            'image' => $img('photo-09.svg'), 'image_caption' => 'The evening count, southbound through the sound.', 'image_credit' => 'Illustration for the Chronicle',
            'featured' => 0, 'views' => 55, 'published' => $ago('-3 days 2 hours'),
            'tags' => 'marine traffic, central coast, fitz hugh sound',
            'body' => $p(
                'The stewardship patrols that log vessel movements through Fitz Hugh Sound counted nine hundred and forty transits between April and July — the busiest season in the record, up almost a third on last year, and most of the growth is one category: small expedition cruise vessels.',
                'The pattern is the industry\'s open secret. The big ships hold their inside-passage lanes under pilotage rules written decades ago; the growth is underneath them, in hundred-passenger vessels that anchor in the small bays, land guests on the estuaries, and operate — legally — in a zone that has monitoring but no marine plan to enforce.',
                'The patrol logs are quietly becoming the region\'s de facto traffic authority. Operators now radio the stewardship offices with itineraries the way they would a harbourmaster, and the offices\' seasonal guidance — which estuaries are closed for feeding season, which anchorages are asked to stay empty — is followed, per the logs, most of the time. "Most of the time," the coordinator noted, "is a courtesy, not a rule. Courtesies have capacity limits."',
                'The federal marine planning process that would turn guidance into rules has been "concluding" for three years. The patrols\' season report, released this week, attaches the traffic curve to a one-sentence recommendation: finish the plan before the curve does.'
            ),
        ],
        [
            'title' => 'Herring roe fishery stays closed for a fourth year',
            'desk' => 'coast', 'dateline' => '', 'byline' => 'Chronicle staff',
            'lede' => 'Spawn measurements improved on the north coast; Strait of Georgia stocks did not.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 40, 'published' => $ago('-3 days 7 hours'),
            'tags' => 'herring, fisheries, closures',
            'body' => $p(
                'The roe herring fishery will stay closed for a fourth consecutive season, the department confirmed this week, extending a pause that began as an emergency and is settling into policy.',
                'The survey numbers explain the split decision the industry had hoped for and did not get. Spawn indices on the north coast improved for the second straight year — enough that a limited opening was modelled — while the Strait of Georgia stocks, the fishery\'s historical centre, stayed flat at levels the department\'s own framework classes as critical.',
                'The closure\'s defenders point at everything that eats herring: the salmon the coast is spending fortunes to recover, the whales the whale-watching economy runs on, the seabird colonies whose failures track the herring cycles almost exactly. A forage fish left in the water, the argument runs, is not unharvested; it is working.',
                'The seine fleet\'s counterargument is generational: licences held forty years, crews that have re-rigged for other fisheries three times, and a processing capacity that will not survive a decade of idleness to greet any recovery. Both arguments are correct, which is why the file produces closures one year at a time rather than a decision.'
            ),
        ],
        [
            'title' => 'The Great Bear agreement was never meant to be a museum',
            'desk' => 'opinion', 'dateline' => '', 'byline' => 'Ruth Casorso',
            'lede' => 'Twenty years on, the parties still argue about who counts as a signatory. The forest does not have another twenty in it.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 33, 'published' => $ago('-3 days 11 hours'),
            'tags' => 'opinion, great bear rainforest, agreements',
            'body' => $p(
                'The agreement that named the Great Bear Rainforest turns twenty this year, and the celebrations have the unmistakable air of a museum opening: retrospectives, anniversary panels, a commemorative map. The map is beautiful. It is also, in the places this newspaper covers, increasingly a picture of how things stood in 2006.',
                'The agreement worked because it froze a war nobody was winning. Logging rates fell, protection percentages rose, and a generation of professionals on all sides built careers administering the peace. Administration, though, has quietly replaced ambition. The joint bodies the deal created meet less each year; the reviews it scheduled slip; and the question this week\'s Nass deferral put plainly — who actually signs, when parties disagree — has no better answer now than it did then.',
                'Meanwhile the forest itself has declined to stay frozen. Fire has arrived in valleys the 2006 maps classed as too wet to burn. The cedar decline creeps north a drainage at a time. The agreement\'s architecture assumes the landscape of its signing; its anniversary falls in a different one.',
                'The deal deserves its birthday. The honest gift would be a renegotiation — parties at a table, the new maps in front of them, arguing again about something that matters. Arguments were what built the agreement. Reverence is what will bury it.'
            ),
        ],
        [
            'title' => 'Two ministries, one caribou herd, no plan',
            'desk' => 'opinion', 'dateline' => '', 'byline' => 'Dan Pelletier',
            'lede' => 'Recovery money is spent annually and reviewed never.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 28, 'published' => $ago('-4 days 4 hours'),
            'tags' => 'opinion, caribou, accountability',
            'body' => $p(
                'This week the legislature learned, over four polite minutes, that no minister of the Crown is accountable for caribou recovery. Two of them share the file. Sharing, in government, is the verb that means neither.',
                'The pattern is visible from any distance: money announced annually, spent through programs that each ministry counts toward its own report, on a herd whose numbers are published by whichever office has the less awkward quarter. The pen in the Peace closed this week after eight years of dedicated, competent, measurable work — and its closure memo could not name the decision-maker who might have changed the landscape around it, because there is not one.',
                'None of this is the fault of the biologists, the crews, or even the ministers, who inherited an org chart drawn to diffuse exactly the accountability the file needs. It is the fault of the drawing. A herd is a single thing. It declines as a single thing. It can only be recovered by decisions that trade one ministry\'s interests against the other\'s — cutblocks against counts, roads against ranges — and trades need a single signature.',
                'The fix costs nothing and is therefore hard: name one minister, publish one target, audit one result. Any government that finds this unreasonable is telling you, in advance, how the story ends.'
            ),
        ],
        [
            'title' => "The coast's oldest cinema changes hands, and keeps the projectionist",
            'desk' => 'culture', 'dateline' => 'Prince Rupert', 'byline' => 'Iris Tam',
            'lede' => 'Ninety-one years, one screen, and a booth that still runs reel-to-reel on Wednesdays. The new owners bought it for exactly that.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 36, 'published' => $ago('-4 days 9 hours'),
            'tags' => 'cinema, prince rupert, culture',
            'body' => $p(
                'The sale closed Friday and the marquee changed Saturday, in the only way the new owners intend to change it: the letters now read UNDER NEW MANAGEMENT · SAME MOVIES, SAME POPCORN, SAME WALTER.',
                'Walter is the projectionist, sixty-eight, employed in the booth since 1979, and the explicit subject of clause eleven of the purchase agreement — the clause the sellers insisted on and the buyers, a couple who moved north from Vancouver "for the weather, unironically," say they would have written themselves. He runs digital six nights a week and, on Wednesdays, the reel-to-reel his predecessors trained him on, for a repertory night that sells out on rain weeks.',
                'Single-screen cinemas survive on coasts like this one for reasons economics has trouble seeing: the ferry schedule strands travellers overnight, the winter is long and horizontal, and a town of twelve thousand will reliably fill a hundred and forty seats for anything with the good sense to start at seven.',
                'The couple\'s renovation plans run to a new water heater and reupholstering row F, "which knows what it did." Everything else stays, including the intermission — the coast\'s last, Walter believes — during which the audience stands, stretches, and discusses the first half like a town council that happens to like each other.'
            ),
        ],
        [
            'title' => 'Prince Rupert votes on a stormwater levy',
            'desk' => 'communities', 'dateline' => 'Prince Rupert', 'byline' => 'Chronicle staff',
            'lede' => 'The wettest city in the country has been draining itself on a budget line written for somewhere drier.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 24, 'published' => $ago('-5 days 3 hours'),
            'tags' => 'prince rupert, stormwater, council',
            'body' => $p(
                'Council votes Monday on a dedicated stormwater levy, and the staff report makes the case with one comparison: the city drains two and a half metres of annual rainfall through infrastructure funded like a town that gets eighty centimetres.',
                'The levy would average eleven dollars a month per household, charged by roof and pavement area rather than assessed value — the model that lets the co-op with the gravel lot pay less than the mall with the flat acre of asphalt.',
                'The culvert failures of the past two winters did the political work the engineering reports could not. Three road washouts, one of them under the hospital route, moved the conversation from whether to how much.',
                'If it passes, the first projects are already ranked: the Hays Creek culverts, the McBride Street outfall, and a maintenance crew that exists year-round instead of after storms.'
            ),
        ],
        [
            'title' => "Nelson's co-op grocery reopens after the flood",
            'desk' => 'communities', 'dateline' => 'Nelson', 'byline' => 'Chronicle staff',
            'lede' => 'Fourteen weeks, a gutted main floor, and a membership that showed up in gumboots.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 21, 'published' => $ago('-5 days 8 hours'),
            'tags' => 'nelson, co-op, flood recovery',
            'body' => $p(
                'The co-op grocery reopened Thursday morning, fourteen weeks after the spring flood put a metre of creek water through its main floor, and the first customer through the door was, by unanimous staff decision, the member who organized the mud-out.',
                'The flood gutted the flooring, the lower shelving, and the walk-in coolers. The rebuild was insured; the survival was not. What carried the store through a quarter with no revenue was the membership structure itself — three hundred households pre-paying their next year\'s member fee in the week after the water dropped.',
                'The reopened floor plan quietly banks the lesson: coolers on plinths, electrical raised to counter height, and the bulk bins — the flood\'s messiest casualty — relocated upstairs.',
                'The general manager\'s reopening speech ran one sentence: "We know what the store is for now."'
            ),
        ],
        [
            'title' => 'Bella Coola gets its first resident veterinarian',
            'desk' => 'communities', 'dateline' => 'Bella Coola', 'byline' => 'Chronicle staff',
            'lede' => 'Until this month, the nearest vet was a ferry ride or a four-hundred-kilometre drive over the Hill.',
            'image' => '', 'image_caption' => '', 'image_credit' => '',
            'featured' => 0, 'views' => 19, 'published' => $ago('-6 days 2 hours'),
            'tags' => 'bella coola, veterinary, valley',
            'body' => $p(
                'The valley has a veterinarian. Dr. Marisol Vega opened a two-room practice behind the farm supply Tuesday, ending an era in which every animal emergency in Bella Coola began with the same calculation: the ferry schedule, or the Hill.',
                'The recruitment took the valley four years and a structure borrowed from rural medicine: a guaranteed-income agreement funded jointly by the two local governments, the cattlemen\'s association, and a community fund seeded — the organizers are happy to specify — by eleven years of the fall fair\'s pie table.',
                'The caseload waiting for her ran the valley\'s full range: herd work for the cattle operations, a backlog of spays the humane society has been ferrying out two at a time, and one goose, of local renown, with a limp.',
                'Dr. Vega, asked why she took a posting three hundred practices turned down, gave the answer the valley has already put on a t-shirt: "Everywhere needs a vet. Here, they built the clinic first."'
            ),
        ],
    ],
];
