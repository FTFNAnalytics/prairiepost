<?php
/**
 * The Burrard Brief — launch pack (brand build, Sep 2026).
 * Seeded once by `PP_SITE=burrard-brief php tools/seed-launch.php`.
 *
 * INAUGURAL SERVICE CONTENT ONLY — identity, desks, wire sources and
 * six launch notes ABOUT the paper (mission, desk methods, how to
 * reach the newsroom). True by construction; no invented local news.
 *
 * Identity from the owner's brand package: the North Shore mountains
 * over Burrard Inlet, one newspaper serif (Source Serif 4), Inlet Teal
 * as the interactive colour, Forest Green reserved for Opinion. The
 * paper's product is brevity: "The Morning Brief" lands at 7 a.m.
 *
 * Desk self-sufficiency: local-news, city-hall, transit and opinion
 * exist network-wide; `housing` and `environment` are new to the
 * network and are created on the first seed (the brand's beats:
 * housing, transit, city halls, the environment). All six are listed
 * so the pack stands alone.
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from.
       NOTE: the registered domain is burrardbrief.ca (the brand sheet's
       mock contact lines say theburrardbrief.ca — the REGISTERED name
       wins; emails below follow it). */
    'domains' => ['burrardbrief.ca', 'www.burrardbrief.ca'],

    'desks' => [
        ['name' => 'Local News',  'slug' => 'local-news',  'color' => '#13606E', 'description' => 'Vancouver and the Lower Mainland, block by block — the people who make this region work.'],
        ['name' => 'Housing',     'slug' => 'housing',     'color' => '#13606E', 'description' => 'Rents, rezonings, towers and the file that decides whether you can live here.'],
        ['name' => 'City Hall',   'slug' => 'city-hall',   'color' => '#13606E', 'description' => 'Councils across the region, reported from the agenda, not the press release.'],
        ['name' => 'Transit',     'slug' => 'transit',     'color' => '#13606E', 'description' => 'SkyTrain, buses, the Broadway line and every way the Lower Mainland moves.'],
        ['name' => 'Environment', 'slug' => 'environment', 'color' => '#13606E', 'description' => 'The inlet, the rivers, the air sheds and the atmospheric ones too.'],
        ['name' => 'Opinion',     'slug' => 'opinion',     'color' => '#2D5A3D', 'description' => 'Signed columns and letters, always labelled. Set in Forest Green, used sparingly.'],
    ],

    'settings' => [
        'site_title'         => 'The Burrard Brief',
        'tagline'            => 'Vancouver & Lower Mainland news, briefly.',
        'meta_description'   => 'The Burrard Brief is an independent, reader-supported newsroom covering Vancouver and the Lower Mainland — housing, transit, city halls, the environment — and we keep it brief.',
        'footer_line'        => 'Local news that matters, delivered briefly.',
        'contact_email'      => 'tips@burrardbrief.ca',
        'newsletter_heading' => 'The Morning Brief',
        'newsletter_copy'    => 'The five Vancouver and Lower Mainland stories worth your time, written to be read in four minutes. Every weekday at 7 a.m., free.',
        'weather_line'       => '14°C|Showers clearing by evening',
        'regions'            => json_encode([
            'vancouver'      => 'Vancouver',
            'lower-mainland' => 'Lower Mainland',
            'bc'             => 'British Columbia',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['Daily Hive Vancouver',  'https://dailyhive.com/feed/vancouver',       'vancouver'],
        ['Vancouver Is Awesome',  'https://www.vancouverisawesome.com/rss',     'vancouver'],
        ['North Shore News',      'https://www.nsnews.com/rss',                 'lower-mainland'],
        ['Global BC',             'https://globalnews.ca/bc/feed/',             'bc'],
    ],

    /* Inaugural service content only (added Sep 2026): every story below
       is ABOUT the paper — mission, method, how to reach the newsroom.
       True by construction; no invented local events, votes or figures
       (CLAUDE.md debt item 1 must not grow with launches). Real
       reporting arrives through the newsroom or Hermes. */
    'stories' => [
        [
            'title'     => 'Why briefly? The case for a four-minute paper',
            'slug'      => 'burrard-why-briefly',
            'desk'      => 'opinion',
            'byline'    => 'The Editorial Board',
            'lede'      => 'The Burrard Brief is built on a bet: that respecting your time is a form of respect, and that a story told in four paragraphs can still be told honestly.',
            'body'      => '<p>Most news is longer than it needs to be, and the length is not free — it is paid for in readers who stop reading. The Brief\'s founding bet is that Vancouver and the Lower Mainland can be covered seriously in stories designed to be finished.</p><p>Brevity is a discipline, not a shortcut. A four-paragraph story has no room for padding, which means every sentence has to carry a fact, and every fact has to be checked. When a story genuinely needs more space, it gets it; what it never gets is filler.</p><p>The same promise shapes The Morning Brief, our weekday newsletter: five stories worth your time, written to be read in four minutes, free. And when we get something wrong, the correction runs at the top of the story, dated — briefly, but plainly.</p>',
            'featured'  => 1,
            'published' => '2026-09-23 18:00:00',
            'tags'      => 'From the Brief',
        ],
        [
            'title'     => 'What the Brief covers — and what it won\'t waste your time on',
            'slug'      => 'burrard-what-we-cover',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'A short guide to this paper\'s desks: The City, Housing, City Hall, Transit and Environment — and the standard every one of them files under.',
            'body'      => '<p>The City is the front page of daily life here. Housing follows the file that shapes everything else in this region. City Hall covers decisions, not announcements. Transit rides the system it reports on. Environment treats the inlet, the mountains and the rain as a beat, not a backdrop.</p><p>Across all of them, one standard: documents before characterizations, named sources wherever possible, opinion always labelled and never mixed in. Wire items that summarize another outlet\'s reporting will say so and link to it.</p><p>This opening edition is about the paper itself — we would rather introduce ourselves honestly than pad the front page with dressed-up filler. The reporting starts as the newsroom files. Tips: <a href="mailto:tips@burrardbrief.ca">tips@burrardbrief.ca</a>.</p>',
            'published' => '2026-09-23 17:40:00',
            'tags'      => 'From the Brief',
        ],
        [
            'title'     => 'How we\'ll read the housing file: numbers before narratives',
            'slug'      => 'burrard-housing-desk-note',
            'desk'      => 'housing',
            'byline'    => 'The Newsroom',
            'lede'      => 'Housing is the story every other Lower Mainland story eventually becomes. This desk\'s method: start from the filings and the data, and let the narrative earn its way in.',
            'body'      => '<p>No beat in this region attracts more confident storytelling on thinner evidence than housing. This desk\'s working rule is to begin where the record is — applications, approvals, completions, vacancy and rent data — and to treat every narrative, hopeful or catastrophic, as a claim to be tested against it.</p><p>Every number we print will say where it came from. When sources disagree, we will show the disagreement rather than picking the convenient line.</p><p>If you are inside the file — tenant, landlord, builder, planner — and see something the numbers miss, the desk reads everything: <a href="mailto:tips@burrardbrief.ca">tips@burrardbrief.ca</a>.</p>',
            'published' => '2026-09-23 17:20:00',
            'tags'      => 'From the Brief',
        ],
        [
            'title'     => 'Council in brief: our promise on process',
            'slug'      => 'burrard-city-hall-desk-note',
            'desk'      => 'city-hall',
            'byline'    => 'The Newsroom',
            'lede'      => 'City Hall coverage in four paragraphs is only honest if the reading behind it was longer. Here is how this desk will work.',
            'body'      => '<p>A brief council story is the end of the process, not the process. Behind each one: the agenda read in advance, the staff report, the debate as it happened and the vote as recorded. The compression happens after the homework, never instead of it.</p><p>We will identify the meeting and item every council story comes from, so the four paragraphs can always be checked against the full record.</p><p>Municipal government across the Lower Mainland is many councils, not one; where a decision in one city moves the others, the desk will say so plainly.</p>',
            'published' => '2026-09-23 17:00:00',
            'tags'      => 'From the Brief',
        ],
        [
            'title'     => 'Transit coverage that rides the system',
            'slug'      => 'burrard-transit-desk-note',
            'desk'      => 'transit',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Transit desk\'s first commitment is the obvious one: cover the network as a rider, from the platform, not from the press conference.',
            'body'      => '<p>Transit stories tend to be written from announcements — funding secured, lines proposed, ribbons cut. The daily experience of the network lives somewhere else: on the platform, at the stop, in the gap between the schedule and the arrival. This desk starts there.</p><p>Announcement coverage will always be paired with the record: what was promised before, what was delivered, what changed. And service changes will be covered as what they are — news that alters thousands of routines at once.</p><p>Riders see the system before anyone else does. What you notice is a tip: <a href="mailto:tips@burrardbrief.ca">tips@burrardbrief.ca</a>.</p>',
            'published' => '2026-09-23 16:40:00',
            'tags'      => 'From the Brief',
        ],
        [
            'title'     => 'The inlet is a beat: how we\'ll cover the environment',
            'slug'      => 'burrard-environment-desk-note',
            'desk'      => 'environment',
            'byline'    => 'The Newsroom',
            'lede'      => 'The paper is named for a body of water. The Environment desk exists so that the inlet, the mountains and the rain are covered as news, not scenery.',
            'body'      => '<p>Around here the environment is not a section you visit occasionally — it is infrastructure. The air in fire season, the creeks under the streets, the shoreline the region is built along: each is a running story with documents, decisions and money attached, and this desk will cover them that way.</p><p>Expect the coverage to be specific: named waterways, cited monitoring, decisions traced to the bodies that made them. General alarm is not reporting.</p><p>If you steward, study or work on any piece of this — streamkeepers, researchers, crews — the desk wants to hear from you: <a href="mailto:tips@burrardbrief.ca">tips@burrardbrief.ca</a>.</p>',
            'published' => '2026-09-23 16:20:00',
            'tags'      => 'From the Brief',
        ],
    ],
];
