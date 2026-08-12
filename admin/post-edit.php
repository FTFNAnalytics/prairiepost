<?php
/**
 * Story editor: write, submit for review, schedule, publish, syndicate.
 * Authors work on their own stories and hand them to the review queue;
 * editors and admins publish, schedule, pin, and choose sites.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_login();
$editor = is_editor($user);

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
    if (!can_edit_post($user, $post)) {
        http_response_code(403);
        exit('That story belongs to another author. Editors can open anything; authors open their own.');
    }
}

$allowedStatuses = $editor
    ? ['draft', 'in_review', 'published', 'scheduled']
    : ['draft', 'in_review'];

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title    = trim((string) ($_POST['title'] ?? ''));
    $lede     = trim((string) ($_POST['lede'] ?? ''));
    $body     = sanitize_html((string) ($_POST['body'] ?? ''));
    $status   = (string) ($_POST['status'] ?? '');
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'draft';
    }
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
        $regions = setting_json('regions');
        $region = (string) ($_POST['region'] ?? '');
        $fields = [
            'title' => $title,
            'post_type' => ($_POST['post_type'] ?? '') === 'link' ? 'link' : 'story',
            'source_name' => trim((string) ($_POST['source_name'] ?? '')),
            'region' => isset($regions[$region]) ? $region : '',
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
            'published_at' => $publishedAt ?: null,
            'updated_at' => now(),
        ];

        if ($editor) {
            $placement = (string) ($_POST['placement'] ?? '');
            if (!in_array($placement, ['', 'hero', 'featured', 'desk_lead'], true)) {
                $placement = '';
            }
            $fields['placement'] = $placement;
            // The home paper must actually run the story; anything else
            // (deselected, or never chosen) stays self-canonical everywhere.
            $canonPick = (int) ($_POST['canonical_site_id'] ?? 0);
            $sitesPick = array_map('intval', (array) ($_POST['sites'] ?? []));
            $fields['canonical_site_id'] = ($canonPick && in_array($canonPick, $sitesPick, true)) ? $canonPick : null;
            $fields['review_note'] = trim((string) ($_POST['review_note'] ?? ''));
            if ($placement === 'hero') {
                db()->exec("UPDATE posts SET placement = '' WHERE placement = 'hero'");
            }
            $newCorrection = trim((string) ($_POST['correction'] ?? ''));
            $fields['correction'] = $newCorrection;
            $hadCorrection = trim((string) ($post['correction'] ?? ''));
            if ($newCorrection !== '' && $newCorrection !== $hadCorrection) {
                $fields['corrected_at'] = now();
            } elseif ($newCorrection === '') {
                $fields['corrected_at'] = null;
            }
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
            $id = pp_last_id('posts');
        }
        set_post_tags($id, (string) ($_POST['tags'] ?? ''));

        // Syndication: editors pick sites; authors' stories default to this
        // site. On the hub no paper is "this site" — an unassigned story
        // stays unmapped and shows as running nowhere on the network desk.
        if ($editor && isset($_POST['sites'])) {
            $picked = array_map('intval', (array) $_POST['sites']);
            set_post_sites($id, $picked ?: (pp_is_hub() ? [] : [current_site_id()]));
        } elseif (!pp_is_hub() && !site_ids_for_post($id)) {
            set_post_sites($id, [current_site_id()]);
        }

        flash_set(match ($status) {
            'published' => 'Published. The story is live.',
            'scheduled' => 'Scheduled. It goes live at the set time (the cron job flips it).',
            'in_review' => 'Submitted. An editor will pick it up from the review queue.',
            default     => 'Draft saved.',
        });
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
$postSites = $id ? site_ids_for_post($id) : (pp_is_hub() ? [] : [current_site_id()]);
$allSites = pp_paper_sites();   // the brochure hub never carries a story

$v = fn (string $key, string $default = '') => e((string) ($post[$key] ?? $default));
$st = (string) ($post['status'] ?? 'draft');

admin_header($id ? 'Edit story' : 'New story', 'posts');
flash_show();
if ($error) {
    echo '<div class="flash flash--error">' . e($error) . '</div>';
}
if (!$editor && !empty($post['review_note'])) {
    echo '<div class="flash flash--error"><strong>From the editor:</strong> ' . e($post['review_note']) . '</div>';
}
if (($post['origin'] ?? '') === 'ai') {
    echo '<div class="flash"><strong>AI-assisted working copy.</strong> The desk gathered and drafted this; it is not journalism yet. '
       . 'Verify every fact and every [VERIFY] mark against the sources, rework it in your own voice, and delete the provenance note — '
       . 'your byline answers for what ships.</div>';
}
?>

<div class="headrow">
  <h1 class="pagetitle"><?= $id ? 'Edit story' : 'New story' ?></h1>
  <div>
    <?php if ($st === 'in_review'): ?><span class="chip chip--scheduled">In review</span><?php endif; ?>
    <?php if ($id && $st === 'published'): ?>
    <a class="btn btn--ghost" href="/story/<?= $v('slug') ?>" target="_blank">View on the site →</a>
    <a class="btn btn--ghost" href="/card/<?= $v('slug') ?>.png" target="_blank">Social card</a>
    <?php endif; ?>
  </div>
</div>

<form method="post" id="storyform" data-post-id="<?= (int) $id ?>">
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
    <button type="button" data-bodyimg="1">Image</button>
    <button type="button" data-cmd="removeFormat">Clear</button>
  </div>
  <div class="editor" id="editor" contenteditable="true"><?= sanitize_html((string) ($post['body'] ?? '')) ?></div>
  <textarea name="body" id="bodyfield" style="display:none"><?= e((string) ($post['body'] ?? '')) ?></textarea>
  <input type="file" id="bodyimgupload" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
  <div class="edmeta"><span id="wordcount"></span><span id="autosavenote"><?= $id ? 'Autosaves every 30 seconds while drafting.' : 'Autosave starts after the first save.' ?></span></div>

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
          <option value="draft"<?= $st === 'draft' ? ' selected' : '' ?>>Draft — visible to the newsroom only</option>
          <option value="in_review"<?= $st === 'in_review' ? ' selected' : '' ?>>Submit for review — an editor signs off</option>
          <?php if ($editor): ?>
          <option value="published"<?= $st === 'published' ? ' selected' : '' ?>>Published — live on the site</option>
          <option value="scheduled"<?= $st === 'scheduled' ? ' selected' : '' ?>>Scheduled — goes live at the set time</option>
          <?php endif; ?>
        </select>
        <?php if ($editor): ?>
        <label for="published_at">Publish time</label>
        <input type="datetime-local" id="published_at" name="published_at"
               value="<?= !empty($post['published_at']) ? e(date('Y-m-d\TH:i', strtotime($post['published_at']))) : '' ?>">
        <p class="help">Leave blank on publish to use right now.</p>
        <label for="review_note">Note to the author · shown when you send a story back</label>
        <textarea id="review_note" name="review_note" class="prose" style="min-height:64px"><?= $v('review_note') ?></textarea>
        <?php else: ?>
        <p class="help">Publishing and scheduling happen at the editor's desk. Submit for review when the story is ready.</p>
        <?php endif; ?>
      </div>
      <div>
        <label for="meta_description">Search description · 155 characters</label>
        <textarea id="meta_description" name="meta_description" maxlength="255" style="min-height:64px"><?= $v('meta_description') ?></textarea>
        <label for="post_type">Kind</label>
        <?php $kind = (string) ($post['post_type'] ?? 'story'); ?>
        <select id="post_type" name="post_type">
          <option value="story"<?= $kind !== 'link' ? ' selected' : '' ?>>Original story — the headline opens the story page here</option>
          <option value="link"<?= $kind === 'link' ? ' selected' : '' ?>>Wire link — the headline links straight to the source outlet</option>
        </select>
        <label for="source_url">Source link · where a wire link points; kept as “source material” on a story</label>
        <input type="url" id="source_url" name="source_url" value="<?= $v('source_url') ?>">
        <label for="source_name">Source credit · the outlet's name on a wire link</label>
        <input type="text" id="source_name" name="source_name" value="<?= $v('source_name') ?>" maxlength="160" placeholder="The Edmonton Echo">
        <?php $regionOpts = setting_json('regions'); if ($regionOpts): ?>
        <label for="region">Region</label>
        <select id="region" name="region">
          <option value="">— No region —</option>
          <?php foreach ($regionOpts as $rk => $rl): ?>
          <option value="<?= e($rk) ?>"<?= (string) ($post['region'] ?? '') === $rk ? ' selected' : '' ?>><?= e($rl) ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if ($editor): ?>
        <label for="placement">Front page placement</label>
        <?php $pl = (string) ($post['placement'] ?? ''); ?>
        <select id="placement" name="placement">
          <option value=""<?= $pl === '' ? ' selected' : '' ?>>None — runs in the normal flow</option>
          <option value="hero"<?= $pl === 'hero' ? ' selected' : '' ?>>Hero — the lead story (replaces the current hero)</option>
          <option value="featured"<?= $pl === 'featured' ? ' selected' : '' ?>>Front featured — the band under the hero (up to four)</option>
          <option value="desk_lead"<?= $pl === 'desk_lead' ? ' selected' : '' ?>>Desk lead — tops its desk, front page and archive</option>
        </select>
        <label for="correction">Correction · what was wrong, and what's right</label>
        <textarea id="correction" name="correction" class="prose" style="min-height:64px" placeholder="An earlier version of this story said… In fact…"><?= $v('correction') ?></textarea>
        <p class="help">A correction renders in Bin Red above the story and joins the public corrections file.</p>
        <?php if (count($allSites) > 1): ?>
        <label>Runs on <span style="float:right;letter-spacing:.08em">home</span></label>
        <?php $canonCur = (int) ($post['canonical_site_id'] ?? 0); ?>
        <?php foreach ($allSites as $site): ?>
        <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:.04em;margin:6px 0">
          <input type="checkbox" name="sites[]" value="<?= (int) $site['id'] ?>" style="width:auto"<?= in_array((int) $site['id'], $postSites, true) ? ' checked' : '' ?>>
          <?= e($site['name']) ?><?= (int) $site['id'] === current_site_id() ? ' (this site)' : '' ?>
          <input type="radio" name="canonical_site_id" value="<?= (int) $site['id'] ?>" style="width:auto;margin-left:auto" title="Home paper — the other copies point their search canonical here"<?= $canonCur === (int) $site['id'] ? ' checked' : '' ?>>
        </label>
        <?php endforeach; ?>
        <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:.04em;margin:6px 0">
          <input type="radio" name="canonical_site_id" value="0" style="width:auto"<?= $canonCur === 0 ? ' checked' : '' ?>>
          No home paper — each copy is its own canonical
        </label>
        <p class="help">A story runs on every site ticked here — one filing, the whole network. For widely-syndicated stories, mark one ticked paper <em>home</em> and the other copies point their search canonical there, so a single paper accrues the ranking.</p>
        <?php else: ?>
        <input type="hidden" name="sites[]" value="<?= current_site_id() ?>">
        <?php endif; ?>
        <?php endif; ?>
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
