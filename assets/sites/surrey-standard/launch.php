<?php
/**
 * Surrey Standard — launch pack (foundation build).
 * Seeded once by `PP_SITE=surrey-standard php tools/seed-launch.php`.
 *
 * INAUGURAL SERVICE CONTENT ONLY — identity, desks, wire sources and
 * six launch notes ABOUT the paper (mission, desk methods, how to
 * reach the newsroom). True by construction; no invented local news.
 *
 * Desk self-sufficiency: local-news, city-hall, development, sports and
 * opinion exist network-wide; `education` is new and is created on the
 * first seed. All six are listed here so the pack stands alone.
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['surreystandard.ca', 'www.surreystandard.ca'],

    'desks' => [
        ['name' => 'Local News',  'slug' => 'local-news',  'color' => '#1B2A4A', 'description' => 'Surrey and White Rock, reported from the ground — six town centres, one newsroom.'],
        ['name' => 'City Hall',   'slug' => 'city-hall',   'color' => '#1B2A4A', 'description' => 'Council, the budget and the police transition file, from the agenda forward.'],
        ['name' => 'Development', 'slug' => 'development', 'color' => '#1B2A4A', 'description' => 'The fastest-growing city in British Columbia, one rezoning at a time.'],
        ['name' => 'Education',   'slug' => 'education',   'color' => '#1B2A4A', 'description' => 'The province\'s largest school district: enrolment, portables, and the capital plan.'],
        ['name' => 'Sports',      'slug' => 'sports',      'color' => '#1B2A4A', 'description' => 'From the Eagles to the leagues that play on Newton\'s fields.'],
        ['name' => 'Opinion',     'slug' => 'opinion',     'color' => '#1B2A4A', 'description' => 'Signed columns and letters, always labelled. The editorial position is the board\'s alone.'],
    ],

    'settings' => [
        'site_title'         => 'The Surrey Standard',
        'tagline'            => 'News that matters. Stories that connect.',
        'meta_description'   => 'The Surrey Standard is the paper of record south of the Fraser — council, growth, schools and the daily life of British Columbia\'s fastest-growing city.',
        'footer_line'        => 'Independent local journalism for a stronger, more connected Surrey.',
        'contact_email'      => 'tips@surreystandard.ca',
        'newsletter_heading' => 'Stay in the Know',
        'newsletter_copy'    => 'The latest Surrey news delivered to your inbox every weekday — what the city decided, approved and built, before your first coffee.',
        'weather_line'       => '16°C|Bear Creek',
        'regions'            => json_encode([
            'surrey' => 'Surrey',
            'bc'     => 'British Columbia',
        ]),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). */
    'sources' => [
        ['Surrey Now-Leader', 'https://www.surreynowleader.com/feed',  'surrey'],
        ['Peace Arch News',   'https://www.peacearchnews.com/feed',    'surrey'],
        ['Global BC',         'https://globalnews.ca/bc/feed/',        'bc'],
    ],

    /* Inaugural service content only (added Sep 2026): every story below
       is ABOUT the paper — mission, method, how to reach the newsroom.
       True by construction; no invented local events, votes or figures
       (CLAUDE.md debt item 1 must not grow with launches). Real
       reporting arrives through the newsroom or Hermes. */
    'stories' => [
        [
            'title'     => 'Welcome to The Surrey Standard',
            'slug'      => 'surrey-welcome-to-the-standard',
            'desk'      => 'opinion',
            'byline'    => 'The Editorial Board',
            'lede'      => 'Surrey is one of the fastest-changing cities in the country, and it deserves a newsroom that keeps pace. Here is what this paper is for, and how to hold it to that.',
            'body'      => '<p>The Surrey Standard starts from a simple premise: news that matters, stories that connect. The first half of that sentence is a promise about substance — what the city decides, approves and builds, reported plainly. The second half is a promise about people — that the point of covering a city is the community living in it.</p><p>We are independent and local. Our desks are listed in the navigation above, and each will publish a note in this first edition explaining how it intends to work. Those notes are commitments, not decoration; when we fall short of them, say so.</p><p>Two standing invitations. First, tips: the newsroom reads everything sent to <a href="mailto:tips@surreystandard.ca">tips@surreystandard.ca</a>. Second, corrections: when we get something wrong, the correction runs at the top of the story, dated. A paper earns trust by how it behaves when it errs.</p>',
            'featured'  => 1,
            'published' => '2026-09-23 18:00:00',
            'tags'      => 'From the Standard',
        ],
        [
            'title'     => 'What the Standard will cover — and how to hold us to it',
            'slug'      => 'surrey-what-we-cover',
            'desk'      => 'local-news',
            'byline'    => 'The Newsroom',
            'lede'      => 'A desk-by-desk guide to this paper: what each section is for, where the coverage starts, and the standards every story is filed under.',
            'body'      => '<p>Surrey News is the front door — the desk for what happened in the city today. City Hall follows the council calendar and the decisions made on it. Development tracks what is proposed, approved and built. Education covers the schools and the people in them. Sports starts with local leagues. Opinion is always signed and always labelled.</p><p>Every desk works from the same rules: documents before characterizations, named sources wherever possible, and a clear line between reporting and opinion. Wire summaries that point to another outlet\'s work will say so and link to it.</p><p>This first edition is deliberately about the paper itself rather than the city — we would rather open with an honest introduction than with filler dressed up as news. The reporting starts as the newsroom files. If you want it delivered, the Stay in the Know newsletter goes out every weekday.</p>',
            'published' => '2026-09-23 17:40:00',
            'tags'      => 'From the Standard',
        ],
        [
            'title'     => 'Our City Hall desk starts with the agenda, not the press release',
            'slug'      => 'surrey-city-hall-desk-note',
            'desk'      => 'city-hall',
            'byline'    => 'The Newsroom',
            'lede'      => 'How the Standard intends to cover council: read the agenda first, attend the vote, report the decision — and keep the record straight afterwards.',
            'body'      => '<p>Council coverage goes wrong in a predictable way: it starts from what an office announces rather than from what the meeting decides. This desk commits to working the other way around — agendas and staff reports first, the vote as it happened, and the follow-through months later when the decision meets the ground.</p><p>We will tell you where a number comes from every time we print one. When we summarize a debate, the full record it came from will be identified so you can check our reading against it.</p><p>Watching something at City Hall you think the city should know about? The desk reads everything sent to <a href="mailto:tips@surreystandard.ca">tips@surreystandard.ca</a>.</p>',
            'published' => '2026-09-23 17:20:00',
            'tags'      => 'From the Standard',
        ],
        [
            'title'     => 'Reading Surrey\'s growth: applications first, ribbons later',
            'slug'      => 'surrey-development-desk-note',
            'desk'      => 'development',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Development desk will follow projects from application to occupancy — because the important decisions happen long before the groundbreaking photo.',
            'body'      => '<p>By the time a ribbon is cut, the interesting questions were settled years earlier: what was applied for, what was varied, what was traded in the approval. This desk will track projects across that whole arc, not just at the ends of it.</p><p>Expect the coverage to be document-driven — applications, hearings and permits — and to keep a running record, so that what was promised at approval can be compared with what was built.</p><p>If you live next to a project we should be watching, tell us: <a href="mailto:tips@surreystandard.ca">tips@surreystandard.ca</a>.</p>',
            'published' => '2026-09-23 17:00:00',
            'tags'      => 'From the Standard',
        ],
        [
            'title'     => 'School communities: tell us what we should be seeing',
            'slug'      => 'surrey-education-desk-note',
            'desk'      => 'education',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Education desk covers one of the largest school communities in the province. The fastest way for it to matter is for parents, students and staff to point it at what counts.',
            'body'      => '<p>Education reporting lives or dies on access to the people inside it — parents at the council table, students in the hallway, staff who see the gap between a policy and a classroom. This desk\'s first ask is simple: tell us what we should be seeing.</p><p>Tips can be sent to <a href="mailto:tips@surreystandard.ca">tips@surreystandard.ca</a>. Say if you need to stay unnamed; we will discuss what protecting that looks like before anything is published. We do not print what we cannot verify, which means the best tips come with something we can check.</p>',
            'published' => '2026-09-23 16:40:00',
            'tags'      => 'From the Standard',
        ],
        [
            'title'     => 'Local sports coverage starts with your leagues',
            'slug'      => 'surrey-sports-desk-note',
            'desk'      => 'sports',
            'byline'    => 'The Newsroom',
            'lede'      => 'The Sports desk\'s beat is the city\'s own fields, rinks and courts — and it is asking leagues, clubs and schools to get in touch from day one.',
            'body'      => '<p>There is no shortage of coverage of professional sport; the gap is local. This desk exists for Surrey\'s own leagues, clubs and school teams — the results, the seasons and the people who run them.</p><p>Leagues and clubs: send schedules, results and contacts to <a href="mailto:tips@surreystandard.ca">tips@surreystandard.ca</a> and the desk will follow. Community sport only works as a beat if the community wires it up, and this note is the invitation.</p>',
            'published' => '2026-09-23 16:20:00',
            'tags'      => 'From the Standard',
        ],
    ],
];
