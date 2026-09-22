<?php
/**
 * Terminal City Times — launch pack (foundation build).
 * Seeded once by `PP_SITE=terminal-city-times php tools/seed-launch.php`.
 *
 * ZERO STORIES BY DESIGN — identity, desks and wire sources only; the
 * front page carries its empty state until the newsroom files. The
 * brand package replaces the foundation palette and wordmark before
 * any editorial launch.
 *
 * "Terminal City" is Vancouver's railway-era nickname: the end of
 * steel, where the line meets the Pacific. The paper is the city's
 * broadsheet of record; The Burrard Brief is its quick-read sibling.
 *
 * Desk self-sufficiency: local-news, city-hall, business, sports and
 * opinion exist network-wide; `arts` is new and is created on the
 * first seed. All six are listed so the pack stands alone.
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['terminalcitytimes.ca', 'www.terminalcitytimes.ca'],

    'desks' => [
        ['name' => 'Local News', 'slug' => 'local-news', 'color' => '#8C2F1B', 'description' => 'Vancouver from the waterfront up: neighbourhoods, housing, and the people the city is built of.'],
        ['name' => 'City Hall',  'slug' => 'city-hall',  'color' => '#8C2F1B', 'description' => 'Council, the park board and the budget, read from the record.'],
        ['name' => 'Business',   'slug' => 'business',   'color' => '#8C2F1B', 'description' => 'The port, the towers, and the ledger of a trading city.'],
        ['name' => 'Arts',       'slug' => 'arts',       'color' => '#8C2F1B', 'description' => 'Stages, galleries, screens and the writers\' rooms of the West Coast.'],
        ['name' => 'Sports',     'slug' => 'sports',     'color' => '#8C2F1B', 'description' => 'The Canucks, the Lions, the Whitecaps, and every rink and pitch in between.'],
        ['name' => 'Opinion',    'slug' => 'opinion',    'color' => '#8C2F1B', 'description' => 'Signed columns and letters, always labelled. The editorial position is the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'Terminal City Times',
        'tagline'            => 'News from the end of steel',
        'meta_description'   => 'Terminal City Times is Vancouver\'s broadsheet of record — city hall, the port, the arts and the neighbourhoods, reported where the line meets the Pacific.',
        'footer_line'        => 'Vancouver\'s broadsheet of record, printed where the line ends.',
        'contact_email'      => 'tips@terminalcitytimes.ca',
        'newsletter_heading' => 'The Morning Departure',
        'newsletter_copy'    => 'The Times\' front page in your inbox every weekday morning — what Vancouver decided, built and argued about, before the first SeaBus sails.',
        'weather_line'       => '15°C|Waterfront',
        'regions'            => json_encode([
            'vancouver' => 'Vancouver',
            'bc'        => 'British Columbia',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['The Tyee',             'https://thetyee.ca/rss2.xml',            'vancouver'],
        ['Vancouver Is Awesome', 'https://www.vancouverisawesome.com/rss', 'vancouver'],
        ['Global BC',            'https://globalnews.ca/bc/feed/',         'bc'],
    ],

    /* Intentionally empty — see the header. */
    'stories' => [],
];
