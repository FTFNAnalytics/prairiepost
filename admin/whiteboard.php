<?php
/**
 * The whiteboard: this paper's private board (app/whiteboard.php).
 * Reader tips from the /tips form land here as unverified tips; the
 * newsroom pins its own leads, rumours and facts beside them. Editors and
 * administrators only, because a tipster's contact appears on this page
 * and nowhere else.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_editor();
$siteId = current_site_id();

$type = (string) ($_GET['type'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$type = isset(PP_WB_TYPES[$type]) ? $type : '';
$status = isset(PP_WB_STATUSES[$status]) ? $status : '';
$here = static fn (string $t, string $s): string => 'whiteboard.php' . (($q = http_build_query(array_filter(['type' => $t, 'status' => $s]))) !== '' ? "?$q" : '');

$formError = '';
$form = ['body' => '', 'source_url' => '', 'note_type' => 'lead', 'status' => 'unverified'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $back = $here((string) ($_POST['f_type'] ?? ''), (string) ($_POST['f_status'] ?? ''));

    if ($action === 'add') {
        [$newId, $formError] = pp_wb_add($siteId, $user, $_POST);
        if ($formError === '') {
            pp_audit('whiteboard.add', 'note:' . $newId, (string) (pp_wb_note($siteId, $newId)['note_type'] ?? ''));
            flash_set('Pinned to the whiteboard.');
            redirect($back);
        }
        // Keep what was typed; fall through and show it with the error.
        $form = array_merge($form, array_intersect_key(array_map(fn ($v) => is_string($v) ? $v : '', $_POST), $form));
    } elseif ($action === 'edit' && ($note = pp_wb_note($siteId, $id))) {
        $error = pp_wb_edit($siteId, $id, $_POST);
        if ($error === '') {
            $saved = pp_wb_note($siteId, $id) ?? [];
            pp_audit('whiteboard.edit', 'note:' . $id, ($saved['note_type'] ?? '') . ', ' . ($saved['status'] ?? ''));
            flash_set('Note saved.');
            redirect($back . '#note-' . $id);
        }
        flash_set($error, true);
        redirect($back . (str_contains($back, '?') ? '&' : '?') . 'edit=' . $id . '#note-' . $id);
    } elseif ($action === 'status') {
        $to = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
        if (pp_wb_set_status($siteId, $id, $to)) {
            pp_audit('whiteboard.status', 'note:' . $id, $to);
        }
        redirect($back . '#note-' . $id);
    } elseif ($action === 'delete' && ($note = pp_wb_note($siteId, $id))) {
        if (pp_wb_delete($siteId, $id)) {
            // The record says what kind of note went, never what it said.
            pp_audit('whiteboard.delete', 'note:' . $id, $note['note_type'] . ' from ' . $note['origin']);
            flash_set('Note deleted.');
        }
        redirect($back);
    } elseif ($action === 'draft') {
        $postId = pp_wb_draft($siteId, $id, $user);
        if ($postId !== null) {
            pp_audit('whiteboard.draft', 'note:' . $id, 'post:' . $postId);
            redirect('post-edit.php?id=' . $postId);
        }
        redirect($back);
    } else {
        redirect($back);
    }
}

$notes = pp_wb_notes($siteId, $type, $status);
$waiting = pp_wb_waiting($siteId);
$editId = (int) ($_GET['edit'] ?? 0);
$chip = ['tip' => 'chip--scheduled', 'lead' => 'chip--published', 'rumour' => 'chip--in_review', 'fact' => 'chip--draft'];
$keep = '<input type="hidden" name="f_type" value="' . e($type) . '"><input type="hidden" name="f_status" value="' . e($status) . '">';
$options = static function (array $choices, string $picked): string {
    $html = '';
    foreach ($choices as $value => $label) {
        $html .= '<option value="' . e($value) . '"' . ($value === $picked ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html;
};

admin_header('Whiteboard', 'whiteboard');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle">Whiteboard</h1>
  <?php if ($waiting): ?><span class="chip chip--scheduled"><?= $waiting ?> reader tip<?= $waiting === 1 ? '' : 's' ?> waiting</span><?php endif; ?>
</div>
<p class="pagesub">Tips from readers, and the newsroom's leads, rumours and facts. Private to
<?= e(setting('site_title', 'this paper')) ?>'s editors: nothing here is published.
<?php if (!pp_is_hub()): ?>Readers send tips from <a href="<?= e(url('tips')) ?>" rel="external">the tips page</a>.<?php endif; ?></p>

<div class="panel">
  <h2>Pin a note</h2>
  <?php if ($formError !== ''): ?><div class="flash flash--error"><?= e($formError) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?><?= $keep ?>
    <input type="hidden" name="action" value="add">
    <label for="wb-body">What did you hear or notice?</label>
    <textarea id="wb-body" name="body" rows="3" maxlength="4000" required><?= e($form['body']) ?></textarea>
    <div class="formgrid">
      <div>
        <label for="wb-url">Source link <span class="help" style="display:inline">optional</span></label>
        <input id="wb-url" type="url" name="source_url" maxlength="1000" value="<?= e($form['source_url']) ?>" placeholder="https://">
      </div>
      <div class="formgrid">
        <div>
          <label for="wb-type">Type</label>
          <select id="wb-type" name="note_type"><?= $options(PP_WB_TYPES, $form['note_type']) ?></select>
        </div>
        <div>
          <label for="wb-status">Status</label>
          <select id="wb-status" name="status"><?= $options(PP_WB_STATUSES, $form['status']) ?></select>
        </div>
      </div>
    </div>
    <p style="margin-top:16px"><button class="btn" type="submit">Pin note</button></p>
  </form>
</div>

<form method="get" class="wb-filters" action="whiteboard.php">
  <select name="type" aria-label="Type"><option value="">All types</option><?= $options(PP_WB_TYPES, $type) ?></select>
  <select name="status" aria-label="Status"><option value="">All statuses</option><?= $options(PP_WB_STATUSES, $status) ?></select>
  <button class="btn btn--ghost btn--small" type="submit">Filter</button>
  <span class="help" style="margin:0"><?= count($notes) ?> note<?= count($notes) === 1 ? '' : 's' ?></span>
</form>

<div class="panel">
  <?php if (!$notes): ?>
  <p><?= $type || $status ? 'No notes match these filters.' : 'The whiteboard is empty. Reader tips and the newsroom\'s notes will gather here.' ?></p>
  <?php endif; ?>
  <?php foreach ($notes as $n): $nid = (int) $n['id']; $reader = $n['origin'] === 'reader'; ?>
  <div class="newsitem wb-note<?= $n['status'] === 'debunked' ? ' is-used' : '' ?>" id="note-<?= $nid ?>">
    <div class="t">
      <?php if ($editId === $nid): ?>
      <form method="post"><?= csrf_field() ?><?= $keep ?>
        <input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= $nid ?>">
        <label for="wb-edit-<?= $nid ?>">Note</label>
        <textarea id="wb-edit-<?= $nid ?>" name="body" rows="4" maxlength="4000" required><?= e((string) $n['body']) ?></textarea>
        <div class="formgrid">
          <div>
            <label for="wb-edit-url-<?= $nid ?>">Source link</label>
            <input id="wb-edit-url-<?= $nid ?>" type="url" name="source_url" maxlength="1000" value="<?= e((string) $n['source_url']) ?>">
          </div>
          <div class="formgrid">
            <div><label for="wb-edit-type-<?= $nid ?>">Type</label><select id="wb-edit-type-<?= $nid ?>" name="note_type"><?= $options(PP_WB_TYPES, (string) $n['note_type']) ?></select></div>
            <div><label for="wb-edit-status-<?= $nid ?>">Status</label><select id="wb-edit-status-<?= $nid ?>" name="status"><?= $options(PP_WB_STATUSES, (string) $n['status']) ?></select></div>
          </div>
        </div>
        <p style="margin-top:12px"><button class="btn btn--small" type="submit">Save</button>
          <a class="btn btn--ghost btn--small" href="<?= e($here($type, $status)) ?>#note-<?= $nid ?>">Cancel</a></p>
      </form>
      <?php else: ?>
      <span class="chip <?= e($chip[$n['note_type']] ?? 'chip--draft') ?>"><?= e(PP_WB_TYPES[$n['note_type']] ?? $n['note_type']) ?></span>
      <div class="wb-body"><?= nl2br(e((string) $n['body'])) ?></div>
      <?php if ((string) $n['source_url'] !== ''): ?>
      <a class="wb-link" href="<?= e((string) $n['source_url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e((string) $n['source_url']) ?></a>
      <?php endif; ?>
      <?php if ($reader): ?>
      <div class="wb-private">
        <?php $contact = (string) $n['contact']; ?>
        From <?= (string) $n['posted_by'] !== '' ? e((string) $n['posted_by']) : 'a reader who left no name' ?><?php if ($contact !== ''): ?> ·
        <?php if (filter_var($contact, FILTER_VALIDATE_EMAIL)): ?><a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a><?php else: ?><?= e($contact) ?><?php endif; ?>
        <?php else: ?> · no way to reply<?php endif; ?>
        <span class="wb-private__note">Private: never copied into a draft.</span>
      </div>
      <?php endif; ?>
      <span class="src"><?= $reader ? 'Sent through the tips page' : 'Pinned by ' . e((string) $n['posted_by']) ?> · <?= e(fmt_date((string) $n['created_at'], 'M j, Y g:i a')) ?></span>
      <?php endif; ?>
    </div>
    <?php if ($editId !== $nid): ?>
    <div class="acts">
      <form method="post" class="inline"><?= csrf_field() ?><?= $keep ?>
        <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $nid ?>">
        <select name="status" aria-label="Status" onchange="this.form.submit()"><?= $options(PP_WB_STATUSES, (string) $n['status']) ?></select>
        <noscript><button class="btn btn--ghost btn--small" type="submit">Set</button></noscript>
      </form>
      <?php if ((int) ($n['post_id'] ?? 0) > 0): ?>
      <a class="btn btn--ghost btn--small" href="post-edit.php?id=<?= (int) $n['post_id'] ?>">Open draft</a>
      <?php else: ?>
      <form method="post" class="inline"><?= csrf_field() ?><?= $keep ?>
        <input type="hidden" name="action" value="draft"><input type="hidden" name="id" value="<?= $nid ?>">
        <button class="btn btn--ghost btn--small" type="submit">Turn into draft</button>
      </form>
      <?php endif; ?>
      <a class="btn btn--ghost btn--small" href="<?= e($here($type, $status)) ?><?= $type || $status ? '&amp;' : '?' ?>edit=<?= $nid ?>#note-<?= $nid ?>">Edit</a>
      <form method="post" class="inline" onsubmit="return confirm('Delete this note for good?')"><?= csrf_field() ?><?= $keep ?>
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $nid ?>">
        <button class="btn btn--danger btn--small" type="submit">Delete</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<?php admin_footer(); ?>
