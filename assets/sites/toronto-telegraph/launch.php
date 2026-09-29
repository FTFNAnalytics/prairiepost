<?php
/**
 * Toronto Telegraph — launch pack (brand build, Sep 2026).
 * Seeded once by `PP_SITE=toronto-telegraph php tools/seed-launch.php`.
 *
 * Identity from the owner's brand package: the double-blue heritage
 * palette — Leafs Navy #00205B, Argos Oxford #0C2340, Argos Cambridge
 * #5F8FB1 — with pure white, charcoal and soft silver; the
 * maple-leaf-and-telegraph-key roundel (traced from the package's app
 * icon); bold modern sans headlines over classic serif body. The
 * package's lines: "Toronto's Independent Voice" (masthead tagline),
 * "Truth. Perspective. Toronto.", "Local stories. Global city.",
 * "Toronto Focused / Community Connected / Truth First". Desks follow
 * the package's website mockup nav: City, Sports, Business, Opinion,
 * Culture.
 *
 * Domain: torontotelegraph.ca — OWNER-CONFIRMED registered (29 Sep).
 *
 * INAUGURAL SERVICE CONTENT ONLY — six launch notes ABOUT the paper
 * (mission, desk methods, how to reach the newsroom), true by
 * construction; no invented local events, votes or figures (CLAUDE.md
 * debt item 1 must not grow with launches). Real reporting arrives
 * through the newsroom or the ingest lanes.
 *
 * Desk self-sufficiency: all five desks exist network-wide
 * (`culture` since Bison's seed); all are listed so the pack stands
 * alone regardless of seed order. `desk_labels` renames `local-news`
 * to "City" for this paper; the desk page stays /desk/local-news.
 */

return [

    'domains' => ['torontotelegraph.ca', 'www.torontotelegraph.ca'],

    'desks' => [
        ['name' => 'City',     'slug' => 'local-news', 'color' => '#00205B', 'description' => 'Toronto street by street: council, transit, housing, and the people the city is built of.'],
        ['name' => 'Sports',   'slug' => 'sports',     'color' => '#00205B', 'description' => 'The Leafs, the Raptors, the Jays, the Argos — and every rink and court in between.'],
        ['name' => 'Business', 'slug' => 'business',   'color' => '#00205B', 'description' => 'Bay Street to the main streets — the money, the towers, and who they work for.'],
        ['name' => 'Culture',  'slug' => 'culture',    'color' => '#00205B', 'description' => 'Stages, screens, galleries and the writers\' rooms of a culture capital.'],
        ['name' => 'Opinion',  'slug' => 'opinion',    'color' => '#00205B', 'description' => 'Signed columns and letters, always labelled. The editorial position is the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'Toronto Telegraph',
        'tagline'            => 'Toronto\'s Independent Voice',
        'meta_description'   => 'Toronto Telegraph is an independent digital newsroom for Toronto — city hall, business, culture and sports, reported with truth and perspective. Local stories. Global city.',
        'footer_line'        => 'Independent news for Toronto — local stories, global city.',
        'contact_email'      => 'tips@torontotelegraph.ca',
        'newsletter_heading' => 'The Morning Wire',
        'newsletter_copy'    => 'Toronto\'s news every weekday morning — what the city decided, built and argued about, before the first streetcar is full.',
        'weather_line'       => '12°C|Light cloud',
        'regions'            => json_encode([
            'toronto' => 'Toronto',
            'ontario' => 'Ontario',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['CBC Toronto',    'https://www.cbc.ca/webfeed/rss/rss-canada-toronto', 'toronto'],
        ['Global Toronto', 'https://globalnews.ca/toronto/feed/',               'toronto'],
        ['blogTO',         'https://www.blogto.com/rss',                        'toronto'],
    ],

    /* Inaugural service content only (see the header). */
    'stories' => [
        [
            'title'     => 'Truth. Perspective. Toronto.',
            'slug'      => 'telegraph-first-editorial',
            'desk'      => 'opinion',
            'byline'    => 'The Editorial Board',
            'lede'      => 'Toronto Telegraph opens under three words that are easy to print and harder to live up to. Here is what they commit this newsroom to.',
            'body'      => '<p>The telegraph was the fastest wire of its century — the machine that made news local everywhere at once — and the key on our masthead sits inside a maple leaf for a reason. This paper\'s wire runs one way: from Toronto, about Toronto, to the people who live here. Local stories, in a global city.</p><p>"Truth" means documents before characterizations, named sources wherever possible, and corrections that run at the top of the story, dated, when we get it wrong. "Perspective" means the second question — what it means, who it lands on — asked in the same story that reports what happened. "Toronto" means the beat is this city, street by street, and the newsroom answers to its readers here and to nobody else.</p><p>Hold us to it. Tips sent to <a href="mailto:tips@torontotelegraph.ca">tips@torontotelegraph.ca</a> are read — all of them. And opinion, including this page, is always signed and always labelled.</p>',
            'featured'  => 1,
            'published' => '2026-09-28 17:00:00',
            'tags'      => 'From the Telegraph',
        ],
        [
            'title'     => 'How the Telegraph works',
            'slug'      => 'telegraph-how-we-work',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'A guide to this paper\'s desks and the rules every story files under — documents first, named sources, and a hard line between news and opinion.',
            'body'      => '<p>City is the front door — council, transit, housing, and what happened in Toronto today. Sports runs from the community rink to the big stages, covered as a Toronto story rather than a syndicated one. Business follows the money from Bay Street to the main streets. Culture takes the stages, screens and galleries of a culture capital seriously. Opinion is signed and labelled, always.</p><p>The rules are the same at every desk: agendas and records before characterizations, named sources wherever possible, numbers that say where they came from, and a clear line between reporting and opinion. Wire items pointing at another outlet\'s work say so and link to it.</p><p>This first edition is about the paper rather than the city — an honest introduction beats manufactured news. The reporting starts as the newsroom files, and The Morning Wire carries it to you every weekday morning.</p>',
            'published' => '2026-09-28 16:40:00',
            'tags'      => 'From the Telegraph',
        ],
        [
            'title'     => 'Toronto sports, covered from here',
            'slug'      => 'telegraph-sports-desk-note',
            'desk'      => 'sports',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Sports desk covers the city\'s own teams, rinks and leagues as a Toronto story — and it is asking the grassroots to get in touch from day one.',
            'body'      => '<p>Toronto\'s sports story runs from the double blue and the blue-and-white down to house league on a Saturday morning, and this desk treats all of it as one beat. The professional stages get covered as a Toronto story — what the season means here — and the community game gets covered at all, which is rarer than it should be.</p><p>Leagues, clubs and schools across the city: send schedules, results and contacts to <a href="mailto:tips@torontotelegraph.ca">tips@torontotelegraph.ca</a> and the desk will follow. Community sport only works as a beat if the community wires it up, and this note is the invitation.</p>',
            'published' => '2026-09-28 16:20:00',
            'tags'      => 'From the Telegraph',
        ],
        [
            'title'     => 'A culture capital deserves a culture desk',
            'slug'      => 'telegraph-culture-desk-note',
            'desk'      => 'culture',
            'byline'    => 'The Newsroom',
            'lede'      => 'Stages, screens, galleries and the writers\' rooms: the Culture desk opens by asking the people who make this city\'s culture to point it at what matters.',
            'body'      => '<p>Toronto makes culture at a scale few cities anywhere match, and loses coverage of it faster than almost anything else it does. This desk exists to push against that: the openings and the closings, the companies and the scenes, the work itself and the conditions it gets made under.</p><p>Artists, companies, venues and festivals: tell the desk what is coming and who is doing the work — <a href="mailto:tips@torontotelegraph.ca">tips@torontotelegraph.ca</a>. The desk\'s promise in return: the coverage starts from the work, and criticism, when it runs, is signed and labelled like every other opinion in this paper.</p>',
            'published' => '2026-09-28 16:00:00',
            'tags'      => 'From the Telegraph',
        ],
        [
            'title'     => 'Following the money, from Bay Street to Main Street',
            'slug'      => 'telegraph-business-desk-note',
            'desk'      => 'business',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Business desk\'s method, published on day one: the numbers say where they came from, and the story says who the money lands on.',
            'body'      => '<p>Toronto is Canada\'s money city, which makes business coverage here civic coverage: the towers and the deals shape the rents, the jobs and the main streets of everyone who never sets foot on Bay Street. This desk covers both ends of that wire — what the money is doing, and who it is doing it to.</p><p>The method is the paper\'s: numbers that say where they came from, filings and records before characterizations, and a hard line between reporting and the opinion page. If your business, your street or your industry is changing in a way nobody outside it has noticed, tell the desk: <a href="mailto:tips@torontotelegraph.ca">tips@torontotelegraph.ca</a>.</p>',
            'published' => '2026-09-28 15:40:00',
            'tags'      => 'From the Telegraph',
        ],
        [
            'title'     => 'Corrections, tips and how to reach the newsroom',
            'slug'      => 'telegraph-service-note',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Telegraph\'s standing service page, published on day one: how tips reach us, how corrections run, and what we will never do with either.',
            'body'      => '<p>Tips: <a href="mailto:tips@torontotelegraph.ca">tips@torontotelegraph.ca</a>. Everything sent there is read by the newsroom. Say if you need to stay unnamed, and we will discuss what protecting that looks like before anything is published. We do not print what we cannot verify, which means the best tips come with something we can check — a document, a date, a name we may contact.</p><p>Corrections: when we get something wrong, the correction runs at the top of the story, dated, and stays there. Requests go to the same address with "Correction" in the subject line; ones that check out run promptly and without argument.</p><p>What we will never do: sell, share or act on a tip for any purpose except reporting it out.</p>',
            'published' => '2026-09-28 15:20:00',
            'tags'      => 'From the Telegraph',
        ],
    ],
];
