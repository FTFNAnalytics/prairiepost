<?php
/**
 * The Pickering Post — launch package.
 * Loaded once by `PP_SITE=pickering-post php tools/seed-launch.php`.
 *
 * This pack seeds NO STORIES, deliberately — the same shape as Turtle
 * Island. Editorial comes from the newsroom, so the seeder sets up the
 * paper's identity, its desks and its wire sources, and the first story
 * published here is a real one.
 *
 * The 'stories' key is absent rather than empty: the seeder iterates
 * `$pack['stories'] ?? []`, so leaving it out is the same as an empty list
 * and says plainly that the omission is intended. It also means this
 * deployment cannot hit the network-wide `posts.slug` uniqueness constraint
 * at all.
 *
 * All eight desks the package names are listed, so the pack stands on its
 * own — the seeder creates only what the shared database is missing and
 * reuses by slug anything a sister paper already seeded. On a network that
 * already runs eleven papers, expect several to be reused rather than added.
 */

return [

    'desks' => [
        ['name' => 'Local News',  'slug' => 'local-news',  'color' => '#0088B0', 'description' => 'Council, the waterfront, the schools, and what happened overnight.'],
        ['name' => 'Community',   'slug' => 'community',   'color' => '#0088B0', 'description' => 'The people, clubs and volunteers who make the place work.'],
        ['name' => 'Events',      'slug' => 'events',      'color' => '#0088B0', 'description' => "What's on this week, from the band shell to the farmers' market."],
        ['name' => 'Sports',      'slug' => 'sports',      'color' => '#0088B0', 'description' => 'Minor hockey, the high schools, and the clubs on the lake.'],
        ['name' => 'Business',    'slug' => 'business',    'color' => '#0088B0', 'description' => 'Main streets, industrial parks, and the people hiring.'],
        ['name' => 'Opinion',     'slug' => 'opinion',     'color' => '#0088B0', 'description' => 'Argument and comment, clearly marked as such.'],
        ['name' => 'Obituaries',  'slug' => 'obituaries',  'color' => '#605D5D', 'description' => 'Lives remembered, printed as families send them.'],
        ['name' => 'Breaking',    'slug' => 'breaking',    'color' => '#D6006C', 'description' => 'Stories that are still moving.'],
    ],

    'settings' => [
        'site_title'         => 'The Pickering Post',
        'tagline'            => "Durham Region's daily",
        'meta_description'   => 'Local news for Pickering and Durham Region: council, the waterfront, community, events, sports and business.',
        'footer_line'        => "Durham Region's daily",
        'contact_email'      => 'newsroom@pickeringpost.ca',
        'newsletter_heading' => 'The morning email',
        'newsletter_copy'    => 'Six stories from Pickering in your inbox by seven. Free, and short enough for the GO train.',
        'breaking_label'     => '',
        'breaking_url'       => '',
        'regions'            => json_encode([
            'pickering' => 'Pickering',
            'durham'    => 'Durham Region',
            'ontario'   => 'Ontario',
        ]),
    ],

    /**
     * Wire sources for the newsroom's morning pull. These fill the dashboard's
     * story-idea feed; they publish nothing on their own. The newsroom decides
     * what, if anything, becomes a story.
     */
    'sources' => [
        ['CBC Toronto',   'https://www.cbc.ca/webfeed/rss/rss-canada-toronto', 'durham'],
        ['TVO Today',     'https://www.tvo.org/feeds/rss/all',                 'ontario'],
        ['CBC Canada',    'https://www.cbc.ca/webfeed/rss/rss-canada',         'ontario'],
    ],
];
