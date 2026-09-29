<?php
/**
 * The Red River Register — FOUNDATION launch pack (Sep 2026).
 * Seeded once by `PP_SITE=red-river-register php tools/seed-launch.php`.
 *
 * FOUNDATION ONLY: the paper is scaffolded and functional but carries
 * placeholder identity — the owner's brand package (palette, marks,
 * typography, tagline, footprint) replaces the visual foundation and
 * this pack's voice before any launch. ZERO stories: the inaugural
 * service edition (six launch notes about the paper itself, true by
 * construction — see the Surrey/Cariboo/Burrard/Rideau packs for the
 * pattern) is written at brand-build time, never invented earlier.
 *
 * Manitoba footprint to confirm with the owner at brand time: the
 * working assumption is the Red River Valley beyond Winnipeg proper
 * (Selkirk, Steinbach, Morris, Emerson, the rural municipalities),
 * with a Winnipeg sister paper covering the city.
 *
 * Desk self-sufficiency: every desk below already exists network-wide;
 * all are listed so the pack stands alone regardless of seed order.
 */

return [

    /* CONFIRM THE REGISTERED DOMAIN before launch — the Burrard lesson:
       the sheet said theburrardbrief.ca, the registration was
       burrardbrief.ca. The seeder writes these into the domains table,
       which bootstrap resolves tenants from. */
    'domains' => ['redriverregister.ca', 'www.redriverregister.ca'],

    'desks' => [
        ['name' => 'Local News',  'slug' => 'local-news',  'color' => '#20242B', 'description' => 'The valley\'s news, reported from here.'],
        ['name' => 'Communities', 'slug' => 'communities', 'color' => '#20242B', 'description' => 'The towns and municipalities along the river.'],
        ['name' => 'Business',    'slug' => 'business',    'color' => '#20242B', 'description' => 'Farms, main streets and the jobs that ride on them.'],
        ['name' => 'Sports',      'slug' => 'sports',      'color' => '#20242B', 'description' => 'Local leagues, rinks and school teams.'],
        ['name' => 'Opinion',     'slug' => 'opinion',     'color' => '#20242B', 'description' => 'Signed columns and letters, always labelled.'],
    ],

    'settings' => [
        'site_title'       => 'The Red River Register',
        /* Placeholder until the brand package names the real line. */
        'tagline'          => 'News for the Red River Valley.',
        'meta_description' => 'The Red River Register — independent local news for the Red River Valley, Manitoba.',
        'footer_line'      => 'Independent local news for the Red River Valley.',
        'contact_email'    => 'tips@redriverregister.ca',
        'weather_line'     => '3°C|Red River',
        'regions'          => json_encode([
            'red-river-valley' => 'Red River Valley',
            'manitoba'         => 'Manitoba',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['CBC Manitoba',   'https://www.cbc.ca/webfeed/rss/rss-canada-manitoba', 'manitoba'],
        ['Global Winnipeg', 'https://globalnews.ca/winnipeg/feed/',              'manitoba'],
    ],

    /* Intentionally empty — see the header. */
    'stories' => [],
];
