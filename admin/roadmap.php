<?php
/**
 * The roadmap — the project's living document, kept in the database so the
 * whole newsroom works from one copy. Phases carry a status, questions get
 * answered in place, notes collect everything else. Seeded once from
 * PLAN-CIVIS.md; after that, this page is the source of truth. Hub only.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_login();
if (!pp_is_hub()) {
    redirect('index.php');
}
$editor = is_editor($user);

$kinds = ['phase' => 'Phase', 'question' => 'Question', 'note' => 'Note'];
$phaseStatuses = ['planned' => 'Planned', 'in_progress' => 'In progress', 'done' => 'Done'];
$chip = fn (string $s) => ['planned' => 'used', 'in_progress' => 'scheduled', 'done' => 'ok',
                           'open' => 'scheduled', 'answered' => 'ok'][$s] ?? 'used';

$fetchItem = function (int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM roadmap_items WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$editor) {
        http_response_code(403);
        exit('Editors and administrators keep the roadmap; everyone reads it.');
    }
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $item = $id ? $fetchItem($id) : null;

    if ($action === 'status' && $item) {
        $status = (string) ($_POST['status'] ?? '');
        $valid = $item['kind'] === 'phase' ? array_keys($phaseStatuses)
               : ($item['kind'] === 'question' ? ['open', 'answered'] : ['']);
        if (in_array($status, $valid, true)) {
            db()->prepare('UPDATE roadmap_items SET status = ?, updated_by = ?, updated_at = ? WHERE id = ?')
                ->execute([$status, $user['name'], now(), $id]);
            flash_set('Marked "' . $item['title'] . '" ' . str_replace('_', ' ', $status) . '.');
        }
    }

    if ($action === 'save' && $item) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($title === '') {
            flash_set('An item keeps its name — the title can\'t be empty.', true);
            redirect('roadmap.php?edit=' . $id);
        }
        db()->prepare('UPDATE roadmap_items SET title = ?, body = ?, sort = ?, updated_by = ?, updated_at = ? WHERE id = ?')
            ->execute([mb_substr($title, 0, 255), $body, (int) ($_POST['sort'] ?? $item['sort']), $user['name'], now(), $id]);
        flash_set('Saved.');
    }

    if ($action === 'add') {
        $kind = isset($kinds[$_POST['kind'] ?? '']) ? (string) $_POST['kind'] : 'note';
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash_set('Give the new item a title.', true);
        } else {
            $status = $kind === 'phase' ? 'planned' : ($kind === 'question' ? 'open' : '');
            $sort = 0;
            if ($kind === 'phase') {
                $sort = 1 + (int) db()->query("SELECT COALESCE(MAX(sort), 0) AS m FROM roadmap_items WHERE kind = 'phase'")->fetch()['m'];
            }
            db()->prepare('INSERT INTO roadmap_items (kind, title, body, status, sort, updated_by, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$kind, mb_substr($title, 0, 255), trim((string) ($_POST['body'] ?? '')), $status, $sort,
                           $user['name'], now(), now()]);
            flash_set(($kinds[$kind]) . ' added to the roadmap.');
        }
    }

    if ($action === 'delete' && $item) {
        if ($user['role'] !== 'admin') {
            flash_set('Only an administrator removes an item outright — mark it done or answered instead.', true);
        } else {
            db()->prepare('DELETE FROM roadmap_items WHERE id = ?')->execute([$id]);
            flash_set('Removed from the roadmap.');
        }
    }

    redirect('roadmap.php');
}

$editing = (int) ($_GET['edit'] ?? 0);
$items = db()->query('SELECT * FROM roadmap_items ORDER BY sort, id')->fetchAll();
$phases = array_values(array_filter($items, fn ($i) => $i['kind'] === 'phase'));
$questions = array_values(array_filter($items, fn ($i) => $i['kind'] === 'question'));
usort($questions, fn ($a, $b) => [$a['status'] !== 'open', $a['id']] <=> [$b['status'] !== 'open', $b['id']]);
$notes = array_values(array_filter($items, fn ($i) => $i['kind'] === 'note'));
$doneCount = count(array_filter($phases, fn ($p) => $p['status'] === 'done'));

$editForm = function (array $i) use ($editing): string {
    if ($editing !== (int) $i['id']) {
        return '';
    }
    $h = '<form method="post" style="margin-top:12px">' . csrf_field()
       . '<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="' . (int) $i['id'] . '">'
       . '<label>Title</label><input type="text" name="title" value="' . e($i['title']) . '" required maxlength="255">'
       . '<label>Body</label><textarea name="body" class="prose" style="min-height:96px">' . e((string) $i['body']) . '</textarea>';
    if ($i['kind'] === 'phase') {
        $h .= '<label>Order</label><input type="number" name="sort" value="' . (int) $i['sort'] . '" style="max-width:110px">';
    } else {
        $h .= '<input type="hidden" name="sort" value="' . (int) $i['sort'] . '">';
    }
    $h .= '<p style="margin-top:12px"><button class="btn" type="submit">Save</button> '
        . '<a class="btn btn--ghost" href="roadmap.php">Cancel</a></p></form>';
    return $h;
};
$stamp = fn (array $i) => $i['updated_by'] !== ''
    ? '<span class="mono" style="color:#5A6A5C">' . e($i['updated_by']) . ' · ' . e(fmt_date($i['updated_at'], 'M j')) . '</span>'
    : '';

admin_header('Roadmap', 'roadmap');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle">The roadmap</h1>
  <span class="chip chip--ok"><?= $doneCount ?> of <?= count($phases) ?> phases done</span>
</div>
<p class="pagesub">The project's living document — the whole newsroom reads it, editors keep it.
Update a phase as work lands, answer questions in place, add notes freely. The design behind it
is <span class="mono">PLAN-CIVIS.md</span> in the repository.</p>

<div class="panel">
  <h2>The build, in phases</h2>
  <?php foreach ($phases as $p): ?>
  <div class="newsitem">
    <div class="t">
      <span class="chip chip--<?= e($chip($p['status'])) ?>"><?= e($phaseStatuses[$p['status']] ?? $p['status']) ?></span>
      <strong style="margin-left:8px"><?= e($p['title']) ?></strong>
      <?php if ($p['body'] !== ''): ?><span class="src" style="display:block;margin-top:4px"><?= nl2br(e($p['body'])) ?></span><?php endif; ?>
      <?= $editForm($p) ?>
    </div>
    <div class="acts">
      <?= $stamp($p) ?>
      <?php if ($editor): ?>
      <form method="post" class="inline"><?= csrf_field() ?>
        <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <select name="status" aria-label="Status">
          <?php foreach ($phaseStatuses as $sv => $sl): ?>
          <option value="<?= $sv ?>"<?= $p['status'] === $sv ? ' selected' : '' ?>><?= $sl ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn--ghost btn--small" type="submit">Set</button>
      </form>
      <a class="btn btn--ghost btn--small" href="roadmap.php?edit=<?= (int) $p['id'] ?>">Edit</a>
      <?php if ($user['role'] === 'admin'): ?>
      <form method="post" class="inline" onsubmit="return confirm('Remove this phase from the roadmap?')"><?= csrf_field() ?>
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <button class="btn btn--danger btn--small" type="submit">Remove</button>
      </form>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="panel">
  <h2>Open questions</h2>
  <?php if (!$questions): ?><p>Nothing open — every question asked has an answer.</p><?php endif; ?>
  <?php foreach ($questions as $q): ?>
  <div class="newsitem<?= $q['status'] === 'answered' ? ' is-used' : '' ?>">
    <div class="t">
      <span class="chip chip--<?= e($chip($q['status'])) ?>"><?= e($q['status']) ?></span>
      <strong style="margin-left:8px"><?= e($q['title']) ?></strong>
      <?php if ($q['body'] !== ''): ?><span class="src" style="display:block;margin-top:4px"><?= nl2br(e($q['body'])) ?></span><?php endif; ?>
      <?= $editForm($q) ?>
    </div>
    <div class="acts">
      <?= $stamp($q) ?>
      <?php if ($editor): ?>
      <a class="btn btn--ghost btn--small" href="roadmap.php?edit=<?= (int) $q['id'] ?>"><?= $q['status'] === 'open' ? 'Answer' : 'Edit' ?></a>
      <form method="post" class="inline"><?= csrf_field() ?>
        <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
        <input type="hidden" name="status" value="<?= $q['status'] === 'open' ? 'answered' : 'open' ?>">
        <button class="btn btn--ghost btn--small" type="submit"><?= $q['status'] === 'open' ? 'Mark answered' : 'Reopen' ?></button>
      </form>
      <?php if ($user['role'] === 'admin'): ?>
      <form method="post" class="inline" onsubmit="return confirm('Remove this question?')"><?= csrf_field() ?>
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
        <button class="btn btn--danger btn--small" type="submit">Remove</button>
      </form>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <p class="help">Answer a question by editing it — write the decision under the question, then mark it answered. The trail stays.</p>
</div>

<?php if ($notes): ?>
<div class="panel">
  <h2>Notes</h2>
  <?php foreach ($notes as $n): ?>
  <div class="newsitem">
    <div class="t">
      <strong><?= e($n['title']) ?></strong>
      <?php if ($n['body'] !== ''): ?><span class="src" style="display:block;margin-top:4px"><?= nl2br(e($n['body'])) ?></span><?php endif; ?>
      <?= $editForm($n) ?>
    </div>
    <div class="acts">
      <?= $stamp($n) ?>
      <?php if ($editor): ?>
      <a class="btn btn--ghost btn--small" href="roadmap.php?edit=<?= (int) $n['id'] ?>">Edit</a>
      <?php if ($user['role'] === 'admin'): ?>
      <form method="post" class="inline" onsubmit="return confirm('Remove this note?')"><?= csrf_field() ?>
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
        <button class="btn btn--danger btn--small" type="submit">Remove</button>
      </form>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($editor): ?>
<div class="panel">
  <h2>Add to the roadmap</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="formgrid">
      <div>
        <label for="rm-kind">Kind</label>
        <select id="rm-kind" name="kind">
          <?php foreach ($kinds as $kv => $kl): ?>
          <option value="<?= $kv ?>"><?= $kl ?></option>
          <?php endforeach; ?>
        </select>
        <label for="rm-title">Title</label>
        <input type="text" id="rm-title" name="title" required maxlength="255">
      </div>
      <div>
        <label for="rm-body">Body</label>
        <textarea id="rm-body" name="body" class="prose" style="min-height:96px"></textarea>
      </div>
    </div>
    <p style="margin-top:12px"><button class="btn" type="submit">Add it</button></p>
  </form>
</div>
<?php endif; ?>

<?php admin_footer(); ?>
