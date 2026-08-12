<?php
/**
 * The audit trail — who did what, where, when, from which address.
 * Append-only: this page filters and reads, it cannot edit or erase.
 * Hub administrators only; the cron prunes entries after ~13 months.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();
if (!pp_is_hub()) {
    redirect('index.php');
}

$fAction = trim((string) ($_GET['faction'] ?? ''));
$fUser   = trim((string) ($_GET['fuser'] ?? ''));
$fDays   = max(0, (int) ($_GET['fdays'] ?? 0));

$where = [];
$args = [];
if ($fAction !== '') {
    $where[] = 'action ' . pp_like() . ' ?';
    $args[] = $fAction . '%';
}
if ($fUser !== '') {
    $where[] = 'user_name ' . pp_like() . ' ?';
    $args[] = '%' . $fUser . '%';
}
if ($fDays > 0) {
    $where[] = 'created_at > ?';
    $args[] = date('Y-m-d H:i:s', time() - $fDays * 86400);
}
$sql = 'SELECT * FROM audit_log'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY id DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

$actions = db()->query('SELECT DISTINCT action FROM audit_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

$siteNames = [];
foreach (db()->query('SELECT id, name FROM sites') as $s) {
    $siteNames[(int) $s['id']] = $s['name'];
}

admin_header('Audit trail', 'audit');
flash_show();
?>

<h1 class="pagetitle">Audit trail</h1>
<p class="pagesub">Sign-ins and the actions that shape the network — settings, campaigns, syndication, agent approvals, accounts. Append-only; nothing here can be edited or removed. Sign-in <em>attempts</em>, including failures, live in their own ledger that feeds the throttle.</p>

<form method="get" class="panel" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
  <div>
    <label for="faction">Action</label>
    <select id="faction" name="faction">
      <option value="">All actions</option>
      <?php foreach ($actions as $a): ?>
      <option value="<?= e($a) ?>"<?= $a === $fAction ? ' selected' : '' ?>><?= e($a) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="fuser">Person</label>
    <input type="text" id="fuser" name="fuser" value="<?= e($fUser) ?>" placeholder="name contains…" style="max-width:180px">
  </div>
  <div>
    <label for="fdays">Window</label>
    <select id="fdays" name="fdays">
      <option value="0">All kept history</option>
      <?php foreach ([1 => 'Past day', 7 => 'Past week', 31 => 'Past month', 92 => 'Past quarter'] as $d => $label): ?>
      <option value="<?= $d ?>"<?= $d === $fDays ? ' selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div><button class="btn btn--ghost" type="submit">Filter</button></div>
</form>

<div class="panel">
  <?php if (!$rows): ?>
  <p>Nothing recorded<?= ($fAction !== '' || $fUser !== '' || $fDays > 0) ? ' under those filters' : ' yet — entries appear as people sign in and work' ?>.</p>
  <?php else: ?>
  <table class="tbl">
    <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Target</th><th>Detail</th><th>Site</th><th>Address</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="mono" style="white-space:nowrap"><?= e(fmt_date($r['created_at'], 'M j, g:i a')) ?></td>
        <td><?= $r['user_name'] !== '' ? e($r['user_name']) : '<span style="color:var(--muted)">—</span>' ?></td>
        <td class="mono"><?= e($r['action']) ?></td>
        <td><?= e($r['target']) ?></td>
        <td style="max-width:340px;overflow-wrap:anywhere"><?= e((string) $r['detail']) ?></td>
        <td><?= e($siteNames[(int) $r['site_id']] ?? ('#' . (int) $r['site_id'])) ?></td>
        <td class="mono"><?= e($r['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (count($rows) === 200): ?><p class="help" style="margin-top:8px">Showing the 200 most recent matches — narrow the filters to reach further back.</p><?php endif; ?>
  <?php endif; ?>
</div>

<?php admin_footer(); ?>
