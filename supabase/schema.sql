-- ============================================================================
-- The Prairie Post network — shared content database schema (Postgres).
--
-- Run this once in the Supabase SQL Editor, OR simply point a site's
-- config.php at the database: the app installs this same schema automatically
-- on first connection if the tables don't exist yet.
--
-- Shared across the network:  sites, users (newsroom accounts), categories,
--                             posts (+ post_sites syndication), tags, sources,
--                             news_items (the wire pool)
-- Per-site:                   settings (keyed by site_id), subscribers
--
-- The PHP layer talks to this over the SESSION POOLER
-- (aws-0-<region>.pooler.supabase.com:5432) with sslmode=require and
-- emulated prepares, so it also works on the transaction pooler (6543).
-- ============================================================================

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
    status VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft | in_review | scheduled | published
    review_note TEXT,
    is_featured INTEGER NOT NULL DEFAULT 0,
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
    created_at TIMESTAMP NOT NULL,
    CONSTRAINT uq_sub UNIQUE (site_id, email)
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
    created_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_posts_status ON posts (status, published_at);
CREATE INDEX idx_posts_category ON posts (category_id);
CREATE INDEX idx_posts_author ON posts (author_id);
CREATE INDEX idx_news_region ON news_items (region, fetched_at);
CREATE INDEX idx_post_sites_site ON post_sites (site_id);
CREATE INDEX idx_ads_site_placement ON ads (site_id, placement);

INSERT INTO settings (site_id, skey, svalue) VALUES (0, 'schema_version', '3');
