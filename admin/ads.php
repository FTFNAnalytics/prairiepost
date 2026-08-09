<?php
/** Advertising: the three slots, the creatives that rotate through them. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
require_editor();

$placements = ['top' => 'Front page — top', 'rail' => 'Rail', 'article' => 'After story text'];
$kinds = ['house' => 'House ad — built from the brand', 'image' => 'Image + link', 'html' => 'Pasted embed code'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $existing = $id ? ad_by_id($id) : null;

    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $placement = isset($placements[$_POST['placement'] ?? '']) ? $_POST['placement'] : 'rail';
        $kind = isset($kinds[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'house';
        $linkUrl = trim((string) ($_POST['link_url'] ?? ''));
        $image = trim((string) ($_POST['image'] ?? ''));

        if (!empty($_FILES['image_file']['name'])) {
            [$uploadedUrl, $err] = pp_handle_image_upload($_FILES['image_file']);
            if ($err !== null) {
                flash_set("The creative didn't upload: $err", true);
                redirect('ads.php' . ($id ? '?edit=' . $id : ''));
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
            flash_set('An ad needs a name — it only shows in this list, never to readers.', true);
        } elseif ($kind === 'image' && $image === '') {
            flash_set('An image ad needs a creative. Upload one or give its path.', true);
        } elseif ($kind !== 'html' && $linkUrl !== '' && !preg_match('#^https?://#i', $linkUrl)) {
            flash_set('The link must start with http:// or https:// — clicks route through the counter first.', true);
        } elseif ($existing) {
            $set = implode(', ', array_map(fn ($k) => "$k = ?", array_keys($fields)));
            db()->prepare("UPDATE ads SET $set WHERE id = ? AND site_id = ?")
                ->execute([...array_values($fields), $id, current_site_id()]);
            flash_set('Ad saved.');
        } else {
            $fields['site_id'] = current_site_id();
            $fields['created_at'] = now();
            $cols = implode(', ', array_keys($fields));
            $marks = implode(', ', array_fill(0, count($fields), '?'));
            db()->prepare("INSERT INTO ads ($cols) VALUES ($marks)")->execute(array_values($fields));
            flash_set('Ad created. It starts rotating in its slot immediately (or at its start time).');
        }
    }
    if ($action === 'toggle' && $existing) {
        db()->prepare('UPDATE ads SET enabled = 1 - enabled WHERE id = ?')->execute([$id]);
    }
    if ($action === 'delete' && $existing) {
        db()->prepare('DELETE FROM ads WHERE id = ?')->execute([$id]);
        flash_set('Ad deleted.');
    }
    redirect('ads.php');
}

$editing = isset($_GET['edit']) ? ad_by_id((int) $_GET['edit']) : null;
$ads = ads_all();

admin_header('Advertising', 'ads');
flash_show();
?>

<h1 class="pagetitle">Advertising</h1>
<p class="pagesub">Three slots: top of the front page, the rail, and after story text. Several ads in one slot rotate evenly. Every slot is labelled “Advertisement” — the design guide's honesty rule — and clicks route through a counter so the numbers below are real. House ads are set in the brand and need no artwork.</p>

<div class="panel">
  <table class="tbl">
    <tr><th>Ad</th><th>Slot</th><th>Kind</th><th>Schedule</th><th>Served / clicks</th><th>Status</th><th></th></tr>
    <?php foreach ($ads as $ad): ?>
    <tr style="<?= $ad['enabled'] ? '' : 'opacity:.55' ?>">
      <td><strong><?= e($ad['name']) ?></strong>
        <?php if ($ad['link_url']): ?><div class="mono" style="color:#5A6A5C;word-break:break-all"><?= e($ad['link_url']) ?></div><?php endif; ?></td>
      <td class="mono"><?= e($placements[$ad['placement']] ?? $ad['placement']) ?></td>
      <td class="mono"><?= e($ad['kind']) ?></td>
      <td class="mono"><?= $ad['start_at'] || $ad['end_at']
          ? e((fmt_date($ad['start_at'], 'M j') ?: '…') . ' — ' . (fmt_date($ad['end_at'], 'M j') ?: '…'))
          : 'always' ?></td>
      <td class="mono"><?= (int) $ad['impressions'] ?> / <?= (int) $ad['clicks'] ?></td>
      <td><span class="chip <?= ad_is_live($ad) ? 'chip--ok' : 'chip--used' ?>"><?= ad_is_live($ad) ? 'live' : ($ad['enabled'] ? 'outside schedule' : 'paused') ?></span></td>
      <td style="white-space:nowrap">
        <a class="btn btn--ghost btn--small" href="ads.php?edit=<?= (int) $ad['id'] ?>">Edit</a>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $ad['id'] ?>"><button class="btn btn--ghost btn--small" type="submit"><?= $ad['enabled'] ? 'Pause' : 'Resume' ?></button></form>
        <form method="post" class="inline" onsubmit="return confirm('Delete this ad?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $ad['id'] ?>"><button class="btn btn--danger btn--small" type="submit">Delete</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (!$ads): ?><p>No ads yet. Build one below — a house ad needs nothing but the words.</p><?php endif; ?>
</div>

<div class="panel">
  <h2><?= $editing ? 'Edit ad: ' . e($editing['name']) : 'Add an ad' ?></h2>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <div class="formgrid">
      <div>
        <label for="name">Name · internal only</label>
        <input type="text" id="name" name="name" value="<?= e($editing['name'] ?? '') ?>" required>
        <label for="placement">Slot</label>
        <select id="placement" name="placement">
          <?php foreach ($placements as $key => $label): ?>
          <option value="<?= $key ?>"<?= ($editing['placement'] ?? 'rail') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <label for="kind">Kind</label>
        <select id="kind" name="kind">
          <?php foreach ($kinds as $key => $label): ?>
          <option value="<?= $key ?>"<?= ($editing['kind'] ?? 'house') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <label for="link_url">Link · where a click lands</label>
        <input type="url" id="link_url" name="link_url" value="<?= e($editing['link_url'] ?? '') ?>" placeholder="https://advertiser.example.com">
        <label for="start_at">Runs from</label>
        <input type="datetime-local" id="start_at" name="start_at" value="<?= !empty($editing['start_at']) ? e(date('Y-m-d\TH:i', strtotime($editing['start_at']))) : '' ?>">
        <label for="end_at">Until</label>
        <input type="datetime-local" id="end_at" name="end_at" value="<?= !empty($editing['end_at']) ? e(date('Y-m-d\TH:i', strtotime($editing['end_at']))) : '' ?>">
        <p class="help">Leave both blank to run always.</p>
      </div>
      <div>
        <label>House ad · the brand does the design</label>
        <input type="text" name="kicker" value="<?= e($editing['kicker'] ?? '') ?>" placeholder="Kicker — e.g. “From our advertisers”">
        <input type="text" name="heading" value="<?= e($editing['heading'] ?? '') ?>" placeholder="Heading" style="margin-top:8px">
        <input type="text" name="body_text" value="<?= e($editing['body_text'] ?? '') ?>" placeholder="One sentence of body copy" style="margin-top:8px">
        <input type="text" name="button_label" value="<?= e($editing['button_label'] ?? '') ?>" placeholder="Button — name the outcome, e.g. “Book a table”" style="margin-top:8px">

        <label for="image">Image ad · creative</label>
        <input type="text" id="image" name="image" value="<?= e($editing['image'] ?? '') ?>" placeholder="/uploads/2026/08/creative.png">
        <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif" style="margin-top:8px">

        <label for="html">Embed ad · pasted code</label>
        <textarea id="html" name="html" class="mono"><?= e((string) ($editing['html'] ?? '')) ?></textarea>
        <p class="help">Only the fields for the chosen kind are used; the others can stay empty.</p>
      </div>
    </div>
    <p style="margin-top:16px">
      <button class="btn" type="submit"><?= $editing ? 'Save the ad' : 'Add the ad' ?></button>
      <?php if ($editing): ?><a class="btn btn--ghost" href="ads.php">Cancel</a><?php endif; ?>
    </p>
  </form>
</div>

<?php admin_footer(); ?>
