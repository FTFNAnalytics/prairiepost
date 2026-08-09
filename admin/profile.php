<?php
/** Your profile: the public author page every byline links to. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_login();

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
        db()->prepare('UPDATE users SET pass_hash = ? WHERE id = ?')
            ->execute([password_hash($pass, PASSWORD_DEFAULT), (int) $user['id']]);
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

<?php admin_footer(); ?>
