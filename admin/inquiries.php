<?php
/**
 * Inquiries — what the brochure's contact form brought in.
 * New ones first; mark them handled once someone has written back.
 * Hub only: the papers have tips lines, the practice has this.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_editor();
if (!pp_is_hub()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM inquiries WHERE id = ? AND site_id = ?');
    $stmt->execute([$id, current_site_id()]);
    $inq = $stmt->fetch();

    if ($action === 'toggle' && $inq) {
        $next = $inq['status'] === 'handled' ? 'new' : 'handled';
        db()->prepare('UPDATE inquiries SET status = ? WHERE id = ?')->execute([$next, $id]);
    }
    if ($action === 'delete' && $inq) {
        db()->prepare('DELETE FROM inquiries WHERE id = ?')->execute([$id]);
        flash_set('Inquiry deleted.');
    }
    redirect('inquiries.php');
}

$stmt = db()->prepare('SELECT * FROM inquiries WHERE site_id = ? ORDER BY id DESC LIMIT 200');
$stmt->execute([current_site_id()]);
$inquiries = $stmt->fetchAll();
$open = count(array_filter($inquiries, fn ($i) => $i['status'] === 'new'));

admin_header('Inquiries', 'inquiries');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle">Inquiries</h1>
  <?php if ($open): ?><span class="chip chip--scheduled"><?= $open ?> waiting</span><?php endif; ?>
</div>
<p class="pagesub">Everything the contact form on <?= e(setting('site_title', 'Civis Media')) ?> brought in.
Write back from your own mailbox, then mark it handled here so nobody answers twice.</p>

<div class="panel">
  <?php if (!$inquiries): ?>
  <p>Nothing yet. When someone writes through the front page, it lands here<?= setting('contact_email') !== '' ? ' and a copy goes to ' . e(setting('contact_email')) : '' ?>.</p>
  <?php endif; ?>
  <?php foreach ($inquiries as $inq): ?>
  <div class="newsitem<?= $inq['status'] === 'handled' ? ' is-used' : '' ?>">
    <div class="t">
      <strong><?= e($inq['name']) ?></strong>
      <?php if ($inq['organization'] !== ''): ?> · <?= e($inq['organization']) ?><?php endif; ?>
      · <a href="mailto:<?= e($inq['email']) ?>"><?= e($inq['email']) ?></a>
      <span class="src"><?= e(fmt_date($inq['created_at'], 'M j, Y g:i a')) ?></span>
      <span class="src" style="display:block;margin-top:6px;color:inherit"><?= nl2br(e((string) $inq['message'])) ?></span>
    </div>
    <div class="acts">
      <?php if ($inq['status'] === 'handled'): ?><span class="chip chip--ok">handled</span>
      <?php else: ?><span class="chip chip--scheduled">new</span><?php endif; ?>
      <form method="post" class="inline"><?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $inq['id'] ?>">
        <button class="btn btn--ghost btn--small" type="submit"><?= $inq['status'] === 'handled' ? 'Reopen' : 'Mark handled' ?></button>
      </form>
      <form method="post" class="inline" onsubmit="return confirm('Delete this inquiry for good?')"><?= csrf_field() ?>
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $inq['id'] ?>">
        <button class="btn btn--danger btn--small" type="submit">Delete</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php admin_footer(); ?>
