<?php
/**
 * Each paper prints on its own clock (PLAN.md 9.8; pp_paper_zone() and
 * pp_date() in app/i18n.php).
 *
 * - Every palette's zone is a real one, and a paper that reports a
 *   city's weather keeps the clock of that city's province.
 * - A stored time (written on the network's clock, here Toronto's) prints
 *   on the paper's clock through every shared helper: fmt_date(),
 *   time_label(), pp_clock(), pp_date_long().
 * - A paper with no zone, or a broken one, prints on the network's clock.
 * - The newsroom prints on the network's clock, because its date inputs
 *   are read on it.
 *
 * Expected times are computed through the same tz database as the code, so
 * the test holds on a server whose tzdata predates 2026's western changes.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('clock');
pp_fixture_prepare($fx);
register_shutdown_function(fn () => pp_fixture_destroy($fx));
putenv('PP_CONFIG=' . $fx['config']);
define('PP_TEST_BOOT', 1);
require $root . '/app/bootstrap.php';
echo 'engine: ' . db()->getAttribute(PDO::ATTR_DRIVER_NAME) . " ({$fx['id']})\n";

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

/* --- Every palette ---------------------------------------------------------- */

$valid = DateTimeZone::listIdentifiers();
$unzoned = [];
foreach (glob("$root/assets/sites/*/palette.json") as $file) {
    $slug = basename(dirname($file));
    $p = json_decode((string) file_get_contents($file), true);
    if (!isset($p['zone'])) {
        $unzoned[] = $slug;
        continue;
    }
    ok(in_array($p['zone'], $valid, true), "$slug: its zone {$p['zone']} is a real time zone");
    $wx = pp_weather_config_from($p);
    if ($wx !== null) {
        $province = PP_WEATHER_ZONES[substr($wx['city'], 0, 2)];
        ok($p['zone'] === $province, "$slug: its clock ({$p['zone']}) is its weather city's ($province)");
    }
}
sort($unzoned);
ok($unzoned === ['civismedia', 'turtle-island-times', 'westernwire'],
   'only the hub and the two papers with no single home city keep the network\'s clock: ' . implode(', ', $unzoned));

/* --- What each paper prints --------------------------------------------------- */

// 05:30 UTC five days ago, stored on the network's clock (the fixture's,
// Toronto) exactly as now() writes it. Old enough that time_label() prints
// a date, not "Yesterday"; and at that hour Toronto and Vancouver are on
// different calendar days, so the date is a real test too.
$base = time() - 5 * 86400;
$instant = $base - ($base % 86400) + 5 * 3600 + 1800;
$stored = (new DateTimeImmutable('@' . $instant))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
$on = static fn (string $zone, string $fmt): string => (new DateTimeImmutable('@' . $instant))->setTimezone(new DateTimeZone($zone))->format($fmt);
$ampm = static fn (string $s): string => strtolower(str_replace(['AM', 'PM'], ['a.m.', 'p.m.'], $s));

/** Bootstrap as one paper in a fresh process and print what its helpers say. */
$ask = function (string $slug, string $extra = '') use ($fx, $root, $stored): array {
    $code = 'define("PP_TEST_BOOT", 1); require ' . var_export("$root/app/bootstrap.php", true) . '; ' . $extra
          . ' echo json_encode(["zone" => pp_paper_zone()->getName(), "fmt" => fmt_date(' . var_export($stored, true) . ', "g:i a"),'
          . ' "label" => time_label(' . var_export($stored, true) . '), "clock" => pp_clock(strtotime(' . var_export($stored, true) . ')),'
          . ' "long" => pp_date_long(strtotime(' . var_export($stored, true) . '))]);';
    $out = shell_exec('cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($fx['config']) . ' PP_SITE=' . escapeshellarg($slug) . ' '
        . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1');
    return json_decode((string) $out, true) ?: ['raw' => (string) $out];
};

// The sites must exist for the resolver to pick them.
foreach (['kermode-chronicle', 'portage-press', 'edmonton-echo', 'kitchener-chronicle', 'westernwire'] as $slug) {
    pp_fixture_tool($fx, 'tools/seed-launch.php', 'PP_SITE=' . escapeshellarg($slug));
}
$network = date_default_timezone_get();
foreach (['kermode-chronicle' => 'America/Vancouver', 'portage-press' => 'America/Winnipeg',
          'edmonton-echo' => 'America/Edmonton', 'kitchener-chronicle' => 'America/Toronto'] as $slug => $zone) {
    $got = $ask($slug);
    if (!ok(($got['zone'] ?? '') === $zone, "$slug: prints on $zone's clock (got " . json_encode($got) . ')')) {
        continue;
    }
    $want = $on($zone, 'g:i a');
    ok(($got['fmt'] ?? '') === $want, "$slug: fmt_date() prints the stored 05:30 UTC as $want");
    ok(($got['label'] ?? '') === $on($zone, 'M j'), "$slug: time_label() dates it on its own calendar");
    ok(($got['clock'] ?? '') === $ampm($on($zone, 'g:i A')), "$slug: pp_clock() agrees");
    ok(($got['long'] ?? '') === $on($zone, 'F j, Y'), "$slug: pp_date_long() agrees");
}
ok($on('America/Vancouver', 'G') !== $on($network, 'G') && $on('America/Vancouver', 'M j') !== $on($network, 'M j'),
   'the check means something: in Victoria it is a different hour and a different day');

// "Today" is the paper's too. At 06:00 UTC it is already tomorrow in
// Toronto and still tonight in Victoria, so a story stored at 05:30 UTC is
// "12:30 a.m."-ish in Toronto and "10:30 p.m."-ish in Victoria, both today.
$now = $instant + 1800;
$todayLabel = function (string $slug) use ($fx, $root, $stored, $now): string {
    $code = 'define("PP_TEST_BOOT", 1); require ' . var_export("$root/app/bootstrap.php", true) . '; echo time_label('
          . var_export($stored, true) . ', ' . $now . ');';
    return trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($fx['config']) . ' PP_SITE='
        . escapeshellarg($slug) . ' ' . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1'));
};
ok($todayLabel('kermode-chronicle') === $ampm($on('America/Vancouver', 'g:i A')), 'time_label(): a story from earlier tonight in Victoria is today\'s, on Victoria\'s clock');
ok($todayLabel('kitchener-chronicle') === $ampm($on('America/Toronto', 'g:i A')), 'time_label(): and the same story is today\'s in Kitchener, on Kitchener\'s clock');

$got = $ask('westernwire');
ok(($got['zone'] ?? '') === $network && ($got['fmt'] ?? '') === $on($network, 'g:i a'), 'a paper with no zone prints on the network\'s clock');

$got = $ask('kermode-chronicle', 'require ' . var_export("$root/admin/_layout.php", true) . ';');
ok(($got['zone'] ?? '') === $network && ($got['fmt'] ?? '') === $on($network, 'g:i a'),
   'the newsroom prints on the network\'s clock, even on a paper\'s domain (got ' . json_encode($got) . ')');

// A broken zone falls back rather than failing the page.
$brokenDir = "$root/assets/sites/clock-test-broken";
@mkdir($brokenDir);
file_put_contents("$brokenDir/palette.json", json_encode(['zone' => 'Mars/Olympus_Mons', 'chrome' => ['template' => 'none']]));
db()->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?,?,?)')->execute(['Broken clock', 'clock-test-broken', now()]);
$got = $ask('clock-test-broken');
@unlink("$brokenDir/palette.json");
@rmdir($brokenDir);
ok(($got['zone'] ?? '') === $network, 'a zone that does not exist falls back to the network\'s clock');

exit($fails ? 1 : 0);
