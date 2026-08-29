<?php
/**
 * Account recovery from the server's command line — the escape hatch for
 * the case the browser cannot help with: nobody can sign in, so nobody
 * can reach the admin that would fix it.
 *
 *   php tools/reset-password.php list
 *   php tools/reset-password.php set --email you@example.com
 *   php tools/reset-password.php unlock --email you@example.com
 *   php tools/reset-password.php clear-2fa --all
 *   php tools/reset-password.php promote --email you@example.com --role admin
 *   php tools/reset-password.php create --email you@example.com --name "Your Name"
 *
 * Accounts are network-wide: one `users` row signs in at every paper and
 * at the hub, so a passphrase set here is the passphrase everywhere.
 *
 * The passphrase is never taken from the command line — the shell's
 * history and every `ps` on the box would hold it. It is prompted for
 * with the echo off, or read from stdin with --stdin for a scripted run.
 *
 * Every write lands in the audit trail under 'cli:reset-password', and
 * setting a passphrase bumps session_epoch, which signs that account out
 * of every browser it was still open in.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

$cmd = $argv[1] ?? '';
// getopt() stops at the first non-option (the subcommand), so parse by hand.
$opt = [];
$flags = [];
for ($i = 2; $i < count($argv); $i++) {
    if (preg_match('/^--(email|name|role)$/', $argv[$i], $m) && isset($argv[$i + 1])) {
        $opt[$m[1]] = $argv[++$i];
    } elseif (preg_match('/^--(email|name|role)=(.*)$/', $argv[$i], $m)) {
        $opt[$m[1]] = $m[2];
    } elseif (preg_match('/^--(stdin|yes|all)$/', $argv[$i], $m)) {
        $flags[$m[1]] = true;
    }
}

/** The account this command is about, or a clean exit explaining why not. */
function pp_rp_user(array $opt): array
{
    $email = mb_strtolower(trim((string) ($opt['email'] ?? '')));
    if ($email === '') {
        exit("--email is required. `list` shows every account.\n");
    }
    $user = user_by_email($email);
    if (!$user) {
        exit("No account for '{$email}'. `list` shows every account.\n");
    }
    return $user;
}

/** Read a passphrase without printing it: twice from a terminal, once from a pipe. */
function pp_rp_passphrase(bool $fromStdin): string
{
    if ($fromStdin) {
        $pass = rtrim((string) fgets(STDIN), "\r\n");
        if (strlen($pass) < 10) {
            exit("The passphrase needs at least 10 characters.\n");
        }
        return $pass;
    }
    if (!stream_isatty(STDIN)) {
        exit("Not a terminal. Pipe the passphrase in and add --stdin.\n");
    }
    $echoOff = @shell_exec('stty -echo 2>/dev/null') !== null;
    fwrite(STDOUT, 'New passphrase (at least 10 characters, not shown): ');
    $pass = rtrim((string) fgets(STDIN), "\r\n");
    fwrite(STDOUT, "\nAgain: ");
    $again = rtrim((string) fgets(STDIN), "\r\n");
    if ($echoOff) {
        shell_exec('stty echo 2>/dev/null');
    }
    fwrite(STDOUT, "\n");
    if ($pass !== $again) {
        exit("Those didn't match. Nothing changed.\n");
    }
    if (strlen($pass) < 10) {
        exit("The passphrase needs at least 10 characters. Nothing changed.\n");
    }
    return $pass;
}

/** The audit trail wants an actor; on the command line that is the shell. */
function pp_rp_actor(): array
{
    $who = trim((string) (getenv('SUDO_USER') ?: getenv('USER') ?: 'root'));
    return ['id' => 0, 'name' => 'cli:reset-password (' . $who . ')'];
}

$pdo = db();

switch ($cmd) {
    case 'list':
        $rows = $pdo->query('SELECT id, name, email, role, created_at FROM users ORDER BY id')->fetchAll();
        if (!$rows) {
            echo "No accounts exist. The first sign-in page will offer to found one, or run `create`.\n";
            break;
        }
        printf("%-4s %-26s %-34s %-8s %s\n", 'ID', 'NAME', 'EMAIL', 'ROLE', 'CREATED');
        foreach ($rows as $r) {
            printf(
                "%-4d %-26s %-34s %-8s %s\n",
                (int) $r['id'],
                mb_strimwidth((string) $r['name'], 0, 26, '…'),
                mb_strimwidth((string) $r['email'], 0, 34, '…'),
                (string) $r['role'],
                (string) $r['created_at']
            );
        }
        // A throttled account looks exactly like a wrong passphrase from the
        // browser, so name it here rather than leaving it to be guessed.
        $since = date('Y-m-d H:i:s', time() - 900);
        $blocked = $pdo->prepare('SELECT email, COUNT(*) AS n FROM login_attempts WHERE succeeded = 0 AND created_at > ? GROUP BY email HAVING COUNT(*) >= 6');
        $blocked->execute([$since]);
        foreach ($blocked->fetchAll() as $b) {
            echo "\nthrottled: {$b['email']} has {$b['n']} failed attempts in the last 15 minutes — "
               . "sign-in is refused until they age out, or run `unlock --email {$b['email']}`.\n";
        }
        // Two-step is gone; a row still carrying a secret is a leftover that
        // would strand its owner if the feature is ever switched back on.
        $left = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE totp_enabled = 1 OR totp_secret != ''")->fetchColumn();
        if ($left > 0) {
            echo "\nleftovers: {$left} account(s) still hold a two-step secret from before the feature was "
               . "removed. Nothing reads it now. `clear-2fa --all` wipes them.\n";
        }
        break;

    case 'set':
        $user = pp_rp_user($opt);
        $pass = pp_rp_passphrase(!empty($flags['stdin']));
        // A new passphrase orphans every other session — the same rule the
        // profile page follows when someone changes their own.
        $pdo->prepare('UPDATE users SET pass_hash = ?, session_epoch = session_epoch + 1 WHERE id = ?')
            ->execute([password_hash($pass, PASSWORD_DEFAULT), (int) $user['id']]);
        pp_login_clear((string) $user['email']);
        pp_audit('password.reset', (string) $user['email'], 'set from the command line; all sessions signed out', pp_rp_actor());
        echo "Passphrase set for {$user['email']} ({$user['role']}). Any open session for that account is now signed out.\n";
        echo "Sign-in is one step — email and passphrase, nothing else to carry.\n";
        break;

    case 'unlock':
        $user = pp_rp_user($opt);
        $n = pp_login_clear((string) $user['email']);
        pp_audit('login.unblocked', (string) $user['email'], "cleared {$n} failed attempts", pp_rp_actor());
        echo "Cleared {$n} failed sign-in attempts for {$user['email']}. The throttle is off for that account.\n";
        break;

    case 'clear-2fa':
        // Two-step sign-in is gone from the code, but the columns stay (the
        // schema only ever grows). This wipes the stored secrets so that if
        // the feature is ever restored, nobody is enrolled to a lost phone.
        if (!empty($flags['all'])) {
            $n = $pdo->exec("UPDATE users SET totp_secret = '', totp_enabled = 0 WHERE totp_enabled = 1 OR totp_secret != ''");
            pp_audit('totp.cleared', 'all accounts', "wiped {$n} leftover secret(s) from the command line", pp_rp_actor());
            echo $n > 0 ? "Wiped {$n} leftover two-step secret(s).\n" : "No leftovers to wipe.\n";
            break;
        }
        $user = pp_rp_user($opt);
        if (empty($user['totp_enabled']) && (string) $user['totp_secret'] === '') {
            echo "{$user['email']} holds no two-step secret. Nothing changed.\n";
            break;
        }
        $pdo->prepare("UPDATE users SET totp_secret = '', totp_enabled = 0 WHERE id = ?")
            ->execute([(int) $user['id']]);
        pp_audit('totp.cleared', (string) $user['email'], 'leftover secret wiped from the command line', pp_rp_actor());
        echo "Wiped the leftover two-step secret for {$user['email']}.\n";
        break;

    case 'hub-check':
        // Three gates stand between an administrator and a hub page, and from
        // the browser all three look alike — a bounce, a 403, a blank list.
        // Read them out instead of guessing which one is shut.
        // Read the raw key: slugify() answers 'story' for an empty string,
        // which would report a hub that isn't configured at all.
        $raw = trim((string) pp_config('hub_slug', ''));
        $hub = $raw === '' ? '' : slugify($raw);
        $here = (string) (current_site()['slug'] ?? '');
        echo "config hub_slug : " . ($hub === '' ? "(unset — no site is the hub)" : $hub) . "\n";
        echo "this release    : {$here}\n";
        echo "pp_is_hub()     : " . (pp_is_hub() ? 'yes' : 'NO — hub-only pages redirect to the dashboard') . "\n";
        if (!pp_is_hub()) {
            echo "\nRun this again with PP_SITE=" . ($hub !== '' ? $hub : '<hub slug>') . " to read the hub's own settings.\n";
        }
        $fence = trim(setting('admin_ip_allowlist', ''));
        echo "address fence   : " . ($fence === '' ? 'open (no allowlist)' : "ON — only {$fence}") . "\n";
        echo "two-step        : removed — sign-in is one step everywhere\n";
        $admins = $pdo->query("SELECT email FROM users WHERE role = 'admin' ORDER BY id")->fetchAll();
        echo "administrators  : " . ($admins ? implode(', ', array_column($admins, 'email')) : 'NONE — no account can open a hub page') . "\n";
        $n = (int) $pdo->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
        echo "audit_log rows  : {$n}\n";
        if ($fence !== '') {
            echo "\nIf the fence is what's shutting you out: php tools/reset-password.php hub-unfence\n";
        }
        break;

    case 'hub-unfence':
        // The fence is a per-site setting, so it must be cleared on the hub's
        // own row — hence PP_SITE. Emptying it restores 'any address'.
        if (!pp_is_hub()) {
            $raw = trim((string) pp_config('hub_slug', '')) ?: 'civismedia';
            exit("Run this against the hub: PP_SITE={$raw} php tools/reset-password.php hub-unfence\n");
        }
        $was = trim(setting('admin_ip_allowlist', ''));
        if ($was === '') {
            echo "The address fence was already open. Nothing changed.\n";
            break;
        }
        set_setting('admin_ip_allowlist', '');
        pp_audit('security.unfenced', 'admin_ip_allowlist', "cleared from the command line (was: {$was})", pp_rp_actor());
        echo "Address fence cleared — the control room accepts any address again.\n";
        echo "Set a fresh range under Settings → Security once you are back in.\n";
        break;

    case 'promote':
        $user = pp_rp_user($opt);
        $role = strtolower(trim((string) ($opt['role'] ?? 'admin')));
        if (!in_array($role, ['admin', 'editor', 'author'], true)) {
            exit("--role must be admin, editor or author.\n");
        }
        $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, (int) $user['id']]);
        pp_audit('role.changed', (string) $user['email'], "{$user['role']} -> {$role} from the command line", pp_rp_actor());
        echo "{$user['email']} is now {$role} (was {$user['role']}).\n";
        break;

    case 'create':
        $email = mb_strtolower(trim((string) ($opt['email'] ?? '')));
        $name  = trim((string) ($opt['name'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
            exit("--email (a real address) and --name are both required.\n");
        }
        if (user_by_email($email)) {
            exit("That address already has an account — use `set` to give it a new passphrase.\n");
        }
        $role = strtolower(trim((string) ($opt['role'] ?? 'admin')));
        if (!in_array($role, ['admin', 'editor', 'author'], true)) {
            exit("--role must be admin, editor or author.\n");
        }
        $pass = pp_rp_passphrase(!empty($flags['stdin']));
        $pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, unique_user_slug($name), now()]);
        pp_audit('account.created', $email, "{$role} created from the command line", pp_rp_actor());
        echo "Created {$email} as {$role}. It signs in at every paper and at the hub.\n";
        break;

    default:
        echo <<<TXT
        Account recovery — run on the server, inside a release directory.

          list                              every account, plus any the throttle is holding
          set --email X                     set a new passphrase (prompted, never echoed)
          unlock --email X                  clear the 15-minute failed-attempt throttle
          clear-2fa --all                   wipe secrets left by the removed two-step feature
          promote --email X --role admin    change a role (admin|editor|author)
          create --email X --name "N"       add an account when none can be recovered

        When it is the hub that won't open (civismedia), two more:

          hub-check                         read out every gate on a hub page
          hub-unfence                       clear the admin address allowlist

        Accounts are network-wide: one row signs in at every paper and at the hub.
        The hub's own settings need PP_SITE, e.g.
        PP_SITE=civismedia php tools/reset-password.php hub-check
        Add --stdin to pipe a passphrase in instead of being prompted.

        TXT;
        break;
}
