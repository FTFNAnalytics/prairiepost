<?php
/**
 * The Rideau Review — launch pack (brand build, Sep 2026).
 * Seeded once by `PP_SITE=rideau-review php tools/seed-launch.php`.
 *
 * INAUGURAL SERVICE CONTENT ONLY — identity, desks, wire sources and
 * six launch notes ABOUT the paper (mission, desk methods, how to
 * reach the newsroom). True by construction; no invented local news.
 *
 * Identity from the owner's brand package: Rideau crimson (#6B1C28 —
 * deliberately not Globe scarlet), warm paper ground, the lock-and-leaf
 * mark before the nameplate, Playfair Display nameplate over Libre
 * Baskerville headlines and a Source Sans 3 interface. Positioning:
 * "The capital, closely read." Place-line, always in this order:
 * Ottawa · Gatineau · Eastern Ontario.
 *
 * Desk self-sufficiency: local-news, politics, culture and opinion
 * exist network-wide (politics arrived with Kitchener, culture with
 * Edmonton); `gatineau` and `corridor` are new and are created on the
 * first seed. All six are listed so the pack stands alone. The brand
 * deck is explicit that Gatineau gets a desk, not a "Quebec page".
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['rideaureview.ca', 'www.rideaureview.ca'],

    'desks' => [
        ['name' => 'Local News', 'slug' => 'local-news', 'color' => '#6B1C28', 'description' => 'City Hall, the wards, and the federal city inside a municipal one — housing, water and the LRT on a 24-ward map.'],
        ['name' => 'Gatineau',   'slug' => 'gatineau',   'color' => '#6B1C28', 'description' => 'The other half of the capital. Hôtel de ville, the Outaouais, and the budgets that arrive in French on this side of the river.'],
        ['name' => 'Corridor',   'slug' => 'corridor',   'color' => '#6B1C28', 'description' => 'Perth, Smiths Falls, Merrickville and the eastern Ontario towns that live in the capital\'s orbit — not a scenic caption.'],
        ['name' => 'Politics',   'slug' => 'politics',   'color' => '#6B1C28', 'description' => 'Where federal decisions land on municipal ground: consultations, mandates and the bridges between them.'],
        ['name' => 'Culture',    'slug' => 'culture',    'color' => '#6B1C28', 'description' => 'The stages, museums and festivals of a two-city capital, in both languages.'],
        ['name' => 'Opinion',    'slug' => 'opinion',    'color' => '#6B1C28', 'description' => 'Signed columns, editorials and letters, always labelled. The editorial position is the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'The Rideau Review',
        'tagline'            => 'The capital, closely read.',
        'meta_description'   => 'The Rideau Review is an independent digital newsroom for Ottawa, Gatineau and Eastern Ontario. We report the record. Then we review it.',
        'footer_line'        => 'Independent news for Ottawa, Gatineau and Eastern Ontario.',
        'contact_email'      => 'tips@rideaureview.ca',
        'newsletter_heading' => 'The Evening Briefing',
        'newsletter_copy'    => 'The capital\'s day, closely read — council, the bridges and the budgets, five evenings a week, across the river and down the canal.',
        'weather_line'       => '4°C|Ottawa–Gatineau',
        'regions'            => json_encode([
            'ottawa'          => 'Ottawa',
            'gatineau'        => 'Gatineau',
            'eastern-ontario' => 'Eastern Ontario',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['CBC Ottawa',    'https://www.cbc.ca/webfeed/rss/rss-canada-ottawa', 'ottawa'],
        ['Le Droit',      'https://www.ledroit.com/rss',                      'gatineau'],
        ['CBC News',      'https://www.cbc.ca/webfeed/rss/rss-canada',        'eastern-ontario'],
    ],

    /* Inaugural service content only (added Sep 2026): every story below
       is ABOUT the paper — mission, method, how to reach the newsroom.
       True by construction; no invented local events, votes or figures
       (CLAUDE.md debt item 1 must not grow with launches). Real
       reporting arrives through the newsroom or Hermes. */
    'stories' => [
        [
            'title'     => 'We report the record. Then we review it.',
            'slug'      => 'rideau-first-editorial',
            'desk'      => 'opinion',
            'byline'    => 'The Editorial Board',
            'lede'      => 'The Rideau Review opens with a sentence that is also a method — and a promise about how an independent newsroom for Ottawa, Gatineau and Eastern Ontario intends to keep it.',
            'body'      => '<p>The name is local on purpose. The Rideau made Bytown a city and still organizes the region\'s geography; a review is a second look — at council, at the bridges, at the budgets that arrive in English in one city and in French in the other. Put together: we report the record first, plainly, and then we read it closely.</p><p>The gap this paper fills is the river. English local news has long treated Gatineau as a view from the Ottawa side. The Review covers the whole map — which is why Gatineau has a desk here, not a page, and why the Corridor\'s towns are a beat rather than a scenic caption.</p><p>Hold us to three things. Tips sent to <a href="mailto:tips@rideaureview.ca">tips@rideaureview.ca</a> are read, all of them. Corrections run at the top of the story, dated. And opinion — including this page — is always signed and always labelled. The capital, closely read.</p>',
            'featured'  => 1,
            'published' => '2026-09-23 18:00:00',
            'tags'      => 'From the Review',
        ],
        [
            'title'     => 'Reading Ottawa closely: how the city desk will work',
            'slug'      => 'rideau-ottawa-desk-note',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'Ottawa is a federal city wrapped around a municipal one. The Ottawa desk covers the municipal one — agendas first, decisions as made, follow-through after.',
            'body'      => '<p>It is easy, in this town, for the municipal story to be crowded out by the national one happening up the street. This desk exists to keep the city itself in focus: the wards, the services, the budgets and the council decisions that shape daily life more directly than most of what makes the national news.</p><p>The method is the paper\'s method: read the agenda before the meeting, report the vote as recorded, identify the documents behind every story so the reading can be checked. Announcements are covered next to the record of what was previously promised.</p><p>This opening edition is about the paper rather than the city — an honest introduction beats invented news. The reporting begins as the newsroom files. Tips: <a href="mailto:tips@rideaureview.ca">tips@rideaureview.ca</a>.</p>',
            'published' => '2026-09-23 17:40:00',
            'tags'      => 'From the Review',
        ],
        [
            'title'     => 'Gatineau gets a desk, not a page',
            'slug'      => 'rideau-gatineau-desk-note',
            'desk'      => 'gatineau',
            'byline'    => 'The Newsroom',
            'lede'      => 'One region, two cities, two provinces, two languages — and, in this newsroom, one standing desk on the north side of the river. Here is why, and how it will work.',
            'body'      => '<p>A capital that cannot see across its own river is not covering itself. The institutions really are separate — different province, different city hall, different paperwork — but the region is one: the same commuters cross the same bridges, and a land-use decision on one bank is a traffic, housing and budget story on the other.</p><p>So Gatineau is a desk here, with the same rules as every other desk: agendas and records first, decisions as made, follow-through after. Where a story crosses the river — and the important ones usually do — the desk will report both halves rather than the half in English.</p><p>Documents on this beat arrive in French; the desk works in both languages. Tips, in either language: <a href="mailto:tips@rideaureview.ca">tips@rideaureview.ca</a>.</p>',
            'published' => '2026-09-23 17:20:00',
            'tags'      => 'From the Review',
        ],
        [
            'title'     => 'The Corridor is a beat, not a caption',
            'slug'      => 'rideau-corridor-desk-note',
            'desk'      => 'corridor',
            'byline'    => 'The Newsroom',
            'lede'      => 'The eastern Ontario towns in the capital\'s orbit usually appear in city media as scenery. The Corridor desk exists to cover them as places where decisions happen.',
            'body'      => '<p>Perth, Smiths Falls, Merrickville and the towns around them are routinely photographed and rarely covered. Yet they hold councils, budgets, hospitals and school boards like anywhere else — and their decisions are made with less scrutiny precisely because the nearest newsrooms sit in the capital.</p><p>This desk\'s commitment is to cover the corridor\'s civic life on its own terms: what its councils decide, what its services deliver, what its heritage rules mean in practice as planning law rather than postcard material.</p><p>The desk cannot be everywhere in a geography this wide, which makes tips from inside it worth more, not less: <a href="mailto:tips@rideaureview.ca">tips@rideaureview.ca</a>.</p>',
            'published' => '2026-09-23 17:00:00',
            'tags'      => 'From the Review',
        ],
        [
            'title'     => 'Where federal decisions land on municipal ground',
            'slug'      => 'rideau-politics-desk-note',
            'desk'      => 'politics',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Politics desk is not a Hill bureau. Its beat is the seam where federal processes meet the region that hosts them — consultations, properties, bridges and budgets.',
            'body'      => '<p>This region already has no shortage of national political coverage. What it lacks is coverage of the seam: the federal consultation that lands on a municipal ward, the crown property whose fate is decided in one process and lived with in another, the interprovincial file that no single government owns.</p><p>That seam is this desk\'s beat. Its method is to follow the process documents — who is consulting, on what timeline, with what authority — and to report where the decision actually sits, which in this region is often the most useful fact a story can carry.</p><p>When a federal calendar collides with a municipal one, the desk\'s job is to say so plainly and early.</p>',
            'published' => '2026-09-23 16:40:00',
            'tags'      => 'From the Review',
        ],
        [
            'title'     => 'A two-city capital, on stage in both languages',
            'slug'      => 'rideau-culture-desk-note',
            'desk'      => 'culture',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Culture desk covers the stages, museums, festivals and rooms of a region whose cultural life has never respected the river as a boundary.',
            'body'      => '<p>Culture in this region is one conversation held in two languages, and audiences cross the bridges for it nightly in both directions. The desk will cover it that way — the anglophone and francophone scenes as one beat, reviewed by people who go.</p><p>Expect working coverage rather than press-release culture: what is actually on, whether it is worth your evening, and the civic side of the file — the venues, the funding decisions, the institutions — reported with the same document-first method as every other desk.</p><p>Companies, venues and festivals: tell the desk what is coming at <a href="mailto:tips@rideaureview.ca">tips@rideaureview.ca</a>.</p>',
            'published' => '2026-09-23 16:20:00',
            'tags'      => 'From the Review',
        ],
    ],
];
