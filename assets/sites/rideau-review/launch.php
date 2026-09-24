<?php
/**
 * The Rideau Review — launch pack (brand build, Sep 2026).
 * Seeded once by `PP_SITE=rideau-review php tools/seed-launch.php`.
 *
 * ZERO STORIES BY DESIGN — identity, desks and wire sources only; the
 * front page carries its empty state until the newsroom files.
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

    /* Intentionally empty — see the header. */
    'stories' => [],
];
