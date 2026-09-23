<?php
/**
 * The Cariboo Compass — launch pack (brand build, Sep 2026).
 * Seeded once by `PP_SITE=cariboo-compass php tools/seed-launch.php`.
 *
 * ZERO STORIES BY DESIGN — identity, desks and wire sources only; the
 * front page carries its empty state until the newsroom files.
 *
 * Identity from the owner's brand package: the gold compass rose under
 * the peaks on Deep Forest Green; serif headlines over a sans body;
 * Compass Gold for accents, Lake Blue for links. "Rugged BC wilderness
 * meets trustworthy journalism" — North & Central BC.
 *
 * Desk self-sufficiency: local-news, business, education, environment
 * and opinion exist network-wide (education arrived with Surrey,
 * environment with Burrard); `communities` is new and is created on
 * the first seed. All six are listed so the pack stands alone.
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['cariboocompass.ca', 'www.cariboocompass.ca'],

    'desks' => [
        ['name' => 'Local News',  'slug' => 'local-news',  'color' => '#1A3C34', 'description' => 'North and Central BC, reported from here — Prince George to 100 Mile House, with direction, integrity and care.'],
        ['name' => 'Communities', 'slug' => 'communities', 'color' => '#1A3C34', 'description' => 'Williams Lake, Quesnel, the Chilcotin and every town along the highway — the people and places that hold the region together.'],
        ['name' => 'Business',    'slug' => 'business',    'color' => '#1A3C34', 'description' => 'Mills, mines, main streets and the jobs that ride on them.'],
        ['name' => 'Environment', 'slug' => 'environment', 'color' => '#C9A227', 'description' => 'Wildfire seasons, the lakes and rivers, and the backcountry the Cariboo lives beside.'],
        ['name' => 'Education',   'slug' => 'education',   'color' => '#1A3C34', 'description' => 'Schools, the college and the university of the north.'],
        ['name' => 'Opinion',     'slug' => 'opinion',     'color' => '#1A3C34', 'description' => 'Signed columns and letters, always labelled. The editorial position is the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'The Cariboo Compass',
        'tagline'            => 'Cariboo News. Community First.',
        'meta_description'   => 'The Cariboo Compass covers North and Central BC — Prince George to the Chilcotin — with clear, accurate local reporting you can trust. Your true north for local news.',
        'footer_line'        => 'Covering the Cariboo. Connecting our communities.',
        'contact_email'      => 'tips@cariboocompass.ca',
        'newsletter_heading' => 'The Morning Bearing',
        'newsletter_copy'    => 'North and Central BC\'s news every weekday morning: what happened, what it means, and what to watch — your true north before the day starts.',
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
