<?php
/**
 * Les Chutes — launch pack (foundation, Nov 2026; brand package applied
 * 28 Nov 2026). Seeded once by `PP_SITE=chutes php tools/seed-launch.php`,
 * at launch. Never before: see DEPLOY-CHUTES.md.
 *
 * ZERO STORIES SO FAR: identity, desks and wire sources only. The tagline
 * is the package's homepage line, and the footprint is its README's:
 * "Québec et l'est du Québec". The desk colour is its Bleu Québec. The
 * newsletter name is still a placeholder, and the six-story inaugural
 * edition (launch notes about the paper itself, true by construction;
 * never invented local news, CLAUDE.md debt item 1) waits on the owner's
 * answers in docs/brand-packages.md.
 *
 * Covers: Quebec City and eastern Quebec. Named for Montmorency Falls, 83 m high. Plural on purpose: "La Chute" sounds like Lachute.
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
        ['name' => 'Actualités', 'slug' => 'actualites', 'color' => '#325EA8', 'description' => 'La ville de Québec et ses quartiers : le logement, le transport et les gens qui y vivent.'],
        ['name' => 'Politique', 'slug' => 'politique', 'color' => '#325EA8', 'description' => 'L’hôtel de ville et l’Assemblée nationale, et ce que leurs décisions changent pour la capitale.'],
        ['name' => 'Économie', 'slug' => 'economie', 'color' => '#325EA8', 'description' => 'Le port, la fonction publique, le tourisme et les entreprises de la région de Québec.'],
        ['name' => 'Culture', 'slug' => 'culture-qc', 'color' => '#325EA8', 'description' => 'Les scènes, les festivals et les artistes de la capitale.'],
        ['name' => 'Sports', 'slug' => 'sports-qc', 'color' => '#325EA8', 'description' => 'Les équipes et les athlètes de Québec, de l’aréna au terrain.'],
        ['name' => 'Idées', 'slug' => 'idees', 'color' => '#325EA8', 'description' => 'Chroniques et lettres signées, toujours identifiées comme opinions.'],
    ],

    'settings' => [
        'site_title' => 'Les Chutes',
        'tagline' => 'L’actualité de Québec et de l’Est-du-Québec',
        'meta_description' => 'Les Chutes couvre Québec et l’Est-du-Québec : la capitale et l’Assemblée nationale, l’économie, la culture et les régions de l’Est.',
        'footer_line' => 'Le journal de la capitale et de l’Est-du-Québec, de la falaise au fleuve.',
        'newsletter_heading' => 'L’infolettre des Chutes',
        'newsletter_copy' => 'La une des Chutes dans votre boîte courriel chaque matin de semaine : ce que Québec et l’Est-du-Québec ont décidé, construit et débattu.',
        'regions' => json_encode([
            'quebec' => 'Québec',
            'capitale-nationale' => 'Capitale-Nationale',
            'est-du-quebec' => 'Est-du-Québec',
        ], JSON_UNESCAPED_UNICODE),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). Each
       answered with RSS on 28 Nov 2026; the three eastern regions' with
       50 items each. */
    'sources' => [
        ['Radio-Canada · Québec', 'https://ici.radio-canada.ca/info/rss/regions/quebec/en-continu', 'quebec'],
        ['Le Journal de Québec', 'https://www.journaldequebec.com/rss.xml', 'quebec'],
        ['Monquartier', 'https://monquartier.quebec/feed/', 'quebec'],
        ['Radio-Canada · Bas-Saint-Laurent', 'https://ici.radio-canada.ca/info/rss/regions/bas-saint-laurent/en-continu', 'est-du-quebec'],
        ['Radio-Canada · Gaspésie–Îles-de-la-Madeleine', 'https://ici.radio-canada.ca/info/rss/regions/gaspesie/en-continu', 'est-du-quebec'],
        ['Radio-Canada · Côte-Nord', 'https://ici.radio-canada.ca/info/rss/regions/cote-nord/en-continu', 'est-du-quebec'],
    ],

    /* Intentionally empty: the brand build writes the inaugural edition. */
    'stories' => [],
];
