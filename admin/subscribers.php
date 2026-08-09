<?php
/** The 6 a.m. list: subscribers, with CSV export for the mail tool. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
require_editor();

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="prairiepost-subscribers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'subscribed_at']);
    $stmt = db()->prepare('SELECT email, created_at FROM subscribers WHERE site_id = ? ORDER BY created_at');
    $stmt->execute([current_site_id()]);
    foreach ($stmt as $row) {
        fputcsv($out, [$row['email'], $row['created_at']]);
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete' && ($id = (int) ($_POST['id'] ?? 0))) {
        db()->prepare('DELETE FROM subscribers WHERE id = ?')->execute([$id]);
        flash_set('Removed from the list.');
    }
    redirect('subscribers.php');
}

$cnt = db()->prepare('SELECT COUNT(*) AS n FROM subscribers WHERE site_id = ?');
$cnt->execute([current_site_id()]);
$total = (int) $cnt->fetch()['n'];
$list = db()->prepare('SELECT * FROM subscribers WHERE site_id = ? ORDER BY created_at DESC LIMIT 200');
$list->execute([current_site_id()]);
$subs = $list->fetchAll();

admin_header('Subscribers', 'subscribers');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle">The 6 a.m. list</h1>
  <a class="btn" href="subscribers.php?export=csv">Download the list as CSV</a>
</div>
<p class="pagesub"><?= $total ?> subscriber<?= $total === 1 ? '' : 's' ?>. The signup form feeds this list; sending the newsletter itself is a job for your mail tool — export the CSV and import it there.</p>

<div class="panel">
  <?php if (!$subs): ?>
  <p>Nobody on the list yet. The signup block is on the front page rail and at /subscribe.</p>
  <?php else: ?>
  <table class="tbl">
    <tr><th>Email</th><th>Signed up</th><th></th></tr>
    <?php foreach ($subs as $s): ?>
    <tr>
      <td class="mono"><?= e($s['email']) ?></td>
      <td class="mono"><?= e(fmt_date($s['created_at'], 'M j, Y g:i a')) ?></td>
      <td><form method="post" class="inline" onsubmit="return confirm('Remove this address?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn--danger btn--small" type="submit">Remove</button></form></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if ($total > 200): ?><p class="help">Showing the newest 200 — the CSV export has everyone.</p><?php endif; ?>
  <?php endif; ?>
</div>

<?php admin_footer(); ?>
