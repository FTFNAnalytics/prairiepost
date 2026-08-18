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

    'desks' => [
        ['name' => 'Council', 'slug' => 'council', 'color' => '#0F3B8C', 'description' => 'City hall, the budget, and the votes the minutes do not explain.'],
        ['name' => 'Mining',  'slug' => 'mining',  'color' => '#0F3B8C', 'description' => 'The industry that built the basin, the companies in it, and what they owe the ground they stand on.'],
        ['name' => 'Housing', 'slug' => 'housing', 'color' => '#0F3B8C', 'description' => 'Who gets to live here, at what rent, and what the city has actually built.'],
        ['name' => 'Letters', 'slug' => 'letters', 'color' => '#33425C', 'description' => 'Readers argue back. Signed, and printed as sent.'],
    ],

    'settings' => [
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
        ['CBC Toronto',      'https://www.cbc.ca/webfeed/rss/rss-canada-toronto',   'ontario'],
        ['TVO Today',        'https://www.tvo.org/feeds/rss/all',                   'ontario'],
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
                'The vote on 14 August is not about the arena. It is about whether the next council inherits a process it can actually see into, or another four years of in-camera sessions and reports that arrive the night before the decision.'
            )
            . $q('Ask who was in the room. Then ask why the minutes do not say so.')
            . $p(
                'The record is not complicated. In 2022 the site was chosen on a staff recommendation whose supporting analysis was never released. In 2023 the cost estimate moved by $41 million between a June report and a November one, and no councillor asked in open session what had changed. In 2024 the file went quiet for seven months.',
                'Sudbury is not short of plans. It is short of anyone willing to say which one they voted for and why.',
                'So: on 14 August, move the in-camera items to open session, publish the 2022 site analysis, and require that any report supporting a capital decision over $10 million be public for ten days before the vote. Three motions, one meeting. Ask your councillor which of the three they will move.'
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
                'The appeals are exhausted and the file is closed, which council has taken to mean the matter is settled. Those are different things.',
                'What remains unanswered is narrow and answerable: who assembled the land, on what timeline, and what the city knew about the assembly when it selected the site. Every one of those answers exists in a document the city holds.',
                'A closed file is not a cleared record. Council can release the assembly timeline this month without touching a single legal question. It should, and a councillor should move it at the next open session.'
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
                'There is a defensible case for paying councillors more. Sudbury is 3,200 square kilometres of ward work, and a council of people who can only afford to serve if they are retired or independently comfortable is a worse council. Make that case out loud.',
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
                'Amalgamation was sold to the outlying communities partly on the promise that services would be planned for the whole city. A metric that structurally disadvantages low-density wards quietly retires that promise without a vote on it.',
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
                'The relevant question is not sentimental. It is jurisdictional: after decommissioning, who holds the long-term monitoring obligation for the ground around the base, and for how long? The province points to the closure plan. The closure plan points to the company. The company points to the closure plan.'
            )
            . $q('A structure that shaped a century of this city\'s air should not leave a hole in the paperwork.')
            . $p(
                'Sudbury has been here before. The regreening programme exists because the public eventually paid for damage that was privately caused and publicly ignored. That was a fifty-year bill.',
                'The city cannot rewrite a provincial closure plan. It can demand, in writing and at an open meeting, that the province state the monitoring obligation and its duration before the last stage of demolition. Council should pass that resolution this fall.'
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
                'Sudbury\'s regreening is a genuine municipal achievement and one of the few environmental stories in this country that improved. It gets cited at conferences. It should be.',
                'It is also thirty-two years of tree planting on ground whose soil chemistry is still being studied, and the monitoring budget has not moved in a decade while the planting budget has. A canopy is not a remediation.',
                'Fund the soil monitoring at the same rate as the planting, and publish the results annually alongside the tree count. If the numbers are as good as the trees look, the city loses nothing by showing them.'
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
                'The expansion adds capacity next to a watershed the city draws from. The technical review is provincial, but the public comment window is the only place a resident enters this process at all, and it is thirty days over a construction summer.',
                'Ask the province for sixty days and a public technical session in Sudbury, not in Toronto. Council can request both this month; the company has no reason to object to either.'
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
                'This is not a Sudbury invention — the provincial framework counts the same way. But Sudbury chose to adopt the measure without adding one of its own, and nothing prevented it from adding one.'
            )
            . $q('A door is not housing policy. A rent is.')
            . $p(
                'Report units by rent band, quarterly, against the incomes actually earned in this city. It costs a staff report and a spreadsheet column. Council should direct it at the next planning committee.'
            ),
        ],
        [
            'title' => 'Rooming houses are the housing stock nobody will defend',
            'desk' => 'housing', 'dateline' => '', 'byline' => 'the Editorial Board',
            'lede' => 'Every rooming house lost is replaced by nothing, and the city has no policy that acknowledges this.',
            'views' => 163, 'published' => $ago('-5 days 3 hours'),
            'tags' => 'housing, rooming houses',
            'body' => $p(
                'The units are small, often poorly maintained, and easy to campaign against. They are also the cheapest legal accommodation in the city, and when one closes its tenants do not move into a new build.',
                'Enforcement is necessary; nobody should live somewhere unsafe. But enforcement without replacement is a housing loss dressed as a standards win, and the city reports it as the latter.',
                'Report the units lost to enforcement each year beside the units gained, in the same table. If the city is comfortable with the trade, it should be comfortable publishing it.'
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
                'A study is cheaper than a build and easier to announce, and it produces a document that can be pointed at for four years. That is why the cycle runs.',
                'Before commissioning a seventh, publish an audit of the six: what each recommended, what was built, what it cost. Then decide. Ask for that audit at budget.'
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
                'You will be handed a report on the Friday for a Tuesday vote and told that deferral costs money. Sometimes that is true. Most times the deadline is one the administration set and can move. Ask which it is, and ask in open session so the answer is on the record.',
                'The second thing: the minutes will not record what you were told in camera, only that you were told something. If a decision turns on information you cannot repeat, say so out loud before you vote.',
                'To the residents reading: write to your councillor before the 14th, not after.'
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
                'If a measure guarantees a result, the people who chose the measure chose the result. That is not a design flaw to be corrected in the next review. It is a decision, and it belongs to named councillors who voted for the terms of reference.',
                'Name them next time. You are usually willing to.'
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
                'How many subscribers do you have, what does an issue cost to produce, and who are the largest individual funders? You say you take no advertising from anyone you cover. Say what you do take.',
                'I expect you will publish this letter, because you publish letters as sent. I would rather you answered it.'
            ),
        ],
    ],
];
