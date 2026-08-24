<?php
/**
 * Turtle Island Times — launch package.
 * Loaded once by `PP_SITE=turtle-island-times php tools/seed-launch.php`.
 *
 * This pack seeds NO STORIES, deliberately. Editorial for this paper comes
 * from the newsroom, not from the launch pack, so the seeder sets up the
 * paper's identity, its desks and its wire sources, and the first story
 * published here is a real one.
 *
 * The 'stories' key is absent rather than empty: the seeder iterates
 * `$pack['stories'] ?? []`, so leaving it out is the same as an empty list and
 * says plainly that the omission is intended.
 *
 * Every desk the paper uses is listed below, so the pack stands on its own —
 * the seeder creates only what the shared database is missing, and reuses by
 * slug anything a sister paper already seeded.
 */

return [

    'desks' => [
        ['name' => 'News',        'slug' => 'news',       'color' => '#004961', 'description' => 'What happened, across the territories.'],
        ['name' => 'Land & Water','slug' => 'land-water', 'color' => '#0088B0', 'description' => 'Rivers, fisheries, forestry and the agreements that govern them.'],
        ['name' => 'Language',    'slug' => 'language',   'color' => '#A8451F', 'description' => 'Speakers, classrooms, and the work of carrying a language forward.'],
        ['name' => 'Culture',     'slug' => 'culture',    'color' => '#0A303E', 'description' => 'Art, ceremony, repatriation and the people doing the work.'],
        ['name' => 'Governance',  'slug' => 'governance', 'color' => '#006786', 'description' => 'Councils, negotiations, and decisions taken on behalf of communities.'],
    ],

    'settings' => [
        'site_title'         => 'Turtle Island Times',
        'tagline'            => 'Independent news from across the territories',
        'meta_description'   => 'Independent news from across the territories: land and water, language, culture and governance.',
        'footer_line'        => 'Independent news from across the territories',
        'contact_email'      => 'contact@turtleislandtimes.ca',
        'newsletter_heading' => 'The morning brief',
        'newsletter_copy'    => 'One email, weekday mornings, five minutes.',
        'breaking_label'     => '',
        'breaking_url'       => '',
        'regions'            => json_encode([
            'territories' => 'The territories',
            'national'    => 'National',
        ]),
    ],

    /**
     * Wire sources for the newsroom's morning pull. These populate the
     * dashboard's story-idea feed; they do not publish anything on their own.
     * The newsroom decides what, if anything, becomes a story.
     */
    'sources' => [
        ['APTN News',          'https://www.aptnnews.ca/feed/',                          'territories'],
        ['CBC Indigenous',     'https://www.cbc.ca/webfeed/rss/rss-canada-indigenous',   'national'],
        ['IndigiNews',         'https://indiginews.com/feed',                            'territories'],
        ['Ku\'ku\'kwes News',  'https://kukukwes.com/feed/',                             'territories'],
        ['Windspeaker',        'https://windspeaker.com/rss.xml',                        'national'],
    ],
];
