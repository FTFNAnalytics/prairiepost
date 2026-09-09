<?php
/**
 * Newsroom sign-in. One step — email and passphrase — throttled through
 * login_attempts. Two-step sign-in was removed while the network is
 * private; recovery for a lost passphrase is tools/reset-password.php, on
 * the server. On a fresh install NO public form creates the founding
 * account — that was first-visitor ownership: whoever reached an
 * unprovisioned hostname first owned the installation. Provisioning is
 * tools/setup-admin.php, run on the server by someone who can already
 * open a shell there.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';

// Burned when the email matches no account, so both paths cost one bcrypt
// verify and timing doesn't reveal which addresses have accounts.
const PP_DECOY_HASH = '$2y$12$1IpKG16KXsBLZNzEgkM61urfG8pcWxG20k3PIPYc2xbIanXl48fGy';

$firstRun = users_count() === 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($firstRun) {
        // No accounts exist and this is a public form: refuse. The founding
        // administrator is created on the server (tools/setup-admin.php),
        // never by whichever visitor arrives first.
        $error = 'No accounts exist yet. The founding administrator is created on the server: php tools/setup-admin.php';
    } else {
        $email = (string) ($_POST['email'] ?? '');
        if (pp_login_blocked($email)) {
            $error = 'Too many attempts. Wait fifteen minutes, then try again.';
        } else {
            $user = user_by_email($email);
            $verified = password_verify((string) ($_POST['password'] ?? ''), $user['pass_hash'] ?? PP_DECOY_HASH);
            if ($user && $verified) {
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
    <h2>Newsroom sign-in</h2>
    <?php if ($firstRun): ?>
    <p style="font-size:15px;margin:0 0 4px">No accounts exist yet. The founding administrator is created on the
    server console — <span class="mono">php tools/setup-admin.php</span> — never from this page.</p>
    <?php endif; ?>
    <?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div>
    <?php elseif (isset($_GET['expired'])): ?><div class="flash">That session ended — sign in again to continue.</div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autocomplete="username">
      <label for="password">Passphrase</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
      <p style="margin-top:18px"><button class="btn" type="submit">Sign in</button></p>
    </form>
  </div>
</div>
</body>
</html>
