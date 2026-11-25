<?php
/**
 * The whiteboard and the tips form (app/whiteboard.php, tips.php,
 * admin/whiteboard.php), on a disposable database and over real HTTP.
 *
 * - The model: the form's token, link checking, the reader's submission
 *   (honeypot, validation, the per-paper cap), the board's site scoping,
 *   and a draft that copies the tip but never the tipster.
 * - The public form: a token drawn and sent at once is refused, a good
 *   tip lands on the right paper's board and nowhere else, and nothing
 *   about the sender is kept (no IP column, no audit row).
 * - The newsroom: authors are refused, an editor sees this paper's board
 *   only, with tips escaped and the waiting count in the nav, and every
 *   staff action is scoped, CSRF-checked and audited.
 * - The templates: no paper's chrome links a mailto any more.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('whiteboard');
pp_fixture_prepare($fx);
$work = $fx['work'];

$fails = 0;
function ok(bool $cond, string $label): bool
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
    return $cond;
}

putenv('PP_CONFIG=' . $fx['config']);
define('PP_TEST_BOOT', 1);
require $root . '/app/bootstrap.php';
$pdo = db();
echo 'engine: ', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), ' (', basename($work), ")\n";

$pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?,?,?)')->execute(['Other Paper', 'other-paper', now()]);
$pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?,?,?)')->execute(['Civis Media', 'civismedia', now()]);
foreach (['paper.test' => 'prairiedispatch', 'other.test' => 'other-paper', 'hub.test' => 'civismedia'] as $host => $slug) {
    $pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')->execute([$host, $slug, now()]);
}
$site = [];
foreach ($pdo->query('SELECT id, slug FROM sites') as $row) {
    $site[$row['slug']] = (int) $row['id'];
}
$A = $site['prairiedispatch'];
$B = $site['other-paper'];
$count = function (string $where = '1 = 1', array $args = []) use ($pdo): int {
    $s = $pdo->prepare("SELECT COUNT(*) FROM whiteboard_notes WHERE $where");
    $s->execute($args);
    $n = (int) $s->fetchColumn();
    $s->closeCursor();
    return $n;
};

/* --- The model ------------------------------------------------------------- */

ok(preg_match('/^[0-9a-f]{64}$/', pp_tips_key()) === 1, 'step 26 minted the tips form key');
$now = time();
ok(!pp_tips_token_ok(pp_tips_token($A, $now), $A, $now), 'a token sent the moment it was drawn is refused');
ok(pp_tips_token_ok(pp_tips_token($A, $now - 10), $A, $now), 'a token ten seconds old is accepted');
ok(!pp_tips_token_ok(pp_tips_token($A, $now - 7 * 3600), $A, $now), 'a token seven hours old is refused');
ok(!pp_tips_token_ok(pp_tips_token($B, $now - 10), $A, $now), 'another paper\'s token is refused');
$t = pp_tips_token($A, $now - 10);
ok(!pp_tips_token_ok(substr($t, 0, -1) . ($t[-1] === 'a' ? 'b' : 'a'), $A, $now), 'a tampered token is refused');
ok(!pp_tips_token_ok('', $A, $now) && !pp_tips_token_ok('nonsense', $A, $now), 'an empty or malformed token is refused');

ok(pp_wb_url('') === '' && pp_wb_url('https://example.test/a?b=c#d') === 'https://example.test/a?b=c#d', 'links: none, or a full https address');
foreach (['javascript:alert(1)', 'http://', 'ftp://example.test/x', 'https://example.test/<b>', 'https://exa mple.test/',
          'https://example.test/' . str_repeat('a', 1000), "https://example.test/\x01", "https://example.test/\xff"] as $bad) {
    ok(pp_wb_url($bad) === null, 'links: refused ' . substr(addcslashes($bad, "\0..\37\177..\377"), 0, 40));
}

$good = ['t' => pp_tips_token($A, $now - 10), 'tip' => "The rink's roof\r\nhas a crack, seen Tuesday.", 'link' => 'https://example.test/photo',
         'name' => '  Pat   Reader ', 'contact' => 'pat@example.test', 'website' => ''];
ok(pp_tips_submit($A, ['website' => 'http://spam.test'] + $good, $now) === ['sent', []] && $count() === 0,
   'a filled honeypot is told "sent" and nothing is stored');
[$st, $errs] = pp_tips_submit($A, ['tip' => 'too short'] + $good, $now);
ok($st === 'invalid' && isset($errs['tip']) && $count() === 0, 'a tip under ten characters is refused');
[$st, $errs] = pp_tips_submit($A, ['link' => 'javascript:alert(1)'] + $good, $now);
ok($st === 'invalid' && isset($errs['link']) && $count() === 0, 'a link that is not a web address is refused');
[$st, $errs] = pp_tips_submit($A, ['name' => str_repeat('n', 121), 'contact' => str_repeat('c', 256)] + $good, $now);
ok($st === 'invalid' && isset($errs['name'], $errs['contact']), 'an overlong name or contact is refused');
[$st] = pp_tips_submit($A, ['t' => pp_tips_token($A, $now)] + $good, $now);
ok($st === 'expired' && $count() === 0, 'a tip sent with a fresh token is refused as too quick');
ok(pp_tips_submit($A, ['tip' => ['array']] + $good, $now)[0] === 'invalid', 'a field sent as an array is refused, not cast');

ok(pp_tips_submit($A, $good, $now) === ['sent', []], 'a good tip is accepted');
$row = $pdo->query('SELECT * FROM whiteboard_notes ORDER BY id DESC LIMIT 1')->fetch();
ok(($row['site_id'] ?? 0) == $A && $row['origin'] === 'reader' && $row['note_type'] === 'tip' && $row['status'] === 'unverified'
   && $row['body'] === "The rink's roof\nhas a crack, seen Tuesday." && $row['posted_by'] === 'Pat Reader'
   && $row['contact'] === 'pat@example.test' && $row['source_url'] === 'https://example.test/photo' && $row['user_id'] === null,
   'it is stored on that paper\'s board as an unverified reader tip, line ends and spaces tidied');
ok(!array_intersect(array_map('strtolower', array_keys($row)), ['ip', 'ip_address', 'user_agent', 'ip_hash']),
   'the notes table has nowhere to keep an IP address or a browser');
$audits = (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action LIKE 'whiteboard%' OR action LIKE 'tips%'")->fetchColumn();
ok($audits === 0, 'a reader\'s tip writes no audit row (an audit row records an IP)');
$tipId = (int) $row['id'];

// The cap: a paper that has had its hour's worth refuses politely.
$ins = $pdo->prepare("INSERT INTO whiteboard_notes (site_id, body, note_type, status, origin, created_at, updated_at) VALUES (?, 'flood', 'tip', 'unverified', 'reader', ?, ?)");
for ($i = 1; $i < PP_TIPS_PER_HOUR; $i++) {
    $ins->execute([$A, date('Y-m-d H:i:s', $now - 60), date('Y-m-d H:i:s', $now - 60)]);
}
$before = $count();
ok(pp_tips_submit($A, $good, $now)[0] === 'busy' && $count() === $before, 'past the hourly cap the form answers busy and stores nothing');
ok(pp_tips_submit($B, ['t' => pp_tips_token($B, $now - 10), 'tip' => 'Sister paper tip: ferry delays at the north dock'] + $good, $now)[0] === 'sent',
   'the cap is per paper: a sister paper still takes tips');
$pdo->exec("DELETE FROM whiteboard_notes WHERE body = 'flood'");
$otherTip = (int) $pdo->query("SELECT MAX(id) FROM whiteboard_notes WHERE site_id = $B")->fetchColumn();

ok(pp_wb_waiting($A) === 1 && pp_wb_waiting($B) === 1, 'the waiting count is each paper\'s unverified reader tips');

// Staff notes, and the site wall around every write.
$editor = ['id' => 0, 'name' => 'Ed Editor'];
[$noteId, $err] = pp_wb_add($A, $editor, ['body' => 'Council may move the vote', 'source_url' => '', 'note_type' => 'rumour', 'status' => 'checking']);
ok($noteId > 0 && $err === '' && pp_wb_waiting($A) === 1, 'a staff note is pinned and does not count as a waiting tip');
ok(pp_wb_add($A, $editor, ['body' => 'x', 'note_type' => 'gossip'])[1] !== '', 'an unknown type is refused');
ok(pp_wb_add($A, $editor, ['body' => '   '])[1] !== '', 'an empty note is refused');
ok(pp_wb_edit($B, $noteId, ['body' => 'hijack', 'note_type' => 'fact', 'status' => 'confirmed']) !== ''
   && pp_wb_note($A, $noteId)['body'] === 'Council may move the vote', 'a sister paper cannot edit this paper\'s note');
ok(!pp_wb_set_status($B, $tipId, 'debunked') && pp_wb_note($A, $tipId)['status'] === 'unverified', 'nor change its status');
ok(!pp_wb_delete($B, $tipId) && pp_wb_note($A, $tipId) !== null, 'nor delete it');
ok(pp_wb_note($B, $tipId) === null && count(pp_wb_notes($A)) === 2 && count(pp_wb_notes($B)) === 1, 'each paper reads only its own board');
ok(count(pp_wb_notes($A, 'tip')) === 1 && count(pp_wb_notes($A, '', 'checking')) === 1 && count(pp_wb_notes($A, 'nonsense')) === 2,
   'filters narrow by type and status, and an unknown filter is ignored');

// A tip into a draft: the words travel, the person does not.
$pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
    ->execute(['Ed Editor', 'ed@test.local', password_hash('correct horse wb', PASSWORD_DEFAULT), 'editor', 'ed-editor-wb', now()]);
$edId = (int) $pdo->query("SELECT id FROM users WHERE email = 'ed@test.local'")->fetchColumn();
$editor = ['id' => $edId, 'name' => 'Ed Editor'];
$pdo->prepare('UPDATE whiteboard_notes SET body = ? WHERE id = ?')->execute(["<script>alert(1)</script> The rink's roof has a crack", $tipId]);
$postId = pp_wb_draft($A, $tipId, $editor);
$post = $pdo->query('SELECT * FROM posts WHERE id = ' . (int) $postId)->fetch();
ok($postId > 0 && $post['status'] === 'draft' && $post['author_id'] == $edId && $post['title'] === "<script>alert(1)</script> The rink's roof has a crack",
   'the tip becomes a draft by the editor who turned it, titled from its first line');
ok(!str_contains($post['body'], '<script') && str_contains($post['body'], '&lt;script&gt;') && str_contains($post['body'], 'https://example.test/photo')
   && str_contains($post['body'], 'From the whiteboard: a reader tip'), 'the draft escapes the tip, keeps its link, and says where it came from');
ok(!str_contains($post['body'], 'Pat Reader') && !str_contains($post['body'], 'pat@example.test')
   && !str_contains((string) $post['byline'], 'Pat') && !str_contains((string) $post['source_url'], 'pat@'),
   'the tipster\'s name and contact are nowhere in the draft');
ok(array_map('intval', $pdo->query("SELECT site_id FROM post_sites WHERE post_id = $postId")->fetchAll(PDO::FETCH_COLUMN)) === [$A],
   'the draft runs on this paper only');
$again = pp_wb_draft($A, $tipId, $editor);
$sameTitle = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE title = ?');
$sameTitle->execute([(string) $post['title']]);
$drafts = (int) $sameTitle->fetchColumn();
$sameTitle->closeCursor();   // an open SQLite cursor would pin this handle to an old snapshot
ok($again === $postId && $drafts === 1,
   'turning it again opens the same draft, never a second');
ok(pp_wb_draft($B, $tipId, $editor) === null, 'a sister paper cannot draft this paper\'s tip');

/* --- Over HTTP --------------------------------------------------------------- */

$port = 8790 + random_int(0, 150);
$serverPid = (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($fx['config']) . ' '
    . escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("$work/server.log") . ' 2>&1 & echo $!'));
usleep(700000);
register_shutdown_function(function () use ($serverPid, $fx) {
    if ($serverPid) {
        @posix_kill($serverPid, 15);
    }
    pp_fixture_destroy($fx);
});

/** One request: [status, body, Location]. */
function web(int $port, string $host, string $method, string $path, array $fields = [], ?string $jar = null): array
{
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false,
                            CURLOPT_HTTPHEADER => ['Host: ' . $host], CURLOPT_HEADER => true]);
    if ($jar !== null) {
        curl_setopt_array($ch, [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headLen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    preg_match('/^Location:\s*(\S+)/mi', substr($raw, 0, $headLen), $m);
    return [$code, substr($raw, $headLen), $m[1] ?? ''];
}

[$code, $page] = web($port, 'paper.test', 'GET', '/tips');
ok($code === 200 && str_contains($page, 'name="tip"') && str_contains($page, 'name="website"') && preg_match('/name="t" value="\d+\.[0-9a-f]{32}"/', $page) === 1,
   "GET /tips draws the form with its token and honeypot (got $code)");
ok(str_contains($page, 'Only the editors of') && str_contains($page, 'without your IP address'), 'the form says who reads a tip and what is kept');
preg_match('/name="t" value="([^"]+)"/', $page, $m);
[$code, $page] = web($port, 'paper.test', 'POST', '/tips', ['t' => $m[1] ?? '', 'tip' => 'A tip sent far too quickly to be a person', 'website' => '']);
ok($code === 422 && str_contains($page, 'sent too quickly') && str_contains($page, 'A tip sent far too quickly to be a person'),
   "a token posted straight back is refused, and the typed tip is kept (got $code)");

$before = $count("site_id = $A");
$fields = ['t' => pp_tips_token($A, time() - 20), 'tip' => 'The bridge on 9th has been closed since dawn', 'link' => '', 'name' => '', 'contact' => '', 'website' => ''];
[$code, , $loc] = web($port, 'paper.test', 'POST', '/tips', $fields);
ok($code === 302 && str_ends_with($loc, '/tips?sent=1') && $count("site_id = $A") === $before + 1
   && $count("site_id = $B AND body LIKE '%bridge on 9th%'") === 0, "a good tip lands on this paper's board and no other (got $code → $loc)");
[$code, $page] = web($port, 'paper.test', 'GET', '/tips?sent=1');
ok($code === 200 && str_contains($page, 'Your tip is with the editors') && !str_contains($page, 'name="tip"'), 'the thank-you page replaces the form');
[$code, $page] = web($port, 'paper.test', 'POST', '/tips', ['link' => 'javascript:alert(1)', 't' => pp_tips_token($A, time() - 20)] + $fields);
ok($code === 422 && str_contains($page, 'not a web address') && str_contains($page, 'aria-invalid="true"'), "a bad link is refused and marked (got $code)");
[$code, , $loc] = web($port, 'hub.test', 'GET', '/tips');
ok($code === 302 && str_ends_with($loc, '/#contact'), "the hub has no tips line; /tips goes to its contact form (got $code → $loc)");
$audits = (int) $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();

/* --- The newsroom ------------------------------------------------------------ */

$pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
    ->execute(['Al Author', 'al@test.local', password_hash('correct horse wb', PASSWORD_DEFAULT), 'author', 'al-author-wb', now()]);
$signIn = function (string $email, string $jar) use ($port): int {
    [, $page] = web($port, 'paper.test', 'GET', '/admin/login.php', [], $jar);
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
    return web($port, 'paper.test', 'POST', '/admin/login.php', ['csrf' => $m[1] ?? '', 'email' => $email, 'password' => 'correct horse wb'], $jar)[0];
};
$alJar = "$work/jar-author";
ok($signIn('al@test.local', $alJar) === 302, 'an author signs in');
[$code] = web($port, 'paper.test', 'GET', '/admin/whiteboard.php', [], $alJar);
ok($code === 403, "an author cannot open the whiteboard (got $code)");

$edJar = "$work/jar-editor";
ok($signIn('ed@test.local', $edJar) === 302, 'an editor signs in');
$pdo->prepare('INSERT INTO whiteboard_notes (site_id, body, note_type, status, origin, posted_by, contact, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
    ->execute([$A, '<img src=x onerror=alert(1)> photo of the crash', 'tip', 'unverified', 'reader', '<b>Bo</b>', 'not-an-email <i>', now(), now()]);
[$code, $page] = web($port, 'paper.test', 'GET', '/admin/whiteboard.php', [], $edJar);
ok($code === 200 && str_contains($page, 'The bridge on 9th has been closed since dawn'), "the editor sees this paper's tips (got $code)");
ok(!str_contains($page, '<img src=x') && str_contains($page, '&lt;img src=x onerror=alert(1)&gt;') && str_contains($page, '&lt;b&gt;Bo&lt;/b&gt;')
   && !str_contains($page, 'mailto:not-an-email'), 'a tip, its name and its contact are escaped, and only a real address is linked');
$waiting = pp_wb_waiting($A);
ok($waiting === 3 && str_contains($page, "Whiteboard ($waiting)") && str_contains($page, "$waiting reader tips waiting"), "the nav and the header count the waiting tips ($waiting)");
ok(str_contains($page, 'Open draft') && str_contains($page, 'Turn into draft'), 'a drafted tip offers its draft; the others offer to become one');
ok(!str_contains($page, 'ferry delays'), 'a sister paper\'s tip is not on this board');
preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
$csrf = $m[1] ?? '';

[$code] = web($port, 'paper.test', 'POST', '/admin/whiteboard.php', ['action' => 'add', 'body' => 'No token', 'note_type' => 'lead', 'status' => 'unverified'], $edJar);
ok($code === 403 && $count("body = 'No token'") === 0, "a post without the form token is refused (got $code)");
[$code, , $loc] = web($port, 'paper.test', 'POST', '/admin/whiteboard.php',
    ['csrf' => $csrf, 'action' => 'add', 'body' => 'Mayor\'s office says a statement is coming', 'source_url' => 'https://example.test/statement',
     'note_type' => 'lead', 'status' => 'checking'], $edJar);
$staff = $pdo->query("SELECT * FROM whiteboard_notes WHERE body LIKE 'Mayor%'")->fetch();
ok($code === 302 && $staff && $staff['origin'] === 'staff' && $staff['posted_by'] === 'Ed Editor' && (int) $staff['user_id'] === $edId && (int) $staff['site_id'] === $A,
   "an editor pins a lead, signed with their name (got $code)");
[$code] = web($port, 'paper.test', 'POST', '/admin/whiteboard.php', ['csrf' => $csrf, 'action' => 'status', 'id' => $staff['id'], 'status' => 'confirmed'], $edJar);
ok($code === 302 && pp_wb_note($A, (int) $staff['id'])['status'] === 'confirmed', 'the status moves');
[$code] = web($port, 'paper.test', 'POST', '/admin/whiteboard.php', ['csrf' => $csrf, 'action' => 'status', 'id' => $otherTip, 'status' => 'debunked'], $edJar);
ok(pp_wb_note($B, $otherTip)['status'] === 'unverified', 'a note on a sister paper\'s board is out of reach by id');
[$code, , $loc] = web($port, 'paper.test', 'POST', '/admin/whiteboard.php', ['csrf' => $csrf, 'action' => 'draft', 'id' => $staff['id']], $edJar);
ok($code === 302 && preg_match('#post-edit\.php\?id=(\d+)$#', $loc, $pm) === 1 && (int) pp_wb_note($A, (int) $staff['id'])['post_id'] === (int) $pm[1],
   "turning a note into a draft opens the draft (got $code → $loc)");
[$code] = web($port, 'paper.test', 'POST', '/admin/whiteboard.php', ['csrf' => $csrf, 'action' => 'delete', 'id' => $staff['id']], $edJar);
ok($code === 302 && pp_wb_note($A, (int) $staff['id']) === null, 'a note is deleted');
$log = $pdo->query("SELECT action, target, detail FROM audit_log WHERE action LIKE 'whiteboard.%' ORDER BY id")->fetchAll();
ok(array_column($log, 'action') === ['whiteboard.add', 'whiteboard.status', 'whiteboard.draft', 'whiteboard.delete'],
   'each staff action is audited: ' . implode(', ', array_column($log, 'action')));
ok(!str_contains(json_encode($log), 'Mayor') && !str_contains(json_encode($log), 'statement'), 'the audit trail names notes, never their words');

/* --- The templates ------------------------------------------------------------ */

$mailtos = [];
foreach (array_merge(glob("$root/app/views/chrome/*.php"), glob("$root/app/views/article-*.php"), glob("$root/app/views/section-*.php")) as $file) {
    // A share-by-email link (mailto:?subject=...) opens the reader's own mail
    // and stays; a mailto to the paper, or the old setting, may not.
    if (preg_match('/mailto:(?!\?)|contact_email/', (string) file_get_contents($file))) {
        $mailtos[] = basename($file);
    }
}
ok($mailtos === [], 'no paper\'s chrome, article or section page links a mailto: ' . implode(', ', $mailtos));
ok(str_contains((string) file_get_contents("$root/router.php"), "'/tips'") && preg_match('#^RewriteRule \^tips/\?\$ tips\.php#m', (string) file_get_contents("$root/.htaccess")) === 1,
   '/tips is routed in router.php and .htaccess');

echo $fails === 0 ? "ok\n" : "$fails failure(s)\n";
exit($fails === 0 ? 0 : 1);
