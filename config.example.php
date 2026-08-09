<?php
/**
 * The Prairie Post — site configuration.
 *
 * Copy this file to config.php and adjust for your server. The site runs on
 * SQLite out of the box (nothing to configure); switch the driver to 'mysql'
 * on shared hosting if you prefer a MySQL database.
 */
return [
    'db' => [
        'driver' => 'sqlite',                                // 'sqlite' or 'mysql'
        'sqlite_path' => __DIR__ . '/data/prairiepost.sqlite',
        'mysql' => [
            'host'    => 'localhost',
            'name'    => 'prairiepost',
            'user'    => 'prairiepost',
            'pass'    => '',
            'charset' => 'utf8mb4',
        ],
    ],

    // Canonical site URL, no trailing slash (e.g. 'https://prairiepost.com').
    // Leave empty to auto-detect from the request.
    'site_url' => '',

    'timezone' => 'America/Edmonton',

    // Show full error output. Never enable in production.
    'debug' => false,
];
