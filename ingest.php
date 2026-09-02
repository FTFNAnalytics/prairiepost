<?php
/**
 * POST /api/ingest — the contract with the Hermes reporting agents.
 *
 * An agent files ONE story per call and never touches the database
 * directly. Its bearer token (issued by tools/make-agent.php, hashed at
 * rest, revocable) is scoped to specific papers and, optionally, desks —
 * a token for paper A cannot write to paper B.
 *
 * Everything an agent files lands as a DRAFT behind the newsroom's
 * existing publish gate. The single exception: a desk listed in the
 * site's `wire_desks` setting publishes immediately, because a live wire
 * that waits for a click is not live — and those stories carry the
 * automated-report treatment like every other agent filing.
 *
 * The server owns what agents must not: the slug (allocated here,
 * auto-suffixed on collision — the silent-skip failure mode does not
 * exist on this path), the byline (the site's `automated_byline`
 * setting; an agent cannot sign as a person), sanitization, and the
 * provenance record (which agent, which sources, retrieved when).
 *
 * Request:  Authorization: Bearer <token>
 *           JSON body:
 *           {site, desk, title, lede, body,
 *            dateline?, tags?, suggested_slug?, external_id?,
 *            sources?: [{url, title?, retrieved_at?}]}
 * Response: 201 {ok, id, slug, status}
 *           200 {ok, duplicate: true, id, slug} on an exact re-file
 *           4xx {ok: false, error} — the reason, never a coercion
 */
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

function pp_hermes_setting(int $siteId, string $key, string $default = ''): string
{
    static $cache = [];
    if (!isset($cache[$siteId])) {
        $stmt = db()->prepare('SELECT skey, svalue FROM settings WHERE site_id = ?');
        $stmt->execute([$siteId]);
        $cache[$siteId] = [];
        foreach ($stmt as $row) {
            $cache[$siteId][$row['skey']] = (string) $row['svalue'];
        }
    }
    $v = $cache[$siteId][$key] ?? '';
    return $v !== '' ? $v : $default;
}

function pp_hermes_out(int $code, array $body): never
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    pp_hermes_out(405, ['ok' => false, 'error' => 'POST one JSON story per call']);
}

/* --- Authentication: token -> agent row, fail closed ---------------------- */
$auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(\S{20,200})$/i', $auth, $m)) {
    pp_hermes_out(401, ['ok' => false, 'error' => 'missing bearer token']);
}
$stmt = db()->prepare('SELECT * FROM ingest_agents WHERE token_hash = ?');
$stmt->execute([hash('sha256', $m[1])]);
$agent = $stmt->fetch();
if (!$agent || (int) $agent['enabled'] !== 1) {
    // A revoked agent and an unknown token answer identically.
    pp_hermes_out(401, ['ok' => false, 'error' => 'unknown or revoked token']);
}
$agentSites = array_filter(array_map('trim', explode(',', (string) $agent['sites'])));
$agentDesks = array_filter(array_map('trim', explode(',', (string) $agent['desks'])));

/* --- Payload -------------------------------------------------------------- */
$raw = file_get_contents('php://input', false, null, 0, 512 * 1024);
$in = json_decode((string) $raw, true);
if (!is_array($in)) {
    pp_hermes_out(400, ['ok' => false, 'error' => 'body must be a JSON object']);
}

$siteSlug = slugify((string) ($in['site'] ?? ''));
if ($siteSlug === '' || !in_array($siteSlug, $agentSites, true)) {
    pp_hermes_out(403, ['ok' => false, 'error' => 'this token is not scoped to that site']);
}
$siteRow = db()->prepare('SELECT * FROM sites WHERE slug = ?');
$siteRow->execute([$siteSlug]);
$site = $siteRow->fetch();
if (!$site) {
    pp_hermes_out(403, ['ok' => false, 'error' => 'that site does not exist yet — it is created at launch, not by ingest']);
}
$siteId = (int) $site['id'];

$deskSlug = slugify((string) ($in['desk'] ?? ''));
if ($deskSlug === '' || ($agentDesks && !in_array($deskSlug, $agentDesks, true))) {
    pp_hermes_out(403, ['ok' => false, 'error' => 'this token is not scoped to that desk']);
}
$deskRow = db()->prepare('SELECT id, slug FROM categories WHERE slug = ?');
$deskRow->execute([$deskSlug]);
$desk = $deskRow->fetch();
if (!$desk) {
    pp_hermes_out(422, ['ok' => false, 'error' => "unknown desk '{$deskSlug}' — desks are created at launch, not by ingest"]);
}

$title = trim(strip_tags((string) ($in['title'] ?? '')));
$lede  = trim(strip_tags((string) ($in['lede'] ?? '')));
$body  = trim(sanitize_html((string) ($in['body'] ?? '')));
if ($title === '' || mb_strlen($title) > 255) {
    pp_hermes_out(422, ['ok' => false, 'error' => 'title is required, at most 255 characters']);
}
if ($lede === '' || mb_strlen($lede) > 1000) {
    pp_hermes_out(422, ['ok' => false, 'error' => 'lede is required, at most 1000 characters']);
}
if ($body === '' || strlen($body) > 200000) {
    pp_hermes_out(422, ['ok' => false, 'error' => 'body is required, at most 200 KB after sanitization']);
}
$dateline = trim(strip_tags((string) ($in['dateline'] ?? '')));
$tags = trim(strip_tags((string) ($in['tags'] ?? '')));
$externalId = trim((string) ($in['external_id'] ?? ''));
if (mb_strlen($externalId) > 120) {
    pp_hermes_out(422, ['ok' => false, 'error' => 'external_id is at most 120 characters']);
}

// Optional image: a URL the server fetches ITSELF, so it goes through the
// public-address guard first — the token holder writes the request but must
// not be able to point it inward. A fetch or format failure never sinks the
// filing; the story lands without a picture and the response says why.
$imageUrl = trim((string) ($in['image'] ?? ''));
$imageCaption = mb_substr(trim(strip_tags((string) ($in['image_caption'] ?? ''))), 0, 255);
$imageCredit = mb_substr(trim(strip_tags((string) ($in['image_credit'] ?? ''))), 0, 120);
if ($imageUrl !== '' && (strlen($imageUrl) > 600 || !preg_match('#^https?://#i', $imageUrl))) {
    pp_hermes_out(422, ['ok' => false, 'error' => 'image must be an http(s) URL, at most 600 characters']);
}

$sources = [];
foreach ((array) ($in['sources'] ?? []) as $i => $src) {
    if (!is_array($src)) {
        pp_hermes_out(422, ['ok' => false, 'error' => "sources[$i] must be an object"]);
    }
    $url = trim((string) ($src['url'] ?? ''));
    if (!preg_match('#^https?://#i', $url) || strlen($url) > 600) {
        pp_hermes_out(422, ['ok' => false, 'error' => "sources[$i].url must be http(s), at most 600 characters"]);
    }
    $sources[] = [
        'url' => $url,
        'title' => mb_substr(trim(strip_tags((string) ($src['title'] ?? ''))), 0, 255),
        'retrieved_at' => trim((string) ($src['retrieved_at'] ?? '')) ?: null,
    ];
    if (count($sources) > 20) {
        pp_hermes_out(422, ['ok' => false, 'error' => 'at most 20 sources per story']);
    }
}

/* --- Rate limit ----------------------------------------------------------- */
$limit = max(1, (int) pp_hermes_setting($siteId, 'ingest_hourly_limit', '60'));
$stmt = db()->prepare("SELECT COUNT(*) FROM posts WHERE filed_by = ? AND created_at > ?");
$stmt->execute([$agent['name'], date('Y-m-d H:i:s', time() - 3600)]);
if ((int) $stmt->fetchColumn() >= $limit) {
    pp_hermes_out(429, ['ok' => false, 'error' => "rate limit: at most {$limit} filings per hour for this token"]);
}

/* --- Dedup: an exact re-file is confirmed, never duplicated --------------- */
$hash = $externalId !== ''
    ? hash('sha256', $agent['name'] . '|' . $siteSlug . '|' . $externalId)
    : hash('sha256', $siteSlug . '|' . $title . '|' . $body);
$stmt = db()->prepare('SELECT p.id, p.slug FROM posts p
    JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = ?
    WHERE p.content_hash = ? LIMIT 1');
$stmt->execute([$siteId, $hash]);
if ($dup = $stmt->fetch()) {
    pp_hermes_out(200, ['ok' => true, 'duplicate' => true, 'id' => (int) $dup['id'], 'slug' => $dup['slug']]);
}

/* --- Slug: allocated here, auto-suffixed, never silently skipped ---------- */
// slugify('') returns its own 'story' fallback, so only consult the
// suggestion when the agent actually sent one.
$suggested = trim((string) ($in['suggested_slug'] ?? ''));
$base = mb_substr($suggested !== '' ? slugify($suggested) : slugify($title), 0, 180);
$slug = $base;
$sel = db()->prepare('SELECT 1 FROM posts WHERE slug = ?');
for ($n = 2; ; $n++) {
    $sel->execute([$slug]);
    if (!$sel->fetch()) {
        break;
    }
    if ($n > 99) {
        pp_hermes_out(422, ['ok' => false, 'error' => 'could not allocate a slug — 98 collisions on this title']);
    }
    $slug = $base . '-' . $n;
}

/* --- Status: draft behind the newsroom's gate; wire desks go live --------- */
$wireDesks = array_filter(array_map('trim', explode(',', pp_hermes_setting($siteId, 'wire_desks'))));
$isWire = in_array($deskSlug, $wireDesks, true);
$status = $isWire ? 'published' : 'draft';
$byline = pp_hermes_setting($siteId, 'automated_byline', 'Automated report');
$now = now();

$imagePath = '';
$imageNote = '';
if ($imageUrl !== '') {
    if (!pp_url_is_public($imageUrl)) {
        $imageNote = 'skipped — the image URL does not resolve to a public address';
    } else {
        [$bytes, $err] = http_get($imageUrl, 15);
        if ($err !== null) {
            $imageNote = 'skipped — fetch failed: ' . $err;
        } else {
            [$imagePath, $storeErr] = pp_store_image_bytes((string) $bytes, pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?? '', PATHINFO_FILENAME) ?: 'wire-image');
            if ($imagePath === null) {
                $imagePath = '';
                $imageNote = 'skipped — ' . $storeErr;
            }
        }
    }
}

$pdo = db();
$pdo->prepare('INSERT INTO posts
    (title, slug, category_id, byline, dateline, lede, body,
     meta_description, post_type, origin, status,
     image, image_caption, image_credit,
     filed_by, content_hash, published_at, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([
        $title, $slug, (int) $desk['id'], $byline, $dateline, $lede, $body,
        excerpt($lede, 155), 'story', 'hermes', $status,
        $imagePath, $imagePath !== '' ? $imageCaption : '', $imagePath !== '' ? $imageCredit : '',
        $agent['name'], $hash, $status === 'published' ? $now : null, $now, $now,
    ]);
$postId = pp_last_id('posts');
$pdo->prepare('INSERT INTO post_sites (post_id, site_id) VALUES (?, ?)')->execute([$postId, $siteId]);

$insSrc = $pdo->prepare('INSERT INTO story_sources (post_id, url, title, retrieved_at, created_at) VALUES (?, ?, ?, ?, ?)');
foreach ($sources as $src) {
    $insSrc->execute([$postId, $src['url'], $src['title'], $src['retrieved_at'], $now]);
}

if ($tags !== '') {
    $selTag = $pdo->prepare('SELECT id FROM tags WHERE slug = ?');
    $insTag = $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');
    $insPT  = $pdo->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)');
    foreach (array_slice(array_filter(array_map('trim', explode(',', $tags))), 0, 10) as $name) {
        $tslug = slugify($name);
        if ($tslug === '') {
            continue;
        }
        $selTag->execute([$tslug]);
        $tag = $selTag->fetch();
        $tagId = $tag ? (int) $tag['id'] : null;
        if ($tagId === null) {
            $insTag->execute([mb_substr($name, 0, 120), $tslug]);
            $tagId = pp_last_id('tags');
        }
        $insPT->execute([$postId, $tagId]);
    }
}

$pdo->prepare('UPDATE ingest_agents SET last_used_at = ? WHERE id = ?')->execute([$now, (int) $agent['id']]);
$pdo->prepare('INSERT INTO audit_log (site_id, user_id, user_name, action, target, detail, ip, created_at)
    VALUES (?, 0, ?, ?, ?, ?, ?, ?)')
    ->execute([$siteId, 'hermes:' . $agent['name'], 'ingest', $slug,
        $isWire ? 'wire desk, published' : 'filed as draft',
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $now]);

$out = ['ok' => true, 'id' => $postId, 'slug' => $slug, 'status' => $status];
if ($imageUrl !== '') {
    $out['image'] = $imagePath !== '' ? $imagePath : $imageNote;
}
pp_hermes_out(201, $out);
