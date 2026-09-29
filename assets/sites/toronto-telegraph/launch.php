<?php
/**
 * The Toronto Telegraph — launch pack (foundation build).
 * Seeded once by `PP_SITE=toronto-telegraph php tools/seed-launch.php`.
 *
 * ZERO STORIES BY DESIGN — identity, desks and wire sources only; the
 * front page carries its empty state until the newsroom files. The
 * brand package replaces the foundation palette and wordmark before
 * any editorial launch, and the six-story inaugural edition is written
 * at brand-build time, never earlier.
 *
 * The telegraph is the fastest wire of its century, and the name sets
 * the paper's promise: Toronto's news, on the wire, on the record.
 *
 * Domain: torontotelegraph.ca — OWNER-CONFIRMED registered (29 Sep).
 *
 * Desk self-sufficiency: every desk listed here already exists
 * network-wide (`arts` was created 29 Sep by Portage Press's first
 * seed), but all are listed so the pack stands alone regardless of
 * seed order (the seeder creates only what's missing).
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['torontotelegraph.ca', 'www.torontotelegraph.ca'],

    'desks' => [
        ['name' => 'Local News', 'slug' => 'local-news', 'color' => '#1E3A5F', 'description' => 'Toronto street by street: neighbourhoods, transit, housing, and the people the city is built of.'],
        ['name' => 'City Hall',  'slug' => 'city-hall',  'color' => '#1E3A5F', 'description' => 'Council, the agencies and the budget, read from the record.'],
        ['name' => 'Business',   'slug' => 'business',   'color' => '#1E3A5F', 'description' => 'Bay Street to the main streets — the money, the towers, and who they work for.'],
        ['name' => 'Arts',       'slug' => 'arts',       'color' => '#1E3A5F', 'description' => 'Stages, screens, galleries and the writers\' rooms of a culture capital.'],
        ['name' => 'Sports',     'slug' => 'sports',     'color' => '#1E3A5F', 'description' => 'The Leafs, the Raptors, the Jays, the TFC — and every rink and court in between.'],
        ['name' => 'Opinion',    'slug' => 'opinion',    'color' => '#1E3A5F', 'description' => 'Signed columns and letters, always labelled. The editorial position is the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'The Toronto Telegraph',
        'tagline'            => 'Toronto, on the wire',
        'meta_description'   => 'The Toronto Telegraph is an independent digital newsroom for Toronto — city hall, the neighbourhoods, business, arts and sports, reported on the record.',
        'footer_line'        => 'Independent news for Toronto, on the wire and on the record.',
        'contact_email'      => 'tips@torontotelegraph.ca',
        'newsletter_heading' => 'The Morning Wire',
        'newsletter_copy'    => 'Toronto\'s news every weekday morning — what the city decided, built and argued about, before the first streetcar is full.',
        'weather_line'       => '13°C|Lake breeze',
        'regions'            => json_encode([
            'toronto' => 'Toronto',
            'ontario' => 'Ontario',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['CBC Toronto',    'https://www.cbc.ca/webfeed/rss/rss-canada-toronto', 'toronto'],
        ['Global Toronto', 'https://globalnews.ca/toronto/feed/',               'toronto'],
        ['blogTO',         'https://www.blogto.com/rss',                        'toronto'],
    ],

    /* Intentionally empty — see the header. */
    'stories' => [],
];
