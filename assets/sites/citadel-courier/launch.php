<?php
/**
 * Citadel Courier — launch pack (FOUNDATION build, Nov 2026).
 * Seeded once by `PP_SITE=citadel-courier php tools/seed-launch.php`, at
 * launch, after the brand build. Never before: see DEPLOY-CITADEL.md.
 *
 * ZERO STORIES BY DESIGN: identity, desks and wire sources only. The
 * brand build replaces the placeholder tagline, newsletter name and
 * colours, and writes the six-story inaugural edition (launch notes
 * about the paper itself, true by construction; never invented local
 * news, CLAUDE.md debt item 1).
 *
 * Covers: Halifax and Nova Scotia. Named for the Halifax Citadel, the star-shaped fort on the hill above the harbour.
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
    'domains' => ['citadelcourier.ca', 'www.citadelcourier.ca'],

    'desks' => [
        ['name' => 'Local News', 'slug' => 'local-news', 'color' => '#182330', 'description' => 'Halifax and the communities around the harbour: neighbourhoods, housing, transit and the people who live here.'],
        ['name' => 'City Hall', 'slug' => 'city-hall', 'color' => '#182330', 'description' => 'Halifax Regional Council and its committees, read from the agenda and the record.'],
        ['name' => 'Politics', 'slug' => 'politics', 'color' => '#182330', 'description' => 'Province House and the government of Nova Scotia, and what their decisions mean for the city.'],
        ['name' => 'Business', 'slug' => 'business', 'color' => '#182330', 'description' => 'The port, the trades and the shops: how Halifax makes its living.'],
        ['name' => 'Sports', 'slug' => 'sports', 'color' => '#182330', 'description' => 'The city\'s teams and athletes, from the junior rinks to the pitch.'],
        ['name' => 'Opinion', 'slug' => 'opinion', 'color' => '#182330', 'description' => 'Signed columns and letters, always labelled as opinion.'],
    ],

    'settings' => [
        'site_title' => 'Citadel Courier',
        'tagline' => 'Halifax, from the hill to the harbour',
        'meta_description' => 'Citadel Courier is a Halifax newspaper: the city, council, Province House, the port and the people around the harbour.',
        'footer_line' => 'Halifax\'s paper, from the hill to the harbour.',
        'newsletter_heading' => 'The Noon Gun',
        'newsletter_copy' => 'The Courier\'s front page in your inbox at midday, when the Citadel fires its gun: what Halifax decided, built and argued about this morning.',
        'regions' => json_encode([
            'halifax' => 'Halifax',
            'nova-scotia' => 'Nova Scotia',
        ], JSON_UNESCAPED_UNICODE),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). Each
       answered with RSS on 28 Nov 2026. */
    'sources' => [
        ['CBC Nova Scotia', 'https://www.cbc.ca/webfeed/rss/rss-canada-novascotia', 'nova-scotia'],
        ['Global Halifax', 'https://globalnews.ca/halifax/feed/', 'halifax'],
        ['Halifax Examiner', 'https://www.halifaxexaminer.ca/feed/', 'halifax'],
    ],

    /* Intentionally empty: the brand build writes the inaugural edition. */
    'stories' => [],
];
