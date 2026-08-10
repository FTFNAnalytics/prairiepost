# Taking The Prairie Dispatch live — deployment handoff

This file walks a deployment operator (human or agent) through putting
**prairiedispatch.ca** live from this repository. Follow the steps in order;
each stage ends with a verification you must pass before moving on. Nothing
here requires editing application code.

**The one rule:** credentials never enter this repository. `config.php` is
gitignored and lives only on the server. Do not commit it, do not paste
secrets into logs, issues, or commit messages.

---

## 0 · What you are deploying

- Branch: `claude/prairie-post-news-site-hiffgl` (also the repo default) —
  deploy its HEAD.
- Stack: PHP 8.1+, no framework, no build step, no Composer. Apache with
  `mod_rewrite` (standard shared hosting) or the PHP built-in server for
  smoke tests.
- Database: a shared Supabase Postgres instance (the content network).
  The app **installs its own tables and seed content on first connection** —
  you do not need to run any SQL by hand. (`supabase/schema.sql` exists for
  reference/manual setup only; never run it against a database the app has
  already initialized.)
- **Namespace isolation:** the app creates and lives entirely inside its own
  Postgres schema (default `prairiedispatch`), never `public`. Other
  applications already in the same Supabase project — their `settings`,
  `sites`, `users`, or anything else — are untouched and invisible to it.
- Required PHP extensions: `pdo_pgsql`, `mbstring`, `simplexml`, `curl`,
  `fileinfo`, `gd` (and `openssl`, normally built in, for SMTP TLS).
  Debian/Ubuntu package names: `php8.3-pgsql php8.3-mbstring php8.3-xml
  php8.3-curl php8.3-gd` (`fileinfo` ships in the core package).
  Check with: `php -m | grep -E 'pdo_pgsql|mbstring|simplexml|curl|fileinfo|gd'`
  — all six must print **before** first boot.

## 1 · Rotate the database password FIRST

The current Supabase database password has been shared over insecure
channels and must be considered burned.

1. Supabase Dashboard → Project Settings → Database → **Reset database
   password**. Generate a strong one; store it in your secrets manager.
2. Use only the new password from here on.

## 2 · Get the connection details (session pooler — not the direct host)

From Supabase Dashboard → **Connect** → **Session pooler**:

- Host: `aws-0-ca-central-1.pooler.supabase.com`
- Port: `5432`
- Database: `postgres`
- User: `postgres.uggnjfladcgzqolfherx`
- Password: the rotated one from step 1

**Do not use `db.uggnjfladcgzqolfherx.supabase.co`.** That direct host is
IPv6-only and unreachable from most hosting; the session pooler is the
supported path. The app already handles pooler quirks (prepared-statement
emulation, TLS) — no tuning needed.

## 3 · Upload the code

Clone or upload the repository contents to the web root for
prairiedispatch.ca (e.g. `public_html/`). Keep the directory layout exactly —
the `.htaccess` files in the root, `uploads/`, and `data/` are load-bearing.

Then make the runtime directories writable by PHP:

```bash
chmod 775 data uploads     # or whatever your host's writable convention is
```

### 3a · If the server is nginx (VPS), not Apache

`.htaccess` files do nothing under nginx — the pretty URLs **and the
protection rules** must be replicated in the server block, or `/app/` and
`/data/` are exposed and uploaded files could execute. Use this as the
baseline (adjust root and the php-fpm socket):

```nginx
server {
    server_name prairiedispatch.ca;
    root /var/www/current;          # the active release
    index index.php;

    # Protection — the .htaccess equivalents. Order matters: these regex
    # locations must appear before the PHP handler.
    location ~ ^/(app|data)/            { deny all; }
    location ~ ^/uploads/.+\.(php|phtml|phar)$ { deny all; }
    location ~ ^/(config\.php|config\.example\.php|router\.php)$ { deny all; }

    # Pretty URLs — the .htaccess rewrites.
    rewrite ^/story/([a-z0-9-]+)/?$   /article.php?slug=$1  last;
    rewrite ^/desk/([a-z0-9-]+)/?$    /section.php?slug=$1  last;
    rewrite ^/author/([a-z0-9-]+)/?$  /author.php?slug=$1   last;
    rewrite ^/card/([a-z0-9-]+)\.png$ /card.php?slug=$1     last;
    rewrite ^/newsletter(/.*)?$       /newsletter.php?path=$1 last;
    rewrite ^/search/?$               /search.php            last;
    rewrite ^/feed/?$                 /feed.php              last;
    rewrite ^/sitemap\.xml$           /sitemap.php           last;
    rewrite ^/subscribe/?$            /subscribe.php         last;
    rewrite ^/ad/?$                   /ad.php                last;
    rewrite ^/corrections/?$          /corrections.php       last;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location / { try_files $uri $uri/ =404; }
}
```

After enabling, re-run the step-5 verification — especially the check that
`/app/bootstrap.php` is denied.

## 4 · Create config.php on the server

Copy `config.example.php` to `config.php` and set:

```php
return [
    'db' => [
        'driver' => 'pgsql',
        'sqlite_path' => __DIR__ . '/data/prairiedispatch.sqlite',   // unused with pgsql
        'pgsql' => [
            'host'    => 'aws-0-ca-central-1.pooler.supabase.com',
            'port'    => 5432,
            'name'    => 'postgres',
            'user'    => 'postgres.uggnjfladcgzqolfherx',
            'pass'    => 'THE-ROTATED-PASSWORD',
            'sslmode' => 'require',
            'schema'  => 'prairiedispatch',   // the app's own namespace in the
                                              // shared database; created
                                              // automatically, must match on
                                              // every network site
        ],
        'mysql' => [ /* leave as-is, unused */ ],
    ],
    'site_slug' => 'prairiedispatch',   // the site's permanent identity in the
                                        // shared database. Pick once, never change:
                                        // changing it later creates a second,
                                        // empty site.
    'site_url' => 'https://prairiedispatch.ca',
    'timezone' => 'America/Edmonton',
    'debug'    => false,
];
```

**Verify** before going further, from the server's shell:

```bash
php -r '$c=(require "config.php")["db"]["pgsql"];
$p=new PDO("pgsql:host={$c["host"]};port={$c["port"]};dbname={$c["name"]};sslmode=require",$c["user"],$c["pass"]);
echo "DB CONNECTION OK\n";'
```

If this fails with a connection error, outbound port 5432 is blocked on the
host — ask hosting support to open outbound TCP 5432. That is the only
network requirement this stack adds.

## 5 · First boot

1. Request `https://prairiedispatch.ca/` once. The first request against an
   empty database installs the schema and seeds the desks, settings, wire
   sources, and sample stories. Expect the front page to render fully.
2. **Immediately** open `https://prairiedispatch.ca/admin/`. With no accounts
   in the database, the sign-in page is a one-time "create the founding
   administrator" form — whoever submits it first owns the paper. Create the
   real admin account now; don't leave a fresh install unclaimed.

**Verify:**
- `/` renders with the masthead THE PRAIRIE DISPATCH and sample stories
- `/story/canola-contracts-move-early-as-growers-watch-a-dry-june` renders
- `/desk/agriculture`, `/search?q=canola`, `/feed/`, `/sitemap.xml` all 200
- `/card/canola-contracts-move-early-as-growers-watch-a-dry-june.png`
  returns a 1200×630 PNG (proves GD + fonts work)
- `/admin/` shows the dashboard after sign-in, wire tabs present
- `https://prairiedispatch.ca/app/bootstrap.php` returns **403** (protection
  rules active — if it returns code or 200, `.htaccess` isn't being honoured;
  fix before continuing)

## 6 · Cron

Hosting panel → Cron Jobs. One job, daily at **06:00 America/Edmonton**:

```
php /path/to/webroot/cron/fetch-news.php
```

It fetches all wire feeds, de-duplicates, prunes stale items, publishes
scheduled stories, and sends the day's newsletter (once enabled). Hourly is
also fine — the newsletter still sends at most once per day, after the
configured hour.

No shell cron available? Use the authenticated URL shown in the admin under
**Settings → The cron job** with any external cron pinger. Treat the secret
in that URL as a credential.

**Verify:** run it once by hand; expect per-feed `ok` lines and a summary.
Two or three publishers may show `error` (CTV and some Postmedia titles block
automated readers — known, documented in the README).

## 7 · Mail — before enabling The 6 a.m.

1. Create the sending mailbox at the host, e.g. `sixam@prairiedispatch.ca`.
2. DNS for `prairiedispatch.ca`: add the host's **SPF** record and enable
   **DKIM** signing (hosting panel → Email → DKIM, or add the provided TXT
   records). Without these, editions land in spam. Add DMARC
   (`v=DMARC1; p=quarantine;`) once SPF/DKIM pass.
3. In the newsroom: **The 6 a.m.** → fill in SMTP host/port/user/password
   (from the hosting panel's email section), From address
   `sixam@prairiedispatch.ca`, From name `The Prairie Dispatch`, and the
   paper's **postal mailing address** — Canada's anti-spam law (CASL)
   requires it in every edition, and the footer template prints it.
4. Click **Send me a test**. Check it arrives in a real inbox (not spam) at
   Gmail and one other provider.
5. Only then tick **Send daily** and save.

Signups on the site are double-opt-in automatically once mail is configured.

## 8 · Post-launch housekeeping (same day)

- Supabase: enable **point-in-time recovery / backups** (Dashboard →
  Database → Backups).
- Confirm HTTPS is forced on prairiedispatch.ca (host panel; add a
  redirect if the host doesn't force it).
- Replace the seeded sample stories with real ones, or unpublish them
  (Newsroom → Stories). The desks, wire sources and settings are meant to
  keep.
- In **Settings**, confirm the site title, tagline, and search description;
  in **Desks**, adjust names if the launch lineup differs.
- Delete this deployment's shell history if the DB password was ever typed
  into a command line.

## 9 · What NOT to do

- Don't commit `config.php`, or any file containing the database password,
  SMTP password, or cron secret.
- Don't run `supabase/schema.sql` after the app has installed itself —
  it will error on existing tables; it's for empty-database manual setup only.
- Don't change `site_slug` after first boot (it creates a second empty site).
  The display name is a Setting; the slug is identity.
- Don't "fix" the direct `db.*.supabase.co` host into the config — it's
  IPv6-only and will fail from the host. Pooler only.
- Don't grant the admin URL to anyone before the founding account exists.

## 10 · Adding the next paper to the network

Each additional site is the **same codebase and the same release** — never a
fork. On the docket: Edmonton Echo (`edmonton-echo`), Grande Prairie Gazette
(`grande-prairie-gazette`), Pacific Post (`pacific-post`), Burrard Bulletin
(`burrard-bulletin`), Kermode Chronicle (`kermode-chronicle`). Brand lockups
for the first two are already committed under `assets/sites/`.

Per new paper:

1. **Brand** (if not already in the repo): a site's whole identity lives in
   `assets/sites/<slug>/` —
   `python3 tools/make-brand.py <slug> "The Paper Name" --ink "#14263B" --paper "#F2F4F6" --board "#C2C9D1"`
   generates the lockups in the paper's own ink; `palette.json` sets the
   colours for social cards, the newsletter and per-site **desk accent
   colours** (`"desks": {...}` — desks are shared data, colours are per
   paper); `brand.css` overrides the `--pp-*` tokens for the site chrome.
   Anything absent falls back to the network default. Edmonton Echo (blue &
   orange) and Grande Prairie Gazette (aurora purple) are complete worked
   examples. Commit, release.
2. **Deploy**: new nginx server block (§3a pattern) + TLS for the new
   domain. The document root can be the **same release directory** — put the
   per-site choice in `config.php` by mapping the host, e.g.:
   ```php
   'site_slug' => match (true) {
       str_contains($_SERVER['HTTP_HOST'] ?? '', 'edmontonecho')  => 'edmonton-echo',
       str_contains($_SERVER['HTTP_HOST'] ?? '', 'gpgazette')     => 'grande-prairie-gazette',
       default => 'prairiedispatch',
   },
   ```
   (Separate per-site directories with their own `config.php` also work —
   but then **share one `uploads/` directory via symlink**, or images on
   syndicated stories 404 on the other papers.)
3. **First boot joins, not installs**: the site row and its default settings
   self-provision; no sample stories are seeded; nothing else in the database
   is touched. There is **no founding-account step** — newsroom accounts are
   network-wide, so existing admins sign in to the new `/admin` immediately.
4. **Launch content**: if the paper has a committed launch package —
   `assets/sites/<slug>/launch.php`; **the Edmonton Echo and the Grande
   Prairie Gazette both ship one** — run, from the web root, after first
   boot:
   ```
   PP_SITE=edmonton-echo php tools/seed-launch.php
   ```
   It fills the paper so it looks finished on day one: launch desks, settings
   (title, regions, weather/traffic/events rails, contact email), wire
   sources, and demonstration stories with commissioned art. Safe to re-run:
   stories are matched by slug, sources by URL, and settings the newsroom has
   already changed are never overwritten. The demonstration stories are
   clearly-voiced launch content meant to be replaced by real reporting.
5. **In the new site's admin**: whatever the launch package didn't cover —
   title/tagline, wire **region tabs** (Settings → regions JSON; the Echo's
   package already adds an `edmonton` region and three Edmonton feeds),
   newsletter identity + SMTP, **SPF/DKIM on the new domain** (per-domain,
   never inherited), CASL postal address, markets/weather, ads.
6. **Cron**: one job per site. With the domain-mapped single directory, the
   CLI has no host to map, so set the site explicitly per job:
   `PP_SITE=edmonton-echo php cron/fetch-news.php`. Feed fetching is shared
   and de-duplicated, so overlap between sites' crons is harmless — each
   site's job matters for its own newsletter and scheduled stories.
7. Syndication: editors tick "Runs on" per story.

## Troubleshooting quick table

| Symptom | Cause → fix |
|---|---|
| `SQLSTATE[08006]` on connect | Outbound 5432 blocked → hosting support opens it; or wrong host (use the pooler) |
| Front page 500s on first boot | Check `debug => true` temporarily; usually a missing PHP extension (`pdo_pgsql`, `gd`) |
| Pretty URLs 404 but `/index.php` works | `mod_rewrite`/`.htaccess` not honoured → enable AllowOverride |
| Cards return the default image only | `gd`/FreeType missing, or `assets/fonts/` didn't upload |
| Newsletter test lands in spam | SPF/DKIM not set for prairiedispatch.ca |
| A feed shows `error` on every fetch | Publisher blocks bots (CTV, Postmedia) — expected; pause the source |
| `/app/` files download as source | PHP not executing / handler misconfigured → hosting support |
| Installer "sees" another app's tables / crashes on missing `posts` | Running a pre-schema build, or `schema` missing from config → pull latest code, set `db.pgsql.schema`, retry — the app never touches `public` |
