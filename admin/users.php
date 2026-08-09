<?php
/** Newsroom accounts. Admins run the paper; editors file and publish stories. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = in_array($_POST['role'] ?? '', ['admin', 'editor', 'author'], true) ? $_POST['role'] : 'author';
        $pass = (string) ($_POST['password'] ?? '');
        $title = trim((string) ($_POST['title'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('An account needs a name and a working email address.', true);
        } elseif (!$id && strlen($pass) < 10) {
            flash_set('A new account needs a passphrase of at least 10 characters.', true);
        } else {
            $existing = user_by_email($email);
            if ($existing && (int) $existing['id'] !== $id) {
                flash_set('That email already has an account.', true);
            } elseif ($id) {
                db()->prepare('UPDATE users SET name = ?, email = ?, role = ?, title = ?, bio = ? WHERE id = ?')
                    ->execute([$name, $email, $role, $title, $bio, $id]);
                if ($pass !== '') {
                    if (strlen($pass) < 10) {
                        flash_set('Passphrase unchanged — it needs at least 10 characters.', true);
                        redirect('users.php');
                    }
                    db()->prepare('UPDATE users SET pass_hash = ? WHERE id = ?')
                        ->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
                }
                flash_set('Account updated.');
            } else {
                db()->prepare('INSERT INTO users (name, email, pass_hash, role, slug, title, bio, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, unique_user_slug($name), $title, $bio, now()]);
                flash_set('Account created. They can sign in at /admin/ now.');
            }
        }
    }

    if ($action === 'delete' && $id) {
        if ($id === (int) $me['id']) {
            flash_set("You can't delete the account you're signed in with.", true);
        } else {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash_set('Account deleted. Their stories stay in the archive.');
        }
    }
    redirect('users.php');
}

$editing = null;
if (isset($_GET['edit'])) {
    $editing = user_by_id((int) $_GET['edit']);
}
$users = db()->query('SELECT * FROM users ORDER BY name')->fetchAll();

admin_header('Accounts', 'users');
flash_show();
?>

<h1 class="pagetitle">Accounts</h1>
<p class="pagesub">Admins run the paper. Editors publish, review, and manage desks and the wire. Authors write and submit for review — an editor signs off before anything goes live. Accounts are shared across every site on the network database.</p>

<div class="panel">
  <table class="tbl">
    <tr><th>Name</th><th>Email</th><th>Role</th><th>Since</th><th></th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><strong><?= e($u['name']) ?></strong><?= (int) $u['id'] === (int) $me['id'] ? ' <span class="chip chip--used">you</span>' : '' ?></td>
      <td class="mono"><?= e($u['email']) ?></td>
      <td><span class="chip <?= $u['role'] === 'admin' ? 'chip--published' : ($u['role'] === 'editor' ? 'chip--scheduled' : 'chip--draft') ?>"><?= e($u['role']) ?></span></td>
      <td class="mono"><?= e(fmt_date($u['created_at'])) ?></td>
      <td style="white-space:nowrap">
        <a class="btn btn--ghost btn--small" href="users.php?edit=<?= (int) $u['id'] ?>">Edit</a>
        <?php if ((int) $u['id'] !== (int) $me['id']): ?>
        <form method="post" class="inline" onsubmit="return confirm('Delete this account?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn--danger btn--small" type="submit">Delete</button></form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="panel">
  <h2><?= $editing ? 'Edit account: ' . e($editing['name']) : 'Add an account' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <div class="formgrid">
      <div>
        <label for="name">Name · appears in bylines</label>
        <input type="text" id="name" name="name" value="<?= e($editing['name'] ?? '') ?>" required>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($editing['email'] ?? '') ?>" required>
      </div>
      <div>
        <label for="role">Role</label>
        <select id="role" name="role">
          <option value="author"<?= ($editing['role'] ?? '') === 'author' ? ' selected' : '' ?>>Author — writes and submits for review</option>
          <option value="editor"<?= ($editing['role'] ?? '') === 'editor' ? ' selected' : '' ?>>Editor — reviews and publishes</option>
          <option value="admin"<?= ($editing['role'] ?? '') === 'admin' ? ' selected' : '' ?>>Admin — everything, plus accounts and settings</option>
        </select>
        <label for="password">Passphrase<?= $editing ? ' · leave blank to keep the current one' : ' · at least 10 characters' ?></label>
        <input type="password" id="password" name="password" autocomplete="new-password">
        <label for="title">Profile title · e.g. “Agriculture reporter”</label>
        <input type="text" id="title" name="title" value="<?= e($editing['title'] ?? '') ?>">
        <label for="bio">Profile bio</label>
        <textarea id="bio" name="bio" class="prose" style="min-height:64px"><?= e($editing['bio'] ?? '') ?></textarea>
      </div>
    </div>
    <p style="margin-top:16px">
      <button class="btn" type="submit"><?= $editing ? 'Save the account' : 'Create the account' ?></button>
      <?php if ($editing): ?><a class="btn btn--ghost" href="users.php">Cancel</a><?php endif; ?>
    </p>
  </form>
</div>

<?php admin_footer(); ?>
