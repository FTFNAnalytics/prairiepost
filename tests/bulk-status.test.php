<?php
/**
 * The Stories list's bulk status change, pp_bulk_set_status(), on the
 * configured engine. Each story passes the single-save write policy
 * against its persisted row; the write is a compare-and-swap on the
 * status the LIST SHOWED, so a stale view never moves a story someone
 * else changed; publishing means live now (published_at stamped when
 * empty or future, kept when past; the publish-on-image flag cleared);
 * the editor's follow-ups run (paper mapping, the agent auto-queue, one
 * audit row). The hub half runs in a child process, because the current
 * site is resolved once per process.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

/* --- Child: the hub's admin (PP_SITE=civismedia, the parent's fixture) ---- */

if (($argv[1] ?? '') === '--hub') {
    require $root . '/app/bootstrap.php';
    ok(pp_is_hub(), 'the child process serves the hub');
    $pdo = db();
    $editor = $pdo->query("SELECT * FROM users WHERE email = 'editor@bulk.test'")->fetch();
    $paperId = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'prairiedispatch'")->fetchColumn();
    $mk = function (string $title) use ($pdo, $editor): int {
        $pdo->prepare('INSERT INTO posts (title, slug, body, status, author_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?)')
            ->execute([$title, slugify($title) . '-' . bin2hex(random_bytes(3)), '<p>body</p>', 'draft', (int) $editor['id'], now(), now()]);
        return pp_last_id('posts');
    };
    $loose = $mk('Hub draft on no paper');
    $placed = $mk('Hub draft already on a paper');
    set_post_sites($placed, [$paperId]);

    $r = pp_bulk_set_status($editor, [$loose => 'draft', $placed => 'draft'], 'published');
    ok(count($r['changed']) === 2, 'the hub publishes both');
    ok($r['unmapped'] === [$loose], 'the hub reports the story that runs on no paper, and only that one');
    ok(site_ids_for_post($loose) === [], 'the hub maps nothing itself: no paper is "this site" there');
    ok(site_ids_for_post($placed) === [$paperId], 'a mapped story keeps its papers');
    ok(str_contains(pp_bulk_status_summary($r, 'published'),
        '1 of them runs on no paper yet: choose its papers on the Network desk'),
        'and the flash tells the editor where to finish the job');
    exit($fails ? 1 : 0);
}

/* --- Parent: a paper's admin ---------------------------------------------- */

require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('bulkstatus');
pp_fixture_prepare($fx, false);   // schema only — this suite plants its own rows

putenv('PP_CONFIG=' . $fx['config']);
require $root . '/app/bootstrap.php';
$pdo = db();
register_shutdown_function(fn () => pp_fixture_destroy($fx));

echo 'engine: ', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), ' server ',
     (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION), "\n";

// Three sites (this paper, the hub, a sister paper) and three people.
foreach (['prairiedispatch' => 'The Prairie Dispatch', 'civismedia' => 'Civis Media', 'sisterpaper' => 'The Sister Paper'] as $slug => $name) {
    $pdo->prepare('INSERT INTO sites (name, slug, domain, created_at) VALUES (?,?,?,?)')->execute([$name, $slug, '', now()]);
}
$siteId = fn (string $slug): int => (int) $pdo->query("SELECT id FROM sites WHERE slug = '$slug'")->fetchColumn();
[$paperId, $hubId, $sisterId] = [$siteId('prairiedispatch'), $siteId('civismedia'), $siteId('sisterpaper')];
ok(!pp_is_hub() && current_site_id() === $paperId, 'the suite runs on a paper\'s admin');

$person = function (string $name, string $role) use ($pdo): array {
    $email = strtolower(strtok($name, ' ')) . '@bulk.test';
    $pdo->prepare('INSERT INTO users (name, email, pass_hash, role, created_at) VALUES (?,?,?,?,?)')
        ->execute([$name, $email, 'x', $role, now()]);
    $s = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $s->execute([$email]);
    return $s->fetch();
};
$editor = $person('Editor Desk', 'editor');
$author = $person('Author Ayla', 'author');
$other  = $person('Other Author', 'author');

// The hub has the SEO-meta agent on auto, so every publish should queue one.
$pdo->prepare('INSERT INTO settings (site_id, skey, svalue) VALUES (?,?,?)')->execute([$hubId, 'auto_agent_seo_meta', '1']);

$mk = function (string $status, string $title, array $who, ?string $publishedAt = null, int $awaiting = 0) use ($pdo): int {
    $pdo->prepare('INSERT INTO posts (title, slug, body, status, author_id, published_at, awaiting_image, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$title, slugify($title) . '-' . bin2hex(random_bytes(3)), '<p>body</p>', $status, (int) $who['id'],
                   $publishedAt, $awaiting, now(), now()]);
    return pp_last_id('posts');
};
$row = function (int $id) use ($pdo): array {
    $s = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: [];
};
$tasks = function (int $id) use ($pdo): int {
    $s = $pdo->prepare("SELECT COUNT(*) FROM agent_tasks WHERE post_id = ? AND kind = 'seo_meta'");
    $s->execute([$id]);
    return (int) $s->fetchColumn();
};
$audits = fn (): array => $pdo->query("SELECT * FROM audit_log WHERE action = 'stories.bulk_status' ORDER BY id")->fetchAll();

/* --- The owner's example: an editor publishes fifteen drafts --------------- */

$drafts = [];
for ($i = 1; $i <= 15; $i++) {
    $drafts[] = $mk('draft', "Bulk draft $i", $author);
}
set_post_sites($drafts[0], [$sisterId]);              // already placed on a sister paper
$live = $mk('published', 'Already live', $author, '2026-01-05 09:00:00');
$seen = array_fill_keys($drafts, 'draft') + [$live => 'published', 999999 => 'draft'];

$before = time();
$r = pp_bulk_set_status($editor, $seen, 'published');
ok($r['changed'] === $drafts, 'all fifteen drafts are published, in the order ticked');
ok($r['skipped'] === ['already' => 1, 'missing' => 1], 'the live story and the vanished id are skipped and named');
ok($r['unmapped'] === [], 'a paper reports nothing unmapped');
$allLive = true;
foreach ($drafts as $id) {
    $p = $row($id);
    $at = strtotime((string) $p['published_at']);
    $allLive = $allLive && $p['status'] === 'published' && $at >= $before - 1 && $at <= time() + 1;
}
ok($allLive, 'each is published with published_at stamped now');
ok(site_ids_for_post($drafts[1]) === [$paperId], 'an unassigned story is mapped to this paper');
ok(site_ids_for_post($drafts[0]) === [$sisterId], 'a story already on a sister paper keeps exactly that mapping');
ok($tasks($drafts[3]) === 1 && $tasks($live) === 0, 'the auto-queue fires for each newly published story, not the one already live');
ok($row($live)['published_at'] !== null && strtotime((string) $row($live)['published_at']) === strtotime('2026-01-05 09:00:00'),
    'the already-live story is untouched');
$log = $audits();
ok(count($log) === 1, 'one audit row for the whole action');
ok(($log[0]['target'] ?? '') === '15 → published' && (int) ($log[0]['user_id'] ?? 0) === (int) $editor['id']
    && str_contains((string) ($log[0]['detail'] ?? ''), 'skipped already 1, missing 1'),
    'the audit row names the count, the status, the editor and the skips');
ok(pp_bulk_status_summary($r, 'published') === '15 stories published. Skipped 2: 1 was already published; 1 no longer exists.',
    'the flash says what happened');

/* --- Publish semantics: dates and the publish-on-image flag ---------------- */

$back = $mk('draft', 'Returning draft', $author, '2020-01-02 03:04:05');
$sched = $mk('scheduled', 'Scheduled for later', $author, date('Y-m-d H:i:s', time() + 7 * 86400));
$waiting = $mk('draft', 'Filed publish-on-image', $author, null, 1);
$r = pp_bulk_set_status($editor, [$back => 'draft', $sched => 'scheduled', $waiting => 'draft'], 'published');
ok(count($r['changed']) === 3, 'a returning draft, a scheduled story and a publish-on-image draft all publish');
ok(strtotime((string) $row($back)['published_at']) === strtotime('2020-01-02 03:04:05'), 'a past publish date is kept');
ok(strtotime((string) $row($sched)['published_at']) <= time() + 1, 'a future publish date becomes now: publish means live now');
ok((int) $row($waiting)['awaiting_image'] === 0, 'publishing by hand clears the publish-on-image flag');

/* --- A stale list never acts: the CAS is on the status the list showed ----- */

$racer = $mk('draft', 'Published by a colleague meanwhile', $author);
$pdo2 = pp_fixture_connect($fx);
$pdo2->prepare("UPDATE posts SET status = 'published', published_at = ? WHERE id = ?")->execute([now(), $racer]);
$r = pp_bulk_set_status($editor, [$racer => 'draft'], 'in_review');
ok($r['changed'] === [] && $r['skipped'] === ['changed' => 1], 'a story that moved on after the list loaded is skipped as changed');
ok($row($racer)['status'] === 'published', 'and stays live: a stale view never pulls a story off the site');
ok(pp_bulk_status_summary($r, 'in_review')
    === 'Nothing changed. Skipped 1: 1 was changed by someone else after this list loaded (reload to see it).',
    'the flash tells the editor to reload');
$r = pp_bulk_set_status($editor, [$racer => 'draft'], 'published');
ok($r['skipped'] === ['already' => 1], 'already at the target counts as already, whatever the list showed');

/* --- Authors: their own drafts and in-review work only, and never publish -- */

$mine = $mk('draft', 'Author own draft', $author);
$theirs = $mk('draft', 'Another author draft', $other);
$myLive = $mk('published', 'Author published story', $author, now());
$mySched = $mk('scheduled', 'Author scheduled story', $author, date('Y-m-d H:i:s', time() + 86400));

$r = pp_bulk_set_status($author, [$mine => 'draft'], 'published');
ok($r['changed'] === [] && $r['skipped'] === ['denied' => 1] && $row($mine)['status'] === 'draft', 'an author cannot publish');
$r = pp_bulk_set_status($author, [$mine => 'draft', $theirs => 'draft', $myLive => 'published', $mySched => 'scheduled'], 'in_review');
ok($r['changed'] === [$mine], 'an author sends their own draft to review');
ok($r['skipped'] === ['denied' => 3], "another author's story and the author's own live and scheduled stories are refused");
ok($row($theirs)['status'] === 'draft' && $row($myLive)['status'] === 'published' && $row($mySched)['status'] === 'scheduled',
    'and none of the refused rows changed');
ok($tasks($mine) === 0, 'review is not a publish: nothing is queued');
$r = pp_bulk_set_status($author, [$mine => 'in_review'], 'draft');
ok($r['changed'] === [$mine] && $row($mine)['status'] === 'draft', 'an author pulls their own story back from review');

/* --- Editors: unpublishing keeps the original date ------------------------- */

$orig = $row($drafts[5])['published_at'];
$r = pp_bulk_set_status($editor, [$drafts[5] => 'published'], 'draft');
ok($r['changed'] === [$drafts[5]] && $row($drafts[5])['status'] === 'draft', 'an editor moves a live story back to draft');
ok($row($drafts[5])['published_at'] === $orig, 'and its publish date is kept for when it returns');
ok(pp_bulk_status_summary($r, 'draft') === '1 story moved to draft.', 'the flash for one story');

/* --- Limits and junk input ------------------------------------------------- */

$many = [];
for ($i = 1; $i <= PP_BULK_STATUS_LIMIT + 5; $i++) {
    $many[900000 + $i] = 'draft';
}
$auditCount = count($audits());
$r = pp_bulk_set_status($editor, $many, 'published');
ok($r['skipped'] === ['limit' => 5, 'missing' => PP_BULK_STATUS_LIMIT], 'one action touches at most ' . PP_BULK_STATUS_LIMIT . ' stories');
ok(count($audits()) === $auditCount, 'an action that changed nothing writes no audit row');

$r = pp_bulk_set_status($editor, [0 => 'draft', -4 => 'draft', 'x' => 'draft', $mine => 'bogus'], 'in_review');
ok($r === ['changed' => [], 'skipped' => [], 'unmapped' => []], 'junk ids and unknown statuses are dropped');
$r = pp_bulk_set_status($editor, [$mine => 'published'], 'in_review');
ok($r['skipped'] === ['changed' => 1] && $row($mine)['status'] === 'draft',
    'a seen status that is not the stored one is a stale view, and nothing moves');
$r = pp_bulk_set_status($editor, [$mine => 'draft'], 'scheduled');
ok($r['skipped'] === ['denied' => 1] && $row($mine)['status'] === 'draft', 'scheduling needs a date, so it is not a bulk target');

ok(pp_bulk_status_summary(['changed' => [], 'skipped' => ['already' => 3], 'unmapped' => []], 'draft')
    === 'Nothing changed. Skipped 3: 3 were already drafts.', 'the nothing-changed flash');
ok(pp_bulk_status_summary(['changed' => [7, 8], 'skipped' => [], 'unmapped' => [7, 8]], 'published')
    === '2 stories published. They run on no paper yet: choose their papers on the Network desk to put them on a front page.',
    'the flash when none of the published stories is on a paper yet');

/* --- The hub ---------------------------------------------------------------- */

exec('PP_CONFIG=' . escapeshellarg($fx['config']) . ' PP_SITE=civismedia '
    . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --hub 2>&1', $out, $code);
ok($code === 0, 'the hub half passes' . ($out ? ":\n    " . implode("\n    ", $out) : ''));

exit($fails ? 1 : 0);
