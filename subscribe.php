<?php
/** The Prairie Dispatch — newsletter signup endpoint and page. */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

require __DIR__ . '/app/newsletter.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot: real readers never see or fill the hidden field.
    $honeypot = (string) ($_POST['website'] ?? '');
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $confirming = false;

    if ($honeypot === '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = db()->prepare('SELECT * FROM subscribers WHERE site_id = ? AND email = ?');
        $stmt->execute([current_site_id(), $email]);
        $existing = $stmt->fetch();

        // With mail configured, CASL-grade double opt-in; without it, the
        // form's submission is recorded as the consent.
        $doubleOptIn = pp_mail_configured();

        if ($existing) {
            if ($existing['status'] !== 'active') {
                if ($doubleOptIn) {
                    db()->prepare("UPDATE subscribers SET status = 'pending' WHERE id = ?")->execute([$existing['id']]);
                    pp_send_confirmation($existing);
                    $confirming = true;
                } else {
                    db()->prepare("UPDATE subscribers SET status = 'active', consent_note = 'web form (re-subscribed)' WHERE id = ?")
                        ->execute([$existing['id']]);
                }
            }
        } else {
            $token = bin2hex(random_bytes(16));
            $status = $doubleOptIn ? 'pending' : 'active';
            $note = $doubleOptIn ? 'web form, confirmation email sent' : 'web form (single opt-in — mail not configured)';
            db()->prepare('INSERT INTO subscribers (site_id, email, status, token, consent_note, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([current_site_id(), $email, $status, $token, $note, now()]);
            if ($doubleOptIn) {
                pp_send_confirmation(['email' => $email, 'token' => $token]);
                $confirming = true;
            }
        }
    }
    redirect(url('subscribe') . '?subscribed=1' . ($confirming ? '&confirm=1' : ''));
}

page_header([
    'title' => setting('newsletter_heading', 'The 6 a.m.'),
    'description' => setting('newsletter_copy'),
]);
?>

<div class="pagehead wrap">
  <span class="pp-meta" style="color:#5A6A5C">Newsletter</span>
  <h1><?= e(setting('newsletter_heading', 'The 6 a.m.')) ?></h1>
  <p class="desc"><?= e(setting('newsletter_copy')) ?></p>
  <div class="pp-horizon"></div>
</div>

<div class="archive wrap">
  <div style="max-width:420px"><?= signup_block() ?></div>
  <p class="pp-meta" style="margin-top:22px;color:#5A6A5C">One email a day. Unsubscribe any time by replying with the word “stop”.</p>
</div>

<?php page_footer(); ?>
