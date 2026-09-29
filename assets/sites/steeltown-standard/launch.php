<?php
/**
 * Steeltown Standard — launch pack (brand build, Sep 2026).
 * Seeded once by `PP_SITE=steeltown-standard php tools/seed-launch.php`.
 *
 * Identity from the owner's brand package: Tiger-Cats gold #FCB525 on
 * white with Hamilton navy #073674, black, accent crimson #A8353A,
 * steel gray and light camel gold; the chain-ring mark (a navy chain
 * circling a tiger-striped gold S built of steel I-beams, traced from
 * the package); a bold condensed uppercase headline face over serif
 * body copy. The package's line: "Local News. Steeltown Strong."
 * Footprint: Hamilton, Ontario. Desks follow the package's site
 * mockup: Local News, Sports, Opinion, Community.
 *
 * Domain: steeltownstandard.ca — OWNER-CONFIRMED registered (29 Sep).
 *
 * INAUGURAL SERVICE CONTENT ONLY — six launch notes ABOUT the paper
 * (mission, desk methods, how to reach the newsroom), true by
 * construction; no invented local events, votes or figures (CLAUDE.md
 * debt item 1 must not grow with launches). Real reporting arrives
 * through the newsroom or the ingest lanes.
 *
 * Desk self-sufficiency: all four desks exist network-wide; all are
 * listed so the pack stands alone regardless of seed order.
 */

return [

    'domains' => ['steeltownstandard.ca', 'www.steeltownstandard.ca'],

    'desks' => [
        ['name' => 'Local News', 'slug' => 'local-news', 'color' => '#073674', 'description' => 'Hamilton from the harbour to the mountain, reported from here — on the record, Steeltown strong.'],
        ['name' => 'Sports',     'slug' => 'sports',     'color' => '#073674', 'description' => 'The Ticats, the Bulldogs, and every rink, diamond and pitch from the bayfront up.'],
        ['name' => 'Community',  'slug' => 'community',  'color' => '#073674', 'description' => 'The neighbourhoods, halls and main streets that hold the Hammer together.'],
        ['name' => 'Opinion',    'slug' => 'opinion',    'color' => '#073674', 'description' => 'Signed columns and letters, always labelled. The editorial position is the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'Steeltown Standard',
        'tagline'            => 'Local News. Steeltown Strong.',
        'meta_description'   => 'Steeltown Standard is an independent digital newsroom for Hamilton, Ontario — the city\'s news from the harbour to the mountain. Local News. Steeltown Strong.',
        'footer_line'        => 'Independent news for Hamilton, Ontario — from the bayfront to the brow.',
        'contact_email'      => 'tips@steeltownstandard.ca',
        'newsletter_heading' => 'The Morning Pour',
        'newsletter_copy'    => 'Hamilton\'s news every weekday morning — what the city decided, built and argued about, before the first shift ends.',
        'weather_line'       => '11°C|Harbour haze',
        'regions'            => json_encode([
            'hamilton' => 'Hamilton',
            'ontario'  => 'Ontario',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['CBC Hamilton',  'https://www.cbc.ca/webfeed/rss/rss-canada-hamiltonnews', 'hamilton'],
        ['inTheHammer',   'https://www.inthehammer.com/feed/',                      'hamilton'],
        ['Global Toronto','https://globalnews.ca/toronto/feed/',                    'ontario'],
    ],

    /* Inaugural service content only (see the header). */
    'stories' => [
        [
            'title'     => 'Local news, Steeltown strong',
            'slug'      => 'steeltown-first-editorial',
            'desk'      => 'opinion',
            'byline'    => 'The Editorial Board',
            'lede'      => 'Steeltown Standard opens under four words that are easy to print and harder to live up to. Here is what they commit this newsroom to.',
            'body'      => '<p>The chain on our masthead is the whole idea: Hamilton\'s story is made of links — the harbour and the mountain, the mills and the main streets, the east end and the west — and a paper worth the name holds them together in one record. The S inside the ring is built of steel because that is what this city is built of, whatever else it becomes.</p><p>"Local news" means the coverage starts here and answers here: to readers in Hamilton, and to nobody else. "Steeltown strong" is not a slogan about the past — it is the standard the coverage is held to. Work that holds, sourced from documents and named people, corrected in the open when we get it wrong.</p><p>Hold us to three things. Tips sent to <a href="mailto:tips@steeltownstandard.ca">tips@steeltownstandard.ca</a> are read — all of them. When we get something wrong, the correction runs at the top of the story, dated. And opinion, including this page, is always signed and always labelled.</p>',
            'featured'  => 1,
            'published' => '2026-09-28 17:00:00',
            'tags'      => 'From the Standard',
        ],
        [
            'title'     => 'How the Standard works',
            'slug'      => 'steeltown-how-we-work',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'A guide to this paper\'s desks and the rules every story files under — documents first, named sources, and a hard line between news and opinion.',
            'body'      => '<p>Local News is the front door — what happened in Hamilton today, from the harbour to the mountain. Sports runs from the community rink up. Community follows the neighbourhoods, halls and main streets that hold the Hammer together. Opinion is signed and labelled, always.</p><p>The rules are the same at every desk: agendas and records before characterizations, named sources wherever possible, numbers that say where they came from, and a clear line between reporting and opinion. Wire items pointing at another outlet\'s work say so and link to it.</p><p>This first edition is about the paper rather than the city — an honest introduction beats manufactured news. The reporting starts as the newsroom files, and The Morning Pour carries it to you every weekday morning.</p>',
            'published' => '2026-09-28 16:40:00',
            'tags'      => 'From the Standard',
        ],
        [
            'title'     => 'Every neighbourhood in the Hammer: tell us where to look',
            'slug'      => 'steeltown-community-desk-note',
            'desk'      => 'community',
            'byline'    => 'The Newsroom',
            'lede'      => 'From the north end to the brow, Dundas to Stoney Creek: the Community desk opens by asking the people who live here to point it at what matters.',
            'body'      => '<p>A city paper fails quietly by covering only its downtown. This desk exists to keep that from happening in Hamilton — its beat runs to every neighbourhood with a hall, a rink and a decision to make, and its first act is an invitation.</p><p>What should we be at? Whose work holds your street together? What is changing that nobody outside your neighbourhood has noticed? Send it to <a href="mailto:tips@steeltownstandard.ca">tips@steeltownstandard.ca</a>. If you need to stay unnamed, say so, and we will talk about what protecting that means before anything runs.</p><p>The desk\'s promise in return: when we come to your neighbourhood, we come to listen first.</p>',
            'published' => '2026-09-28 16:20:00',
            'tags'      => 'From the Standard',
        ],
        [
            'title'     => 'Hamilton sports, from the community rink up',
            'slug'      => 'steeltown-sports-desk-note',
            'desk'      => 'sports',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Sports desk covers the city\'s own leagues, rinks and school teams first — and it is asking them to get in touch from day one.',
            'body'      => '<p>Hamilton\'s sports story is bigger than any one team, black-and-gold Saturdays included. This desk starts local — the results, the seasons, and the volunteers who flood the rinks in November — and covers the bigger stages as a Hamilton story rather than a syndicated one.</p><p>Leagues, clubs and schools across the city: send schedules, results and contacts to <a href="mailto:tips@steeltownstandard.ca">tips@steeltownstandard.ca</a> and the desk will follow. Community sport only works as a beat if the community wires it up, and this note is the invitation.</p>',
            'published' => '2026-09-28 16:00:00',
            'tags'      => 'From the Standard',
        ],
        [
            'title'     => 'Corrections, tips and how to reach the newsroom',
            'slug'      => 'steeltown-service-note',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Standard\'s standing service page, published on day one: how tips reach us, how corrections run, and what we will never do with either.',
            'body'      => '<p>Tips: <a href="mailto:tips@steeltownstandard.ca">tips@steeltownstandard.ca</a>. Everything sent there is read by the newsroom. Say if you need to stay unnamed, and we will discuss what protecting that looks like before anything is published. We do not print what we cannot verify, which means the best tips come with something we can check — a document, a date, a name we may contact.</p><p>Corrections: when we get something wrong, the correction runs at the top of the story, dated, and stays there. Requests go to the same address with "Correction" in the subject line; ones that check out run promptly and without argument.</p><p>What we will never do: sell, share or act on a tip for any purpose except reporting it out.</p>',
            'published' => '2026-09-28 15:40:00',
            'tags'      => 'From the Standard',
        ],
        [
            'title'     => 'Why a chain circles the steel on our masthead',
            'slug'      => 'steeltown-masthead-note',
            'desk'      => 'community',
            'byline'    => 'The Newsroom',
            'lede'      => 'A short note on the Standard\'s mark — the chain, the S of steel, and the promises drawn into them.',
            'body'      => '<p>The S at the centre of our mark is built of I-beams because Hamilton is: the harbour filled with lake boats, the mills that raised the east end, and the steel that left here to hold up half the country. The gold behind it is the city\'s other colour — anyone who has spent a fall Saturday in this town knows which one.</p><p>The chain around it is the part we chose most carefully. A chain is links, and so is this city — neighbourhoods, generations, unions, teams, congregations — each one holding because the others do. A local paper is one of those links or it is nothing.</p><p>Local News. Steeltown Strong. The mark is a small daily reminder of both halves — to readers and to the newsroom alike.</p>',
            'published' => '2026-09-28 15:20:00',
            'tags'      => 'From the Standard',
        ],
    ],
];
