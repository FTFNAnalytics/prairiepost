<?php
/** The 6 a.m. — settings, preview, test send, and the send log. */
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/newsletter.php';
require __DIR__ . '/_layout.php';
$user = require_editor();

if (isset($_GET['preview'])) {
    [, $html] = pp_compile_edition();
    echo str_replace('%%UNSUB%%', '#unsubscribe-link-goes-here', $html);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'settings') {
        foreach (['smtp_host', 'smtp_port', 'smtp_user', 'mail_from', 'mail_from_name', 'paper_address'] as $key) {
            if (isset($_POST[$key])) {
                set_setting($key, trim((string) $_POST[$key]));
            }
        }
        if (($_POST['smtp_pass'] ?? '') !== '') {
            set_setting('smtp_pass', (string) $_POST['smtp_pass']);
        }
        $secure = (string) ($_POST['smtp_secure'] ?? '');
        set_setting('smtp_secure', in_array($secure, ['tls', 'ssl', 'none'], true) ? $secure : 'tls');
        set_setting('newsletter_enabled', isset($_POST['newsletter_enabled']) ? '1' : '0');
        $hour = max(0, min(23, (int) ($_POST['newsletter_send_hour'] ?? 6)));
        set_setting('newsletter_send_hour', (string) $hour);
        flash_set('Newsletter settings saved.');
    }

    if ($action === 'test') {
        $result = pp_send_edition(false, true, $user['email']);
        flash_set($result, str_contains($result, 'failed'));
    }

    if ($action === 'send_now') {
        $result = pp_send_edition(isset($_POST['force']));
        flash_set($result, str_contains($result, 'failed'));
    }

    redirect('newsletter.php');
}

$counts = [
    'active'       => 0,
    'pending'      => 0,
    'unsubscribed' => 0,
];
$stmt = db()->prepare('SELECT status, COUNT(*) AS n FROM subscribers WHERE site_id = ? GROUP BY status');
$stmt->execute([current_site_id()]);
foreach ($stmt as $row) {
    $counts[$row['status']] = (int) $row['n'];
}
$editions = newsletters_recent(20);
$enabled = setting('newsletter_enabled', '0') === '1';
$configured = pp_mail_configured();

admin_header('The 6 a.m.', 'newsletter');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle"><?= e(setting('newsletter_heading', 'The 6 a.m.')) ?></h1>
  <div>
    <a class="btn btn--ghost" href="newsletter.php?preview=1" target="_blank">Preview this morning's edition</a>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="test"><button class="btn btn--sky" type="submit">Send me a test</button></form>
  </div>
</div>
<p class="pagesub">Compiled from the last 24 hours — hero first, then by desk, with the forecast and closing prices. The cron job sends it once a day after the set hour; nothing goes out twice.</p>

<div class="stats">
  <div class="stat"><div class="n"><?= $counts['active'] ?></div><span class="k">Active</span></div>
  <div class="stat"><div class="n"><?= $counts['pending'] ?></div><span class="k">Awaiting confirm</span></div>
  <div class="stat"><div class="n"><?= $counts['unsubscribed'] ?></div><span class="k">Unsubscribed</span></div>
  <div class="stat"><div class="n"><?= $enabled ? 'On' : 'Off' ?></div><span class="k">Daily send</span></div>
  <div class="stat"><div class="n"><?= $configured ? 'Set' : '—' ?></div><span class="k">Mail config</span></div>
</div>

<?php if (!$configured): ?>
<div class="flash flash--error">Mail isn't configured yet, so nothing can send and signups fall back to single opt-in. Fill in the SMTP details below — your host's control panel lists them under Email.</div>
<?php endif; ?>

<div class="panel">
  <h2>Mail &amp; schedule</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="settings">
    <div class="formgrid">
      <div>
        <label for="smtp_host">SMTP host</label>
        <input type="text" id="smtp_host" name="smtp_host" value="<?= e(setting('smtp_host')) ?>" placeholder="smtp.hostinger.com">
        <label for="smtp_port">Port</label>
        <input type="text" id="smtp_port" name="smtp_port" value="<?= e(setting('smtp_port', '587')) ?>">
        <label for="smtp_secure">Encryption</label>
        <select id="smtp_secure" name="smtp_secure">
          <option value="tls"<?= setting('smtp_secure') === 'tls' ? ' selected' : '' ?>>STARTTLS (port 587)</option>
          <option value="ssl"<?= setting('smtp_secure') === 'ssl' ? ' selected' : '' ?>>SSL (port 465)</option>
          <option value="none"<?= setting('smtp_secure') === 'none' ? ' selected' : '' ?>>None (local relay only)</option>
        </select>
        <label for="smtp_user">SMTP user</label>
        <input type="text" id="smtp_user" name="smtp_user" value="<?= e(setting('smtp_user')) ?>" autocomplete="off">
        <label for="smtp_pass">SMTP password · leave blank to keep the current one</label>
        <input type="password" id="smtp_pass" name="smtp_pass" value="" autocomplete="new-password">
      </div>
      <div>
        <label for="mail_from">From address</label>
        <input type="email" id="mail_from" name="mail_from" value="<?= e(setting('mail_from')) ?>" placeholder="sixam@prairiedispatch.com">
        <label for="mail_from_name">From name</label>
        <input type="text" id="mail_from_name" name="mail_from_name" value="<?= e(setting('mail_from_name', setting('site_title'))) ?>">
        <label for="paper_address">The paper's mailing address · required by anti-spam law (CASL)</label>
        <textarea id="paper_address" name="paper_address" class="prose" style="min-height:64px" placeholder="The Prairie Dispatch, Box 100, Three Hills, AB T0M 2A0"><?= e(setting('paper_address')) ?></textarea>
        <label for="newsletter_send_hour">Send after · o'clock, site time</label>
        <input type="text" id="newsletter_send_hour" name="newsletter_send_hour" value="<?= e(setting('newsletter_send_hour', '6')) ?>">
        <label style="display:flex;align-items:center;gap:8px;margin-top:16px;text-transform:none;letter-spacing:.04em">
          <input type="checkbox" name="newsletter_enabled" style="width:auto"<?= $enabled ? ' checked' : '' ?>>
          Send daily (the cron job does the sending — schedule it for the send hour)
        </label>
      </div>
    </div>
    <p style="margin-top:16px"><button class="btn" type="submit">Save the settings</button></p>
  </form>
  <p class="help">Deliverability lives and dies on the domain's SPF and DKIM records — set them in your DNS for the From address's domain, or the editions land in spam.</p>
</div>

<div class="panel">
  <h2>Send log</h2>
  <?php if (!$editions): ?>
  <p>No editions yet. Preview today's, test it on yourself, then either switch on the daily send or send one now.</p>
  <?php else: ?>
  <table class="tbl">
    <tr><th>Edition</th><th>Subject</th><th>Recipients</th><th>Status</th></tr>
    <?php foreach ($editions as $n): ?>
    <tr>
      <td class="mono"><a href="/newsletter/<?= e($n['edition_date']) ?>" target="_blank"><?= e($n['edition_date']) ?></a></td>
      <td><?= e($n['subject']) ?></td>
      <td class="mono"><?= (int) $n['recipients'] ?></td>
      <td><span class="chip <?= $n['status'] === 'sent' ? 'chip--ok' : 'chip--error' ?>"><?= e($n['status']) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <form method="post" style="margin-top:16px" onsubmit="return confirm('Send this morning\'s edition to every active subscriber now?')">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="send_now">
    <div class="formrow">
      <button class="btn" type="submit">Send this morning's edition now</button>
      <label style="display:flex;align-items:center;gap:8px;margin:0;text-transform:none;letter-spacing:.04em">
        <input type="checkbox" name="force" style="width:auto"> Send even if no stories ran in the last 24 hours
      </label>
    </div>
  </form>
</div>

<?php admin_footer(); ?>
