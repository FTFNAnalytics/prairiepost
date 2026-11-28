<?php
/** Newsroom accounts. Admins run the paper; editors file and publish stories. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$me = require_network_admin();
$paperSites = pp_paper_sites();
$siteById = array_column($paperSites, null, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = in_array($_POST['role'] ?? '', ['admin', 'editor', 'author'], true) ? $_POST['role'] : 'author';
        $isNetAdmin = !empty($_POST['is_network_admin']) ? 1 : 0;
        if ($id === (int) $me['id']) {
            $isNetAdmin = 1; // prevent locking yourself out of network administration
        }
        if ($isNetAdmin === 1) {
            $role = 'admin';
        }
        $pass = (string) ($_POST['password'] ?? '');
        $title = trim((string) ($_POST['title'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));

        $parsedMemberships = [];
        if ($isNetAdmin === 0 && is_array($_POST['memberships'] ?? null)) {
            foreach ($_POST['memberships'] as $sid => $cfg) {
                $sid = (int) $sid;
                if (!isset($siteById[$sid]) || !is_array($cfg)) {
                    continue;
                }
                $mRole = (string) ($cfg['role'] ?? '');
                if (!in_array($mRole, ['admin', 'editor', 'author'], true)) {
                    continue;
                }
                $parsedMemberships[$sid] = [
                    'role' => $mRole,
                    'can_publish' => !empty($cfg['can_publish']) ? 1 : 0,
                ];
            }
        }

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('An account needs a name and a working email address.', true);
        } elseif (!$id && strlen($pass) < 10) {
            flash_set('A new account needs a passphrase of at least 10 characters.', true);
        } else {
            $existing = user_by_email($email);
            if ($existing && (int) $existing['id'] !== $id) {
                flash_set('That email already has an account.', true);
            } elseif ($id) {
                $before = user_by_id($id);
                db()->prepare('UPDATE users SET name = ?, email = ?, role = ?, is_network_admin = ?, title = ?, bio = ? WHERE id = ?')
                    ->execute([$name, $email, $role, $isNetAdmin, $title, $bio, $id]);
                if ($isNetAdmin === 1) {
                    pp_set_user_memberships($id, []);
                } elseif (isset($_POST['memberships'])) {
                    pp_set_user_memberships($id, $parsedMemberships);
                }
                $notes = [];
                if ($before && $before['role'] !== $role) {
                    $notes[] = 'role ' . $before['role'] . ' → ' . $role;
                }
                if ($before && (int) ($before['is_network_admin'] ?? 0) !== $isNetAdmin) {
                    $notes[] = $isNetAdmin ? 'network admin granted' : 'network admin revoked';
                }
                if ($isNetAdmin === 0 && isset($_POST['memberships'])) {
                    $notes[] = count($parsedMemberships) ? count($parsedMemberships) . ' paper membership(s)' : 'unscoped';
                }
                if ($pass !== '') {
                    if (strlen($pass) < 10) {
                        flash_set('Passphrase unchanged — it needs at least 10 characters.', true);
                        redirect('users.php');
                    }
                    // The reset also signs the account out everywhere —
                    // that's usually why an admin is resetting it.
                    db()->prepare('UPDATE users SET pass_hash = ?, session_epoch = session_epoch + 1 WHERE id = ?')
                        ->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
                    if ($id === (int) $me['id']) {
                        $mine = user_by_id($id);
                        $_SESSION['epoch'] = (int) ($mine['session_epoch'] ?? 0);
                    }
                    $notes[] = 'passphrase reset, sessions revoked';
                }
                pp_audit('account.updated', $email, implode(', ', $notes));
                flash_set('Account updated.');
            } else {
                db()->prepare('INSERT INTO users (name, email, pass_hash, role, is_network_admin, slug, title, bio, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, $isNetAdmin, unique_user_slug($name), $title, $bio, now()]);
                $newId = pp_last_id('users');
                if ($isNetAdmin === 0 && $parsedMemberships) {
                    pp_set_user_memberships($newId, $parsedMemberships);
                }
                pp_audit('account.created', $email, "role $role" . ($isNetAdmin ? ' (network admin)' : ($parsedMemberships ? ' (' . count($parsedMemberships) . ' paper(s))' : '')));
                flash_set('Account created. They can sign in at /admin/ now.');
            }
        }
    }

    if ($action === 'revoke' && $id) {
        $target = user_by_id($id);
        if ($target) {
            db()->prepare('UPDATE users SET session_epoch = session_epoch + 1 WHERE id = ?')->execute([$id]);
            if ($id === (int) $me['id']) {
                $mine = user_by_id($id);
                $_SESSION['epoch'] = (int) ($mine['session_epoch'] ?? 0);
                flash_set('Your other sessions are out; this one stays.');
            } else {
                flash_set('Done — ' . $target['name'] . ' is signed out everywhere. Their passphrase still works.');
            }
            pp_audit('account.revoked', $target['email'], 'all sessions signed out');
        }
    }

    if ($action === 'delete' && $id) {
        if ($id === (int) $me['id']) {
            flash_set("You can't delete the account you're signed in with.", true);
        } else {
            $gone = user_by_id($id);
            db()->prepare('DELETE FROM site_memberships WHERE user_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            pp_audit('account.deleted', (string) ($gone['email'] ?? "user #$id"), $gone ? 'role ' . $gone['role'] : '');
            flash_set('Account deleted. Their stories stay in the archive.');
        }
    }
    redirect('users.php');
}

$editing = null;
$editingMemberships = [];
if (isset($_GET['edit'])) {
    $editing = user_by_id((int) $_GET['edit']);
    if ($editing) {
        $editingMemberships = pp_user_memberships((int) $editing['id'], true);
    }
}
$users = db()->query('SELECT * FROM users ORDER BY name')->fetchAll();

admin_header('Accounts', 'users');
flash_show();
?>

<h1 class="pagetitle">Accounts</h1>
<p class="pagesub">Network admins run the whole fleet and manage accounts. Assign per-paper memberships below to restrict a local editor or reporter to specific papers only; leave all papers blank for legacy network-wide role behavior.</p>

<div class="panel">
  <table class="tbl">
    <tr><th>Name</th><th>Email</th><th>Role</th><th>Scope</th><th>Since</th><th></th></tr>
    <?php foreach ($users as $u):
      $uMem = pp_user_memberships((int) $u['id']);
      $uNet = pp_is_network_admin($u);
    ?>
    <tr>
      <td><strong><?= e($u['name']) ?></strong><?= (int) $u['id'] === (int) $me['id'] ? ' <span class="chip chip--used">you</span>' : '' ?></td>
      <td class="mono"><?= e($u['email']) ?></td>
      <td><span class="chip <?= $u['role'] === 'admin' ? 'chip--published' : ($u['role'] === 'editor' ? 'chip--scheduled' : 'chip--draft') ?>"><?= e($u['role']) ?></span></td>
      <td>
        <?php if ($uNet): ?>
          <span class="chip chip--published">Network admin</span>
        <?php elseif (!$uMem): ?>
          <span class="chip chip--used">All papers</span>
        <?php else: foreach ($uMem as $sid => $m): if (isset($siteById[$sid])): ?>
          <span class="chip chip--ok"><?= e($siteById[$sid]['name']) ?> (<?= e($m['role']) ?><?= $m['can_publish'] ? '' : ', no publish' ?>)</span>
        <?php endif; endforeach; endif; ?>
      </td>
      <td class="mono"><?= e(fmt_date($u['created_at'])) ?></td>
      <td style="white-space:nowrap">
        <a class="btn btn--ghost btn--small" href="users.php?edit=<?= (int) $u['id'] ?>">Edit</a>
        <form method="post" class="inline" onsubmit="return confirm('Sign this account out of every browser and device? The passphrase keeps working.')"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn--ghost btn--small" type="submit">Sign out</button></form>
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
        <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;margin-top:14px">
          <input type="checkbox" name="is_network_admin" value="1" style="width:auto"<?= $editing && pp_is_network_admin($editing) ? ' checked' : '' ?>>
          Network administrator · full access to all papers and the control room
        </label>
      </div>
      <div>
        <label for="role">Default role (when unscoped)</label>
        <select id="role" name="role">
          <option value="author"<?= ($editing['role'] ?? '') === 'author' ? ' selected' : '' ?>>Author — writes and submits for review</option>
          <option value="editor"<?= ($editing['role'] ?? '') === 'editor' ? ' selected' : '' ?>>Editor — reviews and publishes</option>
          <option value="admin"<?= ($editing['role'] ?? '') === 'admin' ? ' selected' : '' ?>>Admin — everything, plus settings</option>
        </select>
        <label for="password">Passphrase<?= $editing ? ' · leave blank to keep the current one' : ' · at least 10 characters' ?></label>
        <input type="password" id="password" name="password" autocomplete="new-password">
        <label for="title">Profile title · e.g. “Agriculture reporter”</label>
        <input type="text" id="title" name="title" value="<?= e($editing['title'] ?? '') ?>">
        <label for="bio">Profile bio</label>
        <textarea id="bio" name="bio" class="prose" style="min-height:64px"><?= e($editing['bio'] ?? '') ?></textarea>
      </div>
    </div>

    <?php if ($paperSites): ?>
    <div style="margin-top:18px">
      <label>Per-paper access · assign one or more papers to restrict this account to those papers only</label>
      <table class="tbl" style="margin-top:8px">
        <tr><th>Paper</th><th>Role on paper</th><th>Can publish</th></tr>
        <?php foreach ($paperSites as $ps):
          $sid = (int) $ps['id'];
          $m = $editingMemberships[$sid] ?? null;
          $mRole = (string) ($m['role'] ?? '');
          $mPub = $m ? (int) $m['can_publish'] : 0;
        ?>
        <tr>
          <td><?= e($ps['name']) ?></td>
          <td>
            <select name="memberships[<?= $sid ?>][role]" aria-label="Role for <?= e($ps['name']) ?>">
              <option value=""<?= $mRole === '' ? ' selected' : '' ?>>— No access —</option>
              <option value="author"<?= $mRole === 'author' ? ' selected' : '' ?>>Author</option>
              <option value="editor"<?= $mRole === 'editor' ? ' selected' : '' ?>>Editor</option>
              <option value="admin"<?= $mRole === 'admin' ? ' selected' : '' ?>>Paper admin</option>
            </select>
          </td>
          <td>
            <input type="checkbox" name="memberships[<?= $sid ?>][can_publish]" value="1" style="width:auto" aria-label="Can publish on <?= e($ps['name']) ?>"<?= $mPub ? ' checked' : '' ?>>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <p class="help">Ignored when <em>Network administrator</em> is ticked. Once at least one paper is assigned, this account is refused access to every other paper and to the hub.</p>
    </div>
    <?php endif; ?>

    <p style="margin-top:16px">
      <button class="btn" type="submit"><?= $editing ? 'Save the account' : 'Create the account' ?></button>
      <?php if ($editing): ?><a class="btn btn--ghost" href="users.php">Cancel</a><?php endif; ?>
    </p>
  </form>
</div>

<?php admin_footer(); ?>
