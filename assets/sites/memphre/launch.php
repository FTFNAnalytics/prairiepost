<?php
/**
 * Le Memphré — launch pack (foundation, Nov 2026; brand guidelines
 * applied 28 Nov 2026). Seeded once by
 * `PP_SITE=memphre php tools/seed-launch.php`, at launch. Never before:
 * see DEPLOY-MEMPHRE.md.
 *
 * ZERO STORIES SO FAR: identity, desks and wire sources only. The owner's
 * guidelines and draft site are in English; these words are their lines
 * in French (the tagline is the guidelines' subtitle, the footer line the
 * draft site's), and the footprint is theirs: Sherbrooke and the Eastern
 * Townships. Whether the paper publishes in French or English is the
 * owner's open question (docs/brand-packages.md). The newsletter name is
 * still a placeholder, and the six-story inaugural edition (launch notes
 * about the paper itself, true by construction; never invented local
 * news, CLAUDE.md debt item 1) waits on the owner's answers.
 *
 * Covers: Sherbrooke and the Eastern Townships (Estrie), Magog and Lake Memphrémagog among them. Named for Memphré, the lake's legendary serpent.
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
    'domains' => ['memphre.ca', 'www.memphre.ca'],

    'desks' => [
        ['name' => 'Actualités', 'slug' => 'actualites', 'color' => '#1A747C', 'description' => 'Sherbrooke, Magog et les municipalités des Cantons-de-l’Est : les villes, les quartiers et les gens qui y vivent.'],
        ['name' => 'Économie', 'slug' => 'economie', 'color' => '#1A747C', 'description' => 'Les commerces, le tourisme et les entreprises de la région.'],
        ['name' => 'Environnement', 'slug' => 'environnement', 'color' => '#1A747C', 'description' => 'Les lacs, les rivières et les forêts de l’Estrie : la qualité de l’eau, la faune et le territoire.'],
        ['name' => 'Culture', 'slug' => 'culture-qc', 'color' => '#1A747C', 'description' => 'Les scènes, les festivals et les artistes de Sherbrooke et des Cantons-de-l’Est.'],
        ['name' => 'Sports', 'slug' => 'sports-qc', 'color' => '#1A747C', 'description' => 'Les équipes, les athlètes et le plein air de la région.'],
        ['name' => 'Idées', 'slug' => 'idees', 'color' => '#1A747C', 'description' => 'Chroniques et lettres signées, toujours identifiées comme opinions.'],
    ],

    'settings' => [
        'site_title' => 'Le Memphré',
        'tagline' => 'L’actualité de Sherbrooke et des Cantons-de-l’Est',
        'meta_description' => 'Le Memphré est un média d’information locale indépendant consacré à Sherbrooke et aux Cantons-de-l’Est : les villes, les lacs, l’économie et la culture de l’Estrie.',
        'footer_line' => 'L’information indépendante de Sherbrooke et des Cantons-de-l’Est.',
        'newsletter_heading' => 'L’infolettre du Memphré',
        'newsletter_copy' => 'La une du Memphré dans votre boîte courriel chaque matin de semaine : ce que Sherbrooke et les Cantons-de-l’Est ont décidé, construit et débattu.',
        'regions' => json_encode([
            'sherbrooke' => 'Sherbrooke',
            'magog' => 'Magog et le lac',
            'estrie' => 'Estrie',
        ], JSON_UNESCAPED_UNICODE),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). Each
       answered with RSS on 28 Nov 2026; the Sherbrooke Record's with 10
       items. */
    'sources' => [
        ['Radio-Canada · Estrie', 'https://ici.radio-canada.ca/info/rss/regions/estrie/en-continu', 'estrie'],
        ['Le Reflet du Lac', 'https://www.lerefletdulac.com/feed/', 'magog'],
        ['Sherbrooke Record', 'https://www.sherbrookerecord.com/feed/', 'sherbrooke'],
    ],

    /* Intentionally empty: the brand build writes the inaugural edition. */
    'stories' => [],
];
