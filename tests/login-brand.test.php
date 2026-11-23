<?php
/**
 * The newsroom sign-in wears the paper it is served on — over real HTTP on
 * a disposable database.
 *
 * Before this, every paper's /admin/login.php drew the CivisMedia admin
 * sheet around the network-default Prairie Dispatch logo, so Kermode's
 * staff signed in under another paper's name. Now:
 *
 *   1. a paper's sign-in is drawn inside its OWN masthead and footer: its
 *      title, its template stylesheet, its brand assets, its palette on
 *      the card — and not the admin sheet or the network logo;
 *   2. it is private: noindex whatever the paper's indexing switch says,
 *      and the paper's pasted analytics snippet — which its public pages
 *      DO carry — never runs beside the password field;
 *   3. a French paper's card is in French;
 *   4. the sign-in itself is unchanged: the CSRF field every suite scrapes,
 *      a wrong passphrase refused in the card, a right one signed in;
 *   5. the CivisMedia hub keeps its own control-room page.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('loginbrand');
pp_fixture_prepare($fx);
$work = $fx['work'];
$config = $fx['config'];

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

putenv('PP_CONFIG=' . $config);
define('PP_TEST_BOOT', 1);
require $root . '/app/bootstrap.php';
$pdo = db();
echo 'engine: ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . " ({$fx['id']})\n";

// Three real tenants of the tree, by slug, so their palette.json and
// chrome partials are the ones production serves.
$sites = [
    'kermode-chronicle' => ['Kermode Chronicle', 'kermode.test'],
    'bleuet-blanc'      => ['Le Bleuet Blanc', 'bleuet.test'],
    'civismedia'        => ['Civis Media', 'hub.test'],
];
foreach ($sites as $slug => [$title, $host]) {
    $id = (int) ($pdo->query('SELECT id FROM sites WHERE slug = ' . $pdo->quote($slug))->fetchColumn() ?: 0);
    if ($id === 0) {
        $pdo->prepare('INSERT INTO sites (name, slug, created_at) VALUES (?,?,?)')->execute([$title, $slug, now()]);
        $id = (int) pp_last_id('sites');
    }
    $pdo->prepare('DELETE FROM settings WHERE site_id = ? AND skey IN (?, ?)')->execute([$id, 'site_title', 'analytics_code']);
    $pdo->prepare('INSERT INTO settings (site_id, skey, svalue) VALUES (?,?,?)')->execute([$id, 'site_title', $title]);
    // A pasted analytics snippet the public pages carry; the sign-in must not.
    $pdo->prepare('INSERT INTO settings (site_id, skey, svalue) VALUES (?,?,?)')
        ->execute([$id, 'analytics_code', '<script src="https://analytics.example/tag.js" data-marker="pp-analytics-marker"></script>']);
    $pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')->execute([$host, $slug, now()]);
}
$email = 'brand-editor@test.local';
$pdo->prepare('INSERT INTO users (name, email, pass_hash, role, slug, created_at) VALUES (?,?,?,?,?,?)')
    ->execute(['Brand Editor', $email, password_hash('correct horse pp', PASSWORD_DEFAULT), 'editor', 'brand-editor', now()]);

$port = 9600 + random_int(0, 80);
$cmd = 'PP_CONFIG=' . escapeshellarg($config) . ' ' . escapeshellarg(PHP_BINARY)
     . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("$work/server.log") . ' 2>&1 & echo $!';
$serverPid = (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && ' . $cmd));
usleep(700000);
register_shutdown_function(function () use ($serverPid, $fx) {
    if ($serverPid) {
        @posix_kill($serverPid, 15);
    }
    pp_fixture_destroy($fx);
});

function web(string $host, string $method, string $path, array $fields = [], ?string $jar = null): array
{
    $ch = curl_init('http://127.0.0.1:' . $GLOBALS['port'] . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => ['Host: ' . $host],
        CURLOPT_FOLLOWLOCATION => false]);
    if ($jar !== null) {
        curl_setopt_array($ch, [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $loc = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return [$code, $body, $loc];
}

/* ---- 1 + 2. A paper's sign-in is the paper, and private ----------------------- */
[$code, $page] = web('kermode.test', 'GET', '/admin/login.php');
ok($code === 200, "1. Kermode's sign-in answers 200 (got $code)");
ok(str_contains($page, '<title>Newsroom sign-in — Kermode Chronicle</title>'), "1. the title names the paper");
ok(str_contains($page, 'href="/assets/css/chronicle.css"') && str_contains($page, 'class="t-chronicle"'),
   "1. the paper's own template stylesheet and body class are on the page");
ok(str_contains($page, 'class="kc-head"') && str_contains($page, '/assets/sites/kermode-chronicle/bear-crest.png'),
   "1. its own masthead is drawn, crest and all");
ok(str_contains($page, 'href="/assets/css/signin.css"') && str_contains($page, 'class="pp-signin"'),
   '1. the sign-in card and its stylesheet are on the page');
ok(preg_match('/--si-ink:#131E28;--si-signal:#8A5A17;--si-muted:#66727C/', $page) === 1,
   "1. the card carries Kermode's palette (ink, signal, muted from its palette.json)");
ok(!str_contains($page, '/assets/css/admin.css') && !str_contains($page, '/assets/img/logo-primary.svg'),
   '1. and neither the CivisMedia admin sheet nor the network-default logo');
ok(str_contains($page, '<meta name="robots" content="noindex, nofollow">'), '2. the sign-in is noindex');
ok(!str_contains($page, 'pp-analytics-marker'), "2. the paper's analytics snippet is NOT on the sign-in");
[, $front] = web('kermode.test', 'GET', '/');
ok(str_contains($front, 'pp-analytics-marker'), '2. (control: the same snippet IS on the public front page)');

/* ---- 3. A French paper signs in in French --------------------------------------- */
[$code, $fr] = web('bleuet.test', 'GET', '/admin/login.php');
ok($code === 200 && str_contains($fr, '<html lang="fr">') && str_contains($fr, 'Accès rédaction')
   && str_contains($fr, '>Courriel</label>') && str_contains($fr, '>Phrase de passe</label>')
   && str_contains($fr, '>Se connecter</button>') && str_contains($fr, 'class="t-bleuetblanc"'),
   "3. Le Bleuet Blanc's sign-in is in French, in its own chrome (got $code)");

/* ---- 4. Signing in is unchanged ---------------------------------------------------- */
$jar = $work . '/jar';
[, $page] = web('kermode.test', 'GET', '/admin/login.php', [], $jar);
ok(preg_match('/name="csrf" value="([0-9a-f]+)"/', $page, $m) === 1, '4. the CSRF field is where every suite looks for it');
$csrf = $m[1] ?? '';
[$code, $page] = web('kermode.test', 'POST', '/admin/login.php', ['csrf' => $csrf, 'email' => $email, 'password' => 'wrong passphrase'], $jar);
ok($code === 200 && str_contains($page, 'pp-signin__flash--error') && str_contains($page, 'match an account')
   && str_contains($page, 'class="kc-head"'), "4. a wrong passphrase is refused inside the paper's page (got $code)");
$jarFr = $work . '/jar-fr';
preg_match('/name="csrf" value="([0-9a-f]+)"/', web('bleuet.test', 'GET', '/admin/login.php', [], $jarFr)[1], $mf);
$fr = web('bleuet.test', 'POST', '/admin/login.php', ['csrf' => $mf[1] ?? '', 'email' => $email, 'password' => 'wrong passphrase'], $jarFr)[1];
ok(str_contains($fr, 'ne correspondent à aucun compte'), '4. and refused in French on the French paper');
[$code, , $loc] = web('kermode.test', 'POST', '/admin/login.php', ['csrf' => $csrf, 'email' => $email, 'password' => 'correct horse pp'], $jar);
ok($code === 302 && str_ends_with($loc, 'index.php'), "4. the right passphrase signs in (got $code → $loc)");

/* ---- 5. The hub keeps the control room's page --------------------------------------- */
[$code, $hub] = web('hub.test', 'GET', '/admin/login.php');
ok($code === 200 && str_contains($hub, 'href="/assets/css/admin.css"') && str_contains($hub, '/assets/sites/civismedia/logo-primary.svg')
   && !str_contains($hub, 'pp-signin'), "5. the hub's sign-in is still the CivisMedia control-room page (got $code)");

if ($fails === 0) {
    echo "login-brand: all checks pass\n";
    exit(0);
}
exit(1);
