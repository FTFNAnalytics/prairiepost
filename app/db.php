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

        "CREATE TABLE domains (
            id $id,
            hostname VARCHAR(255) NOT NULL UNIQUE,
            site_slug VARCHAR(191) NOT NULL,
            created_at $dt NOT NULL
        )$suffix",

        "CREATE TABLE ingest_agents (
            id $id,
            name VARCHAR(120) NOT NULL UNIQUE,
            token_hash VARCHAR(64) NOT NULL UNIQUE,
            sites TEXT NOT NULL,
            desks TEXT NOT NULL DEFAULT '',
            enabled INTEGER NOT NULL DEFAULT 1,
            created_at $dt NOT NULL,
            last_used_at $dt
        )$suffix",

        "CREATE TABLE story_sources (
            id $id,
            post_id INTEGER NOT NULL,
            url VARCHAR(600) NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            retrieved_at $dt,
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
            totp_secret VARCHAR(64) NOT NULL DEFAULT '',
            totp_enabled INTEGER NOT NULL DEFAULT 0,
            session_epoch INTEGER NOT NULL DEFAULT 0,
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
            canonical_site_id INTEGER,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            review_note TEXT,
            is_featured INTEGER NOT NULL DEFAULT 0,
            placement VARCHAR(20) NOT NULL DEFAULT '',
            views INTEGER NOT NULL DEFAULT 0,
            correction TEXT,
            corrected_at $dt,
            published_at $dt,
            filed_by VARCHAR(120) NOT NULL DEFAULT '',
            content_hash VARCHAR(64) NOT NULL DEFAULT '',
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

        "CREATE TABLE site_metrics_daily (
            id $id,
            site_id INTEGER NOT NULL,
            day DATE NOT NULL,
            sessions INTEGER NOT NULL DEFAULT 0,
            users INTEGER NOT NULL DEFAULT 0,
            pageviews INTEGER NOT NULL DEFAULT 0,
            engaged_sessions INTEGER NOT NULL DEFAULT 0,
            engagement_secs INTEGER NOT NULL DEFAULT 0,
            channels_json TEXT,
            top_pages_json TEXT
        )$suffix",

        "CREATE TABLE gsc_daily (
            id $id,
            site_id INTEGER NOT NULL,
            day DATE NOT NULL,
            dim VARCHAR(10) NOT NULL,
            dkey VARCHAR(255) NOT NULL,
            clicks INTEGER NOT NULL DEFAULT 0,
            impressions INTEGER NOT NULL DEFAULT 0,
            position REAL NOT NULL DEFAULT 0
        )$suffix",

        "CREATE TABLE entities (
            id $id,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(191) NOT NULL UNIQUE,
            kind VARCHAR(40) NOT NULL DEFAULT 'politician',
            url VARCHAR(600) NOT NULL DEFAULT '',
            aliases TEXT,
            enabled INTEGER NOT NULL DEFAULT 1,
            created_at $dt NOT NULL
        )$suffix",

        "CREATE TABLE agent_tasks (
            id $id,
            kind VARCHAR(40) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            post_id INTEGER,
            site_id INTEGER,
            payload TEXT,
            result TEXT,
            log TEXT,
            created_by VARCHAR(120) NOT NULL DEFAULT '',
            created_at $dt NOT NULL,
            started_at $dt,
            finished_at $dt,
            reviewed_by VARCHAR(120) NOT NULL DEFAULT '',
            reviewed_at $dt
        )$suffix",

        "CREATE TABLE login_attempts (
            id $id,
            email VARCHAR(191) NOT NULL DEFAULT '',
            ip VARCHAR(64) NOT NULL DEFAULT '',
            succeeded INTEGER NOT NULL DEFAULT 0,
            created_at $dt NOT NULL
        )$suffix",

        "CREATE TABLE audit_log (
            id $id,
            site_id INTEGER NOT NULL DEFAULT 0,
            user_id INTEGER NOT NULL DEFAULT 0,
            user_name VARCHAR(120) NOT NULL DEFAULT '',
            action VARCHAR(60) NOT NULL,
            target VARCHAR(160) NOT NULL DEFAULT '',
            detail TEXT,
            ip VARCHAR(64) NOT NULL DEFAULT '',
            created_at $dt NOT NULL
        )$suffix",

        "CREATE TABLE post_revisions (
            id $id,
            post_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            lede TEXT,
            body TEXT,
            meta_description VARCHAR(255) NOT NULL DEFAULT '',
            correction TEXT,
            image VARCHAR(255) NOT NULL DEFAULT '',
            image_caption VARCHAR(255) NOT NULL DEFAULT '',
            saved_by VARCHAR(120) NOT NULL DEFAULT '',
            reason VARCHAR(40) NOT NULL DEFAULT 'edit',
            created_at $dt NOT NULL
        )$suffix",

        "CREATE TABLE ops_runs (
            id $id,
            job VARCHAR(40) NOT NULL,
            site_id INTEGER NOT NULL DEFAULT 0,
            ok INTEGER NOT NULL DEFAULT 1,
            note VARCHAR(500) NOT NULL DEFAULT '',
            started_at $dt NOT NULL,
            finished_at $dt
        )$suffix",

        'CREATE INDEX idx_posts_status ON posts (status, published_at)',
        'CREATE INDEX idx_agent_tasks ON agent_tasks (status, created_at)',
        'CREATE INDEX idx_agent_tasks_post ON agent_tasks (post_id, kind)',
        'CREATE UNIQUE INDEX uq_metrics_site_day ON site_metrics_daily (site_id, day)',
        'CREATE UNIQUE INDEX uq_gsc_row ON gsc_daily (site_id, day, dim, dkey)',
        'CREATE INDEX idx_gsc_site_dim ON gsc_daily (site_id, dim, day)',
        'CREATE INDEX idx_posts_category ON posts (category_id)',
        'CREATE INDEX idx_posts_author ON posts (author_id)',
        'CREATE INDEX idx_posts_region ON posts (region)',
        'CREATE INDEX idx_news_region ON news_items (region, fetched_at)',
        'CREATE INDEX idx_monitor_items ON monitor_items (level, region, fetched_at)',
        'CREATE INDEX idx_monitor_status ON monitor_items (status, fetched_at)',
        'CREATE INDEX idx_ideas_status ON story_ideas (status, created_at)',
        'CREATE INDEX idx_post_sites_site ON post_sites (site_id)',
        'CREATE INDEX idx_domains_site ON domains (site_slug)',
        'CREATE INDEX idx_story_sources_post ON story_sources (post_id)',
        'CREATE INDEX idx_posts_content_hash ON posts (content_hash)',
        'CREATE INDEX idx_ads_site_placement ON ads (site_id, placement)',
        'CREATE INDEX idx_ads_campaign ON ads (campaign_id)',
        'CREATE INDEX idx_inquiries_site ON inquiries (site_id, created_at)',
        'CREATE INDEX idx_login_attempts_email ON login_attempts (email, created_at)',
        'CREATE INDEX idx_login_attempts_ip ON login_attempts (ip, created_at)',
        'CREATE INDEX idx_audit_created ON audit_log (created_at)',
        'CREATE INDEX idx_revisions_post ON post_revisions (post_id, id)',
        'CREATE INDEX idx_ops_runs_job ON ops_runs (job, id)',
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

    if ($version < 11) {
        // Analytics & Search Console: one nightly pull into our own tables —
        // GA4 daily traffic per site and Search Console rows by query and
        // page — plus the canonical decision: a widely-syndicated story can
        // name one paper its home, and the others point rel=canonical there
        // so a single paper accrues the ranking.
        $dt = $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
        $id = $driver === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : ($driver === 'pgsql' ? 'INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT');

        $pdo->exec("CREATE TABLE site_metrics_daily (
            id $id,
            site_id INTEGER NOT NULL,
            day DATE NOT NULL,
            sessions INTEGER NOT NULL DEFAULT 0,
            users INTEGER NOT NULL DEFAULT 0,
            pageviews INTEGER NOT NULL DEFAULT 0,
            engaged_sessions INTEGER NOT NULL DEFAULT 0,
            engagement_secs INTEGER NOT NULL DEFAULT 0,
            channels_json TEXT,
            top_pages_json TEXT
        )");
        $pdo->exec('CREATE UNIQUE INDEX uq_metrics_site_day ON site_metrics_daily (site_id, day)');

        $pdo->exec("CREATE TABLE gsc_daily (
            id $id,
            site_id INTEGER NOT NULL,
            day DATE NOT NULL,
            dim VARCHAR(10) NOT NULL,
            dkey VARCHAR(255) NOT NULL,
            clicks INTEGER NOT NULL DEFAULT 0,
            impressions INTEGER NOT NULL DEFAULT 0,
            position REAL NOT NULL DEFAULT 0
        )");
        $pdo->exec('CREATE UNIQUE INDEX uq_gsc_row ON gsc_daily (site_id, day, dim, dkey)');
        $pdo->exec('CREATE INDEX idx_gsc_site_dim ON gsc_daily (site_id, dim, day)');

        $pdo->exec('ALTER TABLE posts ADD COLUMN canonical_site_id INTEGER');

        // The papers' public domains, for cross-site canonicals. Fills the
        // long-empty sites.domain only where it is still blank — a value an
        // admin set by hand always wins.
        $domains = [
            'prairiedispatch'        => 'prairiedispatch.ca',
            'edmonton-echo'          => 'edmontonecho.com',
            'grande-prairie-gazette' => 'www.grandeprairiegazette.ca',
            'kelowna-current'        => 'kelownacurrent.ca',
            'kermode-chronicle'      => 'kermodechronicle.ca',
            'pacific-post'           => 'thepacificpost.com',
            'westernwire'            => 'westernwire.ca',
            'brampton-bulletin'      => 'bramptonbulletin.com',
            'civismedia'             => 'civismedia.ca',
        ];
        $upd = $pdo->prepare("UPDATE sites SET domain = ? WHERE slug = ? AND (domain = '' OR domain IS NULL)");
        foreach ($domains as $slug => $domain) {
            $upd->execute([$domain, $slug]);
        }

        $pdo->exec("UPDATE settings SET svalue = '11' WHERE site_id = 0 AND skey = 'schema_version'");
    }

    if ($version < 12) {
        // The agent control room: a task queue where agents do the tedious
        // passes — linkifier, SEO meta writer, tagger — and editors keep the
        // pen. Every result is a proposal (needs_review) until a person
        // approves it; nothing applies itself. Plus the entity directory the
        // linkifier reads: politicians and organizations with bio URLs,
        // admin-curated, seedable from the Represent API.
        $dt = $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
        $id = $driver === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : ($driver === 'pgsql' ? 'INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT');

        $pdo->exec("CREATE TABLE entities (
            id $id,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(191) NOT NULL UNIQUE,
            kind VARCHAR(40) NOT NULL DEFAULT 'politician',
            url VARCHAR(600) NOT NULL DEFAULT '',
            aliases TEXT,
            enabled INTEGER NOT NULL DEFAULT 1,
            created_at $dt NOT NULL
        )");

        $pdo->exec("CREATE TABLE agent_tasks (
            id $id,
            kind VARCHAR(40) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            post_id INTEGER,
            site_id INTEGER,
            payload TEXT,
            result TEXT,
            log TEXT,
            created_by VARCHAR(120) NOT NULL DEFAULT '',
            created_at $dt NOT NULL,
            started_at $dt,
            finished_at $dt,
            reviewed_by VARCHAR(120) NOT NULL DEFAULT '',
            reviewed_at $dt
        )");
        $pdo->exec('CREATE INDEX idx_agent_tasks ON agent_tasks (status, created_at)');
        $pdo->exec('CREATE INDEX idx_agent_tasks_post ON agent_tasks (post_id, kind)');

        $pdo->exec("UPDATE settings SET svalue = '12' WHERE site_id = 0 AND skey = 'schema_version'");
    }

    if ($version < 13) {
        // Hardening: TOTP two-factor on accounts, a sliding-window ledger of
        // sign-in attempts (throttling reads it, cron prunes it), and an
        // append-only audit trail of the actions that shape the network.
        $dt = $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
        $id = $driver === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : ($driver === 'pgsql' ? 'INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT');

        $pdo->exec("ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) NOT NULL DEFAULT ''");
        $pdo->exec('ALTER TABLE users ADD COLUMN totp_enabled INTEGER NOT NULL DEFAULT 0');

        $pdo->exec("CREATE TABLE login_attempts (
            id $id,
            email VARCHAR(191) NOT NULL DEFAULT '',
            ip VARCHAR(64) NOT NULL DEFAULT '',
            succeeded INTEGER NOT NULL DEFAULT 0,
            created_at $dt NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_login_attempts_email ON login_attempts (email, created_at)');
        $pdo->exec('CREATE INDEX idx_login_attempts_ip ON login_attempts (ip, created_at)');

        $pdo->exec("CREATE TABLE audit_log (
            id $id,
            site_id INTEGER NOT NULL DEFAULT 0,
            user_id INTEGER NOT NULL DEFAULT 0,
            user_name VARCHAR(120) NOT NULL DEFAULT '',
            action VARCHAR(60) NOT NULL,
            target VARCHAR(160) NOT NULL DEFAULT '',
            detail TEXT,
            ip VARCHAR(64) NOT NULL DEFAULT '',
            created_at $dt NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_audit_created ON audit_log (created_at)');

        $pdo->exec("UPDATE settings SET svalue = '13' WHERE site_id = 0 AND skey = 'schema_version'");
    }

    if ($version < 14) {
        // Resilience: a capped revision history per story (what was live,
        // when — corrections and libel defence both need it), a session
        // epoch on accounts so sign-out-everywhere is one integer bump,
        // and a ledger of cron runs so silent failures stop being silent.
        $dt = $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
        $id = $driver === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : ($driver === 'pgsql' ? 'INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT');

        $pdo->exec('ALTER TABLE users ADD COLUMN session_epoch INTEGER NOT NULL DEFAULT 0');

        $pdo->exec("CREATE TABLE post_revisions (
            id $id,
            post_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            lede TEXT,
            body TEXT,
            meta_description VARCHAR(255) NOT NULL DEFAULT '',
            correction TEXT,
            image VARCHAR(255) NOT NULL DEFAULT '',
            image_caption VARCHAR(255) NOT NULL DEFAULT '',
            saved_by VARCHAR(120) NOT NULL DEFAULT '',
            reason VARCHAR(40) NOT NULL DEFAULT 'edit',
            created_at $dt NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_revisions_post ON post_revisions (post_id, id)');

        $pdo->exec("CREATE TABLE ops_runs (
            id $id,
            job VARCHAR(40) NOT NULL,
            site_id INTEGER NOT NULL DEFAULT 0,
            ok INTEGER NOT NULL DEFAULT 1,
            note VARCHAR(500) NOT NULL DEFAULT '',
            started_at $dt NOT NULL,
            finished_at $dt
        )");
        $pdo->exec('CREATE INDEX idx_ops_runs_job ON ops_runs (job, id)');

        $pdo->exec("UPDATE settings SET svalue = '14' WHERE site_id = 0 AND skey = 'schema_version'");
    }

    if ($version < 15) {
        // Tenant resolution moves into the database: every public hostname a
        // paper answers on becomes a row, and bootstrap resolves the request
        // host here first, falling back to the config selector. Rows are
        // written only by the seeder from launch packs — once every live
        // hostname has one, the config file's tenant arms can retire and a
        // launch stops needing a guarded config edit at all. The canonical
        // hostname stays where it has always lived, in sites.domain.
        $dt = $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
        $id = $driver === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : ($driver === 'pgsql' ? 'INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT');

        $pdo->exec("CREATE TABLE domains (
            id $id,
            hostname VARCHAR(255) NOT NULL UNIQUE,
            site_slug VARCHAR(191) NOT NULL,
            created_at $dt NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_domains_site ON domains (site_slug)');

        $pdo->exec("UPDATE settings SET svalue = '15' WHERE site_id = 0 AND skey = 'schema_version'");
    }

    if ($version < 16) {
        // The Hermes ingest pipeline. External agents file stories through
        // /api/ingest with a scoped bearer token; everything lands as a
        // draft behind the newsroom's existing publish gate (wire desks
        // excepted, labelled). Tokens are hashed at rest; a story carries
        // its provenance — which agent, which sources, retrieved when.
        $dt = $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
        $id = $driver === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : ($driver === 'pgsql' ? 'INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT');

        $pdo->exec("CREATE TABLE ingest_agents (
            id $id,
            name VARCHAR(120) NOT NULL UNIQUE,
            token_hash VARCHAR(64) NOT NULL UNIQUE,
            sites TEXT NOT NULL,
            desks TEXT NOT NULL DEFAULT '',
            enabled INTEGER NOT NULL DEFAULT 1,
            created_at $dt NOT NULL,
            last_used_at $dt
        )");

        $pdo->exec("CREATE TABLE story_sources (
            id $id,
            post_id INTEGER NOT NULL,
            url VARCHAR(600) NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            retrieved_at $dt,
            created_at $dt NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_story_sources_post ON story_sources (post_id)');

        $pdo->exec("ALTER TABLE posts ADD COLUMN filed_by VARCHAR(120) NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE posts ADD COLUMN content_hash VARCHAR(64) NOT NULL DEFAULT ''");
        $pdo->exec('CREATE INDEX idx_posts_content_hash ON posts (content_hash)');

        $pdo->exec("UPDATE settings SET svalue = '16' WHERE site_id = 0 AND skey = 'schema_version'");
    }
}
