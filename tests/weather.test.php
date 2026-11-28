<?php
/**
 * Live weather (app/weather.php, cron/weather.php).
 *
 * The record parser, on real ECCC records saved as fixtures (Winnipeg,
 * whose station reports a sky, and Drumheller, whose station doesn't),
 * and on hostile ones. Formatting. Palette validation, including every
 * paper's own block. The read side's freshness rule: no reading, a stale
 * one, one for another city or one from the future all show nothing. The
 * job against a stand-in API: each city fetched once, a failed city
 * leaving its papers' last reading alone, and the cron wrapper recording
 * a run where every city fails. Then real front pages, rendered for every
 * kind of weather slot: a fresh reading appears with its source, a stale
 * one leaves no trace, and no launch-day weather survives anywhere.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
putenv('PP_TRANSPORT_TEST=1');

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('weather');
pp_fixture_prepare($fx);
putenv('PP_CONFIG=' . $fx['config']);
putenv('PP_SITE=prairiedispatch');
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

$record = function (string $id, int $observed, ?callable $edit = null) use ($root): array {
    $json = str_replace('__OBSERVED__', gmdate('Y-m-d\TH:i:s\Z', $observed), (string) file_get_contents("$root/tests/fixtures/eccc-$id.json"));
    $r = json_decode($json, true);
    return $edit ? $edit($r) : $r;
};
$now = time();
$CREDIT = 'Data source: Environment and Climate Change Canada';

/* --- The parser, on real records ------------------------------------------- */

$w = pp_weather_parse($record('mb-38', $now - 1200));
ok($w !== null && $w['temp'] === -3.1 && $w['observed'] === $now - 1200, 'Winnipeg: temperature and observation time');
ok(($w['cond'] ?? null) === ['en' => 'Mostly Cloudy', 'fr' => 'Généralement nuageux'], 'Winnipeg: the sky the station saw, in both languages');
ok(($w['station']['en'] ?? '') === "Winnipeg Richardson Int'l Airport", 'Winnipeg: the observing station');
ok(($w['hi'] ?? null) === 0 && ($w['lo'] ?? null) === -7, 'Winnipeg: the next high and low');
ok(($w['outlook']['en'] ?? '') === 'Tonight: Mainly cloudy' && ($w['outlook']['fr'] ?? '') === 'Ce soir et cette nuit : Généralement nuageux',
    'Winnipeg: the outlook names its period, in each language\'s punctuation');
ok(str_starts_with($w['url']['en'] ?? '', 'https://weather.gc.ca/en/location/') && str_starts_with($w['url']['fr'] ?? '', 'https://meteo.gc.ca/fr/location/'),
    'Winnipeg: the forecast page in each language');
ok(is_int($w['humidity'] ?? null), 'Winnipeg: humidity');

$d = pp_weather_parse($record('ab-62', $now - 1200));
ok($d !== null && $d['temp'] === -7.6, 'Drumheller: a station with no sky still gives its temperature');
ok(($d['cond'] ?? null) === ['en' => '', 'fr' => ''], 'Drumheller: no sky reported means no condition, never a forecast passed off as one');
ok(($d['outlook']['en'] ?? '') !== '' && str_contains($d['outlook']['en'], ': '), 'Drumheller: the outlook is still there, labelled');

/* --- The parser, on records it must not trust ------------------------------ */

ok(pp_weather_parse($record('mb-38', $now, function ($r) { unset($r['properties']['currentConditions']['temperature']); return $r; })) === null,
    'no temperature: no reading');
ok(pp_weather_parse($record('mb-38', $now, function ($r) { $r['properties']['currentConditions']['timestamp'] = ['en' => 'soon']; return $r; })) === null,
    'no usable observation time: no reading');
ok(pp_weather_parse(['type' => 'Feature']) === null && pp_weather_parse(['properties' => 'x']) === null, 'not a city record: no reading');
$hostile = pp_weather_parse($record('mb-38', $now, function ($r) {
    $r['properties']['url'] = ['en' => 'https://evil.example/phish', 'fr' => 'https://meteo.gc.ca/"><script>alert(1)</script>'];
    $r['properties']['currentConditions']['condition'] = ['en' => "Mostly\x00\n  Cloudy" . str_repeat('!', 200), 'fr' => 'x'];
    return $r;
}));
ok(($hostile['url'] ?? null) === ['en' => 'https://weather.gc.ca/', 'fr' => 'https://meteo.gc.ca/'],
    'only ECCC\'s own pages become links; anything else falls back to its home page');
ok(str_starts_with($hostile['cond']['en'] ?? '', 'Mostly Cloudy!') && mb_strlen($hostile['cond']['en']) === 60,
    'control characters out, whitespace collapsed, length bounded');
$single = pp_weather_parse($record('mb-38', $now, function ($r) {
    $r['properties']['forecastGroup']['forecasts'][0]['temperatures']['temperature'] = ['class' => ['en' => 'low'], 'value' => ['en' => -12]];
    return $r;
}));
ok(($single['lo'] ?? null) === -12, 'one temperature sent as an object rather than a list is still read');

/* --- Formatting and configuration ------------------------------------------- */

ok(pp_weather_temp(-0.4) === '0°C' && pp_weather_temp(-3.5) === '-4°C' && pp_weather_temp(12.1) === '12°C', 'temperatures round, and never read "-0°C"');
foreach ([
    [['city' => 'mb-38', 'place' => 'Winnipeg'], true, 'a valid block'],
    [['city' => 'xx-38', 'place' => 'Winnipeg'], false, 'an unknown province'],
    [['city' => 'mb-38a', 'place' => 'Winnipeg'], false, 'a malformed identifier'],
    [['city' => 'mb-38', 'place' => ''], false, 'no place'],
    [['city' => 'mb-38', 'place' => str_repeat('W', 41)], false, 'a place too long for a masthead'],
    ['mb-38', false, 'a bare string'],
] as [$block, $valid, $label]) {
    ok((pp_weather_config_from(['weather' => $block]) !== null) === $valid, "palette block: $label");
}
$expected = ['bison-bulletin', 'borealis-bulletin', 'brampton-bulletin', 'brandon-bulletin', 'burrard-brief', 'cariboo-compass', 'chutes',
    'citadel-courier', 'edmonton-echo', 'grande-prairie-gazette', 'kelowna-current', 'kermode-chronicle', 'kitchener-chronicle',
    'london-lookout', 'memphre', 'pacific-post', 'portage-press', 'prairiedispatch', 'red-river-register', 'rideau-review',
    'surrey-standard', 'terminal-city-times', 'toronto-telegraph'];
foreach (glob("$root/assets/sites/*/palette.json") as $file) {
    $slug = basename(dirname($file));
    $palette = json_decode((string) file_get_contents($file), true);
    $has = array_key_exists('weather', (array) $palette);
    ok($has === in_array($slug, $expected, true), "$slug: " . ($has ? 'has' : 'has no') . ' weather block, as expected');
    ok(!$has || pp_weather_config($slug) !== null, "$slug: its weather block is valid");
}

/* --- The read side: shown only while fresh ---------------------------------- */

$store = function (array $reading, string $city, string $place, ?int $siteId = null): void {
    set_setting('weather_live', (string) json_encode($reading + ['city' => $city, 'place' => $place, 'fetched' => time()]), $siteId);
};
ok(pp_weather() === null && pp_weather_line() === '' && pp_weather_text() === '' && pp_weather_link('x') === ''
    && pp_weather_today() === [] && pp_weather_source_html() === '', 'no reading: every helper renders nothing');

$store($d, 'ab-62', 'Drumheller');
$v = pp_weather();
ok(($v['place'] ?? '') === 'Drumheller' && ($v['temp'] ?? '') === '-8°C', 'a fresh reading: the place from the palette, the temperature rounded');
ok(pp_weather_line() === '-8°C' && pp_weather_text() === 'Drumheller · -8°C', 'no sky reported: the strips show the temperature alone');
// Alberta's clock, not the network's (the fixture config runs on Toronto
// time). Computed through the same tz database as the code, so the test
// holds on servers before and after Alberta's 2026 change.
$clock = static fn (string $zone): string => str_replace(['am', 'pm'], ['a.m.', 'p.m.'],
    (new DateTimeImmutable('@' . (int) $d['observed']))->setTimezone(new DateTimeZone($zone))->format('g:i a'));
ok(($v['observed'] ?? '') === 'Observed ' . $clock('America/Edmonton') && $clock('America/Edmonton') !== $clock('America/Toronto'),
    'the observation time on Alberta\'s clock, not Toronto\'s, written a.m./p.m.: ' . ($v['observed'] ?? ''));
ok(str_starts_with($v['title'] ?? '', $CREDIT . ' · Drumheller, observed '), 'the link title credits ECCC and names the station');
$link = pp_weather_link('x', 'pp-wx');
ok(str_contains($link, 'href="https://weather.gc.ca/en/location/') && str_contains($link, 'class="pp-wx"') && str_contains($link, 'rel="external"'),
    'the strips link to the city\'s forecast');
$today = pp_weather_today();
ok(($today['line'] ?? '') === $d['outlook']['en'] && str_contains($today['source'] ?? '', $CREDIT), 'the forecast box falls back to the labelled outlook and carries the source');

$store($d, 'ab-62', 'Drumheller');
$stale = $d; $stale['observed'] = $now - 4 * 3600;
$store($stale, 'ab-62', 'Drumheller');
ok(pp_weather() === null, 'a reading four hours old is not shown');
$future = $d; $future['observed'] = $now + 2 * 3600;
$store($future, 'ab-62', 'Drumheller');
ok(pp_weather() === null, 'a reading from the future is not shown');
$store($w, 'mb-38', 'Winnipeg');
ok(pp_weather() === null, 'a reading for a city the palette no longer names is not shown');

/* --- The job, against a stand-in API ---------------------------------------- */

$sites = [];
foreach (['civismedia', 'portage-press', 'bison-bulletin', 'toronto-telegraph', 'no-such-paper'] as $slug) {
    $pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?, ?, ?)')->execute([$slug, $slug, now()]);
}
foreach ($pdo->query('SELECT id, slug FROM sites') as $row) {
    $sites[$row['slug']] = (int) $row['id'];
}
$port = 8800 + random_int(0, 90);
$log = "{$fx['work']}/weather-server.log";
$fetches = "{$fx['work']}/weather-fetches.log";
putenv("PP_WEATHER_FIXTURE_LOG=$fetches");
$pid = (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && ' . escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port
    . ' tests/fixtures/weather-server.php >' . escapeshellarg($log) . ' 2>&1 & echo $!'));
register_shutdown_function(function () use ($pid, $fx) {
    if ($pid) {
        @posix_kill($pid, 15);
    }
    pp_fixture_destroy($fx);
});
usleep(600000);
pp_http_test_hooks(['public_ips' => ['127.0.0.1'], 'resolver' => fn (string $h) => $h === 'weather.test' ? ['127.0.0.1'] : []]);
putenv("PP_WEATHER_TEST_API=http://weather.test:$port/items/");

$old = $w; $old['observed'] = $now - 7200;
$store($old, 'on-143', 'Toronto', $sites['toronto-telegraph']);
$before = pp_setting_for_site($sites['toronto-telegraph'], 'weather_live');
[$lines, $okCities, $failed] = pp_weather_refresh_all();
$out = implode("\n", $lines);
ok($okCities === 2 && $failed === 1, "two cities refreshed, one failed\n$out");
foreach (['prairiedispatch', 'portage-press', 'bison-bulletin'] as $slug) {
    ok(str_contains($out, "ok    $slug: "), "$slug: refreshed");
}
ok(str_contains($out, 'WARN  on-143:') && str_contains($out, 'toronto-telegraph keep their last reading'), 'the failed city is named, with the papers it leaves as they were');
ok(pp_setting_for_site($sites['toronto-telegraph'], 'weather_live') === $before, 'a failed city leaves its papers\' last reading untouched');
$portage = json_decode(pp_setting_for_site($sites['portage-press'], 'weather_live'), true);
$bison = json_decode(pp_setting_for_site($sites['bison-bulletin'], 'weather_live'), true);
ok(($portage['city'] ?? '') === 'mb-38' && ($portage['place'] ?? '') === 'Winnipeg' && ($portage['temp'] ?? null) === -3.1
    && abs(time() - 1200 - (int) ($portage['observed'] ?? 0)) < 120, 'Portage stores Winnipeg\'s reading, with its observation time');
ok(($bison['observed'] ?? null) === ($portage['observed'] ?? 0) && ($bison['place'] ?? '') === 'Winnipeg', 'Bison shares the same reading');
$paths = file($fetches, FILE_IGNORE_NEW_LINES) ?: [];
ok(count(array_keys($paths, '/items/mb-38')) === 1 && count($paths) === 3, "two papers in one city cost one fetch; three cities, three fetches\n" . implode("\n", $paths));
ok(pp_setting_for_site($sites['no-such-paper'], 'weather_live') === '' && pp_setting_for_site($sites['civismedia'], 'weather_live') === '',
    'a site without a weather block gets nothing');

putenv('PP_WEATHER_TEST_API=http://weather.test:' . $port . '/items/qc-998?');
pp_weather_refresh_all();   // every request now answers "maintenance": nothing stored, nothing broken
ok(json_decode(pp_setting_for_site($sites['portage-press'], 'weather_live'), true)['observed'] === $portage['observed'], 'an answer that is not JSON changes nothing');

// Through the wrapper, in its own process: the transport hooks aren't armed
// there, so every fetch of the stand-in is refused, which is the every-city-
// failed path. The wrapper must exit 1 and record the failure for the watch.
exec('cd ' . escapeshellarg($root) . ' && PP_SITE=civismedia PP_CONFIG=' . escapeshellarg($fx['config'])
    . ' PP_WEATHER_TEST_API=' . escapeshellarg("http://weather.test:$port/items/") . ' ' . escapeshellarg(PHP_BINARY) . ' cron/run.php weather 2>&1', $wrapOut, $wrapCode);
$wrapOut = implode("\n", $wrapOut);
ok($wrapCode === 1 && str_contains($wrapOut, 'ERROR every city failed'), "the wrapper runs the job, and every city failing fails the run\n$wrapOut");
$runs = pp_ops_latest();
ok(isset($runs['weather']) && !$runs['weather']['ok'], 'the run is in the ops ledger, marked failed');
ok(str_contains((string) file_get_contents("$root/cron/watch.php"), "'weather' => 2 * 3600"), 'the watch expects the weather job at least every two hours');
exec('cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($fx['config']) . ' PP_SITE=portage-press '
    . escapeshellarg(PHP_BINARY) . ' cron/weather.php 2>&1', $paperOut, $paperCode);
ok(str_contains(implode("\n", $paperOut), 'runs on the hub'), 'off the hub, the job explains itself and does nothing');

/* --- Real front pages, one per kind of weather slot ------------------------- */

// Each paper's launch pack, through the real seeder: the boxes beside a
// lead story only render once the paper has one.
$papers = [
    'prairiedispatch'        => 'the default chrome strip and the default front page\'s forecast box',
    'portage-press'          => 'a split strip with its own suffix, and a box with a source line',
    'kitchener-chronicle'    => 'a front-page box with a source line',
    'toronto-telegraph'      => 'a rail box',
    'brandon-bulletin'       => 'a box that used to draw a sun',
    'edmonton-echo'          => 'the Echo\'s forecast card',
    'kermode-chronicle'      => 'the date line',
    'grande-prairie-gazette' => 'a strip that linked to the weather desk',
    'kelowna-current'        => 'a phrase in the utility bar',
    'burrard-brief'          => 'a strip with a fixed city prefix',
    'surrey-standard'        => 'a split strip',
];
foreach (array_keys($papers) as $slug) {
    if ($slug !== 'prairiedispatch') {
        pp_fixture_tool($fx, 'tools/seed-launch.php', 'PP_SITE=' . escapeshellarg($slug));
    }
}
$ids = [];
foreach ($pdo->query('SELECT id, slug FROM sites') as $row) {
    $ids[$row['slug']] = (int) $row['id'];
}
$launchWeather = ['27°C', '24°C', '21°C', '-2°C|', 'Hazy sun', 'smog advisory', 'Updated each morning by the newsroom', 'Frost risk in the valley'];
$render = function (string $slug) use ($fx, $root): string {
    $driver = "{$fx['work']}/render-front.php";
    file_put_contents($driver, "<?php\nchdir(\$argv[1]);\n\$_SERVER['REQUEST_URI'] = '/';\n\$_SERVER['REQUEST_METHOD'] = 'GET';\n"
        . "\$_SERVER['HTTP_HOST'] = 'localhost';\nrequire \$argv[1] . '/index.php';\n");
    exec('PP_CONFIG=' . escapeshellarg($fx['config']) . ' PP_SITE=' . escapeshellarg($slug) . ' '
        . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($driver) . ' ' . escapeshellarg($root) . ' 2>&1', $html, $code);
    return $code === 0 ? implode("\n", $html) : "EXIT $code: " . implode("\n", $html);
};
foreach ($papers as $slug => $kind) {
    $cfg = pp_weather_config($slug);
    if (!ok(isset($ids[$slug]) && $cfg !== null, "$slug: seeded, with a weather city")) {
        continue;
    }
    $fresh = $w; $fresh['observed'] = time() - 900; $fresh['temp'] = -13.4;
    $store($fresh, $cfg['city'], $cfg['place'], $ids[$slug]);
    $html = $render($slug);
    $clean = !str_contains($html, 'EXIT ') && preg_match('/(Warning|Notice|Deprecated|Fatal error):/', $html) !== 1 && str_contains($html, '</html>');
    ok($clean, "$slug: the front page renders cleanly with a reading" . ($clean ? '' : "\n" . mb_substr($html, 0, 600)));
    ok(str_contains($html, '-13°C'), "$slug ($kind): the live temperature is on the page");
    ok(str_contains($html, 'https://weather.gc.ca/en/location/index.html?coords=49.89,-97.13'), "$slug: it links to the forecast it came from");
    ok(str_contains($html, $CREDIT), "$slug: it credits Environment and Climate Change Canada");
    foreach ($launchWeather as $leftover) {
        ok(!str_contains($html, $leftover), "$slug: no launch-day weather (\"$leftover\") survives");
    }
    $old = $fresh; $old['observed'] = time() - 5 * 3600;
    $store($old, $cfg['city'], $cfg['place'], $ids[$slug]);
    $html = $render($slug);
    ok(!str_contains($html, '-13°C') && !str_contains($html, 'weather.gc.ca') && !str_contains($html, $CREDIT),
        "$slug: a stale reading leaves no trace on the page");
}

exit($fails ? 1 : 0);
