<?php
/**
 * Civis Media — the brochure's contact form.
 * POSTs land in the inquiries table (Control room → Inquiries) and, when a
 * contact address is configured, a copy goes out by mail. Honeypot plus a
 * per-connection rate limit keep the bots to a murmur; a tripped honeypot
 * gets the success screen and no row.
 */
require __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/#contact');
}

// The honeypot: a real reader never sees the field, a bot fills it in.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    redirect('/?sent=1#contact');
}

$name    = trim((string) ($_POST['name'] ?? ''));
$email   = trim((string) ($_POST['email'] ?? ''));
$org     = trim((string) ($_POST['organization'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

if ($name === '' || $email === '' || $message === '') {
    redirect('/?err=missing#contact');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect('/?err=email#contact');
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$since = date('Y-m-d H:i:s', strtotime('-1 hour'));
$stmt = db()->prepare('SELECT COUNT(*) AS n FROM inquiries WHERE ip = ? AND created_at > ?');
$stmt->execute([$ip, $since]);
if ((int) $stmt->fetch()['n'] >= 5) {
    redirect('/?err=rate#contact');
}

db()->prepare('INSERT INTO inquiries (site_id, name, email, organization, message, status, ip, created_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([current_site_id(), mb_substr($name, 0, 120), mb_substr($email, 0, 191),
               mb_substr($org, 0, 160), mb_substr($message, 0, 4000), 'new', mb_substr($ip, 0, 64), now()]);

// A copy by mail when the address is set; the row is already safe either way.
$to = setting('contact_email');
if ($to !== '') {
    require_once PP_ROOT . '/app/mailer.php';
    $html = '<p><strong>' . e($name) . '</strong>'
          . ($org !== '' ? ' · ' . e($org) : '') . ' &lt;' . e($email) . '&gt;</p>'
          . '<p>' . nl2br(e($message)) . '</p>'
          . '<p style="color:#5C6672">Filed from the ' . e(setting('site_title', 'Civis Media'))
          . ' contact form — the full list is in the control room under Inquiries.</p>';
    pp_send_mail($to, 'New inquiry — ' . $name, $html);
}

redirect('/?sent=1#contact');
