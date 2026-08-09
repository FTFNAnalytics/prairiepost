# The Prairie Post

A regional news site and newsroom CMS built on the Prairie Post brand system —
*News to the horizon*. PHP 8 with SQLite, a shared Postgres/Supabase network
database, or MySQL. No framework, no build step, no Composer. Upload it to
shared hosting, or run it locally with one command.

The full brand package (guide, tokens, logos) lives in [`design/`](design/) —
open `design/prairie-post-brand-guide.html` in a browser.

## What's here

**Public site** — front page (editor-set hero, featured band, desk blocks with
desk leads, latest river, closing prices, forecast, newsletter signup), story
pages, desk archives, archive search, RSS feed (`/feed/`), `sitemap.xml`,
`robots.txt`, labelled ad slots, Open Graph / Twitter cards / NewsArticle
JSON-LD, the public corrections file (`/corrections`), and the newsletter
archive (`/newsletter/`).

**Front page placements** — each published story can carry one placement, set
in the editor: **Hero** (the lead; setting a new one demotes the old), **Front
featured** (the photo-card band under the hero, up to four), or **Desk lead**
(tops its desk block and archive). The dashboard shows the front page as
currently set. With nothing placed, the latest stories stand in.

**The 6 a.m., delivered** — the daily newsletter compiles itself from the last
24 hours (hero first, then by desk, with the forecast and closing prices),
renders as email-safe HTML in the brand, and sends to the active list through
your host's SMTP (or PHP `mail()`); the cron sends once per day after the
configured hour. Every edition is archived publicly at `/newsletter/{date}`.
Admin page (Newsroom → The 6 a.m.): mail settings, preview, send-me-a-test,
send-now, and the send log. **CASL is built in**: double opt-in confirmation
emails with a stored consent note, per-subscriber tokens, one-click
unsubscribe honoured instantly, `List-Unsubscribe` header, and the paper's
mailing address in every footer. Deliverability note: set SPF/DKIM for the
From domain in DNS or editions land in spam.

**Corrections** — editors file a correction on any story; it renders as the
Bin Red block above the text and joins the public corrections file.

**Newsroom** (`/admin/`) — dashboard with the morning wire pull (regional tabs;
*Start draft* turns a headline into a draft with the source linked, *Mark used*
ticks it off) and the **review queue**, story editor (formatting toolbar, image
upload, drafts, submit-for-review, scheduling, syndication picker, search
description, live word count, 30-second autosave for drafts, images inside
story text), stories list with filters, desks, wire sources with per-feed
test, the ad manager, subscriber list with CSV export, accounts, profiles,
settings.

**Editorial workflow** — three roles. *Authors* write and edit their own
stories and submit them for review; they cannot publish (enforced server-side,
not just hidden in the UI). *Editors* review the queue, send stories back with
a note, publish, schedule, pin to the front page, and choose which sites a
story runs on. *Admins* additionally manage accounts and settings. Public
**author profiles** live at `/author/{slug}` — photo, title, bio, and the
byline archive; every account edits its own under **Your profile**.

**The network** — point several sites at one shared Postgres database
(Supabase) and they become a content network: stories, newsroom accounts,
desks, tags and the wire pool are shared, while settings (title, tagline,
ads, markets) and subscribers stay per-site. Each deployment sets a
`site_slug` in `config.php`; an editor's *Runs on* checkboxes decide which
papers a story appears in. One filing, the whole network.

**Advertising** — an ad manager per site (Newsroom → Ads) with the three
labelled slots: top of the front page, the rail, and after story text. Three
kinds of creative: **house ads** set in the brand (no artwork needed — kicker,
heading, a sentence, a button), uploaded **image + link** creatives, and
pasted **embed code**. Several ads in a slot rotate evenly; clicks route
through a counter, so served/click numbers in the admin are real. Empty slots
render nothing.

**Social cards** — every story gets a generated 1200×630 Open Graph card at
`/card/{slug}.png`: desk kicker, the horizon rule, the headline in condensed
type, the five-band prairie ground. Cards are drawn server-side with the
bundled OFL fonts (`assets/fonts/`), cached in `data/cards/`, and re-render
when the story changes. Story pages carry a share row (Facebook, X, Bluesky,
LinkedIn, email, copy link) and the editor links each published story's card
for manual posting.

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

Requirements: PHP 8.1+ with `pdo_sqlite` / `pdo_pgsql` / `pdo_mysql` to match
your driver, plus `simplexml`, `curl`, `fileinfo`. Apache with `mod_rewrite`
for pretty URLs (the standard shared-host setup); the PHP built-in server uses
`router.php` instead.

## Running a network on Supabase

One Supabase project can feed any number of Prairie Post sites:

1. In `config.php` on **every** site, set the `db` driver to `pgsql` and fill
   the `pgsql` block with the **Session pooler** details from Supabase
   (Dashboard → Connect → Session pooler):
   host `aws-0-<region>.pooler.supabase.com`, port `5432`, database
   `postgres`, user `postgres.<project-ref>`, and the database password.
   Do **not** use the `db.<ref>.supabase.co` host — it is IPv6-only and
   unreachable from most shared hosting.
2. Give each site its own `site_slug`. First boot provisions the site row and
   its default settings automatically; the first site to ever touch the
   database also installs the schema and seed content. (The same schema is in
   `supabase/schema.sql` if you prefer to run it yourself in the SQL Editor.)
3. Newsroom accounts are shared — one sign-in works on every site's `/admin`.
   Stories are filed once and syndicated with the editor's *Runs on*
   checkboxes. Settings, branding, ads, markets and subscribers stay per-site.
4. If outbound Postgres (port 5432) is blocked by your host, ask support to
   open it — it's the one network requirement the shared database adds.

A single-site install that started on SQLite upgrades in place: the app
migrates the old schema on first request after updating the code.

Keep the database password out of the repository — it belongs only in
`config.php`, which is gitignored. Rotate it in Supabase if it has ever been
shared anywhere less private.

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
index.php  article.php  section.php  search.php  author.php   public pages
feed.php   sitemap.php  subscribe.php robots.txt              syndication & signup
router.php                                                    dev server routing
app/        bootstrap, schema + migrations, models, helpers, feed engine, seed
admin/      the newsroom (dashboard, editor, review queue, accounts, profiles)
assets/     stylesheets, editor JS, logos, placeholder art
cron/       fetch-news.php
data/       SQLite database (blocked from the web, gitignored)
design/     the original brand package
supabase/   schema.sql — the shared network database schema
uploads/    reader-visible images (PHP execution disabled)
```
