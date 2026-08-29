# Getting back in

The admin has no "forgot my passphrase" link, and it never will: the mail
path that a reset email needs isn't configured on this network, and a
reset link that goes nowhere is worse than none. Recovery happens on the
server instead, over SSH, with `tools/reset-password.php`.

Run it from inside the current release directory, as root:

    cd /var/www/prairiepost-<sha>-shared
    php tools/reset-password.php list

## One account, every masthead

There is a single `users` table for the whole network — no `site_id`.
One row signs in at every paper *and* at the hub; the `role` column is
what differs. So a passphrase set here is the passphrase everywhere, and
an account locked out of civismedia.ca is locked out of all sixteen.

## What each command is for

| Command | Use it when |
| --- | --- |
| `list` | You want to see which accounts exist, who is an admin, who has two-step on — and whether the throttle is currently holding anyone. |
| `set --email X` | The passphrase is lost or has to change. Prompted twice, never echoed, never taken from the command line. Signs that account out of every open browser. |
| `unlock --email X` | Six wrong tries in fifteen minutes locks an account. This clears it now instead of waiting the window out. |
| `disable-2fa --email X` | The authenticator app or phone is gone. Drops the stored secret so sign-in is one step again; enrol a fresh one from Profile. |
| `promote --email X --role admin` | An account has the wrong role. |
| `create --email X --name "N"` | Nothing is recoverable and you need a new administrator. |
| `hub-check` | A hub page bounces, 403s, or comes up empty and you want to know which gate is shut. |
| `hub-unfence` | The hub's address allowlist no longer contains the address you work from. |

Add `--stdin` to any command that asks for a passphrase to pipe one in
rather than be prompted — for a scripted run, never for a typed one.

## When it is the hub that won't open

Four gates stand between an administrator and a page at civismedia.ca,
and from the browser they look much alike. `hub-check` reads all four:

1. **`hub_slug` in `config.php`** — if it doesn't name the site being
   served, `pp_is_hub()` is false and every hub-only page (Audit trail,
   Network Desk, Advertising) quietly redirects to the dashboard.
2. **The address fence** (`admin_ip_allowlist`, Settings → Security) —
   a 403 reading "limited to approved addresses". Papers never evaluate
   it; only the hub does. `hub-unfence` clears it.
3. **Two-step for hub administrators** (`require_totp`, on by default) —
   an admin who hasn't enrolled is funnelled to Profile and can reach
   nothing else until they do. Not a lockout, but it looks like one.
4. **The role itself** — `require_admin()` answers 403 to an editor.

Because those settings belong to the hub's own site row, read them under
the hub's slug:

    PP_SITE=civismedia php tools/reset-password.php hub-check

## What it writes

Only the `users` and `login_attempts` rows named above, plus one line in
the audit trail for every change — `password.reset`, `login.unblocked`,
`totp.disabled`, `role.changed`, `account.created`, `security.unfenced`
— attributed to `cli:reset-password (<shell user>)`. Nothing else in the
database is touched, and nothing is ever deleted but stale failed
attempts.
