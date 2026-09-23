<?php
/**
 * The Burrard Brief — launch pack (brand build, Sep 2026).
 * Seeded once by `PP_SITE=burrard-brief php tools/seed-launch.php`.
 *
 * ZERO STORIES BY DESIGN — identity, desks and wire sources only; the
 * front page carries its empty state until the newsroom files.
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

    /* Intentionally empty — see the header. */
    'stories' => [],
];
