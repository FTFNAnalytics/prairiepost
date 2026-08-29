# Removing two-step sign-in from the network — deployment runbook

Two-step sign-in is gone from the code. It shipped required-by-default
for hub administrators, enforced by a funnel — every control-room page
redirecting to the profile page until an authenticator was enrolled —
and that funnel locked the network's own administrator out of
civismedia.ca, dashboard included.

This runbook does two separable things, in this order and not the other:

1. **Unlock the control room now**, with no deploy at all. The gates are
   database state the currently-served release reads, so turning them
   off is a tool run, not a release.
2. **Deploy the removal**, which also rolls the hub onto the shared
   release for the first time.

Step 1 alone restores access and is safe to stop after. Step 2 without
step 1 is the risky order: if the release upgrade rolls back, the
operator is still locked out.

**Nothing in this runbook needs a credential.** No tokens, no database
password, no key material is issued, read, or printed. The tools read
the configuration already present in the release directory. Do not
print `config.php` or `app/config.site.php` at any point, and do not
paste their contents into the report.

## Step 0 — Discover and pin

Do not take any path in this document as fact about the server. Derive
them:

- The release root for the **papers**: read it from the enabled nginx
  blocks, strip inline comments, `readlink -f` the result. Some blocks
  reference the `/var/www/prairiepost-current` symlink and some the
  physical directory; they are the same checkout.
- The release root for the **hub**: the same, from the `civismedia`
  block. Expect it to differ from the papers' — that difference is the
  thing step 2 fixes. Record both.
- Never derive a release from `ls /var/www/`. Old releases stay on disk
  for rollback and sort misleadingly.

Pin the code: in a scratch clone (never inside a release), require
commit `134f348` to be an ancestor of
`origin/claude/master-dashboard-control-room-nr3mp4`. That commit is
`kill-2fa`; `dc7fdbb` is the removal itself and `a0049eb` puts the hub
in the release roll.

Baseline before touching anything: every enabled hostname answers 200
with its own exact front-page title, and bleuetblanc.ca answers
`lang="fr"` while three English papers answer `lang="en"`. Record the
list. This is the loop referred to below as **the network loop**.

## Step 1 — Unlock the control room (no deploy)

The tool lives on the release branch, so it needs a checkout that has
it. Either `/root/recovery` (a shallow clone made earlier — `git pull`
it) or a fresh scratch clone, with the live `config.php` and
`app/config.site.php` **copied** in, root-owned and mode 600. It reads
the same database the served releases read; that is the whole point.

    php tools/reset-password.php kill-2fa

It writes `require_totp = '0'` to every site row first, then clears
`totp_enabled` / `totp_secret` on every account — that order and no
other. The reverse moves an enrolled administrator from "asked for a
code" to "must enrol", which is worse than where they started. It
writes the setting to every site rather than only the hub's so that a
wrong assumption about the hub's slug cannot make the command quietly
do nothing.

**Verify**, and only these:

1. The command's own read-back prints `sites still demanding two-step:
   none` and `accounts still flagged : 0`.
2. Sign in at civismedia.ca: email and passphrase reach the dashboard
   directly, with no second screen and no bounce to
   `profile.php?totp=required`.
3. `/admin/audit.php` answers 200 and renders the "Audit trail"
   heading.

Do not stop over the profile page still offering two-step enrolment
here, or over Settings still showing a "Require two-step sign-in"
checkbox. The served release is still the old code; those disappear in
step 2. Their presence at this step is expected.

If sign-in still asks for anything beyond a passphrase after a
successful read-back, stop and report — including which page and what
it asked for. Do not edit the database by hand to force it.

## Step 2 — Deploy the removal, and roll the hub forward

As root, from the papers' release root:

    bash "$REL/tools/vps/upgrade-papers.sh"

(`PP_RELEASE_TARBALL=/path.tgz` if codeload is unreachable from the
box; the script also falls back to a shallow clone on its own.)

This run differs from every previous one: **civismedia is in the vhost
map for the first time.** The script excluded it by name, which is why
the hub sat on its original release while the papers rolled forward on
the same database. Seeing `civismedia.ca` listed is the drift closing,
not an error. `cies` is still excluded and must stay excluded — it is
the Institute, a different application.

The verification rule has changed with it: every domain must answer
afterwards exactly as it answered before — the same status code, and
where that code was 200, the same title and stylesheet set. A domain
that redirected before and answers 200 now fails, which is correct.

**Verify:**

1. No `FATAL`, no `restoring release group`. Every domain `PASS`.
2. The hub's nginx block now names the same release directory the
   papers were moved to (`readlink -f` it; do not eyeball the name).
3. Immediately after the first request against the new release — the
   first request runs any pending migration, and a verification curl is
   itself such a request — re-run **the network loop** and the language
   controls.

If any group rolled back, stop and report which domain failed and with
what code and title. Rollback is per group, so a hub failure leaves the
sixteen papers on the new release; say so explicitly if that happens.

## Step 3 — Confirm the removal is real

Against the **new** release, signed in at civismedia.ca:

1. `/admin/login.php` — signing out and back in shows one form, no
   "Second step" screen.
2. `/admin/profile.php` — 200, and the served HTML contains no
   "Two-step sign-in" section and no `totp_action` field.
3. `/admin/settings.php` — 200, and the Security panel no longer offers
   a two-step checkbox.
4. `/admin/audit.php` — 200, and the trail now carries a `totp.killed`
   row from step 1 and a `password.reset`-family row if one was set.
5. `ls "$REL/app/totp.php"` — absent. `php "$REL/tests/run.php"` — all
   five files pass.
6. The papers are unchanged where they should be: the Kitchener
   Chronicle's draft filed by `hermes-network` still shows its
   `Agent: hermes-network` chip in `/admin/posts.php`.
7. Ingest is still closed: POST to `https://kitchenerchronicle.com/`
   `ingest.php` with no `Authorization` header → 401
   `{"ok":false,"error":"missing bearer token"}`.

## Standing rules

- **Stop after two failed attempts at the same verification.** Report
  exactly what was run, what came back, and what was expected. A
  correct refusal is worth more than a forced pass.
- **No manual database edits.** `tools/reset-password.php` and the
  migrations do every write in this runbook.
- **Do not touch another paper's server block, cron file, or config
  arm**, nor `/var/www/cies-*`. Service actions are limited to
  `nginx -t && systemctl reload nginx`.
- **Never print credentials** — not `config.php`, not
  `app/config.site.php`, not a Hermes token, not key material.
- Do not enable any newsletter. Do not issue a Hermes token. Neither is
  part of this change.

## What this does not do

`users.totp_secret` and `totp_enabled` stay in the schema. The schema
only ever grows, and dropping columns from a shared production database
to delete a feature is a bad trade. Nothing reads them after step 2.
