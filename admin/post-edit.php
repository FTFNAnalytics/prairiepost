<?php
/** Story editor: write, schedule, publish. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_login();

$id = (int) ($_GET['id'] ?? 0);
$post = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if (!$post) {
        flash_set('That story no longer exists — it may have been deleted.', true);
        redirect('posts.php');
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title    = trim((string) ($_POST['title'] ?? ''));
    $lede     = trim((string) ($_POST['lede'] ?? ''));
    $body     = sanitize_html((string) ($_POST['body'] ?? ''));
    $status   = in_array($_POST['status'] ?? '', ['draft', 'published', 'scheduled'], true) ? $_POST['status'] : 'draft';
    $publishedAt = trim((string) ($_POST['published_at'] ?? ''));

    if ($title === '') {
        $error = 'The story needs a headline before it can be saved.';
    } else {
        if ($publishedAt !== '') {
            $ts = strtotime($publishedAt);
            $publishedAt = $ts ? date('Y-m-d H:i:s', $ts) : '';
        }
        if ($status === 'published' && $publishedAt === '') {
            $publishedAt = now();
        }
        if ($status === 'scheduled' && ($publishedAt === '' || $publishedAt <= now())) {
            $error = 'A scheduled story needs a publish time in the future. Set one, or publish it now.';
        }
    }

    if ($error === '') {
        $fields = [
            'title' => $title,
            'category_id' => (int) ($_POST['category_id'] ?? 0) ?: null,
            'byline' => trim((string) ($_POST['byline'] ?? '')),
            'dateline' => trim((string) ($_POST['dateline'] ?? '')),
            'lede' => $lede,
            'body' => $body,
            'image' => trim((string) ($_POST['image'] ?? '')),
            'image_caption' => trim((string) ($_POST['image_caption'] ?? '')),
            'image_credit' => trim((string) ($_POST['image_credit'] ?? '')),
            'meta_description' => mb_substr(trim((string) ($_POST['meta_description'] ?? '')), 0, 255),
            'source_url' => trim((string) ($_POST['source_url'] ?? '')),
            'status' => $status,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'published_at' => $publishedAt ?: null,
            'updated_at' => now(),
        ];
        if ($fields['is_featured']) {
            db()->exec('UPDATE posts SET is_featured = 0');
        }
        if ($post) {
            $fields['slug'] = unique_post_slug($title, (int) $post['id']);
            $set = implode(', ', array_map(fn ($k) => "$k = ?", array_keys($fields)));
            db()->prepare("UPDATE posts SET $set WHERE id = ?")
                ->execute([...array_values($fields), $post['id']]);
            $id = (int) $post['id'];
        } else {
            $fields['slug'] = unique_post_slug($title);
            $fields['author_id'] = (int) $user['id'];
            $fields['created_at'] = now();
            $cols = implode(', ', array_keys($fields));
            $marks = implode(', ', array_fill(0, count($fields), '?'));
            db()->prepare("INSERT INTO posts ($cols) VALUES ($marks)")->execute(array_values($fields));
            $id = (int) db()->lastInsertId();
        }
        set_post_tags($id, (string) ($_POST['tags'] ?? ''));
        flash_set($status === 'published' ? 'Published. The story is live on the site.'
            : ($status === 'scheduled' ? 'Scheduled. It goes live at the set time (the cron job flips it).' : 'Draft saved.'));
        redirect('post-edit.php?id=' . $id);
    }

    // Re-show what was typed on error.
    $post = array_merge($post ?: [], $_POST, ['id' => $id]);
}

$tagsValue = '';
if ($id && empty($error)) {
    $tagsValue = implode(', ', array_column(tags_for_post($id), 'name'));
} elseif (isset($_POST['tags'])) {
    $tagsValue = (string) $_POST['tags'];
}

$v = fn (string $key, string $default = '') => e((string) ($post[$key] ?? $default));

admin_header($id ? 'Edit story' : 'New story', 'posts');
flash_show();
if ($error) {
    echo '<div class="flash flash--error">' . e($error) . '</div>';
}
?>

<div class="headrow">
  <h1 class="pagetitle"><?= $id ? 'Edit story' : 'New story' ?></h1>
  <?php if ($id && ($post['status'] ?? '') === 'published'): ?>
  <a class="btn btn--ghost" href="/story/<?= $v('slug') ?>" target="_blank">View on the site →</a>
  <?php endif; ?>
</div>

<form method="post" id="storyform">
  <?= csrf_field() ?>

  <label for="title">Headline · sentence case</label>
  <input type="text" id="title" name="title" value="<?= $v('title') ?>" required maxlength="255">

  <label for="lede">Lede · one sentence, 30 words maximum</label>
  <textarea id="lede" name="lede" class="prose" style="min-height:64px"><?= $v('lede') ?></textarea>

  <div class="formgrid">
    <div>
      <label for="dateline">Dateline · the place, name it always</label>
      <input type="text" id="dateline" name="dateline" value="<?= $v('dateline') ?>" placeholder="Three Hills">
    </div>
    <div>
      <label for="byline">Byline</label>
      <input type="text" id="byline" name="byline" value="<?= $v('byline', $user['name']) ?>">
    </div>
    <div>
      <label for="category_id">Desk</label>
      <select id="category_id" name="category_id">
        <option value="">— No desk —</option>
        <?php foreach (categories_all() as $cat): ?>
        <option value="<?= (int) $cat['id'] ?>"<?= (int) ($post['category_id'] ?? 0) === (int) $cat['id'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="tags">Tags · comma-separated</label>
      <input type="text" id="tags" name="tags" value="<?= e($tagsValue) ?>" placeholder="canola, county council">
    </div>
  </div>

  <label>Story text</label>
  <div class="edtoolbar" role="toolbar" aria-label="Formatting">
    <button type="button" data-cmd="bold"><strong>B</strong></button>
    <button type="button" data-cmd="italic"><em>I</em></button>
    <button type="button" data-block="h2">Crosshead</button>
    <button type="button" data-block="blockquote">Quote</button>
    <button type="button" data-cmd="insertUnorderedList">List</button>
    <button type="button" data-link="1">Link</button>
    <button type="button" data-cmd="unlink">Unlink</button>
    <button type="button" data-cmd="removeFormat">Clear</button>
  </div>
  <div class="editor" id="editor" contenteditable="true"><?= sanitize_html((string) ($post['body'] ?? '')) ?></div>
  <textarea name="body" id="bodyfield" style="display:none"><?= e((string) ($post['body'] ?? '')) ?></textarea>

  <div class="panel">
    <h2>Photo</h2>
    <div class="formgrid">
      <div>
        <label for="image">Image path or URL</label>
        <input type="text" id="image" name="image" value="<?= $v('image') ?>" placeholder="/uploads/2026/08/field.jpg">
        <p class="help">Upload a JPEG, PNG or WebP and the path fills itself in. The five-band placeholders live in /assets/img/photo-01.svg … photo-06.svg.</p>
        <input type="file" id="imgupload" accept="image/jpeg,image/png,image/webp,image/gif">
      </div>
      <div>
        <label for="image_caption">Caption</label>
        <input type="text" id="image_caption" name="image_caption" value="<?= $v('image_caption') ?>">
        <label for="image_credit">Credit</label>
        <input type="text" id="image_credit" name="image_credit" value="<?= $v('image_credit') ?>" placeholder="Staff photo">
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>Publication</h2>
    <div class="formgrid">
      <div>
        <label for="status">Status</label>
        <select id="status" name="status">
          <?php $st = (string) ($post['status'] ?? 'draft'); ?>
          <option value="draft"<?= $st === 'draft' ? ' selected' : '' ?>>Draft — visible to the newsroom only</option>
          <option value="published"<?= $st === 'published' ? ' selected' : '' ?>>Published — live on the site</option>
          <option value="scheduled"<?= $st === 'scheduled' ? ' selected' : '' ?>>Scheduled — goes live at the set time</option>
        </select>
        <label for="published_at">Publish time</label>
        <input type="datetime-local" id="published_at" name="published_at"
               value="<?= !empty($post['published_at']) ? e(date('Y-m-d\TH:i', strtotime($post['published_at']))) : '' ?>">
        <p class="help">Leave blank on publish to use right now.</p>
      </div>
      <div>
        <label for="meta_description">Search description · 155 characters</label>
        <textarea id="meta_description" name="meta_description" maxlength="255" style="min-height:64px"><?= $v('meta_description') ?></textarea>
        <label for="source_url">Source link · when started from the wire</label>
        <input type="url" id="source_url" name="source_url" value="<?= $v('source_url') ?>">
        <label style="display:flex;align-items:center;gap:8px;margin-top:18px;text-transform:none;letter-spacing:.04em">
          <input type="checkbox" name="is_featured" style="width:auto"<?= !empty($post['is_featured']) ? ' checked' : '' ?>>
          Pin to the top of the front page
        </label>
      </div>
    </div>
  </div>

  <p style="margin-top:20px">
    <button class="btn" type="submit">Save the story</button>
    <a class="btn btn--ghost" href="posts.php">Back to the list</a>
  </p>
</form>

<script src="/assets/js/editor.js"></script>
<?php admin_footer(); ?>
