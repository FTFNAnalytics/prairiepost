<?php
/**
 * The Bison Bulletin — FOUNDATION launch pack (Sep 2026).
 * Seeded once by `PP_SITE=bison-bulletin php tools/seed-launch.php`.
 *
 * FOUNDATION ONLY: the paper is scaffolded and functional but carries
 * placeholder identity — the owner's brand package (palette, marks,
 * typography, tagline, footprint) replaces the visual foundation and
 * this pack's voice before any launch. ZERO stories: the inaugural
 * service edition (six launch notes about the paper itself, true by
 * construction — see the Surrey/Cariboo/Burrard/Rideau packs for the
 * pattern) is written at brand-build time, never invented earlier.
 *
 * Manitoba footprint to confirm with the owner at brand time — the
 * bison is the provincial emblem, so this masthead reads province-wide
 * as naturally as city-wide; whether it is the Winnipeg paper or the
 * wider-Manitoba paper is the owner's call alongside the Red River
 * Register's valley footprint.
 *
 * Desk self-sufficiency: every desk below already exists network-wide;
 * all are listed so the pack stands alone regardless of seed order.
 */

return [

    /* CONFIRM THE REGISTERED DOMAIN before launch — the Burrard lesson:
       the sheet said theburrardbrief.ca, the registration was
       burrardbrief.ca. The seeder writes these into the domains table,
       which bootstrap resolves tenants from. */
    'domains' => ['bisonbulletin.ca', 'www.bisonbulletin.ca'],

    'desks' => [
        ['name' => 'Local News',  'slug' => 'local-news',  'color' => '#20242B', 'description' => 'Manitoba\'s news, reported from here.'],
        ['name' => 'City Hall',   'slug' => 'city-hall',   'color' => '#20242B', 'description' => 'Council decisions and the record behind them.'],
        ['name' => 'Communities', 'slug' => 'communities', 'color' => '#20242B', 'description' => 'The neighbourhoods and towns that make the province.'],
        ['name' => 'Business',    'slug' => 'business',    'color' => '#20242B', 'description' => 'The economy at street level.'],
        ['name' => 'Sports',      'slug' => 'sports',      'color' => '#20242B', 'description' => 'Local leagues, rinks and school teams.'],
        ['name' => 'Opinion',     'slug' => 'opinion',     'color' => '#20242B', 'description' => 'Signed columns and letters, always labelled.'],
    ],

    'settings' => [
        'site_title'       => 'The Bison Bulletin',
        /* Placeholder until the brand package names the real line. */
        'tagline'          => 'Manitoba news, herd first.',
        'meta_description' => 'The Bison Bulletin — independent local news for Manitoba.',
        'footer_line'      => 'Independent local news for Manitoba.',
        'contact_email'    => 'tips@bisonbulletin.ca',
        'weather_line'     => '2°C|The Forks',
        'regions'          => json_encode([
            'winnipeg' => 'Winnipeg',
            'manitoba' => 'Manitoba',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['CBC Manitoba',    'https://www.cbc.ca/webfeed/rss/rss-canada-manitoba', 'manitoba'],
        ['Global Winnipeg', 'https://globalnews.ca/winnipeg/feed/',               'winnipeg'],
    ],

    /* Intentionally empty — see the header. */
    'stories' => [],
];
