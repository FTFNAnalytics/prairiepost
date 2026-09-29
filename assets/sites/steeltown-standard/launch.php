<?php
/**
 * The Steeltown Standard — launch pack (foundation build).
 * Seeded once by `PP_SITE=steeltown-standard php tools/seed-launch.php`.
 *
 * ZERO STORIES BY DESIGN — identity, desks and wire sources only; the
 * front page carries its empty state until the newsroom files. The
 * brand package replaces the foundation palette and wordmark before
 * any editorial launch, and the six-story inaugural edition is written
 * at brand-build time, never earlier.
 *
 * "Steeltown" is Hamilton's own name for itself — the harbour, the
 * mills, and the escarpment neighbourhoods above them. The paper is
 * Hamilton's standard-bearer on the record.
 *
 * Domain: steeltownstandard.ca — OWNER-CONFIRMED registered (29 Sep).
 *
 * Desk self-sufficiency: every desk listed here already exists
 * network-wide, but all are listed so the pack stands alone
 * regardless of seed order (the seeder creates only what's missing).
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['steeltownstandard.ca', 'www.steeltownstandard.ca'],

    'desks' => [
        ['name' => 'Local News', 'slug' => 'local-news', 'color' => '#7A3B2E', 'description' => 'Hamilton from the harbour to the mountain: neighbourhoods, housing, and the people who make the city run.'],
        ['name' => 'City Hall',  'slug' => 'city-hall',  'color' => '#7A3B2E', 'description' => 'Council, the boards and the budget, read from the record.'],
        ['name' => 'Business',   'slug' => 'business',   'color' => '#7A3B2E', 'description' => 'The port, the plants, the main streets — the working ledger of a working city.'],
        ['name' => 'Sports',     'slug' => 'sports',     'color' => '#7A3B2E', 'description' => 'The Ticats, the Bulldogs, and every rink, diamond and pitch from the bayfront up.'],
        ['name' => 'Opinion',    'slug' => 'opinion',    'color' => '#7A3B2E', 'description' => 'Signed columns and letters, always labelled. The editorial position is the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'The Steeltown Standard',
        'tagline'            => 'Hamilton, held to a standard',
        'meta_description'   => 'The Steeltown Standard is an independent digital newsroom for Hamilton — city hall, the harbour, the mountain and the neighbourhoods, reported on the record.',
        'footer_line'        => 'Independent news for Hamilton, from the bayfront to the brow.',
        'contact_email'      => 'tips@steeltownstandard.ca',
        'newsletter_heading' => 'The Morning Pour',
        'newsletter_copy'    => 'Hamilton\'s news every weekday morning — what the city decided, built and argued about, before the first shift ends.',
        'weather_line'       => '11°C|Harbour haze',
        'regions'            => json_encode([
            'hamilton' => 'Hamilton',
            'ontario'  => 'Ontario',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['CBC Hamilton',  'https://www.cbc.ca/webfeed/rss/rss-canada-hamiltonnews', 'hamilton'],
        ['inTheHammer',   'https://www.inthehammer.com/feed/',                      'hamilton'],
        ['Global Toronto','https://globalnews.ca/toronto/feed/',                    'ontario'],
    ],

    /* Intentionally empty — see the header. */
    'stories' => [],
];
