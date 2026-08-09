<?php
/**
 * The Prairie Post — schema install and migrations.
 * Drivers: sqlite (default), pgsql (shared network database, e.g. Supabase),
 * mysql. The canonical Postgres DDL also ships as supabase/schema.sql.
 */

function pp_schema_installed(PDO $pdo, string $driver): bool
{
    try {
        $sql = match ($driver) {
            'mysql' => "SHOW TABLES LIKE 'settings'",
            'pgsql' => "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename = 'settings'",
            default => "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'settings'",
        };
        $stmt = $pdo->query($sql);
        return $stmt !== false && $stmt->fetch() !== false;
    } catch (PDOException) {
        return false;
    }
}

/** DDL statements for the current schema version, per driver. */
function pp_schema_ddl(string $driver): array
{
    $mysql = $driver === 'mysql';
    $pgsql = $driver === 'pgsql';

    $id = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
        : ($pgsql ? 'INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT');
    $dt = $pgsql ? 'TIMESTAMP' : 'DATETIME';
    $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    return [
        "CREATE TABLE sites (
            id $id,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(191) NOT NULL UNIQUE,
            domain VARCHAR(255) NOT NULL DEFAULT '',
            created_at $dt NOT NULL
        )$suffix",

        "CREATE TABLE users (
            id $id,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(191) NOT NULL UNIQUE,
            pass_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'author',
            slug VARCHAR(191) NOT NULL DEFAULT '',
            title VARCHAR(120) NOT NULL DEFAULT '',
            bio TEXT,
            photo VARCHAR(255) NOT NULL DEFAULT '',
            created_at $dt NOT NULL
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
            review_note TEXT,
            is_featured INTEGER NOT NULL DEFAULT 0,
            published_at $dt,
            created_at $dt NOT NULL,
            updated_at $dt NOT NULL
        )$suffix",

        "CREATE TABLE post_sites (
            post_id INTEGER NOT NULL,
            site_id INTEGER NOT NULL,
            PRIMARY KEY (post_id, site_id)
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
            last_fetched_at $dt,
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
            published_at $dt,
            fetched_at $dt NOT NULL,
            used INTEGER NOT NULL DEFAULT 0
        )$suffix",

        "CREATE TABLE subscribers (
            id $id,
            site_id INTEGER NOT NULL DEFAULT 1,
            email VARCHAR(191) NOT NULL,
            created_at $dt NOT NULL,
            CONSTRAINT uq_sub UNIQUE (site_id, email)
        )$suffix",

        "CREATE TABLE settings (
            site_id INTEGER NOT NULL,
            skey VARCHAR(100) NOT NULL,
            svalue TEXT,
            PRIMARY KEY (site_id, skey)
        )$suffix",

        'CREATE INDEX idx_posts_status ON posts (status, published_at)',
        'CREATE INDEX idx_posts_category ON posts (category_id)',
        'CREATE INDEX idx_posts_author ON posts (author_id)',
        'CREATE INDEX idx_news_region ON news_items (region, fetched_at)',
        'CREATE INDEX idx_post_sites_site ON post_sites (site_id)',
    ];
}

function pp_install(PDO $pdo, string $driver): void
{
    foreach (pp_schema_ddl($driver) as $sql) {
        $pdo->exec($sql);
    }
    $pdo->prepare('INSERT INTO settings (site_id, skey, svalue) VALUES (0, ?, ?)')
        ->execute(['schema_version', (string) PP_SCHEMA_VERSION]);
}

/** Current stored schema version; 1 = the pre-network schema. */
function pp_schema_version(PDO $pdo): int
{
    try {
        $stmt = $pdo->query("SELECT svalue FROM settings WHERE site_id = 0 AND skey = 'schema_version'");
        $row = $stmt->fetch();
        return $row ? (int) $row['svalue'] : 1;
    } catch (PDOException) {
        return 1; // settings has no site_id column — v1 schema
    }
}

/** Upgrade an existing database in place. Safe to run on every request. */
function pp_migrate(PDO $pdo, string $driver): void
{
    $version = pp_schema_version($pdo);
    if ($version >= PP_SCHEMA_VERSION) {
        return;
    }

    if ($version < 2) {
        $dt = $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
        $id = $driver === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : ($driver === 'pgsql' ? 'INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT');

        // New tables.
        $pdo->exec("CREATE TABLE sites (
            id $id,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(191) NOT NULL UNIQUE,
            domain VARCHAR(255) NOT NULL DEFAULT '',
            created_at $dt NOT NULL
        )");
        $pdo->exec('CREATE TABLE post_sites (
            post_id INTEGER NOT NULL,
            site_id INTEGER NOT NULL,
            PRIMARY KEY (post_id, site_id)
        )');
        $pdo->exec('CREATE INDEX idx_post_sites_site ON post_sites (site_id)');

        // New columns.
        foreach ([
            "ALTER TABLE posts ADD COLUMN review_note TEXT",
            "ALTER TABLE users ADD COLUMN slug VARCHAR(191) NOT NULL DEFAULT ''",
            "ALTER TABLE users ADD COLUMN title VARCHAR(120) NOT NULL DEFAULT ''",
            'ALTER TABLE users ADD COLUMN bio TEXT',
            "ALTER TABLE users ADD COLUMN photo VARCHAR(255) NOT NULL DEFAULT ''",
            'ALTER TABLE subscribers ADD COLUMN site_id INTEGER NOT NULL DEFAULT 1',
        ] as $sql) {
            $pdo->exec($sql);
        }
        $pdo->exec('CREATE INDEX idx_posts_author ON posts (author_id)');

        // The founding site inherits the existing single-site content.
        $slug = slugify((string) pp_config('site_slug', 'prairiepost'));
        $titleRow = $pdo->query("SELECT svalue FROM settings WHERE skey = 'site_title'")->fetch();
        $pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?, ?, ?)')
            ->execute([$titleRow['svalue'] ?? 'The Prairie Post', $slug, date('Y-m-d H:i:s')]);
        $siteId = $driver === 'pgsql' ? (int) $pdo->lastInsertId('sites_id_seq') : (int) $pdo->lastInsertId();

        $pdo->exec("INSERT INTO post_sites (post_id, site_id) SELECT id, $siteId FROM posts");
        $pdo->exec("UPDATE subscribers SET site_id = $siteId");

        // Rebuild settings with a per-site key.
        $old = $pdo->query('SELECT skey, svalue FROM settings')->fetchAll();
        $pdo->exec('DROP TABLE settings');
        $pdo->exec('CREATE TABLE settings (
            site_id INTEGER NOT NULL,
            skey VARCHAR(100) NOT NULL,
            svalue TEXT,
            PRIMARY KEY (site_id, skey)
        )');
        $ins = $pdo->prepare('INSERT INTO settings (site_id, skey, svalue) VALUES (?, ?, ?)');
        foreach ($old as $row) {
            $ins->execute([$siteId, $row['skey'], $row['svalue']]);
        }
        $ins->execute([0, 'schema_version', '2']);
    }
}
