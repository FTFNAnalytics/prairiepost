<?php
/**
 * The article-editing engine: ONE code path behind two endpoints.
 *
 *   PATCH /api/ingest/drafts/{ref}  ingest-drafts.php  mode 'draft'
 *   POST  /api/ingest/update        ingest-update.php  mode 'published'
 *
 * Both change an EXISTING story in place. Neither files, publishes,
 * schedules, approves, unpublishes, deletes, renames or moves a story
 * between papers, and neither changes its id, slug, public URL,
 * publication date, byline, dateline, correction note, awaiting-image
 * flag or the papers that carry it. Each has its own grant, set at
 * minting and default off:
 *
 *   draft      edit_drafts       a story whose status is exactly 'draft'
 *   published  update_published  a story whose status is exactly 'published'
 *
 * What either may change (omitted = unchanged; see docs/api-ingest.md §6):
 *
 *   title, lede, body            never empty; body through sanitize_html()
 *   image                        an /uploads/ path from /api/ingest-media
 *                                adds or replaces the cover; "" or null
 *                                removes it
 *   image_alt, image_caption,    the cover's text; "" or null clears one.
 *   image_credit                 A NEW image starts from what this request
 *                                sends — empty when omitted — so a
 *                                replaced picture never inherits the old
 *                                one's alt text, caption or credit.
 *   tags                         the whole set, replaced; [] clears
 *   sources                      the whole list, replaced; [] clears
 *   meta_description             the SEO description; "" clears
 *   new_desk (published only)    move to another desk the key holds
 *
 * Any other field is refused (422), so a typo never silently does nothing
 * and a request can never smuggle in a publish.
 *
 * Every edit REQUIRES if_version — the version GET /api/ingest/stories
 * (or this engine's previous answer) gave for the story — and the version
 * is checked twice: once on the read, and again inside the write
 * transaction while the row is locked, so an editor's save landing
 * mid-request is never overwritten. A stale version or a status change is
 * 409 and writes nothing. An edit whose fields already hold the requested
 * values is 200 `unchanged` and writes nothing, whatever version it
 * carries: that is what makes retrying a mutation whose answer was lost
 * safe. A 409 never carries a version — the next one comes from the
 * reader, so the agent sees what changed before it edits again.
 *
 * The story row, its tags and sources, the before/after revision
 * snapshots and the audit row commit in ONE transaction — all of them or
 * none. Revisions keep the text and image fields (an editor restores any
 * agent change from the story's history page, alt text and credit
 * included); the audit row names the key and records the previous tags,
 * sources and desk.
 */

/** Begin the write transaction with the story row locked (all three engines). */
function pp_ingest_edit_begin(PDO $pdo, int $postId): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        // PDO cannot ask SQLite for the immediate lock, so it is opened —
        // and must be ended — with raw statements (see tools/setup-admin.php).
        $pdo->exec('BEGIN IMMEDIATE');
        return;
    }
    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT id FROM posts WHERE id = ? FOR UPDATE');
    $lock->execute([$postId]);
    $lock->fetchAll();
}

function pp_ingest_edit_commit(PDO $pdo): void
{
    $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? $pdo->exec('COMMIT') : $pdo->commit();
}

function pp_ingest_edit_rollback(PDO $pdo): void
{
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec('ROLLBACK');
        } elseif ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Throwable) {
        // Nothing was open; nothing to undo.
    }
}

/**
 * Load one story as the editing engine sees it: the row, its tag names,
 * tag slugs and sources, and its version.
 *
 * @return array{post: array, tags: list<string>, tag_slugs: list<string>, sources: list<array{url: string, title: string, retrieved_at: ?string}>, version: string}|null
 */
function pp_ingest_edit_load(PDO $pdo, int $postId): ?array
{
    $s = $pdo->prepare('SELECT p.id, p.slug, p.status, p.post_type, p.category_id, p.awaiting_image,
            p.title, p.lede, p.body, p.meta_description, p.correction,
            p.image, p.image_alt, p.image_caption, p.image_credit, p.published_at, p.updated_at,
            c.slug AS desk
        FROM posts p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
    $s->execute([$postId]);
    $post = $s->fetch();
    $s->closeCursor();
    if (!$post) {
        return null;
    }
    $t = $pdo->prepare('SELECT t.name, t.slug FROM post_tags pt JOIN tags t ON t.id = pt.tag_id WHERE pt.post_id = ? ORDER BY t.name');
    $t->execute([$postId]);
    $tagRows = $t->fetchAll();
    $src = $pdo->prepare('SELECT url, title, retrieved_at FROM story_sources WHERE post_id = ? ORDER BY id');
    $src->execute([$postId]);
    $sources = [];
    foreach ($src->fetchAll() as $r) {
        $sources[] = ['url' => (string) $r['url'], 'title' => (string) $r['title'],
            'retrieved_at' => $r['retrieved_at'] !== null ? (string) $r['retrieved_at'] : null];
    }
    $tags = array_map(static fn ($r) => (string) $r['name'], $tagRows);
    $plain = array_map(static fn ($x) => ['url' => $x['url'], 'title' => $x['title']], $sources);
    return [
        'post' => $post,
        'tags' => $tags,
        'tag_slugs' => array_map(static fn ($r) => (string) $r['slug'], $tagRows),
        'sources' => $sources,
        'version' => pp_ingest_story_version($post, $tags, $plain),
    ];
}

/**
 * Insert one revision row from the story as it stands, inside the caller's
 * transaction: unlike pp_post_snapshot() this THROWS, because here the
 * revision is part of the edit — no history, no edit.
 */
function pp_ingest_edit_revision(PDO $pdo, int $postId, string $reason, string $by): void
{
    $s = $pdo->prepare('SELECT title, lede, body, meta_description, correction, image, image_caption, image_credit, image_alt FROM posts WHERE id = ?');
    $s->execute([$postId]);
    $p = $s->fetch();
    $s->closeCursor();
    $pdo->prepare('INSERT INTO post_revisions (post_id, title, lede, body, meta_description, correction, image, image_caption, image_credit, image_alt, saved_by, reason, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$postId, (string) $p['title'], (string) ($p['lede'] ?? ''), (string) ($p['body'] ?? ''),
            (string) $p['meta_description'], (string) ($p['correction'] ?? ''), (string) $p['image'],
            (string) $p['image_caption'], (string) ($p['image_credit'] ?? ''), (string) ($p['image_alt'] ?? ''),
            mb_substr($by, 0, 120), mb_substr($reason, 0, 40), now()]);
    // The same 40-row cap pp_post_snapshot() keeps.
    $c = $pdo->prepare('SELECT id FROM post_revisions WHERE post_id = ? ORDER BY id DESC LIMIT 1 OFFSET 39');
    $c->execute([$postId]);
    $cutoff = (int) ($c->fetchColumn() ?: 0);
    $c->closeCursor();
    if ($cutoff > 0) {
        $pdo->prepare('DELETE FROM post_revisions WHERE post_id = ? AND id < ?')->execute([$postId, $cutoff]);
    }
}

/**
 * Run one edit and answer it. $mode is 'draft' or 'published'; $pathRef is
 * the story reference from the URL path (draft mode), '' otherwise.
 */
function pp_ingest_edit(array $agent, string $mode, string $pathRef = ''): never
{
    $isDraft = $mode === 'draft';
    $action = $isDraft ? 'ingest-draft-edit' : 'ingest-update';
    $pdo = db();

    /* --- Capability: after authentication, before any read ---------------- */
    if ($isDraft && !$agent['may_edit_drafts']) {
        pp_ingest_respond(403, ['ok' => false, 'error' => 'this token has no draft-edit permission',
            'reason' => 'edit_drafts is granted at minting (--edit-drafts); without it this surface changes nothing']);
    }
    if (!$isDraft && !$agent['may_update_published']) {
        pp_ingest_respond(403, ['ok' => false, 'error' => 'this token has no update permission',
            'reason' => 'update_published is granted at minting (--update-published); without it this surface changes nothing']);
    }

    /* --- Payload ----------------------------------------------------------- */
    $raw = file_get_contents('php://input', false, null, 0, 512 * 1024);
    $in = json_decode((string) $raw, true);
    if (!is_array($in) || (array_is_list($in) && $in !== [])) {
        pp_ingest_respond(400, ['ok' => false, 'error' => 'body must be a JSON object']);
    }

    /* --- Scope: one paper, one desk, on that paper's own hostname ---------- */
    $outOfScope = static fn (): never => pp_ingest_respond(403, ['ok' => false, 'error' => 'out of scope']);
    $siteSlug = is_string($in['site'] ?? null) ? slugify($in['site']) : '';
    if ($siteSlug === '' || !in_array($siteSlug, $agent['sites'], true)) {
        $outOfScope();
    }
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $stmt = $pdo->prepare('SELECT site_slug FROM domains WHERE hostname = ?');
    $stmt->execute([$host]);
    $served = $stmt->fetch();
    if (!$served || (string) $served['site_slug'] !== $siteSlug) {
        $outOfScope();
    }
    $stmt = $pdo->prepare('SELECT id FROM sites WHERE slug = ?');
    $stmt->execute([$siteSlug]);
    $siteId = (int) ($stmt->fetchColumn() ?: 0);
    if ($siteId === 0) {
        $outOfScope();
    }
    $deskSlug = is_string($in['desk'] ?? null) ? slugify($in['desk']) : '';
    if ($deskSlug === '' || ($agent['desks'] && !in_array($deskSlug, $agent['desks'], true))) {
        $outOfScope();
    }
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
    $stmt->execute([$deskSlug]);
    $deskId = (int) ($stmt->fetchColumn() ?: 0);
    if ($deskId === 0) {
        pp_ingest_respond(422, ['ok' => false, 'error' => "unknown desk '{$deskSlug}'"]);
    }

    /* --- Which story: the reader's ref (drafts: in the path), or the URL --- */
    $refKey = pp_ingest_ref_key();
    if ($refKey === '') {
        pp_ingest_respond(500, ['ok' => false, 'error' => 'editing is not provisioned — run tools/migrate.php --apply']);
    }
    $notFound = static fn (): never => pp_ingest_respond(404, ['ok' => false, 'error' => 'no story with that reference on this paper and desk']);
    $ref = $isDraft ? $pathRef : (is_string($in['ref'] ?? null) ? trim($in['ref']) : '');
    $url = $isDraft ? '' : (is_string($in['url'] ?? null) ? trim($in['url']) : '');
    if ($isDraft && (array_key_exists('ref', $in) || array_key_exists('url', $in))) {
        pp_ingest_respond(422, ['ok' => false,
            'error' => 'the draft\'s ref is in the path (PATCH /api/ingest/drafts/{ref}); send no ref or url in the body']);
    }
    if (!$isDraft && ($ref === '') === ($url === '')) {
        pp_ingest_respond(422, ['ok' => false,
            'error' => 'send exactly one of ref (from /api/ingest/lookup or /api/ingest/stories) or url (the story\'s public address on this paper)']);
    }
    $postId = 0;
    if ($url !== '') {
        $u = parse_url($url);
        if (!is_array($u) || empty($u['host']) || !preg_match('#^/story/([a-z0-9][a-z0-9-]*)/?$#', (string) ($u['path'] ?? ''), $m)) {
            pp_ingest_respond(422, ['ok' => false, 'error' => 'url must be a public story address: https://<this paper>/story/<slug>']);
        }
        // A story address on another paper's hostname is another paper's story.
        if (strtolower((string) $u['host']) !== $host) {
            $outOfScope();
        }
        $s = $pdo->prepare("SELECT p.id FROM posts p JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = ?
            WHERE p.slug = ? AND p.category_id = ? AND p.post_type = 'story'");
        $s->execute([$siteId, $m[1], $deskId]);
        $postId = (int) ($s->fetchColumn() ?: 0);
        $s->closeCursor();
    } else {
        if (!preg_match('/^ar_[0-9a-f]{24}$/', $ref)) {
            pp_ingest_respond(422, ['ok' => false, 'error' => 'ref must be an article reference from /api/ingest/stories or /api/ingest/lookup (ar_ + 24 hex)']);
        }
        // A reference is resolvable only here: recompute it over this
        // paper's stories on this desk until one answers to it.
        $s = $pdo->prepare("SELECT p.id FROM posts p JOIN post_sites ps ON ps.post_id = p.id AND ps.site_id = ?
            WHERE p.category_id = ? AND p.post_type = 'story'");
        $s->execute([$siteId, $deskId]);
        foreach ($s->fetchAll() as $row) {
            if (hash_equals(pp_ingest_article_ref($siteId, (int) $row['id'], $refKey), $ref)) {
                $postId = (int) $row['id'];
                break;
            }
        }
    }
    if ($postId === 0) {
        $notFound();
    }
    $story = pp_ingest_edit_load($pdo, $postId);
    if ($story === null) {
        $notFound();
    }
    $post = $story['post'];
    $articleRef = pp_ingest_article_ref($siteId, $postId, $refKey);
    $wanted = $isDraft ? 'draft' : 'published';
    if ((string) $post['status'] !== $wanted) {
        pp_ingest_respond(409, ['ok' => false,
            'error' => $isDraft
                ? 'that story is not a draft — review and scheduled stories change at the newsroom\'s desk, published ones through POST /api/ingest/update'
                : 'that story is not published — drafts, review and scheduled stories change at the newsroom\'s desk',
            'status' => (string) $post['status']]);
    }
    // A story carried by a paper outside this key's papers is that paper's
    // page too: changing it is an editor's call.
    $foreignPapers = static function (PDO $pdo) use ($postId, $agent): int {
        $marks = implode(',', array_fill(0, count($agent['sites']), '?'));
        $s = $pdo->prepare("SELECT COUNT(*) FROM post_sites ps JOIN sites st ON st.id = ps.site_id
            WHERE ps.post_id = ? AND st.slug NOT IN ($marks)");
        $s->execute([$postId, ...$agent['sites']]);
        $n = (int) $s->fetchColumn();
        $s->closeCursor();
        return $n;
    };
    if ($foreignPapers($pdo) > 0) {
        pp_ingest_respond(409, ['ok' => false,
            'error' => 'this story is also carried by a paper this key does not cover — changing it there is an editor\'s call']);
    }

    /* --- Fields: only these, and each by its documented rule --------------- */
    $editable = ['title', 'lede', 'body', 'image', 'image_alt', 'image_caption', 'image_credit',
        'tags', 'sources', 'meta_description'];
    if (!$isDraft) {
        $editable[] = 'new_desk';
    }
    $control = $isDraft ? ['site', 'desk', 'if_version'] : ['site', 'desk', 'ref', 'url', 'if_version'];
    $unknown = array_values(array_diff(array_keys($in), $editable, $control));
    if ($unknown) {
        $why = in_array('new_desk', $unknown, true) && $isDraft
            ? ' — a draft\'s desk is the newsroom\'s to change'
            : ' — this surface never publishes, schedules, unpublishes, deletes, renames or moves a story between papers';
        pp_ingest_respond(422, ['ok' => false,
            'error' => 'not editable here: ' . implode(', ', array_map('strval', $unknown)) . $why,
            'editable' => $editable]);
    }
    $bad = static fn (string $msg): never => pp_ingest_respond(422, ['ok' => false, 'error' => $msg]);
    /** A plain-text field: string (or null where clearing is allowed). */
    $text = static function (string $k, int $max, bool $clearable) use ($in, $bad): string {
        $v = $in[$k];
        if ($v === null && $clearable) {
            return '';
        }
        if (!is_string($v)) {
            $bad("$k must be a string" . ($clearable ? ' ("" or null clears it)' : ''));
        }
        $v = trim(strip_tags($v));
        if ((!$clearable && $v === '') || mb_strlen($v) > $max) {
            $bad($clearable ? "$k is at most $max characters" : "$k cannot be empty, at most $max characters");
        }
        return $v;
    };
    $cur = static fn (string $k): string => (string) ($post[$k] ?? '');

    $changes = [];
    if (array_key_exists('title', $in)) {
        $changes['title'] = $text('title', 255, false);
    }
    if (array_key_exists('lede', $in)) {
        $changes['lede'] = $text('lede', 1000, false);
    }
    if (array_key_exists('body', $in)) {
        if (!is_string($in['body'])) {
            $bad('body must be a string of HTML');
        }
        $b = trim(sanitize_html($in['body']));
        if ($b === '' || strlen($b) > 200000) {
            $bad('body cannot be empty, at most 200 KB after sanitization');
        }
        $changes['body'] = $b;
    }
    if (array_key_exists('meta_description', $in)) {
        $changes['meta_description'] = $text('meta_description', 255, true);
    }

    // The cover. alt/caption/credit describe ONE picture: a new picture
    // starts from what this request sends, a removed one takes them along.
    $coverText = ['image_alt' => 255, 'image_caption' => 255, 'image_credit' => 120];
    $sentText = [];
    foreach ($coverText as $k => $max) {
        if (array_key_exists($k, $in)) {
            $sentText[$k] = $text($k, $max, true);
        }
    }
    $image = $cur('image');
    if (array_key_exists('image', $in)) {
        $img = $in['image'];
        if ($img === null || $img === '') {
            $image = '';
        } elseif (!is_string($img) || !preg_match('#^/uploads/\d{4}/\d{2}/[a-z0-9][a-z0-9-]*\.(jpg|jpeg|png|webp|gif)$#', $img)
            || !is_file(PP_ROOT . $img)) {
            $bad('image must be an /uploads/ path returned by /api/ingest-media ("" or null removes the cover)');
        } else {
            $image = $img;
        }
        $changes['image'] = $image;
        if ($image === '') {
            foreach ($sentText as $k => $v) {
                if ($v !== '') {
                    $bad('a removed cover takes its alt text, caption and credit with it — send them empty or not at all');
                }
            }
        }
        if ($image !== $cur('image')) {
            foreach ($coverText as $k => $_) {
                $changes[$k] = $sentText[$k] ?? '';
            }
        } else {
            $changes += $sentText;
        }
    } else {
        if ($image === '' && array_filter($sentText, static fn ($v) => $v !== '')) {
            $bad('this story has no cover — send image (from /api/ingest-media) with its alt text, caption and credit');
        }
        $changes += $sentText;
    }

    $newTags = null;   // list<array{name, slug}> when tags are sent
    if (array_key_exists('tags', $in)) {
        $list = $in['tags'];
        if (is_string($list)) {
            $list = explode(',', $list);
        }
        if (!is_array($list) || !array_is_list($list)) {
            $bad('tags must be a list of names (or one comma-separated string); [] clears them');
        }
        $newTags = [];
        foreach ($list as $i => $name) {
            if (!is_string($name)) {
                $bad("tags[$i] must be a string");
            }
            $name = trim(strip_tags($name));
            if ($name === '') {
                continue;
            }
            if (mb_strlen($name) > 120) {
                $bad("tags[$i] is at most 120 characters");
            }
            $slug = mb_substr(slugify($name), 0, 191);
            if ($slug === '' || isset($newTags[$slug])) {
                continue;   // the same tag twice is one tag
            }
            $newTags[$slug] = ['name' => $name, 'slug' => $slug];
        }
        if (count($newTags) > 10) {
            $bad('at most 10 tags per story');
        }
        $newTags = array_values($newTags);
    }

    $newSources = null;   // list<array{url, title, retrieved_at}> when sent
    if (array_key_exists('sources', $in)) {
        if (!is_array($in['sources']) || !array_is_list($in['sources'])) {
            $bad('sources must be a list of {url, title?} objects; [] clears them');
        }
        if (count($in['sources']) > 20) {
            $bad('at most 20 sources per story');
        }
        $kept = [];
        foreach ($story['sources'] as $s) {
            $kept[$s['url']] ??= $s['retrieved_at'];
        }
        $newSources = [];
        $seen = [];
        foreach ($in['sources'] as $i => $src) {
            if (!is_array($src) || (array_is_list($src) && $src !== [])) {
                $bad("sources[$i] must be an object {url, title?}");
            }
            if ($extra = array_diff(array_keys($src), ['url', 'title', 'retrieved_at'])) {
                $bad("sources[$i] has unknown field(s): " . implode(', ', $extra));
            }
            $u = is_string($src['url'] ?? null) ? trim($src['url']) : '';
            if (!preg_match('#^https?://#i', $u) || strlen($u) > 600) {
                $bad("sources[$i].url must be http(s), at most 600 characters");
            }
            if (isset($seen[$u])) {
                $bad("sources[$i].url repeats sources[{$seen[$u]}]");
            }
            $seen[$u] = $i;
            $t = $src['title'] ?? '';
            if (!is_string($t)) {
                $bad("sources[$i].title must be a string");
            }
            $t = trim(strip_tags($t));
            if (mb_strlen($t) > 255) {
                $bad("sources[$i].title is at most 255 characters");
            }
            $at = $kept[$u] ?? null;
            if (is_string($src['retrieved_at'] ?? null) && ($ts = strtotime($src['retrieved_at'])) !== false) {
                $at = date('Y-m-d H:i:s', $ts);
            }
            $newSources[] = ['url' => $u, 'title' => $t, 'retrieved_at' => $at];
        }
    }

    $newDeskId = null;
    $newDeskSlug = '';
    if (array_key_exists('new_desk', $in)) {
        $newDeskSlug = is_string($in['new_desk']) ? slugify($in['new_desk']) : '';
        if ($newDeskSlug === '' || ($agent['desks'] && !in_array($newDeskSlug, $agent['desks'], true))) {
            $outOfScope();
        }
        $s = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
        $s->execute([$newDeskSlug]);
        $newDeskId = (int) ($s->fetchColumn() ?: 0);
        if ($newDeskId === 0) {
            $bad("unknown desk '{$newDeskSlug}'");
        }
    }

    if (!$changes && $newTags === null && $newSources === null && $newDeskId === null) {
        $bad('nothing to edit: send at least one of ' . implode(', ', $editable));
    }
    $ifVersion = $in['if_version'] ?? null;
    if (!is_string($ifVersion) || !preg_match('/^v_[0-9a-f]{16}$/', $ifVersion)) {
        $bad('if_version is required: the version GET /api/ingest/stories returned for this story (v_ + 16 hex)');
    }

    /* --- What actually differs --------------------------------------------- */
    $diff = array_filter($changes, static fn ($v, $k) => $v !== $cur($k), ARRAY_FILTER_USE_BOTH);
    $tagsDiffer = false;
    if ($newTags !== null) {
        $a = array_column($newTags, 'slug');
        $b = $story['tag_slugs'];
        sort($a);
        sort($b);
        $tagsDiffer = $a !== $b;
    }
    $sourcesDiffer = $newSources !== null
        && array_map(static fn ($x) => [$x['url'], $x['title']], $newSources)
            !== array_map(static fn ($x) => [$x['url'], $x['title']], $story['sources']);
    $deskDiffers = $newDeskId !== null && $newDeskId !== (int) $post['category_id'];

    $answer = static function (array $loaded, array $extra = []) use ($articleRef, $siteSlug, $isDraft): array {
        $p = $loaded['post'];
        $out = ['ok' => true] + $extra + ['ref' => $articleRef, 'status' => (string) $p['status'],
            'desk' => (string) ($p['desk'] ?? '')];
        if ((string) $p['status'] === 'published') {
            $out['url'] = pp_story_public_url($siteSlug, (string) $p['slug']);
        }
        if ($isDraft && (int) ($p['awaiting_image'] ?? 0) === 1) {
            $out['awaiting_image'] = true;   // reported, never changed here
        }
        return $out;
    };
    if (!$diff && !$tagsDiffer && !$sourcesDiffer && !$deskDiffers) {
        // Every field already holds what was asked: nothing is written, no
        // history row, no audit row — whatever version the request carried.
        // This is the answer a retried mutation gets once it has landed.
        pp_ingest_respond(200, $answer($story, ['unchanged' => true]) + ['version' => $story['version']]);
    }
    if (!hash_equals($story['version'], $ifVersion)) {
        // No version in this answer, on purpose: the next one comes from
        // the reader, so an agent sees what changed before it edits again
        // instead of resending blind over an editor's save.
        pp_ingest_respond(409, ['ok' => false,
            'error' => 'the story changed since your version — nothing was written; read it again (GET /api/ingest/stories) and resend',
            'status' => (string) $post['status']]);
    }

    /* --- Rate limit: edits per token per hour, from the audit trail -------- */
    $limit = 60;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM audit_log WHERE user_name = ? AND action = ? AND created_at > ?');
    $stmt->execute([mb_substr('hermes:' . $agent['name'], 0, 120), $action, date('Y-m-d H:i:s', time() - 3600)]);
    if ((int) $stmt->fetchColumn() >= $limit) {
        pp_ingest_respond(429, ['ok' => false, 'error' => "rate limit: at most {$limit} " . ($isDraft ? 'draft edits' : 'updates') . ' per hour for this token']);
    }

    /* --- The write: story, tags, sources, revisions and audit, together ---- */
    $by = 'hermes:' . $agent['name'];
    $now = now();
    $fields = array_keys($diff);
    if ($tagsDiffer) {
        $fields[] = 'tags';
    }
    if ($sourcesDiffer) {
        $fields[] = 'sources';
    }
    if ($deskDiffers) {
        $fields[] = 'desk';
    }
    $inFlight = static fn (): never => pp_ingest_respond(409, ['ok' => false,
        'error' => 'the story changed while this edit was in flight (an editor saved, moved or ' . ($isDraft ? 'published' : 'unpublished')
            . ' it) — nothing was written; read it again and resend']);
    try {
        pp_ingest_edit_begin($pdo, $postId);
        // Re-read under the lock: the version, the status, the desk and the
        // papers must all still be what the checks above saw.
        $locked = pp_ingest_edit_load($pdo, $postId);
        $stillHere = $pdo->prepare('SELECT COUNT(*) FROM post_sites WHERE post_id = ? AND site_id = ?');
        $stillHere->execute([$postId, $siteId]);
        $onPaper = (int) $stillHere->fetchColumn() > 0;
        $stillHere->closeCursor();
        if ($locked === null || !hash_equals($locked['version'], $ifVersion) || !$onPaper
            || (string) $locked['post']['status'] !== $wanted || (int) $locked['post']['category_id'] !== $deskId
            || $foreignPapers($pdo) > 0) {
            pp_ingest_edit_rollback($pdo);
            $inFlight();
        }

        // History holds the state before the change: a story an agent or an
        // editor made may have no revision that says exactly it yet.
        $last = $pdo->prepare('SELECT title, lede, body, meta_description, correction, image, image_caption, image_credit, image_alt
            FROM post_revisions WHERE post_id = ? ORDER BY id DESC LIMIT 1');
        $last->execute([$postId]);
        $lr = $last->fetch();
        $last->closeCursor();
        $same = $lr !== false;
        foreach (['title', 'lede', 'body', 'meta_description', 'correction', 'image', 'image_caption', 'image_credit', 'image_alt'] as $k) {
            $same = $same && (string) ($lr[$k] ?? '') === $cur($k);
        }
        if (!$same) {
            pp_ingest_edit_revision($pdo, $postId, $isDraft ? 'before agent draft edit' : 'before agent update', '');
        }

        $set = [];
        $args = [];
        foreach ($diff as $k => $v) {
            $set[] = "$k = ?";
            $args[] = $v;
        }
        if ($deskDiffers) {
            $set[] = 'category_id = ?';
            $args[] = $newDeskId;
        }
        $set[] = 'updated_at = ?';
        $args[] = $now;
        $upd = $pdo->prepare('UPDATE posts SET ' . implode(', ', $set)
            . ' WHERE id = ? AND status = ? AND category_id = ? AND updated_at = ?'
            // The paper, in the write itself: the story must still be on it.
            . ' AND EXISTS (SELECT 1 FROM post_sites ps WHERE ps.post_id = posts.id AND ps.site_id = ?)');
        $upd->execute([...$args, $postId, $wanted, $deskId, (string) $post['updated_at'], $siteId]);
        if ($upd->rowCount() !== 1) {
            pp_ingest_edit_rollback($pdo);
            $inFlight();
        }

        if ($tagsDiffer) {
            $pdo->prepare('DELETE FROM post_tags WHERE post_id = ?')->execute([$postId]);
            $sel = $pdo->prepare('SELECT id FROM tags WHERE slug = ?');
            $insTag = $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');
            $insPT = $pdo->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)');
            foreach ($newTags as $t) {
                $sel->execute([$t['slug']]);
                $tagId = (int) ($sel->fetchColumn() ?: 0);
                $sel->closeCursor();
                if ($tagId === 0) {
                    $insTag->execute([$t['name'], $t['slug']]);
                    $tagId = pp_last_id('tags');
                }
                $insPT->execute([$postId, $tagId]);
            }
        }
        if ($sourcesDiffer) {
            $pdo->prepare('DELETE FROM story_sources WHERE post_id = ?')->execute([$postId]);
            $ins = $pdo->prepare('INSERT INTO story_sources (post_id, url, title, retrieved_at, created_at) VALUES (?, ?, ?, ?, ?)');
            foreach ($newSources as $s) {
                $ins->execute([$postId, $s['url'], $s['title'], $s['retrieved_at'], $now]);
            }
        }

        pp_ingest_edit_revision($pdo, $postId, $isDraft ? 'agent draft edit' : 'agent update', $by);

        $detail = ($isDraft ? 'draft edited: ' : 'published story updated: ') . implode(', ', $fields);
        if (isset($diff['image'])) {
            $detail .= ' (image ' . ($cur('image') !== '' ? $cur('image') : 'none') . ' → ' . ($diff['image'] !== '' ? $diff['image'] : 'none') . ')';
        }
        if ($deskDiffers) {
            $detail .= " (desk {$deskSlug} → {$newDeskSlug})";
        }
        if ($tagsDiffer) {
            $detail .= ' (tags were: ' . ($story['tags'] ? implode(', ', $story['tags']) : 'none') . ')';
        }
        if ($sourcesDiffer) {
            $detail .= ' (sources were: ' . ($story['sources'] ? implode(' ', array_column($story['sources'], 'url')) : 'none') . ')';
        }
        $slug = (string) $post['slug'];
        if (mb_strlen($slug) > 160) {
            $detail .= ' [target cut to 160 characters; post #' . $postId . ']';
        }
        $pdo->prepare('INSERT INTO audit_log (site_id, user_id, user_name, action, target, detail, ip, created_at)
            VALUES (?, 0, ?, ?, ?, ?, ?, ?)')
            ->execute([$siteId, mb_substr($by, 0, 120), $action, mb_substr($slug, 0, 160), $detail,
                mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64), $now]);
        $pdo->prepare('UPDATE ingest_agents SET last_used_at = ? WHERE id = ?')->execute([$now, (int) $agent['id']]);

        $after = pp_ingest_edit_load($pdo, $postId);
        pp_ingest_edit_commit($pdo);
    } catch (Throwable $e) {
        pp_ingest_edit_rollback($pdo);
        error_log('prairiepost: ' . $action . ' of post #' . $postId . ' rolled back: ' . $e->getMessage());
        if ($e instanceof PDOException
            && (str_starts_with((string) $e->getCode(), '23') || str_starts_with((string) $e->getCode(), '40'))) {
            // An integrity or serialization collision with a concurrent write.
            pp_ingest_respond(409, ['ok' => false,
                'error' => 'a concurrent change collided with this edit — nothing was written; read the story again and resend']);
        }
        pp_ingest_respond(500, ['ok' => false, 'error' => 'the edit could not be saved — nothing was written']);
    }

    $extra = ['updated' => $fields];
    $out = $answer($after, $extra) + ['version' => $after['version']];
    if (isset($diff['image'])) {
        $out['previous_image'] = $cur('image');
    }
    pp_ingest_respond(200, $out);
}
