<?php
/** The Prairie Dispatch — shared helpers. */

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

    // Hub administrators must carry two-step sign-in. Until they enrol,
    // the control room shrinks to the profile page (where enrolment lives)
    // and the door out — a funnel, not a lockout.
    if ($user['role'] === 'admin' && empty($user['totp_enabled']) && pp_totp_required()) {
        // SCRIPT_NAME, not REQUEST_URI: the executed script can't be spoofed
        // by PATH_INFO suffixes like /admin/settings.php/profile.php.
        $here = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (!in_array($here, ['profile.php', 'logout.php', 'upload.php'], true)) {
            redirect('/admin/profile.php?totp=required');
        }
    }

    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('That page needs an administrator account.');
    }
    return $user;
}

/** Editors and admins run the paper; authors write for it. */
function is_editor(?array $user): bool
{
    return $user !== null && in_array($user['role'], ['admin', 'editor'], true);
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

/** Authors may open only their own stories; editors and admins open any. */
function can_edit_post(array $user, array $post): bool
{
    return is_editor($user) || (int) ($post['author_id'] ?? 0) === (int) $user['id'];
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
 * Whitelist-sanitize stored article HTML before rendering or saving.
 * Keeps editorial tags, strips scripts, event handlers and javascript: URLs.
 */
function sanitize_html(string $html): string
{
    $allowed = '<p><br><strong><em><b><i><u><s><a><h2><h3><blockquote><ul><ol><li>'
             . '<figure><figcaption><img><table><thead><tbody><tr><td><th><hr><div><span>';
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/\s(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\2/i', '', $html);
    $html = preg_replace('/\s(href|src)\s*=\s*(["\']?)\s*data:text\/html[^"\'>\s]*\2/i', '', $html);
    return $html;
}

function fmt_date(?string $dt, string $format = 'M j, Y'): string
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    return $ts ? date($format, $ts) : '';
}

/**
 * Reading time in whole minutes, at 220 words a minute, floored at one.
 * Counts the lede with the body so a short piece isn't reported as zero.
 */
function read_minutes(array $post): int
{
    $text = strip_tags((string) ($post['lede'] ?? '') . ' ' . (string) ($post['body'] ?? ''));
    $words = str_word_count($text);
    return max(1, (int) round($words / 220));
}

/** "6:40 a.m." / "Yesterday" / "Jul 3" — the register of a dateline, not a widget. */
function time_label(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    if (!$ts) {
        return '';
    }
    $day = date('Y-m-d', $ts);
    if ($day === date('Y-m-d')) {
        return strtolower(str_replace(['AM', 'PM'], ['a.m.', 'p.m.'], date('g:i A', $ts)));
    }
    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return 'Yesterday';
    }
    return date('M j', $ts);
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

/* --- Uploads ------------------------------------------------------------- */

/**
 * Validate and store an uploaded image. Returns [public_url|null, error|null].
 * Shared by the editor's upload endpoint and the profile page.
 */
function pp_handle_image_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [null, 'The upload failed (code ' . (int) ($file['error'] ?? -1) . '). Try a smaller file.'];
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        return [null, 'That file is over 8 MB. Resize it and try again.'];
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
    $dir = PP_ROOT . '/uploads/' . date('Y/m');
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $base = slugify(pathinfo($file['name'], PATHINFO_FILENAME)) ?: 'image';
    $name = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return [null, "The server couldn't write the file. Check that /uploads/ is writable."];
    }
    return ['/uploads/' . date('Y/m') . '/' . $name, null];
}

/* --- Feeds (shared by cron and the sources admin) ----------------------- */

/** Fetch a URL with a short timeout; returns [body, error]. */
function http_get(string $url, int $timeout = 12): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT      => 'PrairieDispatch/1.0 (+news reader)',
        CURLOPT_ENCODING       => '',
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false) {
        return [null, $err ?: 'request failed'];
    }
    if ($code >= 400) {
        return [null, 'HTTP ' . $code];
    }
    return [$body, null];
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
