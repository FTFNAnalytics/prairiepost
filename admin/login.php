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

// A paper's sign-in wears the paper: its own masthead, footer, type and
// palette around the form, so the newsroom signs in on the paper it works
// for. The CivisMedia hub keeps the control room's own page below. The
// page is private — always noindex, and never the pasted analytics
// snippet (page_header's `private`), so no third-party script runs beside
// the password field.
if (!pp_is_hub()) {
    require PP_ROOT . '/app/views/ui.php';
    $palette = pp_brand_palette();
    $raw = (array) (pp_brand_file()['palette'] ?? []);
    $hex = static fn ($v): bool => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v) === 1;
    // The signal colour is the paper's own red when its palette names one;
    // otherwise the rule over the card is drawn in its ink.
    $signal = $hex($raw['red'] ?? null) ? $raw['red'] : $palette['ink'];
    // Secondary text in the paper's own muted grey, else a tint of its ink
    // (signin.css) — never the network default.
    $vars = '--si-ink:' . $palette['ink'] . ';--si-signal:' . $signal
        . ($hex($raw['muted'] ?? null) ? ';--si-muted:' . $raw['muted'] : '');
    page_header(['title' => pp_t('Newsroom sign-in'), 'private' => true, 'styles' => ['/assets/css/signin.css']]);
    ?>
<div class="pp-signin-wrap">
  <section class="pp-signin" style="<?= e($vars) ?>" aria-labelledby="pp-signin-title">
    <p class="pp-signin__kicker"><?= e(setting('site_title', 'The Prairie Dispatch')) ?></p>
    <h1 id="pp-signin-title"><?= e(pp_t('Newsroom sign-in')) ?></h1>
    <?php if ($firstRun): ?>
    <p class="pp-signin__note"><?= e(pp_t('No accounts exist yet. The founding administrator is created on the server console, never from this page:')) ?> <code>php tools/setup-admin.php</code></p>
    <?php endif; ?>
    <?php if ($error): ?><div class="pp-signin__flash pp-signin__flash--error" role="alert"><?= e(pp_t($error)) ?></div>
    <?php elseif (isset($_GET['expired'])): ?><div class="pp-signin__flash"><?= e(pp_t('That session ended — sign in again to continue.')) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label for="email"><?= e(pp_t('Email')) ?></label>
      <input type="email" id="email" name="email" required autocomplete="username">
      <label for="password"><?= e(pp_t('Passphrase')) ?></label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
      <button type="submit"><?= e(pp_t('Sign in')) ?></button>
    </form>
  </section>
</div>
<?php
    page_footer();
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in — Newsroom — <?= e(setting('site_title', 'The Prairie Dispatch')) ?></title>
<meta name="robots" content="noindex,nofollow">
<?php pp_emit_icon_tags(); ?>
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
