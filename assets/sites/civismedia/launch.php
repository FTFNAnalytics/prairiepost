<?php
/**
 * Civis Media — launch settings package.
 * Loaded once by `PP_SITE=civismedia php tools/seed-launch.php`.
 * The hub is a brochure, not a paper: no desks, no wire sources, no stories —
 * just the settings that put words on the front. Copy changes are made in
 * Settings afterwards; this package never overwrites a saved edit.
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['civismedia.ca', 'www.civismedia.ca'],

    'settings' => [
        'site_title'       => 'Civis Media',
        'tagline'          => 'Communications & advertising',
        'meta_description' => 'Civis Media is a communications and advertising practice: brand systems, publications, campaigns, and media placement for organizations that need to be understood.',
        'footer_line'      => 'Civis Media · Communications & advertising',

        /* --- The brochure front ------------------------------------------- */
        'civis_headline'   => 'Clear communications, placed well.',
        'civis_sub'        => 'Civis Media is a communications and advertising practice. We build brand systems, publications and campaigns for organizations that need to be understood — and we place them where they will actually be read.',
        'civis_services'   => json_encode([
            ['Brand & identity', 'Naming, wordmarks, and complete design systems — type, colour, and the rules that keep them coherent long after launch day.'],
            ['Publications & content', 'Editorial-grade writing and production: newsletters, reports, and web publications that read like they were made by people who read.'],
            ['Campaigns', 'Advertising built around one clear message, produced for print, digital and out-of-home, and measured against numbers that are real.'],
            ['Media placement', 'Planning and buying across community and regional media, with placements chosen for readership rather than volume.'],
        ]),
        'civis_approach'   => json_encode([
            ['Listen', 'Every engagement starts with the audience — who they are, what they read, and what they need to hear from you.'],
            ['Build', 'Words and design made to carry one message, in a system your own team can keep using without us.'],
            ['Place & measure', 'Work is placed where it will be seen, and reported on plainly — what ran, where, and what it did.'],
        ]),
    ],
];
