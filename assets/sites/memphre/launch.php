<?php
/**
 * Le Memphré — launch pack (FOUNDATION build, Nov 2026).
 * Seeded once by `PP_SITE=memphre php tools/seed-launch.php`, at
 * launch, after the brand build. Never before: see DEPLOY-MEMPHRE.md.
 *
 * ZERO STORIES BY DESIGN: identity, desks and wire sources only. The
 * brand build replaces the placeholder tagline, newsletter name and
 * colours, and writes the six-story inaugural edition (launch notes
 * about the paper itself, true by construction; never invented local
 * news, CLAUDE.md debt item 1).
 *
 * Covers: Magog and Lake Memphrémagog. Named for Memphré, the lake's legendary monster: the playful title on the slate.
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
        ['name' => 'Actualités', 'slug' => 'actualites', 'color' => '#182330', 'description' => 'Magog et les municipalités autour du lac Memphrémagog : la ville, les quartiers et les gens qui y vivent.'],
        ['name' => 'Économie', 'slug' => 'economie', 'color' => '#182330', 'description' => 'Les commerces, le tourisme et les entreprises de la région.'],
        ['name' => 'Environnement', 'slug' => 'environnement', 'color' => '#182330', 'description' => 'Le lac, ses rives et son bassin versant : la qualité de l’eau, la faune et le territoire.'],
        ['name' => 'Culture', 'slug' => 'culture-qc', 'color' => '#182330', 'description' => 'Les scènes, les festivals et les artistes de Magog et des environs.'],
        ['name' => 'Sports', 'slug' => 'sports-qc', 'color' => '#182330', 'description' => 'Les équipes, les athlètes et le plein air de la région.'],
        ['name' => 'Idées', 'slug' => 'idees', 'color' => '#182330', 'description' => 'Chroniques et lettres signées, toujours identifiées comme opinions.'],
    ],

    'settings' => [
        'site_title' => 'Le Memphré',
        'tagline' => 'Magog et le lac Memphrémagog',
        'meta_description' => 'Le Memphré est le journal de Magog et du lac Memphrémagog : la ville, le lac, l’économie et la culture de la région.',
        'footer_line' => 'Le journal de Magog et du lac Memphrémagog.',
        'newsletter_heading' => 'L’infolettre du Memphré',
        'newsletter_copy' => 'La une du Memphré dans votre boîte courriel chaque matin de semaine : ce que Magog et le lac ont décidé, construit et débattu.',
        'regions' => json_encode([
            'magog' => 'Magog',
            'estrie' => 'Estrie',
        ], JSON_UNESCAPED_UNICODE),
    ],

    /* The dashboard's story-idea feed (write-nothing wire pull). Each
       answered with RSS on 28 Nov 2026. */
    'sources' => [
        ['Radio-Canada · Estrie', 'https://ici.radio-canada.ca/info/rss/regions/estrie/en-continu', 'estrie'],
        ['Le Reflet du Lac', 'https://www.lerefletdulac.com/feed/', 'magog'],
    ],

    /* Intentionally empty: the brand build writes the inaugural edition. */
    'stories' => [],
];
