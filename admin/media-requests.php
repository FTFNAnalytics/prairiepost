<?php
/** Pantheon submissions desk — human disposition for all three media lanes. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_editor();
if (!pp_is_hub()) {
    http_response_code(404);
    exit('Not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $note = trim((string) ($_POST['note'] ?? ''));
    try {
        if ($action === 'review') {
            pp_media_review_state($id, 'in_review', $note, $user['name']);
            flash_set('Request marked in review.');
        } elseif ($action === 'changes') {
            if ($note === '') {
                throw new RuntimeException('Say what needs to change.');
            }
            pp_media_review_state($id, 'changes_requested', $note, $user['name']);
            flash_set('Changes requested; Pantheon will see the note on refresh.');
        } elseif ($action === 'decline') {
            if ($note === '') {
                throw new RuntimeException('A decline needs a reason.');
            }
            pp_media_review_state($id, 'declined', $note, $user['name']);
            flash_set('Request declined.');
        } elseif ($action === 'accept_editorial') {
            $count = pp_media_accept_editorial($id, $user['name']);
            flash_set("Accepted into {$count} paper story-idea queue(s). Nothing was published.");
        } elseif ($action === 'quote') {
            if ($user['role'] !== 'admin') {
                throw new RuntimeException('Only an administrator can issue a commercial quote.');
            }
            pp_media_quote_request(
                $id,
                (float) ($_POST['amount'] ?? -1),
                trim((string) ($_POST['valid_until'] ?? '')),
                $note,
                $user['name'],
            );
            flash_set('Quote issued. It is not an order and nothing is live.');
        } else {
            throw new RuntimeException('Unknown media review action.');
        }
        pp_audit('media.' . $action, "request #{$id}", $note);
    } catch (Throwable $e) {
        flash_set($e->getMessage(), true);
    }
    redirect('media-requests.php?id=' . $id);
}

$state = (string) ($_GET['state'] ?? 'open');
$kind = (string) ($_GET['kind'] ?? '');
$selected = isset($_GET['id']) ? pp_media_request_full((int) $_GET['id']) : null;
$requests = pp_media_requests_list($state, $kind);
admin_header('Pantheon submissions', 'media');
flash_show();
?>

<div class="headrow"><div><h1 class="pagetitle">Pantheon submissions</h1><p class="pagesub">Requests, not instructions. Editorial pitches can become story ideas; paid lanes can be reviewed or quoted. Nothing here publishes, launches, or spends.</p></div></div>

<div class="panel">
  <div class="formrow">
    <?php foreach (['open' => 'Open', 'received' => 'Received', 'quoted' => 'Quoted', 'accepted' => 'Accepted', 'declined' => 'Declined', 'cancelled' => 'Cancelled', 'all' => 'All'] as $key => $label): ?>
      <a class="btn btn--small <?= $state === $key ? 'btn--sky' : 'btn--ghost' ?>" href="media-requests.php?state=<?= e($key) ?><?= $kind ? '&kind=' . e($kind) : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <span style="margin-left:auto"></span>
    <?php foreach (['' => 'All lanes', 'editorial_pitch' => 'Editorial', 'sponsored_post' => 'Sponsored', 'display_ad' => 'Display'] as $key => $label): ?>
      <a class="btn btn--small <?= $kind === $key ? 'btn--outline' : 'btn--ghost' ?>" href="media-requests.php?state=<?= e($state) ?>&kind=<?= e($key) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <table class="tbl" style="margin-top:14px">
    <tr><th>Request</th><th>Lane</th><th>Properties</th><th>Ceiling</th><th>Status</th><th>Updated</th></tr>
    <?php foreach ($requests as $request): ?>
      <tr>
        <td><a class="rowtitle" href="media-requests.php?id=<?= (int) $request['id'] ?>&state=<?= e($state) ?>&kind=<?= e($kind) ?>"><?= e($request['title']) ?></a><div class="mono"><?= e($request['public_ref']) ?> · <?= e($request['client_name']) ?></div></td>
        <td><?= e(str_replace('_', ' ', $request['request_kind'])) ?></td>
        <td class="mono"><?= (int) $request['properties'] ?></td>
        <td class="mono"><?= $request['max_budget'] === null ? '—' : e($request['currency']) . ' ' . number_format((float) $request['max_budget'], 2) ?></td>
        <td><span class="chip <?= in_array($request['state'], ['declined', 'cancelled', 'failed'], true) ? 'chip--error' : 'chip--used' ?>"><?= e(str_replace('_', ' ', $request['state'])) ?></span></td>
        <td class="mono"><?= e(fmt_date($request['updated_at'], 'M j, g:i a')) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php if (!$requests): ?><p>No submissions in this view.</p><?php endif; ?>
</div>

<?php if ($selected):
  $content = (array) json_decode((string) ($selected['content_json'] ?: '{}'), true);
  $target = (array) json_decode((string) ($selected['target_json'] ?: '{}'), true);
?>
<div class="panel">
  <div class="headrow"><div><h2><?= e($selected['title']) ?></h2><div class="mono"><?= e($selected['public_ref']) ?> · Pantheon brief <?= e($selected['pantheon_brief_id']) ?></div></div><span class="chip chip--used"><?= e(str_replace('_', ' ', $selected['state'])) ?></span></div>
  <div class="formgrid" style="margin-top:12px">
    <div><p><strong>Advertiser</strong><br><?= e($selected['advertiser'] ?: 'earned editorial') ?></p><p><strong>Sponsor</strong><br><?= e($selected['sponsor'] ?: '—') ?></p><p><strong>Disclosure</strong><br><?= e($selected['disclosure'] ?: '—') ?></p><p><strong>Window</strong><br><?= e($selected['desired_start'] ?: 'open') ?> → <?= e($selected['desired_end'] ?: 'open') ?></p></div>
    <div><p><strong>Target</strong></p><pre class="mono" style="white-space:pre-wrap"><?= e(json_encode($target, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre><p><strong>Landing page</strong><br><?php if ($selected['landing_url']): ?><a href="<?= e($selected['landing_url']) ?>" target="_blank" rel="noopener"><?= e($selected['landing_url']) ?></a><?php else: ?>—<?php endif; ?></p></div>
  </div>
  <h3>Submitted content</h3><pre class="mono" style="white-space:pre-wrap;max-height:420px;overflow:auto"><?= e(json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
  <h3>Requested properties</h3><table class="tbl"><tr><th>Paper</th><th>Product</th><th>Cap</th></tr><?php foreach ($selected['sites'] as $site): ?><tr><td><?= e($site['site_name']) ?><div class="mono"><?= e($site['site_slug']) ?></div></td><td><?= e($site['product_ref']) ?></td><td class="mono"><?= $site['budget_cap'] === null ? '—' : number_format((float) $site['budget_cap'], 2) ?></td></tr><?php endforeach; ?></table>

  <?php if (!in_array($selected['state'], ['accepted', 'declined', 'cancelled', 'fulfilled'], true)): ?>
  <div class="formgrid" style="margin-top:18px">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $selected['id'] ?>"><label for="review-note">Review note</label><textarea id="review-note" name="note"><?= e((string) $selected['review_note']) ?></textarea><div class="formrow" style="margin-top:8px"><button class="btn btn--ghost" name="action" value="review">Mark in review</button><button class="btn btn--outline" name="action" value="changes">Request changes</button><button class="btn btn--danger" name="action" value="decline">Decline</button></div><?php if ($selected['request_kind'] === 'editorial_pitch'): ?><button class="btn" name="action" value="accept_editorial" style="margin-top:12px">Accept into story-idea desks</button><p class="help">Creates ideas only. No post and no publication.</p><?php endif; ?></form>
    <?php if ($selected['request_kind'] !== 'editorial_pitch' && $user['role'] === 'admin'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $selected['id'] ?>"><label>Quote amount · <?= e($selected['currency']) ?><input type="number" name="amount" min="0" max="<?= e((string) ($selected['max_budget'] ?? '')) ?>" step="0.01" required></label><label>Valid until<input type="date" name="valid_until" required></label><label>Terms / note<textarea name="note"></textarea></label><button class="btn" name="action" value="quote">Issue quote</button><p class="help">A quote is not an order. Pantheon must separately approve any paid booking.</p></form><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($selected['outputs']): ?><h3>Outputs</h3><table class="tbl"><tr><th>Kind</th><th>Paper</th><th>ID</th></tr><?php foreach ($selected['outputs'] as $output): ?><tr><td><?= e($output['output_kind']) ?></td><td><?= e($output['site_name'] ?: 'network') ?></td><td class="mono"><?= (int) $output['output_id'] ?></td></tr><?php endforeach; ?></table><?php endif; ?>
  <h3>History</h3><table class="tbl"><tr><th>When</th><th>Event</th><th>Actor</th><th>Detail</th></tr><?php foreach ($selected['events'] as $event): ?><tr><td class="mono"><?= e(fmt_date($event['created_at'], 'M j, g:i a')) ?></td><td><?= e($event['event_type']) ?></td><td><?= e($event['actor']) ?></td><td class="mono"><?= e($event['payload'] ?: '') ?></td></tr><?php endforeach; ?></table>
</div>
<?php endif; ?>

<?php admin_footer(); ?>
