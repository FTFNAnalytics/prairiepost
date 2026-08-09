<?php
/** Wire sources: the feeds behind the morning pull, with a per-feed test. */
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/fetch.php';
require __DIR__ . '/_layout.php';
require_login();

$regions = setting_json('regions', ['local' => 'Local']);

if (isset($_GET['fetch'])) {
    if ($_GET['fetch'] === 'all') {
        $report = pp_fetch_all(db());
        $added = array_sum(array_column($report, 'added'));
        $errors = array_filter($report, fn ($r) => $r['error'] !== null);
        $msg = "Fetched every enabled feed: $added new item" . ($added === 1 ? '' : 's') . '.';
        if ($errors) {
            $msg .= ' Problems: ' . implode('; ', array_map(fn ($r) => $r['name'] . ' — ' . $r['error'], $errors)) . '.';
        }
        flash_set($msg, (bool) $errors);
    } else {
        $stmt = db()->prepare('SELECT * FROM sources WHERE id = ?');
        $stmt->execute([(int) $_GET['fetch']]);
        if ($source = $stmt->fetch()) {
            [$added, $err] = pp_fetch_source(db(), $source);
            flash_set($err
                ? $source['name'] . " didn't respond with a feed: $err. Check the URL, or the publisher may be blocking automated readers."
                : $source['name'] . " is working — $added new item" . ($added === 1 ? '' : 's') . ' fetched.', (bool) $err);
        }
    }
    redirect('sources.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $feedUrl = trim((string) ($_POST['url'] ?? ''));
        $region = (string) ($_POST['region'] ?? '');
        if ($name === '' || !filter_var($feedUrl, FILTER_VALIDATE_URL) || !isset($regions[$region])) {
            flash_set('A source needs a name, a valid feed URL, and a region.', true);
        } elseif ($id) {
            db()->prepare('UPDATE sources SET name = ?, url = ?, region = ? WHERE id = ?')
                ->execute([$name, $feedUrl, $region, $id]);
            flash_set('Source updated. Test it to confirm the feed answers.');
        } else {
            db()->prepare('INSERT INTO sources (name, url, region, enabled) VALUES (?, ?, ?, 1)')
                ->execute([$name, $feedUrl, $region]);
            flash_set('Source added. Test it to confirm the feed answers.');
        }
    }
    if ($action === 'toggle' && $id) {
        db()->prepare('UPDATE sources SET enabled = 1 - enabled WHERE id = ?')->execute([$id]);
    }
    if ($action === 'delete' && $id) {
        db()->prepare('DELETE FROM sources WHERE id = ?')->execute([$id]);
        db()->prepare('DELETE FROM news_items WHERE source_id = ? AND used = 0')->execute([$id]);
        flash_set('Source deleted. Its unused wire items were cleared too.');
    }
    redirect('sources.php');
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM sources WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

admin_header('Sources', 'sources');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle">Wire sources</h1>
  <a class="btn" href="sources.php?fetch=all">Fetch all feeds now</a>
</div>
<p class="pagesub">The feeds behind the morning pull. The cron job fetches them daily; the buttons here fetch on demand. Some publishers block automated readers — the test says so plainly when they do.</p>

<div class="panel">
  <table class="tbl">
    <tr><th>Source</th><th>Region</th><th>Last fetch</th><th>Status</th><th></th></tr>
    <?php foreach (sources_all() as $s): ?>
    <tr style="<?= $s['enabled'] ? '' : 'opacity:.55' ?>">
      <td><strong><?= e($s['name']) ?></strong><div class="mono" style="color:#5A6A5C;word-break:break-all"><?= e($s['url']) ?></div></td>
      <td class="mono"><?= e($regions[$s['region']] ?? $s['region']) ?></td>
      <td class="mono"><?= e(fmt_date($s['last_fetched_at'], 'M j, g:i a') ?: '—') ?></td>
      <td><?php if ($s['last_status']): ?><span class="chip <?= str_starts_with($s['last_status'], 'ok') ? 'chip--ok' : 'chip--error' ?>"><?= e(str_starts_with($s['last_status'], 'ok') ? 'ok' : 'error') ?></span>
        <div class="mono" style="color:#5A6A5C"><?= e($s['last_status']) ?></div><?php else: ?>—<?php endif; ?></td>
      <td style="white-space:nowrap">
        <a class="btn btn--sky btn--small" href="sources.php?fetch=<?= (int) $s['id'] ?>">Test</a>
        <a class="btn btn--ghost btn--small" href="sources.php?edit=<?= (int) $s['id'] ?>">Edit</a>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn--ghost btn--small" type="submit"><?= $s['enabled'] ? 'Pause' : 'Resume' ?></button></form>
        <form method="post" class="inline" onsubmit="return confirm('Delete this source?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn--danger btn--small" type="submit">Delete</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="panel">
  <h2><?= $editing ? 'Edit source: ' . e($editing['name']) : 'Add a source' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <div class="formgrid">
      <div>
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= e($editing['name'] ?? '') ?>" required>
        <label for="url">Feed URL · RSS or Atom</label>
        <input type="url" id="url" name="url" value="<?= e($editing['url'] ?? '') ?>" required placeholder="https://example.com/feed/">
      </div>
      <div>
        <label for="region">Region tab</label>
        <select id="region" name="region">
          <?php foreach ($regions as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= ($editing['region'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="help">Region tabs are defined in Settings. Changing a region key later orphans that region's fetched items — name them carefully once.</p>
      </div>
    </div>
    <p style="margin-top:16px">
      <button class="btn" type="submit"><?= $editing ? 'Save the source' : 'Add the source' ?></button>
      <?php if ($editing): ?><a class="btn btn--ghost" href="sources.php">Cancel</a><?php endif; ?>
    </p>
  </form>
</div>

<?php admin_footer(); ?>
