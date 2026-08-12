<?php
/**
 * The agent desk — the queue where machine passes wait for a person.
 * Every task's result is a proposal: an editor sees exactly what would
 * change, then approves (applied through the same sanitizer as any save,
 * stamped with who and when) or rejects with a note. Auto-apply does not
 * exist here on purpose.
 *
 * Hub only, editors and up.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/agents.php';
require __DIR__ . '/_layout.php';
$user = require_editor();
if (!pp_is_hub()) {
    http_response_code(404);
    exit('Not found.');
}

$kinds = pp_agent_kinds();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $task = null;
    if ($taskId) {
        $stmt = db()->prepare('SELECT * FROM agent_tasks WHERE id = ?');
        $stmt->execute([$taskId]);
        $task = $stmt->fetch() ?: null;
    }

    if ($action === 'queue') {
        $kind = (string) ($_POST['kind'] ?? '');
        $postId = (int) ($_POST['post_id'] ?? 0);
        $exists = db()->prepare('SELECT 1 FROM posts WHERE id = ?');
        $exists->execute([$postId]);
        if (!$postId || !$exists->fetch()) {
            flash_set('That story id doesn\'t exist.', true);
        } else {
            $r = pp_agent_enqueue($kind, $postId, $user['name']);
            if ($r === 'queued') {
                pp_audit('agent.queued', $kind, "story #$postId");
            }
            flash_set($r === 'queued' ? 'Queued — the runner takes it within ten minutes, or Run now below.'
                                      : 'That story already has an open task of this kind.', $r !== 'queued');
        }
        redirect('agents.php');
    }

    if ($action === 'run_now') {
        set_time_limit(300);
        $report = pp_agents_process(5);
        flash_set('Runner pass: ' . str_replace("\n", ' · ', $report));
        redirect('agents.php');
    }

    if ($action === 'approve' && $task && $task['status'] === 'needs_review') {
        $result = (array) json_decode((string) $task['result'], true);
        $payload = (array) json_decode((string) $task['payload'], true);
        $stmt = db()->prepare('SELECT * FROM posts WHERE id = ?');
        $stmt->execute([(int) $task['post_id']]);
        $post = $stmt->fetch();
        $fail = null;
        if (!$post) {
            $fail = 'the story no longer exists';
        } elseif ($task['kind'] === 'linkify') {
            // A proposal built against an older body must not clobber edits.
            $basedOn = (string) ($result['based_on'] ?? $payload['post_updated_at'] ?? '');
            if ($basedOn !== (string) ($post['updated_at'] ?? '')) {
                $fail = 'the story changed since the agent ran — reject this and queue it again';
            } else {
                db()->prepare('UPDATE posts SET body = ?, updated_at = ? WHERE id = ?')
                    ->execute([sanitize_html((string) ($result['proposed_body'] ?? '')), now(), (int) $post['id']]);
            }
        } elseif ($task['kind'] === 'seo_meta') {
            if (trim((string) $post['meta_description']) !== '') {
                $fail = 'someone filled the description while this waited — nothing applied';
            } else {
                db()->prepare('UPDATE posts SET meta_description = ?, updated_at = ? WHERE id = ?')
                    ->execute([mb_substr((string) ($result['proposed'] ?? ''), 0, 155), now(), (int) $post['id']]);
            }
        } elseif ($task['kind'] === 'tagger') {
            $merged = array_unique(array_merge((array) ($result['current_tags'] ?? []), (array) ($result['proposed_tags'] ?? [])));
            set_post_tags((int) $post['id'], implode(', ', $merged));
        }
        if ($fail === null) {
            db()->prepare("UPDATE agent_tasks SET status = 'approved', reviewed_by = ?, reviewed_at = ? WHERE id = ?")
                ->execute([$user['name'], now(), $taskId]);
            pp_audit('agent.approved', $task['kind'], "task #$taskId applied to story #{$task['post_id']}");
            flash_set('Approved and applied — stamped with your name.');
        } else {
            db()->prepare("UPDATE agent_tasks SET status = 'rejected', reviewed_by = ?, reviewed_at = ?, log = ? WHERE id = ?")
                ->execute([$user['name'], now(), trim((string) $task['log'] . ' · not applied: ' . $fail), $taskId]);
            pp_audit('agent.rejected', $task['kind'], "task #$taskId not applied: $fail");
            flash_set("Couldn't apply: $fail.", true);
        }
        redirect('agents.php');
    }

    if ($action === 'reject' && $task && $task['status'] === 'needs_review') {
        $note = trim((string) ($_POST['note'] ?? ''));
        db()->prepare("UPDATE agent_tasks SET status = 'rejected', reviewed_by = ?, reviewed_at = ?, log = ? WHERE id = ?")
            ->execute([$user['name'], now(),
                       trim((string) $task['log'] . ($note !== '' ? ' · rejected: ' . $note : ' · rejected')), $taskId]);
        pp_audit('agent.rejected', $task['kind'], "task #$taskId" . ($note !== '' ? ", note: $note" : ''));
        flash_set('Rejected — nothing changed on the story.');
        redirect('agents.php');
    }

    if ($action === 'requeue' && $task && in_array($task['status'], ['failed', 'rejected'], true)) {
        if (pp_agent_enqueue($task['kind'], (int) $task['post_id'], $user['name']) === 'queued') {
            pp_audit('agent.queued', $task['kind'], "story #{$task['post_id']} requeued from task #$taskId");
        }
        flash_set('Queued again with a fresh look at the story.');
        redirect('agents.php');
    }
    redirect('agents.php');
}

$fStatus = (string) ($_GET['status'] ?? 'open');
$where = match ($fStatus) {
    'open'     => "t.status IN ('queued', 'running', 'needs_review')",
    'queued', 'running', 'needs_review', 'approved', 'rejected', 'failed' => 't.status = ' . db()->quote($fStatus),
    default    => '1=1',
};
$tasks = db()->query("SELECT t.*, p.title AS post_title, p.slug AS post_slug
                      FROM agent_tasks t LEFT JOIN posts p ON p.id = t.post_id
                      WHERE $where ORDER BY t.id DESC LIMIT 80")->fetchAll();
$counts = [];
foreach (db()->query('SELECT status, COUNT(*) n FROM agent_tasks GROUP BY status') as $row) {
    $counts[$row['status']] = (int) $row['n'];
}

admin_header('Agent desk', 'agents');
flash_show();
?>

<h1 class="pagetitle">The agent desk</h1>
<p class="pagesub">Agents do the tedious passes — links to the entity directory, search descriptions, tags — and editors keep the pen. Everything an agent produces waits here as a proposal; approving applies it through the same sanitizer as any save and stamps your name. Nothing applies itself.</p>

<div class="panel">
  <div class="formrow">
    <?php foreach (['open' => 'Open', 'needs_review' => 'Needs review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'failed' => 'Failed'] as $k => $label): ?>
    <a class="btn <?= $fStatus === $k ? 'btn--sky' : 'btn--ghost' ?> btn--small" href="agents.php?status=<?= $k ?>"><?= $label ?><?= $k === 'needs_review' && !empty($counts['needs_review']) ? ' · ' . $counts['needs_review'] : '' ?></a>
    <?php endforeach; ?>
    <form method="post" class="inline" style="margin-left:auto"><?= csrf_field() ?><input type="hidden" name="action" value="run_now"><button class="btn btn--outline btn--small" type="submit">Run queued now</button></form>
  </div>

  <form method="post" class="formrow" style="margin-top:12px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="queue">
    <select name="kind" aria-label="Agent">
      <?php foreach ($kinds as $k => [$label, $desc]): ?>
      <option value="<?= $k ?>" title="<?= e($desc) ?>"><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="post_id" class="mono" placeholder="Story id" aria-label="Story id" style="max-width:110px">
    <button class="btn btn--ghost" type="submit">Queue for one story</button>
    <span class="help">Or queue from a story's editor, or in bulk from the network desk.</span>
  </form>
</div>

<?php if (!$tasks): ?>
<div class="panel"><p>Nothing here. Queue a pass above, or turn on auto-queue at publish in Settings.</p></div>
<?php endif; ?>

<?php foreach ($tasks as $t):
    $result = (array) json_decode((string) $t['result'], true);
    $chip = match ($t['status']) {
        'needs_review' => 'chip--in_review',
        'approved'     => 'chip--ok',
        'failed', 'rejected' => 'chip--error',
        default        => 'chip--used',
    };
?>
<div class="panel">
  <div class="headrow">
    <h2>#<?= (int) $t['id'] ?> · <?= e($kinds[$t['kind']][0] ?? $t['kind']) ?> ·
      <?php if ($t['post_title']): ?><a href="post-edit.php?id=<?= (int) $t['post_id'] ?>"><?= e($t['post_title']) ?></a><?php else: ?>story <?= (int) $t['post_id'] ?><?php endif; ?></h2>
    <div><span class="chip <?= $chip ?>"><?= e(str_replace('_', ' ', $t['status'])) ?></span></div>
  </div>
  <p class="help">Queued by <?= e($t['created_by']) ?> · <?= e(fmt_date($t['created_at'], 'M j, g:i a')) ?><?= $t['reviewed_by'] ? ' · reviewed by ' . e($t['reviewed_by']) . ' ' . e(fmt_date($t['reviewed_at'], 'M j, g:i a')) : '' ?><?= $t['log'] ? ' · ' . e($t['log']) : '' ?></p>

  <?php if ($t['status'] === 'needs_review'): ?>
    <?php if ($t['kind'] === 'linkify'): ?>
    <table class="tbl">
      <tr><th>Name found</th><th>Links to</th></tr>
      <?php foreach ((array) ($result['links'] ?? []) as $l): ?>
      <tr><td><strong><?= e($l['alias']) ?></strong><?= $l['alias'] !== $l['entity'] ? ' <span class="help">(' . e($l['entity']) . ')</span>' : '' ?></td>
          <td class="mono" style="word-break:break-all"><a href="<?= e($l['url']) ?>" target="_blank" rel="noopener"><?= e($l['url']) ?></a></td></tr>
      <?php endforeach; ?>
    </table>
    <?php
      // The story as it would read, the proposed anchors outlined.
      $preview = sanitize_html((string) ($result['proposed_body'] ?? ''));
      foreach ((array) ($result['links'] ?? []) as $l) {
          $anchor = '<a href="' . e($l['url']) . '">' . e($l['alias']) . '</a>';
          $preview = str_replace($anchor,
              '<a href="' . e($l['url']) . '" style="outline:2px solid var(--color-accent);outline-offset:1px">' . e($l['alias']) . '</a>', $preview);
      }
    ?>
    <div class="prose" style="border:1px solid var(--pp-board);padding:14px 16px;margin-top:12px;max-height:340px;overflow:auto"><?= $preview ?></div>
    <?php elseif ($t['kind'] === 'seo_meta'): ?>
    <p><strong>Proposed search description</strong> (<?= mb_strlen((string) ($result['proposed'] ?? '')) ?> chars):</p>
    <p style="border:1px solid var(--pp-board);padding:12px 14px"><?= e((string) ($result['proposed'] ?? '')) ?></p>
    <?php elseif ($t['kind'] === 'tagger'): ?>
    <p><strong>Proposed additions:</strong>
      <?php foreach ((array) ($result['proposed_tags'] ?? []) as $tag): ?><span class="chip chip--scheduled"><?= e($tag) ?></span> <?php endforeach; ?>
      <?php if (!empty($result['current_tags'])): ?><span class="help">joining: <?= e(implode(', ', (array) $result['current_tags'])) ?></span><?php endif; ?>
    </p>
    <?php endif; ?>
    <form method="post" class="formrow" style="margin-top:12px">
      <?= csrf_field() ?>
      <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
      <button class="btn" name="action" value="approve" type="submit">Approve &amp; apply</button>
      <input type="text" name="note" placeholder="Rejection note · optional" aria-label="Rejection note" style="min-width:220px">
      <button class="btn btn--danger" name="action" value="reject" type="submit">Reject</button>
    </form>
  <?php elseif (in_array($t['status'], ['failed', 'rejected'], true)): ?>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="requeue"><button class="btn btn--ghost btn--small" type="submit">Queue again</button></form>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php admin_footer(); ?>
