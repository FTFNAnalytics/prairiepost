<?php
/** The Prairie Dispatch — shared helpers. */

require_once __DIR__ . '/imagemeta.php';   // every stored image goes through pp_image_clean()

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($t !== false) {
            $text = $t;
        }
    }
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'story';
}

/* --- CSRF -------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(20));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(403);
        exit('The form token expired. Go back, reload the page, and try again.');
    }
}

/* --- Auth --------------------------------------------------------------- */

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $uid = $_SESSION['uid'] ?? 0;
        $user = $uid ? user_by_id((int) $uid) : null;
    }
    return $user;
}

/** Mark a fresh sign-in: when it happened, and under which epoch. */
function pp_session_stamp(int $epoch): void
{
    $_SESSION['auth_at'] = time();
    $_SESSION['last_seen'] = time();
    $_SESSION['epoch'] = $epoch;
}

/** Destroy the session and return to sign-in with a gentle note. */
function pp_session_end(): never
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    redirect('/admin/login.php?expired=1');
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('/admin/login.php');
    }

    // Session lifecycle — a stolen cookie must not outlive its welcome.
    // Epoch: bumping users.session_epoch (sign out everywhere, passphrase
    // change, admin revocation) orphans every session stamped with the old
    // number. Idle and absolute limits are settings; 0 disables either.
    $now = time();
    if ((int) ($_SESSION['epoch'] ?? -1) !== (int) ($user['session_epoch'] ?? 0)) {
        pp_session_end();
    }
    $idleHours = (int) (setting('session_idle_hours', '12') ?: 0);
    if ($idleHours > 0 && (int) ($_SESSION['last_seen'] ?? $now) < $now - $idleHours * 3600) {
        pp_session_end();
    }
    $maxDays = (int) (setting('session_max_days', '14') ?: 0);
    if ($maxDays > 0 && (int) ($_SESSION['auth_at'] ?? 0) < $now - $maxDays * 86400) {
        pp_session_end();
    }
    $_SESSION['last_seen'] = $now;

    // The hub's optional address fence. Papers never evaluate it, so a
    // mistyped range can always be repaired from a paper's shared settings.
    if (pp_is_hub() && !pp_ip_allowlisted()) {
        http_response_code(403);
        exit('The control room is limited to approved addresses. Yours isn\'t on the list — a network administrator can add it under Settings → Security.');
    }

    // Tenant-scoped accounts (those with explicit site_memberships rows and
    // is_network_admin = 0) can only enter papers they are assigned to.
    if (pp_user_site_role($user) === null) {
        http_response_code(403);
        exit('Your account does not have access to this paper.');
    }

    return $user;
}

/**
 * Network administrators hold full access across every paper and the hub.
 * Legacy admin accounts created without site_memberships rows continue to
 * act as network admins until explicitly scoped.
 */
function pp_is_network_admin(?array $user): bool
{
    if ($user === null) {
        return false;
    }
    if (!empty($user['is_network_admin'])) {
        return true;
    }
    return ($user['role'] ?? '') === 'admin' && pp_user_memberships((int) ($user['id'] ?? 0)) === [];
}

/**
 * Effective role for $user on $siteId (defaults to the current site):
 * 'admin', 'editor', 'author', or null when a tenant-scoped account has no
 * membership on that site.
 */
function pp_user_site_role(?array $user, ?int $siteId = null): ?string
{
    if ($user === null) {
        return null;
    }
    if (pp_is_network_admin($user)) {
        return 'admin';
    }
    $memberships = pp_user_memberships((int) ($user['id'] ?? 0));
    if ($memberships === []) {
        return (string) ($user['role'] ?? 'author');
    }
    $sid = $siteId ?? current_site_id();
    return isset($memberships[$sid]) ? (string) $memberships[$sid]['role'] : null;
}

function pp_is_site_admin(?array $user, ?int $siteId = null): bool
{
    return pp_user_site_role($user, $siteId) === 'admin';
}

/** Whether $user may publish or schedule stories on $siteId (defaults to current site). */
function pp_can_publish(?array $user, ?int $siteId = null): bool
{
    if ($user === null) {
        return false;
    }
    if (pp_is_network_admin($user)) {
        return true;
    }
    $memberships = pp_user_memberships((int) ($user['id'] ?? 0));
    if ($memberships === []) {
        return in_array((string) ($user['role'] ?? ''), ['admin', 'editor'], true);
    }
    $sid = $siteId ?? current_site_id();
    return isset($memberships[$sid]) && (int) ($memberships[$sid]['can_publish'] ?? 0) === 1;
}

function require_admin(): array
{
    $user = require_login();
    if (!pp_is_site_admin($user)) {
        http_response_code(403);
        exit('That page needs an administrator account.');
    }
    return $user;
}

function require_network_admin(): array
{
    $user = require_login();
    if (!pp_is_network_admin($user)) {
        http_response_code(403);
        exit('That page needs a network administrator account.');
    }
    return $user;
}

/** Editors and admins run the paper; authors write for it. */
function is_editor(?array $user, ?int $siteId = null): bool
{
    return in_array(pp_user_site_role($user, $siteId), ['admin', 'editor'], true);
}

function require_editor(): array
{
    $user = require_login();
    if (!is_editor($user)) {
        http_response_code(403);
        exit('That page needs an editor or administrator account.');
    }
    return $user;
}

/**
 * Whether $user has editor/admin rights on at least one site where $post runs
 * (or on the current site for an unmapped draft).
 */
function pp_is_editor_for_post(array $user, array $post): bool
{
    $allowedSites = pp_user_allowed_site_ids($user);
    if ($allowedSites === null) {
        return is_editor($user);
    }
    $postSites = !empty($post['id']) ? site_ids_for_post((int) $post['id']) : [];
    if ($postSites === []) {
        return is_editor($user);
    }
    foreach (array_intersect($postSites, $allowedSites) as $sid) {
        if (is_editor($user, (int) $sid)) {
            return true;
        }
    }
    return false;
}

/** Authors may open only their own stories; editors and admins open any on their papers. */
function can_edit_post(array $user, array $post): bool
{
    $allowedSites = pp_user_allowed_site_ids($user);
    if ($allowedSites !== null) {
        $postSites = !empty($post['id']) ? site_ids_for_post((int) $post['id']) : [];
        if ($postSites !== [] && array_intersect($postSites, $allowedSites) === []) {
            return false;
        }
    }
    return pp_is_editor_for_post($user, $post) || (int) ($post['author_id'] ?? 0) === (int) $user['id'];
}

/** A post the approval gate has signed off on: live now, or queued to go live. */
function pp_post_locked(array $post): bool
{
    return in_array((string) ($post['status'] ?? ''), ['published', 'scheduled'], true);
}

/**
 * May this user WRITE to this post (edit, autosave, restore, delete)?
 * The one policy every mutating endpoint asks, against the post's
 * PERSISTED state — not the state the form was rendered from.
 *
 * Editors and admins: anything on their assigned papers. Authors: their own
 * stories only, and only while the story is still theirs to shape (draft / in
 * review). Once an editor has published or scheduled it, changing it — by edit,
 * restore, autosave or delete — is the approval gate's job, so the author is
 * refused and told why. Returns null when allowed, the refusal otherwise.
 */
function pp_post_write_denied(array $user, array $post, string $action = 'edit'): ?string
{
    $allowedSites = pp_user_allowed_site_ids($user);
    if ($allowedSites !== null) {
        $postSites = !empty($post['id']) ? site_ids_for_post((int) $post['id']) : [];
        if ($postSites !== [] && array_intersect($postSites, $allowedSites) === []) {
            return 'That story belongs to another paper.';
        }
    }
    if (pp_is_editor_for_post($user, $post)) {
        return null;
    }
    if ((int) ($post['author_id'] ?? 0) !== (int) $user['id']) {
        return 'That story belongs to another author.';
    }
    if (pp_post_locked($post)) {
        return 'This story is ' . $post['status'] . ' — the live version only changes at an editor\'s desk. '
             . 'Ask an editor to ' . ($action === 'restore' ? 'restore it' : 'take your changes') . ', or to send it back to draft first.';
    }
    return null;
}

/* --- Text --------------------------------------------------------------- */

function excerpt(string $html, int $chars = 180): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    if (mb_strlen($text) <= $chars) {
        return $text;
    }
    $cut = mb_substr($text, 0, $chars);
    $space = mb_strrpos($cut, ' ');
    return ($space ? mb_substr($cut, 0, $space) : $cut) . '…';
}

/**
 * Allowlist-sanitize stored article HTML before rendering or saving.
 * Parser-based (HTML Purifier): the document is decoded and rebuilt the
 * way a browser reads it, so entity-encoded schemes, event handlers and
 * malformed markup cannot survive. The allowlist lives in app/security.php.
 */
function sanitize_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    return pp_purifier()->purify($html);
}

/* --- Search-engine indexing (per site, default OFF) ---------------------- */

/**
 * May search engines index THIS paper? Off unless the site's own
 * `indexing_enabled` setting is exactly '1' — a missing value means no:
 * a hostname existing is not approval to index it, and an unfinished
 * paper must never leak into results because someone forgot a setting.
 * Site-scoped by construction (settings are per-site rows).
 */
function pp_indexing_enabled(): bool
{
    return setting('indexing_enabled', '0') === '1';
}

/**
 * Send the robots response header for non-HTML public representations
 * (feeds, sitemaps, social cards) when this site isn't cleared to index.
 * The header travels with the response, so crawlers can fetch the URL and
 * still read the directive — which is why robots.txt Disallow is NOT used:
 * it would stop crawlers from ever seeing the noindex.
 */
function pp_robots_header(): void
{
    if (!pp_indexing_enabled()) {
        header('X-Robots-Tag: noindex, nofollow');
    }
}

function fmt_date(?string $dt, string $format = 'M j, Y'): string
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    return $ts ? pp_date($format, $ts) : '';
}

/** Default publication byline when no named reporter is credited. */
function pp_default_byline(): string
{
    return pp_t('Newsroom Staff');
}

/**
 * Normalize a story's byline for publication: replace any legacy
 * "Automated report" / "Rapport automatisé" label with the default
 * Newsroom Staff byline.
 */
function pp_normalize_byline(?string $byline, string $postType = 'story'): string
{
    $clean = trim((string) $byline);
    if ($clean !== '' && preg_match('/^(automated report|rapport automatis[eé])$/iu', $clean) === 1) {
        return pp_default_byline();
    }
    if ($clean === '' && $postType !== 'link') {
        return pp_default_byline();
    }
    return $clean;
}

/**
 * Schema.org author object for NewsArticle JSON-LD.
 * Emits @type: Organization for newsroom, staff, desk, or masthead bylines
 * and @type: Person only for named human authors.
 */
function pp_jsonld_author(array $post, string $siteTitle): array
{
    $byline = pp_normalize_byline((string) ($post['byline'] ?? ''), (string) ($post['post_type'] ?? 'story'));
    $authorSlug = trim((string) ($post['author_slug'] ?? ''));
    $autoByline = trim((string) (pp_brand_file()['automated_byline'] ?? ''));

    $isOrg = $byline === '';
    if (!$isOrg && $authorSlug === '') {
        $norm = mb_strtolower($byline);
        $siteNorm = mb_strtolower(trim($siteTitle));
        $autoNorm = mb_strtolower($autoByline);
        if (
            $norm === $siteNorm
            || ($autoNorm !== '' && $norm === $autoNorm)
            || preg_match('/^(newsroom staff|staff|newsroom|editorial board|la r[eé]daction|wire|news desk|staff reporter|local journalism|civis media)(\b|$)/iu', $byline) === 1
            || preg_match('/\b(newsroom|staff|desk|editorial|bureau|wire)\b/iu', $byline) === 1
        ) {
            $isOrg = true;
        }
    }

    if ($isOrg) {
        return [
            '@type' => 'Organization',
            'name'  => $byline !== '' ? $byline : pp_default_byline(),
            'url'   => site_url(),
        ];
    }

    return array_filter([
        '@type' => 'Person',
        'name'  => $byline,
        'url'   => $authorSlug !== '' ? site_url() . '/author/' . $authorSlug : null,
    ]);
}

/**
 * Reading time in whole minutes, at 200 words a minute, rounded up.
 * Counts the lede with the body so a short piece isn't reported as zero.
 */
function read_minutes(array $post): int
{
    $text = strip_tags((string) ($post['lede'] ?? '') . ' ' . (string) ($post['body'] ?? ''));
    $words = str_word_count($text);
    return max(1, (int) ceil($words / 200));
}

/** "6:40 a.m." / "Yesterday" / "Jul 3" — the register of a dateline, not a widget. */
function time_label(?string $dt, ?int $now = null): string
{
    $now ??= time();
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    if (!$ts) {
        return '';
    }
    // Today and yesterday are the paper's, like the clock.
    $day = pp_date('Y-m-d', $ts);
    if ($day === pp_date('Y-m-d', $now)) {
        return strtolower(str_replace(['AM', 'PM'], ['a.m.', 'p.m.'], pp_date('g:i A', $ts)));
    }
    if ($day === pp_date('Y-m-d', $now - 86400)) {
        return 'Yesterday';
    }
    return pp_date('M j', $ts);
}

/** Full dateline: "THREE HILLS — By Dana Ruthven · 6:40 a.m." */
function dateline(array $post): string
{
    $parts = [];
    // A wire link credits the outlet that reported it, never a house byline.
    if (($post['post_type'] ?? '') === 'link' && !empty($post['source_url'])) {
        $label = (string) ($post['source_name'] ?? '');
        if ($label === '') {
            $label = preg_replace('/^www\./', '', parse_url((string) $post['source_url'], PHP_URL_HOST) ?: '');
        }
        if ($label !== '') {
            $parts[] = $label;
        }
        if (!empty($post['published_at'])) {
            $parts[] = time_label($post['published_at']);
        }
        return implode(' · ', array_map('e', $parts));
    }
    if (!empty($post['dateline'])) {
        $parts[] = mb_strtoupper($post['dateline']);
    }
    if (!empty($post['byline'])) {
        $parts[] = 'By ' . $post['byline'];
    }
    if (!empty($post['published_at'])) {
        $parts[] = time_label($post['published_at']);
    }
    return implode(' · ', array_map('e', $parts));
}

/* --- Per-site branding ---------------------------------------------------- */

/**
 * URL for a brand asset, preferring the current site's own version.
 * A site drops overrides into assets/sites/<slug>/ (logo-primary.svg,
 * logo-reversed.svg, logo-stacked.svg, favicon.svg, og-default.png,
 * brand.css); anything missing falls back to the network default.
 *
 * That fallback is the flagship's art. Publisher logos, default share
 * images and the icon set must not use it for any other paper — those
 * go through pp_owned_asset() in app/icons.php.
 */
function site_asset(string $file): string
{
    $slug = current_site()['slug'] ?? '';
    if ($slug !== '' && is_file(PP_ROOT . '/assets/sites/' . $slug . '/' . $file)) {
        return '/assets/sites/' . $slug . '/' . $file;
    }
    return '/assets/img/' . $file;
}

/** Filesystem path variant of site_asset(). */
function site_asset_path(string $file): string
{
    return PP_ROOT . site_asset($file);
}

/**
 * The site's palette for generated media (social cards, the newsletter).
 * Defaults are the prairie system; a site overrides any subset with the
 * brand_palette setting (JSON object of the keys below).
 */
function pp_brand_palette(): array
{
    static $palette = null;
    if ($palette === null) {
        $hex = fn ($v) => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v);
        $palette = array_merge([
            'ink'     => '#17301C',   // primary ink, the rule
            'paper'   => '#F1F2F0',   // page ground
            'board'   => '#C4C0B4',   // hairlines
            'sky'     => '#77B2D6',   // fills
            'hill'    => '#3F5A22',   // band 2 / positive numbers
            'field'   => '#58651C',   // band 4
            'stubble' => '#7A661F',   // band 5
            'red'     => '#9C3B22',   // signal
            'muted'   => '#5A6A5C',   // secondary text
        ], array_filter(pp_brand_file()['palette'] ?? [], $hex),
           array_filter(setting_json('brand_palette'), $hex));
    }
    return $palette;
}

/** The site's committed brand file (assets/sites/<slug>/palette.json), if any. */
function pp_brand_file(): array
{
    static $brand = null;
    if ($brand === null) {
        $brand = [];
        $slug = current_site()['slug'] ?? '';
        $file = PP_ROOT . '/assets/sites/' . $slug . '/palette.json';
        if ($slug !== '' && is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $brand = $decoded;
            }
        }
    }
    return $brand;
}

/**
 * The accent colour a desk carries on THIS site. Desks (categories) are
 * shared across the network, but each paper may recolour them in its
 * palette.json under "desks": {"politics": "#20618F", …}.
 */
function pp_desk_hex(?string $categorySlug, ?string $default): string
{
    $default = $default ?: '#17301C';
    if (!$categorySlug) {
        return $default;
    }
    $override = pp_brand_file()['desks'][$categorySlug] ?? null;
    return (is_string($override) && preg_match('/^#[0-9A-Fa-f]{6}$/', $override)) ? $override : $default;
}

/**
 * The name a desk carries on THIS site. Desks are shared network-wide, so a
 * paper that calls the shared "Business & Markets" desk simply "Business"
 * renames it for itself in palette.json under
 * "desk_labels": {"business": "Business", …}. The stored category name is
 * never touched — changing it would rename the desk for every other paper.
 */
function pp_desk_label(?string $categorySlug, ?string $default): string
{
    $default = (string) $default;
    if (!$categorySlug) {
        return $default;
    }
    $override = pp_brand_file()['desk_labels'][$categorySlug] ?? null;
    return (is_string($override) && $override !== '') ? $override : $default;
}

/* --- Uploads ------------------------------------------------------------- */

/**
 * Validate and store an uploaded image. Returns [public_url|null, error|null].
 * Shared by the editor's upload endpoint, the advertising desks and the
 * profile page. What is stored is the image without its embedded metadata
 * (GPS location, camera, time — app/imagemeta.php), never the bytes as
 * they arrived.
 */
function pp_handle_image_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [null, 'The upload failed (code ' . (int) ($file['error'] ?? -1) . '). Try a smaller file.'];
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        return [null, 'That file is over 8 MB. Resize it and try again.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [null, 'The upload failed. Try again.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!isset($extensions[$mime])) {
        return [null, 'Only JPEG, PNG, WebP or GIF images can be uploaded.'];
    }
    [$clean, , $cleanErr] = pp_image_clean((string) file_get_contents($file['tmp_name']), $mime);
    if ($clean === null) {
        return [null, ucfirst($cleanErr) . '.'];
    }
    $dir = PP_ROOT . '/uploads/' . date('Y/m');
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $base = slugify(pathinfo($file['name'], PATHINFO_FILENAME)) ?: 'image';
    $name = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $extensions[$mime];
    if (file_put_contents($dir . '/' . $name, $clean) === false) {
        return [null, "The server couldn't write the file. Check that /uploads/ is writable."];
    }
    return ['/uploads/' . date('Y/m') . '/' . $name, null];
}

/**
 * Store image BYTES the server fetched itself (the ingest pipeline's image
 * field) under the same rules as a browser upload: 8 MB cap, MIME sniffed
 * from the bytes, the same four formats, the same /uploads/YYYY/MM home,
 * and the embedded metadata stripped before anything is written.
 * Returns [public path, null] or [null, reason].
 */
function pp_store_image_bytes(string $bytes, string $baseName): array
{
    if ($bytes === '') {
        return [null, 'the image was empty'];
    }
    if (strlen($bytes) > 8 * 1024 * 1024) {
        return [null, 'the image is over 8 MB'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($bytes);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!isset($extensions[$mime])) {
        return [null, 'only JPEG, PNG, WebP or GIF images are accepted (got ' . $mime . ')'];
    }
    // The content-type sniff says what the bytes claim to be; this says the
    // bytes actually decode as an image of that kind.
    if (@getimagesizefromstring($bytes) === false) {
        return [null, 'the file does not decode as an image'];
    }
    [$clean, , $cleanErr] = pp_image_clean($bytes, $mime);
    if ($clean === null) {
        return [null, $cleanErr];
    }
    $dir = PP_ROOT . '/uploads/' . date('Y/m');
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $base = slugify($baseName) ?: 'image';
    $name = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $extensions[$mime];
    if (file_put_contents($dir . '/' . $name, $clean) === false) {
        return [null, 'the server could not write the file'];
    }
    return ['/uploads/' . date('Y/m') . '/' . $name, null];
}

/**
 * May the server fetch this URL on someone else's say-so? The ingest
 * image field hands the box a URL to download, which is a request the
 * token holder writes and the server executes — so it must not be able to
 * point inward. http(s) only, and the host must not resolve to loopback,
 * private, link-local or otherwise non-public address space.
 */
function pp_url_is_public(string $url): bool
{
    [$p, $err] = pp_http_check_url($url);
    if ($err !== null) {
        return false;
    }
    [, $resolveErr] = pp_http_resolve((string) $p['host']);
    return $resolveErr === null;
}

/* --- Feeds (shared by cron and the sources admin) ----------------------- */

/**
 * Fetch a URL with a short timeout; returns [body, error]. Every caller
 * of this function handles URLs someone else wrote (feed rows, pasted
 * links, ingest payloads), so it IS the safe untrusted-URL transport:
 * destination policy, per-hop redirect validation, address pinning and
 * a streamed size cap all live in app/transport.php.
 */
function http_get(string $url, int $timeout = 12): array
{
    return pp_http_get($url, ['timeout' => $timeout]);
}

/**
 * Parse an RSS 2.0 or Atom document into [[title, url, summary, published_at], …].
 * Returns [items, error].
 */
function parse_feed(string $xml): array
{
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($doc === false) {
        return [[], 'not a valid RSS or Atom feed'];
    }

    $items = [];

    if (isset($doc->channel->item)) {                       // RSS 2.0
        foreach ($doc->channel->item as $item) {
            $items[] = [
                'title'        => trim((string) $item->title),
                'url'          => trim((string) $item->link),
                'summary'      => excerpt((string) ($item->description ?? ''), 300),
                'published_at' => (string) $item->pubDate !== '' ? date('Y-m-d H:i:s', strtotime((string) $item->pubDate)) : null,
            ];
        }
    } elseif (isset($doc->entry)) {                          // Atom
        foreach ($doc->entry as $entry) {
            $link = '';
            foreach ($entry->link as $l) {
                $rel = (string) $l['rel'];
                if ($rel === '' || $rel === 'alternate') {
                    $link = (string) $l['href'];
                    break;
                }
            }
            $when = (string) ($entry->published ?? $entry->updated ?? '');
            $items[] = [
                'title'        => trim((string) $entry->title),
                'url'          => trim($link),
                'summary'      => excerpt((string) ($entry->summary ?? $entry->content ?? ''), 300),
                'published_at' => $when !== '' ? date('Y-m-d H:i:s', strtotime($when)) : null,
            ];
        }
    } else {
        return [[], 'no items found in feed'];
    }

    $items = array_values(array_filter($items, fn ($i) => $i['title'] !== '' && $i['url'] !== ''));
    return [$items, null];
}
