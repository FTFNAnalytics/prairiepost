<?php
/**
 * Re-sanitize stored story bodies through the parser-based sanitizer.
 *
 * Rendering is already safe — every template passes stored HTML through
 * sanitize_html() at output time — so this tool is housekeeping, not the
 * fix: it rewrites stored bodies so the database itself carries clean
 * markup. Dry run by default; nothing is written without --apply.
 *
 *   php tools/sanitize-content.php                dry run, report only
 *   php tools/sanitize-content.php --apply        rewrite, with history
 *   php tools/sanitize-content.php --start-id=500 resume from a post id
 *   php tools/sanitize-content.php --batch=100    rows per batch (default 200)
 *
 * Every rewritten story gets a revision snapshot of its pre-rewrite text
 * FIRST, so the original is recoverable from the story's History panel.
 * Idempotent: a second run finds nothing to change. Restart-safe: rows are
 * walked in id order and the last processed id is printed on exit.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$apply = in_array('--apply', $argv, true);
$startId = 0;
$batch = 200;
foreach ($argv as $arg) {
    if (preg_match('/^--start-id=(\d+)$/', $arg, $m)) {
        $startId = (int) $m[1];
    }
    if (preg_match('/^--batch=(\d+)$/', $arg, $m)) {
        $batch = max(1, min(1000, (int) $m[1]));
    }
}

/** Tag names present in a fragment, for reporting what a rewrite dropped. */
function pp_tag_inventory(string $html): array
{
    preg_match_all('/<([a-z][a-z0-9]*)/i', $html, $m);
    return array_unique(array_map('strtolower', $m[1]));
}

$pdo = db();
echo $apply ? "APPLY RUN — rewrites are written, originals snapshot to history.\n"
            : "DRY RUN — nothing is written. Add --apply to rewrite.\n";

$scanned = 0;
$changed = 0;
$flagged = 0;
$lastId = $startId;

while (true) {
    $stmt = $pdo->prepare('SELECT id, title, body FROM posts WHERE id > ? ORDER BY id LIMIT ' . (int) $batch);
    $stmt->execute([$lastId]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        break;
    }
    foreach ($rows as $row) {
        $lastId = (int) $row['id'];
        $scanned++;
        $before = (string) $row['body'];
        $after = sanitize_html($before);
        if ($after === $before) {
            continue;
        }
        $changed++;
        $dropped = array_diff(pp_tag_inventory($before), pp_tag_inventory($after));
        $delta = strlen($after) - strlen($before);
        $note = '';
        if ($dropped) {
            $note .= ' DROPPED TAGS: ' . implode(',', $dropped) . ';';
            $flagged++;
        }
        if (abs($delta) > max(200, (int) (strlen($before) * 0.10))) {
            $note .= ' LARGE CHANGE — review this story by hand;';
            $flagged++;
        }
        printf("%s #%d %-50s body %+d byte(s)%s\n",
            $apply ? 'rewrote' : 'would rewrite',
            $row['id'], mb_substr((string) $row['title'], 0, 50), $delta, $note);
        if ($apply) {
            // Snapshot the story AS IT STANDS (pre-rewrite) so the original
            // text is recoverable, then write the sanitized body.
            pp_post_snapshot((int) $row['id'], 'sanitize', 'tools/sanitize-content.php');
            $pdo->prepare('UPDATE posts SET body = ?, updated_at = ? WHERE id = ? AND body = ?')
                ->execute([$after, now(), (int) $row['id'], $before]);
        }
    }
    if (count($rows) < $batch) {
        break;
    }
}

echo "\n";
printf("scanned %d post(s), %s %d, %d flagged for editorial review; last id processed: %d\n",
    $scanned, $apply ? 'rewrote' : 'would rewrite', $changed, $flagged, $lastId);
if ($apply && $changed > 0) {
    echo "Verify: re-run without --apply — it must report 0 to rewrite.\n";
}
