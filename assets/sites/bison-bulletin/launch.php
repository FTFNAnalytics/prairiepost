<?php
/**
 * The Bison Bulletin — launch pack (brand build, Sep 2026).
 * Seeded once by `PP_SITE=bison-bulletin php tools/seed-launch.php`.
 *
 * Identity from the owner's brand package: bison red #8B0000 on
 * prairie cream #F5F5DC with earth brown; Montserrat headlines over an
 * Open Sans interface; the red bison mark (traced from the package's
 * primary logo). Tagline, from the package: "Manitoba News You Can
 * Trust." The package's own subtitle ("Manitoba News", "from the
 * heart of Manitoba") settles the footprint question: this is the
 * PROVINCE-WIDE masthead; the Red River Register covers the valley,
 * and a Winnipeg city title remains to be named.
 *
 * INAUGURAL SERVICE CONTENT ONLY — six launch notes ABOUT the paper
 * (mission, desk methods, how to reach the newsroom), true by
 * construction; no invented local events, votes or figures (CLAUDE.md
 * debt item 1 must not grow with launches). Real reporting arrives
 * through the newsroom or the ingest lanes.
 *
 * Desk self-sufficiency: all six desks exist network-wide; all are
 * listed so the pack stands alone regardless of seed order.
 */

return [

    /* CONFIRM THE REGISTERED DOMAIN before launch (the Burrard lesson). */
    'domains' => ['bisonbulletin.ca', 'www.bisonbulletin.ca'],

    'desks' => [
        ['name' => 'Local News', 'slug' => 'local-news', 'color' => '#8B0000', 'description' => 'Manitoba\'s news, reported from here — the whole herd, not just the biggest pen.'],
        ['name' => 'Politics',   'slug' => 'politics',   'color' => '#8B0000', 'description' => 'The legislature, the councils, and the record behind every announcement.'],
        ['name' => 'Business',   'slug' => 'business',   'color' => '#8B0000', 'description' => 'Manitoba\'s economy at street level — farms, floors and main streets.'],
        ['name' => 'Sports',     'slug' => 'sports',     'color' => '#8B0000', 'description' => 'From the community rink to the big leagues, Manitoba first.'],
        ['name' => 'Culture',    'slug' => 'culture',    'color' => '#8B0000', 'description' => 'The stages, festivals and rooms where Manitoba tells its own story.'],
        ['name' => 'Opinion',    'slug' => 'opinion',    'color' => '#8B0000', 'description' => 'Signed columns and letters, always labelled. The editorial position is the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'The Bison Bulletin',
        'tagline'            => 'Manitoba News You Can Trust',
        'meta_description'   => 'The Bison Bulletin delivers the latest updates, community stories, and in-depth reporting directly from the heart of Manitoba. Manitoba News You Can Trust.',
        'footer_line'        => 'Delivering community stories and in-depth reporting from the heart of Manitoba.',
        'contact_email'      => 'tips@bisonbulletin.ca',
        'newsletter_heading' => 'The Morning Bulletin',
        'newsletter_copy'    => 'Manitoba\'s news every weekday morning — what happened, what it means, and what to watch, from the heart of the province.',
        'weather_line'       => '2°C|The Forks',
        'regions'            => json_encode([
            'winnipeg' => 'Winnipeg',
            'manitoba' => 'Manitoba',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['CBC Manitoba',    'https://www.cbc.ca/webfeed/rss/rss-canada-manitoba', 'manitoba'],
        ['Global Winnipeg', 'https://globalnews.ca/winnipeg/feed/',               'winnipeg'],
    ],

    /* Inaugural service content only (see the header). */
    'stories' => [
        [
            'title'     => 'Manitoba news you can trust: what that sentence commits us to',
            'slug'      => 'bison-first-editorial',
            'desk'      => 'opinion',
            'byline'    => 'The Editorial Board',
            'lede'      => 'The Bison Bulletin opens under a tagline that is easy to say and hard to earn. Here is what this paper means by it, and how to hold us to it.',
            'body'      => '<p>The bison on our masthead is Manitoba\'s emblem for a reason: it belongs to the whole province, not to one city. So does this paper. The Bulletin\'s beat is Manitoba entire — the legislature and the local rink, the Perimeter and everything past it — reported by a newsroom that answers to its readers and to nobody else.</p><p>Trust is not a slogan; it is a set of habits. Documents before characterizations. Named sources wherever possible. Numbers that say where they came from. A hard line between reporting and opinion, with opinion always signed and always labelled — including this page.</p><p>Two standing invitations. Tips: everything sent to <a href="mailto:tips@bisonbulletin.ca">tips@bisonbulletin.ca</a> is read. Corrections: when we get something wrong, the correction runs at the top of the story, dated. That is how a paper earns the second half of its tagline.</p>',
            'featured'  => 1,
            'published' => '2026-09-28 17:00:00',
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'What the Bulletin covers, and how',
            'slug'      => 'bison-how-we-work',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'A guide to this paper\'s desks and the standard every one of them files under.',
            'body'      => '<p>News is the front door — what happened in Manitoba today. Politics follows the legislature and the councils, on the record. Business covers the province\'s economy at street level. Sports runs from the community rink up. Culture covers the stages and festivals where Manitoba tells its own story. Opinion is signed and labelled, always.</p><p>Every desk works the same way: start from documents and records, name sources wherever possible, show where numbers come from, and keep reporting and opinion apart. Wire items that summarize another outlet\'s work say so and link to it.</p><p>This first edition is about the paper itself rather than the province — an honest introduction beats manufactured news. The reporting starts as the newsroom files, and The Morning Bulletin carries it to you every weekday.</p>',
            'published' => '2026-09-28 16:40:00',
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'Politics coverage starts with the record',
            'slug'      => 'bison-politics-desk-note',
            'desk'      => 'politics',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Politics desk\'s method, stated on day one: the order paper before the press release, the vote as recorded, the follow-through after.',
            'body'      => '<p>Political coverage goes wrong in a predictable way: it starts from what an office announces rather than what a chamber decides. This desk commits to working the other way — Hansard, order papers, committee records and council minutes first, the vote as it happened, and the follow-through months later when the decision meets the ground.</p><p>Every number we print will say where it came from, and when we summarize a debate, the record it came from will be identified so you can check our reading against it.</p><p>Watching something in provincial or municipal politics you think Manitoba should know about? The desk reads everything sent to <a href="mailto:tips@bisonbulletin.ca">tips@bisonbulletin.ca</a>.</p>',
            'published' => '2026-09-28 16:20:00',
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'Manitoba\'s economy, at street level',
            'slug'      => 'bison-business-desk-note',
            'desk'      => 'business',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Business desk covers the province\'s economy where it is actually lived — the farm gate, the shop floor and the main street.',
            'body'      => '<p>Manitoba\'s economy is not an index; it is what the crop looks like, who the plants are hiring, and whether the main-street storefront turns its lights on. This desk starts at that altitude and works up, never the reverse.</p><p>When the desk covers an opening, a closure or an investment, it will trace the decision to whoever made it and put the numbers in reach of anyone who wants to check them.</p><p>Owners, workers, growers, builders: the desk\'s door is <a href="mailto:tips@bisonbulletin.ca">tips@bisonbulletin.ca</a>.</p>',
            'published' => '2026-09-28 16:00:00',
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'From the community rink up: the Sports desk',
            'slug'      => 'bison-sports-desk-note',
            'desk'      => 'sports',
            'byline'    => 'The Newsroom',
            'lede'      => 'Manitoba\'s sports story runs from November rinks to the big leagues, and this desk covers it in that order.',
            'body'      => '<p>The province\'s biggest teams are covered everywhere; its leagues, clubs and school teams mostly are not. This desk starts local — the results, the seasons, and the volunteers who keep the lights on — and covers the big leagues as a Manitoba story, not a syndicated one.</p><p>Leagues and clubs across the province: send schedules, results and contacts to <a href="mailto:tips@bisonbulletin.ca">tips@bisonbulletin.ca</a> and the desk will follow. Community sport only works as a beat if the community wires it up, and this note is the invitation.</p>',
            'published' => '2026-09-28 15:40:00',
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'Where Manitoba tells its own story',
            'slug'      => 'bison-culture-desk-note',
            'desk'      => 'culture',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Culture desk covers the stages, festivals, galleries and rooms where the province speaks for itself — with working coverage, not press-release culture.',
            'body'      => '<p>Manitoba\'s cultural life is bigger than its size and older than its cities, and it deserves coverage that shows up: what is actually on, whether it is worth your evening, and the civic side of the file — the venues, the funding decisions, the institutions — reported with the same document-first method as every other desk.</p><p>Companies, venues, festivals and artists across the province: tell the desk what is coming at <a href="mailto:tips@bisonbulletin.ca">tips@bisonbulletin.ca</a>.</p>',
            'published' => '2026-09-28 15:20:00',
            'tags'      => 'From the Bulletin',
        ],
    ],
];
