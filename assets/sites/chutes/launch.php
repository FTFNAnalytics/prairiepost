<?php
/**
 * Les Chutes — launch pack (FOUNDATION build, Nov 2026).
 * Seeded once by `PP_SITE=chutes php tools/seed-launch.php`, at
 * launch, after the brand build. Never before: see DEPLOY-CHUTES.md.
 *
 * ZERO STORIES BY DESIGN: identity, desks and wire sources only. The
 * brand build replaces the placeholder tagline, newsletter name and
 * colours, and writes the six-story inaugural edition (launch notes
 * about the paper itself, true by construction; never invented local
 * news, CLAUDE.md debt item 1).
 *
 * Covers: Quebec City. Named for Montmorency Falls, 83 m high. Plural on purpose: "La Chute" sounds like Lachute.
 *
 * Domains: unregistered when this was written (RDAP, 28 Nov 2026).
 * The owner registers them before any launch; a different hostname
 * in the brand package is a question for the owner, not a fix to apply.
 *
 * Desk self-sufficiency: every desk the pack lists already exists
 * network-wide; all are listed so the pack stands alone. palette.json
 * labels them for this paper (desk_labels).
 */

return [

    /* Every public hostname this paper answers on. The seeder writes these
       into the domains table, which bootstrap resolves tenants from. */
    'domains' => ['chutesquebec.ca', 'www.chutesquebec.ca'],

    'desks' => [
        ['name' => 'Actualités', 'slug' => 'actualites', 'color' => '#182330', 'description' => 'La ville de Québec et ses quartiers : le logement, le transport et les gens qui y vivent.'],
        ['name' => 'Politique', 'slug' => 'politique', 'color' => '#182330', 'description' => 'L’hôtel de ville et l’Assemblée nationale, et ce que leurs décisions changent pour la capitale.'],
        ['name' => 'Économie', 'slug' => 'economie', 'color' => '#182330', 'description' => 'Le port, la fonction publique, le tourisme et les entreprises de la région de Québec.'],
        ['name' => 'Culture', 'slug' => 'culture-qc', 'color' => '#182330', 'description' => 'Les scènes, les festivals et les artistes de la capitale.'],
        ['name' => 'Sports', 'slug' => 'sports-qc', 'color' => '#182330', 'description' => 'Les équipes et les athlètes de Québec, de l’aréna au terrain.'],
        ['name' => 'Idées', 'slug' => 'idees', 'color' => '#182330', 'description' => 'Chroniques et lettres signées, toujours identifiées comme opinions.'],
    ],

    'settings' => [
        'site_title' => 'Les Chutes',
        'tagline' => 'La capitale, de la falaise au fleuve',
        'meta_description' => 'Les Chutes est un journal de Québec : la ville, l’Assemblée nationale, l’économie et la culture de la capitale.',
        'footer_line' => 'Le journal de la capitale, de la falaise au fleuve.',
        'newsletter_heading' => 'L’infolettre des Chutes',
        'newsletter_copy' => 'La une des Chutes dans votre boîte courriel chaque matin de semaine : ce que la capitale a décidé, construit et débattu.',
        'regions' => json_encode([
            'quebec' => 'Québec',
            'capitale-nationale' => 'Capitale-Nationale',
        ], JSON_UNESCAPED_UNICODE),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). Each
       answered with RSS on 28 Nov 2026. */
    'sources' => [
        ['Radio-Canada · Québec', 'https://ici.radio-canada.ca/info/rss/regions/quebec/en-continu', 'quebec'],
        ['Le Journal de Québec', 'https://www.journaldequebec.com/rss.xml', 'quebec'],
        ['Monquartier', 'https://monquartier.quebec/feed/', 'quebec'],
    ],

    /* Intentionally empty: the brand build writes the inaugural edition. */
    'stories' => [],
];
