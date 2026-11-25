<?php
/**
 * Widgets that are only true for a while (app/freshness.php), and London's
 * illustrative panels, gone.
 *
 * - The rules: a banner needs an expiry ahead; traffic and prices show for
 *   a set time after an editor changes them; an event shows through its
 *   own date, and an event with no readable date never shows.
 * - Settings, over HTTP as an administrator: a new banner gets six hours,
 *   saving Settings for another reason never puts a stale banner or stale
 *   prices back up, and a time in the past is refused.
 * - Real front pages carrying the launch-day values production still holds
 *   (banners, August events, traffic that links to "#", invented prices,
 *   London's council tracker, open files and member count): none of it
 *   shows. Live values, given a clock, do.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('freshness');
pp_fixture_prepare($fx);
putenv('PP_CONFIG=' . $fx['config']);
define('PP_TEST_BOOT', 1);
require $root . '/app/bootstrap.php';
$pdo = db();
echo 'engine: ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . " ({$fx['id']})\n";

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

/* --- The rules ------------------------------------------------------------- */

$now = mktime(12, 0, 0, 11, 25, 2026);
$day = static fn (int $m, int $d, int $y): int => mktime(0, 0, 0, $m, $d, $y);
$cases = [
    'Aug 13' => $day(8, 13, 2026), 'Sat Aug 15' => $day(8, 15, 2026), 'Dec 4' => $day(12, 4, 2026),
    '4 Dec' => $day(12, 4, 2026), 'Dec 4, 2027' => $day(12, 4, 2027), '2026-12-04' => $day(12, 4, 2026),
    'Sept 9 · Chamber' => $day(9, 9, 2026), 'Nov 25' => $day(11, 25, 2026),
    'Saturday' => null, 'Tuesday, 9:30 a.m.' => null, 'Feb 30' => null, '' => null, 'next week' => null,
];
foreach ($cases as $text => $want) {
    ok(pp_event_day($text, $now) === $want, "an event dated \"$text\" falls on " . ($want === null ? 'no day' : date('Y-m-d', $want)));
}
ok(pp_event_day('Jan 4', mktime(12, 0, 0, 12, 20, 2026)) === $day(1, 4, 2027), 'January written in December means next January');

$rows = [['Past', '#', 'Aug 13'], ['Today', '/a', 'Nov 25'], ['Soon', '/b', 'Dec 4', 'Hall'], ['Undated', '#'], ['Weekday', '#', 'Saturday'], 'junk'];
ok(array_column(pp_events_upcoming($rows, $now), 0) === ['Today', 'Soon'], 'the events list keeps today and later, and drops past, undated and malformed rows');

set_setting('breaking_label', 'Water main break on 9th');
set_setting('breaking_url', '/story/water-main');
set_setting('breaking_until', '');
ok(!pp_breaking_live($now), 'a banner with no expiry never shows (every launch-day banner)');
set_setting('breaking_until', (string) ($now + 60));
ok(pp_breaking_live($now), 'a banner shows until its time');
ok(!pp_breaking_live($now + 61), 'and not a second after');
set_setting('breaking_url', '');
ok(!pp_breaking_live($now), 'a banner with no link never shows');

set_setting('markets', json_encode([['Canola', '687.40', '+4.10']]));
set_setting('markets_saved', '');
ok(pp_fresh_rows('markets', $now) === [], 'prices nobody dated never show (the seeded demo prices)');
set_setting('markets_saved', (string) ($now - 29 * 3600));
ok(count(pp_fresh_rows('markets', $now)) === 1, 'prices show for 30 hours after they change');
set_setting('markets_saved', (string) ($now - 31 * 3600));
ok(pp_fresh_rows('markets', $now) === [], 'and not after');
set_setting('traffic_items', json_encode([['Groat Road single lane', '/story/groat']]));
set_setting('traffic_items_saved', (string) ($now - 13 * 3600));
ok(pp_fresh_rows('traffic_items', $now) === [], 'traffic is gone after 12 hours');
ok(pp_fresh_rows('events_items', $now) === [], 'a key with no clock of its own is never "fresh"');

/* --- Settings, as an administrator ----------------------------------------- */

$pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
    ->execute(['Ada Admin', 'ada@test.local', password_hash('correct horse fr', PASSWORD_DEFAULT), 'admin', 'ada-admin-fr', now()]);
$port = 8950 + random_int(0, 40);
$serverPid = (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($fx['config']) . ' '
    . escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("{$fx['work']}/server.log") . ' 2>&1 & echo $!'));
usleep(700000);
register_shutdown_function(function () use ($serverPid, $fx) {
    if ($serverPid) {
        @posix_kill($serverPid, 15);
    }
    pp_fixture_destroy($fx);
});
$jar = "{$fx['work']}/jar";
$web = function (string $method, string $path, array $fields = []) use ($port, $jar): array {
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false,
                            CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body];
};
/** A setting as the server left it, read fresh (the in-process cache knows nothing of the server's writes). */
$stored = function (string $key) use ($pdo): string {
    $s = $pdo->prepare('SELECT svalue FROM settings WHERE site_id = ? AND skey = ?');
    $s->execute([current_site_id(), $key]);
    $v = (string) ($s->fetchColumn() ?: '');
    $s->closeCursor();
    return $v;
};
[, $page] = $web('GET', '/admin/login.php');
preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
[$code] = $web('POST', '/admin/login.php', ['csrf' => $m[1] ?? '', 'email' => 'ada@test.local', 'password' => 'correct horse fr']);
ok($code === 302, "an administrator signs in (got $code)");
[$code, $page] = $web('GET', '/admin/settings.php');
preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m);
$csrf = $m[1] ?? '';
ok($code === 200 && str_contains($page, 'name="breaking_until"'), 'Settings offers a "show until" time for the banner');

$save = fn (array $fields): array => $web('POST', '/admin/settings.php', ['csrf' => $csrf] + $fields);
$save(['breaking_label' => 'Storm closes schools', 'breaking_url' => '/story/storm', 'breaking_until' => '']);
$until = (int) $stored('breaking_until');
ok(abs($until - (time() + PP_BREAKING_DEFAULT)) < 60, 'a new banner saved with no time stays up six hours');
$save(['breaking_label' => 'Storm closes schools', 'breaking_url' => '/story/storm', 'breaking_until' => date('Y-m-d\TH:i', $until), 'tagline' => 'Unrelated']);
ok(abs((int) $stored('breaking_until') - $until) < 60, 'an unrelated save keeps the banner\'s time');
$pdo->prepare('UPDATE settings SET svalue = ? WHERE site_id = ? AND skey = ?')->execute([(string) (time() - 60), current_site_id(), 'breaking_until']);
$save(['breaking_label' => 'Storm closes schools', 'breaking_url' => '/story/storm', 'breaking_until' => '', 'tagline' => 'Unrelated again']);
ok((int) $stored('breaking_until') < time(), 'an unrelated save leaves a banner whose time has passed down');
$pdo->prepare('UPDATE settings SET svalue = ? WHERE site_id = ? AND skey = ?')->execute(['', current_site_id(), 'breaking_until']);
$save(['breaking_label' => 'Storm closes schools', 'breaking_url' => '/story/storm', 'breaking_until' => '']);
ok($stored('breaking_until') === '', 'a launch-day banner that never had a time stays down through a save');
[$code, $page] = $save(['breaking_label' => 'Storm closes schools', 'breaking_url' => '/story/storm', 'breaking_until' => date('Y-m-d\TH:i', time() - 7200)]);
ok($stored('breaking_until') === '', 'a "show until" time in the past is refused and the banner stays down');

$prices = json_encode([['Canola Jan', '701.10', '+2.00']]);
$save(['markets' => $prices]);
$stamp = (int) $stored('markets_saved');
ok(abs($stamp - time()) < 60, 'changing the prices starts their clock');
$pdo->prepare('UPDATE settings SET svalue = ? WHERE site_id = ? AND skey = ?')->execute([(string) (time() - 40 * 3600), current_site_id(), 'markets_saved']);
$save(['markets' => $prices, 'tagline' => 'Unrelated a third time']);
ok((int) $stored('markets_saved') < time() - 39 * 3600, 'saving Settings with the same prices does not make them look current');
$save(['traffic_items' => json_encode([['Whitemud Drive lane closures', '/story/whitemud']])]);
ok(abs((int) $stored('traffic_items_saved') - time()) < 60, 'changing the traffic box starts its clock');

/* --- Front pages holding production's launch-day values -------------------- */

$papers = ['brampton-bulletin', 'mississauga-monitor', 'kelowna-current', 'edmonton-echo', 'grande-prairie-gazette', 'london-lookout'];
foreach ($papers as $slug) {
    pp_fixture_tool($fx, 'tools/seed-launch.php', 'PP_SITE=' . escapeshellarg($slug));
}
$ids = [];
foreach ($pdo->query('SELECT id, slug FROM sites') as $row) {
    $ids[$row['slug']] = (int) $row['id'];
}
// Exactly what the launch packs wrote before 25 Nov, and production still holds.
$launch = [
    'brampton-bulletin' => ['breaking_label' => 'Budget 2027 — council debates the DC freeze', 'breaking_url' => '/story/council-votes-7-4-to-freeze-development-charges-for-two-years'],
    'mississauga-monitor' => ['breaking_label' => 'Multi-vehicle crash on Highway 401 eastbound causes major delays in Mississauga', 'breaking_url' => '/story/mm-401-crash-eastbound-delays'],
    'kelowna-current' => ['breaking_label' => 'Morning briefing: the five B.C. stories shaping the day', 'breaking_url' => '/newsletter/'],
    'edmonton-echo' => [
        'traffic_items' => json_encode([['Yellowhead closures at 97 Street', '#'], ['Groat Road single lane at the bridge', '#']]),
        'events_items' => json_encode([["Downtown farmers' market, Saturday", '#'], ['City council meets Tuesday, 9:30 a.m.', '#']]),
    ],
    'grande-prairie-gazette' => ['events_items' => json_encode([['Bear Creek Folk Festival opens', '#', 'Aug 13', 'Muskoseepi Park · 5 p.m.']])],
    'london-lookout' => [
        'council_meeting' => '26 Aug meeting',
        'council_tracker' => json_encode([['state' => 'Passed', 'item' => 'Vacant unit tax, 1% for 2027']]),
        'open_files' => json_encode([['tag' => 'Day 41', 'status' => 'FOI pending', 'title' => 'What did the city pay for the Adelaide land assembly?', 'note' => 'x']]),
        'council_agenda' => json_encode([['what' => 'Budget committee', 'when' => 'Tue 2 Sep, 09:30']]),
        'member_pitch' => 'The Lookout is funded by readers in London.', 'member_count' => '1240', 'member_goal' => '2000',
    ],
    'prairiedispatch' => ['markets' => json_encode([['Canola Nov', '687.40', '+4.10']]), 'markets_note' => 'Prices last updated 4:15 p.m.', 'markets_saved' => ''],
];
foreach ($launch as $slug => $settings) {
    foreach ($settings as $k => $v) {
        set_setting($k, $v, $ids[$slug]);
    }
}
$render = function (string $slug, string $path = '/') use ($fx, $root): string {
    $driver = "{$fx['work']}/render.php";
    file_put_contents($driver, "<?php\nchdir(\$argv[1]);\n\$_SERVER['REQUEST_URI'] = \$argv[2];\n\$_SERVER['REQUEST_METHOD'] = 'GET';\n"
        . "\$_SERVER['HTTP_HOST'] = 'localhost';\nif (preg_match('#^/desk/([a-z0-9-]+)#', \$argv[2], \$m)) { \$_GET['slug'] = \$m[1]; require \$argv[1] . '/section.php'; }\n"
        . "else { require \$argv[1] . '/index.php'; }\n");
    exec('PP_CONFIG=' . escapeshellarg($fx['config']) . ' PP_SITE=' . escapeshellarg($slug) . ' '
        . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($driver) . ' ' . escapeshellarg($root) . ' ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    return $code === 0 ? implode("\n", $out) : "EXIT $code: " . implode("\n", $out);
};
$clean = fn (string $html): bool => !str_contains($html, 'EXIT ') && preg_match('/(Warning|Notice|Deprecated|Fatal error):/', $html) !== 1 && str_contains($html, '</html>');

$gone = [
    'brampton-bulletin' => ['council debates the DC freeze'],
    // The banner's words are also a launch story's headline, so look for the bar itself.
    'mississauga-monitor' => ['class="mm-breaking"', 'Breaking news'],
    'kelowna-current' => ['the five B.C. stories shaping the day'],
    'edmonton-echo' => ['Yellowhead closures', 'Downtown farmers', 'City council meets Tuesday'],
    'grande-prairie-gazette' => ['Bear Creek Folk Festival'],
    'london-lookout' => ['Council tracker', 'Vacant unit tax', 'Open files we are still chasing', 'Adelaide land assembly', '1,240 members', 'Reader funded', '26 Aug meeting'],
    'prairiedispatch' => ['687.40', 'Prices last updated'],
];
foreach ($gone as $slug => $needles) {
    $html = $render($slug);
    if (!ok($clean($html), "$slug: the front page renders cleanly" . ($clean($html) ? '' : "\n" . mb_substr($html, 0, 600)))) {
        continue;
    }
    foreach ($needles as $needle) {
        ok(!str_contains($html, $needle) && !str_contains($html, htmlspecialchars($needle, ENT_QUOTES)), "$slug: the launch-day \"$needle\" is gone");
    }
}
if (category_by_slug('city-hall')) {
    $desk = $render('london-lookout', '/desk/city-hall');
    ok($clean($desk) && !str_contains($desk, 'Next at City Hall') && !str_contains($desk, 'Budget committee'), 'london-lookout: the city-hall desk has no invented agenda');
}

// The same widgets, given a clock, come back.
set_setting('breaking_until', (string) (time() + 3600), $ids['brampton-bulletin']);
ok(str_contains($render('brampton-bulletin'), 'council debates the DC freeze'), 'brampton-bulletin: a banner with time ahead shows');
$soon = date('M j', time() + 2 * 86400);
set_setting('events_items', json_encode([['Winter market opens', '/story/winter-market', $soon, 'Muskoseepi Park']]), $ids['grande-prairie-gazette']);
ok(str_contains($render('grande-prairie-gazette'), 'Winter market opens'), "grande-prairie-gazette: an event dated $soon shows");
set_setting('traffic_items_saved', (string) time(), $ids['edmonton-echo']);
ok(str_contains($render('edmonton-echo'), 'Yellowhead closures'), 'edmonton-echo: traffic saved just now shows');
set_setting('markets_saved', (string) time(), $ids['prairiedispatch']);
ok(str_contains($render('prairiedispatch'), '687.40'), 'prairiedispatch: prices changed just now show');

exit($fails ? 1 : 0);
