<?php
/**
 * Portage Press — launch pack (brand build, Sep 2026).
 * Seeded once by `PP_SITE=portage-press php tools/seed-launch.php`.
 *
 * The Winnipeg city title (completing Manitoba: Bison = province,
 * Brandon = the southwest, Register = the valley). Identity from the
 * owner's brand guidelines: Polar Night Blue #041E42 with accent red
 * #AC162C and Dark Gray #4B5563 body; Inter primary with Playfair
 * Display for pull quotes; the P-pin mark (traced two-color from the
 * package's icon sheet). Tagline, from the package: "Local News.
 * Winnipeg Matters." Domain per the package's own mockup:
 * portagepress.ca.
 *
 * INAUGURAL SERVICE CONTENT ONLY — six launch notes ABOUT the paper
 * (mission, desk methods, how to reach the newsroom), true by
 * construction; no invented local events, votes or figures (CLAUDE.md
 * debt item 1 must not grow with launches). Real reporting arrives
 * through the newsroom or the ingest lanes.
 *
 * Desk self-sufficiency: `arts` is NEW network-wide (it appears only
 * in the dormant Terminal City pack, which has never seeded) and is
 * created on this pack's first seed; the other five desks exist. All
 * six are listed so the pack stands alone regardless of seed order.
 */

return [

    /* CONFIRM THE REGISTERED DOMAIN before launch (the Burrard lesson). */
    'domains' => ['portagepress.ca', 'www.portagepress.ca'],

    'desks' => [
        ['name' => 'Local News', 'slug' => 'local-news', 'color' => '#AC162C', 'description' => 'Winnipeg\'s news, reported from here — the city that meets at Portage and Main.'],
        ['name' => 'Sports',     'slug' => 'sports',     'color' => '#AC162C', 'description' => 'From the community rink to the downtown arena, Winnipeg first.'],
        ['name' => 'Politics',   'slug' => 'politics',   'color' => '#AC162C', 'description' => 'City hall and the legislature, covered from the record out.'],
        ['name' => 'Business',   'slug' => 'business',   'color' => '#AC162C', 'description' => 'Winnipeg\'s economy at street level — the Exchange to the industrial park.'],
        ['name' => 'Arts',       'slug' => 'arts',       'color' => '#AC162C', 'description' => 'The stages, galleries and festivals of a city that punches above its weight.'],
        ['name' => 'Opinion',    'slug' => 'opinion',    'color' => '#AC162C', 'description' => 'Signed columns and letters, always labelled. The editorial position is the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'Portage Press',
        'tagline'            => 'Local News. Winnipeg Matters.',
        'meta_description'   => 'Portage Press is Winnipeg\'s independent source for local news, weather, and community stories that shape our city. We dig deeper, report fairly, and deliver the context you need.',
        'footer_line'        => 'Winnipeg\'s independent source for local news, weather, and community stories that shape our city.',
        'contact_email'      => 'tips@portagepress.ca',
        'newsletter_heading' => 'Stay in the Know',
        'newsletter_copy'    => 'Get the latest Winnipeg news delivered to your inbox — what the city decided, what it means, and what\'s ahead, every weekday.',
        'weather_line'       => '-2°C|Mostly cloudy',
        'regions'            => json_encode([
            'winnipeg' => 'Winnipeg',
            'manitoba' => 'Manitoba',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['CBC Manitoba',    'https://www.cbc.ca/webfeed/rss/rss-canada-manitoba', 'winnipeg'],
        ['Global Winnipeg', 'https://globalnews.ca/winnipeg/feed/',               'winnipeg'],
    ],

    /* Inaugural service content only (see the header). */
    'stories' => [
        [
            'title'     => 'Local news, because Winnipeg matters',
            'slug'      => 'portage-first-editorial',
            'desk'      => 'opinion',
            'byline'    => 'The Editorial Board',
            'lede'      => 'Portage Press opens under a tagline with two short sentences in it. The first names the work; the second names the reason. Here is what both commit this newsroom to.',
            'body'      => '<p>The Press takes its name from the street where this city has always met itself — and from the oldest promise in this trade: what happens here gets written down. Our beat is Winnipeg entire, from city hall to the community club, reported by a newsroom that answers to its readers and to nobody else.</p><p>We dig deeper, report fairly, and deliver the context you need. Those are working rules, not slogans: documents before characterizations, named sources wherever possible, numbers that say where they came from, and a hard line between reporting and opinion — with opinion, including this page, always signed and always labelled.</p><p>Hold us to two more. Tips sent to <a href="mailto:tips@portagepress.ca">tips@portagepress.ca</a> are read — all of them. And when we get something wrong, the correction runs at the top of the story, dated. Winnipeg\'s strength is our community, and a paper earns its place in one by keeping its word.</p>',
            'featured'  => 1,
            'published' => '2026-09-28 17:00:00',
            'tags'      => 'From the Press',
        ],
        [
            'title'     => 'How the Press works',
            'slug'      => 'portage-how-we-work',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'A guide to this paper\'s desks and the standard every one of them files under.',
            'body'      => '<p>News is the front door — what happened in Winnipeg today. Sports runs from the community rink to the downtown arena. Politics covers city hall and the legislature from the record out. Business follows the city\'s economy at street level. Arts covers the stages and galleries of a city that punches above its weight. Opinion is signed and labelled, always.</p><p>Every desk works the same way: agendas and records before characterizations, named sources wherever possible, and a clear line between reporting and opinion. Wire items pointing at another outlet\'s work say so and link to it.</p><p>This first edition is about the paper rather than the city — an honest introduction beats manufactured news. The reporting starts as the newsroom files; subscribe on the front page and it lands in your inbox every weekday.</p>',
            'published' => '2026-09-28 16:40:00',
            'tags'      => 'From the Press',
        ],
        [
            'title'     => 'City hall from the record out: the Politics desk',
            'slug'      => 'portage-politics-desk-note',
            'desk'      => 'politics',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Politics desk\'s method, stated on day one: the agenda before the press release, the vote as recorded, the follow-through after.',
            'body'      => '<p>Political coverage goes wrong in a predictable way: it starts from what an office announces rather than what a chamber decides. This desk commits to working the other way — council agendas, committee reports and the legislature\'s record first, the vote as it happened, and the follow-through months later when the decision meets the street.</p><p>Every number we print will say where it came from, and when we summarize a debate, the record it came from will be identified so you can check our reading against it.</p><p>Watching something at city hall or the legislature you think Winnipeg should know about? The desk reads everything sent to <a href="mailto:tips@portagepress.ca">tips@portagepress.ca</a>.</p>',
            'published' => '2026-09-28 16:20:00',
            'tags'      => 'From the Press',
        ],
        [
            'title'     => 'The Exchange to the industrial park: the Business desk',
            'slug'      => 'portage-business-desk-note',
            'desk'      => 'business',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Business desk covers Winnipeg\'s economy where it is lived — the storefront, the shop floor and the order book.',
            'body'      => '<p>Winnipeg\'s economy is not an index; it is whether the storefront turns its lights on, who the plants are hiring, and what the order book says about next year. This desk starts at that altitude and works up, never the reverse.</p><p>When the desk covers an opening, a closure or an investment, it will trace the decision to whoever made it and put the numbers in reach of anyone who wants to check them.</p><p>Owners, workers, builders, buyers: the desk\'s door is <a href="mailto:tips@portagepress.ca">tips@portagepress.ca</a>.</p>',
            'published' => '2026-09-28 16:00:00',
            'tags'      => 'From the Press',
        ],
        [
            'title'     => 'A city that punches above its weight: the Arts desk',
            'slug'      => 'portage-arts-desk-note',
            'desk'      => 'arts',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Arts desk covers Winnipeg\'s stages, galleries and festivals with working coverage — what\'s on, whether it\'s worth your evening, and the civic file behind it.',
            'body'      => '<p>For its size, this city\'s cultural life is enormous, and it deserves coverage that shows up: what is actually on, whether it is worth your evening, and the civic side of the file — the venues, the funding decisions, the institutions — reported with the same document-first method as every other desk.</p><p>Companies, venues, festivals and artists: tell the desk what is coming at <a href="mailto:tips@portagepress.ca">tips@portagepress.ca</a>.</p>',
            'published' => '2026-09-28 15:40:00',
            'tags'      => 'From the Press',
        ],
        [
            'title'     => 'From the community rink to the downtown arena: the Sports desk',
            'slug'      => 'portage-sports-desk-note',
            'desk'      => 'sports',
            'byline'    => 'The Newsroom',
            'lede'      => 'Winnipeg\'s sports story is bigger than one arena, and this desk covers it in that order — the local leagues first, the big leagues as a Winnipeg story.',
            'body'      => '<p>The city\'s biggest teams are covered everywhere; its leagues, clubs and school teams mostly are not. This desk starts local — the results, the seasons, and the volunteers who keep the rinks open — and covers the big leagues as a Winnipeg story rather than a syndicated one.</p><p>Leagues, clubs and schools across the city: send schedules, results and contacts to <a href="mailto:tips@portagepress.ca">tips@portagepress.ca</a> and the desk will follow. Community sport only works as a beat if the community wires it up, and this note is the invitation.</p>',
            'published' => '2026-09-28 15:20:00',
            'tags'      => 'From the Press',
        ],
    ],
];
