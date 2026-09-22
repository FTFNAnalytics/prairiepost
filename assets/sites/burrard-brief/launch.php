<?php
/**
 * The Burrard Brief — launch pack (foundation build).
 * Seeded once by `PP_SITE=burrard-brief php tools/seed-launch.php`.
 *
 * ZERO STORIES BY DESIGN. This pack ships identity, desks and wire
 * sources only — like Turtle Island and Pickering before it, the front
 * page carries the empty state until the newsroom files. The brand
 * package replaces the foundation palette and wordmark before any
 * editorial launch; no demonstration content is added at this layer.
 *
 * Desk self-sufficiency: every desk this paper uses is listed here with
 * its own definition. local-news, city-hall, transit, business and
 * opinion already exist network-wide, so a correct run against the full
 * network prints no "desk added" lines for them; `culture` is new and
 * will be created on first seed.
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['burrardbrief.ca', 'www.burrardbrief.ca'],

    'desks' => [
        ['name' => 'Local News', 'slug' => 'local-news', 'color' => '#0E5A8A', 'description' => 'Vancouver, block by block — neighbourhoods, housing, policing and what changed overnight.'],
        ['name' => 'City Hall',  'slug' => 'city-hall',  'color' => '#0E5A8A', 'description' => 'Council, the park board and the budget, reported from the agenda not the press release.'],
        ['name' => 'Transit',    'slug' => 'transit',    'color' => '#0E5A8A', 'description' => 'SkyTrain, buses, the Broadway line and every way the city moves.'],
        ['name' => 'Business',   'slug' => 'business',   'color' => '#0E5A8A', 'description' => 'The port, the towers and the storefronts — who is hiring, building and closing.'],
        ['name' => 'Culture',    'slug' => 'culture',    'color' => '#0E5A8A', 'description' => 'Food, film, music and the rooms where the city spends its evenings.'],
        ['name' => 'Opinion',    'slug' => 'opinion',    'color' => '#0E5A8A', 'description' => 'Signed columns and letters, always labelled as such.'],
    ],

    'settings' => [
        'site_title'         => 'The Burrard Brief',
        'tagline'            => 'Vancouver, in brief',
        'meta_description'   => 'The Burrard Brief is Vancouver\'s quick, complete daily read — the city\'s news in minutes, reported plainly and linked to the record.',
        'footer_line'        => 'Vancouver\'s daily brief — short by design, complete by principle.',
        'contact_email'      => 'tips@burrardbrief.ca',
        'newsletter_heading' => 'The First Brief',
        'newsletter_copy'    => 'Every weekday at 6 a.m.: everything Vancouver decided yesterday and everything worth watching today, in one brief you can finish before the coffee.',
        'weather_line'       => '14°C|English Bay',
        'regions'            => json_encode([
            'vancouver' => 'Vancouver',
            'bc'        => 'British Columbia',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['Daily Hive Vancouver',  'https://dailyhive.com/feed/vancouver',       'vancouver'],
        ['Vancouver Is Awesome',  'https://www.vancouverisawesome.com/rss',     'vancouver'],
        ['North Shore News',      'https://www.nsnews.com/rss',                 'vancouver'],
        ['Global BC',             'https://globalnews.ca/bc/feed/',             'bc'],
    ],

    /* Intentionally empty — see the header. */
    'stories' => [],
];
