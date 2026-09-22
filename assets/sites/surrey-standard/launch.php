<?php
/**
 * Surrey Standard — launch pack (foundation build).
 * Seeded once by `PP_SITE=surrey-standard php tools/seed-launch.php`.
 *
 * ZERO STORIES BY DESIGN — identity, desks and wire sources only; the
 * front page carries its empty state until the newsroom files. The
 * brand package replaces the foundation palette and wordmark before
 * any editorial launch.
 *
 * Desk self-sufficiency: local-news, city-hall, development, sports and
 * opinion exist network-wide; `education` is new and is created on the
 * first seed. All six are listed here so the pack stands alone.
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['surreystandard.ca', 'www.surreystandard.ca'],

    'desks' => [
        ['name' => 'Local News',  'slug' => 'local-news',  'color' => '#1B2A4A', 'description' => 'Surrey and White Rock, reported from the ground — six town centres, one newsroom.'],
        ['name' => 'City Hall',   'slug' => 'city-hall',   'color' => '#1B2A4A', 'description' => 'Council, the budget and the police transition file, from the agenda forward.'],
        ['name' => 'Development', 'slug' => 'development', 'color' => '#1B2A4A', 'description' => 'The fastest-growing city in British Columbia, one rezoning at a time.'],
        ['name' => 'Education',   'slug' => 'education',   'color' => '#1B2A4A', 'description' => 'The province\'s largest school district: enrolment, portables, and the capital plan.'],
        ['name' => 'Sports',      'slug' => 'sports',      'color' => '#1B2A4A', 'description' => 'From the Eagles to the leagues that play on Newton\'s fields.'],
        ['name' => 'Opinion',     'slug' => 'opinion',     'color' => '#1B2A4A', 'description' => 'Signed columns and letters, always labelled. The editorial position is the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'Surrey Standard',
        'tagline'            => 'South of the Fraser, on the record',
        'meta_description'   => 'Surrey Standard is the paper of record south of the Fraser — council, growth, schools and the daily life of British Columbia\'s fastest-growing city.',
        'footer_line'        => 'The paper of record south of the Fraser. Independent, and free to read.',
        'contact_email'      => 'tips@surreystandard.ca',
        'newsletter_heading' => 'The Morning Standard',
        'newsletter_copy'    => 'What Surrey decided, approved and built yesterday — in your inbox every weekday morning.',
        'weather_line'       => '16°C|Bear Creek',
        'regions'            => json_encode([
            'surrey' => 'Surrey',
            'bc'     => 'British Columbia',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['Surrey Now-Leader', 'https://www.surreynowleader.com/feed',  'surrey'],
        ['Peace Arch News',   'https://www.peacearchnews.com/feed',    'surrey'],
        ['Global BC',         'https://globalnews.ca/bc/feed/',        'bc'],
    ],

    /* Intentionally empty — see the header. */
    'stories' => [],
];
