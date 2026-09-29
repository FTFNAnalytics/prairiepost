<?php
/**
 * The Red River Register — launch pack (brand build, Sep 2026).
 * Seeded once by `PP_SITE=red-river-register php tools/seed-launch.php`.
 *
 * Identity: the owner asked for the Rideau Review's design style as
 * the Register's own — the Globe-gravity chassis (Playfair nameplate,
 * Libre Baskerville headlines, Source Sans 3 interface, one accent
 * rule) retokened in Red River clay with the ledger-and-meander mark.
 * Positioning: "The valley, on the record." Footprint (owner's working
 * assumption, confirm before launch): the Red River Valley beyond
 * Winnipeg — Selkirk, Steinbach, Morris, Emerson and the rural
 * municipalities — with a Winnipeg sister paper covering the city.
 *
 * INAUGURAL SERVICE CONTENT ONLY — six launch notes ABOUT the paper
 * (mission, desk methods, how to reach the newsroom), true by
 * construction; no invented local events, votes or figures (CLAUDE.md
 * debt item 1 must not grow with launches). Real reporting arrives
 * through the newsroom or the ingest lanes.
 *
 * Desk self-sufficiency: all five desks exist network-wide; all are
 * listed so the pack stands alone regardless of seed order.
 */

return [

    /* CONFIRM THE REGISTERED DOMAIN before launch (the Burrard lesson). */
    'domains' => ['redriverregister.ca', 'www.redriverregister.ca'],

    'desks' => [
        ['name' => 'Local News',  'slug' => 'local-news',  'color' => '#7E3517', 'description' => 'The valley\'s news, reported from here — Selkirk to Emerson, on the record.'],
        ['name' => 'Communities', 'slug' => 'communities', 'color' => '#7E3517', 'description' => 'The towns and rural municipalities along the river, covered as places where decisions happen.'],
        ['name' => 'Business',    'slug' => 'business',    'color' => '#7E3517', 'description' => 'Farms, main streets, and the jobs that ride on both.'],
        ['name' => 'Sports',      'slug' => 'sports',      'color' => '#7E3517', 'description' => 'The valley\'s own rinks, diamonds and school teams.'],
        ['name' => 'Opinion',     'slug' => 'opinion',     'color' => '#7E3517', 'description' => 'Signed columns and letters, always labelled. The editorial position is the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'The Red River Register',
        'tagline'            => 'The valley, on the record.',
        'meta_description'   => 'The Red River Register is an independent digital newsroom for the Red River Valley — Selkirk, Steinbach, Morris and the municipalities between. The valley, on the record.',
        'footer_line'        => 'Independent news for the Red River Valley.',
        'contact_email'      => 'tips@redriverregister.ca',
        'newsletter_heading' => 'The Morning Record',
        'newsletter_copy'    => 'The valley\'s day, on the record — councils, main streets and the river itself, every weekday morning before the gravel roads fill.',
        'weather_line'       => '3°C|Red River at Selkirk',
        'regions'            => json_encode([
            'red-river-valley' => 'Red River Valley',
            'manitoba'         => 'Manitoba',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['CBC Manitoba',    'https://www.cbc.ca/webfeed/rss/rss-canada-manitoba', 'manitoba'],
        ['Global Winnipeg', 'https://globalnews.ca/winnipeg/feed/',               'manitoba'],
    ],

    /* Inaugural service content only (see the header). */
    'stories' => [
        [
            'title'     => 'The valley, on the record',
            'slug'      => 'redriver-first-editorial',
            'desk'      => 'opinion',
            'byline'    => 'The Editorial Board',
            'lede'      => 'The Red River Register opens with a name that is also a promise — and a note on how an independent newsroom for the valley intends to keep it.',
            'body'      => '<p>A register is where things are written down so they cannot quietly be unwritten: births, deeds, decisions. That is the job this paper takes on for the Red River Valley — the councils, budgets and main streets from Selkirk to Emerson, recorded plainly, by a newsroom that answers to the valley it covers.</p><p>The gap we fill is a familiar one. The valley\'s towns make real decisions with real money, and most of them are made without a reporter in the room, because the nearest newsrooms sit an hour north and cover the city. The Register\'s beat starts where the Perimeter ends.</p><p>Hold us to three things. Tips sent to <a href="mailto:tips@redriverregister.ca">tips@redriverregister.ca</a> are read — all of them. When we get something wrong, the correction runs at the top of the story, dated. And opinion, including this page, is always signed and always labelled. The valley, on the record.</p>',
            'featured'  => 1,
            'published' => '2026-09-28 17:00:00',
            'tags'      => 'From the Register',
        ],
        [
            'title'     => 'How the Register keeps its ledger',
            'slug'      => 'redriver-how-we-work',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'A guide to this paper\'s desks and the rules every story files under — documents first, named sources, and a hard line between news and opinion.',
            'body'      => '<p>The Valley is the front door — what happened along the river today. Communities follows each town and rural municipality on its own terms. Business covers the farms and main streets together, because in this valley they are one economy. Sports starts at the local rink. Opinion is signed and labelled, always.</p><p>The rules are the same at every desk: agendas and records before characterizations, named sources wherever possible, numbers that say where they came from, and a clear line between reporting and opinion. Wire items pointing at another outlet\'s work say so and link to it.</p><p>This first edition is about the paper rather than the valley — an honest introduction beats manufactured news. The reporting starts as the newsroom files, and The Morning Record will carry it to you every weekday.</p>',
            'published' => '2026-09-28 16:40:00',
            'tags'      => 'From the Register',
        ],
        [
            'title'     => 'Every town on the river: tell us where to look',
            'slug'      => 'redriver-communities-desk-note',
            'desk'      => 'communities',
            'byline'    => 'The Newsroom',
            'lede'      => 'Selkirk, Steinbach, Morris, Emerson and every municipality between: the Communities desk opens by asking the people who live there to point it at what matters.',
            'body'      => '<p>A valley paper fails quietly by covering only its biggest towns. This desk exists to keep that from happening — its beat is every community along the river and off it, and its first act is an invitation.</p><p>What should we be at? Whose work holds your town together? What is changing that nobody outside it has noticed? Send it to <a href="mailto:tips@redriverregister.ca">tips@redriverregister.ca</a>. If you need to stay unnamed, say so, and we will talk about what protecting that means before anything runs.</p><p>The desk\'s promise in return: when we come to your town, we come to listen first.</p>',
            'published' => '2026-09-28 16:20:00',
            'tags'      => 'From the Register',
        ],
        [
            'title'     => 'Farms and main streets: how the Business desk will work',
            'slug'      => 'redriver-business-desk-note',
            'desk'      => 'business',
            'byline'    => 'The Newsroom',
            'lede'      => 'The valley\'s economy runs from the field to the storefront, and the two rise and fall together. This desk covers them as one beat.',
            'body'      => '<p>Business coverage here is not a stock ticker. It is what the crop looks like, what the elevator is paying, whether the machine shop found its second welder, and whether the last grocery store on a main street stays open. This desk starts at that altitude and works up, never the reverse.</p><p>When the desk covers an opening, a closure or an investment, it will trace the decision to whoever made it and put the numbers in reach of anyone who wants to check them.</p><p>Owners, growers, workers, buyers: the desk\'s door is <a href="mailto:tips@redriverregister.ca">tips@redriverregister.ca</a>.</p>',
            'published' => '2026-09-28 16:00:00',
            'tags'      => 'From the Register',
        ],
        [
            'title'     => 'Valley sports start at the local rink',
            'slug'      => 'redriver-sports-desk-note',
            'desk'      => 'sports',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Sports desk\'s beat is the valley\'s own rinks, diamonds and gyms — and it is asking leagues, clubs and schools to get in touch from day one.',
            'body'      => '<p>There is no shortage of coverage of professional sport an hour up the highway; the gap is local. This desk exists for the valley\'s own leagues, clubs and school teams — the results, the seasons, and the volunteers who flood the rinks in November.</p><p>Leagues and clubs: send schedules, results and contacts to <a href="mailto:tips@redriverregister.ca">tips@redriverregister.ca</a> and the desk will follow. Community sport only works as a beat if the community wires it up, and this note is the invitation.</p>',
            'published' => '2026-09-28 15:40:00',
            'tags'      => 'From the Register',
        ],
        [
            'title'     => 'Corrections, tips and how to reach the newsroom',
            'slug'      => 'redriver-service-note',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Register\'s standing service page, published on day one: how tips reach us, how corrections run, and what we will never do with either.',
            'body'      => '<p>Tips: <a href="mailto:tips@redriverregister.ca">tips@redriverregister.ca</a>. Everything sent there is read by the newsroom. Say if you need to stay unnamed, and we will discuss what protecting that looks like before anything is published. We do not print what we cannot verify, which means the best tips come with something we can check — a document, a date, a name we may contact.</p><p>Corrections: when we get something wrong, the correction runs at the top of the story, dated, and stays there. Requests go to the same address with "Correction" in the subject line; ones that check out run promptly and without argument.</p><p>What we will never do: sell, share or act on a tip for any purpose except reporting it out.</p>',
            'published' => '2026-09-28 15:20:00',
            'tags'      => 'From the Register',
        ],
    ],
];
