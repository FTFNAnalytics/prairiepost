<?php
/**
 * Sprint 3 regression suite (Schema v28):
 * 1. Async article view-count beacon (/ad?v=<id>) keeps GET /story/<slug> read-only.
 * 2. PostgreSQL GIN tsvector full-text search (and SQLite fallback).
 * 3. Responsive WebP 480w/960w derivatives on upload + srcset/sizes in story_photo().
 * 4. Parallel curl_multi domain probing in app/watch.php.
 * 5. Resumable newsletter_deliveries outbox processed in bounded batches without duplicates.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__ . '/lib/fixture.php';
$fx = pp_fixture_create('sprint3');
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
require $root . '/app/views/ui.php';
require $root . '/app/newsletter.php';
require $root . '/app/watch.php';
$pdo = db();

$paperAId = (int) $pdo->query("SELECT id FROM sites WHERE slug = 'prairiedispatch'")->fetchColumn();
$pdo->prepare("INSERT INTO sites (name, slug, domain, created_at) VALUES ('Other Paper', 'other-paper', 'other.test', ?)")->execute([now()]);
$paperBId = pp_last_id('sites');
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?, ?, ?)')->execute(['paper-a.test', 'prairiedispatch', now()]);
$pdo->prepare('INSERT INTO domains (hostname, site_slug, created_at) VALUES (?, ?, ?)')->execute(['other.test', 'other-paper', now()]);

$mkPost = function (string $title, string $slug, string $status, array $sites, string $body = '<p>Story body.</p>') use ($pdo): int {
    $pub = $status === 'published' ? now() : null;
    $pdo->prepare('INSERT INTO posts (title, slug, byline, body, lede, status, views, published_at, created_at, updated_at)
                   VALUES (?,?,?,?,?,?,0,?,?,?)')
        ->execute([$title, $slug, 'Newsroom Staff', $body, 'Lede text about municipal transit.', $status, $pub, now(), now()]);
    $id = pp_last_id('posts');
    set_post_sites($id, $sites);
    return $id;
};

$pLiveA  = $mkPost('Beacon Counted Story', 'beacon-counted-story', 'published', [$paperAId], '<p>Prairie grain elevator modernization report.</p>');
$pDraftA = $mkPost('Unpublished Draft Story', 'unpublished-draft-story', 'draft', [$paperAId]);
$pLiveB  = $mkPost('Other Paper Story', 'other-paper-story', 'published', [$paperBId]);

$cleanupFiles = [];
$port = 8800 + random_int(0, 60);
$cmd = 'PP_CONFIG=' . escapeshellarg($config) . ' ' . escapeshellarg(PHP_BINARY)
     . ' -S 127.0.0.1:' . $port . ' router.php >' . escapeshellarg("$work/server.log") . ' 2>&1 & echo $!';
$serverPid = (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && ' . $cmd));
usleep(700000);
register_shutdown_function(function () use ($serverPid, $fx, &$cleanupFiles) {
    if ($serverPid) {
        @posix_kill($serverPid, 15);
    }
    foreach ($cleanupFiles as $f) {
        @unlink($f);
    }
    pp_fixture_destroy($fx);
});

$base = 'http://127.0.0.1:' . $port;
function s3_get(string $url, string $host = 'paper-a.test', string $ua = 'Mozilla/5.0'): array
{
    $ch = curl_init($url);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => $ua,
        CURLOPT_HTTPHEADER => ['Host: ' . $host],
        CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$headers): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($line);
        },
    ]);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body, $headers];
}

$viewsOf = fn (int $id): int => (int) $pdo->query("SELECT views FROM posts WHERE id = $id")->fetchColumn();

/* =========================================================================
 * 1. Async Article View-Count Beacon (/ad?v=<id>)
 * ========================================================================= */

[$code, $html] = s3_get("$base/story/beacon-counted-story", 'paper-a.test');
ok($code === 200, "article GET returns 200 (got $code)");
ok(str_contains($html, '/ad?v=' . $pLiveA), 'article footer emits /ad?v=<id> beacon');
ok($viewsOf($pLiveA) === 0, 'article GET performed zero synchronous view writes');

[$cB, , $hB] = s3_get("$base/ad?v=$pLiveA", 'paper-a.test', 'Mozilla/5.0');
ok($cB === 204 && str_contains($hB['cache-control'] ?? '', 'no-store'), "beacon returns 204 no-store (got $cB)");
ok($viewsOf($pLiveA) === 1, 'browser beacon incremented views to 1');

[$cBot] = s3_get("$base/ad?v=$pLiveA", 'paper-a.test', 'Googlebot/2.1 (+http://www.google.com/bot.html)');
ok($cBot === 204 && $viewsOf($pLiveA) === 1, 'bot beacon did not increment views');

[$cDraft] = s3_get("$base/ad?v=$pDraftA", 'paper-a.test', 'Mozilla/5.0');
ok($cDraft === 204 && $viewsOf($pDraftA) === 0, 'beacon ignores unpublished drafts');

[$cOther] = s3_get("$base/ad?v=$pLiveB", 'paper-a.test', 'Mozilla/5.0');
ok($cOther === 204 && $viewsOf($pLiveB) === 0, 'beacon ignores stories belonging to another site');

/* =========================================================================
 * 2. Full-Text Search (PostgreSQL GIN tsvector + SQLite fallback)
 * ========================================================================= */

$found = search_posts('elevator modernization', 10);
$foundCount = count_search_posts('elevator modernization');
ok($foundCount >= 1 && in_array($pLiveA, array_map('intval', array_column($found, 'id')), true),
    "search_posts finds matching story (count=$foundCount)");
ok(count_search_posts('nonexistenttermxyz987') === 0, 'search returns 0 for unmatched query');

if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
    $hasGin = (bool) $pdo->query("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'idx_posts_search_fts'")->fetchColumn();
    ok($hasGin, 'PostgreSQL schema has GIN full-text search index idx_posts_search_fts');
}

/* =========================================================================
 * 3. Responsive WebP srcset Derivatives at Upload Time
 * ========================================================================= */

if (function_exists('imagecreatetruecolor') && function_exists('imagewebp')) {
    $img = imagecreatetruecolor(1200, 800);
    $bg = imagecolorallocate($img, 23, 48, 28);
    imagefilledrectangle($img, 0, 0, 1200, 800, $bg);
    ob_start();
    imagepng($img);
    $pngBytes = (string) ob_get_clean();
    imagedestroy($img);

    [$pubUrl, $err] = pp_store_image_bytes($pngBytes, 'sprint3-responsive-test');
    ok($err === null && is_string($pubUrl) && str_starts_with($pubUrl, '/uploads/'), "stored 1200x800 PNG upload ($err)");
    if (is_string($pubUrl)) {
        $stem = pathinfo($pubUrl, PATHINFO_FILENAME);
        $dirUrl = dirname($pubUrl);
        $w480 = $dirUrl . '/' . $stem . '-480w.webp';
        $w960 = $dirUrl . '/' . $stem . '-960w.webp';
        $cleanupFiles[] = PP_ROOT . $pubUrl;
        $cleanupFiles[] = PP_ROOT . $w480;
        $cleanupFiles[] = PP_ROOT . $w960;

        ok(is_file(PP_ROOT . $w480), 'generated -480w.webp derivative');
        ok(is_file(PP_ROOT . $w960), 'generated -960w.webp derivative');
        $info480 = @getimagesize(PP_ROOT . $w480);
        $info960 = @getimagesize(PP_ROOT . $w960);
        ok(is_array($info480) && $info480[0] === 480 && $info480[1] === 320, '480w derivative has exact 480x320 dimensions');
        ok(is_array($info960) && $info960[0] === 960 && $info960[1] === 640, '960w derivative has exact 960x640 dimensions');

        $srcset = pp_image_srcset($pubUrl);
        ok(str_contains($srcset, "$w480 480w") && str_contains($srcset, "$w960 960w") && str_contains($srcset, "$pubUrl 1200w"),
            "pp_image_srcset returns 480w, 960w, and original 1200w ($srcset)");

        $photoHtml = story_photo(['image' => $pubUrl, 'title' => 'Test', 'slug' => 'test', 'image_caption' => '', 'image_credit' => '']);
        ok(str_contains($photoHtml, 'srcset="') && str_contains($photoHtml, 'sizes="(max-width: 640px) 480px, 960px"'),
            'story_photo emits srcset and sizes attributes for responsive images');
    }
}

/* =========================================================================
 * 4. Parallel curl_multi Domain Probing (app/watch.php)
 * ========================================================================= */

$probeRes = pp_watch_probe_domains([
    'a' => "127.0.0.1:$port",
    'dead' => '127.0.0.1:1',
], 4, 3, 'http');
ok(($probeRes["127.0.0.1:$port"] ?? 0) === 200, "parallel prober got HTTP 200 for live server (got " . ($probeRes["127.0.0.1:$port"] ?? -1) . ')');
ok(($probeRes['127.0.0.1:1'] ?? -1) === 0, 'parallel prober got 0 for unreachable port');

/* =========================================================================
 * 5. Resumable Newsletter Delivery Outbox (newsletter_deliveries)
 * ========================================================================= */

set_setting('newsletter_enabled', '1', $paperAId);
set_setting('newsletter_send_hour', '6', $paperAId);
$sendNow = strtotime(pp_paper_date('Y-m-d') . ' 12:00:00');
for ($i = 1; $i <= 5; $i++) {
    $pdo->prepare("INSERT INTO subscribers (site_id, email, status, token, created_at) VALUES (?, ?, 'active', ?, ?)")
        ->execute([$paperAId, "reader{$i}@example.test", "tok{$i}", now()]);
}

$delivered = [];
$GLOBALS['pp_newsletter_sender'] = static function (string $to, string $subject, string $html, string $unsub) use (&$delivered): ?string {
    $delivered[] = $to;
    return $to === 'reader3@example.test' ? 'simulated bounce' : null;
};

// Batch 1 (2 of 5): queues all 5, delivers first 2, leaves edition in 'sending'.
$msg1 = pp_maybe_send_daily($sendNow, 2, 0);
ok(str_contains($msg1, '3 remaining'), "batch 1 leaves 3 remaining (got: $msg1)");
ok(count($delivered) === 2, 'batch 1 sent exactly 2 emails');

// Batch 2 (next 2 of 5, including reader3 simulated failure): 1 remaining.
$msg2 = pp_maybe_send_daily($sendNow, 2, 0);
ok(str_contains($msg2, '1 remaining'), "batch 2 leaves 1 remaining (got: $msg2)");
ok(count($delivered) === 4, 'batch 2 processed 2 more (4 total)');

// Batch 3 (final 1 of 5): finishes edition cleanly.
$msg3 = pp_maybe_send_daily($sendNow, 2, 0);
ok(str_contains($msg3, 'finished edition') && str_contains($msg3, '4 sent, 1 failed'), "batch 3 finished edition (got: $msg3)");
ok(count($delivered) === 5 && count(array_unique($delivered)) === 5, 'all 5 subscribers attempted exactly once with zero duplicates');

// Batch 4: idempotent — already sent today.
$msg4 = pp_maybe_send_daily($sendNow, 2, 0);
ok($msg4 === 'newsletter: already sent today' && count($delivered) === 5, "batch 4 is a clean no-op (got: $msg4)");

$edRow = $pdo->query("SELECT status, recipients FROM newsletters WHERE site_id = $paperAId ORDER BY id DESC LIMIT 1")->fetch();
ok(($edRow['status'] ?? '') === 'sent' && (int) ($edRow['recipients'] ?? 0) === 4, 'newsletters row finalized as sent with 4 recipients');

unset($GLOBALS['pp_newsletter_sender']);

exit($fails ? 1 : 0);
