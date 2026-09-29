# Taking bisonbulletin.ca live — deployment runbook (FOUNDATION, not launch-ready)

The Bison Bulletin (slug `bison-bulletin`, template `bison`) is a
FOUNDATION: scaffolded, functional and deliberately plain. **Do not
launch it from this state.** Before any launch order:

1. The owner's brand package must land (palette, marks, typography,
   tagline, footprint) and be applied as a brand build — see the
   Rideau/Cariboo/Burrard builds for the shape.
2. The brand build writes the six-story inaugural service edition into
   this pack (launch notes about the paper itself, true by
   construction — never invented local news).
3. **Confirm the REGISTERED domain.** The pack assumes
   `bisonbulletin.ca`; the Burrard launch taught that the sheet and
   the registration can differ. Fix the pack first if they do.
4. Confirm the footprint with the owner: the bison reads Winnipeg-wide
   or province-wide equally well — settle which this masthead is,
   alongside the Red River Register's valley footprint, before the
   inaugural notes are written.

When those are done, the launch itself is the standard config-edit-free
flow (this runbook is then revised to brand-complete form): DNS via
`dig @1.1.1.1` → `make-vhost.sh` with the BARE fpm socket path (the
generator adds `unix:` itself), both address families, no
`default_server` → certbot both hostnames, `listen 443 ssl` on BOTH
families → `PP_SITE=bison-bulletin php tools/seed-launch.php` →
verify served bytes per the revised runbook.

Pre-seed expectation table (foundation state):

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Template class `t-bison`, bison.css (scaffold styling) | Release tree | Present before the seed |
| Title "The Bison Bulletin", placeholder tagline | This pack's settings | Absent until the seed — the tagline reads the network default before it |
| Desks in the nav | Shared `categories` + chrome.nav | ALL six exist network-wide already — read the real list with `categories_all()` from `$REL`, assert no absences |
| Stories | This pack | NONE until the brand build writes the inaugural edition |
