# Brief — roll the network to 388f1bd03ffe: icons, share cards, and everything still unreleased

**For: the VPS agent** (root SSH; server work only). Written 26 Nov 2026.

**This brief supersedes `docs/briefs/roll-acb508aa4fd0.md`.** Run this pin,
not `acb508aa4fd016dafeb0c12dbda25dc7de793bed`. That older pin is an
ancestor of this one, and its roll has not run: on 26 Nov every paper
still 404'd `/tips` and thirteen papers still printed
`prairiedispatch.ca`. Do that brief's steps with the pin below
substituted, then the icon checks at the end. Do not run both pins.

The roll did not run from the environment that merged the code. The box
answers on SSH and refuses the connection: no key is held here.

## The pin

```
388f1bd03ffe6483a61eea099029af5c26a3e41b
```

That is the merge of #105 onto the release branch
`claude/master-dashboard-control-room-nr3mp4`, after #104. It is the
commit to roll. A later commit that only adds this brief is
documentation; do not wait for it, and do not roll a different SHA.

#104 gave every paper its own icon set (`assets/sites/<slug>/icons/`,
including a 48px image inside `favicon.ico`), its own `og-default.png`,
`twitter:image`, and `theme-color`. #105, in this merge, cuts Red
River's meander out of the path so the SVG tab icon is not a mask, and
names the four Manitoba papers' browser colour in `palette.json`
`theme`: Portage `#041E42`, Bison `#8B0000`, Brandon `#E6BF2E`, Red
River `#7E3517`. `/favicon.ico`, `/apple-touch-icon.png`, `/icon-32.png`
and `/site.webmanifest` are routes (`icon.php`), one file per host.
Nothing in this roll changes `robots` or turns indexing on.

The upgrader file is unchanged since `acb508aa`. Its sha256 at this pin
is still

```
d28307e3c31c185362c773d6329d3f640f39fad2d6dc69fe3999b4387203a96b
```

## The command

Fetch the upgrader **at the pin** (do not run a copy already on the
box), then run it the way DEPLOY.md says: detached, pinned, and reading
the log rather than the terminal. `$W` is the empty work directory from
the superseded brief.

```bash
PIN=388f1bd03ffe6483a61eea099029af5c26a3e41b
curl -fsSL -o "$W/upgrade-papers.sh" \
  "https://raw.githubusercontent.com/FTFNAnalytics/prairiepost/$PIN/tools/vps/upgrade-papers.sh"
sha256sum "$W/upgrade-papers.sh"
# must be d28307e3c31c185362c773d6329d3f640f39fad2d6dc69fe3999b4387203a96b
# A mismatch: STOP. Do not roll.

systemd-run --unit=pp-roll-$(date +%s) --wait --pipe \
  env PP_UPGRADE_SHA=$PIN bash "$W/upgrade-papers.sh"
```

The log is `/var/log/prairiepost-upgrade/roll-<stamp>.log`. A dropped
session does not stop `systemd-run`. If the roll stops after the
migration, fix what stopped it and run this same pin again: it finishes
rather than starting over. Do not abandon it, and do not point the
vhosts back by hand.

## Do the superseded brief, with this pin

Carry out `docs/briefs/roll-acb508aa4fd0.md` from Step A through Step D,
with every occurrence of

```
acb508aa4fd016dafeb0c12dbda25dc7de793bed
```

and of the short prefix `acb508aa4fd0` in a release-directory name,
replaced by this pin and by `388f1bd03ffe`. In particular:

- A5's ancestor check uses this pin. Expect `ahead behind_by=0` or
  `identical behind_by=0` against
  `claude/master-dashboard-control-room-nr3mp4`. The old "until #102 is
  merged" stop does not apply.
- A5's upgrader hash is the one above. The file did not change.
- Step B is the command in this brief, not the old pin.
- D3's release directory is
  `/var/www/prairiepost-388f1bd03ffe-<label>`.
- Schema still goes to 26 if production is on 25. Forward only once
  that step commits.
- C1 (weather cron), C2 (its first run) and C3 (image cleanup) still
  apply if they have not been done. Production had not done them on
  26 Nov.
- No config edit. No ingest key. No manual SQL. Do not enable indexing.
  `robots` stays `noindex`.

## Icons, after the roll (two-failure hard stop)

These are in addition to D4, which already requires every domain's
canonical, `og:url`, sitemap and feed to name itself. Probe through the
box, as that brief does (`curl --resolve "$host:443:127.0.0.1"`).

For each of `portagepress.ca`, `bisonbulletin.ca`,
`brandonbulletin.com`, `redriverregister.ca`, and for
`kelownacurrent.ca` (the paper that must not regress: it already named
itself):

| Check | Pass |
| --- | --- |
| Front `<link rel="canonical">` | `https://` that host `/` |
| `og:url` | the same |
| `og:image` | `https://<host>/assets/sites/<slug>/og-default.png` and that URL is `200` `image/png` |
| `twitter:image` | the same URL as `og:image` |
| `theme-color` | Portage `#041E42`, Bison `#8B0000`, Brandon `#E6BF2E`, Red River `#7E3517`, Kelowna whatever its palette ink is, and present |
| `robots` | still `noindex` |
| `GET /favicon.ico` | `200`, `Content-Type` `image/x-icon`, body starts `00 00 01 00` |
| `GET /apple-touch-icon.png` | `200`, `image/png` |
| `GET /icon-32.png` | `200`, `image/png` |
| sitemap `<loc>` and the feed's channel `<link>` | every one on that host |

Slugs: `portage-press`, `bison-bulletin`, `brandon-bulletin`,
`red-river-register`, `kelowna-current`.

Kelowna's canonical, `og:url`, sitemap and feed must still be
`kelownacurrent.ca`, not `prairiedispatch.ca` and not a Manitoba host.
A paper that named itself before and does not after is a failure of
this roll.

Red River's `/assets/sites/red-river-register/favicon.svg` must be
`200` and must not contain `<mask`.

Report the five hosts as a table: canonical host, og:image host, icon
status codes, theme-color, robots. And say whether the roll ran, the
log path, and `migrate.php --status` verbatim.
