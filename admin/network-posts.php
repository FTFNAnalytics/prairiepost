<?php
/**
 * The network desk — the control room's view of every story on every paper.
 * Each row shows which papers a story runs on; tick stories, tick papers,
 * and add or remove the mapping in bulk. One filing, the whole network.
 * Hub only: on a paper's admin this page hands over to the Stories list.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_editor();
if (!pp_is_hub()) {
    redirect('posts.php');
}

$allSites = sites_all();
$siteById = array_column($allSites, null, 'id');
$sites = pp_paper_sites();   // assignment targets — the hub never carries a story

/** Short chip label per site: the last word of its name, unless that collides. */
$labels = [];
$lastWords = array_map(fn ($s) => preg_replace('/^.*\s/', '', $s['name']), $allSites);
foreach ($allSites as $i => $s) {
    $w = $lastWords[$i];
    $labels[(int) $s['id']] = count(array_keys($lastWords, $w)) === 1 ? $w : $s['name'];
}

$filters = array_filter([
    'site'   => (int) ($_GET['site'] ?? 0) ?: null,
    'status' => (string) ($_GET['status'] ?? '') ?: null,
    'category' => (int) ($_GET['category'] ?? 0) ?: null,
    'origin' => (string) ($_GET['origin'] ?? '') ?: null,
    'q'      => trim((string) ($_GET['q'] ?? '')) ?: null,
    'page'   => max(1, (int) ($_GET['page'] ?? 1)) > 1 ? (int) $_GET['page'] : null,
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action  = (string) ($_POST['action'] ?? '');
    $ids     = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
    $paperIds = array_map(fn ($s) => (int) $s['id'], $sites);
    $targets = array_values(array_filter(array_map('intval', (array) ($_POST['sites'] ?? [])),
        fn ($id) => in_array($id, $paperIds, true)));

    if (!in_array($action, ['add_sites', 'remove_sites'], true) || !$ids || !$targets) {
        flash_set('Tick at least one story and one paper, then choose add or remove.', true);
        redirect('network-posts.php?' . http_build_query($filters));
    }

    $ins = db()->prepare('INSERT INTO post_sites (post_id, site_id) VALUES (?, ?)');
    $del = db()->prepare('DELETE FROM post_sites WHERE post_id = ? AND site_id = ?');
    $changed = 0;
    $orphaned = 0;
    foreach ($ids as $postId) {
        $current = site_ids_for_post($postId);
        if ($action === 'add_sites') {
            foreach (array_diff($targets, $current) as $siteId) {
                $ins->execute([$postId, $siteId]);
                $changed++;
            }
        } else {
            foreach (array_intersect($targets, $current) as $siteId) {
                $del->execute([$postId, $siteId]);
                $changed++;
            }
            if (!site_ids_for_post($postId)) {
                $orphaned++;
            }
        }
    }
    $paperNames = implode(', ', array_map(fn ($id) => $labels[$id], $targets));
    $note = $action === 'add_sites'
        ? "Added — $changed placement(s) across $paperNames."
        : "Removed — $changed placement(s) from $paperNames.";
    if ($orphaned) {
        $note .= " $orphaned story(ies) now run on no paper at all — they stay in the system but appear nowhere until reassigned.";
    }
    flash_set($note, (bool) $orphaned);
    redirect('network-posts.php?' . http_build_query($filters));
}

$siteFilter = (int) ($_GET['site'] ?? 0);
$status     = (string) ($_GET['status'] ?? '');
$catFilter  = (int) ($_GET['category'] ?? 0);
$origin     = (string) ($_GET['origin'] ?? '');
$q          = trim((string) ($_GET['q'] ?? ''));
$page       = max(1, (int) ($_GET['page'] ?? 1));
$perPage    = 25;

$where = [];
$params = [];
if ($siteFilter && isset($siteById[$siteFilter])) {
    $where[] = 'EXISTS (SELECT 1 FROM post_sites ps WHERE ps.post_id = p.id AND ps.site_id = ?)';
    $params[] = $siteFilter;
}
if (in_array($status, ['draft', 'in_review', 'published', 'scheduled'], true)) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if ($catFilter) {
    $where[] = 'p.category_id = ?';
    $params[] = $catFilter;
}
if (in_array($origin, ['newsroom', 'wire', 'ai'], true)) {
    $where[] = 'p.origin = ?';
    $params[] = $origin === 'newsroom' ? '' : $origin;
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

// Which papers each story on this page runs on, in one query.
$runsOn = [];
if ($posts) {
    $in = implode(',', array_map(fn ($p) => (int) $p['id'], $posts));
    foreach (db()->query("SELECT post_id, site_id FROM post_sites WHERE post_id IN ($in)") as $row) {
        $runsOn[(int) $row['post_id']][] = (int) $row['site_id'];
    }
}

admin_header('Network desk', 'network');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle">The network desk</h1>
  <a class="btn" href="post-edit.php">+ New story</a>
</div>
<p class="pagesub"><?= $total ?> <?= $total === 1 ? 'story' : 'stories' ?> across <?= count($sites) ?> papers.
Every story is filed once and runs wherever it's mapped — tick stories below, tick papers, and move them in bulk.
The editor's own <em>Runs on</em> picker still rules a single story.</p>

<form method="get" class="formrow">
  <select name="site" aria-label="Paper">
    <option value="">Any paper</option>
    <?php foreach ($sites as $s): ?>
    <option value="<?= (int) $s['id'] ?>"<?= $siteFilter === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
    <?php endforeach; ?>
  </select>
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
  <select name="origin" aria-label="Origin">
    <option value="">Any origin</option>
    <option value="newsroom"<?= $origin === 'newsroom' ? ' selected' : '' ?>>Newsroom</option>
    <option value="wire"<?= $origin === 'wire' ? ' selected' : '' ?>>From the wire</option>
    <option value="ai"<?= $origin === 'ai' ? ' selected' : '' ?>>AI-assisted</option>
  </select>
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="Title, byline, dateline…" style="max-width:220px">
  <button class="btn btn--ghost" type="submit">Filter</button>
</form>

<form method="post">
  <?= csrf_field() ?>
  <div class="panel">
    <table class="tbl">
      <tr>
        <th style="width:28px"><input type="checkbox" onclick="document.querySelectorAll('input[name=\'ids[]\']').forEach(c=>c.checked=this.checked)" aria-label="Select all"></th>
        <th>Story</th><th>Desk</th><th>Status</th><th>Runs on</th><th>When</th>
      </tr>
      <?php foreach ($posts as $p): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" aria-label="Select story"></td>
        <td>
          <a class="rowtitle" href="post-edit.php?id=<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a>
          <?php if ($p['post_type'] === 'link'): ?> <span class="chip chip--used">wire link</span><?php endif; ?>
          <?php if ($p['origin'] === 'ai'): ?> <span class="chip chip--scheduled">AI-assisted</span><?php endif; ?>
          <div class="mono" style="color:#5A6A5C"><?= e($p['byline']) ?><?= $p['dateline'] ? ' · ' . e(mb_strtoupper($p['dateline'])) : '' ?></div>
        </td>
        <td><?php if ($p['category_name']): ?><span class="deskdot" style="background:<?= e($p['category_color']) ?>"></span><?= e($p['category_name']) ?><?php endif; ?></td>
        <td><span class="chip chip--<?= e($p['status']) ?>"><?= e(str_replace('_', ' ', $p['status'])) ?></span></td>
        <td>
          <?php $on = $runsOn[(int) $p['id']] ?? []; ?>
          <?php if (!$on): ?><span class="chip chip--used" title="This story appears on no paper">nowhere</span>
          <?php else: foreach ($on as $sid): if (isset($siteById[$sid])): ?>
          <span class="chip chip--ok" title="<?= e($siteById[$sid]['name']) ?>"><?= e($labels[$sid]) ?></span>
          <?php endif; endforeach; endif; ?>
        </td>
        <td class="mono" style="white-space:nowrap"><?= e(fmt_date($p['published_at'] ?: $p['updated_at'], 'M j, Y')) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php if (!$posts): ?><p>No stories match those filters.</p><?php endif; ?>
  </div>

  <div class="panel">
    <h2>Move the ticked stories</h2>
    <div class="formrow" style="flex-wrap:wrap;align-items:center">
      <?php foreach ($sites as $s): ?>
      <label class="inline" style="display:inline-flex;align-items:center;gap:6px;margin-right:14px">
        <input type="checkbox" name="sites[]" value="<?= (int) $s['id'] ?>"> <?= e($s['name']) ?>
      </label>
      <?php endforeach; ?>
    </div>
    <p style="margin-top:12px">
      <button class="btn" type="submit" name="action" value="add_sites">Add to the ticked papers</button>
      <button class="btn btn--danger" type="submit" name="action" value="remove_sites"
        onclick="return confirm('Remove the ticked stories from the ticked papers? A published story disappears from those front pages immediately.')">Remove from the ticked papers</button>
    </p>
    <p class="help">Adding never duplicates a placement; removing only touches the ticked papers. A story removed from every paper stays in the system but appears nowhere.</p>
  </div>
</form>

<?php if ($pages > 1): ?>
<div class="formrow" style="margin-top:16px">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <?php $qs = http_build_query(array_merge($filters, ['page' => $i])); ?>
    <?php if ($i === $page): ?><span class="chip chip--used"><?= $i ?></span>
    <?php else: ?><a class="btn btn--ghost btn--small" href="network-posts.php?<?= e($qs) ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php admin_footer(); ?>
