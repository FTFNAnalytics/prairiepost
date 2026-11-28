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
$canPublish = pp_can_publish($user);

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
        exit('That story belongs to another author or paper. Editors can open stories on their assigned papers; authors open their own.');
    }
}

$allowedStatuses = $canPublish
    ? ['draft', 'in_review', 'published', 'scheduled']
    : ['draft', 'in_review'];
$allowedEditorSites = pp_user_allowed_site_ids($user, ['admin', 'editor']);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // The write policy runs against the post's PERSISTED state: an author
    // cannot change a story an editor has published or scheduled — not by
    // editing it, and not by demoting it back to draft on the way through.
    if ($post && ($denied = pp_post_write_denied($user, $post))) {
        $error = $denied;
    }
    $title    = trim((string) ($_POST['title'] ?? ''));
    $lede     = trim((string) ($_POST['lede'] ?? ''));
    $body     = sanitize_html((string) ($_POST['body'] ?? ''));
    $status   = (string) ($_POST['status'] ?? '');
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'draft';
    }
    $publishedAt = trim((string) ($_POST['published_at'] ?? ''));
    $sourceUrlInput = trim((string) ($_POST['source_url'] ?? ''));
    $imageInput = trim((string) ($_POST['image'] ?? ''));

    if ($error !== '') {
        // Refused above — fall through to re-render with the reason.
    } elseif ($title === '') {
        $error = 'The story needs a headline before it can be saved.';
    } elseif ($sourceUrlInput !== '' && (!preg_match('#^https?://#i', $sourceUrlInput) || strlen($sourceUrlInput) > 600 || preg_match('/[\r\n\x00-\x1f]/', $sourceUrlInput))) {
        $error = 'The source URL must be a valid http:// or https:// address (at most 600 characters).';
    } elseif ($imageInput !== '' && (!preg_match('#^(https?://|/uploads/|/assets/)#i', $imageInput) || strlen($imageInput) > 600 || preg_match('/[\r\n\x00-\x1f"\'<>]/', $imageInput))) {
        $error = 'The image path must be an /uploads/ path or an http:// or https:// URL.';
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
        $postType = ($_POST['post_type'] ?? '') === 'link' ? 'link' : 'story';
        $fields = [
            'title' => $title,
            'post_type' => $postType,
            'source_name' => trim((string) ($_POST['source_name'] ?? '')),
            'region' => isset($regions[$region]) ? $region : '',
            'category_id' => (int) ($_POST['category_id'] ?? 0) ?: null,
            'byline' => pp_normalize_byline((string) ($_POST['byline'] ?? ''), $postType),
            'dateline' => trim((string) ($_POST['dateline'] ?? '')),
            'lede' => $lede,
            'body' => $body,
            'image' => $imageInput,
            'image_caption' => trim((string) ($_POST['image_caption'] ?? '')),
            'image_credit' => trim((string) ($_POST['image_credit'] ?? '')),
            'meta_description' => mb_substr(trim((string) ($_POST['meta_description'] ?? '')), 0, 255),
            'source_url' => $sourceUrlInput,
            'status' => $status,
            'published_at' => $publishedAt ?: null,
            'updated_at' => now(),
        ];

        // Publishing or scheduling by hand consumes the publish-on-image
        // flag, as the Stories list's bulk publish does.
        if ($status === 'published' || $status === 'scheduled') {
            $fields['awaiting_image'] = 0;
        }

        $placement = '';
        $existingSites = $post ? site_ids_for_post((int) $post['id']) : [];
        $existingPlacements = $post ? pp_post_site_placements((int) $post['id']) : [];
        $finalSites = $existingSites;
        $finalPlacements = $existingPlacements;
        $recordRedirectFrom = '';

        if ($editor) {
            $placement = pp_valid_placement((string) ($_POST['placement'] ?? ''));
            $fields['placement'] = $placement;
            $fields['review_note'] = trim((string) ($_POST['review_note'] ?? ''));

            // Resolve the target site set, respecting tenant-scoped editors:
            // a scoped editor can only toggle papers they manage; sibling papers
            // outside their scope stay attached untouched.
            if (isset($_POST['sites'])) {
                $picked = array_values(array_unique(array_filter(array_map('intval', (array) $_POST['sites']), fn ($sid) => $sid > 0)));
                if ($allowedEditorSites !== null) {
                    $picked = array_values(array_intersect($picked, $allowedEditorSites));
                    $untouched = array_values(array_diff($existingSites, $allowedEditorSites));
                    $finalSites = array_values(array_unique(array_merge($untouched, $picked)));
                } else {
                    $finalSites = $picked;
                }
                if (!$finalSites && !pp_is_hub()) {
                    $finalSites = [current_site_id()];
                }
            } elseif (!$finalSites && !pp_is_hub()) {
                $finalSites = [current_site_id()];
            }

            // Build per-site placement map:
            // - On a paper, the main "placement" selector rules for current_site_id().
            // - site_placement[sid] overrides individual sites (for sites this editor manages).
            // - Newly added sites without an explicit site_placement inherit $placement.
            $postedSitePl = is_array($_POST['site_placement'] ?? null) ? $_POST['site_placement'] : [];
            $nextPlacements = [];
            foreach ($finalSites as $sid) {
                $canManageSite = $allowedEditorSites === null || in_array($sid, $allowedEditorSites, true);
                if (!$canManageSite) {
                    $nextPlacements[$sid] = $existingPlacements[$sid] ?? '';
                } elseif (!pp_is_hub() && $sid === current_site_id()) {
                    $nextPlacements[$sid] = $placement;
                } elseif (array_key_exists($sid, $postedSitePl) || array_key_exists((string) $sid, $postedSitePl)) {
                    $raw = $postedSitePl[$sid] ?? $postedSitePl[(string) $sid] ?? '';
                    $nextPlacements[$sid] = pp_valid_placement((string) $raw);
                } elseif (array_key_exists($sid, $existingPlacements)) {
                    $nextPlacements[$sid] = pp_is_hub() ? $placement : $existingPlacements[$sid];
                } else {
                    $nextPlacements[$sid] = $placement;
                }
            }
            $finalPlacements = $nextPlacements;

            // The home paper must actually run the story; anything else
            // (deselected, or never chosen) stays self-canonical everywhere.
            $canonPick = (int) ($_POST['canonical_site_id'] ?? 0);
            $fields['canonical_site_id'] = ($canonPick && in_array($canonPick, $finalSites, true)) ? $canonPick : null;

            $newCorrection = trim((string) ($_POST['correction'] ?? ''));
            $fields['correction'] = $newCorrection;
            $hadCorrection = trim((string) ($post['correction'] ?? ''));
            if ($newCorrection !== '' && $newCorrection !== $hadCorrection) {
                $fields['corrected_at'] = now();
            } elseif ($newCorrection === '') {
                $fields['corrected_at'] = null;
            }
        } elseif (!pp_is_hub() && !$finalSites) {
            $finalSites = [current_site_id()];
        }

        if ($post) {
            // Preserve the live URL once a story has ever been published or
            // scheduled unless an editor explicitly edits the URL slug field
            // (in which case we record a 301 redirect from the old slug).
            $oldSlug = trim((string) ($post['slug'] ?? ''));
            $wasLive = pp_post_locked($post) || !empty($post['published_at']);
            $requestedSlug = $editor && isset($_POST['slug']) ? trim((string) $_POST['slug']) : '';
            if ($wasLive && $oldSlug !== '') {
                if ($requestedSlug !== '' && slugify($requestedSlug) !== $oldSlug) {
                    $fields['slug'] = unique_post_slug($requestedSlug, (int) $post['id']);
                    if ($fields['slug'] !== $oldSlug) {
                        $recordRedirectFrom = $oldSlug;
                    }
                }
            } else {
                if ($requestedSlug !== '' && slugify($requestedSlug) !== $oldSlug) {
                    $fields['slug'] = unique_post_slug($requestedSlug, (int) $post['id']);
                } else {
                    $fields['slug'] = unique_post_slug($title, (int) $post['id']);
                }
            }
            // Authors' writes are conditional on the story STILL being in a
            // state they may write to — the check and the write are one
            // statement, so an editor publishing mid-edit wins the race and
            // the stale save lands nowhere. Editors write unconditionally.
            $wrote = pp_guarded_post_update((int) $post['id'], $fields,
                $editor ? null : ['draft', 'in_review']);
            if ($wrote) {
                $id = (int) $post['id'];
                pp_post_snapshot($id, 'edit', $user['name']);
            } else {
                $error = pp_guarded_update_failure((int) $post['id']) === 'missing'
                    ? 'Not saved: this story was deleted while you were editing. The text below is your unsaved copy.'
                    : 'Not saved: an editor published or scheduled this story while you were editing. '
                    . 'Reload to see the live version — the text below is your unsaved copy.';
            }
        } else {
            $fields['slug'] = unique_post_slug($title);
            $fields['author_id'] = (int) $user['id'];
            $fields['created_at'] = now();
            $cols = implode(', ', array_keys($fields));
            $marks = implode(', ', array_fill(0, count($fields), '?'));
            db()->prepare("INSERT INTO posts ($cols) VALUES ($marks)")->execute(array_values($fields));
            $id = pp_last_id('posts');
            pp_post_snapshot($id, 'create', $user['name']);
        }

        // Dependent writes (tags, site mapping, per-site hero clearing, 301
        // redirects, agent queue) only after a write that actually landed.
        if ($error === '') {
            set_post_tags($id, (string) ($_POST['tags'] ?? ''));

            if ($editor) {
                $heroSites = [];
                foreach ($finalPlacements as $sid => $pl) {
                    if ($pl === 'hero') {
                        $heroSites[] = (int) $sid;
                    }
                }
                if ($heroSites) {
                    pp_clear_hero_for_sites($heroSites, $id);
                }
                set_post_sites($id, $finalSites, $finalPlacements);
                pp_sync_legacy_post_placement($id);
            } elseif (!pp_is_hub() && !site_ids_for_post($id)) {
                set_post_sites($id, [current_site_id()]);
            }

            if ($recordRedirectFrom !== '') {
                $redirSites = $finalSites ?: (pp_is_hub() ? [] : [current_site_id()]);
                pp_record_slug_redirect($id, $recordRedirectFrom, $redirSites, (string) ($fields['slug'] ?? ''));
            }

            // The agent desk's auto-queue fires on the transition to published —
            // per-kind checkboxes in the hub's Settings, all off by default.
            if ($status === 'published' && (($post['status'] ?? '') !== 'published')) {
                require_once dirname(__DIR__) . '/app/agents.php';
                pp_agent_auto_enqueue($id);
            }
            if ($status === 'published') {
                pp_indexnow_notify_post($id);
            }

            flash_set(match ($status) {
                'published' => 'Published. The story is live.',
                'scheduled' => 'Scheduled. It goes live at the set time (the cron job flips it).',
                'in_review' => 'Submitted. An editor will pick it up from the review queue.',
                default     => 'Draft saved.',
            });
            redirect('post-edit.php?id=' . $id);
        }
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
$postSitePlacements = $id ? pp_post_site_placements($id) : [];
$allSites = pp_paper_sites();   // the brochure hub never carries a story
if ($allowedEditorSites !== null) {
    $allSites = array_values(array_filter($allSites, fn ($s) => in_array((int) $s['id'], $allowedEditorSites, true)));
}
$slugRedirects = $id ? pp_slug_redirects_for_post($id) : [];

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
          <?php if ($canPublish): ?>
          <option value="published"<?= $st === 'published' ? ' selected' : '' ?>>Published — live on the site</option>
          <option value="scheduled"<?= $st === 'scheduled' ? ' selected' : '' ?>>Scheduled — goes live at the set time</option>
          <?php endif; ?>
        </select>
        <?php if ($editor): ?>
        <?php if ($canPublish): ?>
        <label for="published_at">Publish time</label>
        <input type="datetime-local" id="published_at" name="published_at"
               value="<?= !empty($post['published_at']) ? e(date('Y-m-d\TH:i', strtotime($post['published_at']))) : '' ?>">
        <p class="help">Leave blank on publish to use right now.</p>
        <?php endif; ?>
        <label for="review_note">Note to the author · shown when you send a story back</label>
        <textarea id="review_note" name="review_note" class="prose" style="min-height:64px"><?= $v('review_note') ?></textarea>
        <?php if ($id): ?>
        <label for="slug">URL slug · changing a live slug emits an automatic 301 redirect</label>
        <input type="text" id="slug" name="slug" value="<?= $v('slug') ?>" maxlength="191" class="mono">
        <?php if ($slugRedirects): ?>
        <p class="help">Redirecting here (301): <?php foreach ($slugRedirects as $oldS): ?><span class="chip chip--used">/story/<?= e($oldS) ?></span> <?php endforeach; ?></p>
        <?php else: ?>
        <p class="help">Headline edits keep the live URL stable unless you change this field deliberately.</p>
        <?php endif; ?>
        <?php endif; ?>
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
        <label for="placement"><?= pp_is_hub() ? 'Default front page placement' : 'Front page placement (this paper)' ?></label>
        <?php
          $pl = (!pp_is_hub() && array_key_exists(current_site_id(), $postSitePlacements))
              ? (string) $postSitePlacements[current_site_id()]
              : (string) ($post['placement'] ?? '');
        ?>
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
        <label>Runs on <span style="float:right;letter-spacing:.08em">placement · home</span></label>
        <?php $canonCur = (int) ($post['canonical_site_id'] ?? 0); ?>
        <?php foreach ($allSites as $site):
          $sid = (int) $site['id'];
          $isHere = !pp_is_hub() && $sid === current_site_id();
          $sPl = (string) ($postSitePlacements[$sid] ?? '');
        ?>
        <div style="display:flex;align-items:center;gap:8px;margin:6px 0">
          <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:.04em;margin:0;flex:1">
            <input type="checkbox" name="sites[]" value="<?= $sid ?>" style="width:auto"<?= in_array($sid, $postSites, true) ? ' checked' : '' ?>>
            <?= e($site['name']) ?><?= $isHere ? ' (this site)' : '' ?>
          </label>
          <?php if (!$isHere): ?>
          <select name="site_placement[<?= $sid ?>]" aria-label="Placement on <?= e($site['name']) ?>" style="width:auto;padding:2px 6px;font-size:.82rem">
            <option value=""<?= $sPl === '' ? ' selected' : '' ?>>Normal</option>
            <option value="hero"<?= $sPl === 'hero' ? ' selected' : '' ?>>Hero</option>
            <option value="featured"<?= $sPl === 'featured' ? ' selected' : '' ?>>Featured</option>
            <option value="desk_lead"<?= $sPl === 'desk_lead' ? ' selected' : '' ?>>Desk lead</option>
          </select>
          <?php endif; ?>
          <input type="radio" name="canonical_site_id" value="<?= $sid ?>" style="width:auto" title="Home paper — the other copies point their search canonical here"<?= $canonCur === $sid ? ' checked' : '' ?>>
        </div>
        <?php endforeach; ?>
        <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:.04em;margin:6px 0">
          <input type="radio" name="canonical_site_id" value="0" style="width:auto"<?= $canonCur === 0 ? ' checked' : '' ?>>
          No home paper — each copy is its own canonical
        </label>
        <p class="help">A story runs on every site ticked here — each paper keeps its own front-page placement independently. Mark one ticked paper <em>home</em> to point sibling canonicals there.</p>
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

<?php if ($id && $editor && pp_is_hub()): ?>
<div class="panel">
  <h2>Agents</h2>
  <p class="pagesub" style="margin-bottom:12px">Queue a machine pass over this story. Every result waits on the <a href="agents.php">agent desk</a> as a proposal — nothing changes until an editor approves it.</p>
  <p>
    <?php foreach (['linkify' => 'Run linkifier', 'seo_meta' => 'Write search description', 'tagger' => 'Suggest tags'] as $k => $label): ?>
    <form method="post" action="agents.php" class="inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="queue">
      <input type="hidden" name="kind" value="<?= $k ?>">
      <input type="hidden" name="post_id" value="<?= (int) $id ?>">
      <button class="btn btn--outline btn--small" type="submit"><?= $label ?></button>
    </form>
    <?php endforeach; ?>
  </p>
</div>
<?php endif; ?>

<?php if ($id): $revisions = pp_post_revisions((int) $id); ?>
<div class="panel">
  <h2>History</h2>
  <p class="pagesub" style="margin-bottom:12px">Every save keeps a copy of the story as it stood — the record of what was live, when. Open one to read it in full or put it back.</p>
  <?php if (!$revisions): ?>
  <p>No revisions yet — the first save writes one.</p>
  <?php else: ?>
  <table class="tbl">
    <thead><tr><th>Saved</th><th>By</th><th>Why</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($revisions as $rev): ?>
      <tr>
        <td class="mono" style="white-space:nowrap"><?= e(fmt_date($rev['created_at'], 'M j, g:i a')) ?></td>
        <td><?= e($rev['saved_by'] ?: '—') ?></td>
        <td class="mono"><?= e($rev['reason']) ?></td>
        <td><a class="btn btn--ghost btn--small" href="revision.php?id=<?= (int) $rev['id'] ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<script src="/assets/js/editor.js"></script>
<?php admin_footer(); ?>
