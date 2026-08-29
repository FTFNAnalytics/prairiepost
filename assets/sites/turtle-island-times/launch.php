<?php
/**
 * Turtle Island Times — launch package.
 * Loaded once by `PP_SITE=turtle-island-times php tools/seed-launch.php`.
 *
 * DEMO CONTENT. These twelve pieces exist to showcase the design — the
 * featured plate, the tile river with and without photographs, the full-bleed
 * pull quote, the section fronts. They are illustrative, not reported, and
 * are meant to be replaced by the newsroom's own work.
 *
 * Every story carries an explicit `ti-` slug. `posts.slug` is UNIQUE across
 * the whole shared database and the seeder silently skips a story whose slug
 * is already taken, so nothing here is left to chance.
 *
 * Every desk the pack uses is listed in 'desks', so the pack stands on its
 * own — the seeder creates only what the shared database is missing and
 * reuses by slug anything a sister paper already seeded.
 *
 * Safe to re-run: existing stories are skipped, and settings the newsroom
 * has edited are left alone.
 */

$ago = fn (string $offset) => date('Y-m-d H:i:s', strtotime($offset));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$q = fn (string $quote, string $who) => '<blockquote><p>' . $quote . '</p><cite>' . $who . '</cite></blockquote>';
$h = fn (string $head) => '<h2>' . $head . '</h2>';
$img = fn (string $file) => '/assets/sites/turtle-island-times/img/' . $file;

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['turtleislandtimes.ca', 'www.turtleislandtimes.ca'],

    'desks' => [
        ['name' => 'News',        'slug' => 'news',       'color' => '#004961', 'description' => 'What happened, across the territories.'],
        ['name' => 'Land & Water','slug' => 'land-water', 'color' => '#0088B0', 'description' => 'Rivers, fisheries, forestry and the agreements that govern them.'],
        ['name' => 'Language',    'slug' => 'language',   'color' => '#A8451F', 'description' => 'Speakers, classrooms, and the work of carrying a language forward.'],
        ['name' => 'Culture',     'slug' => 'culture',    'color' => '#0A303E', 'description' => 'Art, ceremony, repatriation and the people doing the work.'],
        ['name' => 'Governance',  'slug' => 'governance', 'color' => '#006786', 'description' => 'Councils, negotiations, and decisions taken on behalf of communities.'],
    ],

    'settings' => [
        'site_title'         => 'Turtle Island Times',
        'tagline'            => 'Independent news from across the territories',
        'meta_description'   => 'Independent news from across the territories: land and water, language, culture and governance.',
        'footer_line'        => 'Independent news from across the territories',
        'contact_email'      => 'contact@turtleislandtimes.ca',
        'newsletter_heading' => 'The morning brief',
        'newsletter_copy'    => 'One email, weekday mornings, five minutes.',
        'breaking_label'     => '',
        'breaking_url'       => '',
        'regions'            => json_encode([
            'territories' => 'The territories',
            'national'    => 'National',
        ]),
    ],

    /**
     * Wire sources for the newsroom's morning pull. These populate the
     * dashboard's story-idea feed; they do not publish anything on their own.
     */
    'sources' => [
        ['APTN News',          'https://www.aptnnews.ca/feed/',                          'territories'],
        ['CBC Indigenous',     'https://www.cbc.ca/webfeed/rss/rss-Indigenous',          'national'],
        ['IndigiNews',         'https://indiginews.com/feed',                            'territories'],
        ['Ku\'ku\'kwes News',  'https://kukukwes.com/feed/',                             'territories'],
        ['The Turtle Island News', 'https://theturtleislandnews.com/index.php/feed/',    'national'],
    ],

    'stories' => [

        /* ----------------------------------------------------- the feature --- */
        [
            'title' => 'Sockeye return to the upper river for the first time in seventy years',
            'slug' => 'ti-sockeye-return-to-the-upper-river',
            'desk' => 'land-water', 'byline' => 'Danielle Paul', 'dateline' => 'AT THE FISH FENCE',
            'lede' => 'Counts at the weir topped four thousand this week, three years after the flow agreement. Elders who remember the last run walked the bank on Sunday.',
            'image' => $img('river-weir.svg'),
            'image_caption' => 'The weir at first light. Counters work two-hour shifts from four in the morning.',
            'image_credit' => 'Turtle Island Times',
            'featured' => 1, 'placement' => 'hero', 'views' => 1840, 'published' => $ago('-3 hours'),
            'tags' => 'fisheries, water, agreements',
            'body' => $p(
                'The first fish came through on a Tuesday. Nobody was expecting it that early, and the two counters on shift spent a minute arguing about what they had seen before either of them wrote anything down.',
                'By Friday the tally was past four hundred. By the following Wednesday it had cleared four thousand, and the fisheries office had stopped calling it an anomaly.',
                'The fence goes in the second week of June and comes out when the water drops. In between, two people stand in it for two hours at a time, in waders, counting fish by eye and marking a tally sheet that has not changed format since 2017.'
            )
            . $h('What the agreement actually changed')
            . $p(
                'Three things, in the order they were argued over: the summer release schedule, the temperature trigger, and who holds the gauge data. The last one took longer than the other two combined.',
                'The gauge had been read by the utility since it went in. The agreement moved the reading, the publishing and the archive to the fisheries office — the part nobody expected to win, and the part that has changed the most.',
                'Before the agreement the official number came from a gauge the utility read, published quarterly, and reconciled to nobody. The fisheries office kept its own count the whole time. For six years the two numbers sat side by side and only one of them was allowed to matter.'
            )
            . $q('My grandmother described this river to me and I did not believe her. I thought she was describing a different place.', 'Marcel Sam, fisheries technician')
            . $p(
                'The counts are still a fraction of what the river carried before the dam. Nobody at the fence pretends otherwise. What has changed is that the number is no longer zero, and it is no longer somebody else\'s number to publish.',
                'Counting continues until the water drops below the trigger, which on current forecasts means the second week of September.',
                'What happens after that is the question nobody at the fence wants to answer out loud. One good year is a good year. The flow agreement runs to 2031, and the temperature trigger — the clause that took the second-longest to write — has not yet been tested by a genuinely hot August.',
                'The office will publish the final count in October, the same week the panel sits. That timing is not an accident.'
            )
            . $p(
                'There is a version of this that is only about fish, and it is not the version anyone at the fence tells. The dam went in without consent and the run went out within a decade of it. Everything since — the studies, the panels, the twenty-year fight over whether the loss was even attributable — has been about who gets to describe what happened to a river.',
                'That is why the gauge clause mattered more than the release schedule. A number nobody here produced can be revised by people nobody here elected. A number produced at this fence, by these staff, on a form that has not changed since 2017, is harder to argue away and easier to build on.',
                'The elders who walked the bank on Sunday were not there for the count. Several of them had been at the hearings in the eighties, and two had testified. One of them asked the counters to say the number out loud rather than show her the sheet.',
                'She said she wanted to hear somebody say it.',
            ),
        ],

        /* ---------------------------------------------------- land & water --- */
        [
            'title' => 'Two cutblocks deferred, eleven still standing on the schedule',
            'slug' => 'ti-two-cutblocks-deferred',
            'desk' => 'land-water', 'byline' => 'Joseph Whitecalf', 'dateline' => '',
            'lede' => 'The deferral covers 2,100 hectares. The schedule it was carved out of covers considerably more.',
            'image' => $img('forest-cut.svg'),
            'image_caption' => 'The boundary of the deferred area, looking north.',
            'image_credit' => 'Turtle Island Times',
            'views' => 640, 'published' => $ago('-9 hours'),
            'tags' => 'forestry, deferrals',
            'body' => $p(
                'The deferral was announced as a pause. Read the order and it is a pause on two of thirteen blocks, with the remaining eleven unchanged and three of them scheduled inside the year.',
                'That is not nothing. The two blocks carry the oldest stands in the licence area, and the deferral is indefinite rather than dated — a stronger instrument than the ones used in the last round.',
                'It is also the third time this valley has been through a process that pauses part of a schedule and leaves the rest running. The council has asked three times for the full schedule to be tabled at the planning table. It has not yet been.',
                'The licensee points out, fairly, that it has met every requirement and that the deferral was voluntary. Both things are true. The question the council keeps asking is not whether the process was followed but why a process that produces a thirteen-block schedule can only be argued with two blocks at a time.',
                'There is a planning table. It has met twice this year. Neither meeting had the schedule on the agenda, and both had the deferral on it — which is a way of discussing the exception without discussing the rule.'
            )
            . $p(
                'The stands in the two deferred blocks are between two and four hundred years old. That is the number that made them deferrable, and it is also the number that makes the other eleven blocks look like an accounting exercise: younger timber, faster approval, same watershed.',
                'The council\'s position has been consistent through three rounds. It is not that no tree may be cut. It is that a schedule assembled by one party and negotiated one exception at a time is not a plan, and that being asked to celebrate each exception is a way of never discussing the plan.',
                'The next planning table sits in November. The schedule is not yet on the agenda.',
            ),
        ],
        [
            'title' => 'The reservoir sits at eighty per cent, and downstream is asking why',
            'slug' => 'ti-reservoir-at-eighty-per-cent',
            'desk' => 'land-water', 'byline' => 'Sam Isaac', 'dateline' => '',
            'lede' => 'The release schedule is public and the storage numbers are public. The two do not obviously reconcile.',
            'image' => $img('reservoir.svg'),
            'image_caption' => 'The forebay, from the access road.',
            'image_credit' => 'Turtle Island Times',
            'views' => 415, 'published' => $ago('-1 day 2 hours'),
            'tags' => 'water, utility',
            'body' => $p(
                'Storage is high for the date. Releases have sat at the agreement minimum since the second week of July. Both facts are published and neither is disputed.',
                'What is disputed is whether the minimum was ever meant to be the operating target rather than the floor. The agreement says "not less than" — which the utility reads as a threshold and the fisheries office reads as a starting point.',
                'The panel meets in October. Until then, the distance between those two readings is roughly the flow of a small river.',
                'It matters most in the third week of August, which is when the temperature trigger becomes live. If storage is high and releases are at the floor when the trigger fires, the utility has to move a great deal of water quickly, and moving a great deal of water quickly is its own problem downstream.',
                'The fisheries office has asked for a graduated schedule that anticipates the trigger rather than reacting to it. The utility says that is a matter for the panel. The panel meets after the trigger window closes.'
            )
            . $p(
                'Both parties have been here before. In 2019 the trigger fired in the last week of August with storage at eighty-four per cent, and the release that followed scoured two spawning beds that had been rebuilt the previous autumn. The utility\'s own report called the ramp rate \'suboptimal\'.',
                'The fisheries office asked then for the same thing it is asking for now. The panel agreed then that the question was reasonable and referred it to a working group, which met four times and produced a document that recommended further study.',
                'The difference this year is that the office holds the gauge data, so the argument is at least being had over one set of numbers rather than two.',
            ),
        ],
        [
            'title' => 'Every fishery closure this summer, on one map',
            'slug' => 'ti-every-fishery-closure-on-one-map',
            'desk' => 'land-water', 'byline' => 'the newsroom', 'dateline' => '',
            'lede' => 'What the summer order covers, river by river, and which sections come back first.',
            'image' => $img('map-table.svg'),
            'image_caption' => 'The closure map, marked up at the fisheries office.',
            'image_credit' => 'Turtle Island Times',
            'views' => 980, 'published' => $ago('-2 days'),
            'tags' => 'fisheries, closures, maps',
            'body' => $p(
                'The order runs to eleven pages and covers nine systems. This is the same information, drawn.',
                'Closures are coded by the date they lift. Three sections carry no lift date at all, which in practice means closed until the order is amended.',
                'Corrections to the map are welcome and will be applied the same day.'
            ),
        ],

        /* ----------------------------------------------------------- news --- */
        [
            'title' => 'New treaty signed in the coastal region after four years at the table',
            'slug' => 'ti-new-treaty-signed-coastal-region',
            'desk' => 'news', 'byline' => 'Sam Isaac', 'dateline' => 'ON THE COAST',
            'lede' => 'Four years of negotiation, and a map that finally starts filled in rather than blank.',
            'image' => $img('coast-village.svg'),
            'image_caption' => 'The village, the morning after the signing.',
            'image_credit' => 'Turtle Island Times',
            'views' => 2210, 'published' => $ago('-6 hours'),
            'tags' => 'treaty, negotiations',
            'body' => $p(
                'The signing took forty minutes. The four years before it are the story.',
                'What is different about this agreement is where it starts. Previous rounds opened with a blank map and asked the nation to prove its way onto it. This one opened with the nation\'s own survey and asked the province to argue its way off.',
                'That reversal is not rhetorical. It changed what evidence had to be produced, who had to produce it, and how long each round took. The nation spent the first eighteen months assembling a survey — oral record, archival material, and a great deal of walking — and then spent two years defending it line by line.'
            )
            . $q('We stopped negotiating for recognition somewhere in year two. After that we were negotiating implementation, and that is a different table.', 'A member of the negotiating team')
            . $p(
                'Implementation begins in April. The first test is the shared decision-making provision on referrals — the clause that took longest to draft, and the one everyone expects to be litigated first.',
                'Referrals are the daily texture of this relationship: a permit application arrives, someone has to say yes or no, and until now the nation has been consulted rather than consulted-with. The new clause requires agreement rather than input. What agreement means when the two parties disagree is exactly what the drafting fights were about, and the language that survived is deliberately narrow.',
                'The first referrals under the new regime arrive in May.'
            )
            . $p(
                'What is not in the agreement is as instructive as what is. There is no extinguishment language, which is the single most fought-over absence in the document and the reason it took until year three to have anything to initial. There is no cash settlement that closes the file. And there is no clause that makes implementation contingent on future provincial budgets, which was struck twice and stayed struck.',
                'The nation\'s negotiators have been careful in public not to call it a template. Three other tables are watching it closely enough that the word gets used anyway.',
                'The chief said one sentence at the signing and then sat down. It was about her father, who had worked on the first submission in 1994 and did not live to see this one.',
            ),
        ],
        [
            'title' => 'Housing starts in three communities, and the water lines to match',
            'slug' => 'ti-housing-starts-and-water-lines',
            'desk' => 'news', 'byline' => 'Rita Nyland', 'dateline' => '',
            'lede' => 'Forty-two units are funded. The servicing that makes them habitable is funded separately, and later.',
            'views' => 520, 'published' => $ago('-1 day 6 hours'),
            'tags' => 'housing, infrastructure',
            'body' => $p(
                'The units are real and the money is committed. The servicing is a separate program with a separate application window — one that closes after construction is meant to begin.',
                'Everyone involved knows this. The workaround is to build to the point of connection and wait, which is what happened the last two rounds, and is why four finished houses in the next community stood empty through a winter.',
                'The ask is small and specific: align the two windows. It has been made in writing three times.'
            ),
        ],

        /* ------------------------------------------------------- language --- */
        [
            'title' => 'The last three fluent speakers, and the class of forty',
            'slug' => 'ti-last-three-fluent-speakers',
            'desk' => 'language', 'byline' => 'Rita Nyland', 'dateline' => '',
            'lede' => 'Two nights a week in a room above the band office, forty people are learning a language that three people speak.',
            'image' => $img('band-office.svg'),
            'image_caption' => 'The room above the band office, Tuesday evening.',
            'image_credit' => 'Turtle Island Times',
            'views' => 1620, 'published' => $ago('-1 day 4 hours'),
            'tags' => 'language, teaching',
            'body' => $p(
                'The class started with eleven. It is at forty now, and the room is the constraint rather than the interest.',
                'Three fluent speakers remain, all of them over eighty, and two of them teach. The arithmetic of that is not lost on anyone in the room.'
            )
            . $q('I am not worried about whether they learn it. I am worried about whether they have anyone to speak it to in ten years. That is a different problem and it needs more than a classroom.', 'One of the three')
            . $p(
                'The nests are the answer to that, and the nests need wages — which is what the council voted on last week.',
                'The class itself is not a language programme in the usual sense. There is no curriculum and no textbook, because there is no textbook. There are two elders, a whiteboard, and a rule that nobody speaks English after the first ten minutes.',
                'It is slow. Adults learning a language they were meant to grow up in learn it badly at first and take it personally, which is a thing the elders have got good at managing. Attendance has not dropped once since February.'
            )
            . $p(
                'The forty are not a homogeneous room. About a third are in their twenties, a third are parents of school-age children, and the rest are older people who heard the language as children and lost it at school. That last group is the hardest to teach and the most determined.',
                'There is a recording project running alongside the class — about four hundred hours so far, all three speakers, transcribed by the students as an exercise. It is slow and it is the most useful thing the class produces, because it will outlast everyone in the room.',
                'Two nights a week. It has not been cancelled once.',
            ),
        ],
        [
            'title' => 'A dictionary forty years in the making goes to the printer',
            'slug' => 'ti-dictionary-goes-to-the-printer',
            'desk' => 'language', 'byline' => 'the newsroom', 'dateline' => '',
            'lede' => 'Nine thousand entries, four dialects, and a decision about orthography that took a decade on its own.',
            'views' => 380, 'published' => $ago('-3 days'),
            'tags' => 'language, publishing',
            'body' => $p(
                'The first cards were written in 1986 on the back of band office stationery. The last entry was checked in March.',
                'The orthography argument is the part outsiders find hardest to follow and speakers find easiest: a writing system is a decision about who the written language is for. The committee chose the one the schools can teach.',
                'Two thousand copies. The first four hundred go to households in the community before a single one goes anywhere else.'
            ),
        ],

        /* -------------------------------------------------------- culture --- */
        [
            'title' => 'A wampum belt comes home after ninety years in storage',
            'slug' => 'ti-wampum-belt-comes-home',
            'desk' => 'culture', 'byline' => 'Danielle Paul', 'dateline' => '',
            'lede' => 'Ninety years in a drawer, four pages of agreement, and one condition that was not the one anybody expected.',
            'image' => $img('gathering.svg'),
            'image_caption' => 'The welcome on the flats, Saturday.',
            'image_credit' => 'Turtle Island Times',
            'views' => 2450, 'published' => $ago('-2 days 3 hours'),
            'tags' => 'repatriation, culture',
            'body' => $p(
                'The belt left in 1934 in circumstances the museum\'s own file describes as unclear. It came back on a Saturday in a wooden case built for it by two men from the community who had never seen it.',
                'The agreement runs four pages. Three of them are insurance and transport. The fourth is the condition — and the condition came from this side of the table.'
            )
            . $q('It is not an artifact. It is a record. Records get read, and you cannot read a thing through two centimetres of glass in a room that closes at five.', 'A member of the repatriation committee')
            . $p(
                'It will be kept in the community, brought out when it is needed, and handled by people who know what it says.',
                'The museum has been careful in public and, by its own account, slow in private. The file that came with the belt runs to sixty pages and contains three different accounts of how it was acquired, none of them written by anyone who was there.',
                'The committee has asked for the file to be copied and kept alongside the belt. That request was granted without discussion, which several people noted was the easiest thing anyone agreed to in four years.'
            )
            . $p(
                'Repatriation files usually turn on ownership, and this one deliberately did not. The committee\'s position from the first meeting was that ownership was not in dispute and never had been; what was in dispute was custody, and custody is a practical question with a practical answer.',
                'That reframing shortened the negotiation by years, according to people on both sides of it. It also produced the fourth page: if the argument is about custody rather than title, then how the thing is kept becomes the substance of the agreement rather than a footnote to it.',
                'The case was built in three weeks by two men who measured the belt from photographs, because they were not permitted to measure it directly until it arrived.',
            ),
        ],
        [
            'title' => 'The cannery flats fill for a festival that nearly did not run',
            'slug' => 'ti-cannery-flats-festival',
            'desk' => 'culture', 'byline' => 'Rita Nyland', 'dateline' => '',
            'lede' => 'Three days of dancing below the old cannery, on a budget that came together six weeks out.',
            'views' => 760, 'published' => $ago('-4 days'),
            'tags' => 'festival, culture',
            'body' => $p(
                'The grant that carried the festival for eight years was not renewed. The organisers found out in June, which is late.',
                'What replaced it was smaller, local and largely in cash: the co-op, two businesses, and a great many envelopes. The festival ran at about two-thirds its usual size, and the flats were fuller than last year.',
                'The organisers would rather have the grant back. They also say the envelopes changed something about who felt the weekend belonged to them.'
            ),
        ],

        /* ----------------------------------------------------- governance --- */
        [
            'title' => 'Council votes to fund language nests in three communities',
            'slug' => 'ti-council-funds-language-nests',
            'desk' => 'governance', 'byline' => 'Sam Isaac', 'dateline' => '',
            'lede' => 'The commitment covers wages for eight fluent speakers, which is the part that took the argument.',
            'views' => 890, 'published' => $ago('-12 hours'),
            'tags' => 'language, council, budget',
            'body' => $p(
                'Nests are cheap to describe and expensive to run. The room is free. The speakers are not, and should not be.',
                'The motion attaches wages to eight positions for three years — long enough that somebody could leave another job to take one, which is the threshold that matters.',
                'The debate was never about whether. It was about whether three years is long enough to ask that of a person, and the answer in the room was that it is the longest this council can honestly promise.',
                'A nest is a room where nothing but the language is spoken, from the time a child arrives to the time they leave. It works because immersion works, and it is hard because it requires fluent adults to be in that room all day, every day, for years.',
                'Three of the eight positions are filled. The other five are the reason the council attached wages rather than a programme budget: a wage is something a person can plan a life around, and a programme budget is not.'
            )
            . $p(
                'The money comes from the council\'s own revenue rather than a program, which is the detail that made the debate long. Program money arrives with reporting requirements and departs on a schedule set elsewhere; own-source money is finite and every dollar of it is already claimed by something.',
                'What the motion displaces is a capital line — a maintenance yard, deferred a fourth time. Two councillors voted against on exactly that ground and said so plainly, which the room respected.',
                'The vote was seven to two.',
            ),
        ],
        [
            'title' => 'Who reads the gauge, and why it took nine years to get it back',
            'slug' => 'ti-who-reads-the-gauge',
            'desk' => 'governance', 'byline' => 'Rita Nyland', 'dateline' => '',
            'lede' => 'A profile of the office that counts the fish by hand, and the nine-year argument over whose number it is.',
            'image' => $img('salmon-run.svg'),
            'image_caption' => 'Counted, photographed, and let through.',
            'image_credit' => 'Turtle Island Times',
            'views' => 1130, 'published' => $ago('-5 days'),
            'tags' => 'fisheries, data, governance',
            'body' => $p(
                'The office has four staff, two of them seasonal, and it has produced an unbroken daily count since 2017.',
                'For the first six of those years the count was unofficial. The official number came from a gauge read by the utility, published quarterly, and reconciled to nobody.',
                'The agreement moved the gauge. That sentence took nine years to write, and it is the reason the number in the first paragraph of this week\'s lead story is a number this community produced.',
                'The office is two rooms above the shop. There is a whiteboard with the daily count, a filing cabinet of tally sheets going back to the first season, and a laminated card by the door listing the four things that get written down for every fish: time, direction, condition, and who saw it.',
                'The seasonal staff are usually students. Several of them have come back for three and four summers, which the office treats as its actual succession plan.'
            )
            . $p(
                'The four things on the laminated card are not arbitrary. Time and direction produce the count. Condition flags fish that are injured or spawned out, which is the data the biologists actually want. And \'who saw it\' exists because in the second season a count was challenged and there was no way to ask the person who made it.',
                'That challenge is the reason the office keeps paper. Everything is entered digitally the same evening, but the sheets go in the cabinet, and the cabinet is the thing the office would save in a fire.',
            ),
        ],
    ],
];
