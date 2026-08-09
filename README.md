# The Prairie Post

A regional news site and newsroom CMS built on the Prairie Post brand system —
*News to the horizon*. PHP 8 + SQLite (or MySQL), no framework, no build step,
no Composer. Upload it to shared hosting, or run it locally with one command.

The full brand package (guide, tokens, logos) lives in [`design/`](design/) —
open `design/prairie-post-brand-guide.html` in a browser.

## What's here

**Public site** — front page (pinned hero, latest river, desk blocks, closing
prices, forecast, newsletter signup), story pages, desk archives, archive
search, RSS feed (`/feed/`), `sitemap.xml`, `robots.txt`, labelled ad slots,
Open Graph / Twitter cards / NewsArticle JSON-LD.

**Newsroom** (`/admin/`) — dashboard with the morning wire pull (regional tabs;
*Start draft* turns a headline into a draft with the source linked, *Mark used*
ticks it off), story editor (formatting toolbar, image upload, drafts,
scheduling, search description), stories list with filters, desks, wire sources
with per-feed test, subscriber list with CSV export, accounts (admin/editor),
settings.

**Automation** — `cron/fetch-news.php` fetches every enabled feed,
de-duplicates by URL, prunes stale unused items, and publishes scheduled
stories whose time has come.

**Security** — CSRF tokens on every admin form, `password_hash` credentials,
prepared statements throughout, `app/` and `data/` blocked from the web, PHP
execution disabled in `uploads/`, MIME-checked image uploads, whitelist
sanitization of story HTML.

## Run it locally

```bash
php -S localhost:8080 router.php
```

Open http://localhost:8080. First run creates `data/prairiepost.sqlite`,
installs the schema, and seeds the desks, settings, wire sources and sample
stories. Then open http://localhost:8080/admin/ — with no accounts yet, the
sign-in page becomes a one-time form that creates the founding administrator.

## Deploy to shared hosting (Hostinger or similar)

1. Upload everything in this repository to the site's web root
   (`public_html/`). Keep the directory layout; the `.htaccess` files matter.
2. Copy `config.example.php` to `config.php`. SQLite works as-is; for MySQL,
   create a database in the hosting panel and switch the `db` block. Set
   `site_url` to the real domain so feeds and sitemaps carry absolute URLs.
3. Make sure `data/` and `uploads/` are writable by PHP (this is the default on
   most shared hosts).
4. Visit `/admin/` and create the founding account **immediately** — the first
   visitor to a fresh install gets that form.
5. Add the cron job (hosting panel → Cron Jobs), daily or hourly:

   ```
   php /home/USER/public_html/cron/fetch-news.php
   ```

   No shell cron? Use the authenticated URL shown in **Settings → The cron
   job** with any uptime/cron pinger.

Requirements: PHP 8.1+ with `pdo_sqlite` (or `pdo_mysql`), `simplexml`, `curl`,
`fileinfo`. Apache with `mod_rewrite` for pretty URLs (the standard shared-host
setup); the PHP built-in server uses `router.php` instead.

## Wire sources

Seeded with feeds verified working at build time: Drumheller Mail, ECA Review,
Strathmore Times and CBC Calgary (local); CBC Edmonton, Global Calgary and
Global Edmonton (Alberta); CBC Top Stories, CBC Canada and Global National
(Canada); Canadian Cattlemen, Grainews, Manitoba Co-operator and Farmtario
(agriculture wire).

Candidates that **failed** verification and were left out: CTV (Calgary and
national), RealAgriculture, Western Producer and Alberta Farmer Express — those
publishers block automated readers. If you add them later, the per-feed *Test*
button on the Sources page will tell you plainly whether they answer.

Region tabs are defined in Settings as JSON. The region *keys* are stored on
every fetched item, so name them once and rename with care.

## The design system, in code

Two rules from the brand guide that are easiest to lose in a build, both
honoured here and worth protecting in future changes:

1. **The horizon** is 4px of ink plus a 1px hairline two pixels below —
   `.pp-horizon` in `assets/css/prairie.css` — never a single border.
2. **Border radius is zero everywhere**, including buttons, cards, inputs and
   images. A rounded corner reads as a different paper.

Colours, type roles (`Archivo` display / `Newsreader` body / `IBM Plex Mono`
utility) and desk assignments follow `design/prairie-post-tokens.css`. Each
desk owns one colour, used in exactly two places: the nav underline and the
eyebrow. Fill-only colours (Weather's Noon Sky) never carry text — the desks
admin has a "fill only" flag that switches the eyebrow to ink-on-block.

Fonts load from Google Fonts by default. Self-hosting them (drop the woff2
files in `assets/fonts/` and swap the `@import` in both stylesheets) is faster
and keeps every request on your own domain.

## Layout

```
index.php  article.php  section.php  search.php     public pages
feed.php   sitemap.php  subscribe.php robots.txt    syndication & signup
router.php                                          dev server routing
app/        bootstrap, schema, models, helpers, feed engine, seed
admin/      the newsroom
assets/     stylesheets, editor JS, logos, placeholder art
cron/       fetch-news.php
data/       SQLite database (blocked from the web, gitignored)
design/     the original brand package
uploads/    reader-visible images (PHP execution disabled)
```
