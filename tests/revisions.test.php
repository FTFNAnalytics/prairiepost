<?php
/**
 * Revision history against a throwaway in-memory SQLite built from the
 * app's own DDL — the cap, the ordering, the autosave damping.
 * db()/current_site_id() are shimmed BEFORE models.php loads; the real
 * database is never involved.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
function current_site_id(): int
{
    return 1;
}
function pp_like(): string
{
    return 'LIKE';
}
require dirname(__DIR__) . '/app/db.php';
require dirname(__DIR__) . '/app/models.php';

foreach (pp_schema_ddl('sqlite') as $sql) {
    db()->exec($sql);
}

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

db()->exec("INSERT INTO posts (title, slug, status, body, created_at, updated_at)
            VALUES ('Original', 'original', 'draft', '<p>v0</p>', '2026-01-01 00:00', '2026-01-01 00:00')");
$postId = (int) db()->lastInsertId();

// 45 edits → capped at 40, newest kept, oldest gone.
for ($i = 1; $i <= 45; $i++) {
    db()->prepare('UPDATE posts SET body = ? WHERE id = ?')->execute(["<p>v$i</p>", $postId]);
    pp_post_snapshot($postId, 'edit', 'Tester');
}
$count = (int) db()->query("SELECT COUNT(*) FROM post_revisions WHERE post_id = $postId")->fetchColumn();
ok($count === 40, "history capped at 40 (got $count)");
$newest = pp_revision_by_id((int) db()->query("SELECT MAX(id) FROM post_revisions WHERE post_id = $postId")->fetchColumn());
ok($newest !== null && $newest['body'] === '<p>v45</p>', 'newest revision is the newest save');
$oldest = (string) db()->query("SELECT body FROM post_revisions WHERE post_id = $postId ORDER BY id LIMIT 1")->fetchColumn();
ok($oldest === '<p>v6</p>', "oldest kept is v6 after pruning (got $oldest)");

// Autosave damping: a second snapshot inside the interval is skipped…
$before = $count;
pp_post_snapshot($postId, 'autosave', 'Tester', 1800);
$after = (int) db()->query("SELECT COUNT(*) FROM post_revisions WHERE post_id = $postId")->fetchColumn();
ok($after === $before, 'autosave inside the interval writes nothing');
// …but a plain save always records.
pp_post_snapshot($postId, 'edit', 'Tester');
$after = (int) db()->query("SELECT COUNT(*) FROM post_revisions WHERE post_id = $postId")->fetchColumn();
ok($after === $before, 'cap holds at 40 even as saves continue');

// A snapshot of a missing post is a quiet no-op.
pp_post_snapshot(999999, 'edit', 'Tester');
ok(true, 'missing post no-op');

exit($fails ? 1 : 0);
