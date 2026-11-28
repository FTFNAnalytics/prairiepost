<?php
/**
 * Borealis Bulletin — launch pack (FOUNDATION build, Nov 2026).
 * Seeded once by `PP_SITE=borealis-bulletin php tools/seed-launch.php`, at
 * launch, after the brand build. Never before: see DEPLOY-BOREALIS.md.
 *
 * ZERO STORIES BY DESIGN: identity, desks and wire sources only. The
 * brand build replaces the placeholder tagline, newsletter name and
 * colours, and writes the six-story inaugural edition (launch notes
 * about the paper itself, true by construction; never invented local
 * news, CLAUDE.md debt item 1).
 *
 * Covers: The North: Yukon, the Northwest Territories and Nunavut. The northern lights, visible across all three territories; "boreal" also names the forest.
 *
 * Domains: unregistered when this was written (RDAP, 28 Nov 2026).
 * The owner registers them before any launch; a different hostname
 * in the brand package is a question for the owner, not a fix to apply.
 *
 * Desk self-sufficiency: every desk the pack lists already exists
 * network-wide; all are listed so the pack stands alone. palette.json
 * labels them for this paper (desk_labels).
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['borealisbulletin.ca', 'www.borealisbulletin.ca'],

    'desks' => [
        ['name' => 'News', 'slug' => 'news', 'color' => '#182330', 'description' => 'Yukon, the Northwest Territories and Nunavut, read together: the communities, the capitals and the people who live north of sixty.'],
        ['name' => 'Politics', 'slug' => 'politics', 'color' => '#182330', 'description' => 'The territorial legislatures, Indigenous governments and the North\'s seats in Ottawa.'],
        ['name' => 'Resources', 'slug' => 'resources', 'color' => '#182330', 'description' => 'Mines, energy and the projects that shape northern economies, and who decides on them.'],
        ['name' => 'Environment', 'slug' => 'environment', 'color' => '#182330', 'description' => 'Permafrost, ice, wildlife and weather: the land and the changes it is going through.'],
        ['name' => 'Culture', 'slug' => 'culture', 'color' => '#182330', 'description' => 'The music, art, languages and festivals of the North.'],
        ['name' => 'Sports', 'slug' => 'sports', 'color' => '#182330', 'description' => 'Northern teams and athletes, from community gyms to the Arctic Winter Games.'],
        ['name' => 'Opinion', 'slug' => 'opinion', 'color' => '#182330', 'description' => 'Signed columns and letters, always labelled as opinion.'],
    ],

    'settings' => [
        'site_title' => 'Borealis Bulletin',
        'tagline' => 'News from north of sixty',
        'meta_description' => 'Borealis Bulletin covers the North: Yukon, the Northwest Territories and Nunavut, their communities, governments and land.',
        'footer_line' => 'The North\'s paper, from Yukon to Nunavut.',
        'newsletter_heading' => 'First Light',
        'newsletter_copy' => 'The Bulletin\'s front page in your inbox every weekday morning: what the three territories decided, built and argued about.',
        'regions' => json_encode([
            'yukon' => 'Yukon',
            'northwest-territories' => 'Northwest Territories',
            'nunavut' => 'Nunavut',
            'north' => 'The North',
        ], JSON_UNESCAPED_UNICODE),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). Each
       answered with RSS on 28 Nov 2026. */
    'sources' => [
        ['CBC North', 'https://www.cbc.ca/webfeed/rss/rss-canada-north', 'north'],
        ['Cabin Radio', 'https://cabinradio.ca/feed/', 'northwest-territories'],
        ['Nunatsiaq News', 'https://nunatsiaq.com/feed/', 'nunavut'],
        ['Yukon News', 'https://yukon-news.com/feed/', 'yukon'],
    ],

    /* Intentionally empty: the brand build writes the inaugural edition. */
    'stories' => [],
];
