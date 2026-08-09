<?php
/** Stories list: filter, edit, feature, delete. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_login();
$editor = is_editor($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $target = null;
    if ($id) {
        $stmt = db()->prepare('SELECT * FROM posts WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
    }
    if ($action === 'delete' && $target) {
        // Authors clear their own unpublished work; editors delete anything.
        if (!$editor && ((int) $target['author_id'] !== (int) $user['id'] || $target['status'] === 'published')) {
            flash_set("Only editors can delete a published story or another author's work.", true);
        } else {
            db()->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
            db()->prepare('DELETE FROM post_tags WHERE post_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM post_sites WHERE post_id = ?')->execute([$id]);
            flash_set('Story deleted. It is gone from the site and the archive.');
        }
    }
    if ($action === 'feature' && $target && $editor) {
        db()->exec('UPDATE posts SET is_featured = 0');
        db()->prepare('UPDATE posts SET is_featured = 1 WHERE id = ?')->execute([$id]);
        flash_set('Pinned to the top of the front page.');
    }
    redirect('posts.php?' . http_build_query(array_filter([
        'status' => $_GET['status'] ?? '', 'category' => $_GET['category'] ?? '', 'q' => $_GET['q'] ?? '',
    ])));
}

$status = (string) ($_GET['status'] ?? '');
$catFilter = (int) ($_GET['category'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$where = [];
$params = [];
if (!$editor) {
    $where[] = 'p.author_id = ?';
    $params[] = (int) $user['id'];
}
if (in_array($status, ['draft', 'in_review', 'published', 'scheduled'], true)) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if ($catFilter) {
    $where[] = 'p.category_id = ?';
    $params[] = $catFilter;
}
if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.byline LIKE ? OR p.dateline LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = db()->prepare('SELECT COUNT(*) AS n FROM posts p' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['n'];
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);

$stmt = db()->prepare('SELECT p.*, c.name AS category_name, c.color AS category_color FROM posts p
    LEFT JOIN categories c ON c.id = p.category_id' . $whereSql . '
    ORDER BY COALESCE(p.published_at, p.updated_at) DESC
    LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage));
$stmt->execute($params);
$posts = $stmt->fetchAll();

admin_header('Stories', 'posts');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle">Stories</h1>
  <a class="btn" href="post-edit.php">+ New story</a>
</div>
<p class="pagesub"><?= $total ?> <?= $total === 1 ? 'story' : 'stories' ?><?= $editor ? ' in the system.' : ' of yours.' ?></p>

<form method="get" class="formrow">
  <select name="status" aria-label="Status">
    <option value="">Any status</option>
    <?php foreach (['published' => 'Published', 'draft' => 'Draft', 'in_review' => 'In review', 'scheduled' => 'Scheduled'] as $s => $label): ?>
    <option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= $label ?></option>
    <?php endforeach; ?>
  </select>
  <select name="category" aria-label="Desk">
    <option value="">Any desk</option>
    <?php foreach (categories_all() as $cat): ?>
    <option value="<?= (int) $cat['id'] ?>"<?= $catFilter === (int) $cat['id'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="Title, byline, dateline…" style="max-width:260px">
  <button class="btn btn--ghost" type="submit">Filter</button>
</form>

<div class="panel">
  <table class="tbl">
    <tr><th>Story</th><th>Desk</th><th>Status</th><th>When</th><th></th></tr>
    <?php foreach ($posts as $p): ?>
    <tr>
      <td>
        <a class="rowtitle" href="post-edit.php?id=<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a>
        <?php if ($p['is_featured']): ?> <span class="chip chip--scheduled">Front</span><?php endif; ?>
        <div class="mono" style="color:#5A6A5C"><?= e($p['byline']) ?><?= $p['dateline'] ? ' · ' . e(mb_strtoupper($p['dateline'])) : '' ?></div>
      </td>
      <td><?php if ($p['category_name']): ?><span class="deskdot" style="background:<?= e($p['category_color']) ?>"></span><?= e($p['category_name']) ?><?php endif; ?></td>
      <td><span class="chip chip--<?= e($p['status']) ?>"><?= e(str_replace('_', ' ', $p['status'])) ?></span></td>
      <td class="mono"><?= e(fmt_date($p['published_at'] ?: $p['updated_at'], 'M j, Y g:i a')) ?></td>
      <td style="white-space:nowrap">
        <?php if ($editor && $p['status'] === 'published' && !$p['is_featured']): ?>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="feature"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn--ghost btn--small" type="submit">Pin to front</button></form>
        <?php endif; ?>
        <?php if ($editor || ($p['status'] !== 'published' && (int) $p['author_id'] === (int) $user['id'])): ?>
        <form method="post" class="inline" onsubmit="return confirm('Delete this story? It is removed from the site immediately.')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn--danger btn--small" type="submit">Delete</button></form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (!$posts): ?><p>No stories match those filters. Clear them, or <a href="post-edit.php">file a fresh story</a>.</p><?php endif; ?>
</div>

<?php if ($pages > 1): ?>
<div class="formrow" style="margin-top:16px">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <?php $qs = http_build_query(array_filter(['status' => $status, 'category' => $catFilter, 'q' => $q, 'page' => $i])); ?>
    <?php if ($i === $page): ?><span class="chip chip--used"><?= $i ?></span>
    <?php else: ?><a class="btn btn--ghost btn--small" href="posts.php?<?= e($qs) ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php admin_footer(); ?>
