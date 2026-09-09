<?php
/**
 * pp_guarded_post_update() semantics at the database, on the actual
 * configured engine — above all the MySQL matched-vs-changed distinction:
 * an ALLOWED save whose content happens to be identical must succeed on
 * every engine (PDO::MYSQL_ATTR_FOUND_ROWS), while missing rows and
 * disallowed states must still refuse, and a concurrent state change
 * across two live connections must still win.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('guarded');
pp_fixture_prepare($fx, false);   // schema only — this suite plants its own rows

putenv('PP_CONFIG=' . $fx['config']);
require $root . '/app/bootstrap.php';
$pdo = db();
register_shutdown_function(fn () => pp_fixture_destroy($fx));

// Evidence line: the engine this run actually exercised.
echo 'engine: ', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), ' server ',
     (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION), "\n";

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

$mk = function (string $status, string $title) use ($pdo): int {
    $pdo->prepare('INSERT INTO posts (title, slug, body, status, author_id, published_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$title, slugify($title) . '-' . bin2hex(random_bytes(3)), '<p>body</p>', $status, 7,
                   $status === 'draft' ? null : now(), now(), now()]);
    return pp_last_id('posts');
};
$row = function (int $id) use ($pdo): array {
    $s = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: [];
};

/* --- Allowed writes ------------------------------------------------------- */

$draft = $mk('draft', 'Guarded draft');
ok(pp_guarded_post_update($draft, ['title' => 'Changed title', 'updated_at' => now()], ['draft', 'in_review']) === true,
    'changed save on an allowed state succeeds');
ok($row($draft)['title'] === 'Changed title', 'and the change landed');

// THE MySQL case: identical content, allowed state. Without FOUND_ROWS,
// MySQL reports 0 changed rows here and a legitimate save looks like a
// conflict, suppressing every dependent write behind it.
$current = $row($draft);
ok(pp_guarded_post_update($draft, ['title' => $current['title'], 'updated_at' => $current['updated_at']], ['draft', 'in_review']) === true,
    'an UNCHANGED save on an allowed state still succeeds (matched-rows semantics)');

/* --- Refusals ------------------------------------------------------------- */

ok(pp_guarded_post_update(999999, ['title' => 'ghost'], ['draft', 'in_review']) === false,
    'a missing post refuses');
ok(pp_guarded_update_failure(999999) === 'missing', 'and is classified as missing');

$pub = $mk('published', 'Guarded published');
ok(pp_guarded_post_update($pub, ['title' => 'Overwrite live'], ['draft', 'in_review']) === false,
    'a published post refuses the author-state condition');
ok(pp_guarded_update_failure($pub) === 'state', 'and is classified as a state refusal');
ok($row($pub)['title'] === 'Guarded published', 'the live row is untouched');

$sched = $mk('scheduled', 'Guarded scheduled');
ok(pp_guarded_post_update($sched, ['title' => 'Overwrite scheduled'], ['draft', 'in_review']) === false,
    'a scheduled post refuses too');

// The unconditional (editor) path writes in any state.
ok(pp_guarded_post_update($pub, ['title' => 'Editor retitle', 'updated_at' => now()], null) === true,
    'the unconditional path still writes to published content');

/* --- Concurrency across two live connections ------------------------------- */

$race = $mk('draft', 'Guarded race');
$pdo2 = pp_fixture_connect($fx);
$pdo2->prepare("UPDATE posts SET status = 'published', published_at = ? WHERE id = ?")->execute([now(), $race]);
ok(pp_guarded_post_update($race, ['title' => 'Stale write'], ['draft', 'in_review']) === false,
    'a state change on another connection defeats the stale conditional write');
ok($row($race)['title'] === 'Guarded race', 'and nothing landed');

exit($fails ? 1 : 0);
