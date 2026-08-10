<?php
/**
 * The Grande Prairie Gazette — launch package.
 * Loaded once by `PP_SITE=grande-prairie-gazette php tools/seed-launch.php`.
 * Identity, rails and wire sources for the aurora design; stories are the
 * newsroom's to file (syndicate from the network or write fresh).
 */

return [

    'desks' => [
        ['name' => 'Energy', 'slug' => 'energy', 'color' => '#1E3A6E', 'description' => 'Montney gas, the rigs, the royalties, and the towns they pay for.'],
        ['name' => 'Sports', 'slug' => 'sports', 'color' => '#1D5C8C', 'description' => 'The rinks, the diamonds, and who pays for the ice.'],
    ],

    'settings' => [
        'site_title'         => 'Grande Prairie Gazette',
        'tagline'            => "Peace Country's daily",
        'meta_description'   => 'Independent local reporting for Grande Prairie, the County and the Peace Country: council, courts, energy, agriculture and the games.',
        'footer_line'        => 'Independent local reporting for Grande Prairie, the County and the Peace Country.',
        'weather_line'       => 'Grande Prairie, AB · 21°C, clear',
        'contact_email'      => 'tips@grandeprairiegazette.ca',
        'newsletter_heading' => 'The Morning Aurora',
        'newsletter_copy'    => 'Everything that happened in Grande Prairie, in your inbox by 6 a.m. Free, five days a week.',
        'regions'            => json_encode([
            'peace'   => 'Peace Country',
            'alberta' => 'Alberta',
            'canada'  => 'Canada',
        ]),
        'events_items'       => json_encode([
            ['Bear Creek Folk Festival opens', '#', 'Aug 13', 'Muskoseepi Park · 5 p.m.'],
            ['Council committee: transit review', '#', 'Aug 15', 'City Hall · 9 a.m.'],
            ["Farmers' market, last summer date", '#', 'Aug 17', 'Montrose Cultural Centre · 9 a.m.'],
        ]),
    ],

    'sources' => [
        ['EverythingGP',          'https://everythinggp.com/feed/',                     'peace'],
        ['My Grande Prairie Now', 'https://www.mygrandeprairienow.com/feed/',           'peace'],
        ['Fairview Post',         'https://www.fairviewpost.com/category/news/feed',    'peace'],
    ],

    'stories' => [],
];
