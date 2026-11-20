<?php
/**
 * POST /api/ingest/lookup — read-only: has this source story already been
 * filed on this paper and desk?
 *
 * Built for a filing job that must not refile what the newsroom already
 * has — whether the story arrived through this API, was hand-entered by
 * an editor, or predates stable story ids. The answer is one of three
 * words, and for a verified match an opaque article reference, the
 * editorial status, and the public URL when the story is published.
 * Nothing else: no draft copy, no editor details, no listing. This
 * endpoint WRITES NOTHING — not an audit row, not last_used_at — and an
 * error here can never authorize a filing: only POST /api/ingest files,
 * and its own (site, source_story_id) uniqueness holds regardless of
 * what a lookup said or failed to say.
 *
 * Permission is separate from filing and fail-closed: a token looks up
 * only if it was minted with lookup_scope 'own' (its own filings, plus
 * published stories) or 'all' (the full editorial queue — drafts in
 * review, scheduled and published alike, whoever created them — within
 * the token's papers and desks). Finding editor-created or other
 * filers' unpublished work is exactly what 'all' grants and 'own' does
 * not. /api/ingest-queue remains the artwork queue; this surface reads
 * nothing from it.
 *
 * Scope is one paper and one desk per call, checked server-side three
 * ways: the requested site must be in the token's papers, must be the
 * paper THIS hostname serves (the domains table, exactly as tenant
 * resolution reads it), and the desk must be within the token's desks.
 * Every scope failure is answered with the same three words — 403
 * {"ok":false,"error":"out of scope"} — whether or not the site, the
 * desk or the story exists.
 *
 * Matching: the stored source_story_id is checked FIRST, against the
 * receipts the filing path keeps, regardless of artwork or status. Only
 * then the bounded legacy comparison for older or hand-entered stories:
 * the exact normalized headline (lowercased, whitespace collapsed) and
 * the canonical primary-source URL where both sides have one. A source
 * URL alone, or a merely similar headline, is never a match. When legacy
 * coverage cannot be ruled out — exact headline but no URL to confirm,
 * conflicting URL evidence, a receipt whose article moved desks, or a
 * scan that hit its bound — the answer is review_required, not
 * not_found.
 *
 * Request:  Authorization: Bearer <token>
 *           JSON body: {site, desk, headline, source_story_id?, source_url?}
 * Response: 200 {ok, result: "match", article: {ref, status, url?}}
 *           200 {ok, result: "review_required"}
 *           200 {ok, result: "not_found"}
 *           401/403/422 {ok: false, error} — and never a receipt
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/ingest.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    pp_ingest_respond(405, ['ok' => false, 'error' => 'POST one lookup per call']);
}

/* --- Authentication: token -> agent, fail closed (app/ingest.php) --------- */
$agent = pp_ingest_authenticate();

/* --- Capability: after authentication, before any read ------------------- */
// article:lookup is granted at minting (ingest_agents.lookup_scope) and is
// separate from filing and publishing. Refused outright rather than
// answered not_found, so a key of the wrong kind is visible at once —
// and, like every refusal here, it reveals nothing about any story.
if ($agent['lookup'] === '') {
    pp_ingest_respond(403, [
        'ok' => false,
        'error' => 'this token has no lookup permission',
        'reason' => 'article:lookup is granted at minting (lookup_scope own|all); without it this surface answers nothing',
    ]);
}

/* --- Payload -------------------------------------------------------------- */
$raw = file_get_contents('php://input', false, null, 0, 64 * 1024);
$in = json_decode((string) $raw, true);
if (!is_array($in)) {
    pp_ingest_respond(400, ['ok' => false, 'error' => 'body must be a JSON object']);
}

/* --- Scope: one paper, one desk, on that paper's own hostname -------------
   One answer for every failure in this block, existence notwithstanding. */
$outOfScope = static fn (): never => pp_ingest_respond(403, ['ok' => false, 'error' => 'out of scope']);

$siteSlug = slugify((string) ($in['site'] ?? ''));
if ($siteSlug === '' || !in_array($siteSlug, $agent['sites'], true)) {
    $outOfScope();
}
// The hostname this request arrived on must BE that paper — the same
// domains rows tenant resolution reads. A token may hold several papers,
// but it asks each paper about itself, on that paper's own door.
$host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
$stmt = db()->prepare('SELECT site_slug FROM domains WHERE hostname = ?');
$stmt->execute([$host]);
$served = $stmt->fetch();
if (!$served || (string) $served['site_slug'] !== $siteSlug) {
    $outOfScope();
}
$siteRow = db()->prepare('SELECT id FROM sites WHERE slug = ?');
$siteRow->execute([$siteSlug]);
$site = $siteRow->fetch();
if (!$site) {
    $outOfScope();
}
$siteId = (int) $site['id'];

$deskSlug = slugify((string) ($in['desk'] ?? ''));
if ($deskSlug === '' || ($agent['desks'] && !in_array($deskSlug, $agent['desks'], true))) {
    $outOfScope();
}
$deskRow = db()->prepare('SELECT id FROM categories WHERE slug = ?');
$deskRow->execute([$deskSlug]);
$desk = $deskRow->fetch();
if (!$desk) {
    pp_ingest_respond(422, ['ok' => false, 'error' => "unknown desk '{$deskSlug}'"]);
}
$deskId = (int) $desk['id'];

/* --- The question ---------------------------------------------------------- */
$headline = trim((string) ($in['headline'] ?? ''));
if ($headline === '' || mb_strlen($headline) > 255) {
    pp_ingest_respond(422, ['ok' => false, 'error' => 'headline is required, at most 255 characters']);
}
$sourceStoryId = trim((string) ($in['source_story_id'] ?? ''));
if (mb_strlen($sourceStoryId) > 120 || preg_match('/[\x00-\x1f\x7f]/', $sourceStoryId)) {
    pp_ingest_respond(422, ['ok' => false, 'error' => 'source_story_id is at most 120 visible characters']);
}
$sourceUrl = trim((string) ($in['source_url'] ?? ''));
if ($sourceUrl !== '' && (strlen($sourceUrl) > 600 || !preg_match('#^https?://#i', $sourceUrl))) {
    pp_ingest_respond(422, ['ok' => false, 'error' => 'source_url must be http(s), at most 600 characters']);
}

// The per-install key behind opaque article references (minted by the
// schema, never here: this endpoint writes nothing).
$k = db()->prepare("SELECT svalue FROM settings WHERE site_id = 0 AND skey = 'ingest_ref_key'");
$k->execute();
$refKey = (string) ($k->fetchColumn() ?: '');
if ($refKey === '') {
    pp_ingest_respond(500, ['ok' => false, 'error' => 'lookup is not provisioned — run tools/migrate.php --apply']);
}

/** The small, closed answer for a verified match. */
$answerMatch = static function (array $post) use ($refKey, $siteId, $siteSlug): never {
    $article = [
        'ref' => 'ar_' . substr(hash_hmac('sha256', $siteId . '/' . (int) $post['id'], $refKey), 0, 24),
        'status' => (string) $post['status'],
    ];
    if ($post['status'] === 'published') {
        $article['url'] = pp_story_public_url($siteSlug, (string) $post['slug']);
    }
    pp_ingest_respond(200, ['ok' => true, 'result' => 'match', 'article' => $article]);
};
$answer = static fn (string $result): never => pp_ingest_respond(200, ['ok' => true, 'result' => $result]);

/* --- 1. The stored story id, first, regardless of artwork or status ------- */
if ($sourceStoryId !== '') {
    $stmt = db()->prepare('SELECT * FROM ingest_receipts WHERE site_id = ? AND source_story_id = ?');
    $stmt->execute([$siteId, $sourceStoryId]);
    if ($receipt = $stmt->fetch()) {
        $p = db()->prepare('SELECT id, slug, status, category_id, filed_by FROM posts WHERE id = ?');
        $p->execute([(int) $receipt['post_id']]);
        $post = $p->fetch();
        // The receipt is certain knowledge that this id was filed here. When
        // what it points at cannot be shown whole within THIS desk and THIS
        // token's grant — the article was removed, an editor moved it to
        // another desk, or an 'own' grant meets another filer's unpublished
        // work — the answer is review_required: it cannot be ruled out, and
        // nothing more is revealed.
        if (!$post || (int) $post['category_id'] !== $deskId) {
            $answer('review_required');
        }
        if ($agent['lookup'] === 'own' && $post['status'] !== 'published'
            && (string) $receipt['filed_by'] !== $agent['name']) {
            $answer('review_required');
        }
        $answerMatch($post);
    }
}

/* --- 2. The bounded legacy comparison -------------------------------------
   For stories older than stable ids, or hand-entered by an editor. The
   evidence is the exact normalized headline and the canonical
   primary-source URL where both sides have one; either alone falls to
   review_required, never to a match — and never silently to not_found. */
$normalize = static fn (string $t): string => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $t)));
$canonical = static function (string $url): string {
    $p = parse_url(trim($url));
    if (!is_array($p) || empty($p['host'])) {
        return '';
    }
    $q = '';
    if (!empty($p['query'])) {
        parse_str($p['query'], $pairs);
        foreach (array_keys($pairs) as $key) {
            if (preg_match('/^(utm_|fbclid$|gclid$)/i', (string) $key)) {
                unset($pairs[$key]);
            }
        }
        $q = $pairs ? '?' . http_build_query($pairs) : '';
    }
    return strtolower((string) $p['host'])
        . rtrim((string) ($p['path'] ?? ''), '/')
        . $q;
};
$wantTitle = $normalize($headline);
$wantUrl = $sourceUrl !== '' ? $canonical($sourceUrl) : '';

$bound = 400;
$scopeOwn = $agent['lookup'] === 'own';
$sql = "SELECT p.id, p.slug, p.status, p.title, p.source_url
          FROM posts p
          JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = ?
         WHERE p.category_id = ? AND p.post_type = 'story'"
     . ($scopeOwn ? " AND (p.status = 'published' OR p.filed_by = ?)" : '')
     . ' ORDER BY p.id DESC LIMIT ' . ($bound + 1);
$args = $scopeOwn ? [$siteId, $deskId, $agent['name']] : [$siteId, $deskId];
$stmt = db()->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();
$stmt->closeCursor();

$truncated = count($rows) > $bound;
$rows = array_slice($rows, 0, $bound);
$urlsOf = static function (array $post) use ($canonical): array {
    $urls = [];
    if ((string) $post['source_url'] !== '') {
        $urls[] = $canonical((string) $post['source_url']);
    }
    $s = db()->prepare('SELECT url FROM story_sources WHERE post_id = ?');
    $s->execute([(int) $post['id']]);
    foreach ($s->fetchAll() as $r) {
        $urls[] = $canonical((string) $r['url']);
    }
    return array_values(array_filter(array_unique($urls)));
};

$uncertain = $truncated;   // a scan that hit its bound cannot rule anything out
foreach ($rows as $post) {
    $titleHit = $normalize((string) $post['title']) === $wantTitle;
    if (!$titleHit && $wantUrl === '') {
        continue;
    }
    $urls = $urlsOf($post);
    $urlHit = $wantUrl !== '' && in_array($wantUrl, $urls, true);
    if ($titleHit && $urlHit) {
        $answerMatch($post);               // both lines of evidence agree
    }
    if ($titleHit || $urlHit) {
        $uncertain = true;                 // one line of evidence, not two
    }
}
$answer($uncertain ? 'review_required' : 'not_found');
