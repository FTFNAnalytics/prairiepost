<?php
/**
 * Per-site indexing control (F06): every public surface carries noindex by
 * default — a missing setting means NO — and enabling one paper touches
 * that paper alone. Disposable database, two tenant hostnames.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('indexing');
pp_fixture_prepare($fx);            // migrate --apply + seed-core, explicitly
$work = $fx['work'];
$config = $fx['config'];
$dbfile = $fx['sqlite_path'] ?? '';

putenv('PP_CONFIG=' . $config);
require $root . '/app/bootstrap.php';
$pdo = db();

// A second paper, resolved by hostname, to prove the switch is site-scoped.
require_once $root . '/app/seed.php';
$siteA = current_site();                    // prairiedispatch (seeded)
$siteB = pp_create_site($pdo, 'beta-test-paper');
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?, ?, ?), (?, ?, ?)')
    ->execute(['alpha.test', $siteA['slug'], now(), 'beta.test', 'beta-test-paper', now()]);

$port = 8700 + random_int(0, 90);
$pid = (int) trim((string) shell_exec(
    'cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($config) . ' '
    . escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("$work/server.log") . ' 2>&1 & echo $!'
));
usleep(700000);
register_shutdown_function(function () use ($pid, $fx) {
    if ($pid) {
        @posix_kill($pid, 15);
    }
    pp_fixture_destroy($fx);
});

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}
function req(string $host, string $path, int $port): array
{
    $ch = curl_init("http://127.0.0.1:$port$path");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => ["Host: $host"],
    ]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $split = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$code, substr($raw, 0, $split), substr($raw, $split)];
}

/* --- Default: everything noindex, HTML meta and non-HTML header ------------- */

foreach (['/', '/search?q=x', '/corrections', '/about', '/newsletter/'] as $path) {
    [$code, , $body] = req('alpha.test', $path, $port);
    ok($code === 200 && str_contains($body, 'name="robots" content="noindex'),
        "HTML page carries noindex by default: $path");
}
foreach (['/feed/', '/sitemap.xml'] as $path) {
    [$code, $headers] = req('alpha.test', $path, $port);
    ok($code === 200 && stripos($headers, 'X-Robots-Tag: noindex') !== false,
        "non-HTML surface carries X-Robots-Tag by default: $path");
}

// The site with NO settings rows at all (a bare site row) is also noindex —
// absence of the setting is a NO, not an unknown.
[$code, , $body] = req('beta.test', '/', $port);
ok($code === 200 && str_contains($body, 'name="robots" content="noindex'),
    'a site with no indexing setting at all defaults to noindex');

/* --- Enabling ONE site opens that site only --------------------------------- */

set_setting('indexing_enabled', '1', (int) $siteB['id']);

[$code, , $body] = req('beta.test', '/', $port);
ok($code === 200 && !str_contains($body, 'name="robots"'), 'the enabled site drops the noindex meta');
[, $headers] = req('beta.test', '/feed/', $port);
ok(stripos($headers, 'X-Robots-Tag') === false, 'the enabled site drops the X-Robots-Tag header');

[$code, , $body] = req('alpha.test', '/', $port);
ok($code === 200 && str_contains($body, 'name="robots" content="noindex'),
    'the OTHER site is untouched by the neighbour\'s switch');
[, $headers] = req('alpha.test', '/sitemap.xml', $port);
ok(stripos($headers, 'X-Robots-Tag: noindex') !== false, 'the other site\'s non-HTML surfaces too');

/* --- The switch is admin-gated ---------------------------------------------- */

[$code] = req('alpha.test', '/admin/settings.php', $port);
ok($code === 302, 'the settings page (where the switch lives) rejects the unauthenticated');

/* --- New launch packs carry the default -------------------------------------- */

$defaults = pp_site_default_settings('X');
ok(($defaults['indexing_enabled'] ?? '') === '0', "the seeder's default settings ship indexing OFF");

exit($fails ? 1 : 0);
