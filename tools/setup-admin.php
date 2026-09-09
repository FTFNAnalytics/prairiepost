<?php
/**
 * Create the founding administrator — the ONLY way to provision a fresh
 * installation. Replaces the old first-visitor form on /admin/login.php,
 * which handed the installation to whoever reached the hostname first.
 *
 *   php tools/setup-admin.php "Jo Editor" jo@example.com
 *
 * The passphrase is read from standard input, never from an argument (so
 * it stays out of shell history and process lists): interactively it is
 * prompted with the echo off; non-interactively it is read from the pipe
 *
 *   printf '%s' "$PASS" | php tools/setup-admin.php "Jo Editor" jo@example.com
 *
 * Refuses when any account already exists — an installation that has
 * administrators is provisioned; recovery for those is
 * tools/reset-password.php. Creation is atomic under a table lock, so two
 * simultaneous runs create exactly one account.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$name = trim((string) ($argv[1] ?? ''));
$email = mb_strtolower(trim((string) ($argv[2] ?? '')));
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/setup-admin.php \"Full Name\" email@example.com\n"
                 . "The passphrase is read from stdin (prompted, or piped).\n");
    exit(2);
}

// Read the passphrase from stdin — hidden at a terminal, piped otherwise.
$interactive = function_exists('posix_isatty') ? @posix_isatty(STDIN) : false;
if ($interactive) {
    fwrite(STDOUT, "Passphrase (10+ characters, not echoed): ");
    shell_exec('stty -echo 2>/dev/null');
    $pass = rtrim((string) fgets(STDIN), "\r\n");
    shell_exec('stty echo 2>/dev/null');
    fwrite(STDOUT, "\n");
} else {
    $pass = rtrim((string) stream_get_contents(STDIN), "\r\n");
}
if (strlen($pass) < 10) {
    fwrite(STDERR, "Refused: the passphrase must be at least 10 characters — a few words will do.\n");
    exit(2);
}

$pdo = db();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

// Atomic founding: the emptiness check and the insert commit as one unit,
// so two simultaneous runs cannot both found the paper.
try {
    if ($driver === 'pgsql') {
        $pdo->beginTransaction();
        $pdo->exec('LOCK TABLE users IN ACCESS EXCLUSIVE MODE');
    } elseif ($driver === 'sqlite') {
        $pdo->exec('BEGIN IMMEDIATE');
    } else { // mysql: an advisory lock spans the check + insert
        $got = $pdo->query("SELECT GET_LOCK('pp_setup_admin', 10)")->fetchColumn();
        if ((string) $got !== '1') {
            fwrite(STDERR, "Refused: another provisioning run holds the lock.\n");
            exit(1);
        }
        $pdo->beginTransaction();
    }

    $existing = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($existing > 0) {
        $pdo->rollBack();
        fwrite(STDERR, "Refused: $existing account(s) already exist — this installation is provisioned.\n"
                     . "Lost passphrase? php tools/reset-password.php\n");
        exit(1);
    }

    $pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), 'admin', unique_user_slug($name), now()]);
    $uid = pp_last_id('users');
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Refused: " . $e->getMessage() . "\n");
    exit(1);
} finally {
    if ($driver === 'mysql') {
        @$pdo->query("SELECT RELEASE_LOCK('pp_setup_admin')");
    }
}

pp_audit('account.founded', $email, 'founding administrator created by tools/setup-admin.php',
         ['id' => $uid, 'name' => $name]);
echo "Founding administrator created: $name <$email> (user #$uid).\n";
echo "Sign in at /admin/login.php.\n";
