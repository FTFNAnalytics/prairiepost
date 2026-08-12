<?php
/** The Prairie Dispatch — feed aggregation, shared by cron and the sources admin. */

/** Fetch one source; returns [added_count, error|null]. Updates source status. */
function pp_fetch_source(PDO $db, array $source): array
{
    [$body, $err] = http_get($source['url']);
    if ($err !== null) {
        $db->prepare('UPDATE sources SET last_fetched_at = ?, last_status = ? WHERE id = ?')
           ->execute([now(), 'error: ' . $err, $source['id']]);
        return [0, $err];
    }

    [$items, $parseErr] = parse_feed($body);
    if ($parseErr !== null) {
        $db->prepare('UPDATE sources SET last_fetched_at = ?, last_status = ? WHERE id = ?')
           ->execute([now(), 'error: ' . $parseErr, $source['id']]);
        return [0, $parseErr];
    }

    $added = 0;
    $insert = $db->prepare('INSERT INTO news_items (source_id, region, title, url, url_hash, summary, published_at, fetched_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $exists = $db->prepare('SELECT id FROM news_items WHERE url_hash = ?');

    foreach (array_slice($items, 0, 30) as $item) {
        $hash = sha1($item['url']);
        $exists->execute([$hash]);
        if ($exists->fetch()) {
            continue;
        }
        $insert->execute([
            $source['id'], $source['region'],
            mb_substr($item['title'], 0, 500), mb_substr($item['url'], 0, 600),
            $hash, $item['summary'], $item['published_at'], now(),
        ]);
        $added++;
    }

    $db->prepare('UPDATE sources SET last_fetched_at = ?, last_status = ? WHERE id = ?')
       ->execute([now(), 'ok: ' . count($items) . ' items, ' . $added . ' new', $source['id']]);
    return [$added, null];
}

/** Fetch every enabled source. Returns a per-source report. */
function pp_fetch_all(PDO $db): array
{
    $report = [];
    $sources = $db->query('SELECT * FROM sources WHERE enabled = 1 ORDER BY region, name')->fetchAll();
    foreach ($sources as $source) {
        [$added, $err] = pp_fetch_source($db, $source);
        $report[] = ['name' => $source['name'], 'added' => $added, 'error' => $err];
    }
    return $report;
}

/* --- The aggregator: paste a link, get a prefilled wire post -------------- */

/**
 * Fetch a page and read its Open Graph / Twitter card metadata.
 * Returns [title, description, image, site_name, published_at, host, error];
 * on error everything else is best-effort empty.
 */
function pp_fetch_link_meta(string $url): array
{
    $meta = ['title' => '', 'description' => '', 'image' => '', 'site_name' => '',
             'published_at' => null, 'host' => '', 'error' => null];

    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
        $meta['error'] = 'That doesn\'t look like a full http(s) link.';
        return $meta;
    }
    $meta['host'] = preg_replace('/^www\./', '', parse_url($url, PHP_URL_HOST) ?: '');

    [$body, $err] = http_get($url);
    if ($err !== null) {
        $meta['error'] = 'The page couldn\'t be fetched (' . $err . '). You can still fill the fields in by hand.';
        return $meta;
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    // The XML prologue pins the parser to UTF-8 regardless of the page's own headers.
    if (!@$doc->loadHTML('<?xml encoding="utf-8"?>' . $body)) {
        $meta['error'] = 'The page fetched but couldn\'t be parsed. Fill the fields in by hand.';
        return $meta;
    }

    $tags = [];
    foreach ($doc->getElementsByTagName('meta') as $m) {
        $key = strtolower($m->getAttribute('property') ?: $m->getAttribute('name'));
        $val = trim($m->getAttribute('content'));
        if ($key !== '' && $val !== '' && !isset($tags[$key])) {
            $tags[$key] = $val;
        }
    }

    $meta['title'] = $tags['og:title'] ?? $tags['twitter:title'] ?? '';
    if ($meta['title'] === '') {
        $titles = $doc->getElementsByTagName('title');
        $meta['title'] = $titles->length ? trim($titles->item(0)->textContent) : '';
    }
    $meta['description'] = $tags['og:description'] ?? $tags['twitter:description'] ?? $tags['description'] ?? '';
    $meta['site_name']   = $tags['og:site_name'] ?? '';
    $meta['image']       = $tags['og:image'] ?? $tags['og:image:url'] ?? $tags['twitter:image'] ?? '';

    // A relative image path resolves against the article's own URL.
    if ($meta['image'] !== '' && !preg_match('#^https?://#i', $meta['image'])) {
        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $meta['image'] = str_starts_with($meta['image'], '//')
            ? $scheme . ':' . $meta['image']
            : $scheme . '://' . $host . '/' . ltrim($meta['image'], '/');
    }

    $when = $tags['article:published_time'] ?? $tags['og:article:published_time'] ?? '';
    if ($when !== '' && ($ts = strtotime($when))) {
        $meta['published_at'] = date('Y-m-d H:i:s', $ts);
    }

    $meta['title'] = mb_substr(html_entity_decode($meta['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 255);
    $meta['description'] = mb_substr(html_entity_decode($meta['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 500);
    $meta['site_name'] = mb_substr(html_entity_decode($meta['site_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 160);
    return $meta;
}

/**
 * Download a remote image into /uploads/ so wire cards never break when the
 * source site moves or resizes theirs. Returns [public_path|null, error|null].
 */
function pp_cache_remote_image(string $url): array
{
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
        return [null, 'not a valid image URL'];
    }
    [$body, $err] = http_get($url, 20);
    if ($err !== null) {
        return [null, $err];
    }
    if (strlen($body) > 8 * 1024 * 1024) {
        return [null, 'the image is over 8 MB'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($body);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!isset($extensions[$mime])) {
        return [null, 'not a JPEG, PNG, WebP or GIF (' . $mime . ')'];
    }
    $dir = PP_ROOT . '/uploads/' . date('Y/m');
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        return [null, 'the uploads folder isn\'t writable'];
    }
    $name = 'wire-' . substr(bin2hex(random_bytes(5)), 0, 8) . '.' . $extensions[$mime];
    if (file_put_contents($dir . '/' . $name, $body) === false) {
        return [null, 'the server couldn\'t write the file'];
    }
    return ['/uploads/' . date('Y/m') . '/' . $name, null];
}

/** Drop unused wire items older than 14 days so the pull stays current. */
function pp_prune_news(PDO $db): int
{
    $stmt = $db->prepare('DELETE FROM news_items WHERE used = 0 AND fetched_at < ?');
    $stmt->execute([date('Y-m-d H:i:s', strtotime('-14 days'))]);
    return $stmt->rowCount();
}

/** Flip scheduled posts live once their time arrives. */
function pp_publish_due(PDO $db): int
{
    $stmt = $db->prepare("SELECT id FROM posts WHERE status = 'scheduled' AND published_at <= ?");
    $stmt->execute([now()]);
    $due = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$due) {
        return 0;
    }
    $marks = implode(',', array_fill(0, count($due), '?'));
    $db->prepare("UPDATE posts SET status = 'published' WHERE id IN ($marks)")->execute($due);
    // The agent desk's auto-queue fires on the publish transition, however
    // it happens — the hub's per-kind settings decide, off by default.
    require_once PP_ROOT . '/app/agents.php';
    foreach ($due as $postId) {
        pp_agent_auto_enqueue((int) $postId);
    }
    return count($due);
}
