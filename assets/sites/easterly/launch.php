<?php
/**
 * The Easterly — launch pack (FOUNDATION build, Nov 2026).
 * Seeded once by `PP_SITE=easterly php tools/seed-launch.php`, at
 * launch, after the brand build. Never before: see DEPLOY-EASTERLY.md.
 *
 * ZERO STORIES BY DESIGN: identity, desks and wire sources only. The
 * brand build replaces the placeholder tagline, newsletter name and
 * colours, and writes the six-story inaugural edition (launch notes
 * about the paper itself, true by construction; never invented local
 * news, CLAUDE.md debt item 1).
 *
 * Covers: Atlantic Canada, region-wide. An east wind off the Atlantic: the region-wide paper, the way Western Wire covers the West.
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
    'domains' => ['easterly.ca', 'www.easterly.ca'],

    'desks' => [
        ['name' => 'News', 'slug' => 'news', 'color' => '#182330', 'description' => 'Atlantic Canada\'s four provinces, read together: Nova Scotia, New Brunswick, Prince Edward Island, and Newfoundland and Labrador.'],
        ['name' => 'Politics', 'slug' => 'politics', 'color' => '#182330', 'description' => 'Four legislatures and the Atlantic seats in Ottawa, and the decisions that cross provincial lines.'],
        ['name' => 'Business', 'slug' => 'business', 'color' => '#182330', 'description' => 'Fisheries, ports, energy and the region\'s employers: how the East makes its living.'],
        ['name' => 'Environment', 'slug' => 'environment', 'color' => '#182330', 'description' => 'The coast, the weather and the land: storms, the fishery and the changing shoreline.'],
        ['name' => 'Culture', 'slug' => 'culture', 'color' => '#182330', 'description' => 'The music, writing, stages and festivals of the four provinces.'],
        ['name' => 'Sports', 'slug' => 'sports', 'color' => '#182330', 'description' => 'The region\'s teams and athletes, from the junior rinks up.'],
        ['name' => 'Opinion', 'slug' => 'opinion', 'color' => '#182330', 'description' => 'Signed columns and letters, always labelled as opinion.'],
    ],

    'settings' => [
        'site_title' => 'The Easterly',
        'tagline' => 'Atlantic Canada, on the east wind',
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

    /* Intentionally empty: the brand build writes the inaugural edition. */
    'stories' => [],
];
