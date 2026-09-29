# Taking torontotelegraph.ca live — deployment runbook (FOUNDATION, not launch-ready)

The Toronto Telegraph (slug `toronto-telegraph`, template `telegraph`)
is a FOUNDATION: scaffolded, functional and deliberately plain. **Do
not launch it from this state.** Before any launch order:

1. The owner's brand package must land (palette, marks, typography,
   tagline, footprint) and be applied as a brand build — see the
   Manitoba builds for the shape.
2. The brand build writes the six-story inaugural service edition into
   this pack (launch notes about the paper itself, true by
   construction — never invented local news).
3. The registered domain is **owner-confirmed: `torontotelegraph.ca`**
   (29 Sep). If the brand package names a different hostname, that is
   a conflict to resolve with the owner, not a correction to apply.
4. Confirm the footprint with the owner: working assumption is Toronto
   proper (the 416), which shapes the desks and the inaugural notes.

When those are done, the launch itself is the standard config-edit-free
flow (this runbook is then revised to brand-complete form): DNS via
`dig @1.1.1.1` → `make-vhost.sh` with the BARE fpm socket path (the
generator adds `unix:` itself), both address families, no
`default_server` → certbot both hostnames, `listen 443 ssl` on BOTH
families → `PP_SITE=toronto-telegraph php tools/seed-launch.php` →
verify served bytes per the revised runbook.

Pre-seed expectation table (foundation state):

| Value | Where it comes from | Pre-seed state |
| --- | --- | --- |
| Template class `t-telegraph`, telegraph.css (scaffold styling) | Release tree | Present before the seed |
| Title "The Toronto Telegraph", placeholder tagline | This pack's settings | Absent until the seed — the tagline reads the network default before it |
| Desks in the nav | Shared `categories` + chrome.nav | ALL six exist network-wide already (`arts` since Portage's 29 Sep seed) — read the real list with `categories_all()` from `$REL`, assert no absences |
| Stories | This pack | NONE until the brand build writes the inaugural edition |
