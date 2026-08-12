-- ============================================================================
-- The Prairie Dispatch network — shared content database schema (Postgres).
--
-- Run this once in the Supabase SQL Editor, OR simply point a site's
-- config.php at the database: the app installs this same schema automatically
-- on first connection if the tables don't exist yet.
--
-- Shared across the network:  sites, users (newsroom accounts), categories,
--                             posts (+ post_sites syndication), tags, sources,
--                             news_items (the wire pool), roadmap_items (the
--                             control room's living project document)
-- Per-site:                   settings (keyed by site_id), subscribers,
--                             newsletters, ads, inquiries
--
-- The PHP layer talks to this over the SESSION POOLER
-- (aws-0-<region>.pooler.supabase.com:5432) with sslmode=require and
-- emulated prepares, so it also works on the transaction pooler (6543).
--
-- Everything lives in the app's own schema (default: prairiedispatch), so a
-- Supabase project shared with other applications stays collision-free. The
-- schema name here must match db.pgsql.schema in every site's config.php.
-- ============================================================================

CREATE SCHEMA IF NOT EXISTS prairiedispatch;
SET search_path TO prairiedispatch;

CREATE TABLE sites (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    domain VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL
);

CREATE TABLE users (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    pass_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'author',   -- admin | editor | author
    slug VARCHAR(191) NOT NULL DEFAULT '',
    title VARCHAR(120) NOT NULL DEFAULT '',
    bio TEXT,
    photo VARCHAR(255) NOT NULL DEFAULT '',
    totp_secret VARCHAR(64) NOT NULL DEFAULT '',  -- base32; '' until enrolled
    totp_enabled INTEGER NOT NULL DEFAULT 0,
    session_epoch INTEGER NOT NULL DEFAULT 0,     -- bump = sign out everywhere
    created_at TIMESTAMP NOT NULL
);

CREATE TABLE categories (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    color VARCHAR(20) NOT NULL DEFAULT '#17301C',
    color_is_fill INTEGER NOT NULL DEFAULT 0,     -- under 4.5:1 — never text
    description TEXT,
    sort INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE posts (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
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
    source_name VARCHAR(160) NOT NULL DEFAULT '', -- the outlet a wire link credits
    post_type VARCHAR(20) NOT NULL DEFAULT 'story', -- story | link (headline links out)
    region VARCHAR(40) NOT NULL DEFAULT '',       -- aggregator region key (e.g. bc, alberta)
    origin VARCHAR(20) NOT NULL DEFAULT '',       -- '' newsroom | wire | ai (always human-finished)
    canonical_site_id INTEGER,                    -- set = the story's home paper; other copies point rel=canonical there
    status VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft | in_review | scheduled | published
    review_note TEXT,
    is_featured INTEGER NOT NULL DEFAULT 0,       -- superseded by placement; kept for compatibility
    placement VARCHAR(20) NOT NULL DEFAULT '',    -- '' | hero | featured | desk_lead
    views INTEGER NOT NULL DEFAULT 0,             -- article read count
    correction TEXT,
    corrected_at TIMESTAMP,
    published_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

-- Syndication: a story runs on every site it is mapped to.
CREATE TABLE post_sites (
    post_id INTEGER NOT NULL,
    site_id INTEGER NOT NULL,
    PRIMARY KEY (post_id, site_id)
);

CREATE TABLE tags (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE
);

CREATE TABLE post_tags (
    post_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (post_id, tag_id)
);

CREATE TABLE sources (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    url VARCHAR(500) NOT NULL,
    region VARCHAR(40) NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    last_fetched_at TIMESTAMP,
    last_status VARCHAR(255) NOT NULL DEFAULT ''
);

CREATE TABLE news_items (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    source_id INTEGER NOT NULL,
    region VARCHAR(40) NOT NULL,
    title VARCHAR(500) NOT NULL,
    url VARCHAR(600) NOT NULL,
    url_hash CHAR(40) NOT NULL UNIQUE,
    summary TEXT,
    published_at TIMESTAMP,
    fetched_at TIMESTAMP NOT NULL,
    used INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE subscribers (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    site_id INTEGER NOT NULL DEFAULT 1,
    email VARCHAR(191) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active', -- pending | active | unsubscribed
    token VARCHAR(64) NOT NULL DEFAULT '',        -- confirm & unsubscribe link token
    confirmed_at TIMESTAMP,
    consent_note VARCHAR(255) NOT NULL DEFAULT '',-- CASL consent record
    created_at TIMESTAMP NOT NULL,
    CONSTRAINT uq_sub UNIQUE (site_id, email)
);

-- Sent editions of each site's daily newsletter, archived as delivered.
CREATE TABLE newsletters (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    site_id INTEGER NOT NULL,
    edition_date VARCHAR(10) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    html TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'sent',
    recipients INTEGER NOT NULL DEFAULT 0,
    sent_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL,
    CONSTRAINT uq_edition UNIQUE (site_id, edition_date)
);

CREATE TABLE settings (
    site_id INTEGER NOT NULL,                     -- 0 = network-global keys
    skey VARCHAR(100) NOT NULL,
    svalue TEXT,
    PRIMARY KEY (site_id, skey)
);

-- Advertising: each site sells its own slots (top | rail | article).
-- kind: 'house' = brand-styled block built from the text fields;
--       'image' = uploaded creative + link; 'html' = pasted embed code.
CREATE TABLE ads (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
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
    start_at TIMESTAMP,
    end_at TIMESTAMP,
    enabled INTEGER NOT NULL DEFAULT 1,
    impressions INTEGER NOT NULL DEFAULT 0,
    clicks INTEGER NOT NULL DEFAULT 0,
    campaign_id INTEGER,                          -- set = a network campaign row, managed from the hub
    created_at TIMESTAMP NOT NULL
);

-- Network campaigns: filed once on the hub, fanned out to an ads row per paper.
CREATE TABLE campaigns (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    advertiser VARCHAR(160) NOT NULL DEFAULT '',
    notes TEXT,
    created_at TIMESTAMP NOT NULL
);

-- The hub's contact-form inquiries (civismedia.ca), per-site like ads.
CREATE TABLE inquiries (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    site_id INTEGER NOT NULL,
    name VARCHAR(120) NOT NULL DEFAULT '',
    email VARCHAR(191) NOT NULL DEFAULT '',
    organization VARCHAR(160) NOT NULL DEFAULT '',
    message TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'new',    -- new | handled
    ip VARCHAR(64) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL
);

-- The roadmap: the control room's living project document (network-global).
CREATE TABLE roadmap_items (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    kind VARCHAR(20) NOT NULL DEFAULT 'note',     -- phase | question | note
    title VARCHAR(255) NOT NULL,
    body TEXT,
    status VARCHAR(20) NOT NULL DEFAULT '',       -- phase: planned|in_progress|done · question: open|answered
    sort INTEGER NOT NULL DEFAULT 0,
    updated_by VARCHAR(120) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

-- The media monitoring desk's own feed list — kept apart from the newspaper
-- wire, so releases never pollute the morning pull.
CREATE TABLE monitor_feeds (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    url VARCHAR(600) NOT NULL,
    level VARCHAR(20) NOT NULL DEFAULT 'agency',  -- federal | provincial | municipal | agency
    region VARCHAR(40) NOT NULL DEFAULT '',
    doc_type VARCHAR(30) NOT NULL DEFAULT 'release',
    enabled INTEGER NOT NULL DEFAULT 1,
    last_fetched_at TIMESTAMP,
    last_status VARCHAR(255) NOT NULL DEFAULT ''
);

-- Government publications and press releases: from the external scraping
-- agent (/api/monitor), the feeds above, or captured by hand.
CREATE TABLE monitor_items (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    feed_id INTEGER,
    source_name VARCHAR(160) NOT NULL DEFAULT '',
    level VARCHAR(20) NOT NULL DEFAULT 'agency',
    region VARCHAR(40) NOT NULL DEFAULT '',
    doc_type VARCHAR(30) NOT NULL DEFAULT 'other', -- release|gazette|order-in-council|hansard|bill|tender|agenda|minutes|decision|report|other
    title VARCHAR(255) NOT NULL,
    url VARCHAR(600) NOT NULL,
    url_hash VARCHAR(40) NOT NULL UNIQUE,
    summary TEXT,
    body_excerpt TEXT,
    published_at TIMESTAMP,
    fetched_at TIMESTAMP NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',    -- new | flagged | claimed | used | dismissed
    flagged_by VARCHAR(120) NOT NULL DEFAULT '',
    claimed_by VARCHAR(120) NOT NULL DEFAULT ''
);

-- Story ideas: pitches from the desk (origin ai) and the newsroom — a
-- journalist claims one into a draft; never auto-filed stories.
CREATE TABLE story_ideas (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    monitor_item_id INTEGER,
    site_id INTEGER,
    title VARCHAR(255) NOT NULL,
    angle TEXT,
    rationale TEXT,
    region VARCHAR(40) NOT NULL DEFAULT '',
    origin VARCHAR(20) NOT NULL DEFAULT 'newsroom', -- ai | newsroom
    status VARCHAR(20) NOT NULL DEFAULT 'open',     -- open | claimed | dismissed
    claimed_by VARCHAR(120) NOT NULL DEFAULT '',
    created_by VARCHAR(120) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_posts_status ON posts (status, published_at);
CREATE INDEX idx_posts_category ON posts (category_id);
CREATE INDEX idx_posts_author ON posts (author_id);
CREATE INDEX idx_posts_region ON posts (region);
CREATE INDEX idx_news_region ON news_items (region, fetched_at);
CREATE INDEX idx_post_sites_site ON post_sites (site_id);
CREATE INDEX idx_ads_site_placement ON ads (site_id, placement);
CREATE INDEX idx_ads_campaign ON ads (campaign_id);
CREATE INDEX idx_inquiries_site ON inquiries (site_id, created_at);
CREATE INDEX idx_monitor_items ON monitor_items (level, region, fetched_at);
CREATE INDEX idx_monitor_status ON monitor_items (status, fetched_at);
CREATE INDEX idx_ideas_status ON story_ideas (status, created_at);

-- GA4 traffic, one row per site per day, pulled nightly by cron/analytics.php.
CREATE TABLE site_metrics_daily (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    site_id INTEGER NOT NULL,
    day DATE NOT NULL,
    sessions INTEGER NOT NULL DEFAULT 0,
    users INTEGER NOT NULL DEFAULT 0,
    pageviews INTEGER NOT NULL DEFAULT 0,
    engaged_sessions INTEGER NOT NULL DEFAULT 0,
    engagement_secs INTEGER NOT NULL DEFAULT 0,
    channels_json TEXT,
    top_pages_json TEXT
);
CREATE UNIQUE INDEX uq_metrics_site_day ON site_metrics_daily (site_id, day);

-- Search Console rows by query and by page; pruned to 16 months (GSC's own
-- retention).
CREATE TABLE gsc_daily (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    site_id INTEGER NOT NULL,
    day DATE NOT NULL,
    dim VARCHAR(10) NOT NULL,                     -- query | page
    dkey VARCHAR(255) NOT NULL,
    clicks INTEGER NOT NULL DEFAULT 0,
    impressions INTEGER NOT NULL DEFAULT 0,
    position REAL NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX uq_gsc_row ON gsc_daily (site_id, day, dim, dkey);
CREATE INDEX idx_gsc_site_dim ON gsc_daily (site_id, dim, day);

-- The entity directory the linkifier reads — admin-curated, seedable from
-- the Represent API (tools/import-represent.php).
CREATE TABLE entities (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    kind VARCHAR(40) NOT NULL DEFAULT 'politician', -- politician | organization | place | other
    url VARCHAR(600) NOT NULL DEFAULT '',
    aliases TEXT,                                   -- JSON array of alternate spellings
    enabled INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL
);

-- The agent control room's task queue. Every result is a proposal
-- (needs_review) until an editor approves; nothing applies itself.
CREATE TABLE agent_tasks (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    kind VARCHAR(40) NOT NULL,                      -- linkify | seo_meta | tagger
    status VARCHAR(20) NOT NULL DEFAULT 'queued',   -- queued → running → needs_review → approved | rejected | failed
    post_id INTEGER,
    site_id INTEGER,
    payload TEXT,
    result TEXT,
    log TEXT,
    created_by VARCHAR(120) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL,
    started_at TIMESTAMP,
    finished_at TIMESTAMP,
    reviewed_by VARCHAR(120) NOT NULL DEFAULT '',
    reviewed_at TIMESTAMP
);
CREATE INDEX idx_agent_tasks ON agent_tasks (status, created_at);
CREATE INDEX idx_agent_tasks_post ON agent_tasks (post_id, kind);

-- Sign-in attempts, success and failure alike. The login form's throttle
-- counts recent failures per email and per address; the hourly cron prunes
-- rows older than 30 days.
CREATE TABLE login_attempts (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email VARCHAR(191) NOT NULL DEFAULT '',
    ip VARCHAR(64) NOT NULL DEFAULT '',
    succeeded INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL
);
CREATE INDEX idx_login_attempts_email ON login_attempts (email, created_at);
CREATE INDEX idx_login_attempts_ip ON login_attempts (ip, created_at);

-- Append-only audit trail: sign-ins and the actions that shape the network
-- (settings, campaigns, syndication, agent approvals, accounts). Read from
-- the hub's Audit page; pruned after ~13 months.
CREATE TABLE audit_log (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    site_id INTEGER NOT NULL DEFAULT 0,
    user_id INTEGER NOT NULL DEFAULT 0,
    user_name VARCHAR(120) NOT NULL DEFAULT '',
    action VARCHAR(60) NOT NULL,
    target VARCHAR(160) NOT NULL DEFAULT '',
    detail TEXT,
    ip VARCHAR(64) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL
);
CREATE INDEX idx_audit_created ON audit_log (created_at);

-- What a story said, when — capped history per story (40 revisions), written
-- on every create/save/agent-approval/restore. Corrections and libel defence
-- both read from here; the editor's History panel restores from it.
CREATE TABLE post_revisions (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    post_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    lede TEXT,
    body TEXT,
    meta_description VARCHAR(255) NOT NULL DEFAULT '',
    correction TEXT,
    image VARCHAR(255) NOT NULL DEFAULT '',
    image_caption VARCHAR(255) NOT NULL DEFAULT '',
    saved_by VARCHAR(120) NOT NULL DEFAULT '',
    reason VARCHAR(40) NOT NULL DEFAULT 'edit',   -- create|edit|autosave|agent|restore
    created_at TIMESTAMP NOT NULL
);
CREATE INDEX idx_revisions_post ON post_revisions (post_id, id);

-- The cron ledger: every scheduled job files its outcome through
-- cron/run.php, and the five-minute watch reads staleness from here.
CREATE TABLE ops_runs (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    job VARCHAR(40) NOT NULL,
    site_id INTEGER NOT NULL DEFAULT 0,
    ok INTEGER NOT NULL DEFAULT 1,
    note VARCHAR(500) NOT NULL DEFAULT '',
    started_at TIMESTAMP NOT NULL,
    finished_at TIMESTAMP
);
CREATE INDEX idx_ops_runs_job ON ops_runs (job, id);

INSERT INTO settings (site_id, skey, svalue) VALUES (0, 'schema_version', '14');
