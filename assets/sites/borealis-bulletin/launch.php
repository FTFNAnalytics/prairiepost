<?php
/**
 * Borealis Bulletin — launch pack (foundation, Nov 2026; the owner's brand
 * package applied 28 Nov 2026). Seeded once by
 * `PP_SITE=borealis-bulletin php tools/seed-launch.php`, at launch. Never
 * before: see DEPLOY-BOREALIS.md.
 *
 * The package shipped this pack: its desks, words and six launch notes.
 * The brand build kept them, with four changes, because a launch note is
 * about the paper itself and true by construction (CLAUDE.md, debt item 1):
 * - every tip and correction goes through /tips on the paper itself, not a
 *   mailto to an address on a domain nobody has registered yet;
 * - statements about the North (its share of the country's land, its time
 *   zones, how fast it is warming, how other outlets cover it) became
 *   statements of what each desk covers and how;
 * - each note is dated when the seeder runs, newest first, so the edition
 *   carries the launch date;
 * - the contact_email setting is gone: only the hub reads it.
 *
 * Covers: Yukon, the Northwest Territories and Nunavut, with Inuit
 * Nunangat, the provincial Norths and the circumpolar Arctic beyond them,
 * as the package sets it. Home: Yellowknife.
 *
 * Domains: unregistered when this was written (RDAP, 28 Nov 2026). The
 * package names borealisbulletin.com beside the .ca; it is listed here only
 * once the owner has registered it and pointed it at the VPS, because the
 * launch's DNS gate checks every hostname in this list.
 *
 * Desk self-sufficiency: all six desks are listed. arctic-north is new
 * network-wide, so this pack's description becomes the network's; the
 * other five exist, and palette.json labels them for this paper.
 */

$ago = fn (string $offset): string => date('Y-m-d H:i:s', strtotime($offset));

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['borealisbulletin.ca', 'www.borealisbulletin.ca'],

    'desks' => [
        ['name' => 'Territories',    'slug' => 'local-news',   'color' => '#0F5243', 'description' => 'Yukon, the Northwest Territories and Nunavut: assemblies, councils, courts and daily life above 60°.'],
        ['name' => 'Arctic & North', 'slug' => 'arctic-north', 'color' => '#0B1E2D', 'description' => 'Inuit Nunangat, the provincial Norths, Arctic shipping, sovereignty, infrastructure and the circumpolar world.'],
        ['name' => 'Land & Climate', 'slug' => 'environment',  'color' => '#0F5243', 'description' => 'Permafrost, sea ice, boreal wildfire, caribou, watersheds and northern science.'],
        ['name' => 'Economy',        'slug' => 'business',     'color' => '#133147', 'description' => 'Critical minerals, winter roads, marine resupply, fisheries, aviation and the cost of northern living.'],
        ['name' => 'Communities',    'slug' => 'communities',  'color' => '#0F5243', 'description' => 'Housing, health care, northern arts, the Arctic Winter Games, and the fly-in and highway towns between the capitals.'],
        ['name' => 'Opinion',        'slug' => 'opinion',      'color' => '#9E4A1E', 'description' => 'Signed columns and letters from across the North, always labelled as opinion.'],
    ],

    'settings' => [
        'site_title' => 'Borealis Bulletin',
        'tagline' => 'Above the 60th. On the record.',
        'meta_description' => 'Borealis Bulletin is an independent newsroom covering Yukon, the Northwest Territories, Nunavut, Inuit Nunangat and the circumpolar North.',
        'footer_line' => 'Independent northern reporting from Whitehorse, Yellowknife, Iqaluit, and every latitude north of 60°.',
        'newsletter_heading' => 'The 60th Parallel Brief',
        'newsletter_copy' => 'Dispatches from Yukon, NWT, Nunavut and the Arctic every weekday morning: what happened, what it means on the ground, and what is moving north.',
        'regions' => json_encode([
            'yukon' => 'Yukon',
            'northwest-territories' => 'Northwest Territories',
            'nunavut' => 'Nunavut',
            'north' => 'The North',
            'circumpolar' => 'Circumpolar North',
        ], JSON_UNESCAPED_UNICODE),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). Each
       answered with RSS on 28 Nov 2026: CBC North 20 items, Cabin Radio 10,
       Nunatsiaq News 25, Yukon News 10, Eye on the Arctic 20. */
    'sources' => [
        ['CBC North', 'https://www.cbc.ca/webfeed/rss/rss-canada-north', 'north'],
        ['Cabin Radio', 'https://cabinradio.ca/feed/', 'northwest-territories'],
        ['Nunatsiaq News', 'https://nunatsiaq.com/feed/', 'nunavut'],
        ['Yukon News', 'https://yukon-news.com/feed/', 'yukon'],
        ['Eye on the Arctic', 'https://www.rcinet.ca/eye-on-the-arctic/feed/', 'circumpolar'],
    ],

    /* The inaugural edition: six launch notes about the paper itself, one
       per desk, newest first, dated at seed time. */
    'stories' => [
        [
            'title'     => 'Above the 60th. On the record.',
            'slug'      => 'borealis-first-editorial',
            'desk'      => 'opinion',
            'byline'    => 'The Editorial Board',
            'lede'      => 'Borealis Bulletin opens with a plain promise: to treat the Canadian North and the Arctic as home, not as a distant backdrop for southern headlines.',
            'body'      => '<p>Borealis Bulletin is a newspaper for the North: Yukon, the Northwest Territories and Nunavut, and the Arctic beyond them. It is written for the people who live here, and it exists to stay on the beat after the charter leaves.</p><p>Our masthead carries Polaris above an auroral ribbon and the 60th-parallel horizon: the sky and the land that every community north of sixty shares, and a public record anyone can check.</p><p>Hold us to three commitments. First, documents and primary sources before characterizations: assembly transcripts, land and water board filings, marine notices and municipal records. Second, coverage that reaches beyond Whitehorse, Yellowknife and Iqaluit to the fly-in communities, the winter-road towns and our circumpolar neighbours. Third, dated corrections at the top of any story we get wrong, and a hard line between reporting and signed opinion.</p><p>Opinion, this page included, is always signed and always labelled. And the newsroom wants to hear from you: tips sent through our <a href="/tips">tips form</a> go straight to the newsroom, and the form keeps only what you type.</p>',
            'featured'  => 1,
            'published' => $ago('-5 minutes'),
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'How the Bulletin covers the Territories and the Arctic',
            'slug'      => 'borealis-how-we-work',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'A guide to Borealis Bulletin\'s six desks, our footprint from the Yukon border to Baffin Bay, and the rules every story files under.',
            'body'      => '<p>Territories is our front door: legislative assemblies, municipal councils, courts, highways and daily public life across Yukon, the Northwest Territories and Nunavut. Arctic &amp; North widens the frame to Inuit Nunangat, including Nunavik and Nunatsiavut, the provincial Norths, Arctic shipping, defence and circumpolar affairs from Alaska to Greenland and Svalbard.</p><p>Land &amp; Climate follows permafrost, sea ice, wildfire, wildlife co-management and watershed science. Economy tracks the working North: critical minerals, winter roads, sealift schedules, hydro, fisheries, aviation and grocery bills. Communities stays close to housing, health care, northern arts and the people holding remote towns together. Opinion is always signed and always labelled.</p><p>The rules are the same at every desk: documents and records first, named sources wherever possible, and a clear line between reporting and opinion. Times printed on the Bulletin are Yellowknife time.</p><p>This inaugural edition introduces the paper rather than inventing news. The reporting starts as the newsroom files.</p>',
            'published' => $ago('-25 minutes'),
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'From the Beaufort Sea to Baffin Bay: our Arctic & Circumpolar beat',
            'slug'      => 'borealis-arctic-desk-note',
            'desk'      => 'arctic-north',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Arctic & North desk follows the circumpolar world from a northern vantage point: the Northwest Passage, Nuuk, Anchorage and northern Quebec, read for what they mean in Canada\'s territories.',
            'body'      => '<p>Geography does not stop at territorial borders, and neither does this desk. It covers ice conditions in the Arctic channels, telecommunications across the high Arctic, federal icebreaker procurement, and policy in Greenland, Alaska and the Nordic countries, read for what each means in northern Canadian communities.</p><p>The Arctic &amp; North desk reads marine advisories, parliamentary committee evidence, regional corporation reports and Arctic Council working papers before summarizing what they mean on the coast and inland.</p><p>Researchers, mariners, community leaders and northern observers with documents or leads can reach the desk through our <a href="/tips">tips form</a>.</p>',
            'published' => $ago('-45 minutes'),
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'Winter roads, sealift and the real northern economy',
            'slug'      => 'borealis-economy-desk-note',
            'desk'      => 'business',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Economy desk reports the North\'s economy as northerners live it: ice roads, barge windows, diesel rates, mineral royalties and the price of groceries on the shelf.',
            'body'      => '<p>The desk follows what moves household and community budgets above the 60th parallel: the length of the winter-road season, the marine resupply schedule, mine remediation, and the routes regional airlines fly.</p><p>Our Economy desk starts with primary numbers: territorial budgets, energy utility filings, Nutrition North data, and mining and co-management filings. It always states where the figures came from and who they land on.</p><p>If a supply chain, project or price shift is changing life in your region, tell the desk through our <a href="/tips">tips form</a>.</p>',
            'published' => $ago('-65 minutes'),
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'Land, ice and boreal sky: how we report climate and science',
            'slug'      => 'borealis-land-climate-note',
            'desk'      => 'environment',
            'byline'    => 'The Newsroom',
            'lede'      => 'Our Land & Climate desk pairs primary field data with observation from the people who live on the land.',
            'body'      => '<p>The desk covers permafrost under runways and homes, freeze-up and break-up dates, the fire season, reservoir levels and caribou surveys: the climate as northerners meet it, in their infrastructure and on the land.</p><p>The Land &amp; Climate desk reports from primary studies, hydrometric gauges, fire-management bulletins and land users\' observations. It links readers to the underlying data wherever it can, and keeps scientific uncertainty plain rather than sensational.</p>',
            'published' => $ago('-85 minutes'),
            'tags'      => 'From the Bulletin',
        ],
        [
            'title'     => 'Corrections, news tips and reaching the newsroom across 867 and beyond',
            'slug'      => 'borealis-service-note',
            'desk'      => 'communities',
            'byline'    => 'The Newsroom',
            'lede'      => 'Our standing day-one service note: how to send a tip from any northern community, how our corrections policy works, and what we promise in return.',
            'body'      => '<p>A northern paper fails if it only hears from the three capitals. Whether you live in Dawson City, Inuvik, Hay River, Rankin Inlet, Pond Inlet, Kuujjuaq, Happy Valley-Goose Bay, Churchill or anywhere else north, our newsroom wants to hear what is happening where you are.</p><p>Send tips through our <a href="/tips">tips form</a>, which keeps only what you type. Tell us up front if you need to remain unnamed, so we can explain how we protect confidential sources before anything is published. The strongest tips arrive with something we can check: a council packet, a notice, a date or a document.</p><p>When we make a factual error, the correction runs at the top of the story, dated, and remains part of the record. Send correction requests through the same form, naming the story and what is wrong. Every correction is listed on our <a href="/corrections">corrections page</a>.</p>',
            'published' => $ago('-105 minutes'),
            'tags'      => 'From the Bulletin',
        ],
    ],
];
