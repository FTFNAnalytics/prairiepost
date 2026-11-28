<?php
/**
 * Sprint 2 regression suite:
 * 1. Per-site dynamic /ads.txt (404 when empty, text/plain when configured, isolated per paper).
 * 2. Bilingual per-site /privacy (PIPEDA / Quebec Law 25) and /standards pages.
 * 3. NewsArticle JSON-LD: @type Organization vs Person for author, NewsMediaOrganization policy links.
 * 4. Shared AI & Hermes disclosure partial across default AND bespoke article-*.php templates.
 * 5. Fail-closed IndexNow key verification (/<key>.txt) and publish notifications.
 * 6. AI default model (claude-sonnet-4-6) and daily call budget guardrail.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('sprint2');
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

// Local stub endpoint for IndexNow + Anthropic API testing (no external network calls).
$stubPort = 8600 + random_int(0, 70);
$stubLog = $work . '/stub-requests.jsonl';
$stubScript = $work . '/stub.php';
$varLog = var_export($stubLog, true);
file_put_contents($stubScript, <<<PHP
<?php
\$path = parse_url(\$_SERVER['REQUEST_URI'], PHP_URL_PATH);
\$raw = file_get_contents('php://input');
file_put_contents($varLog, json_encode(['path' => \$path, 'body' => \$raw]) . "\n", FILE_APPEND);
header('Content-Type: application/json');
if (\$path === '/v1/messages') {
    echo json_encode([
        'type' => 'message',
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 120, 'output_tokens' => 45],
        'content' => [['type' => 'text', 'text' => 'Stubbed response']],
    ]);
    exit;
}
http_response_code(200);
echo json_encode(['ok' => true]);
PHP);

// Inject stub URLs + dummy Anthropic key into fixture config.
$cfgData = require $config;
$cfgData['anthropic_api_key'] = 'test-ant-key-local';
$cfgData['anthropic_base_url'] = "http://127.0.0.1:$stubPort";
$cfgData['indexnow_endpoint'] = "http://127.0.0.1:$stubPort/indexnow";
file_put_contents($config, "<?php\nreturn " . var_export($cfgData, true) . ";\n");

putenv('PP_CONFIG=' . $config);
define('PP_TEST_BOOT', 1);
require $root . '/app/bootstrap.php';
$pdo = db();

// Paper A (default prairiedispatch, en), Paper B (bleuet-blanc, fr), Paper C (kitchener-chronicle, bespoke article template).
$paperAId = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'prairiedispatch'")->fetchColumn();
$pdo->prepare("UPDATE sites SET domain = 'paper-a.test' WHERE id = ?")->execute([$paperAId]);
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?, ?, ?)')->execute(['paper-a.test', 'prairiedispatch', now()]);

$pdo->prepare("INSERT INTO sites (name, slug, domain, created_at) VALUES ('Le Bleuet Blanc', 'bleuet-blanc', 'bleuet.test', ?)")->execute([now()]);
$paperBId = pp_last_id('sites');
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?, ?, ?)')->execute(['bleuet.test', 'bleuet-blanc', now()]);
$pdo->prepare("INSERT INTO settings (site_id, skey, svalue) VALUES (?, 'site_title', 'Le Bleuet Blanc')")->execute([$paperBId]);

$pdo->prepare("INSERT INTO sites (name, slug, domain, created_at) VALUES ('The Kitchener Chronicle', 'kitchener-chronicle', 'kitchener.test', ?)")->execute([now()]);
$paperCId = pp_last_id('sites');
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?, ?, ?)')->execute(['kitchener.test', 'kitchener-chronicle', now()]);
$pdo->prepare("INSERT INTO settings (site_id, skey, svalue) VALUES (?, 'site_title', 'The Kitchener Chronicle')")->execute([$paperCId]);

// Create network admin.
$pdo->prepare('INSERT INTO users (name, email, pass_hash, role, is_network_admin, slug, created_at) VALUES (?,?,?,?,?,?,?)')
    ->execute(['Net Admin', 'admin@test.local', password_hash('correct horse pp', PASSWORD_DEFAULT), 'admin', 1, 'net-admin', now()]);
$adminId = pp_last_id('users');

// Create test stories: named author, automated report, AI-origin, Hermes-origin (including on Kitchener bespoke template).
$mkPost = function (string $title, string $slug, string $byline, string $origin, ?int $authorId, array $sites) use ($pdo): int {
    $pdo->prepare('INSERT INTO posts (title, slug, byline, origin, author_id, body, lede, status, published_at, created_at, updated_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$title, $slug, $byline, $origin, $authorId, '<p>Story body.</p>', 'Story lede.', 'published', now(), now(), now()]);
    $id = pp_last_id('posts');
    set_post_sites($id, $sites);
    return $id;
};

$pHuman  = $mkPost('Named Reporter Piece', 'named-reporter-piece', 'Dana Ruthven', '', $adminId, [$paperAId]);
$pAuto   = $mkPost('Automated Council Notice', 'automated-council-notice', 'Automated report', 'hermes', null, [$paperAId, $paperCId]);
$pAi     = $mkPost('AI Assisted Explainer', 'ai-assisted-explainer', 'Newsroom Staff', 'ai', null, [$paperAId, $paperCId]);

// Start stub server + app dev server.
$port = 8700 + random_int(0, 70);
$stubPid = (int) trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $stubPort . ' ' . escapeshellarg($stubScript) . ' >/dev/null 2>&1 & echo $!'));
$cmd = 'PP_CONFIG=' . escapeshellarg($config) . ' ' . escapeshellarg(PHP_BINARY)
     . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("$work/server.log") . ' 2>&1 & echo $!';
$serverPid = (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && ' . $cmd));
usleep(700000);
register_shutdown_function(function () use ($serverPid, $stubPid, $fx) {
    if ($serverPid) { @posix_kill($serverPid, 15); }
    if ($stubPid) { @posix_kill($stubPid, 15); }
    pp_fixture_destroy($fx);
});

$base = 'http://127.0.0.1:' . $port;

function s2_http(string $method, string $url, array $fields, string $jar, string $base, string $host = 'paper-a.test'): array
{
    $ch = curl_init($base . $url);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Host: ' . $host],
        CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$headers): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($line);
        },
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body, $headers];
}

// Sign in as admin on Paper A.
$jar = $work . '/jar-admin';
[, $loginPage] = s2_http('GET', '/admin/login.php', [], $jar, $base, 'paper-a.test');
preg_match('/name="csrf" value="([0-9a-f]+)"/', $loginPage, $m);
$csrf = $m[1] ?? '';
[$cLogin] = s2_http('POST', '/admin/login.php', ['csrf' => $csrf, 'email' => 'admin@test.local', 'password' => 'correct horse pp'], $jar, $base, 'paper-a.test');
ok($cLogin === 302, "admin signs in (got $cLogin)");

/* =========================================================================
 * 1. Dynamic /ads.txt
 * ========================================================================= */

[$c, $b] = s2_http('GET', '/ads.txt', [], $work . '/anon', $base, 'paper-a.test');
ok($c === 404, "empty ads.txt returns 404 (got $c)");

[$c] = s2_http('POST', '/admin/settings.php', [
    'csrf' => $csrf,
    'ads_txt' => "google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0\x00\n# comment line",
    'funding_note' => 'Independently published by Civis Media Inc.',
    'privacy_officer' => 'privacy@paper-a.test',
], $jar, $base, 'paper-a.test');
ok($c === 302, "settings saved with ads_txt and trust metadata (got $c)");

[$c, $b, $h] = s2_http('GET', '/ads.txt', [], $work . '/anon', $base, 'paper-a.test');
ok($c === 200 && str_contains($h['content-type'] ?? '', 'text/plain') && str_contains($b, 'pub-1234567890123456') && !str_contains($b, "\x00"),
    "configured /ads.txt serves 200 text/plain without NUL bytes (got $c)");

[$cB] = s2_http('GET', '/ads.txt', [], $work . '/anon', $base, 'bleuet.test');
ok($cB === 404, "Paper B /ads.txt remains 404 (per-site isolation)");

/* =========================================================================
 * 2. Trust & Policy Pages (/privacy, /standards) in EN & FR
 * ========================================================================= */

[$c, $b] = s2_http('GET', '/privacy', [], $work . '/anon', $base, 'paper-a.test');
ok($c === 200 && str_contains($b, 'Privacy policy') && str_contains($b, 'privacy@paper-a.test'),
    "English /privacy serves 200 with privacy officer (got $c)");

[$c, $b] = s2_http('GET', '/standards', [], $work . '/anon', $base, 'paper-a.test');
ok($c === 200 && str_contains($b, 'Editorial standards') && str_contains($b, 'Independently published by Civis Media Inc.') && str_contains($b, '/corrections') && stripos($b, 'automated') === false,
    "English /standards serves 200 with funding note, corrections link, and no automated-report wording (got $c)");

[$c, $b] = s2_http('GET', '/privacy', [], $work . '/anon', $base, 'bleuet.test');
ok($c === 200 && str_contains($b, 'Politique de confidentialité') && str_contains($b, 'Loi 25'),
    "French /privacy serves 200 with Quebec Law 25 copy (got $c)");

[$c, $b] = s2_http('GET', '/standards', [], $work . '/anon', $base, 'bleuet.test');
ok($c === 200 && str_contains($b, 'Normes éditoriales') && str_contains($b, 'Corrections') && stripos($b, 'automatis') === false,
    "French /standards serves 200 with French editorial standards copy and no automated wording (got $c)");

// Custom privacy_body sanitizes script tags.
set_setting('privacy_body', '<p>Custom policy.</p><script>alert(1)</script>', $paperAId);
[$c, $b] = s2_http('GET', '/privacy', [], $work . '/anon', $base, 'paper-a.test');
ok($c === 200 && str_contains($b, 'Custom policy.') && !str_contains($b, '<script>alert(1)'),
    'custom privacy_body renders sanitized HTML');

/* =========================================================================
 * 3. JSON-LD Author @type, Newsroom Staff normalization & NewsMediaOrganization
 * ========================================================================= */

$extractLd = function (string $html): array {
    if (preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m)) {
        return json_decode($m[1], true) ?: [];
    }
    return [];
};

[, $htmlHuman] = s2_http('GET', '/story/named-reporter-piece', [], $work . '/anon', $base, 'paper-a.test');
$ldHuman = $extractLd($htmlHuman);
ok(($ldHuman['author']['@type'] ?? '') === 'Person' && ($ldHuman['author']['name'] ?? '') === 'Dana Ruthven',
    'named journalist emits @type: Person in JSON-LD');
ok(($ldHuman['publisher']['@type'] ?? '') === 'NewsMediaOrganization'
    && str_ends_with((string) ($ldHuman['publisher']['publishingPrinciples'] ?? ''), '/standards')
    && str_ends_with((string) ($ldHuman['publisher']['correctionsPolicy'] ?? ''), '/corrections')
    && str_ends_with((string) ($ldHuman['publisher']['ownershipFundingInfo'] ?? ''), '/about'),
    'NewsMediaOrganization JSON-LD includes publishingPrinciples, correctionsPolicy, and ownershipFundingInfo');

[, $htmlAuto] = s2_http('GET', '/story/automated-council-notice', [], $work . '/anon', $base, 'paper-a.test');
$ldAuto = $extractLd($htmlAuto);
ok(($ldAuto['author']['@type'] ?? '') === 'Organization' && ($ldAuto['author']['name'] ?? '') === 'Newsroom Staff',
    'legacy Automated report normalizes to Newsroom Staff with @type: Organization');
ok(stripos($htmlAuto, 'Automated report') === false && str_contains($htmlAuto, 'Newsroom Staff'),
    'public article HTML never prints Automated report and shows Newsroom Staff');

pp_set_site_placement($pAuto, $paperAId, 'hero');
[, $htmlFrontA] = s2_http('GET', '/', [], $work . '/anon', $base, 'paper-a.test');
ok(stripos($htmlFrontA, 'Automated report') === false && str_contains($htmlFrontA, 'Newsroom Staff'),
    'public front page never prints Automated report and shows Newsroom Staff');

[, $htmlAi] = s2_http('GET', '/story/ai-assisted-explainer', [], $work . '/anon', $base, 'paper-a.test');
$ldAi = $extractLd($htmlAi);
ok(($ldAi['author']['@type'] ?? '') === 'Organization' && ($ldAi['author']['name'] ?? '') === 'Newsroom Staff',
    'Newsroom Staff byline emits @type: Organization');

/* =========================================================================
 * 4. Shared Optional AI Disclosure Partial (OFF by default, works across all templates when enabled)
 * ========================================================================= */

ok(!str_contains($htmlAi, 'pp-disclosure') && !str_contains($htmlAuto, 'pp-disclosure'),
    'AI disclosure stays strictly OFF when ai_disclosure setting is blank');

set_setting('ai_disclosure', '1', $paperAId);
set_setting('ai_disclosure', '1', $paperCId);

[, $htmlHumanOn] = s2_http('GET', '/story/named-reporter-piece', [], $work . '/anon', $base, 'paper-a.test');
[, $htmlAiOn]    = s2_http('GET', '/story/ai-assisted-explainer', [], $work . '/anon', $base, 'paper-a.test');
[, $htmlHermesOn] = s2_http('GET', '/story/automated-council-notice', [], $work . '/anon', $base, 'paper-a.test');
[, $htmlKcAi]    = s2_http('GET', '/story/ai-assisted-explainer', [], $work . '/anon', $base, 'kitchener.test');
[, $htmlKcHermes] = s2_http('GET', '/story/automated-council-notice', [], $work . '/anon', $base, 'kitchener.test');

ok(!str_contains($htmlHumanOn, 'pp-disclosure'), 'standard newsroom story never carries AI disclosure');
ok(str_contains($htmlAiOn, 'Prepared with AI assistance and reviewed by an editor.'), 'default template renders AI disclosure when explicitly enabled');
ok(str_contains($htmlHermesOn, 'Prepared with AI assistance and reviewed by an editor.') && stripos($htmlHermesOn, 'Automated report') === false,
    'default template renders clean disclosure without Automated report when explicitly enabled');
ok(str_contains($htmlKcAi, 'Prepared with AI assistance and reviewed by an editor.'), 'bespoke article-kitchener.php renders shared disclosure when explicitly enabled');
ok(str_contains($htmlKcHermes, 'Prepared with AI assistance and reviewed by an editor.') && stripos($htmlKcHermes, 'Automated report') === false,
    'bespoke article-kitchener.php renders Newsroom Staff and clean disclosure when explicitly enabled');

/* =========================================================================
 * 5. IndexNow Key Verification & Fail-Closed Publish Notifications
 * ========================================================================= */

[$c] = s2_http('POST', '/admin/settings.php', ['csrf' => $csrf, 'action' => 'new_indexnow_key'], $jar, $base, 'paper-a.test');
ok($c === 302, 'IndexNow key generated via admin/settings.php');
$inKey = (string) $pdo->query("SELECT svalue FROM settings WHERE site_id = $paperAId AND skey = 'indexnow_key'")->fetchColumn();
ok(strlen($inKey) === 32 && ctype_xdigit($inKey), "IndexNow key is 32 hex chars (got $inKey)");

[$cKey, $bKey] = s2_http('GET', "/$inKey.txt", [], $work . '/anon', $base, 'paper-a.test');
ok($cKey === 200 && trim($bKey) === $inKey, "GET /<key>.txt serves 200 with exact key (got $cKey)");

[$cBad] = s2_http('GET', '/' . str_repeat('a', 32) . '.txt', [], $work . '/anon', $base, 'paper-a.test');
ok($cBad === 404, "wrong 32-hex key returns 404 (got $cBad)");

[$cOther] = s2_http('GET', "/$inKey.txt", [], $work . '/anon', $base, 'bleuet.test');
ok($cOther === 404, "Paper A IndexNow key does not verify on Paper B (got $cOther)");

// Indexing is OFF -> IndexNow must fail closed and make zero outbound requests.
$resOff = pp_indexnow_notify_post($pHuman);
ok(($resOff[$paperAId]['sent'] ?? null) === false && ($resOff[$paperAId]['reason'] ?? '') === 'indexing_disabled',
    'IndexNow refuses to ping when indexing_enabled is off');

// Turn indexing ON for Paper A -> IndexNow pings our local stub endpoint.
set_setting('indexing_enabled', '1', $paperAId);
$resOn = pp_indexnow_notify_post($pHuman);
ok(($resOn[$paperAId]['sent'] ?? null) === true && ($resOn[$paperAId]['status'] ?? 0) === 200,
    'IndexNow pings stub endpoint when indexing_enabled=1 and key is configured');

$stubLines = is_file($stubLog) ? file($stubLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$lastStub = $stubLines ? json_decode(end($stubLines), true) : [];
$sentPayload = json_decode((string) ($lastStub['body'] ?? ''), true) ?: [];
ok(($sentPayload['host'] ?? '') === 'paper-a.test'
    && ($sentPayload['key'] ?? '') === $inKey
    && ($sentPayload['keyLocation'] ?? '') === "https://paper-a.test/$inKey.txt"
    && in_array('https://paper-a.test/story/named-reporter-piece', (array) ($sentPayload['urlList'] ?? []), true),
    'IndexNow payload includes exact host, key, keyLocation, and story URL');

/* =========================================================================
 * 6. AI Model Default & Daily Call Budget Guardrail
 * ========================================================================= */

ok(pp_ai_model() === 'claude-sonnet-4-6', 'default AI model is claude-sonnet-4-6 (got ' . pp_ai_model() . ')');

set_setting('ai_daily_limit', '2', $paperAId);
$r1 = pp_ai_message('sys', [['role' => 'user', 'content' => 'hi']]);
$r2 = pp_ai_message('sys', [['role' => 'user', 'content' => 'hi again']]);
$r3 = pp_ai_message('sys', [['role' => 'user', 'content' => 'over limit']]);

ok($r1['ok'] === true && $r2['ok'] === true, 'first two AI calls within daily limit succeed');
ok($r3['ok'] === false && str_contains((string) $r3['error'], 'Daily AI call limit reached (2/2)'),
    'third AI call refused cleanly by daily call budget guardrail');
ok(pp_ai_calls_today() === 2, 'audit_log recorded exactly 2 ai.call rows today');

exit($fails ? 1 : 0);
