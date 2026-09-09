<?php
/**
 * One revision, in full — the story as it stood at that save. Restoring
 * copies this text back onto the story (and that restore becomes a new
 * revision itself, so the history never loses a step). Authors can restore
 * their own stories; editors any.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_login();

$rev = pp_revision_by_id((int) ($_GET['id'] ?? $_POST['id'] ?? 0));
if (!$rev) {
    flash_set('That revision is gone — history is capped per story.', true);
    redirect('posts.php');
}
$stmt = db()->prepare('SELECT * FROM posts WHERE id = ?');
$stmt->execute([(int) $rev['post_id']]);
$post = $stmt->fetch();
if (!$post || !can_edit_post($user, $post)) {
    http_response_code(403);
    exit('That story isn\'t yours to open.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    csrf_check();
    // Restoring rewrites the story's live text, so it obeys the same write
    // policy as any other edit: an author may reshape their own draft, but
    // once a story is published or scheduled, putting an old version back
    // is an editor's call — this was the F02 approval bypass.
    if ($denied = pp_post_write_denied($user, $post, 'restore')) {
        http_response_code(403);
        exit(e($denied));
    }
    // The revision body is sanitized on the way back in: history rows
    // written before the parser-based sanitizer may carry markup the old
    // regex let through, and a restore must not resurrect it.
    $wrote = pp_guarded_post_update((int) $post['id'], [
        'title'            => (string) $rev['title'],
        'lede'             => (string) ($rev['lede'] ?? ''),
        'body'             => sanitize_html((string) ($rev['body'] ?? '')),
        'meta_description' => (string) $rev['meta_description'],
        'correction'       => (string) ($rev['correction'] ?? ''),
        'image'            => (string) $rev['image'],
        'image_caption'    => (string) $rev['image_caption'],
        'updated_at'       => now(),
    ], is_editor($user) ? null : ['draft', 'in_review']);
    if (!$wrote) {
        flash_set('Not restored: an editor published or scheduled this story while you had the revision open.', true);
        redirect('post-edit.php?id=' . (int) $post['id']);
    }
    pp_post_snapshot((int) $post['id'], 'restore', $user['name']);
    pp_audit('story.restored', mb_substr((string) $rev['title'], 0, 120),
             'story #' . (int) $post['id'] . ' put back to the ' . $rev['created_at'] . ' revision');
    flash_set('Restored — the story now reads as it did at ' . fmt_date($rev['created_at'], 'M j, g:i a') . '. This restore is a revision too.');
    redirect('post-edit.php?id=' . (int) $post['id']);
}

admin_header('Revision', 'posts');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle">Revision · <?= e(fmt_date($rev['created_at'], 'M j, Y g:i a')) ?></h1>
  <a class="btn btn--ghost" href="post-edit.php?id=<?= (int) $post['id'] ?>">← Back to the story</a>
</div>
<p class="pagesub">
  <strong><?= e($post['title']) ?></strong> as it stood at this save
  — recorded by <?= e($rev['saved_by'] ?: 'the system') ?>, reason <span class="mono"><?= e($rev['reason']) ?></span>.
  The story's current text is untouched until you restore.
</p>

<form method="post" class="inline" onsubmit="return confirm('Put the story back to this version? The current text stays in history — nothing is lost.')">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="restore">
  <input type="hidden" name="id" value="<?= (int) $rev['id'] ?>">
  <button class="btn" type="submit">Restore this version</button>
</form>

<div class="panel" style="margin-top:14px">
  <h2><?= e($rev['title']) ?></h2>
  <?php if (trim((string) $rev['lede']) !== ''): ?>
  <p style="font-size:17px"><em><?= e((string) $rev['lede']) ?></em></p>
  <?php endif; ?>
  <?php if (trim((string) $rev['correction']) !== ''): ?>
  <div class="flash">Correction on file at this point: <?= e((string) $rev['correction']) ?></div>
  <?php endif; ?>
  <div class="prose" style="max-width:72ch">
    <?= sanitize_html((string) $rev['body']) ?>
  </div>
  <?php if (trim((string) $rev['meta_description']) !== ''): ?>
  <p class="help" style="margin-top:16px">Search description at this point: <?= e($rev['meta_description']) ?></p>
  <?php endif; ?>
  <?php if (trim((string) $rev['image']) !== ''): ?>
  <p class="help">Image: <span class="mono"><?= e($rev['image']) ?></span><?= $rev['image_caption'] !== '' ? ' · ' . e($rev['image_caption']) : '' ?></p>
  <?php endif; ?>
</div>

<?php admin_footer(); ?>
