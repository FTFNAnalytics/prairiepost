# Roadmap — the platform plan and where it stands

## Current state · 24 Nov 2026

**Live:** 27 sites, which is 26 papers and the CivisMedia hub, at
schema version 25. Steeltown Standard and Toronto Telegraph are in that
count; their launch report hasn't reached the repository. One foundation
paper, `terminal-city-times`, waits for its brand package. Twelve more
mastheads are named in `NETWORK-EXPANSION.md`; none is registered or
built.

**Rolled:** `7cb82fb` (the Stories list's bulk status change, and each
paper's newsroom wearing that paper's branding), a code-only roll from
`feb2b1f4f6eb`. Its report hasn't reached the repository, but all 27
domains serve `assets/js/bulk.js`, which only that tree carries (checked
from outside on 24 Nov).

**Analytics:** eight papers load analytics as of 24 Nov. Kermode
Chronicle carries a Google Analytics tag, and seven papers each carry
their own Google Tag Manager container. No paper has a privacy page yet
(see the policy decisions below).

**Merged, not rolled:**
- photo metadata stripping (9.1 below, #91) and the image-hold fix for an
  editor's publish (9.5, #90), whose brief was
  `docs/briefs/roll-72f8adccbcca.md`;
- live weather (9.6, #94), the whiteboard and tips form (9.10, #95), the
  launch-day widgets retired (9.7, #96), each paper's own clock (9.8,
  #97), and the routing half of 9.2 (#98), all merged 25 Nov.

The brief that rolls all of it is `docs/briefs/roll-1458b09870e0.md`.
It raises the schema to 26 (forward only), and the upgrader converts the
hand-written nginx blocks. Afterwards it adds the weather job's cron line,
and cleans the stored images if the earlier brief's cleanup never ran.

The 24 Nov audit's loose ends are 9.6–9.10. The owner settled its three
open questions on 25 Nov (decided, under the policy decisions below).

**The 25 Nov review** checked all 27 sites against good practice in news
and design. Its findings are Phase 10 (proposed, nothing started), and
its first item is the roll above.

**The platform phases below (0–3):** Phases 2 and 3 are done. Phase 0
is done except the GitHub default branch, which is the owner's switch.
Phase 1 is done except converting the 14 hand-written nginx blocks and
the catch-all server, both of which have moved to Phase 9. Phase 3's
ingest path has kept growing since it closed:

- agent ingest v2: media upload and hub-minted keys (27 Sep);
- the publish-on-image lane (28 Sep);
- story lookup (21 Nov);
- the read lane and the two edit lanes, for drafts and published
  stories (22–23 Nov).

The contract is `docs/api-ingest.md`.

**The hub's phases (1–8)** are in `PLAN-CIVIS.md`, with their runbooks
in `DEPLOY-CIVIS.md` §9–14. Phase 8 (resilience) built the backups, the
five-minute watch, revisions, session lifecycle and the edge kit. The
hub's own Roadmap page (database-backed, edited in the admin) is the
newsroom's working copy of that plan; this file is the repository's.

| Phase | Status, 24 Nov |
| --- | --- |
| 0 Safety nets | 0.1, 0.2, 0.4 done. 0.3 waits on the owner. |
| 1 Launch machinery | 1.1, 1.4, 1.5 done. 1.2 is done for new blocks; the 14 legacy blocks and 1.3 are now 9.2. |
| 2 Template registry | Done. 2.3 is in the backlog by owner decision. |
| 3 Hermes ingest | Done, and extended (above). |
| Hub 1–8 | See `PLAN-CIVIS.md` and `DEPLOY-CIVIS.md`. |
| **9 Exposure and upkeep** | **Next. 9.1 and 9.5 merged 24 Nov (#91, #90), rolling with `roll-72f8adccbcca`; 9.6–9.8, 9.10 and 9.2's routing half merged 25 Nov (#94–#98), rolling with `roll-1458b09870e0`; 9.2's catch-all, 9.3, 9.4 open.** |
| 10 The reader's paper | Proposed 25 Nov from a review of all 27 sites against news and design practice. 10.0's address fix is code (#101), and rolls with the next roll. 10.2, 10.3 and 10.5 wait on owner decisions. |

---

## Phase 9 — Exposure and upkeep (next)

It is numbered after the hub plan's Phase 8, the last numbered phase on
either plan. Every item below was confirmed open against the code on
24 Nov, not taken from older notes.

**9.1 — Strip embedded metadata from every stored image.** *Merged
24 Nov (#91), not rolled; `docs/briefs/roll-72f8adccbcca.md` rolls it
and runs the cleanup.* Until now every path stored image bytes exactly
as they arrived:

- the newsroom's uploads, including ads and profile photos;
- `POST /api/ingest-media`;
- image URLs fetched by `POST /api/ingest`;
- wire images cached from a pasted link.

So a phone photo published its GPS position, its camera and the time it
was taken. For a newsroom that is a source-protection failure.
`app/imagemeta.php` rewrites JPEG, PNG, WebP and GIF containers
losslessly, copying the compressed image data byte for byte. It keeps
only what a picture needs to look right: the colour profile, the JFIF
and Adobe markers, the animation and looping blocks, and a non-default
orientation, kept on its own. A file it cannot walk end to end is
refused, never stored unexamined.

C2PA Content Credentials are removed too. A manifest can embed the same
EXIF and creator assertions, and once anything else is removed it would
no longer validate. Disclosure that a graphic was generated belongs in
the visible credit line.

What remains after the roll is cleaning the uploads already on disk.
`tools/strip-image-metadata.php` runs a dry run first and `--apply`
second; it is lossless, atomic per file, keeps each file's owner and
mode, and is safe to re-run. The roll brief runs it from the new
release as `www-data`, which owns the shared uploads, right after the
roll. The backup sets taken before the cleanup still hold the
originals until they age out (7 daily + 4 weekly, about four weeks); the
owner decides whether to purge them sooner.

*Acceptance:* a GPS-tagged photo sent through the admin upload and
through `/api/ingest-media` is stored with no location, camera or time
(PHP's own EXIF reader finds none), decodes to identical pixels, and
still displays upright. A truncated photo is refused on both paths. The
cleanup's dry run changes nothing, and a second `--apply` finds nothing.
All of this is in `tests/imagemeta.test.php` and
`tests/imagemeta-http.test.php`. The production cleanup's report is
filed.

**9.2 — Convert the 14 legacy nginx blocks, and add the catch-all.**
This is 1.2's remainder and 1.3, which was never built.

*The routing half is merged (25 Nov, #98) and runs on the next roll,
`docs/briefs/roll-1458b09870e0.md`.* `upgrade-papers.sh` now changes each hand-written
block it repoints: the `try_files $uri $uri/ =404` fallback of
`location /` becomes `router.php`, behind the same internal-only exact
location generated blocks use. The block's rewrite lines stay and go on
serving the routes they already serve. Every block also gets an unlogged
`/tips` location (9.10). Both edits are idempotent by marker and go in
beside the block's PHP handler, so certbot's port-80 redirect servers are
never touched. Both use the php-fpm socket the block already names, and
a block without one is left alone. They run inside the roll, under its
nginx test, its masthead and stylesheet guard and its rollback.

Tested on nginx 1.24 with php-fpm 8.3, on a block shaped like
`DEPLOY.md`'s and one from the template:
- before, the hand-written block answered 404 for `/about` and `/tips`;
- after, `/about` and `/tips` answer 200, unknown paths get the branded
  404, `/router.php` stays unreachable (404), `/app/` stays denied (403),
  and `/api/ingest` answers 401 through the router;
- neither `/tips` request reached the access log;
- a second pass changed nothing.

`tools/test-release-flow.sh` asserts both edits on a legacy-shaped
fixture block. Still to build: the catch-all and the both-family audit
below.

- Every block takes `tools/vps/vhost.template`'s shape and routes
  through `router.php`. Every API route added since September has needed
  a hand-written pass over those 14 blocks (the 7cb82fb roll carried
  another one).
- An explicit `default_server` on 80 and 443, both address families,
  answers unknown hostnames with `444` behind a self-signed certificate.
  Without one, nginx falls back to the first block bound to each socket.
  That is how Turtle Island served Brampton's certificate.
- The same pass audits every block for both-family `listen` pairs.
  No paper publishes an AAAA record (24 Nov), so readers reach every
  paper over IPv4 only. Publish AAAA records, with the VPS's own IPv6
  `2a02:4780:2d:6f65::1`, only after every block answers correctly on
  both families. Until then an IPv6 visitor could be served another
  site's certificate.

Two cautions. The box hosts foreign tenants (the Institute and Calgary
Dispatch), whose blocks must be left alone, and any tenant's broken
block fails the global `nginx -t`. The runbooks currently say "do not
add `default_server`"; the brief for this item replaces that rule
rather than breaking it quietly.

*Acceptance:* a route added to `router.php` alone serves on every paper.
`curl` against an unknown hostname gets 444 on both families, never a
sister paper's certificate. Every paper keeps its masthead and
stylesheet through the conversion; the upgrader's guard runs it.

**9.3 — Prune old release directories.** Every roll leaves full copies
under `/var/www/prairiepost-<sha12>-<label>`, and nothing removes them.
Eventually the disk fills and all 27 sites fail together. The pruner:

- keeps each group's live release and a small number of predecessors;
- never touches a directory any enabled nginx block or cron file
  references;
- never touches roots outside `/var/www/prairiepost-*`.

Since migrations are forward-only past steps 22 and 23
(`docs/MIGRATION-COMPAT.md`), a release older than the stored schema
cannot serve as a fallback anyway.

*Acceptance:* after a roll, at most N release directories remain per
group, the live ones intact, and a dry run lists exactly what it would
remove.

**9.4 — Move the SMTP password out of the database.** Today
`smtp_pass` is a `settings` row written from the Newsletter page and
read by `app/mailer.php`. The AI and Google keys already live in the
server-only config. The password moves there too, the admin field
becomes "set on the server", and a migration clears the stored rows.

*Acceptance:* no `smtp_pass` value exists in the database or in its
backups; mail still sends.

**9.5 — An editor's publish clears the image hold.** *Merged 24 Nov
(#90), not rolled; it rolls with the same brief.* The story editor
published without clearing
`awaiting_image` (the bulk status change already clears it). A story an
editor published by hand and later moved back to draft therefore
re-entered the image agent's queue, and `/api/ingest-publish` could
publish it again over the editor's decision. #90 clears the flag when an
editor saves a story as published or scheduled. Rows already in
production keep their flag until saved once after the roll; #90 carries
a read-only query that counts them.

*Acceptance:* a story published or scheduled from the editor never
appears in `GET /api/ingest-queue` afterwards, whatever its later
status. `tests/publish-flow.test.php` checks this in #90.

**9.6 — Live weather.** *Merged 25 Nov (#94), not rolled;
`docs/briefs/roll-1458b09870e0.md` rolls it and adds the cron line.* Until now a paper's weather was a line an editor
typed into Settings at launch, and nothing ever changed it. On 24 Nov
sixteen papers printed launch-day weather beside today's date; Brampton
read "Brampton, Ont. · 27°C · Humid, smog advisory" in late November.

Now the hub's `weather` job reads Environment and Climate Change
Canada's city pages every 30 minutes through the MSC GeoMet API
(`api.weather.gc.ca`: free, no key, English and French). A paper names
its city page in its `palette.json`, as
`"weather": {"city": "mb-38", "place": "Winnipeg"}`. Nineteen papers do,
covering 15 cities, and each city is fetched once however many papers
share it. Every template now follows four rules:

- A reading observed more than three hours ago is never shown; the
  widget disappears instead.
- Every rendering credits ECCC, as its licence asks, and links to the
  city's forecast.
- Conditions are only what the station reported. Where a station
  reports no sky, the strip shows the temperature alone and a forecast
  box shows the labelled outlook ("Tonight: Mainly cloudy"), never a
  forecast passed off as an observation.
- The observation time is on the city's own clock.

Settings shows the live reading where the typed field was. The eight
papers whose templates have no weather slot carry no block. The old
`weather_line` and `weather_today` rows stay in the database, unread.

Rolling it needs three server steps beyond the roll itself, all for the
next roll brief:

1. A line in `/etc/cron.d/civismedia`, which the upgrader repoints but
   never extends. It is the line `tools/vps/deploy-civis.sh` now writes:
   `7,37 * * * * www-data cd $REL && PP_SITE=civismedia php cron/run.php weather >> /var/log/civis/weather.log 2>&1`
2. A time-zone database that knows 2026's changes. British Columbia,
   Alberta and Manitoba stopped changing clocks this year and stay on
   UTC−7, −6 and −5 year-round (tzdata 2026b, 2026c and 2026e; ECCC
   prints PCT, ABT and MBT). On older data, every time printed for
   those papers is an hour early. The check prints the hour of
   24 Nov 22:00 UTC in Vancouver, Edmonton and Winnipeg, and must read
   `15 16 17`:
   `php -r 'foreach (["Vancouver","Edmonton","Winnipeg"] as $c) echo (new DateTime("2026-11-24 22:00 UTC"))->setTimezone(new DateTimeZone("America/$c"))->format("G "); echo "\n";'`
   If it doesn't, update the `tzdata` package and reload PHP-FPM.
3. Outbound HTTPS from the box to `api.weather.gc.ca`.

*Acceptance:* `tests/weather.test.php` passes. It covers all 19
palettes, the parser on real and hostile records, freshness, one fetch
per city, a failed city keeping its last reading, the wrapper's failure
record, and real front pages for 11 papers. In production, the first
run's log lists every city, each paper with a block shows a reading
within the hour, and the watch reports the job on time.

**9.7 — Retire the other launch-day widgets that never update.** *Merged
25 Nov (#96), not rolled. Code only: no schema step, no route, no server
step.* The weather was not the
only field typed in at launch and left. The 24 Nov audit found these on
live front pages:

- breaking-news banners still up from launch day on Brampton, Kelowna
  (pointing at a newsletter that doesn't exist), Mississauga
  ("Multi-vehicle crash on Highway 401") and Tri-Cities;
- events listings dated in August on Grande Prairie (13 and 15 Aug) and
  Edmonton;
- Edmonton's six traffic items, each linking to `#`;
- London's council tracker (a "26 Aug meeting" with invented votes) and
  its membership bar ("1,240 members", goal 2,000);
- Prairie Dispatch's markets box, with invented prices "last updated
  4:15 p.m." from `app/seed.php`'s demo settings.

What was built (`app/freshness.php`). Every such widget now has a clock,
and none of it edits a row: the launch-day values stay in the database,
unread, as the typed weather lines did.

- A breaking banner shows only until `breaking_until`. Settings sets it
  when a new banner is saved: six hours, or the time the editor picks. A
  banner with no expiry never shows, and no launch-day banner has one.
  Saving Settings for another reason never puts a stale banner back up.
- Traffic shows for 12 hours, and closing prices for 30, after an editor
  last changed them in Settings. Seeded and launch-day rows were never
  changed there, so they never show. Unchanged values don't restart the
  clock.
- An event shows through its own date. A row whose date names no month
  and day ("Saturday", or no date at all) never shows, since it could
  never expire. Settings now says to date every event.
- London's council tracker, open-files list, membership band and
  "Next at City Hall" agenda are gone from its templates. Only the launch
  pack ever filled them, with figures it called illustrative, and no
  screen could edit them. They come back, if London wants them, as
  features the newsroom edits and dates.
- The packs and the seed no longer carry any of it: no banners, events,
  traffic or prices, and none of London's panels.

Mississauga's "Multi-vehicle crash on Highway 401" is also a launch
story's headline. That story is launch editorial (debt item 1 in
`CLAUDE.md`), replaced with the paper's pack, not a widget.

*Acceptance:* `tests/freshness.test.php` passes. It loads production's
launch-day values into real front pages for six papers and Prairie
Dispatch and finds none of them, then gives each widget a clock and
finds it back. It also checks Settings' expiry and clock rules over
HTTP, and every rule is mutation-tested.

**9.8 — Print each paper's times on its own clock.** *Merged 25 Nov
(#97), not rolled. Code only; the roll brief checks the box's tzdata.* The network formats
every time in one configured zone, America/Edmonton per `DEPLOY.md` and
the Kermode runbook, so the story times on every paper are Alberta's.
Since 1 Nov that is an hour ahead of local time in British Columbia and
an hour behind in Manitoba and Ontario (two hours behind in Ontario's
summer). Kermode's runbook deferred this as "a follow-up decision".
Storage stays in the network zone and only the display converts, using a
zone per paper. It has the same time-zone database dependency as 9.6.

What was built. Each paper's `palette.json` names its zone (`"zone":
"America/Vancouver"`). `pp_date()` in `app/i18n.php` prints on that clock,
and every printed time on a public page goes through it: `fmt_date()`,
`time_label()` (whose "today" and "yesterday" are the paper's too),
`pp_clock()`, the French dates, the mastheads' datelines, and the
newsletter's date line and subject. Storage and parsing stay on the
network's clock, so no row changes. Three things stay on the network's
clock:
- The newsroom (`admin/_layout.php` defines `PP_NEWSROOM_CLOCK`). Its
  date inputs, such as a story's scheduled time, are read on that clock,
  and a form that printed one clock and read another would mis-schedule.
  Moving the newsroom too means reading its inputs on the paper's clock,
  a separate change.
- The newsletter's send hour and edition dates.
- Western Wire and Turtle Island, which cover the West and the
  territories and have no single home clock. They carry no zone.

*Acceptance:* in winter, a story published at 22:00 UTC prints
3:00 p.m. on Kermode, 4:00 p.m. on the Echo, and 5:00 p.m. on Portage
and Kitchener. `tests/clock.test.php` checks this through every shared
helper, with expected times computed through the same tz database. It
also checks that a paper's zone matches its weather city's province,
that the newsroom stays on the network's clock, and that a broken zone
falls back.

**9.9 — Two small reader-facing fixes.**

- *Fixed with 9.10.* Kitchener's "Email" share link put only the story's
  path in the message, because `url()` returns `/story/<slug>`, so the
  recipient got a link that went nowhere. It now carries the story's
  canonical URL, as every other paper's share link does.
- `/about` answers 404 on 11 papers and the hub. The page is in the
  code (`about.php`, routed in `router.php` and `.htaccess`); what is
  missing is the rewrite in the hand-written nginx blocks, which carry
  eleven routes and not this one. *Fixed by the upgrader (9.2) on the
  next roll*, which sends their unknown paths to `router.php`. Nothing links to it yet, but readers
  look for one, and it is where a paper names its owner and how to
  reach the newsroom.

*Acceptance:* the emailed link opens the story, and every paper and the
hub answer `/about` with a page.

**9.10 — The whiteboard and the tips form.** *Merged 25 Nov (#95), not
rolled; `docs/briefs/roll-1458b09870e0.md` rolls it (schema 26). The owner's order of
25 Nov: "Draw on the whiteboard from the Calgary Dispatch. Have the tips
form go to a new whiteboard on each site admin portal."*

Every paper's newsroom gets a private board (`admin/whiteboard.php`),
taken from Calgary Dispatch's: notes typed as a reader tip, lead, rumour
or fact, each with a status (unverified, checking, confirmed, debunked)
and an optional source link, filtered by both, editable, and turned into
a draft with one click. Editors and administrators open it; authors
don't. The nav shows how many reader tips are still unverified.

Readers reach it through `/tips`, a form on every paper that pins their
tip to that paper's board and no other. Every paper's Tips and Contact
links now point there instead of a mailto: 22 of the 24 tips addresses
were on domains with no mail server. What the form keeps is only what
the reader types: the tip, a link, and optionally a name and a way to
reach them. It stores no IP address and no device details, and writes
no audit row (an audit row records an IP). A signed timing token and a
honeypot stop naive bots, and a cap of 30 tips an hour and 200 a day per
paper bounds a flood without knowing who sends. Turning a tip into a
draft copies the tip and its link, never the tipster.

Schema step 26 adds the `whiteboard_notes` table and the
`tips_form_key` setting. Like every step since 21, it is forward only
(`docs/MIGRATION-COMPAT.md`).

Rolling it needs nothing beyond the roll itself, if the roll runs an
upgrader from `claude/legacy-front-controller` or later (9.2). That
upgrader routes `/tips` on the hand-written blocks and keeps it out of
every block's access log; otherwise the log would hold each sender's IP
beside the time of a tip the database stores without one. Only certbot's
plain-HTTP redirect servers, which the roll leaves alone, still log a
typed `http://…/tips` visit as a 301. No tip ever reaches them.

Not built: Calgary's agent lane, `/api/ingest/notes`. Letting Hermes pin
notes needs a new grant on the ingest surface, which widens only by the
owner's order.

*Acceptance:* `tests/whiteboard.test.php` passes (the token, the
honeypot, the cap, site scoping of every read and write, escaping, the
draft that leaves the tipster out, author refusal, CSRF and the audit
trail). In production, a tip sent through one paper's `/tips` appears on
that paper's board and no other, its row carries no IP, and no access
log line records the POST.

## Phase 10 — The reader's paper (proposed 25 Nov; nothing started)

On 25 Nov the owner asked for the network to be checked against good
practice in news and design, and for ways to improve how the sites
feel. This phase is the answer, in the order it should be done. Nothing
in it has an order yet. Items marked **decision** need the owner's call
before any code.

*How it was measured.* On 25 Nov a browser visited the front of all 27
live sites and one story on each, at 1280 px and on a 390 px phone.
Production was on `7cb82fb` or `72f8adc`, so nothing from #90–#98 is in
these numbers. On each page it:
- ran axe-core 4.10 against WCAG 2.1 A and AA;
- recorded transfer weight, image markup, head metadata and structured
  data, and the links the front offers;
- measured the story's body type as rendered.

It also fetched every `/about`, every sitemap and one feed. Timings went
through this session's proxy and are only indicative. Weights and markup
are exact.
The scripts and results are not in the repository. axe finds only part
of what a keyboard and screen-reader pass would, so 10.6 still needs one.

*What already works.* Each paper looks like itself. The brand packages
bought distinct mastheads, palettes and type, and it shows. Body type is
in range nearly everywhere:
- 17–19 px;
- line height 1.5–1.75;
- 53–81 characters a line (Portage, at 92, is the one too wide).

No front scrolls sideways on a phone, and layout shift stays under 0.1
on every front. Every paper's front links its corrections file. Every
page has a canonical URL and an Open Graph card, and every story has
`NewsArticle` data. What lets the sites down is the system around the
design: what the pictures are, whose names are on the stories, what
counts as local, and what a reader can learn about the paper.

**10.0 — Two fixes first.**

*Give thirteen papers their own address.* On thirteen papers,
`site_url()` returns `https://prairiedispatch.ca`: Bison, Brandon,
Burrard, Cariboo, Kitchener, London, Mississauga, Portage, Red River,
Rideau, Steeltown, Surrey and Toronto. It returns the configured
`site_url` unchanged, so the live tenant configuration gives these
tenants the flagship's address. As a result:
- every page's canonical link, `og:url`, share image and story data names
  prairiedispatch.ca;
- so does every URL in their sitemaps and feed items;
- the Prairie Dispatch has none of those stories, so a feed reader or a
  shared link lands on a 404 there;
- the share card falls back to the flagship's nameless prairie
  landscape.

Today noindex keeps search engines out. Once indexing is on, these
papers would point every search engine at 404s on another paper. So
this is fixed before any of the thirteen is indexed.

*Found the same day, and fixed in code (#101).* The thirteen are
exactly the papers launched config-edit-free:
- Kitchener, London and Mississauga, in the 3 Sep batch;
- every launch since: Surrey, Cariboo, Burrard, Rideau, the Manitoba
  four, Steeltown and Toronto.

Their runbooks say so ("There is no config-file step"). They resolve
their tenant from the `domains` table, but `site_url()` read only the
config, so the config's default, the flagship's address, reached them.
Migration 15's own note says a paper's canonical hostname lives in
`sites.domain`, yet nothing read it there. Only migration 11 ever
filled it, for nine sites.

`site_url()` now keeps the configured URL unless its host belongs to
another paper. Then it uses the paper's own canonical host: its
`sites.domain`, else the shortest hostname `domains` maps to it. That is
the rule `pp_post_public_url()` already used, so the URLs the agents'
publish lane returns were always right. A configured URL that names the
paper itself, or no paper, is kept, and an empty one still means the
request's host. So the other fourteen sites and every test fixture are
unchanged.

No config edit is needed and there is no schema step; it reaches
production with the next roll. A future launch gets its own address with
no config edit (compliance gate item 8). The roll's verification checks
every domain's canonical, sitemap and feed host. The upgrader's probe
should also learn to flag a domain whose canonical names another paper.

*Acceptance:* on all 27 domains, the front's canonical, a story's
canonical and `og:url`, every sitemap URL and every feed link name the
domain's own host, or its canonical alias.

*Roll what is merged.* Today's fronts still show launch-day widgets as
current:
- Kelowna's masthead reads "24°C · Clear" above a lead story about the
  season's first snow;
- the Prairie Dispatch shows 21° and launch-day closing prices for
  canola;
- London shows 18°C.

`docs/briefs/roll-1458b09870e0.md` puts ECCC's readings in the weather's
place (9.6), retires the stale widgets (9.7) and puts each paper on its
own clock (9.8). It also makes `/about` and `/tips` answer on every
block (9.2, 9.10); today `/about` is a 404 on 11 papers and the hub.
Judge the rest of this phase on the live sites only after the roll.

**10.1 — Pictures that aren't the headline again.** Of the 124
uploaded images on the 27 fronts, 99 are the image agent's
`agent-image-*` uploads. Twelve were sampled, one from each of twelve
papers, and all twelve were headline cards: the paper's masthead, a
desk label and the headline set in type. As a result:
- most fronts print each headline twice, once as text and once in the
  picture;
- thumbnails crop the card to slivers of unreadable type;
- the overlay heroes (Steeltown, Toronto, Portage) set the headline over
  the same headline in the picture, and the two collide.

The card duplicates what `/card/<slug>.png` already makes, in each
paper's colours, for sharing (once 10.0 gives each paper its own host).

- The image agent's guide (`docs/agent-workflow.md`) says a featured
  image is a photograph or an illustration with no words in it, with a
  credit. A headline card is never a featured image, and with no picture
  the agent sends none.
- A story with no picture runs without one. The newer papers' fronts
  already have a no-art treatment, because their inaugural editions use
  it. An older front that lacks one gets one, checked against the render
  baseline. Cards already published leave the fronts within days once no
  new ones arrive, so nothing stored needs rewriting.
- Responsive images. None of the 218 images on the fronts has a
  `srcset`, and 14 carry width and height. The agent's PNGs run 180–690
  KB, served whole to phones. Steeltown's front moves 3.2 MB of images
  and the Prairie Dispatch's 2.5 MB. The median front moves 539 KB of
  images and 767 KB in all. Make sized
  derivatives at upload, each written through `pp_image_clean()` like
  every other file under `/uploads`. `story_photo()` and the front
  templates then print `srcset`, `sizes`, `width` and `height`.

*Acceptance:* no front shows a picture carrying the headline beside
it. Every front image has width and height, and a phone fetches no image
more than twice its slot's width. The median front is under 500 KB and
none is over 1.5 MB.

**10.2 — Honest bylines, and a disclosure the agents' stories carry.**
**Decision:** the bylines and the disclosure's wording.
- On ten papers the agents' stories are signed "Automated report":
  Bison, Brandon, Burrard, Cariboo, Portage, Red River, Rideau,
  Steeltown, Surrey and Toronto. That is `ingest.php`'s fallback for a
  paper with no `automated_byline` setting.
- Every byline, "Automated report" and "Echo Newsroom" alike, is a
  `Person` in the story's structured data.
- The AI-assistance line cannot reach these stories even when it is
  switched on. It prints only on `origin = 'ai'` stories (the newsroom's
  AI draft tool), and every agent filing is `origin = 'hermes'`. It also
  prints only through the shared `article.php`. The 18 papers with their
  own article template print no disclosure at all.
- The standard line says "reviewed by an editor", which is not true of a
  story the image lane publishes with no editor's action.

What to build:
- Every paper gets an `automated_byline` in the pattern the owner picks.
- One shared partial prints the disclosure on `hermes` and `ai` stories
  in every article template, worded by the owner. Its wording is true
  for each way a story goes live: published by an editor, or by the
  image lane.
- A newsroom or automated byline is an `Organization` in the data. A
  `Person` is only a named author with an author page.

*Acceptance:* a test files an agent story on every paper and finds the
disclosure on its page (when the setting is on) and an `Organization`
author in its data. No paper falls back to the generic byline.

**10.3 — Retire the invented launch content.** **Decision:** when.
The older launch packs are demonstration editorial about real cities
(debt item 1 in `CLAUDE.md`). Turtle Island's pack calls its content
"illustrative, not reported". Fourteen packs sign it with about 80
people who do not exist: reporters, and in Pickering and Sudbury letter
writers. Nine of those papers still show at least one such name on
their front today, Bleuet Blanc five and Turtle Island four. Noindex
keeps these pages out of search, but anyone can read them, and a reader
who looks one of those reporters up finds no one.

Before any paper's indexing is switched on, and before Western Wire,
Bleuet Blanc and Turtle Island launch:
- an editor moves each pack's demonstration stories to draft with the
  Stories list's bulk change, never by SQL;
- each of those papers takes a true-by-construction inaugural edition,
  as the newer papers launched with, so no front goes empty.

*Acceptance:* no public page carries a byline from the packs' invented
names (the test holds the list). Every older paper's front opens on its
inaugural edition or on newer news.

**10.4 — Local leads local.** On 25 Nov the same story led Toronto,
Steeltown and London: a design contract for Highway 17 between Arnprior
and Renfrew, in the Ottawa Valley. Toronto labelled it City; Steeltown
and London labelled it Local News. Steeltown's next three, all labelled Local News,
are datelined Sault Ste. Marie, Toronto and Mississauga. A city paper
whose "local" is the whole province reads like a wire, and a reader
notices that before anything about the design.
- Each paper's `palette.json` names its coverage area: the places it
  calls local, beside its weather city and its zone.
- A front's lead slot and its Local desk label take only stories
  datelined inside that area. Provincial stories go to a province desk
  on that paper.
- The wire map (`tools/wire-map.php`) exports the same list, so Hermes
  files to the right desk in the first place. The ingest contract does
  not change.

*Acceptance:* a test files a story datelined outside a paper's area,
and it never takes that paper's lead slot or its Local label.

**10.5 — What a reader can learn about the paper.** **Decision:** the
ownership, funding and privacy text.
- Only Sudbury and Turtle Island link an About page from their fronts.
- Where `/about` answers, only Sudbury's has copy of its own. The
  others print their one-line description, some of which make claims
  the page should either support or drop ("reader-supported", "the
  paper of record", "a regional daily of record").
- No paper has a privacy page, though eight load analytics, every
  newsletter form collects addresses, and the tips form collects names
  and contact details.
- No paper says who owns it, how it is paid for, or how its stories are
  made.

These are what the Trust Project's indicators ask for, and what readers
and platforms look for first.

What to write:
- An About for every paper, in its settings (`about_heading`,
  `about_standfirst`, `about_body`), linked from every footer. It says
  who owns and runs the paper, how it is funded, how its stories are
  made (the agents included), and how to send a tip or a correction.
- One network standards page (ethics, sourcing, corrections, automation,
  unnamed sources), served on every domain and linked from every footer.
- A privacy page, served on every domain. It names what is collected
  (newsletter addresses, tips, analytics) and why, and puts consent
  before analytics wherever the owner's counsel says the law requires
  it. Bleuet Blanc, a Quebec paper, is where that question comes first.
  No paper's indexing switches on without one; that is already a policy
  decision below.
- The `NewsMediaOrganization` data names those pages, with
  `publishingPrinciples`, `correctionsPolicy`, `ethicsPolicy`,
  `ownershipFundingInfo`, `actionableFeedbackPolicy` and `masthead`.

*Acceptance:* every front, the hub's included, links About, Standards,
Privacy and Corrections. Every `/about` carries the paper's own text.

**10.6 — Accessibility to WCAG 2.1 AA, held by CI.** axe finds at
least one failure on 21 of the 27 sites:
- Text contrast below 4.5:1 on 18 sites, 220 elements in all, mostly
  small meta lines and accent-coloured desk labels:
  - London's grey-greens, 3.2–4.2:1;
  - Grande Prairie's `#9a9a9a`, 2.8:1;
  - Steeltown's gold label, 3.6:1;
  - Kelowna's teal, 3.7:1;
  - Turtle Island's grey, 3.8:1;
  - Kelowna's large gold numerals, 2.1:1 (large text needs 3:1).
- 45 links with no accessible name on 9 sites. Mostly a story's picture
  is a second link to the story, with `alt=""` and no text, so a screen
  reader announces only "link".
- On 4 sites, links in body copy differ from the text around them only
  by colour.

What to change:
- Darken each paper's muted and accent text colours until they pass. The
  accent stays for rules, chips and backgrounds.
- Make each card one link, or take the duplicate image link out of the
  tab order and the accessibility tree.
- Underline links in body copy.
- Hold the line in CI. It already renders every paper
  (`tools/baseline.sh`), so run axe over those renders and fail the build
  on a new violation, as `tests/admin-brand.test.php` already checks
  every newsroom palette.

*Acceptance:* axe reports no A or AA violation, in CI, on every paper's
front, a story, a desk page and `/tips`. A manual keyboard and
screen-reader pass of one paper per template is filed.

**10.7 — Dates a reader can trust.** A story's header prints
`time_label()`:
- a bare time on the day it runs ("2:32 p.m.");
- "Yesterday" the next day;
- after that a month and day with no year, forever, so a story from
  11 Aug 2026 will still read "Aug 11" a year later.

No zone is printed, although 9.8 makes the clock the paper's. No story
page marks its date up as `<time datetime>`, and none says when it was
updated.
- On the story page, print the full date with the year, the time and
  the zone.
- Add "Updated" with its time when the text changed after publication
  (`post_revisions` records it).
- Mark both up as `<time datetime>` with the offset.
- Fronts may keep the short label, adding the year to a story from
  another year.

*Acceptance:* a test finds the year on a story from a previous year, a
`<time datetime>` on every story page whose instant equals
`published_at`, and "Updated" on a story edited after publication.

**10.8 — What browsers and platforms read.**
- *Icons.* Each page has an SVG favicon and nothing else. No site has an
  `apple-touch-icon`, a PNG fallback, a `theme-color` or a manifest.
  Generate them per paper from its mark.
- *The network's art standing in for a paper's.* `site_asset()` falls
  back to the network's file when a paper has none of its own, and two
  such fallbacks are public:
  - Kelowna and Kermode ship no `mark.svg`, so their story data names
    the Prairie Dispatch's mark as their publisher logo;
  - fourteen papers ship no `og-default.png`, so sharing their front
    page, a desk or `/about` shows the flagship's nameless prairie
    landscape. They are Bison, Bleuet Blanc, Brandon, Burrard, Cariboo,
    Kitchener, London, Mississauga, Portage, Red River, Rideau,
    Steeltown, Surrey and Toronto.

  Each paper gets its own. `tools/make-paper.php` scaffolds both, and
  the compliance gate checks them.
- *Story metadata.* Add `article:published_time`, `modified_time` and
  `section`, and `og:image:alt`. Bleuet Blanc gets `og:locale` `fr_CA`
  and `inLanguage` `fr`.
- *Newsletter promises.* No edition goes out before the pages are live
  (decided 25 Nov), but the sign-up links already promise one: "Get the
  7am digest", and London's "Become a member", which opens the same
  form. Until the first edition is sent, say that it starts soon.
- *Portage's measure.* Its body copy runs 92 characters a line. Cap it
  near 70.

*Acceptance:* every paper serves its icon set, its own mark and its own
default share card. A test fails any paper that would fall back to the
network's.

## Waiting on the owner (no code needed)

1. **Agent key grants for the editing API.** It is live, but no key
   carries the read, edit-drafts or update-published grants until you
   name which keys get them. Granting one is a revoke and re-mint.
2. **Surrey's test residue.** Delete draft #315 and story #316 in
   Surrey's admin (never by SQL); #316 is publicly visible. Two test PNGs
   sit under `/uploads/2026/09/`.
3. **An off-site destination for backups.** They run nightly and are
   verified, but they sit on the same server as everything else. The
   hook is `/etc/civis-backup-offsite`.
4. **The ops alert email,** in the hub's Settings → Security. Until it
   is set, the five-minute watch can only show problems on the
   dashboard, not email them.
5. **The GitHub default branch** (0.3): repoint it to
   `claude/master-dashboard-control-room-nr3mp4`.

Done: **Pacific Post's DNS** (found and fixed 24 Nov). The bare domain
carried two stray Hostinger records beside the VPS: an A record that
failed about half of visits with a certificate error, and an AAAA
record that wasn't the VPS's IPv6 either. The owner deleted both in
hPanel. Two public resolvers now return only the VPS for the bare
domain and `www`, and visits to the bare domain succeeded 12 times out
of 12.

## Policy decisions before more code

- **AI-assistance disclosure.** The `ai_disclosure` setting is blank
  (off) by default and no launch pack sets it. Decide whether readers
  see a note on stories drafted with the desk's help. As built, it could
  not reach the agents' own stories even if switched on (10.2), so the
  decision comes with the code that fixes that.
- **A privacy page and reader consent.** Eight papers load analytics
  as of 24 Nov: Kermode's Google Analytics tag, and Google Tag Manager
  containers on Burrard, Edmonton, Grande Prairie, Portage, Prairie
  Dispatch, Surrey and Toronto. No paper has a privacy page yet. Settle
  this before more papers add tags, and before any paper's indexing is
  switched on.
- **A tamper-evident audit log.** Today's audit rows are ordinary
  database rows, which anyone with write access to the database can
  change. Decide whether they should be hash-chained or shipped off the
  box.

Decided by the owner on 25 Nov, from the 24 Nov audit:

- **Tips go to the whiteboard.** Of the 24 papers that published a tips
  address, only two (londonlookout.com and westernwire.ca) had a mail
  server for it. Instead of mailboxes, every paper's tips now go
  through its `/tips` form to its whiteboard (9.10).
- **Newsletters start once the pages are live; not yet.** No edition
  has been sent, and none goes out before then. The sign-up forms are on
  the pages today and accept addresses.
- **Western Wire, Bleuet Blanc and Turtle Island are the next tranche to
  go live.** They are not quiet papers to retire: their newest stories
  (11 Aug, 24 Aug, 24 Aug) are their launch content, and their own
  launch comes next.

---

## The hardening plan as written (papers 14 and 15 shipped against it)

Each phase ends with an acceptance test that either passes or the phase
is not done. The ordering rule: safety nets before surgery, launch
machinery before launches, template surgery under baseline protection,
and the ingest pipeline before any Hermes agent holds a token.

---

## Phase 0 — Safety nets (one session)

> **Status, 24 Nov:** 0.1, 0.2 and 0.4 done; 0.3 is the owner's switch
> (above). CI runs on every PR to the release branch:
>
> - lint;
> - the generated schema check;
> - brand palettes validate;
> - the suite on SQLite and on PostgreSQL;
> - the release-flow fixture;
> - `seed-all.sh` with zero skipped lines;
> - the render baseline against the base branch (`tools/render-gate.sh`).
>
> 0.4 shipped as the per-site `indexing_enabled` setting, default off.
> Until an admin enables it, every page carries a noindex meta, and
> feeds, sitemaps and social cards carry an `X-Robots-Tag` header. None
> had been enabled at the last check. `robots.txt` deliberately stays
> `Allow`, because a crawler that is disallowed never sees the noindex.

The render-diff harness has caught three shipped-invisible defects
(stray newline on twelve papers, wrong-layer EN DIRECT check, Pickering
phantom font). It lives in `/tmp` and runs when someone remembers. That
ends here.

**0.1 — Commit the harness.** `tools/baseline.sh` renders every public
page type on every paper against a database seeded from all launch
packs, twice for the base tree, and reports only real differences
(clock ticks and tag-tie ordering are noise, not signal). A companion
`tools/seed-all.sh` builds the N-paper test database from scratch in
launch order.

**0.2 — CI on every PR to the release branch.** GitHub Actions:
`php -l` over every PHP file; every `assets/sites/*/palette.json`
parses; `tools/seed-all.sh` completes with **zero `skipped` lines**
(this catches network-wide slug collisions and desk dependencies at PR
time, where they are a red X, instead of at deploy time, where they are
a hard stop on a live server); `tools/baseline.sh` shows zero real
differences against the base branch unless the PR declares it changes
rendered output.

**0.3 — Repoint the GitHub default branch** to
`claude/master-dashboard-control-room-nr3mp4`. The trunk that is
visible must be the trunk that ships. Then delete the stale merged
feature branches.

**0.4 — Stop indexing invented content.** Thirteen mastheads of
plausible fake news about real cities, indexable, is the largest
standing risk on this list. Add a `noindex` robots meta emitted by the
shared chrome whenever the new setting `indexing_enabled` is not `'1'`,
and flip `robots.txt` to match. The flag is per-site, so each paper
starts indexing the day its newsroom replaces the demonstration pack —
one setting, no deploy. **Owner decision recorded here:** default is
noindex-until-real-editorial.

*Acceptance:* a PR that introduces a deliberate slug collision fails
CI; a PR that changes nothing renders byte-identical in CI; every live
paper serves the noindex meta until its flag is flipped.

## Phase 1 — Launch machinery (the step that has caused every stop)

> **Status, 24 Nov:** 1.1, 1.4 and 1.5 done.
>
> - **1.1:** the `domains` table resolves the tenant ahead of the
>   config, and every launch since August has been config-edit-free.
>   Whether the live config's own host arms have been retired has not
>   been confirmed on the server.
> - **1.4:** `upgrade-papers.sh` records each domain's status, title
>   *and* front-page stylesheet set before a roll, and rolls the group
>   back if any of them changes. That is the template-loss check.
> - **1.2:** the template is committed and every block created since is
>   generated from it (13 of 27). The 14 hand-written blocks were never
>   converted.
> - **1.3:** the catch-all was never built.
>
> 1.2's remainder and 1.3 are Phase 9.2.
>
> *(Aug 24 status, for the record: 1.1 resolved all 22 hostnames from
> the database; 1.2's template was committed; 1.3 was recorded as done.
> That 1.3 entry was wrong. No catch-all exists in the template, the
> deploy scripts or any runbook, and the runbooks forbid adding
> `default_server`.)*

Three of the last four deployments hard-stopped at least once, and
every stop but one traces to the same two designs: a hand-edited
guarded config file, and routes that live in three places.

**1.1 — Tenant mapping moves into the database.** New `domains` table:
`hostname (unique) → site slug`, plus a per-site `canonical_url`
setting. Bootstrap resolves the tenant from the request host via the
table, falling back to the existing `app/config.site.php` match arms so
the cutover is riskless; launch packs gain a `domains` entry and the
seeder writes it. After all thirteen papers' rows exist and serve, the
match arms are retired and the config file holds credentials only —
which makes the action-guarded step of every future launch *disappear*.
Launching a paper becomes: DNS, nginx, cert, seed.

**1.2 — Nginx becomes a front controller.** Every paper block reduces
to the same shape: TLS lines, root at the release, deny locations, and
`try_files $uri /router.php$is_args$args`. Routes then live in
`router.php` alone — the "three homes" defect class (`/region/` 404ing
only in production) cannot recur. A canonical block template is
committed at `tools/vps/vhost.template` and new blocks are generated
from it, not copied from a sibling by hand.

**1.3 — Explicit `default_server` catch-all** on 80 and 443, both
address families, returning `444` with a self-signed certificate. An
unknown hostname or a missing `listen` line then fails loudly and
obviously instead of serving Brampton's certificate to half the
internet. This retires the Turtle Island failure class permanently.
All thirteen existing blocks are audited for both-family listen pairs
in the same pass.

**1.4 — `upgrade-papers.sh` learns to see template loss.** Its
rollback guard gains one assertion per bespoke paper: the served front
page contains that paper's stylesheet name. The `<title>` check alone
passes on a paper whose entire template tree is missing, because titles
come from the database.

**1.5 — Slug scoping decision.** `posts.slug` stays globally unique
for now (changing the constraint under 13 live papers is riskier than
the disease), but the seeder stops failing silently: a skipped story
becomes a non-zero exit and a loud line, and Phase 3's ingest API
allocates slugs server-side with automatic suffixing. Revisit a
per-site constraint only if the newsroom-facing admin ever hits it.

*Acceptance:* a test hostname added only via seeder resolves to its
paper with zero config-file edits; `curl` against an unknown hostname
gets 444, not a sister paper's certificate; a release built with a
bespoke paper's CSS deleted rolls back automatically.

## Phase 2 — Template registry (the deferred refactor, done safely)

> **Status, Aug 24 (final):** 2.1, 2.2 and 2.4 done — all twelve papers'
> chrome in partials, ui.php name-free, the six phantom-font stacks
> renamed on faulty sandbox evidence — CORRECTED Aug 25: all families
> now vendored and declared in fonts.css, six stacks restored to Source
> Serif 4, zero font-CDN loads network-wide — and the operational
> close-out with them: `tools/make-paper.php` scaffolds a complete
> files-only paper (palette, pack skeleton with domains, css, three
> views, two chrome partials; refuses to overwrite), so builds 14 and 15
> start in the compliance-gate shape by construction.
> **2.3 (accessibility levelling) is delayed by owner decision, Aug 24**
> — moved to the standing backlog as a declared-diff pass with
> screenshot review. Phase 2 is otherwise closed.

`ui.php` carries 30+ per-paper branches across three dispatch chains;
the Torch and the Standard conflicted in all four shared files; a blank
line in the footer chain nearly shipped a stray newline to twelve live
papers. The two failed extraction attempts taught the method: **never
again as one big-bang parse — one paper per PR, CI baseline green after
each.**

**2.1 — Convention dispatch.** `index.php`, `article.php`,
`section.php` require `app/views/front-{template}.php` (etc.) when the
file exists — the if-chains are deleted once every paper's files
follow the convention (they already do; only the chains remain).

**2.2 — Chrome partials.** Each paper's masthead and footer move,
verbatim, from `ui.php` into `app/views/chrome/{template}-head.php` /
`-foot.php`, one paper per PR, with the shared default as the
fallthrough. After the last one, `ui.php` contains no paper names.
End state: **a new paper adds files and touches nothing shared** —
palette, CSS, three templates, two chrome partials, a launch pack.

**2.3 — Accessibility levelling, in the same pass.** As each paper's
chrome moves: skip link, visible focus states, `aria-current` on
active nav, one contrast check of its palette tokens. The older papers
predate these; moving their chrome is the moment to level them.

**2.4 — The fonts decision, decided.** Six papers name Source Serif 4
without declaring it and have always rendered Georgia — which their
pages were approved with. **Recommendation: declare nothing; rename
the six stacks to lead with Georgia honestly.** Changing six live
papers' typography in one release to match a name nobody has ever seen
rendered is the wrong direction. Either way the stack must stop lying;
owner may override toward declaring the face instead.

*Acceptance:* zero real baseline differences after every single PR in
the phase; `grep -c "template') ===" app/views/ui.php` returns 0 at
the end; a scaffold paper (`tools/make-brand.py` output) reaches a
rendering front page with no shared-file edits.

## Phase 3 — The Hermes ingest pipeline (before any agent files a story)

> **Status, Aug 24:** built and verified locally — endpoint, scoped
> revocable tokens (tools/make-agent.php), server-side slugs, dedup by
> external_id, draft gate with the wire-desk exception, provenance box
> rendered on all six article templates, French labels, rate limit.
> The contract for agent authors is docs/HERMES-INGEST.md. Finalized
> repo-side: the admin posts list badges agent-filed stories, and the
> Bleuet Blanc pack ships Hermes-ready (wire_desks 'le-fil', the
> automated byline in French). **Deployed Aug 24:** release
> 3c2335fdbf53 on schema 16, the endpoint verified fail-closed
> network-wide from on-box and off-box, a live draft filing passing
> creation/invisibility/idempotency, and token hermes-quebec issued to
> /root/hermes-tokens/ (never printed). Phase 3 is closed; the ingest
> path opens for real the day the first Hermes agent receives the token.
>
> **Since then (24 Nov):** ingest v2 (27 Sep) put key minting on the
> hub's API-keys page and added `/api/ingest-media`. The publish-on-image
> lane followed on 28 Sep. Story lookup (`/api/ingest/lookup`) went live
> 21 Nov. The read lane (`GET /api/ingest/stories`) and the two edit lanes
> (`PATCH /api/ingest/drafts/{ref}` and `POST /api/ingest/update`) went
> live 22–23 Nov, each behind its own grant. The current contract is
> `docs/api-ingest.md`; `docs/HERMES-INGEST.md` is the original.

Agents tracking databases and public sources will feed the papers. The
write path they use decides whether the network stays trustworthy. No
Hermes agent receives a token until this phase is done.

**3.1 — Ingest API.** `POST /api/ingest`: per-agent bearer tokens
(hashed at rest), scoped to specific sites and desks, revocable
individually — revocation is the kill switch. Payload: title, desk,
lede, body, tags, dateline, `sources[]` (URL + retrieved-at), optional
image reference. Rate-limited per token.

**3.2 — Server-side slug allocation and dedup.** The API generates the
slug and auto-suffixes on collision — the silent-skip failure mode
must not exist on this path. Dedup at ingest by source URL and content
hash: three agents watching the same court filing file it once.

**3.3 — Everything lands as a draft.** Agent-filed stories enter
`status = pending` in a review queue in the admin — list, diff against
any prior version, approve / reject. A human click is the publish gate
for anything wearing a byline. The single exception: wire/`le fil`
style desks may auto-publish, clearly labelled as live feeds.

**3.4 — Provenance is first-class and visible.** Which agent, which
sources, retrieved when — stored per story and rendered as a sources
box on the article. **Byline convention, decided now:** automated
stories carry an explicit automated-report treatment, not a
human-sounding name and not the house "La rédaction". This is both the
ethical disclosure and the debugging trail.

*Acceptance:* an agent token scoped to paper A cannot write to paper
B; a duplicate filing dedupes; a revoked token fails closed; a
published agent story shows its sources box and automated byline.

## The compliance gate for every new paper

Written for papers 14 and 15; every paper since has been built against
it, and the next one is too. A new paper build is accepted only when
all of the following hold:

1. **Files only.** The build adds `assets/sites/<slug>/` (palette,
   launch pack, art), `assets/css/<template>.css`, three view
   templates, two chrome partials. `git diff` against shared files is
   empty.
2. **Every font named is a font declared** — its stylesheet carries the
   `@font-face` for every family in its stacks, and CDP
   (`CSS.getPlatformFontsForNode`) confirms the face actually paints.
3. **The launch pack carries its own world:** every desk its stories
   use, explicit collision-proof slugs, its `domains` entry, and
   `indexing_enabled` unset (noindex) until real editorial.
4. **CI green** on the PR: lint, palettes, seed-all with zero skips,
   baseline with zero real differences on the existing papers.
5. **Runbook verify items name served bytes,** each traced to the step
   and the layer that produces it (CLAUDE.md lessons 1 and 10), and the
   nginx block comes from `vhost.template`.
6. **Launch is config-edit-free:** DNS, generated nginx block, cert,
   seed. If the deployment brief contains a config-file edit step, the
   phase-1 work isn't finished — stop and finish it first.
7. **The newsroom wears the paper** (since 23 Nov): the palette's
   `admin` block is chosen from the rendered masthead, and
   `tests/admin-brand.test.php` passes for it.
8. **The paper prints its own address** (since 25 Nov): the pack's
   `domains` lists the canonical host first, and the launch runbook's
   verification checks that the front's canonical link and a sitemap URL
   name that host. A paper launched config-edit-free has no arm in the
   server's tenant config, so before the 25 Nov fix it printed the
   flagship's address, and no masthead check could see it (PLAN.md 10.0).

## Standing backlog (not gating, tracked)

- Newsletter infrastructure per paper: the owner's mail setup
  (mailboxes, SMTP identity, SPF, DKIM, a test send). No paper has its
  newsletter enabled.
- `NewsArticle` JSON-LD and per-story OG images once indexing turns on.
- Desk pagination and related stories before agent volume arrives.
- Analytics beyond the `views` column.
- Replacing the older demonstration packs with real editorial, paper
  by paper. The newer papers' inaugural editions are about the papers
  themselves and true by construction. The older packs still carry
  invented editorial about real cities, kept out of search only by
  noindex.
- Accessibility levelling (2.3), as a declared-diff pass with
  screenshot review.
- Slug renames with redirects. No redirect infrastructure exists, so
  the editing API never changes a URL.
