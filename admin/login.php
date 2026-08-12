<?php
/**
 * Newsroom sign-in. On a fresh install this creates the founding account.
 * Sign-in is throttled through login_attempts, and accounts with two-step
 * enabled get a second screen: passphrase first, then the six-digit code
 * from their authenticator app.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';

// Burned when the email matches no account, so both paths cost one bcrypt
// verify and timing doesn't reveal which addresses have accounts.
const PP_DECOY_HASH = '$2y$12$1IpKG16KXsBLZNzEgkM61urfG8pcWxG20k3PIPYc2xbIanXl48fGy';

$firstRun = users_count() === 0;
$error = '';

if (isset($_GET['cancel'])) {
    unset($_SESSION['totp_uid'], $_SESSION['totp_exp']);
}

/** The half-signed-in account: passphrase accepted, code still owed. */
function pp_totp_pending(): ?array
{
    $uid = (int) ($_SESSION['totp_uid'] ?? 0);
    if ($uid > 0 && time() < (int) ($_SESSION['totp_exp'] ?? 0)) {
        return user_by_id($uid);
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($firstRun) {
        $name  = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $pass  = (string) ($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'The account needs a name and a working email address.';
        } elseif (strlen($pass) < 10) {
            $error = 'Pick a passphrase of at least 10 characters — a few words will do.';
        } else {
            db()->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), 'admin', unique_user_slug($name), now()]);
            $_SESSION['uid'] = pp_last_id('users');
            pp_session_stamp(0);
            session_regenerate_id(true);
            pp_audit('account.founded', $email, 'founding administrator created at first run', ['id' => $_SESSION['uid'], 'name' => $name]);
            redirect('index.php');
        }
    } elseif (isset($_POST['totp_code'])) {
        $pending = pp_totp_pending();
        if (!$pending) {
            $error = 'That sign-in took too long. Start again from your email and passphrase.';
        } elseif (pp_login_blocked($pending['email'])) {
            $error = 'Too many attempts. Wait fifteen minutes, then try again.';
        } elseif (pp_totp_verify((string) $pending['totp_secret'], (string) $_POST['totp_code'])) {
            unset($_SESSION['totp_uid'], $_SESSION['totp_exp']);
            $_SESSION['uid'] = (int) $pending['id'];
            pp_session_stamp((int) ($pending['session_epoch'] ?? 0));
            session_regenerate_id(true);
            pp_login_record($pending['email'], true);
            pp_audit('login', $pending['email'], 'two-step', $pending);
            redirect('index.php');
        } else {
            pp_login_record($pending['email'], false);
            $error = 'That code didn\'t match. Codes change every 30 seconds — check the app and try the current one.';
        }
    } else {
        $email = (string) ($_POST['email'] ?? '');
        if (pp_login_blocked($email)) {
            $error = 'Too many attempts. Wait fifteen minutes, then try again.';
        } else {
            $user = user_by_email($email);
            $verified = password_verify((string) ($_POST['password'] ?? ''), $user['pass_hash'] ?? PP_DECOY_HASH);
            if ($user && $verified && !empty($user['totp_enabled'])) {
                $_SESSION['totp_uid'] = (int) $user['id'];
                $_SESSION['totp_exp'] = time() + 300;
                session_regenerate_id(true);
                redirect('login.php');
            } elseif ($user && $verified) {
                $_SESSION['uid'] = (int) $user['id'];
                pp_session_stamp((int) ($user['session_epoch'] ?? 0));
                session_regenerate_id(true);
                pp_login_record($user['email'], true);
                pp_audit('login', $user['email'], '', $user);
                redirect('index.php');
            }
            pp_login_record($email, false);
            $error = 'That email and passphrase don\'t match an account. Check both and try again.';
        }
    }
}

if (current_user()) {
    redirect('index.php');
}

$totpStep = pp_totp_pending() !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in — Newsroom — <?= e(setting('site_title', 'The Prairie Dispatch')) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/svg+xml" href="<?= e(site_asset('favicon.svg')) ?>">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="login">
  <img class="mast" src="<?= e(site_asset('logo-primary.svg')) ?>" alt="<?= e(setting('site_title', 'The Prairie Dispatch')) ?>">
  <div class="pp-horizon"></div>
  <div class="panel">
    <?php if ($totpStep): ?>
    <h2>Second step</h2>
    <p style="font-size:15px;margin:0 0 4px">Passphrase accepted. Enter the six-digit code from your authenticator app.</p>
    <?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label for="totp_code">Authenticator code</label>
      <input type="text" id="totp_code" name="totp_code" required autofocus inputmode="numeric" pattern="[0-9 ]*" maxlength="8" autocomplete="one-time-code" placeholder="123 456">
      <p style="margin-top:18px"><button class="btn" type="submit">Finish signing in</button></p>
      <p style="font-size:14px;margin:10px 0 0"><a href="login.php?cancel=1">Start over with a different account</a></p>
    </form>
    <?php else: ?>
    <h2><?= $firstRun ? 'Start the paper' : 'Newsroom sign-in' ?></h2>
    <?php if ($firstRun): ?>
    <p style="font-size:15px;margin:0 0 4px">No accounts exist yet. This form creates the founding administrator.</p>
    <?php endif; ?>
    <?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div>
    <?php elseif (isset($_GET['expired'])): ?><div class="flash">That session ended — sign in again to continue.</div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <?php if ($firstRun): ?>
      <label for="name">Your name</label>
      <input type="text" id="name" name="name" required autocomplete="name">
      <?php endif; ?>
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autocomplete="username">
      <label for="password">Passphrase</label>
      <input type="password" id="password" name="password" required autocomplete="<?= $firstRun ? 'new-password' : 'current-password' ?>">
      <p style="margin-top:18px"><button class="btn" type="submit"><?= $firstRun ? 'Create the account' : 'Sign in' ?></button></p>
    </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
