# Taking westernwire.ca live — deployment runbook

This runbook takes **Western Wire** live on the VPS that already serves
the network (prairiedispatch.ca, edmontonecho.com, and the rest). Same
codebase, same release directory, same shared Supabase database — an
eighth nginx server block and a config mapping, never a copy of the code.
Follow the steps in order; each ends with a verification to pass before
moving on.

Western Wire is different from the other seven papers in one structural
way: **it is the network's aggregator.** Most of its posts are *wire
links* — the editor pastes a URL from another newsroom, the tool reads
the page's headline, summary and featured image, the editor assigns a
region and tags, and the item publishes as a hyperlink. On the site, the
headline links straight to the outlet that reported the story, credited
by name. Original Western Wire stories work exactly like stories on any
other paper.

The Wire's domain is **westernwire.ca**. The bare domain is canonical;
`www` serves and redirects via the same block.

**The one rule stands:** credentials never enter the repository.
`config.php` lives only on the server.

---

## 0 · Pull and verify the release

- Branch `claude/western-wire-aggregator-iw8pth` — **pull the latest
  HEAD first.** This release carries schema version 7 (three new posts
  columns and an index for the aggregator), the Post-a-link newsroom
  tool, the `/region/` pages, the westernwire template, and the Wire's
  brand assets and launch package.

```bash
cd /path/to/release && git fetch origin claude/western-wire-aggregator-iw8pth \
  && git checkout claude/western-wire-aggregator-iw8pth \
  && git pull origin claude/western-wire-aggregator-iw8pth
```

**Verify:**

```bash
# the Wire's assets are present:
ls assets/sites/westernwire/       # launch.php, palette.json, mark.svg, og-default.png, img/
ls assets/css/westernwire.css

# every brand file must parse — prints one JSON blob per site, no errors:
for f in assets/sites/*/palette.json; do php -r "json_decode(file_get_contents('$f'), true, 512, JSON_THROW_ON_ERROR); echo '$f ok', PHP_EOL;"; done

# the new code parses:
php -l admin/link-post.php && php -l region.php && php -l app/views/front-westernwire.php
```

**Schema note:** the first request against any site after this pull
migrates the shared database from v6 to v7 (adds `posts.source_name`,
`posts.post_type`, `posts.region` and one index — additive only, the
other papers are unaffected). Nothing to run by hand; watch it happen in
step 4's verification.

## 1 · DNS check (both names)

```bash
dig +short westernwire.ca A
dig +short www.westernwire.ca A
```

If `dig` is missing use `getent hosts westernwire.ca` (glibc, no
install; it consults `/etc/hosts` first, so cross-check that file if the
answer looks wrong). Both names must resolve to this server before the
TLS step. If they don't, fix DNS at the registrar and wait; nothing
below works without it.

## 2 · nginx server block

Add an eighth block alongside the existing ones. It is the standard
paper block **plus one rewrite the older blocks don't have** — the
aggregator's `/region/` pages:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name westernwire.ca www.westernwire.ca;

    root /path/to/release;
    index index.php;

    # locations must appear before the PHP handler.
    location ~ ^/(app|data)/            { deny all; }
    location ~ ^/uploads/.+\.(php|phtml|phar)$ { deny all; }
    location ~ ^/(config\.php|config\.example\.php|router\.php)$ { deny all; }

    # Pretty URLs — the .htaccess rewrites.
    rewrite ^/story/([a-z0-9-]+)/?$   /article.php?slug=$1  last;
    rewrite ^/desk/([a-z0-9-]+)/?$    /section.php?slug=$1  last;
    rewrite ^/region/([a-z0-9-]+)/?$  /region.php?slug=$1   last;
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
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;   # match the existing blocks
    }
    location / { try_files $uri $uri/ =404; }
}
```

The `/region/` rewrite is harmless on the other papers' blocks but only
Western Wire needs it — no need to touch them.

```bash
nginx -t && systemctl reload nginx
```

**Verify:** `curl -H "Host: westernwire.ca" http://127.0.0.1/` returns
HTML (any of the papers' chrome is fine at this point — the mapping
comes in step 4).

## 3 · TLS

Issue the certificate the same way as the other papers (certbot shown;
match whatever the server actually uses):

```bash
certbot --nginx -d westernwire.ca -d www.westernwire.ca
```

**Verify:** `https://westernwire.ca/` serves with a valid certificate;
plain http redirects to https.

## 4 · The host mapping in config.php

Edit the server's existing `config.php` **in a plain-text editor on the
server**. Add one arm to each `match`:

```php
'site_slug' => match (true) {
    str_contains($host, 'westernwire')          => 'westernwire',
    str_contains($host, 'bramptonbulletin')     => 'brampton-bulletin',
    // ... existing arms unchanged ...
    default                                     => 'prairiedispatch',
},
'site_url' => match (true) {
    str_contains($host, 'westernwire')          => 'https://westernwire.ca',
    // ... existing arms unchanged ...
    default                                     => 'https://prairiedispatch.ca',
},
```

Everything else in config.php stays exactly as it is.

**Verify:** `php -l config.php` passes, then:

- `https://westernwire.ca/` renders **the Wire's chrome**: the dark
  Slate utility strip ("Western Canada · today's date"), the tracked-out
  WESTERN WIRE wordmark over the full-width rule with the gold live
  segment, the dark Front Range section nav with the four province
  links, and the `/assets/css/westernwire.css` stylesheet.
- The front is nearly empty ("Nothing on the wire yet") — correct until
  step 5.
- The first request self-provisioned the site row and ran the v7
  migration. Every other paper still renders its own chrome and its
  stories still open — that's the migration verified.
- Existing network admins sign in at `/admin/` immediately — no
  founding-account form.

## 5 · Launch content — one command

From the release directory:

```bash
PP_SITE=westernwire php tools/seed-launch.php
```

This fills the Wire: identity (Western Wire, "The West, on one wire",
The 6 a.m. Wire newsletter), the Energy & Resources and Culture desks
(the other desks are the network's shared ones), twelve western wire
feeds keyed by province (bc / alberta / saskatchewan / manitoba), and
eleven launch items. Safe to re-run; it never overwrites a setting the
newsroom has already changed.

**The launch wire items are real links.** Eight of them point at launch
stories on the network's own papers — the Pacific Post, Kelowna Current,
Kermode Chronicle, Edmonton Echo, Grande Prairie Gazette and Prairie
Dispatch — so every outbound headline resolves to a live page you
control. The other three are original Western Wire stories (the potash
piece, the flood-maps piece, and "How Western Wire works"). Feeds
already registered by sister papers are matched by URL and skipped —
that's expected output, not an error.

**Verify, all on https://westernwire.ca:**

- `/` — the Echo's curbside-parking story leads with the streetlight
  illustration; its headline goes **to edmontonecho.com in a new tab**.
- Wire items carry the credit line — "The Pacific Post · 2 h" — and
  original stories carry a byline instead.
- `/region/bc`, `/region/alberta`, `/region/saskatchewan`,
  `/region/manitoba` — each renders its province archive.
- "Across the four provinces" shows all four columns.
- `/story/potash-on-the-water-…` (original) renders **on** the Wire;
  `/story/two-hours-on-curbside-parking-…` (wire link) **302-redirects**
  to the Echo.
- `/feed/` — wire items' `<link>` elements point at the source outlets.
- `/sitemap.xml` — contains the original stories, none of the wire links.

## 6 · Cron

Nothing new to install. The existing per-minute/daily cron already calls
`cron/fetch-news.php`, which fetches **all** enabled sources network-wide
— including the Wire's twelve new provincial feeds — into the shared
pool. Confirm after the next run:

- Newsroom → Dashboard on the Wire shows four region tabs (British
  Columbia / Alberta / Saskatchewan / Manitoba) with fresh headlines.

If the server ever runs a per-site cron with `PP_SITE`, add
`PP_SITE=westernwire` for the newsletter send; the fetch itself is
shared either way.

## 7 · Mail — before enabling The 6 a.m. Wire

Same procedure as the other papers: create `sixam@westernwire.ca` (and
make sure `tips@westernwire.ca` exists — the launch settings and the
About story print it), set the SMTP settings and the paper's mailing
address in Newsroom → Settings, send yourself a test from Newsroom → The
6 a.m., and set SPF/DKIM for westernwire.ca in DNS before flipping
`newsletter_enabled`. Note the newsletter's links go through the Wire's
own `/story/` URLs, so wire items in the email redirect to the source
outlet — by design.

## 8 · The aggregator, day to day

This is the workflow the Wire exists for:

- **Newsroom → Post a link** — paste any article URL, hit *Fetch the
  details*, and the headline, summary, outlet name and featured image
  fill in from the page's Open Graph tags. Assign region, desk, tags;
  *Post to the wire*. The image is cached into `/uploads/` by default so
  cards survive the outlet moving theirs; the credit line always names
  the outlet.
- **Dashboard → the morning pull** — every wire headline now has a
  **Post link** button beside *Start draft*. It lands on Post-a-link
  prefilled from the feed item, region already set.
- **The story editor** — every post has a *Kind* selector (Original
  story / Wire link), a source credit and a region, so anything the
  quick tool created can be re-edited, and any story can be converted.
- Wire links run on Western Wire only unless an editor opens them in
  the story editor and ticks other sites under *Runs on*.

## 9 · Same-day housekeeping

- The eight cross-network launch links are real and can simply stay;
  replace the three demonstration originals as real reporting lands.
- Images: the Post-a-link tool caches the outlet's Open Graph image and
  credits the outlet by name. If an outlet objects to image reuse,
  clear the image field when posting their links — the wire renders
  headline-only items cleanly. "How we aggregate" expectations live in
  the About story; keep it accurate.
- Set `breaking_label` + `breaking_url` in Settings to light the gold
  **Developing** strip under the nav; clear them to drop it.

## Troubleshooting

| Symptom | Likely cause |
| --- | --- |
| `/region/…` 404s but `/region.php?slug=…` works | the new rewrite is missing from the Wire's server block (step 2) |
| Post-a-link says the page couldn't be fetched | the outlet blocks server-side fetches or the URL isn't public — fill the fields by hand, the form still posts |
| A wire headline opens the Wire's own story page | the post's *Kind* is still "Original story" or its source link is empty — fix both in the editor |
| Wire card image broken after a while | the item was posted with *Cache a copy* unticked — edit the post and paste a local `/uploads/` path, or re-cache via Post-a-link |
| Region tabs empty on the dashboard | the cron hasn't run since the Wire's feeds were added — run `php cron/fetch-news.php` once by hand |

## What NOT to do

- Don't change `site_slug` mappings after first boot.
- Don't copy the release directory — the Wire is a server block plus a
  mapping, on the same checkout as every other paper.
- Don't hand-edit the shared database to "convert" stories; the story
  editor's *Kind* selector does it safely.
- Don't strip the credit or the outbound link from wire items. Credited
  headlines that link out are what make an aggregator welcome; the
  About story promises it publicly.
