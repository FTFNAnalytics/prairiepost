<?php
/** Desks: each one owns a colour, a slug, and a line of description. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
require_editor();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = slugify((string) ($_POST['slug'] ?? '') ?: $name);
        $color = (string) ($_POST['color'] ?? '#17301C');
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#17301C';
        }
        $fill = isset($_POST['color_is_fill']) ? 1 : 0;
        $desc = trim((string) ($_POST['description'] ?? ''));
        $sort = (int) ($_POST['sort'] ?? 0);
        if ($name === '') {
            flash_set('A desk needs a name.', true);
        } elseif ($id) {
            db()->prepare('UPDATE categories SET name = ?, slug = ?, color = ?, color_is_fill = ?, description = ?, sort = ? WHERE id = ?')
                ->execute([$name, $slug, $color, $fill, $desc, $sort, $id]);
            flash_set('Desk updated.');
        } else {
            db()->prepare('INSERT INTO categories (name, slug, color, color_is_fill, description, sort) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$name, $slug, $color, $fill, $desc, $sort]);
            flash_set('Desk added. It appears in the navigation immediately.');
        }
    }

    if ($action === 'delete' && $id) {
        $stmt = db()->prepare('SELECT COUNT(*) AS n FROM posts WHERE category_id = ?');
        $stmt->execute([$id]);
        $n = (int) $stmt->fetch()['n'];
        if ($n > 0) {
            flash_set("That desk still has $n " . ($n === 1 ? 'story' : 'stories') . '. Move them to another desk first, then delete it.', true);
        } else {
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash_set('Desk deleted.');
        }
    }
    redirect('categories.php');
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

admin_header('Desks', 'categories');
flash_show();
?>

<h1 class="pagetitle">Desks</h1>
<p class="pagesub">Each desk owns one colour, and it appears in exactly two places on the site: the nav underline and the eyebrow above the headline. Colours under 4.5:1 contrast are fills — tick the box and the eyebrow sets ink type on a colour block instead.</p>

<div class="panel">
  <table class="tbl">
    <tr><th>Desk</th><th>Slug</th><th>Colour</th><th>Stories</th><th></th></tr>
    <?php foreach (categories_all() as $cat): ?>
    <tr>
      <td><span class="deskdot" style="background:<?= e($cat['color']) ?>"></span><strong><?= e($cat['name']) ?></strong>
        <?php if ($cat['color_is_fill']): ?> <span class="chip chip--used">fill only</span><?php endif; ?></td>
      <td class="mono">/desk/<?= e($cat['slug']) ?></td>
      <td class="mono"><?= e($cat['color']) ?></td>
      <td class="mono"><?= count_posts_in_category((int) $cat['id']) ?></td>
      <td style="white-space:nowrap">
        <a class="btn btn--ghost btn--small" href="categories.php?edit=<?= (int) $cat['id'] ?>">Edit</a>
        <form method="post" class="inline" onsubmit="return confirm('Delete this desk?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $cat['id'] ?>"><button class="btn btn--danger btn--small" type="submit">Delete</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="panel">
  <h2><?= $editing ? 'Edit desk: ' . e($editing['name']) : 'Add a desk' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <div class="formgrid">
      <div>
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= e($editing['name'] ?? '') ?>" required>
        <label for="slug">Slug · letters and hyphens</label>
        <input type="text" id="slug" name="slug" value="<?= e($editing['slug'] ?? '') ?>" placeholder="auto from name">
        <label for="sort">Order in the nav</label>
        <input type="text" id="sort" name="sort" value="<?= e((string) ($editing['sort'] ?? '0')) ?>">
      </div>
      <div>
        <label for="color">Colour · hex</label>
        <input type="text" id="color" name="color" value="<?= e($editing['color'] ?? '#17301C') ?>" placeholder="#3F5A22">
        <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:.04em">
          <input type="checkbox" name="color_is_fill" style="width:auto"<?= !empty($editing['color_is_fill']) ? ' checked' : '' ?>>
          Fill only (under 4.5:1 — never carries text)
        </label>
        <label for="description">Description · shows on the desk front</label>
        <textarea id="description" name="description" class="prose" style="min-height:64px"><?= e($editing['description'] ?? '') ?></textarea>
      </div>
    </div>
    <p style="margin-top:16px">
      <button class="btn" type="submit"><?= $editing ? 'Save the desk' : 'Add the desk' ?></button>
      <?php if ($editing): ?><a class="btn btn--ghost" href="categories.php">Cancel</a><?php endif; ?>
    </p>
  </form>
</div>

<?php admin_footer(); ?>
