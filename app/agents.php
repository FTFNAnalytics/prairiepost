<?php
/**
 * The agent control room's rails: the kind registry, the enqueue helper,
 * and the dispatcher the runner calls. Handlers live one-per-kind in
 * app/agents/ — adding an agent is adding a file and a registry row.
 *
 * The iron rule: an agent's output is a PROPOSAL. Handlers write into
 * result and the task lands in needs_review; only an editor's approval —
 * through admin/agents.php, through the same sanitizer as every save —
 * changes a story. Nothing here publishes, and nothing applies itself.
 */

require_once __DIR__ . '/agents/linkify.php';
require_once __DIR__ . '/agents/seo-meta.php';
require_once __DIR__ . '/agents/tagger.php';

/** kind => [label, one-line description, needs AI]. */
function pp_agent_kinds(): array
{
    return [
        'linkify'  => ['Linkifier', 'Proposes links from names in the story to the entity directory\'s bio URLs', false],
        'seo_meta' => ['SEO meta writer', 'Fills an empty search description from the title and lede', true],
        'tagger'   => ['Tagger', 'Proposes tags from the network\'s existing tag vocabulary', true],
    ];
}

/**
 * Queue one task, de-duplicated: a story never carries two open tasks of
 * the same kind. Returns 'queued' or 'exists'.
 */
function pp_agent_enqueue(string $kind, int $postId, string $byName): string
{
    if (!isset(pp_agent_kinds()[$kind])) {
        return 'exists';
    }
    $db = db();
    $stmt = $db->prepare("SELECT 1 FROM agent_tasks WHERE post_id = ? AND kind = ? AND status IN ('queued', 'running', 'needs_review')");
    $stmt->execute([$postId, $kind]);
    if ($stmt->fetch()) {
        return 'exists';
    }
    // The story's updated_at rides along so a stale proposal can't apply
    // over edits made while the task waited.
    $stmt = $db->prepare('SELECT updated_at FROM posts WHERE id = ?');
    $stmt->execute([$postId]);
    $seen = (string) ($stmt->fetchColumn() ?: '');
    $db->prepare('INSERT INTO agent_tasks (kind, status, post_id, payload, created_by, created_at)
                  VALUES (?, ?, ?, ?, ?, ?)')
       ->execute([$kind, 'queued', $postId, json_encode(['post_updated_at' => $seen]), $byName, now()]);
    return 'queued';
}

/**
 * Run one claimed task. Returns [ok, resultArray|null, logLine] — the
 * caller owns status transitions and persistence.
 */
function pp_agent_run(array $task): array
{
    $post = null;
    if (!empty($task['post_id'])) {
        $stmt = db()->prepare('SELECT * FROM posts WHERE id = ?');
        $stmt->execute([(int) $task['post_id']]);
        $post = $stmt->fetch() ?: null;
    }
    if ($post === null) {
        return [false, null, 'the story no longer exists'];
    }
    return match ($task['kind']) {
        'linkify'  => pp_agent_linkify($post),
        'seo_meta' => pp_agent_seo_meta($post),
        'tagger'   => pp_agent_tagger($post),
        default    => [false, null, 'unknown agent kind ' . $task['kind']],
    };
}

/** The hub's site id, for reading the control room's own settings anywhere. */
function pp_hub_site_id(): int
{
    static $id = null;
    if ($id === null) {
        $hub = slugify((string) pp_config('hub_slug', ''));
        $stmt = db()->prepare('SELECT id FROM sites WHERE slug = ?');
        $stmt->execute([$hub]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
    }
    return $id;
}

/**
 * On a story's publish transition, queue whatever the hub's auto settings
 * ask for. Off by default; per-kind checkboxes in the hub's Settings.
 */
function pp_agent_auto_enqueue(int $postId): void
{
    $hubId = pp_hub_site_id();
    if (!$hubId) {
        return;
    }
    foreach (array_keys(pp_agent_kinds()) as $kind) {
        if (pp_setting_for_site($hubId, "auto_agent_$kind") === '1') {
            pp_agent_enqueue($kind, $postId, 'auto on publish');
        }
    }
}

/**
 * Claim-and-run up to $max queued tasks — the runner's whole loop, shared
 * by the cron and the desk's Run now. The guarded UPDATE is what makes
 * overlapping runs safe: only one caller ever flips a task to running.
 */
function pp_agents_process(int $max): string
{
    $db = db();
    $done = 0;
    $lines = [];
    $deadline = time() + 240;
    while ($done < $max && time() < $deadline) {
        $next = $db->query("SELECT id FROM agent_tasks WHERE status = 'queued' ORDER BY id LIMIT 1")->fetchColumn();
        if (!$next) {
            break;
        }
        $claim = $db->prepare("UPDATE agent_tasks SET status = 'running', started_at = ? WHERE id = ? AND status = 'queued'");
        $claim->execute([now(), (int) $next]);
        if ($claim->rowCount() !== 1) {
            continue;   // another runner got it — take the next one
        }
        $stmt = $db->prepare('SELECT * FROM agent_tasks WHERE id = ?');
        $stmt->execute([(int) $next]);
        $task = $stmt->fetch();
        try {
            [$ok, $result, $log] = pp_agent_run($task);
        } catch (Throwable $e) {
            [$ok, $result, $log] = [false, null, 'handler threw: ' . $e->getMessage()];
        }
        $db->prepare('UPDATE agent_tasks SET status = ?, result = ?, log = ?, finished_at = ? WHERE id = ?')
           ->execute([$ok ? 'needs_review' : 'failed',
                      $result !== null ? json_encode($result, JSON_UNESCAPED_SLASHES) : null,
                      $log, now(), (int) $next]);
        $lines[] = ($ok ? 'ok    ' : 'FAIL  ') . "#{$task['id']} {$task['kind']} (post {$task['post_id']}): $log";
        $done++;
    }
    $lines[] = "----\n$done task(s) processed.";
    return implode("\n", $lines);
}
