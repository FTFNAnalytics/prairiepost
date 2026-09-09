<?php
/**
 * Internal-path protection through the dev-server router (the router
 * mirrors .htaccess and the nginx template; those are exercised by their
 * own servers in production — see the handoff's server matrix). Sensitive
 * trees, dotfiles, tools and encoded traversal variants must all be
 * refused while every legitimate route and asset still answers.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$work = sys_get_temp_dir() . '/pp-paths-' . bin2hex(random_bytes(4));
mkdir($work);
$dbfile = $work . '/t.sqlite';
$config = $work . '/config.php';
file_put_contents($config, "<?php\nreturn ['db' => ['driver' => 'sqlite', 'sqlite_path' => '$dbfile'],\n"
    . " 'site_slug' => 'prairiedispatch', 'hub_slug' => 'civismedia', 'site_url' => '', 'timezone' => 'America/Toronto', 'debug' => false];\n");

// Boot once so the database exists and a public story slug is known.
putenv('PP_CONFIG=' . $config);
require $root . '/app/bootstrap.php';
$storySlug = (string) db()->query("SELECT slug FROM posts WHERE status = 'published' ORDER BY id LIMIT 1")->fetchColumn();

$port = 8600 + random_int(0, 90);
$pid = (int) trim((string) shell_exec(
    'cd ' . escapeshellarg($root) . ' && PP_CONFIG=' . escapeshellarg($config) . ' '
    . escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("$work/server.log") . ' 2>&1 & echo $!'
));
usleep(700000);
register_shutdown_function(function () use ($pid, $work) {
    if ($pid) {
        @posix_kill($pid, 15);
    }
    array_map('unlink', glob("$work/*") ?: []);
    @rmdir($work);
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
function req(string $path, int $port): array
{
    $ch = curl_init("http://127.0.0.1:$port$path");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body];
}

/* --- Denied: internal trees, dotfiles, tools, configs ---------------------- */

foreach ([
    '/app/helpers.php', '/app/config.site.php', '/app/', '/app',
    '/data/prairiedispatch.sqlite', '/data/',
    '/tools/reset-password.php', '/tools/setup-admin.php', '/tools/make-agent.php',
    '/tests/run.php', '/tests/fixtures/transport-server.php',
    '/vendor/ezyang/htmlpurifier/library/HTMLPurifier.php', '/vendor/autoload.php',
    '/docs/HERMES-INGEST.md',
    '/.git/config', '/.git/HEAD', '/.gitignore', '/.htaccess',
    '/composer.json', '/composer.lock', '/router.php',
    '/DEPLOY.md', '/CLAUDE.md',
    '/uploads/shell.php', '/uploads/2026/08/x.phtml',
] as $path) {
    [$code, $body] = req($path, $port);
    ok($code === 404 || $code === 403, "denied: $path (got $code)");
    ok(!str_contains($body, '<?php') && !str_contains($body, 'PP_SCHEMA'), "no source leaked from $path");
}

/* --- Denied: encoded and traversal variants -------------------------------- */

foreach ([
    '/%61pp/helpers.php',           // %61 = 'a' → app/
    '/%2E%67it/config',             // %2E%67 → .git
    '/app%2fhelpers.php',           // encoded slash
    '/story/%2e%2e/config.php',     // traversal inside a pretty route
    '/..%2fconfig.php',
    '/assets/../config.php',
    '/tools%2Freset-password.php',
    '/%2e%2e%2f%2e%2e%2fetc%2fpasswd',
    '/story/..%5c..%5cconfig.php',  // backslash separators
] as $path) {
    [$code, $body] = req($path, $port);
    ok($code >= 400, "traversal refused: $path (got $code)");
    ok(!str_contains($body, '<?php') && !str_contains($body, 'db\' =>'), "nothing leaked from $path");
}

/* --- The cron scripts stay reachable but fail closed ------------------------ */

[$code, $body] = req('/cron/fetch-news.php', $port);
ok($code === 403 && str_contains($body, 'key'), 'web cron without the secret answers 403');

/* --- Legitimate routes and assets still answer ------------------------------ */

foreach ([
    ['/', 200], ['/assets/css/prairie.css', 200], ['/assets/css/admin.css', 200],
    ['/feed/', 200], ['/sitemap.xml', 200], ['/search?q=council', 200],
    ['/admin/login.php', 200], ['/corrections', 200], ['/about', 200],
] as [$path, $want]) {
    [$code] = req($path, $port);
    ok($code === $want, "legit route answers: $path (got $code, want $want)");
}
if ($storySlug !== '') {
    [$code, $body] = req('/story/' . $storySlug, $port);
    ok($code === 200 && str_contains($body, '<article') || str_contains($body, 'story'), 'a story page renders');
}
[$code] = req('/.well-known/acme-challenge/test-token', $port);
ok($code === 404, 'ACME path is routable (404 for a missing token, not a policy 403)');

exit($fails ? 1 : 0);
