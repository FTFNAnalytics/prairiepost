<?php
/**
 * Citadel Courier — launch pack (foundation, Nov 2026; brand package
 * applied 28 Nov 2026). Seeded once by
 * `PP_SITE=citadel-courier php tools/seed-launch.php`, at launch. Never
 * before: see DEPLOY-CITADEL.md.
 *
 * ZERO STORIES SO FAR: identity, desks and wire sources only. The tagline
 * is the package's homepage line under the wordmark and the footer line
 * its homepage footer; the footprint is its README's, "Halifax and Nova
 * Scotia". The desk colour is its Nova Scotia Blue. The newsletter name
 * is still a placeholder, and the six-story inaugural edition (launch
 * notes about the paper itself, true by construction; never invented
 * local news, CLAUDE.md debt item 1) waits on the owner's answers in
 * docs/brand-packages.md.
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
        ['name' => 'Local News', 'slug' => 'local-news', 'color' => '#0047B6', 'description' => 'Halifax and the communities around the harbour: neighbourhoods, housing, transit and the people who live here.'],
        ['name' => 'City Hall', 'slug' => 'city-hall', 'color' => '#0047B6', 'description' => 'Halifax Regional Council and its committees, read from the agenda and the record.'],
        ['name' => 'Politics', 'slug' => 'politics', 'color' => '#0047B6', 'description' => 'Province House and the government of Nova Scotia, and what their decisions mean for the city.'],
        ['name' => 'Business', 'slug' => 'business', 'color' => '#0047B6', 'description' => 'The port, the trades and the shops: how Halifax makes its living.'],
        ['name' => 'Sports', 'slug' => 'sports', 'color' => '#0047B6', 'description' => 'The city\'s teams and athletes, from the junior rinks to the pitch.'],
        ['name' => 'Opinion', 'slug' => 'opinion', 'color' => '#0047B6', 'description' => 'Signed columns and letters, always labelled as opinion.'],
    ],

    'settings' => [
        'site_title' => 'Citadel Courier',
        'tagline' => 'Halifax • Nova Scotia',
        'meta_description' => 'Citadel Courier covers Halifax and Nova Scotia: the city, council, Province House, the port and the people around the harbour.',
        'footer_line' => 'News that matters, from where you live.',
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
