<?php
/** The Prairie Post — newsletter signup endpoint and page. */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot: real readers never see or fill the hidden field.
    $honeypot = (string) ($_POST['website'] ?? '');
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));

    if ($honeypot === '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            db()->prepare('INSERT INTO subscribers (site_id, email, created_at) VALUES (?, ?, ?)')
                ->execute([current_site_id(), $email, now()]);
        } catch (PDOException) {
            // Already subscribed — the confirmation reads the same either way.
        }
    }
    redirect(url('subscribe') . '?subscribed=1');
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
