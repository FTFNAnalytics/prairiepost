<?php
/**
 * The ingest lanes' hourly limits per token (app/ingest.php,
 * pp_ingest_hourly_limit()).
 *
 * Raised on 26 Nov 2026 so one publishing key can run a full news round
 * across the network in an hour: 200 filings (POST /api/ingest), 200
 * uploads (POST /api/ingest-media), and 120 edits on each edit lane
 * (POST /api/ingest/update, PATCH /api/ingest/drafts/{ref}). Each comes, in
 * order, from its PP_INGEST_*_LIMIT_PER_HOUR environment variable, the same
 * name in the config, or that default. A value that isn't a whole number
 * from 1 to 100000 is skipped. Filings take a paper's own
 * ingest_hourly_limit setting before any of them.
 *
 * - The resolver: the defaults, the environment, the config, their order,
 *   and the values it skips.
 * - Filings at the default, over HTTP: the 200th of the hour is filed and
 *   the 201st is a 429 naming the limit. (The other lanes' defaults are
 *   held by ingest-auth, ingest-edit and ingest-update.)
 * - A server started with the three variables set: filings, uploads and
 *   draft edits each stop at the number the environment names, with the
 *   same 429 answer; a paper's own setting outranks the environment, and a
 *   setting of 0 is skipped rather than shutting the lane.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('ingestlimits');
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
require $root . '/app/ingest.php';
require $root . '/app/ingest-edit.php';
$pdo = db();
echo 'engine: ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . " ({$fx['id']})\n";

// None of the three may leak in from the shell that runs the suite.
$clean = 'env -u PP_INGEST_STORY_LIMIT_PER_HOUR -u PP_INGEST_MEDIA_LIMIT_PER_HOUR -u PP_INGEST_EDIT_LIMIT_PER_HOUR';

/* ---- 1. The resolver --------------------------------------------------------- */

/** The three limits as a fresh process resolves them, under $env and $cfg. */
$limits = function (string $env = '', ?string $cfg = null) use ($root, $config, $clean): array {
    $code = 'define("PP_TEST_BOOT", 1); require ' . var_export("$root/app/bootstrap.php", true) . '; require '
          . var_export("$root/app/ingest.php", true) . '; echo json_encode(["story" => pp_ingest_hourly_limit("story"),'
          . ' "media" => pp_ingest_hourly_limit("media"), "edit" => pp_ingest_hourly_limit("edit")]);';
    $out = (string) shell_exec('cd ' . escapeshellarg($root) . " && $clean PP_CONFIG=" . escapeshellarg($cfg ?? $config) . ' ' . $env . ' '
        . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1');
    return json_decode($out, true) ?: ['raw' => $out];
};
$base = (string) file_get_contents($config);
$withConfig = "$work/config-limits.php";
file_put_contents($withConfig, str_replace("'debug' => false]", "'debug' => false, 'ingest_media_limit_per_hour' => 250,"
    . " 'ingest_edit_limit_per_hour' => '75', 'ingest_story_limit_per_hour' => 'two hundred']", $base));
ok(str_contains((string) file_get_contents($withConfig), 'ingest_media_limit_per_hour'), 'the config variant was written');

$got = $limits();
ok($got === ['story' => 200, 'media' => 200, 'edit' => 120], 'with nothing set: 200 filings, 200 uploads, 120 edits (got ' . json_encode($got) . ')');

$got = $limits('PP_INGEST_STORY_LIMIT_PER_HOUR=500 PP_INGEST_MEDIA_LIMIT_PER_HOUR=300 PP_INGEST_EDIT_LIMIT_PER_HOUR=45');
ok($got === ['story' => 500, 'media' => 300, 'edit' => 45], 'each environment variable sets its own lane (got ' . json_encode($got) . ')');

$got = $limits('', $withConfig);
ok($got === ['story' => 200, 'media' => 250, 'edit' => 75],
   'the config sets a lane as a number or as a string, and a config value that is not a number is skipped (got ' . json_encode($got) . ')');

$got = $limits('PP_INGEST_MEDIA_LIMIT_PER_HOUR=310', $withConfig);
ok($got === ['story' => 200, 'media' => 310, 'edit' => 75], 'the environment outranks the config (got ' . json_encode($got) . ')');

foreach (['0', '-5', 'abc', '2OO', '1e3', '12.5', '100001', '007', ''] as $bad) {
    $got = $limits('PP_INGEST_MEDIA_LIMIT_PER_HOUR=' . escapeshellarg($bad));
    ok(($got['media'] ?? null) === 200, "an environment value of '$bad' is skipped: the default stands (got " . json_encode($got) . ')');
}
$got = $limits('PP_INGEST_MEDIA_LIMIT_PER_HOUR=0', $withConfig);
ok(($got['media'] ?? null) === 250, 'a skipped environment value falls through to the config, not to the default (got ' . json_encode($got) . ')');
foreach (['1' => 1, '100000' => 100000, ' 150 ' => 150] as $edge => $want) {
    $got = $limits('PP_INGEST_EDIT_LIMIT_PER_HOUR=' . escapeshellarg((string) $edge));
    ok(($got['edit'] ?? null) === $want, "an environment value of '$edge' is taken as $want (got " . json_encode($got) . ')');
}

/* ---- Fixtures for the HTTP sections ------------------------------------------- */

$pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute(['Limits Desk', 'limits-desk']);
$deskId = (int) $pdo->query("SELECT id FROM categories WHERE slug = 'limits-desk'")->fetchColumn();
$siteId = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'prairiedispatch'")->fetchColumn();
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?,?,?)')->execute(['limits.test', 'prairiedispatch', now()]);

$mint = function (string $name, int $editDrafts = 0) use ($pdo): string {
    $t = 'hermes_' . bin2hex(random_bytes(28));
    $pdo->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, draft_only, lookup_scope, update_published, read_scope, edit_drafts, created_at)
        VALUES (?,?,?,?,1,1,?,0,?,?,?)')
        ->execute([$name, hash('sha256', $t), 'prairiedispatch', '', '', 'all', $editDrafts, now()]);
    return $t;
};

/** Start a php -S on the fixture with $env, and stop it when the suite ends. */
$servers = [];
$storedFiles = [];
$serve = function (string $env) use ($root, $config, $work, $clean, &$servers): int {
    $port = 9700 + random_int(0, 250);
    $cmd = "$clean PP_CONFIG=" . escapeshellarg($config) . ' ' . $env . ' ' . escapeshellarg(PHP_BINARY)
         . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("$work/server-$port.log") . ' 2>&1 & echo $!';
    $servers[] = (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && ' . $cmd));
    usleep(700000);
    return $port;
};
register_shutdown_function(function () use (&$servers, &$storedFiles, $root, $fx) {
    foreach ($servers as $pid) {
        if ($pid) {
            @posix_kill($pid, 15);
        }
    }
    foreach ($storedFiles as $f) {
        @unlink($root . $f);
    }
    pp_fixture_destroy($fx);
});

/** One request to $port as limits.test: [status, decoded body]. */
function call(int $port, string $method, string $path, string $token, $body, string $ctype = 'application/json'): array
{
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Host: limits.test', 'Authorization: Bearer ' . $token, 'Content-Type: ' . $ctype],
        CURLOPT_POSTFIELDS => is_string($body) ? $body : json_encode($body)]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, json_decode($raw, true)];
}

$n = 0;
$file = function (int $port, string $token) use (&$n): array {
    $n++;
    return call($port, 'POST', '/api/ingest', $token, ['site' => 'prairiedispatch', 'desk' => 'limits-desk',
        'title' => "Limits filing number $n", 'lede' => 'A lede long enough to pass the gate.',
        'body' => '<p>Body copy for the limits suite.</p>']);
};
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$upload = function (int $port, string $token) use ($png, &$storedFiles): array {
    $r = call($port, 'POST', '/api/ingest-media', $token, $png, 'image/png');
    if ($r[0] === 201 && is_array($r[1]) && !empty($r[1]['path'])) {
        $storedFiles[] = (string) $r[1]['path'];
    }
    return $r;
};
/** Rows a token filed earlier in this hour, as the filing count reads them. */
$filedEarlier = function (string $agentName, int $count) use ($pdo): void {
    $ins = $pdo->prepare('INSERT INTO posts (title, slug, status, filed_by, created_at, updated_at) VALUES (?,?,?,?,?,?)');
    for ($i = 0; $i < $count; $i++) {
        $ins->execute(["Earlier $agentName $i", "limits-earlier-$agentName-$i", 'draft', $agentName, now(), now()]);
    }
};
$refusal = fn (int $limit, string $what): array => ['ok' => false, 'error' => "rate limit: at most $limit $what per hour for this token"];

/* ---- 2. Filings at the default --------------------------------------------- */

$plain = $serve('');
$atDefault = $mint('limits-default');
$filedEarlier('limits-default', 199);
[$code, $j] = $file($plain, $atDefault);
ok($code === 201, "the 200th filing of the hour is filed (got $code " . json_encode($j) . ')');
[$code, $j] = $file($plain, $atDefault);
ok($code === 429 && $j === $refusal(200, 'filings'), "the 201st is refused: 429, naming the limit of 200 (got $code " . json_encode($j) . ')');

/* ---- 3. A server whose environment sets all three ------------------------------ */

$tuned = $serve('PP_INGEST_STORY_LIMIT_PER_HOUR=3 PP_INGEST_MEDIA_LIMIT_PER_HOUR=2 PP_INGEST_EDIT_LIMIT_PER_HOUR=2');

$filer = $mint('limits-env-filer');
$codes = [];
for ($i = 0; $i < 3; $i++) {
    $codes[] = $file($tuned, $filer)[0];
}
[$code, $j] = $file($tuned, $filer);
ok($codes === [201, 201, 201] && $code === 429 && $j === $refusal(3, 'filings'),
   'filings stop at the 3 the environment names (got ' . json_encode($codes) . " then $code " . json_encode($j) . ')');

$uploader = $mint('limits-env-uploader');
$codes = [$upload($tuned, $uploader)[0], $upload($tuned, $uploader)[0]];
[$code, $j] = $upload($tuned, $uploader);
ok($codes === [201, 201] && $code === 429 && $j === $refusal(2, 'uploads'),
   'uploads stop at the 2 the environment names (got ' . json_encode($codes) . " then $code " . json_encode($j) . ')');

$editor = $mint('limits-env-editor', 1);
$pdo->prepare('INSERT INTO posts (title, slug, category_id, lede, body, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)')
    ->execute(['A draft to edit', 'limits-draft-to-edit', $deskId, 'The lede.', '<p>The body.</p>', 'draft', now(), now()]);
$draftId = (int) pp_last_id('posts');
$pdo->prepare('INSERT INTO post_sites (post_id, site_id) VALUES (?,?)')->execute([$draftId, $siteId]);
$ref = pp_ingest_article_ref($siteId, $draftId, pp_ingest_ref_key());
$edit = function (string $title) use ($tuned, $editor, $ref, $draftId, $config): array {
    $fresh = pp_db_connect();
    $version = (string) (pp_ingest_edit_load($fresh, $draftId)['version'] ?? '');
    $fresh = null;
    return call($tuned, 'PATCH', '/api/ingest/drafts/' . $ref, $editor,
        ['site' => 'prairiedispatch', 'desk' => 'limits-desk', 'title' => $title, 'if_version' => $version]);
};
$codes = [$edit('First edit of the hour')[0], $edit('Second edit of the hour')[0]];
[$code, $j] = $edit('Third edit of the hour');
$title = (string) $pdo->query("SELECT title FROM posts WHERE id = $draftId")->fetchColumn();
ok($codes === [200, 200] && $code === 429 && $j === $refusal(2, 'draft edits') && $title === 'Second edit of the hour',
   'draft edits stop at the 2 the environment names, and the refused one writes nothing (got ' . json_encode($codes) . " then $code " . json_encode($j) . ", title '$title')");

// A paper's own setting outranks the environment for filings.
$pdo->prepare('INSERT INTO settings (site_id, skey, svalue) VALUES (?, ?, ?)')->execute([$siteId, 'ingest_hourly_limit', '5']);
$ownPaper = $mint('limits-own-setting');
$codes = [];
for ($i = 0; $i < 5; $i++) {
    $codes[] = $file($tuned, $ownPaper)[0];
}
[$code, $j] = $file($tuned, $ownPaper);
ok($codes === [201, 201, 201, 201, 201] && $code === 429 && $j === $refusal(5, 'filings'),
   "the paper's own ingest_hourly_limit of 5 outranks the environment's 3 (got " . json_encode($codes) . " then $code " . json_encode($j) . ')');

// Before 26 Nov a setting of 0 meant one filing an hour. Now it is skipped.
$pdo->prepare('UPDATE settings SET svalue = ? WHERE site_id = ? AND skey = ?')->execute(['0', $siteId, 'ingest_hourly_limit']);
$zeroPaper = $mint('limits-zero-setting');
$codes = [];
for ($i = 0; $i < 3; $i++) {
    $codes[] = $file($tuned, $zeroPaper)[0];
}
[$code, $j] = $file($tuned, $zeroPaper);
ok($codes === [201, 201, 201] && $code === 429 && $j === $refusal(3, 'filings'),
   'a setting of 0 is skipped: the environment\'s 3 applies, not 1 (got ' . json_encode($codes) . " then $code " . json_encode($j) . ')');

exit($fails ? 1 : 0);
