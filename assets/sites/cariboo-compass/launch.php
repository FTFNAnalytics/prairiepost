<?php
/**
 * Cariboo Compass — launch pack (foundation build).
 * Seeded once by `PP_SITE=cariboo-compass php tools/seed-launch.php`.
 *
 * ZERO STORIES BY DESIGN — identity, desks and wire sources only; the
 * front page carries its empty state until the newsroom files. The
 * brand package replaces the foundation palette and wordmark before
 * any editorial launch.
 *
 * Desk self-sufficiency: local-news, business and sports exist
 * network-wide; `resources` and `outdoors` are new to the network and
 * are created on the first seed. All five are listed so the pack
 * stands alone against an empty database.
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['cariboocompass.ca', 'www.cariboocompass.ca'],

    'desks' => [
        ['name' => 'Local News', 'slug' => 'local-news', 'color' => '#2F4A33', 'description' => 'Prince George, Quesnel, Williams Lake and 100 Mile House — the Interior\'s news, reported from the Interior.'],
        ['name' => 'Resources',  'slug' => 'resources',  'color' => '#2F4A33', 'description' => 'Forestry, mills, mines and the jobs that ride on them. The file that decides the region\'s decade.'],
        ['name' => 'Business',   'slug' => 'business',   'color' => '#2F4A33', 'description' => 'Main street and the industrial park: who is hiring, expanding and closing.'],
        ['name' => 'Outdoors',   'slug' => 'outdoors',   'color' => '#2F4A33', 'description' => 'Rivers, ranges, wildfire seasons and the backcountry the Cariboo lives beside.'],
        ['name' => 'Sports',     'slug' => 'sports',     'color' => '#2F4A33', 'description' => 'The Cougars, the ice, and every rink from Mackenzie to the Chilcotin.'],
    ],

    'settings' => [
        'site_title'         => 'Cariboo Compass',
        'tagline'            => 'True north for the Interior',
        'meta_description'   => 'Cariboo Compass covers British Columbia\'s northern Interior — Prince George to the Chilcotin — with news that gets its bearings from the region, not the coast.',
        'footer_line'        => 'Reported from the Interior, for the Interior. Independent and free to read.',
        'contact_email'      => 'tips@cariboocompass.ca',
        'newsletter_heading' => 'The Morning Bearing',
        'newsletter_copy'    => 'The Interior\'s news every weekday morning: what happened, what it means north of the Fraser Canyon, and what to watch today.',
        'weather_line'       => '9°C|Nechako',
        'regions'            => json_encode([
            'prince-george' => 'Prince George',
            'cariboo'       => 'The Cariboo',
            'bc'            => 'British Columbia',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['Prince George Citizen',  'https://www.princegeorgecitizen.com/rss', 'prince-george'],
        ['Williams Lake Tribune',  'https://www.wltribune.com/feed',          'cariboo'],
        ['Global BC',              'https://globalnews.ca/bc/feed/',          'bc'],
    ],

    /* Intentionally empty — see the header. */
    'stories' => [],
];
