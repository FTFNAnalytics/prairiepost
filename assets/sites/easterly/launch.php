<?php
/**
 * The Easterly — launch pack (foundation, Nov 2026; brand package
 * applied 28 Nov 2026). Seeded once by
 * `PP_SITE=easterly php tools/seed-launch.php`, at launch. Never
 * before: see DEPLOY-EASTERLY.md.
 *
 * The tagline is the brand package's lockup line; the desk colour is its
 * Harbour. The newsletter name and footer line are still placeholders.
 *
 * THE INAUGURAL EDITION is seven launch notes, one per desk: the
 * editorial board's opening piece on Opinion and a method note on each
 * other desk. Every one is about the paper itself, true by construction:
 * no events, votes, figures or human bylines, and links only to pages
 * this paper serves (CLAUDE.md debt item 1). They follow the owner's
 * answers of 28 Nov 2026: The Easterly does its own reporting, keeps
 * Atlantic time, and covers the four provinces as one region. Each is
 * dated when the seeder runs, so the edition is stamped with the launch.
 *
 * Covers: Atlantic Canada, region-wide, with its own reporting. An east wind off the Atlantic.
 *
 * Domains: unregistered when this was written (RDAP, 28 Nov 2026).
 * The owner registers them before any launch; a different hostname
 * in the brand package is a question for the owner, not a fix to apply.
 *
 * Desk self-sufficiency: every desk the pack lists already exists
 * network-wide; all are listed so the pack stands alone. palette.json
 * labels them for this paper (desk_labels).
 */

/* Dates the inaugural edition when the seeder runs (the older packs' helper). */
$ago = fn (string $offset): string => date('Y-m-d H:i:s', strtotime($offset));

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['easterly.ca', 'www.easterly.ca'],

    'desks' => [
        ['name' => 'News', 'slug' => 'news', 'color' => '#1D6A85', 'description' => 'Atlantic Canada\'s four provinces, read together: Nova Scotia, New Brunswick, Prince Edward Island, and Newfoundland and Labrador.'],
        ['name' => 'Politics', 'slug' => 'politics', 'color' => '#1D6A85', 'description' => 'Four legislatures and the Atlantic seats in Ottawa, and the decisions that cross provincial lines.'],
        ['name' => 'Business', 'slug' => 'business', 'color' => '#1D6A85', 'description' => 'Fisheries, ports, energy and the region\'s employers: how the East makes its living.'],
        ['name' => 'Environment', 'slug' => 'environment', 'color' => '#1D6A85', 'description' => 'The coast, the weather and the land: storms, the fishery and the changing shoreline.'],
        ['name' => 'Culture', 'slug' => 'culture', 'color' => '#1D6A85', 'description' => 'The music, writing, stages and festivals of the four provinces.'],
        ['name' => 'Sports', 'slug' => 'sports', 'color' => '#1D6A85', 'description' => 'The region\'s teams and athletes, from the junior rinks up.'],
        ['name' => 'Opinion', 'slug' => 'opinion', 'color' => '#1D6A85', 'description' => 'Signed columns and letters, always labelled as opinion.'],
    ],

    'settings' => [
        'site_title' => 'The Easterly',
        'tagline' => 'News from Atlantic Canada',
        'meta_description' => 'The Easterly covers Atlantic Canada as one region: Nova Scotia, New Brunswick, Prince Edward Island, and Newfoundland and Labrador.',
        'footer_line' => 'Atlantic Canada, read as one region.',
        'newsletter_heading' => 'The Morning Tide',
        'newsletter_copy' => 'The Easterly\'s front page in your inbox every weekday morning: what the four Atlantic provinces decided, built and argued about.',
        'regions' => json_encode([
            'nova-scotia' => 'Nova Scotia',
            'new-brunswick' => 'New Brunswick',
            'prince-edward-island' => 'Prince Edward Island',
            'newfoundland-labrador' => 'Newfoundland and Labrador',
        ], JSON_UNESCAPED_UNICODE),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). Each
       answered with RSS on 28 Nov 2026. */
    'sources' => [
        ['CBC Nova Scotia', 'https://www.cbc.ca/webfeed/rss/rss-canada-novascotia', 'nova-scotia'],
        ['CBC New Brunswick', 'https://www.cbc.ca/webfeed/rss/rss-canada-newbrunswick', 'new-brunswick'],
        ['CBC Prince Edward Island', 'https://www.cbc.ca/webfeed/rss/rss-canada-pei', 'prince-edward-island'],
        ['CBC Newfoundland and Labrador', 'https://www.cbc.ca/webfeed/rss/rss-canada-newfoundland', 'newfoundland-labrador'],
    ],

    /* The inaugural edition: seven launch notes about the paper itself,
       newest first, dated at seed time. */
    'stories' => [
        [
            'title'     => 'Four provinces, one paper',
            'slug'      => 'easterly-first-editorial',
            'desk'      => 'opinion',
            'byline'    => 'The Editorial Board',
            'lede'      => 'The Easterly opens as a newspaper for all of Atlantic Canada. Here is what that commits this newsroom to.',
            'body'      => '<p>An easterly is the wind that comes in off the Atlantic, and it reaches all four provinces. So does this paper. The Easterly covers Nova Scotia, New Brunswick, Prince Edward Island, and Newfoundland and Labrador as one region: the decisions that cross provincial lines, the economy the coast shares, and the stories that matter in one province and are news in the other three.</p><p>The Easterly does its own reporting. That means records before characterizations, named sources wherever possible, and numbers that say where they came from. When we get something wrong, the correction runs at the top of the story, dated.</p><p>Opinion, this page included, is always signed and always labelled. And the newsroom wants to hear from you: tips sent through our <a href="/tips">tips form</a> go straight to the newsroom, and the form keeps only what you type.</p>',
            'featured'  => 1,
            'published' => $ago('-5 minutes'),
            'tags'      => 'From The Easterly',
        ],
        [
            'title'     => 'How The Easterly works',
            'slug'      => 'easterly-how-we-work',
            'desk'      => 'news',
            'byline'    => 'The Newsroom',
            'lede'      => 'A guide to this paper\'s desks and the rules every story files under.',
            'body'      => '<p>News is the region\'s front page: what happened across the four provinces today, and why it matters beyond the province it happened in. Politics follows four legislatures and the region\'s members in Ottawa. Business covers how the East makes its living, from the fisheries and the ports to energy and the region\'s employers. Environment covers the coast, the weather and the land. Culture takes the region\'s music, writing, stages and festivals seriously. Sports runs from the junior rinks up. Opinion is signed and labelled, always.</p><p>The rules are the same at every desk: documents and records first, named sources wherever possible, and a clear line between reporting and opinion. Times on The Easterly are Atlantic time; Newfoundland time is half an hour later.</p><p>This first edition is about the paper rather than the region. An honest introduction beats manufactured news, and the reporting starts as the newsroom files.</p>',
            'published' => $ago('-25 minutes'),
            'tags'      => 'From The Easterly',
        ],
        [
            'title'     => 'Four legislatures, one politics desk',
            'slug'      => 'easterly-politics-desk',
            'desk'      => 'politics',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Politics desk covers Halifax, Fredericton, Charlottetown and St. John\'s together, with the region\'s members in Ottawa.',
            'body'      => '<p>Most political coverage in Atlantic Canada stops at a provincial border. This desk reads the four legislatures side by side, because so many of the region\'s questions do not stop there: health care, transportation, energy, and the money that flows from Ottawa.</p><p>The method is the record: bills, votes and budgets as they were tabled and passed, read before anyone\'s account of them. The desk names its sources, and it says so when a source would speak only on background.</p><p>If you have a document the public should see, the <a href="/tips">tips form</a> reaches this desk.</p>',
            'published' => $ago('-45 minutes'),
            'tags'      => 'From The Easterly',
        ],
        [
            'title'     => 'How the East makes its living',
            'slug'      => 'easterly-business-desk',
            'desk'      => 'business',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Business desk covers the region\'s working economy: the fisheries, the ports, energy, and the employers in all four provinces.',
            'body'      => '<p>The Business desk follows the money that keeps the region running, from the wharf to the boardroom. It covers the fisheries and the people who work them, the ports and what moves through them, energy in all its forms, and the employers, large and small, across the four provinces.</p><p>Numbers on this desk say where they came from: a statistics release, a company filing, a budget line. Where a figure is an estimate, the story says whose estimate it is.</p><p>If you work in an industry the region depends on and something is changing, the newsroom wants to hear it: <a href="/tips">send a tip</a>.</p>',
            'published' => $ago('-65 minutes'),
            'tags'      => 'From The Easterly',
        ],
        [
            'title'     => 'The coast, the weather and the land',
            'slug'      => 'easterly-environment-desk',
            'desk'      => 'environment',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Environment desk covers the natural forces that shape life on the Atlantic coast, and the decisions people make about them.',
            'body'      => '<p>Life in Atlantic Canada runs close to the ocean. The Environment desk covers the coast and its changing shoreline, the storms that cross the region, the health of the fishery, and the land, forests and rivers inland.</p><p>Weather and science stories on this desk name their sources: the forecasters, the researchers and the agencies whose data they rely on. A forecast is reported as a forecast.</p><p>Seen something change on your stretch of coast? The <a href="/tips">tips form</a> reaches the newsroom.</p>',
            'published' => $ago('-85 minutes'),
            'tags'      => 'From The Easterly',
        ],
        [
            'title'     => 'The region\'s stages, pages and festivals',
            'slug'      => 'easterly-culture-desk',
            'desk'      => 'culture',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Culture desk covers the music, writing, theatre and festivals of the four Atlantic provinces.',
            'body'      => '<p>Atlantic Canada\'s culture travels well beyond the region, and this desk covers it from here: the music, the writing, the stages and the festivals, in the cities and in the towns.</p><p>Reviews on this desk are signed and labelled as reviews, and listings say where they came from.</p><p>Artists, venues and organizers: send what is coming up through the <a href="/tips">tips form</a>.</p>',
            'published' => $ago('-105 minutes'),
            'tags'      => 'From The Easterly',
        ],
        [
            'title'     => 'From the junior rinks up',
            'slug'      => 'easterly-sports-desk',
            'desk'      => 'sports',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Sports desk covers the region\'s teams and athletes at every level, as an Atlantic story.',
            'body'      => '<p>Sport in Atlantic Canada runs from the junior rinks and school fields to the athletes who carry the region\'s name much further. This desk covers all of it, and treats the community game as news.</p><p>Leagues, clubs and schools across the four provinces: send schedules, results and contacts through the <a href="/tips">tips form</a>. They reach the desk directly.</p>',
            'published' => $ago('-125 minutes'),
            'tags'      => 'From The Easterly',
        ],
    ],
];
