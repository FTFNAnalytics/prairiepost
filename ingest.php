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
 * A token minted draft-only (ingest_agents.draft_only = 1; the
 * `--draft-only` flag of tools/make-agent.php) files drafts and nothing
 * else. The wire-desk exception and the publish_on_image flag are both
 * publish instructions, and that credential cannot give them: its
 * filing lands as a plain draft whatever the desk or the flag says, is
 * never queued for the image agent, and the response carries `reason`
 * saying so. Only the newsroom publishes what a draft-only token files.
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
 *            image?, image_caption?, image_credit?,
 *            publish_on_image?,
 *            dateline?, tags?, suggested_slug?, external_id?,
 *            source_story_id?,
 *            sources?: [{url, title?, retrieved_at?}]}
 *           `image` is an https URL the server fetches itself, or an
 *           /uploads/… path returned by POST /api/ingest-media.
 *           `publish_on_image` (true) is the filing agent's "ready"
 *           checkbox: the copy is approved and the story publishes the
 *           moment it has a featured image — immediately when this
 *           filing carries one, otherwise when a second agent attaches
 *           one through POST /api/ingest-publish. Flagged stories
 *           waiting for art are listed by GET /api/ingest-queue.
 *           `source_story_id` is the filer's stable id for the source
 *           story. The database enforces uniqueness on
 *           (site, source_story_id) — two simultaneous submissions
 *           create exactly one article. An identical retry is answered
 *           with the original receipt (200, duplicate: true); a
 *           DIFFERENT payload under an already-used id is refused 409
 *           and never overwrites what an editor may have changed.
 *           Genuine updates stay on the newsroom's editorial path.
 * Response: 201 {ok, id, slug, status, reason?}
 *           `reason` is present when a draft-only token's filing was kept
 *           as a draft (it says what was withheld).
 *           200 {ok, duplicate: true, id, slug} on an exact re-file
 *           409 {ok: false, error} — source_story_id taken by different content
 *           4xx {ok: false, error} — the reason, never a coercion
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/ingest.php';

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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    pp_ingest_respond(405, ['ok' => false, 'error' => 'POST one JSON story per call']);
}

/* --- Authentication: token -> agent, fail closed (app/ingest.php) --------- */
$agent = pp_ingest_authenticate();

/* --- Payload -------------------------------------------------------------- */
$raw = file_get_contents('php://input', false, null, 0, 512 * 1024);
$in = json_decode((string) $raw, true);
if (!is_array($in)) {
    pp_ingest_respond(400, ['ok' => false, 'error' => 'body must be a JSON object']);
}

$siteSlug = slugify((string) ($in['site'] ?? ''));
if ($siteSlug === '' || !in_array($siteSlug, $agent['sites'], true)) {
    pp_ingest_respond(403, ['ok' => false, 'error' => 'this token is not scoped to that site']);
}
$siteRow = db()->prepare('SELECT * FROM sites WHERE slug = ?');
$siteRow->execute([$siteSlug]);
$site = $siteRow->fetch();
if (!$site) {
    pp_ingest_respond(403, ['ok' => false, 'error' => 'that site does not exist yet — it is created at launch, not by ingest']);
}
$siteId = (int) $site['id'];

$deskSlug = slugify((string) ($in['desk'] ?? ''));
if ($deskSlug === '' || ($agent['desks'] && !in_array($deskSlug, $agent['desks'], true))) {
    pp_ingest_respond(403, ['ok' => false, 'error' => 'this token is not scoped to that desk']);
}
$deskRow = db()->prepare('SELECT id, slug FROM categories WHERE slug = ?');
$deskRow->execute([$deskSlug]);
$desk = $deskRow->fetch();
if (!$desk) {
    pp_ingest_respond(422, ['ok' => false, 'error' => "unknown desk '{$deskSlug}' — desks are created at launch, not by ingest"]);
}

$title = trim(strip_tags((string) ($in['title'] ?? '')));
$lede  = trim(strip_tags((string) ($in['lede'] ?? '')));
$body  = trim(sanitize_html((string) ($in['body'] ?? '')));
if ($title === '' || mb_strlen($title) > 255) {
    pp_ingest_respond(422, ['ok' => false, 'error' => 'title is required, at most 255 characters']);
}
if ($lede === '' || mb_strlen($lede) > 1000) {
    pp_ingest_respond(422, ['ok' => false, 'error' => 'lede is required, at most 1000 characters']);
}
if ($body === '' || strlen($body) > 200000) {
    pp_ingest_respond(422, ['ok' => false, 'error' => 'body is required, at most 200 KB after sanitization']);
}
$dateline = trim(strip_tags((string) ($in['dateline'] ?? '')));
$tags = trim(strip_tags((string) ($in['tags'] ?? '')));
$externalId = trim((string) ($in['external_id'] ?? ''));
if (mb_strlen($externalId) > 120) {
    pp_ingest_respond(422, ['ok' => false, 'error' => 'external_id is at most 120 characters']);
}
// The filer's stable id for the source story. Visible characters only —
// it lands in a UNIQUE column, audit rows and support conversations.
$sourceStoryId = trim((string) ($in['source_story_id'] ?? ''));
if ($sourceStoryId !== '' && (mb_strlen($sourceStoryId) > 120 || preg_match('/[\x00-\x1f\x7f]/', $sourceStoryId))) {
    pp_ingest_respond(422, ['ok' => false, 'error' => 'source_story_id is at most 120 visible characters']);
}

// Optional image, two forms. An `/uploads/…` path is one this install
// minted itself — /api/ingest-media returns exactly this shape, and the
// strict pattern plus the file-exists check mean an agent can only name
// a stored upload, never traverse to anything else. A URL the server
// fetches ITSELF goes through the public-address guard first — the
// token holder writes the request but must not be able to point it
// inward. A fetch or format failure never sinks the filing; the story
// lands without a picture and the response says why.
$imageUrl = trim((string) ($in['image'] ?? ''));
$imageCaption = mb_substr(trim(strip_tags((string) ($in['image_caption'] ?? ''))), 0, 255);
$imageCredit = mb_substr(trim(strip_tags((string) ($in['image_credit'] ?? ''))), 0, 120);
$imageIsUpload = (bool) preg_match('#^/uploads/\d{4}/\d{2}/[a-z0-9][a-z0-9-]*\.(jpg|jpeg|png|webp|gif)$#', $imageUrl);
if ($imageIsUpload && !is_file(PP_ROOT . $imageUrl)) {
    pp_ingest_respond(422, ['ok' => false, 'error' => 'image names an /uploads/ path that does not exist — upload it first via /api/ingest-media']);
}
if ($imageUrl !== '' && !$imageIsUpload && (strlen($imageUrl) > 600 || !preg_match('#^https?://#i', $imageUrl))) {
    pp_ingest_respond(422, ['ok' => false, 'error' => 'image must be an http(s) URL, or an /uploads/ path returned by /api/ingest-media']);
}

$sources = [];
foreach ((array) ($in['sources'] ?? []) as $i => $src) {
    if (!is_array($src)) {
        pp_ingest_respond(422, ['ok' => false, 'error' => "sources[$i] must be an object"]);
    }
    $url = trim((string) ($src['url'] ?? ''));
    if (!preg_match('#^https?://#i', $url) || strlen($url) > 600) {
        pp_ingest_respond(422, ['ok' => false, 'error' => "sources[$i].url must be http(s), at most 600 characters"]);
    }
    $sources[] = [
        'url' => $url,
        'title' => mb_substr(trim(strip_tags((string) ($src['title'] ?? ''))), 0, 255),
        'retrieved_at' => trim((string) ($src['retrieved_at'] ?? '')) ?: null,
    ];
    if (count($sources) > 20) {
        pp_ingest_respond(422, ['ok' => false, 'error' => 'at most 20 sources per story']);
    }
}

/* --- source_story_id: retries are safe, the DATABASE enforces it ----------
   The payload fingerprint is the request as the agent sent it (after
   validation, before any fetch), so an identical retry hashes identically
   whatever happened to the image in between. The fast path below answers
   retries before the rate limit and before any image work; the UNIQUE
   constraint on ingest_receipts (site_id, source_story_id) is what makes
   two SIMULTANEOUS first submissions create exactly one article — the
   loser's insert fails and is answered from the winner's receipt. */
$payloadHash = hash('sha256', json_encode([
    $siteSlug, $deskSlug, $title, $lede, $body,
    $imageUrl, $imageCaption, $imageCredit, !empty($in['publish_on_image']),
    $dateline, $tags, trim((string) ($in['suggested_slug'] ?? '')),
    array_column($sources, 'url'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

/**
 * Answer from an existing receipt and stop: the original receipt for an
 * identical retry, 409 for different content under the same id. Editorial
 * edits are never overwritten — this path writes nothing.
 */
function pp_ssid_answer(array $receipt, string $payloadHash, string $siteSlug, array $agent): never
{
    if (!hash_equals((string) $receipt['payload_hash'], $payloadHash)) {
        pp_ingest_audit((int) $receipt['site_id'], (string) $agent['name'], 'ingest-conflict',
            mb_substr((string) $receipt['source_story_id'], 0, 160),
            'refused 409: source_story_id already filed with different content (post #' . (int) $receipt['post_id'] . ' untouched)',
            (int) $receipt['post_id']);
        pp_ingest_respond(409, ['ok' => false,
            'error' => 'source_story_id already filed with different content — the stored article was not changed; corrections go through the newsroom']);
    }
    $out = ['ok' => true, 'duplicate' => true,
        'id' => (int) $receipt['post_id'], 'slug' => (string) $receipt['slug']];
    $p = db()->prepare('SELECT status, slug FROM posts WHERE id = ?');
    $p->execute([(int) $receipt['post_id']]);
    if ($row = $p->fetch()) {
        $out['status'] = (string) $row['status'];
        if ($row['status'] === 'published') {
            $out['url'] = pp_story_public_url($siteSlug, (string) $row['slug']);
        }
    }
    pp_ingest_respond(200, $out);
}

if ($sourceStoryId !== '') {
    $stmt = db()->prepare('SELECT * FROM ingest_receipts WHERE site_id = ? AND source_story_id = ?');
    $stmt->execute([$siteId, $sourceStoryId]);
    if ($receipt = $stmt->fetch()) {
        pp_ssid_answer($receipt, $payloadHash, $siteSlug, $agent);
    }
}

/* --- Rate limit: filings per token per hour, from the posts it filed ------ */
// Counted across every paper the token files to. The number is the target
// paper's own ingest_hourly_limit setting when it holds one, else the
// network's: 200 unless PP_INGEST_STORY_LIMIT_PER_HOUR says otherwise
// (app/ingest.php). Before 26 Nov 2026 an unset setting meant 60, and a
// setting of 0 or nonsense meant 1; now both mean "not set".
$limit = pp_ingest_limit_value(pp_hermes_setting($siteId, 'ingest_hourly_limit', ''))
    ?? pp_ingest_hourly_limit('story');
$stmt = db()->prepare("SELECT COUNT(*) FROM posts WHERE filed_by = ? AND created_at > ?");
$stmt->execute([$agent['name'], date('Y-m-d H:i:s', time() - 3600)]);
if ((int) $stmt->fetchColumn() >= $limit) {
    pp_ingest_respond(429, ['ok' => false, 'error' => "rate limit: at most {$limit} filings per hour for this token"]);
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
    pp_ingest_respond(200, ['ok' => true, 'duplicate' => true, 'id' => (int) $dup['id'], 'slug' => $dup['slug']]);
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
        pp_ingest_respond(422, ['ok' => false, 'error' => 'could not allocate a slug — 98 collisions on this title']);
    }
    $slug = $base . '-' . $n;
}

/* --- Status: draft behind the newsroom's gate; wire desks go live.
   The publish_on_image flag is the filer's "ready" checkbox: approved
   copy that goes live as soon as it has a featured image — now, when
   this filing brought one, or later through /api/ingest-publish. ------ */
$wireDesks = array_filter(array_map('trim', explode(',', pp_hermes_setting($siteId, 'wire_desks'))));
$isWire = in_array($deskSlug, $wireDesks, true);
$readyOnImage = !empty($in['publish_on_image']);
// A draft-only token can give neither publish instruction: the wire-desk
// exception is withheld here and the ready flag below, so its filing is
// a plain draft for the newsroom — never queued for the image agent.
$status = ($isWire && $agent['may_publish']) ? 'published' : 'draft';
$awaitingImage = 0;   // resolved below, once the image outcome is known
$draftReason = '';
if (!$agent['may_publish']) {
    $withheld = [];
    if ($isWire) {
        $withheld[] = 'wire-desk auto-publish withheld';
    }
    if ($readyOnImage) {
        $withheld[] = 'publish_on_image not honoured';
    }
    $draftReason = 'draft_only token: this credential files drafts for the newsroom to publish'
        . ($withheld ? ' (' . implode('; ', $withheld) . ')' : '');
}
$byline = pp_normalize_byline(pp_hermes_setting($siteId, 'automated_byline', 'Newsroom Staff'));
$now = now();

$imagePath = '';
$imageNote = '';
if ($imageIsUpload) {
    $imagePath = $imageUrl;   // validated above: this install's own stored upload
} elseif ($imageUrl !== '') {
    if (!pp_url_is_public($imageUrl)) {
        $imageNote = 'skipped — the image URL does not resolve to a public address';
    } else {
        [$bytes, $err] = pp_http_get($imageUrl, ['timeout' => 15, 'max_bytes' => 8 * 1024 * 1024]);
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

// The ready flag resolves only NOW, when the image outcome is known: a
// filing that brought usable art publishes at once; one still waiting
// stays a draft that /api/ingest-publish may flip when the art arrives.
if ($readyOnImage && $agent['may_publish'] && $status !== 'published') {
    if ($imagePath !== '') {
        $status = 'published';
    } else {
        $awaitingImage = 1;
    }
}

$pdo = db();
// The filing and its receipt commit together: if the receipt loses a race
// on UNIQUE (site_id, source_story_id), the whole filing rolls back and
// the request is answered from the winner's receipt — one article, ever.
$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO posts
        (title, slug, category_id, byline, dateline, lede, body,
         meta_description, post_type, origin, status, awaiting_image,
         image, image_caption, image_credit,
         filed_by, content_hash, published_at, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            $title, $slug, (int) $desk['id'], $byline, $dateline, $lede, $body,
            excerpt($lede, 155), 'story', 'hermes', $status, $awaitingImage,
            $imagePath, $imagePath !== '' ? $imageCaption : '', $imagePath !== '' ? $imageCredit : '',
            $agent['name'], $hash, $status === 'published' ? $now : null, $now, $now,
        ]);
    $postId = pp_last_id('posts');
    $pdo->prepare('INSERT INTO post_sites (post_id, site_id) VALUES (?, ?)')->execute([$postId, $siteId]);
    if ($sourceStoryId !== '') {
        $pdo->prepare('INSERT INTO ingest_receipts (site_id, source_story_id, post_id, slug, payload_hash, filed_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$siteId, $sourceStoryId, $postId, $slug, $payloadHash, $agent['name'], $now]);
    }

    // Sources and tags commit WITH the story and its receipt: a receipt
    // must never vouch for a story that was only partly saved, or a retry
    // would answer `duplicate` for a filing missing its provenance.
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
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    // 23xxx is the integrity-constraint class on all three engines. With a
    // source_story_id in play that is the lost race: answer from the
    // winner's receipt exactly as the fast path would have.
    if ($sourceStoryId !== '' && str_starts_with((string) $e->getCode(), '23')) {
        $stmt = $pdo->prepare('SELECT * FROM ingest_receipts WHERE site_id = ? AND source_story_id = ?');
        $stmt->execute([$siteId, $sourceStoryId]);
        if ($receipt = $stmt->fetch()) {
            pp_ssid_answer($receipt, $payloadHash, $siteSlug, $agent);
        }
    }
    throw $e;
}

$pdo->prepare('UPDATE ingest_agents SET last_used_at = ? WHERE id = ?')->execute([$now, (int) $agent['id']]);
// The story is stored by now, so this row only observes. pp_ingest_audit()
// cuts every value to its column — a slug can be longer than
// audit_log.target, which PostgreSQL rejects — and never throws: a lost
// audit row must not turn a filed story into a 500.
pp_ingest_audit($siteId, (string) $agent['name'], 'ingest', $slug,
    $status === 'published'
        ? ($isWire ? 'wire desk, published' : 'ready flag + image, published')
        : ($awaitingImage ? 'filed as draft, awaiting image (publishes on attach)'
            : (!$agent['may_publish']
                ? 'filed as draft (draft-only token' . ($isWire ? '; wire desk withheld' : '')
                    . ($readyOnImage ? '; publish_on_image not honoured' : '') . ')'
                : 'filed as draft')),
    $postId, $now);

$out = ['ok' => true, 'id' => $postId, 'slug' => $slug, 'status' => $status];
if ($draftReason !== '') {
    $out['reason'] = $draftReason;
}
if ($awaitingImage === 1) {
    $out['awaiting_image'] = true;
}
if ($status === 'published') {
    $out['url'] = pp_story_public_url($siteSlug, $slug);
}
if ($imageUrl !== '') {
    $out['image'] = $imagePath !== '' ? $imagePath : $imageNote;
}
pp_ingest_respond(201, $out);
