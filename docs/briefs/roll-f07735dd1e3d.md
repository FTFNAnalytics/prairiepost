# Brief — roll the network to f07735dd1e3d: icon URLs that the static-file regex was 404ing

**For: the VPS agent** (root SSH; server work only). Written 26 Nov 2026.

Production is on `117c158a545b`, schema 26. That roll succeeded. `/favicon.ico`
and `/apple-touch-icon.png` still 404 on Edmonton Echo, Grande Prairie
Gazette, Kermode Chronicle, The Pacific Post and Prairie Dispatch. Those
blocks have `location ~* \.(?:svg|png|...|ico)$`, which nginx chooses
before `location /`, so the name is served from disk and a missing file
is a static 404. It never reaches `router.php`.

This pin adds an exact `location =` for each icon and manifest URL the
app serves, passed straight to `router.php`. An exact match is chosen
before any regex. `/assets` and `/uploads` are not in the list, so a
real file there still uses the static location. A second run finds the
marker `pp-icon-routes` and does not edit the block again.

Do not enable indexing and do not change `robots`. This pin also
contains the Settings button from #103, already merged on the release
branch. That button does not turn a paper on by itself. Leave every
`indexing_enabled` as it is.

## The pin

```
f07735dd1e3db5cfcf4a36cf046f05f30d267baa
```

The upgrader at this pin, and only this hash:

```
06e1c2680b940d65206393746637086f5a459d0abd980372d1bbf070f42a6633
```

A mismatch: STOP. The release directory prefix is `f07735dd1e3d`.
Schema stays 26. No config edit. No ingest key. No manual SQL.
Do not set `PP_UPGRADE_ABANDON` unless an enabled vhost still names an
unfinished release. `117c158a545b` completed; it is not one.

## The command

`$W` is an empty work directory.

```bash
PIN=f07735dd1e3db5cfcf4a36cf046f05f30d267baa
curl -fsSL -o "$W/upgrade-papers.sh" \
  "https://raw.githubusercontent.com/FTFNAnalytics/prairiepost/$PIN/tools/vps/upgrade-papers.sh"
sha256sum "$W/upgrade-papers.sh"
# must be 06e1c2680b940d65206393746637086f5a459d0abd980372d1bbf070f42a6633

systemd-run --unit=pp-roll-$(date +%s) --wait --pipe \
  env PP_UPGRADE_SHA=$PIN bash "$W/upgrade-papers.sh"
```

The log is `/var/log/prairiepost-upgrade/roll-<stamp>.log`. Expect the
five blocks, and every other block that has a top-level PHP handler, to
print `icon and manifest URLs beat the static-file regex` once. A
re-run of this same pin must not print that line again.

`nginx -t` must succeed. Inside `location ^~ /admin/` the fallback is
still `try_files $uri $uri/ =404`. The static-file regex line is still
present.

## Verification

Probe through the box (`curl --resolve "$host:443:127.0.0.1"`). For
every host below, each of these is `200`:

- `/favicon.ico` with `Content-Type` `image/x-icon`
- `/favicon.svg` with `Content-Type` `image/svg+xml`
- `/apple-touch-icon.png` and `/apple-touch-icon-precomposed.png` with `image/png`
- `/icon-32.png`, `/icon-192.png`, `/icon-512.png` with `image/png`
- `/site.webmanifest` with a JSON content type

And one real static file is still `200`, not the icon route: the
paper's `/assets/sites/<slug>/favicon.svg`, or `/assets/css/prairie.css`
on the flagship. A 404 there means the exact locations caught a path
they must not.

The front page's `robots` meta is still `noindex` on a paper whose
indexing switch is off. Do not turn any paper on.

| Host | Slug |
| --- | --- |
| civismedia.ca | civismedia |
| bisonbulletin.ca | bison-bulletin |
| bleuetblanc.ca | bleuet-blanc |
| bramptonbulletin.com | brampton-bulletin |
| brandonbulletin.com | brandon-bulletin |
| burrardbrief.ca | burrard-brief |
| cariboocompass.ca | cariboo-compass |
| edmontonecho.com | edmonton-echo |
| www.grandeprairiegazette.ca | grande-prairie-gazette |
| kelownacurrent.ca | kelowna-current |
| kermodechronicle.ca | kermode-chronicle |
| kitchenerchronicle.com | kitchener-chronicle |
| londonlookout.com | london-lookout |
| mississaugamonitor.com | mississauga-monitor |
| pickeringpost.ca | pickering-post |
| portagepress.ca | portage-press |
| prairiedispatch.ca | prairiedispatch |
| redriverregister.ca | red-river-register |
| rideaureview.ca | rideau-review |
| steeltownstandard.ca | steeltown-standard |
| sudburystandard.ca | sudbury-standard |
| surreystandard.ca | surrey-standard |
| thepacificpost.com | pacific-post |
| torontotelegraph.ca | toronto-telegraph |
| tricitiestorch.ca | tri-cities-torch |
| turtleislandtimes.ca | turtle-island-times |
| westernwire.ca | westernwire |

Report the 27 hosts as a table of those status codes, and say whether
the five that 404'd (Edmonton, Grande Prairie, Kermode, Pacific Post,
Prairie Dispatch) now answer `200`.
