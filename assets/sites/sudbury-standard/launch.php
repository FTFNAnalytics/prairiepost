<?php
/**
 * The Sudbury Standard — launch package.
 * Loaded once by `PP_SITE=sudbury-standard php tools/seed-launch.php`.
 *
 * An opinion desk, so every piece takes a side and ends on the ask. The
 * package's voice rules are the house style: a headline is a claim and not
 * a topic, a standfirst is one sentence saying what the piece argues, and
 * the last line says what the reader or the council should do.
 *
 * Every desk the stories use is listed in 'desks' below, so the pack stands
 * on its own — the seeder creates only what the shared database is missing.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$q = fn (string $quote) => '<blockquote><p>' . $quote . '</p></blockquote>';
$img = fn (string $file) => '/assets/sites/sudbury-standard/img/' . $file;

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['sudburystandard.ca', 'www.sudburystandard.ca'],

    'desks' => [
        ['name' => 'Council', 'slug' => 'council', 'color' => '#0F3B8C', 'description' => 'City hall, the budget, and the votes the minutes do not explain.'],
        ['name' => 'Mining',  'slug' => 'mining',  'color' => '#0F3B8C', 'description' => 'The industry that built the basin, the companies in it, and what they owe the ground they stand on.'],
        ['name' => 'Housing', 'slug' => 'housing', 'color' => '#0F3B8C', 'description' => 'Who gets to live here, at what rent, and what the city has actually built.'],
        ['name' => 'Letters', 'slug' => 'letters', 'color' => '#33425C', 'description' => 'Readers argue back. Signed, and printed as sent.'],
    ],

    'settings' => [
        // The byline every Hermes filing carries here. Without it the
        // server falls back to the generic 'Automated report'.
        'automated_byline'   => 'Standard Newsroom',
        'site_title'         => 'The Sudbury Standard',
        'tagline'            => 'Opinion and argument from the Nickel City',
        'meta_description'   => 'An opinion desk for Sudbury: city hall, mining, housing and the arguments that follow. Independent and reader-funded.',
        'footer_line'        => 'Independent, reader-funded',
        'contact_email'      => 'tips@sudburystandard.ca',
        'newsletter_heading' => 'The Weekly Standard',
        'newsletter_copy'    => 'One argument, every Thursday morning.',
        'funding_note'       => 'The Standard is funded by readers. No advertising from anyone the paper covers.',
        'about_heading'      => 'About The Sudbury Standard',
        'about_standfirst'   => 'An opinion desk that names names. We take a side, show the document, and say what should happen next.',
        'about_body'         => '<p>The Standard is a Sudbury opinion desk. Every piece here takes a side and says what should happen next. Balance is not a house value; accuracy is.</p>'
            . '<p>We are digital-native. The institutional look is a choice — it buys the credibility that the writing then spends — not a reproduction of a broadsheet.</p>'
            . '<h2>What we publish</h2>'
            . '<p>Argument grounded in the record: the councillor, the vote count and the date. Where there is a document, we link it, quote it and publish it. Where we get something wrong, we correct it fast and in public, at the top of the piece.</p>'
            . '<h2>How we are paid</h2>'
            . '<p>By readers. The Standard takes no advertising from anyone it covers, which means no advertising from the city, the mining companies, or the developers whose applications appear in these pages.</p>'
            . '<h2>Write to us</h2>'
            . '<p>Letters are welcome and are printed as sent, over a name. Send them, and anything else, to the tips address in the bar above.</p>',
        'breaking_label'     => '',
        'breaking_url'       => '',
        'regions'            => json_encode([
            'sudbury'  => 'Greater Sudbury',
            'northern' => 'Northern Ontario',
            'ontario'  => 'Ontario',
        ]),
    ],

    'sources' => [
        ['CBC Sudbury',      'https://www.cbc.ca/webfeed/rss/rss-canada-sudbury',   'sudbury'],
        ['CBC Thunder Bay',  'https://www.cbc.ca/webfeed/rss/rss-canada-thunderbay', 'northern'],
        // CBC Toronto belongs to `gta`, claimed by another pack — listing
        // it again here never added anything. TVO retired its feed.
        ['SooToday',                  'https://www.sootoday.com/rss',                'northern'],
        ['Northern Ontario Business', 'https://www.northernontariobusiness.com/rss', 'northern'],
        ['The Trillium',              'https://www.thetrillium.ca/rss',              'ontario'],
    ],

    'stories' => [

        /* ------------------------------------------------- the argument --- */
        [
            'title' => 'Council spent four years arguing about a parking lot',
            'desk' => 'council', 'dateline' => '', 'byline' => 'the Editorial Board',
            'lede' => 'The arena file was never about hockey. It was about who decides where this city puts its money, and that question is still open.',
            'image' => $img('photo-01.svg'),
            'image_caption' => 'The fenced excavation downtown, four years on.',
            'image_credit' => 'Standard photo',
            'featured' => 1, 'placement' => 'hero', 'views' => 412, 'published' => $ago('-4 hours'),
            'tags' => 'arena, city hall, downtown',
            'body' => $p(
                'Four councils, three consultants and one site plan later, the city has a hole where a downtown arena was supposed to be. The money is spent either way. What is still unspent is the credibility of the people who signed off on it.',
                'There is a version of this story in which nobody did anything wrong. Cities are slow. Large projects attract appeals, appeals attract delay, and a council that inherits a half-finished file is not obliged to like it. Every one of those things is true. None of them explains why a resident who wants to know how the site was chosen has to go looking for a document the city has never published.',
                'The vote on 14 August is not about the arena. It is about whether the next council inherits a process it can actually see into, or another four years of in-camera sessions and reports that arrive the night before the decision.'
            )
            . $q('Ask who was in the room. Then ask why the minutes do not say so.')
            . $p(
                'The record is not complicated. In 2022 the site was chosen on a staff recommendation whose supporting analysis was never released. In 2023 the cost estimate moved by $41 million between a June report and a November one, and no councillor asked in open session what had changed. In 2024 the file went quiet for seven months.',
                'Each of those steps was permitted. That is the part worth sitting with. Nothing in the sequence required a rule to be broken; it required only that the people involved use discretion they already had — to move an item behind closed doors, to release an analysis later rather than sooner, to answer a narrow question narrowly and let the wider one go unasked. Discretion used that way is not corruption. It is a habit. Habits are harder to vote out than people are.',
                'The defence offered is always the same, and it is not a stupid one: commercial confidence. Land negotiations do collapse when the other side can read the file. Legal advice does stop being useful the moment it is public. Those are real categories and they deserve respect. They also cover a fraction of what has actually been withheld here. A site-selection analysis, once the site has been selected and the ground opened beside it, protects nothing but the reputation of the person who wrote it.',
                'And the delay has cost more than money. Four years bought something the budget does not show: a cohort of residents who have concluded, not unreasonably, that the decision was made somewhere they could not see, and that turning up to a Tuesday meeting would not have changed it. That belief is corrosive in a way a cost overrun is not, and it will outlast whatever eventually gets built on the site.',
                'Sudbury is not short of plans. It is short of anyone willing to say which one they voted for and why.',
                'So: on 14 August, move the in-camera items to open session, publish the 2022 site analysis, and require that any report supporting a capital decision over $10 million be public for ten days before the vote. Three motions, one meeting.',
                'None of the three costs the city a dollar. None needs provincial permission. None of them touches a live legal question, which is the objection that will be raised anyway. A councillor who supports all three in principle and can move none of them this month is telling you something about how this place works. Ask your councillor which of the three they will move — and ask before the vote, not after it.'
            ),
        ],

        /* ------------------------------------------------------ council --- */
        [
            'title' => 'The Kingsway file is not finished',
            'desk' => 'council', 'dateline' => '', 'byline' => 'the Editorial Board',
            'lede' => 'The lawsuits ended. The questions about how the land was assembled did not, and council has never answered them.',
            'image' => $img('photo-08.svg'),
            'image_caption' => 'The chamber, before a Tuesday meeting.',
            'image_credit' => 'Standard photo',
            'views' => 268, 'published' => $ago('-1 day 3 hours'),
            'tags' => 'kingsway, city hall, land',
            'body' => $p(
                'The appeals are exhausted and the file is closed, which council has taken to mean the matter is settled. Those are different things, and the distance between them is where public trust goes to die.',
                'What remains unanswered is narrow and answerable: who assembled the land, on what timeline, and what the city knew about the assembly when it selected the site. Every one of those answers exists in a document the city already holds. None of them requires a new study, a consultant, or a single hour of legal time.',
                'The standard objection is that relitigating a closed file wastes money. It would, if anyone were asking council to relitigate it. Nobody is. The request is for a chronology — dates, parcels, owners — of a transaction that has already been through every tribunal available to it. A chronology cannot be appealed. It can only be embarrassing, which is a different concern and not one the public is obliged to protect.',
                'It is worth being precise about what is and is not alleged here. Nothing in the public record suggests anyone profited improperly, and this paper has not seen a document that would support such a claim. What the record shows is a city that assembled a significant piece of land, selected it for a significant project, and has never set out the order in which those two things happened. In the absence of a chronology, residents supply their own, and the ones they supply are considerably less flattering than the truth almost certainly is.',
                'It matters because the next site selection is coming. It always is. A city that has never explained how it chose the last one has no way to reassure anyone about the next, and will find itself paying for that in delay and litigation whether it publishes the timeline or not.',
                'A closed file is not a cleared record. Council can release the assembly timeline this month without touching a single live legal question. It should, and a councillor should move it at the next open session.'
            ),
        ],
        [
            'title' => 'Council votes itself a raise and calls it a benchmark',
            'desk' => 'council', 'dateline' => '', 'byline' => 'the Editorial Board',
            'lede' => 'A remuneration review that compares Sudbury to cities it does not resemble is not a benchmark; it is a search for a number.',
            'views' => 231, 'published' => $ago('-2 days 2 hours'),
            'tags' => 'council pay, budget',
            'body' => $p(
                'The review sets councillor pay against a comparator group of nine municipalities. Four of them have more than twice Sudbury\'s assessment base. One has a third of its geography. The group was not published until after the vote.',
                'A benchmark is only a benchmark if the comparison set is chosen before anybody knows what it will produce. Choose it afterwards and you have not measured anything; you have gone looking for a number you already wanted and found nine reasons to keep it. Any consultant will tell you this, and the ones who do the work know exactly which municipalities move the average and in which direction.',
                'There is a defensible case for paying councillors more. Sudbury is 3,200 square kilometres of ward work, most of it done at night, by people with day jobs, on files that take years to close. A council only affordable to the retired and the independently comfortable is a narrower council, and a narrower council makes worse decisions about housing and transit than one that contains someone who has recently had to take a bus.',
                'Make that case out loud. It survives contact with a public meeting. Residents are not as reflexively hostile to municipal pay as councillors seem to believe — they are hostile to being handed a conclusion and told it is arithmetic.',
                'The process compounds the problem. A remuneration review is commissioned by the body it pays, reviewed by the body it pays, and adopted by the body it pays, and the only defence against that arrangement is a comparator set chosen in the open. Remove that and there is nothing left but the assurance of the people receiving the money that the money is appropriate. Councillors would not accept such an assurance from a contractor. They should not expect residents to accept it from them.',
                'What is not defensible is arriving at the number first. Publish the comparator group before the next review, not after, and let residents argue with the arithmetic while it can still change.'
            ),
        ],
        [
            'title' => 'Transit cuts land hardest in Val Caron, and the map shows it',
            'desk' => 'council', 'dateline' => '', 'byline' => 'the Editorial Board',
            'lede' => 'The service review protects ridership per hour, which is precisely the measure that guarantees the outlying communities lose.',
            'image' => $img('photo-05.svg'),
            'image_caption' => 'The last inbound run of the evening.',
            'image_credit' => 'Standard photo',
            'views' => 194, 'published' => $ago('-3 days 5 hours'),
            'tags' => 'transit, val caron, service cuts',
            'body' => $p(
                'The review\'s governing metric is boardings per service hour. On that measure a route through Val Caron will always lose to a route through the core, because the core has more people per kilometre. That is not a finding. It is the definition of the metric.',
                'Efficiency measures are not neutral instruments. Every one of them encodes a choice about what the service is for, and this one encodes a clear answer: transit exists to move the largest number of people for the lowest cost per rider. Adopt that and the outlying runs are indefensible, permanently, no matter what the ridership does. There is no schedule change and no marketing campaign that can rescue a route from a metric designed around density it does not have.',
                'The alternative is not to abandon efficiency. It is to say plainly that a transit system also exists so that a person without a car in Val Caron can hold a job in the city, get to a medical appointment, and not be housebound at nineteen or at eighty. That purpose has a cost per rider, and it is a high one. It is still a purpose, and it is one an amalgamated city took on deliberately.',
                'Amalgamation was sold to the outlying communities partly on the promise that services would be planned for the whole city. A metric that structurally disadvantages low-density wards quietly retires that promise without a vote on it — which is the tidiest way to break a commitment, because nobody has to be recorded breaking it.',
                'A floor is the standard remedy and it is not exotic. Set a minimum level of service per community — a first run, a last run, and a frequency the schedule may not fall below — and let the efficiency measure allocate everything above the floor. Transit systems in comparable regions do this and publish the floor, which has the additional benefit of making a future cut a visible act rather than a schedule revision.',
                'If council wants to cut service, it should cut it in the open and say which communities it is choosing. Direct staff to report the cuts by ward, with a minimum service floor for each, before the schedule change takes effect in September.'
            ),
        ],

        /* ------------------------------------------------------- mining --- */
        [
            'title' => 'The Superstack question nobody wants to answer',
            'desk' => 'mining', 'dateline' => '', 'byline' => 'the Editorial Board',
            'lede' => 'Decommissioning is a company decision with a public afterlife, and no level of government has said who is responsible for the ground beneath it.',
            'image' => $img('photo-02.svg'),
            'image_caption' => 'The stack, from the Copper Cliff road.',
            'image_credit' => 'Standard photo',
            'views' => 356, 'published' => $ago('-6 hours'),
            'tags' => 'superstack, vale, environment',
            'body' => $p(
                'The stack is coming down in stages, and the company has been clear about the engineering. Nobody has been clear about the site.',
                'Most of the public conversation has been about what the skyline will look like, which is understandable and beside the point. The stack is a landmark to people who never worked a shift under it and a working structure to people who did, and both groups will be arguing about the view for a decade. Neither argument determines who is responsible for the ground.',
                'The relevant question is not sentimental. It is jurisdictional: after decommissioning, who holds the long-term monitoring obligation for the ground around the base, and for how long? The province points to the closure plan. The closure plan points to the company. The company points to the closure plan.'
            )
            . $q('A structure that shaped a century of this city\'s air should not leave a hole in the paperwork.')
            . $p(
                'Closure plans are written to be adequate at the moment they are filed. They are financial instruments as much as environmental ones, and their assumptions — about ownership, about corporate continuity, about what a monitoring programme costs in year forty — are assumptions about a future nobody in the room will be present for. That is not an argument against them. It is an argument for reading them out loud while the people who filed them are still available to answer questions.',
                'Sudbury has been here before. The regreening programme exists because the public eventually paid for damage that was privately caused and publicly ignored. That was a fifty-year bill, and the city is still paying instalments on it in the form of soil work that never appears in anyone\'s cost of production.',
                'The lesson from that period is not that the companies were uniquely bad actors. It is that when the obligation is vague, the obligation lands on whoever is still standing there in thirty years, and in this basin that has always been the municipality.',
                'The city cannot rewrite a provincial closure plan. It can demand, in writing and at an open meeting, that the province state the monitoring obligation and its duration before the last stage of demolition. Council should pass that resolution this fall, while the question is still theoretical and therefore still cheap to answer.'
            ),
        ],
        [
            'title' => 'Regreening worked, which is exactly why it should not be the last word',
            'desk' => 'mining', 'dateline' => '', 'byline' => 'the Editorial Board',
            'lede' => 'The city planted nine million trees and earned the right to say so; it has not earned the right to stop measuring what is under them.',
            'image' => $img('photo-06.svg'),
            'image_caption' => 'Young pine on the ridges above the city.',
            'image_credit' => 'Standard photo',
            'views' => 178, 'published' => $ago('-2 days 9 hours'),
            'tags' => 'regreening, environment',
            'body' => $p(
                'Sudbury\'s regreening is a genuine municipal achievement and one of the very few environmental stories in this country that improved. It gets cited at conferences, in textbooks, and by people who have never been here. It should be.',
                'It is also thirty-two years of tree planting on ground whose soil chemistry is still being studied, and the monitoring budget has not moved in a decade while the planting budget has. A canopy is not a remediation. It is the most visible part of one, which is not the same thing and is easier to photograph.',
                'This is the specific hazard of a success story: it becomes the answer to questions it was never designed to answer. Ask about metals in the soil and you will be shown the tree count. Ask about the lakes and you will be shown the tree count. The count is real and the trees are real, and neither tells you what is happening two feet down.',
                'Nobody planting on those ridges believes otherwise. The people who run the programme have been the most candid voices in the city about what it does and does not do. The gap is not in the science. It is in the budget line, and in a council that finds it easier to fund something it can hold a photograph of.',
                'There is a version of this argument that says raising the question at all is disloyal — that a city with this much environmental history has earned the right to enjoy the good news without an asterisk. It has earned that. What it has not earned is the right to stop looking, and the paper is not aware of anyone involved in the work who would claim otherwise.',
                'Fund the soil monitoring at the same rate as the planting, and publish the results annually alongside the tree count, in the same document. If the numbers are as good as the trees look, the city loses nothing by showing them — and if they are not, the city needs to know before it spends another thirty years planting on the assumption that they are.'
            ),
        ],
        [
            'title' => 'A tailings expansion should not clear the table in one meeting',
            'desk' => 'mining', 'dateline' => '', 'byline' => 'the Editorial Board',
            'lede' => 'The application is technically routine and geographically permanent, and those two facts are why it deserves more than a single evening.',
            'image' => $img('photo-04.svg'),
            'image_caption' => 'The headframe above the shaft.',
            'image_credit' => 'Standard photo',
            'views' => 142, 'published' => $ago('-4 days 4 hours'),
            'tags' => 'tailings, permits, water',
            'body' => $p(
                'Nothing in the application is unusual. That is not reassurance; it is a description of how permanent things get approved.',
                'The expansion adds capacity next to a watershed the city draws from. The technical review is provincial and by all appearances competent. But the public comment window is the only point at which a resident enters this process at all, and it is thirty days over a construction summer, on a document written for engineers.',
                'Thirty days is enough time to file an objection. It is not enough time to understand the thing you are objecting to, find someone qualified to read it with you, and put a question in language the ministry will treat as substantive. The window is not short by accident; it is short because it was designed around the applicant\'s schedule, and the applicant\'s schedule is the one part of this process that has an owner.',
                'The counter-argument is that public comment rarely changes a technical decision, and that is largely true. It is also an argument that proves too much. If the comment period cannot change the outcome, it is theatre, and the province should say so plainly rather than running a consultation it does not intend to be consulted by.',
                'The company will point out, correctly, that it has met every requirement and that the timeline is the province\'s. Both true. The requirement being met is the point at issue: a process can be followed exactly and still be built so that the only people with time to participate are the ones being paid to.',
                'Tailings are permanent in the way few municipal decisions are. Councils reverse zoning, cancel projects and rewrite plans; nobody moves a tailings area. A decision with that half-life can survive an extra month of scrutiny.',
                'Ask the province for sixty days and a public technical session in Sudbury, not in Toronto. Council can request both this month; the company has no reason to object to either, and its willingness to say so publicly is itself worth knowing.'
            ),
        ],

        /* ------------------------------------------------------ housing --- */
        [
            'title' => 'The city counts units built and calls it a housing policy',
            'desk' => 'housing', 'dateline' => '', 'byline' => 'the Editorial Board',
            'lede' => 'A target that counts doors without counting rents will be met, and it will not house the people the shortage is actually about.',
            'image' => $img('photo-07.svg'),
            'image_caption' => 'A street in the Donovan.',
            'image_credit' => 'Standard photo',
            'views' => 287, 'published' => $ago('-1 day 8 hours'),
            'tags' => 'housing, rent, targets',
            'body' => $p(
                'The housing target is a number of units. It is not a number of affordable units, and the reporting does not separate the two, so the city can hit its target while the shortage it was written to address gets worse.',
                'This is not a Sudbury invention — the provincial framework counts the same way, and every municipality in Ontario is filing the same shape of number. But Sudbury chose to adopt the measure without adding one of its own, and nothing prevented it from adding one. A city is allowed to publish more than it is asked for.',
                'The theory behind counting doors is filtering: build enough of anything and the pressure comes off everything, as households move up and vacate what they leave behind. It is not a foolish theory. In a market with a functioning middle of the ladder it broadly works. It works slowly, it works unevenly, and it works least well for the households furthest down — which in this city means the people the housing conversation is nominally about.'
            )
            . $q('A door is not housing policy. A rent is.')
            . $p(
                'There is also a political cost to the current measure that council should care about even if it disputes everything above. A target reported this way is unfalsifiable. It will be met. It will be announced as being met. And the people on the waiting list, who can read a vacancy listing as well as anyone, will conclude that the city is either not paying attention or counting on them not to. Neither reading helps the next time the city needs public support for a housing decision.',
                'Report units by rent band, quarterly, against the incomes actually earned in this city — not against a provincial average median that describes somewhere else. It costs a staff report and a spreadsheet column. Council should direct it at the next planning committee, and residents should notice which councillors argue that the column is unnecessary.'
            ),
        ],
        [
            'title' => 'Rooming houses are the housing stock nobody will defend',
            'desk' => 'housing', 'dateline' => '', 'byline' => 'the Editorial Board',
            'lede' => 'Every rooming house lost is replaced by nothing, and the city has no policy that acknowledges this.',
            'views' => 163, 'published' => $ago('-5 days 3 hours'),
            'tags' => 'housing, rooming houses',
            'body' => $p(
                'The units are small, often poorly maintained, and easy to campaign against. They are also the cheapest legal accommodation in the city, and when one closes its tenants do not move into a new build. They move into a worse room, a couch, or nothing.',
                'Nobody runs for council on rooming houses. There is no constituency for them: the tenants are transient and rarely vote, the neighbours are organised and always do, and the buildings are frequently owned by people it is satisfying to be angry at. Some of that anger is earned. None of it produces a unit.',
                'Enforcement is necessary; nobody should live somewhere unsafe, and the fire code exists for reasons this city has had cause to remember. But enforcement without replacement is a housing loss dressed as a standards win, and the city reports it as the latter — a closure appears in the record as a problem solved, not as three people who now need somewhere to sleep.',
                'The honest version of the argument is that these units are the bottom of the stock and the bottom should be raised, even at a cost. That may be right. It is a policy with a price, and the price is currently paid entirely by people with no way to appear in the minutes.',
                'There is a constructive version of the same enforcement power, and other cities have found it: licensing that brings the units up to standard while they stay occupied, with the work ordered on a schedule and the tenancy protected during it. It is slower than closure and considerably less satisfying, and it ends with a habitable room and a person in it.',
                'Report the units lost to enforcement each year beside the units gained, in the same table, on the same page. If the city is comfortable with the trade, it should be comfortable publishing it.'
            ),
        ],
        [
            'title' => 'Downtown will not be fixed by another study of downtown',
            'desk' => 'housing', 'dateline' => '', 'byline' => 'the Editorial Board',
            'lede' => 'Sudbury has commissioned six downtown plans since 2005 and built almost none of what they recommended, which is a decision the city keeps making without admitting it.',
            'image' => $img('photo-09.svg'),
            'image_caption' => 'The yard on the edge of the basin.',
            'image_credit' => 'Standard photo',
            'views' => 149, 'published' => $ago('-6 days 5 hours'),
            'tags' => 'downtown, planning',
            'body' => $p(
                'Six plans. Two of them are good. None of them is being executed, and each new one is commissioned partly because the last one was not.',
                'A study is cheaper than a build and much easier to announce. It produces a document with renderings in it that can be pointed at for four years, it commits no council to anything, and it can be delivered inside a single term. A build cannot. That asymmetry, not a shortage of ideas, is why the cycle runs.',
                'The plans themselves are not the problem. Read the 2012 one: the recommendations about ground-floor uses and the transit interchange were sound then and would be sound now. It was not refuted. It was simply never funded, and then it aged, and then its age became the argument for commissioning another.',
                'Every new study also resets the clock on accountability. Nobody has to explain why the last set of recommendations went unbuilt if the current conversation is about a fresh set. That is a convenience, and conveniences that recur six times stop being accidents.',
                'None of this is an argument that downtown is beyond help, and it is certainly not an argument that the people who work on it are wasting their time. The businesses that have opened in the last few years did not do so because a plan told them to, and they are the strongest evidence available that the demand is real. What they lack is a city willing to do the unglamorous half — the streetscape, the lighting, the parking decision nobody wants to make in an election year.',
                'Before commissioning a seventh, publish an audit of the six: what each recommended, what was built, what it cost, and which recommendations were rejected on the record rather than allowed to lapse. Then decide whether the city needs a new plan or the nerve to execute an old one. Ask for that audit at budget.'
            ),
        ],

        /* ------------------------------------------------------ letters --- */
        [
            'title' => 'A letter to the incoming council',
            'desk' => 'letters', 'dateline' => '', 'byline' => 'M. Ouellette, New Sudbury',
            'lede' => 'You will be asked to approve things quickly and told the timing is out of your hands; it is worth knowing now that it usually is not.',
            'views' => 208, 'published' => $ago('-2 days 6 hours'),
            'tags' => 'letters, city hall',
            'body' => $p(
                'I sat on a community advisory panel for six years, so I have watched a number of you learn this the slow way.',
                'You will be handed a report on the Friday for a Tuesday vote and told that deferral costs money. Sometimes that is true. Most times the deadline is one the administration set and can move. Ask which it is, and ask in open session so the answer is on the record. You will only need to do this three or four times before the reports start arriving earlier.',
                'The second thing: the minutes will not record what you were told in camera, only that you were told something. If a decision turns on information you cannot repeat, say so out loud before you vote. You are permitted to say that much, and it is the only signal a resident reading the minutes will ever get.',
                'The third thing, which nobody will tell you: the staff are not your opponents. They are working with the direction they were given, usually by a council that sat before you, and most of them would like clearer direction than they have. A question asked in public is not an accusation, and if you frame it as one you will get careful answers for four years.',
                'To the residents reading: write to your councillor before the 14th, not after. Afterwards is a complaint. Before is a vote.'
            ),
        ],
        [
            'title' => 'You were too easy on the transit review',
            'desk' => 'letters', 'dateline' => '', 'byline' => 'R. Beaudry, Val Caron',
            'lede' => 'The Standard treated a service cut as a design problem when it is a decision about who counts, and readers out here noticed the difference.',
            'views' => 121, 'published' => $ago('-4 days 8 hours'),
            'tags' => 'letters, transit',
            'body' => $p(
                'Your piece on the transit review made the right argument about the metric and then stopped short of the obvious conclusion.',
                'If a measure guarantees a result, the people who chose the measure chose the result. That is not a design flaw to be corrected in the next review. It is a decision, and it belongs to named councillors who voted for the terms of reference — in public, on a recorded vote, which I have gone and read.',
                'I understand why you wrote it the way you did. It is easier to argue with a methodology than with a person, and the methodology does not write to complain. But the effect is to describe a choice as though it were weather.',
                'Out here the 4:40 is how my daughter gets home from her shift. When it goes, the conversation about boardings per service hour will be over and she will still need to get home. Somebody chose that. Name them next time. You are usually willing to.'
            ),
        ],
        [
            'title' => 'The paper should say what it costs to run',
            'desk' => 'letters', 'dateline' => '', 'byline' => 'J. Kalliokoski, Copper Cliff',
            'lede' => 'A paper that demands the city publish its numbers should publish its own, and the Standard has not.',
            'views' => 98, 'published' => $ago('-7 days 4 hours'),
            'tags' => 'letters, funding',
            'body' => $p(
                'You end most pieces by asking someone to release a figure. Fair enough. Then release yours.',
                'How many subscribers do you have, what does an issue cost to produce, and who are the largest individual funders? You say you take no advertising from anyone you cover. Say what you do take. "Reader-funded" is a category, not a disclosure, and you would not accept it from the city.',
                'I am not suggesting anything improper. I am suggesting that a paper which argues, correctly, that discretion left unexamined becomes a habit is subject to the same rule as everyone else it writes about.',
                'I expect you will publish this letter, because you publish letters as sent. I would rather you answered it.'
            ),
        ],
    ],
];
