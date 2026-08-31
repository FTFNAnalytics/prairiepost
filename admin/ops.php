<?php
/**
 * Operations — what the watch sees, and the cron ledger behind it.
 * Domains, certificates, job runs, the backup state, sign-in pressure.
 * Hub administrators only. The watch itself runs every five minutes;
 * this page just reads what it filed.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();
if (!pp_is_hub()) {
    redirect('index.php');
}

$ops = json_decode(setting('ops_snapshot', ''), true) ?: null;
$runs = db()->query('SELECT * FROM ops_runs ORDER BY id DESC LIMIT 60')->fetchAll();
$alertTo = setting('ops_alert_email');

admin_header('Operations', 'ops');
flash_show();
?>

<h1 class="pagetitle">Operations</h1>
<p class="pagesub">The watch patrols every five minutes: every masthead the network serves, their certificates, the cron jobs, the wire's freshness, last night's backup, and pressure on the sign-in form. Alerts go to <?= $alertTo !== '' ? '<span class="mono">' . e($alertTo) . '</span>' : 'nobody yet — set an alert address under Settings → Security' ?>, at most one email every six hours.</p>

<?php if (!$ops): ?>
<div class="panel"><p>No watch report yet. Once this release's cron is live it files one every five minutes — check back shortly, or run it by hand: <span class="mono">PP_SITE=civismedia php cron/run.php watch</span></p></div>
<?php else: ?>
<div class="panel">
  <h2>Domains · as of <?= e(fmt_date($ops['at'], 'M j, g:i a')) ?></h2>
  <table class="tbl">
    <thead><tr><th>Domain</th><th>Front page</th><th>Certificate</th></tr></thead>
    <tbody>
    <?php foreach (($ops['domains'] ?? []) as $domain => $code): $days = $ops['certs'][$domain] ?? null; ?>
      <tr>
        <td class="mono"><?= e($domain) ?></td>
        <td><span class="chip <?= $code === 200 ? 'chip--ok' : 'chip--used' ?>"><?= $code === 200 ? '200 · up' : ($code ?: 'unreachable') ?></span></td>
        <td class="mono"><?= $days === null ? '—' : ($days < 14 ? '<span class="chip chip--used">' . $days . ' days left</span>' : $days . ' days left') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="panel">
  <h2>Scheduled work</h2>
  <table class="tbl">
    <thead><tr><th>Job</th><th>Expected</th><th>Last run</th><th>State</th></tr></thead>
    <tbody>
    <?php
    $cadence = ['monitor' => 'hourly at :17', 'agents' => 'every 10 minutes', 'analytics' => 'nightly 02:43'];
    foreach (($ops['jobs'] ?? []) as $job => $st): ?>
      <tr>
        <td class="mono"><?= e($job) ?></td>
        <td><?= e($cadence[$job] ?? '') ?></td>
        <td class="mono"><?= $st['age'] !== null ? round($st['age'] / 60) . 'm ago' : 'never' ?></td>
        <td><span class="chip <?= ($st['ok'] ?? null) === true ? 'chip--ok' : 'chip--used' ?>"><?= ($st['ok'] ?? null) === true ? 'ok' : (($st['ok'] ?? null) === false ? 'failed' : 'no record') ?></span></td>
      </tr>
    <?php endforeach; ?>
    <tr>
      <td class="mono">backup</td>
      <td>nightly 03:17</td>
      <td class="mono"><?= isset($ops['backup']['age']) && $ops['backup']['age'] !== null ? round($ops['backup']['age'] / 3600, 1) . 'h ago' : '—' ?></td>
      <td><?php if (!$ops['backup']): ?><span class="chip chip--draft">not set up</span>
          <?php elseif (!empty($ops['backup']['ok'])): ?><span class="chip chip--ok">ok<?= !empty($ops['backup']['db_bytes']) ? ' · ' . round($ops['backup']['db_bytes'] / 1048576, 1) . ' MB db' : '' ?></span>
          <?php else: ?><span class="chip chip--used">FAILED — see /var/log/civis/backup.log</span><?php endif; ?></td>
    </tr>
    <tr>
      <td class="mono">wire</td>
      <td>papers fetch daily</td>
      <td class="mono">—</td>
      <td><span class="chip <?= !empty($ops['wire_fresh']) ? 'chip--ok' : 'chip--used' ?>"><?= !empty($ops['wire_fresh']) ? 'fresh' : 'stale > 26h' ?></span></td>
    </tr>
    </tbody>
  </table>
  <p class="help">Failed sign-ins in the last hour: <?= (int) ($ops['login_failures'] ?? 0) ?> — the throttle locks an account after 6 misses, an address after 20. The <a href="audit.php">audit trail</a> has the who-and-when.</p>
</div>
<?php endif; ?>

<div class="panel">
  <h2>The cron ledger · last 60 runs</h2>
  <?php if (!$runs): ?>
  <p>Empty — rows appear as jobs run through the wrapper (<span class="mono">cron/run.php</span>).</p>
  <?php else: ?>
  <table class="tbl">
    <thead><tr><th>When</th><th>Job</th><th>Outcome</th><th>Note</th></tr></thead>
    <tbody>
    <?php foreach ($runs as $r): ?>
      <tr>
        <td class="mono" style="white-space:nowrap"><?= e(fmt_date($r['started_at'], 'M j, g:i a')) ?></td>
        <td class="mono"><?= e($r['job']) ?></td>
        <td><span class="chip <?= $r['ok'] ? 'chip--ok' : 'chip--used' ?>"><?= $r['ok'] ? 'ok' : 'failed' ?></span></td>
        <td style="max-width:520px;overflow-wrap:anywhere;font-size:12.5px"><?= e($r['note']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php admin_footer(); ?>
