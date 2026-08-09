<?php
/**
 * The Prairie Post — The 6 a.m., public side.
 *   /newsletter/                the archive of past editions + signup
 *   /newsletter/2026-08-09      one edition, as it was sent
 *   /newsletter/confirm?t=…     double opt-in confirmation
 *   /newsletter/unsubscribe?t=… one click, effective immediately
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

$path = trim((string) ($_GET['path'] ?? ''), '/');

/* --- Confirm ------------------------------------------------------------- */
if ($path === 'confirm') {
    $sub = subscriber_by_token((string) ($_GET['t'] ?? ''));
    if ($sub && $sub['status'] !== 'active') {
        db()->prepare("UPDATE subscribers SET status = 'active', confirmed_at = ?, consent_note = 'double opt-in confirmed' WHERE id = ?")
            ->execute([now(), $sub['id']]);
    }
    page_header(['title' => 'Subscription confirmed']);
    echo '<div class="wrap pagehead"><h1>' . ($sub ? 'You\'re on the list' : 'That link has expired') . '</h1>';
    echo $sub
        ? '<p class="desc">The next edition of ' . e(setting('newsletter_heading', 'The 6 a.m.')) . ' lands in your inbox at 6 in the morning. Until then, the <a href="/">front page</a> is always open.</p>'
        : '<p class="desc">The confirmation link didn\'t match a pending subscription. Sign up again from the <a href="' . e(url('subscribe')) . '">newsletter page</a>.</p>';
    echo '<div class="pp-horizon"></div></div>';
    page_footer();
    exit;
}

/* --- Unsubscribe ---------------------------------------------------------- */
if ($path === 'unsubscribe') {
    $sub = subscriber_by_token((string) ($_GET['t'] ?? ''));
    if ($sub) {
        db()->prepare("UPDATE subscribers SET status = 'unsubscribed' WHERE id = ?")->execute([$sub['id']]);
    }
    page_header(['title' => 'Unsubscribed']);
    echo '<div class="wrap pagehead"><h1>Done</h1>';
    echo '<p class="desc">' . ($sub
        ? 'That address is off the list, effective now. No follow-up email, no second question. If it was a mistake, the signup form is on the <a href="/">front page</a>.'
        : 'That link didn\'t match a subscription — nothing was changed.') . '</p>';
    echo '<div class="pp-horizon"></div></div>';
    page_footer();
    exit;
}

/* --- One edition, as sent -------------------------------------------------- */
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $path)) {
    $edition = newsletter_by_date($path);
    if ($edition) {
        // The stored email HTML is a complete document; serve it as the page.
        echo str_replace('%%UNSUB%%', e(url('newsletter/')), $edition['html']);
        exit;
    }
    http_response_code(404);
    page_header(['title' => 'No edition that day']);
    echo '<div class="wrap pagehead"><h1>No edition that day</h1>';
    echo '<div class="empty">Nothing went out on ' . e($path) . '. The <a href="' . e(url('newsletter/')) . '">archive</a> lists every morning that did.</div></div>';
    page_footer();
    exit;
}

/* --- The archive ----------------------------------------------------------- */
$editions = array_filter(newsletters_recent(60), fn ($n) => $n['status'] === 'sent');

page_header([
    'title' => setting('newsletter_heading', 'The 6 a.m.') . ' — the archive',
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
  <div class="frontgrid">
    <div>
      <?php if (!$editions): ?>
      <div class="empty">No editions in the archive yet — the first one lands the morning after the newsletter is switched on.</div>
      <?php else: ?>
      <ul class="river">
        <?php foreach ($editions as $n): ?>
        <li>
          <time datetime="<?= e($n['edition_date']) ?>"><?= e(fmt_date($n['edition_date'], 'M j, Y')) ?></time>
          <a href="<?= e(url('newsletter/' . $n['edition_date'])) ?>"><?= e($n['subject']) ?></a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <aside class="rail"><?= signup_block() ?></aside>
  </div>
</div>
<?php page_footer(); ?>
