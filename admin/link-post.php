<?php
/**
 * The wire desk: paste a link, post it to the site as an outbound wire item.
 * Fetches the page's Open Graph metadata (headline, summary, featured image,
 * outlet name), lets the editor assign a region, desk and tags, and publishes
 * a link post — the headline on the site links to the outlet that reported it.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/fetch.php';
require __DIR__ . '/_layout.php';
$user = require_editor();

$regions = setting_json('regions');
$error = '';
$form = null;   // when set, the prefilled details form renders

/** Prefill the form from a fetched page. */
$prefill = function (string $url, array $meta, string $region = '') use ($regions): array {
    if (!isset($regions[$region])) {
        $region = '';
    }
    return [
        'url'         => $url,
        'title'       => $meta['title'],
        'lede'        => $meta['description'],
        'source_name' => $meta['site_name'] !== '' ? $meta['site_name'] : $meta['host'],
        'image'       => $meta['image'],
        'region'      => $region,
        'category_id' => 0,
        'tags'        => '',
        'item_id'     => 0,
    ];
};

/* --- Start from a wire-pull item (?item=ID) ------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (int) ($_GET['item'] ?? 0)) {
    $stmt = db()->prepare('SELECT n.*, s.name AS source_name FROM news_items n JOIN sources s ON s.id = n.source_id WHERE n.id = ?');
    $stmt->execute([(int) $_GET['item']]);
    if ($item = $stmt->fetch()) {
        $meta = pp_fetch_link_meta($item['url']);
        $error = (string) ($meta['error'] ?? '');
        $form = $prefill($item['url'], $meta, (string) $item['region']);
        // The feed's own title and summary beat an empty scrape.
        $form['title'] = $form['title'] !== '' ? $form['title'] : (string) $item['title'];
        $form['lede'] = $form['lede'] !== '' ? $form['lede'] : (string) ($item['summary'] ?? '');
        $form['source_name'] = (string) $item['source_name'];
        $form['item_id'] = (int) $item['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    /* --- Step 1: fetch the page and prefill ------------------------------- */
    if ($action === 'fetch') {
        $url = trim((string) ($_POST['url'] ?? ''));
        $meta = pp_fetch_link_meta($url);
        if ($meta['error'] !== null && $meta['host'] === '') {
            $error = $meta['error'];   // not even a URL — back to the paste box
        } else {
            $error = (string) ($meta['error'] ?? '');
            $form = $prefill($url, $meta);
        }
    }

    /* --- Step 2: publish the link post ------------------------------------ */
    if ($action === 'publish' || $action === 'draft') {
        $form = [
            'url'         => trim((string) ($_POST['url'] ?? '')),
            'title'       => trim((string) ($_POST['title'] ?? '')),
            'lede'        => trim((string) ($_POST['lede'] ?? '')),
            'source_name' => trim((string) ($_POST['source_name'] ?? '')),
            'image'       => trim((string) ($_POST['image'] ?? '')),
            'region'      => (string) ($_POST['region'] ?? ''),
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'tags'        => trim((string) ($_POST['tags'] ?? '')),
            'item_id'     => (int) ($_POST['item_id'] ?? 0),
        ];
        if (!isset($regions[$form['region']])) {
            $form['region'] = '';
        }

        if ($form['title'] === '') {
            $error = 'The wire item needs a headline.';
        } elseif (!filter_var($form['url'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $form['url'])) {
            $error = 'The link has to be a full http(s) URL — that\'s where the headline points.';
        } else {
            $image = $form['image'];
            $imageNote = '';
            // Cache the outlet's image locally so the card survives their CDN.
            if (!empty($_POST['cache_image']) && preg_match('#^https?://#i', $image)) {
                [$cached, $imgErr] = pp_cache_remote_image($image);
                if ($cached !== null) {
                    $image = $cached;
                } else {
                    $imageNote = ' The image couldn\'t be cached (' . $imgErr . ') so the card uses the outlet\'s copy directly.';
                }
            }

            $sourceName = $form['source_name'] !== ''
                ? $form['source_name']
                : preg_replace('/^www\./', '', parse_url($form['url'], PHP_URL_HOST) ?: '');
            $status = $action === 'publish' ? 'published' : 'draft';
            $fields = [
                'title'            => $form['title'],
                'slug'             => unique_post_slug($form['title']),
                'category_id'      => $form['category_id'] ?: null,
                'author_id'        => (int) $user['id'],
                'byline'           => '',
                'lede'             => $form['lede'],
                'body'             => '',
                'image'            => $image,
                'image_credit'     => $image !== '' ? $sourceName : '',
                'meta_description' => excerpt($form['lede'], 155),
                'source_url'       => $form['url'],
                'source_name'      => $sourceName,
                'post_type'        => 'link',
                'region'           => $form['region'],
                'origin'           => 'wire',
                'status'           => $status,
                'published_at'     => $status === 'published' ? now() : null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
            $cols = implode(', ', array_keys($fields));
            $marks = implode(', ', array_fill(0, count($fields), '?'));
            db()->prepare("INSERT INTO posts ($cols) VALUES ($marks)")->execute(array_values($fields));
            $postId = pp_last_id('posts');
            set_post_tags($postId, $form['tags']);
            set_post_sites($postId, [current_site_id()]);
            if ($form['item_id']) {
                db()->prepare('UPDATE news_items SET used = 1 WHERE id = ?')->execute([$form['item_id']]);
            }

            flash_set(($status === 'published'
                ? 'On the wire. The headline now links to ' . $sourceName . '.'
                : 'Saved as a draft — publish it from the editor.') . $imageNote);
            redirect('link-post.php?posted=' . $postId);
        }
    }
}

$posted = (int) ($_GET['posted'] ?? 0);
$v = fn (string $key) => e((string) ($form[$key] ?? ''));

admin_header('Post a link', 'linkpost');
flash_show();
if ($error !== '') {
    echo '<div class="flash flash--error">' . e($error) . '</div>';
}
?>

<div class="headrow">
  <h1 class="pagetitle">Post a link</h1>
  <?php if ($posted): ?>
  <div>
    <a class="btn btn--ghost" href="post-edit.php?id=<?= $posted ?>">Open the last one in the editor</a>
    <a class="btn btn--ghost" href="/" target="_blank">View the site →</a>
  </div>
  <?php endif; ?>
</div>
<p class="pagesub">Drop in a link from another newsroom. The page's headline, summary and featured
image fill themselves in; assign a region and tags, and it posts to the site as a hyperlink —
readers land on the outlet that reported it, credited by name.</p>

<?php if ($form === null): ?>
<div class="panel">
  <h2>The link</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="fetch">
    <label for="url">Paste the article's URL</label>
    <input type="url" id="url" name="url" required placeholder="https://edmontonecho.com/story/…" autofocus>
    <p class="help">Any article page works — the tool reads its Open Graph tags. Headlines from the
    morning pull have a “Post link” button that lands here prefilled.</p>
    <p style="margin-top:14px"><button class="btn" type="submit">Fetch the details</button></p>
  </form>
</div>

<?php else: ?>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="item_id" value="<?= (int) ($form['item_id'] ?? 0) ?>">

  <label for="url">Links to</label>
  <input type="url" id="url" name="url" value="<?= $v('url') ?>" required>

  <label for="title">Headline</label>
  <input type="text" id="title" name="title" value="<?= $v('title') ?>" required maxlength="255">

  <label for="lede">Summary · shown under the headline on the wire</label>
  <textarea id="lede" name="lede" class="prose" style="min-height:64px"><?= $v('lede') ?></textarea>

  <div class="formgrid">
    <div>
      <label for="source_name">Credit · the outlet's name</label>
      <input type="text" id="source_name" name="source_name" value="<?= $v('source_name') ?>" maxlength="160" placeholder="The Edmonton Echo">
    </div>
    <div>
      <label for="region">Region</label>
      <select id="region" name="region">
        <option value="">— No region —</option>
        <?php foreach ($regions as $key => $label): ?>
        <option value="<?= e($key) ?>"<?= ($form['region'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="category_id">Desk</label>
      <select id="category_id" name="category_id">
        <option value="">— No desk —</option>
        <?php foreach (categories_all() as $cat): ?>
        <option value="<?= (int) $cat['id'] ?>"<?= (int) ($form['category_id'] ?? 0) === (int) $cat['id'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="tags">Tags · comma-separated</label>
      <input type="text" id="tags" name="tags" value="<?= $v('tags') ?>" placeholder="wildfire, housing, grid">
    </div>
  </div>

  <div class="panel">
    <h2>Featured image</h2>
    <div class="formgrid">
      <div>
        <label for="image">Image URL · from the article's Open Graph tags</label>
        <input type="text" id="image" name="image" value="<?= $v('image') ?>">
        <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:.04em;margin:10px 0 0">
          <input type="checkbox" name="cache_image" value="1" checked style="width:auto">
          Cache a copy in /uploads/ (recommended — the card keeps working if the outlet moves theirs)
        </label>
        <p class="help">Clear the field to run the item without an image; the credit line always names the outlet.</p>
      </div>
      <div>
        <?php if (($form['image'] ?? '') !== ''): ?>
        <img src="<?= $v('image') ?>" alt="Featured image preview" style="max-width:100%;border:1px solid var(--pp-board,#C4C0B4)">
        <?php else: ?>
        <p class="help">No image found on the page.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <p style="margin-top:20px">
    <button class="btn" type="submit" name="action" value="publish">Post to the wire</button>
    <button class="btn btn--ghost" type="submit" name="action" value="draft">Save as a draft</button>
    <a class="btn btn--ghost" href="link-post.php">Start over</a>
  </p>
</form>
<?php endif; ?>

<?php admin_footer(); ?>
