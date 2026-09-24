<?php
/**
 * The Cariboo Compass — launch pack (brand build, Sep 2026).
 * Seeded once by `PP_SITE=cariboo-compass php tools/seed-launch.php`.
 *
 * INAUGURAL SERVICE CONTENT ONLY — identity, desks, wire sources and
 * six launch notes ABOUT the paper (mission, desk methods, how to
 * reach the newsroom). True by construction; no invented local news.
 *
 * Identity from the owner's brand package: the gold compass rose under
 * the peaks on Deep Forest Green; serif headlines over a sans body;
 * Compass Gold for accents, Lake Blue for links. "Rugged BC wilderness
 * meets trustworthy journalism" — North & Central BC.
 *
 * Desk self-sufficiency: local-news, business, education, environment
 * and opinion exist network-wide (education arrived with Surrey,
 * environment with Burrard); `communities` is new and is created on
 * the first seed. All six are listed so the pack stands alone.
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['cariboocompass.ca', 'www.cariboocompass.ca'],

    'desks' => [
        ['name' => 'Local News',  'slug' => 'local-news',  'color' => '#1A3C34', 'description' => 'North and Central BC, reported from here — Prince George to 100 Mile House, with direction, integrity and care.'],
        ['name' => 'Communities', 'slug' => 'communities', 'color' => '#1A3C34', 'description' => 'Williams Lake, Quesnel, the Chilcotin and every town along the highway — the people and places that hold the region together.'],
        ['name' => 'Business',    'slug' => 'business',    'color' => '#1A3C34', 'description' => 'Mills, mines, main streets and the jobs that ride on them.'],
        ['name' => 'Environment', 'slug' => 'environment', 'color' => '#C9A227', 'description' => 'Wildfire seasons, the lakes and rivers, and the backcountry the Cariboo lives beside.'],
        ['name' => 'Education',   'slug' => 'education',   'color' => '#1A3C34', 'description' => 'Schools, the college and the university of the north.'],
        ['name' => 'Opinion',     'slug' => 'opinion',     'color' => '#1A3C34', 'description' => 'Signed columns and letters, always labelled. The editorial position is the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'The Cariboo Compass',
        'tagline'            => 'Cariboo News. Community First.',
        'meta_description'   => 'The Cariboo Compass covers North and Central BC — Prince George to the Chilcotin — with clear, accurate local reporting you can trust. Your true north for local news.',
        'footer_line'        => 'Covering the Cariboo. Connecting our communities.',
        'contact_email'      => 'tips@cariboocompass.ca',
        'newsletter_heading' => 'The Morning Bearing',
        'newsletter_copy'    => 'North and Central BC\'s news every weekday morning: what happened, what it means, and what to watch — your true north before the day starts.',
        'weather_line'       => '9°C|Nechako',
        'regions'            => json_encode([
            'prince-george' => 'Prince George',
            'cariboo'       => 'The Cariboo',
            'bc'            => 'British Columbia',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['Prince George Citizen',  'https://www.princegeorgecitizen.com/rss', 'prince-george'],
        ['Williams Lake Tribune',  'https://www.wltribune.com/feed',          'cariboo'],
        ['Global BC',              'https://globalnews.ca/bc/feed/',          'bc'],
    ],

    /* Inaugural service content only (added Sep 2026): every story below
       is ABOUT the paper — mission, method, how to reach the newsroom.
       True by construction; no invented local events, votes or figures
       (CLAUDE.md debt item 1 must not grow with launches). Real
       reporting arrives through the newsroom or Hermes. */
    'stories' => [
        [
            'title'     => 'Welcome aboard The Cariboo Compass',
            'slug'      => 'cariboo-welcome-aboard',
            'desk'      => 'opinion',
            'byline'    => 'The Editorial Board',
            'lede'      => 'North and Central BC has carried more than its share of news lately and had fewer and fewer people here to report it. This paper is a bearing on that problem.',
            'body'      => '<p>A compass does not tell you where to go. It tells you, reliably, where things stand — and lets you navigate from there. That is the job this paper takes on for the Cariboo: clear, accurate local reporting, from Prince George to 100 Mile House, gathered by people who answer to the region they cover.</p><p>Cariboo news, community first. The order of that sentence is the editorial policy: the desks exist to serve the towns, not the other way around. Each desk publishes its own note in this first edition saying how it intends to work; those notes are commitments you are invited to hold us to.</p><p>Two things you can rely on from the start. Tips sent to <a href="mailto:tips@cariboocompass.ca">tips@cariboocompass.ca</a> are read — all of them. And when we get something wrong, the correction runs at the top of the story, dated. True north for a newsroom is simply the truth, kept current.</p>',
            'featured'  => 1,
            'published' => '2026-09-23 18:00:00',
            'tags'      => 'From the Compass',
        ],
        [
            'title'     => 'How the Compass takes its bearings',
            'slug'      => 'cariboo-how-we-work',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'A guide to this paper\'s desks and the rules every story files under — documents first, named sources, and a hard line between news and opinion.',
            'body'      => '<p>News is the front door — what happened in the region today. Communities follows the towns along the highway. Business covers the mills, mines and main streets. Environment takes wildfire seasons and the backcountry as a year-round beat. Education runs from the schoolhouse to the university of the north. Opinion is signed and labelled, always.</p><p>The rules are the same at every desk: start from documents and records, name sources wherever possible, show where numbers come from, and never mix reporting with opinion. Wire items pointing at another outlet\'s work will say so and link to it.</p><p>This first edition is about the paper rather than the region — an honest introduction beats manufactured news. The reporting starts as the newsroom files, and the Morning Bearing newsletter will carry it to you every weekday.</p>',
            'published' => '2026-09-23 17:40:00',
            'tags'      => 'From the Compass',
        ],
        [
            'title'     => 'Every town on the highway: tell us where to look',
            'slug'      => 'cariboo-communities-desk-note',
            'desk'      => 'communities',
            'byline'    => 'The Newsroom',
            'lede'      => 'Williams Lake, Quesnel, the Chilcotin and every community between: the Communities desk opens by asking the people who live there to point it at what matters.',
            'body'      => '<p>A regional paper fails quietly by covering only the biggest town in the region. This desk exists to keep that from happening here — its beat is every community along the highway and off it, and its first act is an invitation.</p><p>What should we be at? Whose work holds your town together? What is changing that nobody outside it has noticed? Send it to <a href="mailto:tips@cariboocompass.ca">tips@cariboocompass.ca</a>. If you need to stay unnamed, say so, and we will talk about what protecting that means before anything runs.</p><p>The desk\'s promise in return: when we come to your town, we come to listen first.</p>',
            'published' => '2026-09-23 17:20:00',
            'tags'      => 'From the Compass',
        ],
        [
            'title'     => 'Main street first: how the Business desk will work',
            'slug'      => 'cariboo-business-desk-note',
            'desk'      => 'business',
            'byline'    => 'The Newsroom',
            'lede'      => 'The region\'s economy is mills, mines and main streets — and the jobs riding on all three. This desk will cover it from the ground, not from the quarterly report.',
            'body'      => '<p>Business coverage in a region like this one is not a stock ticker. It is whether the mill is hiring, what the mine\'s permit says, and whether the last hardware store on a main street stays open. This desk starts at that altitude and works up, not the reverse.</p><p>When the desk covers a closure, an opening or an investment, it will trace the decision to whoever made it and put the numbers in reach of anyone who wants to check them.</p><p>Owners, workers, buyers, sellers: the desk\'s door is <a href="mailto:tips@cariboocompass.ca">tips@cariboocompass.ca</a>.</p>',
            'published' => '2026-09-23 17:00:00',
            'tags'      => 'From the Compass',
        ],
        [
            'title'     => 'Covering wildfire seasons like the year-round story they are',
            'slug'      => 'cariboo-environment-desk-note',
            'desk'      => 'environment',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Environment desk\'s standing commitment: fire, water and the backcountry get covered before, during and after the emergency — not only at its peak.',
            'body'      => '<p>The region knows better than most that a fire season does not start with the first evacuation alert and does not end with the rain. Preparedness, response and recovery are one continuous story, and this desk commits to covering the whole arc — including the quiet months when the decisions that matter are actually made.</p><p>During active emergencies the desk\'s rule is strict: official sources for anything safety-critical, clearly timestamped, corrected at the top the moment anything changes. Speed never outranks accuracy when the information is what people act on.</p><p>The lakes, rivers and backcountry the Cariboo lives beside get the same treatment the rest of the year: a beat, not a backdrop.</p>',
            'published' => '2026-09-23 16:40:00',
            'tags'      => 'From the Compass',
        ],
        [
            'title'     => 'From the schoolhouse to the university of the north',
            'slug'      => 'cariboo-education-desk-note',
            'desk'      => 'education',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Education desk covers the full span — rural schools, the college and the university — and it wants parents, students and staff wired in from the first bell.',
            'body'      => '<p>Education in this region runs from one-hallway rural schools to the university of the north, and decisions about any part of it ripple through every town that sends students there. This desk will follow the span, not just the headlines at either end.</p><p>The fastest way for the coverage to matter is for the people inside it to point it: parents at the council table, students, teachers and staff who see where policy meets the classroom. Write to <a href="mailto:tips@cariboocompass.ca">tips@cariboocompass.ca</a> — and say if you need to stay unnamed, so we can talk about what that requires before anything is published.</p>',
            'published' => '2026-09-23 16:20:00',
            'tags'      => 'From the Compass',
        ],
    ],
];
