<?php
/** Your profile: the public author page every byline links to. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['session_action'])) {
    csrf_check();
    if ($_POST['session_action'] === 'revoke_all') {
        db()->prepare('UPDATE users SET session_epoch = session_epoch + 1 WHERE id = ?')->execute([(int) $user['id']]);
        $fresh = user_by_id((int) $user['id']);
        // This browser keeps working: stamp it with the new epoch. Every
        // other signed-in browser fails the epoch check on its next click.
        $_SESSION['epoch'] = (int) ($fresh['session_epoch'] ?? 0);
        pp_audit('session.revoked_all', $user['email']);
        flash_set('Done — every other signed-in browser and device is out. This one stays.');
    }
    redirect('profile.php#sessions');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim((string) ($_POST['name'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $bio   = trim((string) ($_POST['bio'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');
    $photo = trim((string) ($_POST['photo'] ?? ''));

    if ($name === '') {
        flash_set('Your profile needs a name — it appears on every byline.', true);
        redirect('profile.php');
    }

    if (!empty($_FILES['photo_file']['name'])) {
        [$uploadedUrl, $err] = pp_handle_image_upload($_FILES['photo_file']);
        if ($err !== null) {
            flash_set("The photo didn't upload: $err", true);
            redirect('profile.php');
        }
        $photo = $uploadedUrl;
    }

    $slug = $user['slug'] ?: unique_user_slug($name, (int) $user['id']);
    db()->prepare('UPDATE users SET name = ?, slug = ?, title = ?, bio = ?, photo = ? WHERE id = ?')
        ->execute([$name, $slug, $title, $bio, $photo, (int) $user['id']]);

    if ($pass !== '') {
        if (strlen($pass) < 10) {
            flash_set('Profile saved, but the passphrase was not changed — it needs at least 10 characters.', true);
            redirect('profile.php');
        }
        // A new passphrase orphans every other session — if it changed
        // because the old one leaked, whoever holds it is out now too.
        db()->prepare('UPDATE users SET pass_hash = ?, session_epoch = session_epoch + 1 WHERE id = ?')
            ->execute([password_hash($pass, PASSWORD_DEFAULT), (int) $user['id']]);
        $fresh = user_by_id((int) $user['id']);
        $_SESSION['epoch'] = (int) ($fresh['session_epoch'] ?? 0);
        pp_audit('password.changed', $user['email']);
    }
    flash_set('Profile saved. Your public page is up to date.');
    redirect('profile.php');
}

$user = user_by_id((int) $user['id']);

admin_header('Your profile', 'profile');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle">Your profile</h1>
  <?php if ($user['slug']): ?>
  <a class="btn btn--ghost" href="/author/<?= e($user['slug']) ?>" target="_blank">View your public page →</a>
  <?php endif; ?>
</div>
<p class="pagesub">This is the page readers reach from your byline, on every site in the network.</p>

<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="formgrid">
    <div>
      <label for="name">Name · appears on bylines</label>
      <input type="text" id="name" name="name" value="<?= e($user['name']) ?>" required>
      <label for="title">Title · e.g. “Agriculture reporter”</label>
      <input type="text" id="title" name="title" value="<?= e($user['title']) ?>">
      <label for="bio">Bio · two or three sentences, plain register</label>
      <textarea id="bio" name="bio" class="prose"><?= e((string) $user['bio']) ?></textarea>
    </div>
    <div>
      <label for="photo">Photo path</label>
      <input type="text" id="photo" name="photo" value="<?= e($user['photo']) ?>" placeholder="/uploads/2026/08/you.jpg">
      <label for="photo_file">…or upload one</label>
      <input type="file" id="photo_file" name="photo_file" accept="image/jpeg,image/png,image/webp">
      <label for="password">New passphrase · leave blank to keep the current one</label>
      <input type="password" id="password" name="password" autocomplete="new-password">
      <p class="help">Your public page address:
        <span class="mono">/author/<?= e($user['slug'] ?: '(set on first save)') ?></span></p>
    </div>
  </div>
  <p style="margin-top:20px"><button class="btn" type="submit">Save the profile</button></p>
</form>

<h2 id="sessions" style="margin-top:36px">Signed-in sessions</h2>
<p class="pagesub">Left a newsroom machine signed in, or worried a session cookie walked off? This signs out every browser and device on your account — except the one you're using now. Changing your passphrase does the same automatically.</p>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="session_action" value="revoke_all">
  <button class="btn btn--ghost" type="submit">Sign out everywhere else</button>
</form>

<?php admin_footer(); ?>
