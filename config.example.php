<?php
/**
 * The Prairie Dispatch — site configuration.
 *
 * Copy this file to config.php and adjust for your server. Three database
 * drivers are supported:
 *
 *   sqlite — zero-config, single site, good for local work and simple deploys.
 *   pgsql  — a shared Postgres database (e.g. Supabase) that several sites in
 *            the network read and write together. Articles, authors, desks and
 *            the wire pool are shared; settings and subscribers are per-site.
 *   mysql  — a conventional single-site MySQL database on shared hosting.
 *
 * For Supabase, use the SESSION POOLER connection details (Dashboard →
 * Connect → Session pooler). The direct db.<ref>.supabase.co host is
 * IPv6-only and unreachable from most shared hosting.
 */
return [
    'db' => [
        'driver' => 'sqlite',                                // 'sqlite', 'pgsql' or 'mysql'
        'sqlite_path' => __DIR__ . '/data/prairiedispatch.sqlite',
        'pgsql' => [
            'host'    => 'aws-0-ca-central-1.pooler.supabase.com',
            'port'    => 5432,
            'name'    => 'postgres',
            'user'    => 'postgres.PROJECTREF',
            'pass'    => '',
            'sslmode' => 'require',
            // The Postgres schema (namespace) this app owns. It is created
            // automatically and keeps the network's tables fully isolated
            // from any other application sharing the same database — never
            // installs into 'public'. All network sites must use the same
            // value. Lowercase letters, digits, underscores only.
            'schema'  => 'prairiedispatch',
        ],
        'mysql' => [
            'host'    => 'localhost',
            'name'    => 'prairiedispatch',
            'user'    => 'prairiedispatch',
            'pass'    => '',
            'charset' => 'utf8mb4',
        ],
    ],

    // Which site of the network this deployment is. Every install with the
    // same shared database gets its own slug; the slug scopes which published
    // articles, settings and subscribers belong to this front end.
    'site_slug' => 'prairiedispatch',

    // Which site of the network is the master control room (the Civis Media
    // hub). When the current site's slug matches, /admin grows the network
    // pages — the network desk, the full newswire, the roadmap, inquiries.
    // Keyed to a slug, not a boolean, so a shared release directory serving
    // several domains stays correct: only the hub's host gets the control
    // room. Leave as-is even on the papers; it only takes effect on the hub.
    'hub_slug' => 'civismedia',

    // Canonical site URL, no trailing slash (e.g. 'https://prairiedispatch.com').
    // Leave empty to auto-detect from the request.
    'site_url' => '',

    'timezone' => 'America/Edmonton',

    // Show full error output. Never enable in production.
    'debug' => false,
];
