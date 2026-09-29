<?php
/**
 * The Brandon Bulletin — launch pack (brand build, Sep 2026).
 * Seeded once by `PP_SITE=brandon-bulletin php tools/seed-launch.php`.
 *
 * Identity from the owner's brand package: Brandon Gold #E6BF2E over
 * white with black and cool gray, Light Wheat #F7E5AD as the accent
 * well; the wheat-over-open-book badge (traced two-color from the
 * package); a serif nameplate over clean sans headlines. The
 * package's lines: "News with heart. Rooted in place." (tagline),
 * "Trusted. Local. Rooted in the Prairie.", "Prairie values. Local
 * stories. Lasting impact." Footprint: Brandon — the Wheat City —
 * and Southwest Manitoba, alongside the Bison Bulletin (province)
 * and the Red River Register (the valley).
 *
 * INAUGURAL SERVICE CONTENT ONLY — six launch notes ABOUT the paper
 * (mission, desk methods, how to reach the newsroom), true by
 * construction; no invented local events, votes or figures (CLAUDE.md
 * debt item 1 must not grow with launches). Real reporting arrives
 * through the newsroom or the ingest lanes.
 *
 * Desk self-sufficiency: all four desks exist network-wide; all are
 * listed so the pack stands alone regardless of seed order. The nav
 * follows the package's mockup (Local News, Sports, Community,
 * Opinion — Weather is the rail box, not a desk).
 */

return [

    /* CONFIRM THE REGISTERED DOMAIN before launch (the Burrard lesson). */
    'domains' => ['brandonbulletin.ca', 'www.brandonbulletin.ca'],

    'desks' => [
        ['name' => 'Local News', 'slug' => 'local-news', 'color' => '#E6BF2E', 'description' => 'Brandon and Southwest Manitoba, reported from here — with heart, on the record.'],
        ['name' => 'Sports',     'slug' => 'sports',     'color' => '#E6BF2E', 'description' => 'The Wheat City\'s rinks, diamonds and courts, and the southwest\'s teams on the road.'],
        ['name' => 'Community',  'slug' => 'community',  'color' => '#E6BF2E', 'description' => 'The people, halls and main streets that hold the southwest together.'],
        ['name' => 'Opinion',    'slug' => 'opinion',    'color' => '#E6BF2E', 'description' => 'Signed columns and letters, always labelled. The editorial position is the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'The Brandon Bulletin',
        'tagline'            => 'News with heart. Rooted in place.',
        'meta_description'   => 'The Brandon Bulletin is an independent digital newsroom for Brandon and Southwest Manitoba. Trusted. Local. Rooted in the Prairie.',
        'footer_line'        => 'Independent news for Brandon and Southwest Manitoba. Trusted. Local. Rooted in the Prairie.',
        'contact_email'      => 'tips@brandonbulletin.ca',
        'newsletter_heading' => 'The Wheat City Brief',
        'newsletter_copy'    => 'Brandon and the southwest\'s news every weekday morning — what happened, what it means, and what to watch, before the day gets going.',
        'weather_line'       => '4°C|Sunny',
        'regions'            => json_encode([
            'brandon'            => 'Brandon',
            'southwest-manitoba' => 'Southwest Manitoba',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['CBC Manitoba',    'https://www.cbc.ca/webfeed/rss/rss-canada-manitoba', 'southwest-manitoba'],
        ['Global Winnipeg', 'https://globalnews.ca/winnipeg/feed/',               'southwest-manitoba'],
    ],

    /* Inaugural service content only (see the header). */
    'stories' => [
        [
            'title'     => 'News with heart, rooted in place',
            'slug'      => 'brandon-first-editorial',
            'desk'      => 'opinion',
            'byline'    => 'The Editorial Board',
            'lede'      => 'The Brandon Bulletin opens under two short sentences that are easy to print and hard to live up to. Here is what they commit this newsroom to.',
            'body'      => '<p>The wheat on our masthead grows out of an open book, and that is the whole idea: a paper that belongs to this place, and a record anyone can check. The Bulletin\'s beat is Brandon and the southwest — the Wheat City\'s halls and rinks, and the towns around it that make the region more than a dot an hour off the Trans-Canada.</p><p>"With heart" does not mean soft. It means the coverage starts from the people it affects and stays until the follow-through, not just the announcement. "Rooted in place" means the newsroom answers to its readers here, and to nobody else.</p><p>Hold us to three things. Tips sent to <a href="mailto:tips@brandonbulletin.ca">tips@brandonbulletin.ca</a> are read — all of them. When we get something wrong, the correction runs at the top of the story, dated. And opinion, including this page, is always signed and always labelled.</p>',
            'featured'  => 1,
            'published' => '2026-09-28 17:00:00',
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'How the Bulletin works',
            'slug'      => 'brandon-how-we-work',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'A guide to this paper\'s desks and the rules every story files under — documents first, named sources, and a hard line between news and opinion.',
            'body'      => '<p>Local News is the front door — what happened in Brandon and the southwest today. Sports runs from the community rink up. Community follows the people, halls and main streets that hold the region together. Opinion is signed and labelled, always.</p><p>The rules are the same at every desk: agendas and records before characterizations, named sources wherever possible, numbers that say where they came from, and a clear line between reporting and opinion. Wire items pointing at another outlet\'s work say so and link to it.</p><p>This first edition is about the paper rather than the city — an honest introduction beats manufactured news. The reporting starts as the newsroom files, and The Wheat City Brief carries it to you every weekday morning.</p>',
            'published' => '2026-09-28 16:40:00',
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'Every hall in the southwest: tell us where to look',
            'slug'      => 'brandon-community-desk-note',
            'desk'      => 'community',
            'byline'    => 'The Newsroom',
            'lede'      => 'Brandon, Souris, Virden, Killarney and every town between: the Community desk opens by asking the people who live here to point it at what matters.',
            'body'      => '<p>A regional paper fails quietly by covering only its biggest town. This desk exists to keep that from happening in the southwest — its beat runs to every community with a hall, a rink and a decision to make, and its first act is an invitation.</p><p>What should we be at? Whose work holds your town together? What is changing that nobody outside it has noticed? Send it to <a href="mailto:tips@brandonbulletin.ca">tips@brandonbulletin.ca</a>. If you need to stay unnamed, say so, and we will talk about what protecting that means before anything runs.</p><p>The desk\'s promise in return: when we come to your town, we come to listen first.</p>',
            'published' => '2026-09-28 16:20:00',
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'Wheat City sports, from the community rink up',
            'slug'      => 'brandon-sports-desk-note',
            'desk'      => 'sports',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Sports desk covers the southwest\'s own leagues, rinks and school teams first — and it is asking them to get in touch from day one.',
            'body'      => '<p>Brandon\'s sports story is bigger than any one team, and the southwest\'s is bigger than Brandon\'s. This desk starts local — the results, the seasons, and the volunteers who flood the rinks in November — and covers the bigger stages as a southwest story rather than a syndicated one.</p><p>Leagues, clubs and schools across the region: send schedules, results and contacts to <a href="mailto:tips@brandonbulletin.ca">tips@brandonbulletin.ca</a> and the desk will follow. Community sport only works as a beat if the community wires it up, and this note is the invitation.</p>',
            'published' => '2026-09-28 16:00:00',
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'Corrections, tips and how to reach the newsroom',
            'slug'      => 'brandon-service-note',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Bulletin\'s standing service page, published on day one: how tips reach us, how corrections run, and what we will never do with either.',
            'body'      => '<p>Tips: <a href="mailto:tips@brandonbulletin.ca">tips@brandonbulletin.ca</a>. Everything sent there is read by the newsroom. Say if you need to stay unnamed, and we will discuss what protecting that looks like before anything is published. We do not print what we cannot verify, which means the best tips come with something we can check — a document, a date, a name we may contact.</p><p>Corrections: when we get something wrong, the correction runs at the top of the story, dated, and stays there. Requests go to the same address with "Correction" in the subject line; ones that check out run promptly and without argument.</p><p>What we will never do: sell, share or act on a tip for any purpose except reporting it out.</p>',
            'published' => '2026-09-28 15:40:00',
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'Why wheat grows from an open book on our masthead',
            'slug'      => 'brandon-masthead-note',
            'desk'      => 'community',
            'byline'    => 'The Newsroom',
            'lede'      => 'A short note on the Bulletin\'s badge, its gold, and the two promises drawn into them.',
            'body'      => '<p>Brandon has been the Wheat City for as long as anyone here has needed a shorthand for it, and the gold on this page is that wheat\'s gold. The open book underneath it is the other half of the idea: a record, kept in public, that the place can check itself against.</p><p>Put together they are the Bulletin\'s two promises — rooted in this place, and on the record. The masthead is a small daily reminder of both, to readers and to the newsroom alike.</p><p>Prairie values. Local stories. Lasting impact.</p>',
            'published' => '2026-09-28 15:20:00',
            'tags'      => 'From the Bulletin',
        ],
    ],
];
