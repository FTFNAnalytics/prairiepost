<?php
/**
 * Network advertising — the control room's campaign desk.
 * A campaign is filed once: one creative, one slot, one schedule, and the
 * papers it runs on. Saving fans it out to one ads row per chosen paper,
 * so per-site serving, rotation and the served/click counters stay exactly
 * as they've always been; the campaign_id stamp ties the rows together for
 * reporting and marks them read-only on the papers' own ad pages.
 * Hub only, administrators only.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_admin();
if (!pp_is_hub()) {
    redirect('ads.php');
}

$placements = ['top' => 'Front page — top', 'rail' => 'Rail', 'article' => 'After story text'];
$kinds = ['house' => 'House ad — built from the brand', 'image' => 'Image + link', 'html' => 'Pasted embed code'];
$papers = pp_paper_sites();
$paperIds = array_map(fn ($s) => (int) $s['id'], $papers);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $campaign = $id ? campaign_by_id($id) : null;

    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $advertiser = trim((string) ($_POST['advertiser'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $placement = isset($placements[$_POST['placement'] ?? '']) ? $_POST['placement'] : 'rail';
        $kind = isset($kinds[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'house';
        $linkUrl = trim((string) ($_POST['link_url'] ?? ''));
        $image = trim((string) ($_POST['image'] ?? ''));
        $sites = array_values(array_filter(array_map('intval', (array) ($_POST['sites'] ?? [])),
            fn ($sid) => in_array($sid, $paperIds, true)));

        if (!empty($_FILES['image_file']['name'])) {
            [$uploadedUrl, $err] = pp_handle_image_upload($_FILES['image_file']);
            if ($err !== null) {
                flash_set("The creative didn't upload: $err", true);
                redirect('network-ads.php' . ($id ? '?edit=' . $id : ''));
            }
            $image = $uploadedUrl;
        }

        $parseWhen = function (string $raw): ?string {
            $raw = trim($raw);
            if ($raw === '') {
                return null;
            }
            $ts = strtotime($raw);
            return $ts ? date('Y-m-d H:i:s', $ts) : null;
        };

        // The fields every fanned-out row carries. 'enabled' stays out —
        // pause/resume is its own action and a save never un-pauses.
        $fields = [
            'name' => $name,
            'placement' => $placement,
            'kind' => $kind,
            'image' => $image,
            'link_url' => $linkUrl,
            'html' => (string) ($_POST['html'] ?? ''),
            'kicker' => trim((string) ($_POST['kicker'] ?? '')),
            'heading' => trim((string) ($_POST['heading'] ?? '')),
            'body_text' => trim((string) ($_POST['body_text'] ?? '')),
            'button_label' => trim((string) ($_POST['button_label'] ?? '')),
            'start_at' => $parseWhen((string) ($_POST['start_at'] ?? '')),
            'end_at' => $parseWhen((string) ($_POST['end_at'] ?? '')),
        ];

        if ($name === '') {
            flash_set('A campaign needs a name — it shows in this list and the papers\' ad pages, never to readers.', true);
        } elseif (!$sites) {
            flash_set('Tick at least one paper — a campaign with nowhere to run isn\'t running.', true);
        } elseif ($kind === 'image' && $image === '') {
            flash_set('An image campaign needs a creative. Upload one or give its path.', true);
        } elseif ($kind !== 'html' && $linkUrl !== '' && !preg_match('#^https?://#i', $linkUrl)) {
            flash_set('The link must start with http:// or https:// — clicks route through the counter first.', true);
        } else {
            if ($campaign) {
                db()->prepare('UPDATE campaigns SET name = ?, advertiser = ?, notes = ? WHERE id = ?')
                    ->execute([$name, $advertiser, $notes, $id]);
            } else {
                db()->prepare('INSERT INTO campaigns (name, advertiser, notes, created_at) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $advertiser, $notes, now()]);
                $id = pp_last_id('campaigns');
            }
            [$added, $updated, $removed] = pp_sync_campaign_ads($id, $sites, $fields);
            pp_audit('campaign.saved', $name, "campaign #$id, on " . count($sites) . ' paper(s)');
            $note = "Campaign saved — running on " . count($sites) . " paper(s)";
            $parts = array_filter([$added ? "$added added" : '', $updated ? "$updated updated" : '', $removed ? "$removed removed" : '']);
            flash_set($note . ($parts ? ' (' . implode(', ', $parts) . ')' : '') . '.');
        }
        redirect('network-ads.php' . ($id && $action === 'save' ? '?edit=' . $id : ''));
    }

    if ($action === 'toggle' && $campaign) {
        $any = (int) db()->query('SELECT COALESCE(MAX(enabled), 0) FROM ads WHERE campaign_id = ' . (int) $id)->fetchColumn();
        db()->prepare('UPDATE ads SET enabled = ? WHERE campaign_id = ?')->execute([$any ? 0 : 1, $id]);
        pp_audit($any ? 'campaign.paused' : 'campaign.resumed', $campaign['name'], "campaign #$id");
        flash_set($any ? 'Campaign paused on every paper.' : 'Campaign running again on every paper.');
    }
    if ($action === 'delete' && $campaign) {
        db()->prepare('DELETE FROM ads WHERE campaign_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM campaigns WHERE id = ?')->execute([$id]);
        pp_audit('campaign.deleted', $campaign['name'], "campaign #$id, rows and history removed");
        flash_set('Campaign deleted — its rows and their served/click history are gone from every paper.');
    }
    redirect('network-ads.php');
}

$editing = null;
$editingRows = [];
$editingSites = [];
if (isset($_GET['edit'])) {
    $editing = campaign_by_id((int) $_GET['edit']);
    if ($editing) {
        $editingRows = campaign_ads((int) $editing['id']);
        $editingSites = array_map(fn ($r) => (int) $r['site_id'], $editingRows);
    }
}
// The form prefills from one fanned-out row — they're identical by construction.
$ex = $editingRows[0] ?? null;
$campaigns = campaigns_with_stats();

admin_header('Network advertising', 'ads');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle">Network advertising</h1>
</div>
<p class="pagesub">One campaign, filed once, running on any set of papers. Every slot stays labelled
“Advertisement”, clicks still route through the counter, and the numbers below are the sum of the
real per-paper counts. The papers' own ad pages keep working for local sales; campaign rows show
there as read-only.</p>

<div class="panel">
  <h2>Campaigns</h2>
  <table class="tbl">
    <tr><th>Campaign</th><th>Slot</th><th>Kind</th><th>Schedule</th><th>Papers</th><th>Served / clicks · CTR</th><th>Status</th><th></th></tr>
    <?php foreach ($campaigns as $c): ?>
    <?php
      $live = $c['papers'] > 0 && ad_is_live(['enabled' => (int) $c['any_enabled'], 'start_at' => $c['start_at'], 'end_at' => $c['end_at']]);
      $ctr = $c['impressions'] > 0 ? number_format($c['clicks'] / $c['impressions'] * 100, 1) . '%' : '—';
    ?>
    <tr style="<?= $c['any_enabled'] ? '' : 'opacity:.55' ?>">
      <td>
        <a class="rowtitle" href="network-ads.php?edit=<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a>
        <?php if ($c['advertiser'] !== ''): ?><div class="mono"><?= e($c['advertiser']) ?></div><?php endif; ?>
      </td>
      <td class="mono"><?= e($placements[$c['placement']] ?? (string) $c['placement']) ?></td>
      <td class="mono"><?= e((string) $c['kind']) ?></td>
      <td class="mono"><?= $c['start_at'] || $c['end_at']
          ? e((fmt_date($c['start_at'], 'M j') ?: '…') . ' — ' . (fmt_date($c['end_at'], 'M j') ?: '…'))
          : 'always' ?></td>
      <td class="mono"><?= (int) $c['papers'] ?></td>
      <td class="mono"><?= (int) $c['impressions'] ?> / <?= (int) $c['clicks'] ?> · <?= $ctr ?></td>
      <td><span class="chip <?= $live ? 'chip--ok' : 'chip--used' ?>"><?= $live ? 'live' : ($c['any_enabled'] ? 'outside schedule' : ($c['papers'] ? 'paused' : 'no papers')) ?></span></td>
      <td style="white-space:nowrap">
        <a class="btn btn--ghost btn--small" href="network-ads.php?edit=<?= (int) $c['id'] ?>">Edit</a>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn--ghost btn--small" type="submit"><?= $c['any_enabled'] ? 'Pause all' : 'Resume all' ?></button></form>
        <form method="post" class="inline" onsubmit="return confirm('Delete this campaign? Its rows disappear from every paper and the served/click history goes with them.')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn--danger btn--small" type="submit">Delete</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (!$campaigns): ?><p>No campaigns yet. Build one below — a house ad needs nothing but the words.</p><?php endif; ?>
</div>

<?php if ($editing && $editingRows): ?>
<div class="panel">
  <h2>By paper — <?= e($editing['name']) ?></h2>
  <table class="tbl">
    <tr><th>Paper</th><th>Served</th><th>Clicks</th><th>CTR</th><th>Status</th></tr>
    <?php foreach ($editingRows as $r): ?>
    <tr>
      <td><?= e($r['site_name']) ?> <span class="mono"><?= e($r['site_slug']) ?></span></td>
      <td class="mono"><?= (int) $r['impressions'] ?></td>
      <td class="mono"><?= (int) $r['clicks'] ?></td>
      <td class="mono"><?= $r['impressions'] > 0 ? number_format($r['clicks'] / $r['impressions'] * 100, 1) . '%' : '—' ?></td>
      <td><span class="chip <?= ad_is_live($r) ? 'chip--ok' : 'chip--used' ?>"><?= ad_is_live($r) ? 'live' : ($r['enabled'] ? 'outside schedule' : 'paused') ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="panel">
  <h2><?= $editing ? 'Edit campaign: ' . e($editing['name']) : 'File a campaign' ?></h2>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <div class="formgrid">
      <div>
        <label for="name">Campaign name · internal only</label>
        <input type="text" id="name" name="name" value="<?= e($editing['name'] ?? '') ?>" required>
        <label for="advertiser">Advertiser</label>
        <input type="text" id="advertiser" name="advertiser" value="<?= e($editing['advertiser'] ?? '') ?>" placeholder="Who's paying for the run">
        <label for="placement">Slot</label>
        <select id="placement" name="placement">
          <?php foreach ($placements as $key => $label): ?>
          <option value="<?= $key ?>"<?= ($ex['placement'] ?? 'rail') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <label for="kind">Kind</label>
        <select id="kind" name="kind">
          <?php foreach ($kinds as $key => $label): ?>
          <option value="<?= $key ?>"<?= ($ex['kind'] ?? 'house') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <label for="link_url">Link · where a click lands</label>
        <input type="url" id="link_url" name="link_url" value="<?= e($ex['link_url'] ?? '') ?>" placeholder="https://advertiser.example.com">
        <label for="start_at">Runs from</label>
        <input type="datetime-local" id="start_at" name="start_at" value="<?= !empty($ex['start_at']) ? e(date('Y-m-d\TH:i', strtotime($ex['start_at']))) : '' ?>">
        <label for="end_at">Until</label>
        <input type="datetime-local" id="end_at" name="end_at" value="<?= !empty($ex['end_at']) ? e(date('Y-m-d\TH:i', strtotime($ex['end_at']))) : '' ?>">
        <label for="notes">Notes · rate, contact, the paper trail</label>
        <textarea id="notes" name="notes" class="prose" style="min-height:64px"><?= e((string) ($editing['notes'] ?? '')) ?></textarea>
      </div>
      <div>
        <label>Runs on</label>
        <?php foreach ($papers as $s): ?>
        <label style="display:flex;align-items:center;gap:8px;text-transform:none;margin:6px 0">
          <input type="checkbox" name="sites[]" value="<?= (int) $s['id'] ?>" style="width:auto;min-height:0"<?= $editing ? (in_array((int) $s['id'], $editingSites, true) ? ' checked' : '') : ' checked' ?>>
          <?= e($s['name']) ?>
        </label>
        <?php endforeach; ?>
        <p class="help">Unticking a paper removes its row — and its served/click counts leave the totals. Pausing keeps everything.</p>

        <label>House ad · the brand does the design</label>
        <input type="text" name="kicker" value="<?= e($ex['kicker'] ?? '') ?>" placeholder="Kicker — e.g. “From our advertisers”">
        <input type="text" name="heading" value="<?= e($ex['heading'] ?? '') ?>" placeholder="Heading" style="margin-top:8px">
        <input type="text" name="body_text" value="<?= e($ex['body_text'] ?? '') ?>" placeholder="One sentence of body copy" style="margin-top:8px">
        <input type="text" name="button_label" value="<?= e($ex['button_label'] ?? '') ?>" placeholder="Button — name the outcome" style="margin-top:8px">

        <label for="image">Image ad · creative</label>
        <input type="text" id="image" name="image" value="<?= e($ex['image'] ?? '') ?>" placeholder="/uploads/2026/08/creative.png">
        <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif" style="margin-top:8px">
        <p class="help">Creatives upload to the hub's shared /uploads — see the deploy note on shared uploads if a paper's slot shows a broken image.</p>

        <label for="html">Embed ad · pasted code</label>
        <textarea id="html" name="html" class="mono"><?= e((string) ($ex['html'] ?? '')) ?></textarea>
        <p class="help">Only the fields for the chosen kind are used; the others can stay empty.</p>
      </div>
    </div>
    <p style="margin-top:16px">
      <button class="btn" type="submit"><?= $editing ? 'Save the campaign' : 'File the campaign' ?></button>
      <?php if ($editing): ?><a class="btn btn--ghost" href="network-ads.php">Cancel</a><?php endif; ?>
    </p>
  </form>
</div>

<?php admin_footer(); ?>
