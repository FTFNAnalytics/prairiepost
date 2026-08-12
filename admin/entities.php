<?php
/**
 * The entity directory — the names the linkifier knows: politicians and
 * organizations with bio URLs, admin-curated. Seed it from the Represent
 * API (tools/import-represent.php) so it starts full; prune and correct
 * here. Hub only, admins.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_admin();
if (!pp_is_hub()) {
    http_response_code(404);
    exit('Not found.');
}

$kinds = ['politician' => 'Politician', 'organization' => 'Organization', 'place' => 'Place', 'other' => 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $url  = trim((string) ($_POST['url'] ?? ''));
        if ($name === '') {
            flash_set('An entity needs a name.', true);
            redirect('entities.php');
        }
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            flash_set('The bio URL must start with http:// or https:// (or stay blank until there is one).', true);
            redirect('entities.php' . ($id ? '?edit=' . $id : ''));
        }
        $aliases = array_values(array_filter(array_map('trim', preg_split('/\R+/', (string) ($_POST['aliases'] ?? '')) ?: [])));
        $fields = [
            'name' => mb_substr($name, 0, 160),
            'kind' => isset($kinds[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'other',
            'url' => mb_substr($url, 0, 600),
            'aliases' => json_encode($aliases, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        if ($id) {
            db()->prepare('UPDATE entities SET name = ?, kind = ?, url = ?, aliases = ? WHERE id = ?')
                ->execute([...array_values($fields), $id]);
            flash_set('Entity saved.');
        } else {
            $slug = slugify($name);
            $probe = db()->prepare('SELECT 1 FROM entities WHERE slug = ?');
            $probe->execute([$slug]);
            if ($probe->fetch()) {
                $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 4);
            }
            db()->prepare('INSERT INTO entities (name, slug, kind, url, aliases, enabled, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)')
                ->execute([$fields['name'], $slug, $fields['kind'], $fields['url'], $fields['aliases'], now()]);
            flash_set('Entity added — the linkifier sees it on its next pass.');
        }
    }
    if ($action === 'toggle' && $id) {
        db()->prepare('UPDATE entities SET enabled = 1 - enabled WHERE id = ?')->execute([$id]);
    }
    if ($action === 'delete' && $id) {
        db()->prepare('DELETE FROM entities WHERE id = ?')->execute([$id]);
        flash_set('Entity removed. Links already approved into stories stay as written.');
    }
    redirect('entities.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 50;
$where = '1=1';
$params = [];
if ($q !== '') {
    $op = pp_like();
    $where = "(name $op ? OR aliases $op ?)";
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    $params = [$like, $like];
}
$stmt = db()->prepare("SELECT COUNT(*) n FROM entities WHERE $where");
$stmt->execute($params);
$total = (int) $stmt->fetch()['n'];
$stmt = db()->prepare("SELECT * FROM entities WHERE $where ORDER BY name LIMIT $per OFFSET " . (($page - 1) * $per));
$stmt->execute($params);
$rows = $stmt->fetchAll();
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM entities WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

admin_header('Entities', 'entities');
flash_show();
?>

<h1 class="pagetitle">The entity directory</h1>
<p class="pagesub"><?= number_format($total) ?> name<?= $total === 1 ? '' : 's' ?> the linkifier knows. Seed elected officials in bulk from the box: <span class="mono">PP_SITE=civismedia php tools/import-represent.php</span> — then curate here: fix URLs, add aliases (one per line — nicknames, honorifics, maiden names), pause what shouldn't link.</p>

<div class="panel">
  <form method="get" class="formrow">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search names and aliases…" aria-label="Search" style="min-width:260px">
    <button class="btn btn--ghost" type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="btn btn--ghost" href="entities.php">Clear</a><?php endif; ?>
  </form>
  <table class="tbl" style="margin-top:12px">
    <tr><th>Name</th><th>Kind</th><th>Bio URL</th><th>Aliases</th><th>Status</th><th></th></tr>
    <?php foreach ($rows as $ent): $aliases = (array) json_decode((string) $ent['aliases'], true); ?>
    <tr style="<?= $ent['enabled'] ? '' : 'opacity:.55' ?>">
      <td><strong><?= e($ent['name']) ?></strong></td>
      <td class="mono"><?= e($kinds[$ent['kind']] ?? $ent['kind']) ?></td>
      <td class="mono" style="word-break:break-all;max-width:300px"><?= $ent['url'] ? '<a href="' . e($ent['url']) . '" target="_blank" rel="noopener">' . e($ent['url']) . '</a>' : '—' ?></td>
      <td><?= e(implode(' · ', $aliases)) ?: '—' ?></td>
      <td><span class="chip <?= $ent['enabled'] ? 'chip--ok' : 'chip--used' ?>"><?= $ent['enabled'] ? 'linking' : 'paused' ?></span></td>
      <td style="white-space:nowrap">
        <a class="btn btn--ghost btn--small" href="entities.php?edit=<?= (int) $ent['id'] ?><?= $q !== '' ? '&amp;q=' . urlencode($q) : '' ?>">Edit</a>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $ent['id'] ?>"><button class="btn btn--ghost btn--small" type="submit"><?= $ent['enabled'] ? 'Pause' : 'Resume' ?></button></form>
        <form method="post" class="inline" onsubmit="return confirm('Remove this entity? Approved links in stories stay.')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $ent['id'] ?>"><button class="btn btn--danger btn--small" type="submit">Delete</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (!$rows): ?><p><?= $q !== '' ? 'No matches.' : 'Empty — run the import, or add the first name below.' ?></p><?php endif; ?>
  <?php if ($total > $per): ?>
  <p style="margin-top:12px">
    <?php for ($i = 1; $i <= min(30, (int) ceil($total / $per)); $i++): ?>
      <?php if ($i === $page): ?><span class="chip chip--used"><?= $i ?></span>
      <?php else: ?><a class="btn btn--ghost btn--small" href="entities.php?page=<?= $i ?><?= $q !== '' ? '&amp;q=' . urlencode($q) : '' ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </p>
  <?php endif; ?>
</div>

<div class="panel">
  <h2><?= $editing ? 'Edit: ' . e($editing['name']) : 'Add an entity' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <div class="formgrid">
      <div>
        <label for="name">Name · as it appears in copy</label>
        <input type="text" id="name" name="name" value="<?= e($editing['name'] ?? '') ?>" required>
        <label for="kind">Kind</label>
        <select id="kind" name="kind">
          <?php foreach ($kinds as $k => $v): ?>
          <option value="<?= $k ?>"<?= ($editing['kind'] ?? 'politician') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
        <label for="url">Bio URL · where the link lands</label>
        <input type="url" id="url" name="url" value="<?= e($editing['url'] ?? '') ?>" placeholder="https://…">
      </div>
      <div>
        <label for="aliases">Aliases · one per line</label>
        <textarea id="aliases" name="aliases" style="min-height:120px" placeholder="Hon. Dana Rowe&#10;Mayor Rowe"><?= e(implode("\n", (array) json_decode((string) ($editing['aliases'] ?? '[]'), true))) ?></textarea>
        <p class="help">The linkifier matches whole words, longest alias first, and links each entity once per story — outside headings and existing links.</p>
      </div>
    </div>
    <p style="margin-top:16px">
      <button class="btn" type="submit"><?= $editing ? 'Save' : 'Add' ?></button>
      <?php if ($editing): ?><a class="btn btn--ghost" href="entities.php">Cancel</a><?php endif; ?>
    </p>
  </form>
</div>

<?php admin_footer(); ?>
