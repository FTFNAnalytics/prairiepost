<?php
/**
 * The Prairie Dispatch — schema install and migrations.
 * Drivers: sqlite (default), pgsql (shared network database, e.g. Supabase),
 * mysql. The canonical Postgres DDL also ships as supabase/schema.sql.
 */

function pp_schema_installed(PDO $pdo, string $driver): bool
{
    try {
        if ($driver === 'pgsql') {
            // Scope the check to OUR namespace, and to a table only this app
            // creates. A shared database may carry another application's
            // "settings" table in public — that must never read as ours.
            $stmt = $pdo->prepare('SELECT 1 FROM pg_tables WHERE schemaname = ? AND tablename = ?');
            $stmt->execute([pp_pg_schema(), 'post_sites']);
            return $stmt->fetch() !== false;
        }
        $sql = $driver === 'mysql'
            ? "SHOW TABLES LIKE 'settings'"
            : "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'settings'";
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
            source_name VARCHAR(160) NOT NULL DEFAULT '',
            post_type VARCHAR(20) NOT NULL DEFAULT 'story',
            region VARCHAR(40) NOT NULL DEFAULT '',
            origin VARCHAR(20) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            review_note TEXT,
            is_featured INTEGER NOT NULL DEFAULT 0,
            placement VARCHAR(20) NOT NULL DEFAULT '',
            views INTEGER NOT NULL DEFAULT 0,
            correction TEXT,
            corrected_at $dt,
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
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            token VARCHAR(64) NOT NULL DEFAULT '',
            confirmed_at $dt,
            consent_note VARCHAR(255) NOT NULL DEFAULT '',
            created_at $dt NOT NULL,
            CONSTRAINT uq_sub UNIQUE (site_id, email)
        )$suffix",

        "CREATE TABLE newsletters (
            id $id,
            site_id INTEGER NOT NULL,
            edition_date VARCHAR(10) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            html TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'sent',
            recipients INTEGER NOT NULL DEFAULT 0,
            sent_at $dt,
            created_at $dt NOT NULL,
            CONSTRAINT uq_edition UNIQUE (site_id, edition_date)
        )$suffix",

        "CREATE TABLE settings (
            site_id INTEGER NOT NULL,
            skey VARCHAR(100) NOT NULL,
            svalue TEXT,
            PRIMARY KEY (site_id, skey)
        )$suffix",

        "CREATE TABLE ads (
            id $id,
            site_id INTEGER NOT NULL,
            name VARCHAR(160) NOT NULL,
            placement VARCHAR(20) NOT NULL DEFAULT 'rail',
            kind VARCHAR(20) NOT NULL DEFAULT 'house',
            image VARCHAR(255) NOT NULL DEFAULT '',
            link_url VARCHAR(500) NOT NULL DEFAULT '',
            html TEXT,
            kicker VARCHAR(80) NOT NULL DEFAULT '',
            heading VARCHAR(160) NOT NULL DEFAULT '',
            body_text VARCHAR(255) NOT NULL DEFAULT '',
            button_label VARCHAR(60) NOT NULL DEFAULT '',
            start_at $dt,
            end_at $dt,
            enabled INTEGER NOT NULL DEFAULT 1,
            impressions INTEGER NOT NULL DEFAULT 0,
            clicks INTEGER NOT NULL DEFAULT 0,
            campaign_id INTEGER,
            created_at $dt NOT NULL
        )$suffix",

        "CREATE TABLE campaigns (
            id $id,
            name VARCHAR(160) NOT NULL,
            advertiser VARCHAR(160) NOT NULL DEFAULT '',
            notes TEXT,
            created_at $dt NOT NULL
        )$suffix",

        "CREATE TABLE inquiries (
            id $id,
            site_id INTEGER NOT NULL,
            name VARCHAR(120) NOT NULL DEFAULT '',
            email VARCHAR(191) NOT NULL DEFAULT '',
            organization VARCHAR(160) NOT NULL DEFAULT '',
            message TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            ip VARCHAR(64) NOT NULL DEFAULT '',
            created_at $dt NOT NULL
        )$suffix",

        "CREATE TABLE roadmap_items (
            id $id,
            kind VARCHAR(20) NOT NULL DEFAULT 'note',
            title VARCHAR(255) NOT NULL,
            body TEXT,
            status VARCHAR(20) NOT NULL DEFAULT '',
            sort INTEGER NOT NULL DEFAULT 0,
            updated_by VARCHAR(120) NOT NULL DEFAULT '',
            created_at $dt NOT NULL,
            updated_at $dt NOT NULL
        )$suffix",

        "CREATE TABLE monitor_feeds (
            id $id,
            name VARCHAR(160) NOT NULL,
            url VARCHAR(600) NOT NULL,
            level VARCHAR(20) NOT NULL DEFAULT 'agency',
            region VARCHAR(40) NOT NULL DEFAULT '',
            doc_type VARCHAR(30) NOT NULL DEFAULT 'release',
            enabled INTEGER NOT NULL DEFAULT 1,
            last_fetched_at $dt,
            last_status VARCHAR(255) NOT NULL DEFAULT ''
        )$suffix",

        "CREATE TABLE monitor_items (
            id $id,
            feed_id INTEGER,
            source_name VARCHAR(160) NOT NULL DEFAULT '',
            level VARCHAR(20) NOT NULL DEFAULT 'agency',
            region VARCHAR(40) NOT NULL DEFAULT '',
            doc_type VARCHAR(30) NOT NULL DEFAULT 'other',
            title VARCHAR(255) NOT NULL,
            url VARCHAR(600) NOT NULL,
            url_hash VARCHAR(40) NOT NULL UNIQUE,
            summary TEXT,
            body_excerpt TEXT,
            published_at $dt,
            fetched_at $dt NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            flagged_by VARCHAR(120) NOT NULL DEFAULT '',
            claimed_by VARCHAR(120) NOT NULL DEFAULT ''
        )$suffix",

        "CREATE TABLE story_ideas (
            id $id,
            monitor_item_id INTEGER,
            site_id INTEGER,
            title VARCHAR(255) NOT NULL,
            angle TEXT,
            rationale TEXT,
            region VARCHAR(40) NOT NULL DEFAULT '',
            origin VARCHAR(20) NOT NULL DEFAULT 'newsroom',
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            claimed_by VARCHAR(120) NOT NULL DEFAULT '',
            created_by VARCHAR(120) NOT NULL DEFAULT '',
            created_at $dt NOT NULL
        )$suffix",

        'CREATE INDEX idx_posts_status ON posts (status, published_at)',
        'CREATE INDEX idx_posts_category ON posts (category_id)',
        'CREATE INDEX idx_posts_author ON posts (author_id)',
        'CREATE INDEX idx_posts_region ON posts (region)',
        'CREATE INDEX idx_news_region ON news_items (region, fetched_at)',
        'CREATE INDEX idx_monitor_items ON monitor_items (level, region, fetched_at)',
        'CREATE INDEX idx_monitor_status ON monitor_items (status, fetched_at)',
        'CREATE INDEX idx_ideas_status ON story_ideas (status, created_at)',
        'CREATE INDEX idx_post_sites_site ON post_sites (site_id)',
        'CREATE INDEX idx_ads_site_placement ON ads (site_id, placement)',
        'CREATE INDEX idx_ads_campaign ON ads (campaign_id)',
        'CREATE INDEX idx_inquiries_site ON inquiries (site_id, created_at)',
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
        $slug = slugify((string) pp_config('site_slug', 'prairiedispatch'));
        $titleRow = $pdo->query("SELECT svalue FROM settings WHERE skey = 'site_title'")->fetch();
        $pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?, ?, ?)')
            ->execute([$titleRow['svalue'] ?? 'The Prairie Dispatch', $slug, date('Y-m-d H:i:s')]);
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

    if ($version < 3) {
        $dt = $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
        $id = $driver === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : ($driver === 'pgsql' ? 'INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT');
        $pdo->exec("CREATE TABLE ads (
            id $id,
            site_id INTEGER NOT NULL,
            name VARCHAR(160) NOT NULL,
            placement VARCHAR(20) NOT NULL DEFAULT 'rail',
            kind VARCHAR(20) NOT NULL DEFAULT 'house',
            image VARCHAR(255) NOT NULL DEFAULT '',
            link_url VARCHAR(500) NOT NULL DEFAULT '',
            html TEXT,
            kicker VARCHAR(80) NOT NULL DEFAULT '',
            heading VARCHAR(160) NOT NULL DEFAULT '',
            body_text VARCHAR(255) NOT NULL DEFAULT '',
            button_label VARCHAR(60) NOT NULL DEFAULT '',
            start_at $dt,
            end_at $dt,
            enabled INTEGER NOT NULL DEFAULT 1,
            impressions INTEGER NOT NULL DEFAULT 0,
            clicks INTEGER NOT NULL DEFAULT 0,
            created_at $dt NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_ads_site_placement ON ads (site_id, placement)');
        $pdo->exec("UPDATE settings SET svalue = '3' WHERE site_id = 0 AND skey = 'schema_version'");
    }

    if ($version < 4) {
        $dt = $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
        $id = $driver === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : ($driver === 'pgsql' ? 'INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT');

        foreach ([
            "ALTER TABLE posts ADD COLUMN placement VARCHAR(20) NOT NULL DEFAULT ''",
            'ALTER TABLE posts ADD COLUMN correction TEXT',
            "ALTER TABLE posts ADD COLUMN corrected_at $dt",
            "ALTER TABLE subscribers ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'",
            "ALTER TABLE subscribers ADD COLUMN token VARCHAR(64) NOT NULL DEFAULT ''",
            "ALTER TABLE subscribers ADD COLUMN confirmed_at $dt",
            "ALTER TABLE subscribers ADD COLUMN consent_note VARCHAR(255) NOT NULL DEFAULT ''",
        ] as $sql) {
            $pdo->exec($sql);
        }

        $pdo->exec("CREATE TABLE newsletters (
            id $id,
            site_id INTEGER NOT NULL,
            edition_date VARCHAR(10) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            html TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'sent',
            recipients INTEGER NOT NULL DEFAULT 0,
            sent_at $dt,
            created_at $dt NOT NULL,
            CONSTRAINT uq_edition UNIQUE (site_id, edition_date)
        )");

        // The old single pin becomes the hero placement.
        $pdo->exec("UPDATE posts SET placement = 'hero' WHERE is_featured = 1");

        // Existing subscribers predate the token system: grandfather them in
        // as active with a consent note, and give each an unsubscribe token.
        $rows = $pdo->query('SELECT id FROM subscribers')->fetchAll();
        $upd = $pdo->prepare("UPDATE subscribers SET token = ?, consent_note = 'web form (pre-token import)' WHERE id = ?");
        foreach ($rows as $row) {
            $upd->execute([bin2hex(random_bytes(16)), $row['id']]);
        }

        $pdo->exec("UPDATE settings SET svalue = '4' WHERE site_id = 0 AND skey = 'schema_version'");
    }

    if ($version < 5) {
        // The paper renamed: The Prairie Post → The Prairie Dispatch.
        // Only values still carrying the old default are touched; anything an
        // editor customised stays exactly as they wrote it.
        $rows = $pdo->query("SELECT site_id, skey, svalue FROM settings WHERE svalue LIKE '%Prairie Post%'")->fetchAll();
        $upd = $pdo->prepare('UPDATE settings SET svalue = ? WHERE site_id = ? AND skey = ?');
        foreach ($rows as $row) {
            $upd->execute([str_replace(['The Prairie Post', 'Prairie Post'], ['The Prairie Dispatch', 'Prairie Dispatch'], $row['svalue']), $row['site_id'], $row['skey']]);
        }
        $sites = $pdo->query("SELECT id, name FROM sites WHERE name LIKE '%Prairie Post%'")->fetchAll();
        $updSite = $pdo->prepare('UPDATE sites SET name = ? WHERE id = ?');
        foreach ($sites as $site) {
            $updSite->execute([str_replace(['The Prairie Post', 'Prairie Post'], ['The Prairie Dispatch', 'Prairie Dispatch'], $site['name']), $site['id']]);
        }
        $pdo->exec("UPDATE settings SET svalue = '5' WHERE site_id = 0 AND skey = 'schema_version'");
    }

    if ($version < 6) {
        // Read counts, for the Trending panel and the newsroom's own numbers.
        $pdo->exec('ALTER TABLE posts ADD COLUMN views INTEGER NOT NULL DEFAULT 0');
        $pdo->exec("UPDATE settings SET svalue = '6' WHERE site_id = 0 AND skey = 'schema_version'");
    }

    if ($version < 7) {
        // The aggregator: a post can be an outbound wire link — its headline
        // links to the outlet that reported it — and carries the outlet's
        // name and a region so aggregator fronts can group by province.
        foreach ([
            "ALTER TABLE posts ADD COLUMN source_name VARCHAR(160) NOT NULL DEFAULT ''",
            "ALTER TABLE posts ADD COLUMN post_type VARCHAR(20) NOT NULL DEFAULT 'story'",
            "ALTER TABLE posts ADD COLUMN region VARCHAR(40) NOT NULL DEFAULT ''",
        ] as $sql) {
            $pdo->exec($sql);
        }
        $pdo->exec('CREATE INDEX idx_posts_region ON posts (region)');
        $pdo->exec("UPDATE settings SET svalue = '7' WHERE site_id = 0 AND skey = 'schema_version'");
    }

    if ($version < 8) {
        // The Civis Media control room: provenance on every story ('' newsroom,
        // 'wire' started from the pull, 'ai' machine-assisted — always
        // human-finished), the hub's contact-form inquiries, and the roadmap —
        // the project's living document, kept in the database so the whole
        // newsroom works from one copy.
        $dt = $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
        $id = $driver === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : ($driver === 'pgsql' ? 'INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT');

        $pdo->exec("ALTER TABLE posts ADD COLUMN origin VARCHAR(20) NOT NULL DEFAULT ''");

        $pdo->exec("CREATE TABLE inquiries (
            id $id,
            site_id INTEGER NOT NULL,
            name VARCHAR(120) NOT NULL DEFAULT '',
            email VARCHAR(191) NOT NULL DEFAULT '',
            organization VARCHAR(160) NOT NULL DEFAULT '',
            message TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            ip VARCHAR(64) NOT NULL DEFAULT '',
            created_at $dt NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_inquiries_site ON inquiries (site_id, created_at)');

        $pdo->exec("CREATE TABLE roadmap_items (
            id $id,
            kind VARCHAR(20) NOT NULL DEFAULT 'note',
            title VARCHAR(255) NOT NULL,
            body TEXT,
            status VARCHAR(20) NOT NULL DEFAULT '',
            sort INTEGER NOT NULL DEFAULT 0,
            updated_by VARCHAR(120) NOT NULL DEFAULT '',
            created_at $dt NOT NULL,
            updated_at $dt NOT NULL
        )");

        require_once PP_ROOT . '/app/seed.php';
        pp_seed_roadmap($pdo);

        $pdo->exec("UPDATE settings SET svalue = '8' WHERE site_id = 0 AND skey = 'schema_version'");
    }

    if ($version < 9) {
        // Network advertising: a campaign is filed once on the hub and fans
        // out to one ads row per chosen paper — per-site serving, rotation
        // and counters stay exactly as they are; the campaign_id stamp is
        // what ties the rows together for reporting and protects them from
        // local edits.
        $dt = $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
        $id = $driver === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : ($driver === 'pgsql' ? 'INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT');

        $pdo->exec('ALTER TABLE ads ADD COLUMN campaign_id INTEGER');
        $pdo->exec('CREATE INDEX idx_ads_campaign ON ads (campaign_id)');

        $pdo->exec("CREATE TABLE campaigns (
            id $id,
            name VARCHAR(160) NOT NULL,
            advertiser VARCHAR(160) NOT NULL DEFAULT '',
            notes TEXT,
            created_at $dt NOT NULL
        )");

        $pdo->exec("UPDATE settings SET svalue = '9' WHERE site_id = 0 AND skey = 'schema_version'");
    }

    if ($version < 10) {
        // The media monitoring desk: government publications and press
        // releases, streamed in by the external scraping agent through
        // /api/monitor or polled from the desk's own feed list — kept apart
        // from the newspaper wire so releases never pollute the morning
        // pull — plus the story ideas they generate. Journalists triage by
        // region and jurisdiction on the hub's board.
        $dt = $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
        $id = $driver === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : ($driver === 'pgsql' ? 'INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT');

        $pdo->exec("CREATE TABLE monitor_feeds (
            id $id,
            name VARCHAR(160) NOT NULL,
            url VARCHAR(600) NOT NULL,
            level VARCHAR(20) NOT NULL DEFAULT 'agency',
            region VARCHAR(40) NOT NULL DEFAULT '',
            doc_type VARCHAR(30) NOT NULL DEFAULT 'release',
            enabled INTEGER NOT NULL DEFAULT 1,
            last_fetched_at $dt,
            last_status VARCHAR(255) NOT NULL DEFAULT ''
        )");

        $pdo->exec("CREATE TABLE monitor_items (
            id $id,
            feed_id INTEGER,
            source_name VARCHAR(160) NOT NULL DEFAULT '',
            level VARCHAR(20) NOT NULL DEFAULT 'agency',
            region VARCHAR(40) NOT NULL DEFAULT '',
            doc_type VARCHAR(30) NOT NULL DEFAULT 'other',
            title VARCHAR(255) NOT NULL,
            url VARCHAR(600) NOT NULL,
            url_hash VARCHAR(40) NOT NULL UNIQUE,
            summary TEXT,
            body_excerpt TEXT,
            published_at $dt,
            fetched_at $dt NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            flagged_by VARCHAR(120) NOT NULL DEFAULT '',
            claimed_by VARCHAR(120) NOT NULL DEFAULT ''
        )");
        $pdo->exec('CREATE INDEX idx_monitor_items ON monitor_items (level, region, fetched_at)');
        $pdo->exec('CREATE INDEX idx_monitor_status ON monitor_items (status, fetched_at)');

        $pdo->exec("CREATE TABLE story_ideas (
            id $id,
            monitor_item_id INTEGER,
            site_id INTEGER,
            title VARCHAR(255) NOT NULL,
            angle TEXT,
            rationale TEXT,
            region VARCHAR(40) NOT NULL DEFAULT '',
            origin VARCHAR(20) NOT NULL DEFAULT 'newsroom',
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            claimed_by VARCHAR(120) NOT NULL DEFAULT '',
            created_by VARCHAR(120) NOT NULL DEFAULT '',
            created_at $dt NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_ideas_status ON story_ideas (status, created_at)');

        $pdo->exec("UPDATE settings SET svalue = '10' WHERE site_id = 0 AND skey = 'schema_version'");
    }
}
