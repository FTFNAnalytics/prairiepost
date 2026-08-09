<?php
/** The Prairie Post — schema install. Supports SQLite (default) and MySQL. */

function pp_schema_installed(PDO $pdo, string $driver): bool
{
    try {
        if ($driver === 'mysql') {
            $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
            return $stmt !== false && $stmt->fetch() !== false;
        }
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'settings'");
        return $stmt !== false && $stmt->fetch() !== false;
    } catch (PDOException) {
        return false;
    }
}

function pp_install(PDO $pdo, string $driver): void
{
    $mysql = $driver === 'mysql';
    $id     = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $tables = [
        "CREATE TABLE users (
            id $id,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(191) NOT NULL UNIQUE,
            pass_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'editor',
            created_at DATETIME NOT NULL
        )$suffix",

        "CREATE TABLE categories (
            id $id,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(191) NOT NULL UNIQUE,
            color VARCHAR(20) NOT NULL DEFAULT '#17301C',
            color_is_fill INTEGER NOT NULL DEFAULT 0,
            description TEXT,
            sort INTEGER NOT NULL DEFAULT 0
        )$suffix",

        "CREATE TABLE posts (
            id $id,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(191) NOT NULL UNIQUE,
            category_id INTEGER,
            author_id INTEGER,
            byline VARCHAR(120) NOT NULL DEFAULT '',
            dateline VARCHAR(120) NOT NULL DEFAULT '',
            lede TEXT,
            body TEXT,
            image VARCHAR(255) NOT NULL DEFAULT '',
            image_caption VARCHAR(255) NOT NULL DEFAULT '',
            image_credit VARCHAR(120) NOT NULL DEFAULT '',
            meta_description VARCHAR(255) NOT NULL DEFAULT '',
            source_url VARCHAR(500) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            is_featured INTEGER NOT NULL DEFAULT 0,
            published_at DATETIME,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )$suffix",

        "CREATE TABLE tags (
            id $id,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(191) NOT NULL UNIQUE
        )$suffix",

        "CREATE TABLE post_tags (
            post_id INTEGER NOT NULL,
            tag_id INTEGER NOT NULL,
            PRIMARY KEY (post_id, tag_id)
        )$suffix",

        "CREATE TABLE sources (
            id $id,
            name VARCHAR(160) NOT NULL,
            url VARCHAR(500) NOT NULL,
            region VARCHAR(40) NOT NULL,
            enabled INTEGER NOT NULL DEFAULT 1,
            last_fetched_at DATETIME,
            last_status VARCHAR(255) NOT NULL DEFAULT ''
        )$suffix",

        "CREATE TABLE news_items (
            id $id,
            source_id INTEGER NOT NULL,
            region VARCHAR(40) NOT NULL,
            title VARCHAR(500) NOT NULL,
            url VARCHAR(600) NOT NULL,
            url_hash CHAR(40) NOT NULL UNIQUE,
            summary TEXT,
            published_at DATETIME,
            fetched_at DATETIME NOT NULL,
            used INTEGER NOT NULL DEFAULT 0
        )$suffix",

        "CREATE TABLE subscribers (
            id $id,
            email VARCHAR(191) NOT NULL UNIQUE,
            created_at DATETIME NOT NULL
        )$suffix",

        "CREATE TABLE settings (
            skey VARCHAR(100) NOT NULL PRIMARY KEY,
            svalue TEXT
        )$suffix",
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    $indexes = [
        'CREATE INDEX idx_posts_status ON posts (status, published_at)',
        'CREATE INDEX idx_posts_category ON posts (category_id)',
        'CREATE INDEX idx_news_region ON news_items (region, fetched_at)',
    ];
    foreach ($indexes as $sql) {
        $pdo->exec($sql);
    }
}
