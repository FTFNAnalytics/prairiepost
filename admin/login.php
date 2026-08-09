<?php
/** Newsroom sign-in. On a fresh install this creates the founding account. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';

$firstRun = users_count() === 0;
$error = '';

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
            session_regenerate_id(true);
            redirect('index.php');
        }
    } else {
        $user = user_by_email((string) ($_POST['email'] ?? ''));
        if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['pass_hash'])) {
            $_SESSION['uid'] = (int) $user['id'];
            session_regenerate_id(true);
            redirect('index.php');
        }
        $error = 'That email and passphrase don\'t match an account. Check both and try again.';
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
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="login">
  <img class="mast" src="/assets/img/logo-primary.svg" alt="<?= e(setting('site_title', 'The Prairie Dispatch')) ?>">
  <div class="pp-horizon"></div>
  <div class="panel">
    <h2><?= $firstRun ? 'Start the paper' : 'Newsroom sign-in' ?></h2>
    <?php if ($firstRun): ?>
    <p style="font-size:15px;margin:0 0 4px">No accounts exist yet. This form creates the founding administrator.</p>
    <?php endif; ?>
    <?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>
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
  </div>
</div>
</body>
</html>
