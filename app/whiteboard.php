<?php
/**
 * The whiteboard: each paper's private board for the loose ends of
 * reporting. It holds reader tips, leads, rumours and facts noted in
 * research.
 *
 * Modelled on Calgary Dispatch's admin whiteboard (its feature/whiteboard
 * branch). A note carries a type, a verification status and an optional
 * source link, and can be turned into a draft. Two things differ here:
 *
 * - Every note belongs to one site. A paper's newsroom sees only its own
 *   board; the hub's board is the control room's own.
 * - Readers can pin to it. Each paper's /tips form (tips.php) files a `tip`
 *   note, `unverified`, carrying only what the reader chose to type: the
 *   tip, a link, a name and a way to reach them. Nothing else about the
 *   sender is stored: no IP address, no browser details, no audit row.
 *   Flood protection is a per-paper cap, so it never needs to know who
 *   is sending.
 *
 * Nothing on a whiteboard is public. Editors and administrators open it;
 * authors don't. A tipster's name and contact never leave the board:
 * turning a tip into a draft copies the tip, not the person.
 */

const PP_WB_TYPES = ['tip' => 'Reader tip', 'lead' => 'Lead', 'rumour' => 'Rumour', 'fact' => 'Fact'];
const PP_WB_STATUSES = ['unverified' => 'Unverified', 'checking' => 'Checking', 'confirmed' => 'Confirmed', 'debunked' => 'Debunked'];
const PP_WB_MAX_TEXT = 4000;

/** Reader tips one paper accepts per hour and per day. Past either, the form asks readers to try later. */
const PP_TIPS_PER_HOUR = 30;
const PP_TIPS_PER_DAY = 200;
/** The form's token must be at least this many seconds old (a person reads before sending) and no older than the second. */
const PP_TIPS_MIN_AGE = 3;
const PP_TIPS_MAX_AGE = 6 * 3600;
const PP_TIPS_MIN_TEXT = 10;

/* --- Input hygiene -------------------------------------------------------- */

/** Trimmed text with Windows line ends normalised and control characters (bar newline and tab) removed. */
function pp_wb_text(mixed $value): string
{
    $s = str_replace(["\r\n", "\r"], "\n", is_string($value) ? $value : '');
    return trim((string) preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $s));
}

/** One line of text: every run of whitespace becomes a single space. */
function pp_wb_line(mixed $value): string
{
    return trim((string) preg_replace('/[\s\x00-\x1F\x7F]+/u', ' ', is_string($value) ? $value : ''));
}

/**
 * A source link: '' (none given), the link itself when it is an absolute
 * http(s) address of at most 1,000 characters, or null when it is not.
 */
function pp_wb_url(mixed $value): ?string
{
    $u = trim(is_string($value) ? $value : '');
    if ($u === '') {
        return '';
    }
    if (!mb_check_encoding($u, 'UTF-8') || mb_strlen($u) > 1000 || preg_match('/[\s\x00-\x1F\x7F<>"]/u', $u) === 1) {
        return null;
    }
    $parts = parse_url($u);
    if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        || (string) ($parts['host'] ?? '') === '') {
        return null;
    }
    return $u;
}

/* --- The board ------------------------------------------------------------ */

/** A site's notes, newest first, optionally filtered by type and status. */
function pp_wb_notes(int $siteId, string $type = '', string $status = '', int $limit = 200): array
{
    $sql = 'SELECT * FROM whiteboard_notes WHERE site_id = ?';
    $args = [$siteId];
    if (isset(PP_WB_TYPES[$type])) {
        $sql .= ' AND note_type = ?';
        $args[] = $type;
    }
    if (isset(PP_WB_STATUSES[$status])) {
        $sql .= ' AND status = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . max(1, min($limit, 500));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

/** One note, only if it is on this site's board. */
function pp_wb_note(int $siteId, int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM whiteboard_notes WHERE id = ? AND site_id = ?');
    $stmt->execute([$id, $siteId]);
    return $stmt->fetch() ?: null;
}

/** Reader tips still unverified: the count the admin nav carries. */
function pp_wb_waiting(int $siteId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM whiteboard_notes WHERE site_id = ? AND origin = 'reader' AND status = 'unverified'");
    $stmt->execute([$siteId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Check a note's editable fields. Returns [fields, error]; error is ''
 * when the fields are good.
 */
function pp_wb_fields(array $in): array
{
    $body = pp_wb_text($in['body'] ?? '');
    $url = pp_wb_url($in['source_url'] ?? '');
    // A missing field takes its default; anything that is not a string is refused.
    $type = $in['note_type'] ?? 'lead';
    $status = $in['status'] ?? 'unverified';
    $type = is_string($type) ? $type : '';
    $status = is_string($status) ? $status : '';
    $error = match (true) {
        $body === ''                         => 'Write the note first.',
        mb_strlen($body) > PP_WB_MAX_TEXT    => 'A note holds at most 4,000 characters.',
        $url === null                        => 'The source link must be a full web address starting with http:// or https://.',
        !isset(PP_WB_TYPES[$type])           => 'Pick a type for the note.',
        !isset(PP_WB_STATUSES[$status])      => 'Pick a status for the note.',
        default                              => '',
    };
    return [['body' => $body, 'source_url' => (string) $url, 'note_type' => $type, 'status' => $status], $error];
}

/** Pin a staff note. Returns [id, error]: id 0 with an error when nothing was written. */
function pp_wb_add(int $siteId, array $user, array $in): array
{
    [$f, $error] = pp_wb_fields($in);
    if ($error !== '') {
        return [0, $error];
    }
    db()->prepare('INSERT INTO whiteboard_notes (site_id, body, source_url, note_type, status, origin, posted_by, contact, user_id, created_at, updated_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$siteId, $f['body'], $f['source_url'], $f['note_type'], $f['status'], 'staff',
                   mb_substr((string) ($user['name'] ?? ''), 0, 120), '', (int) ($user['id'] ?? 0) ?: null, now(), now()]);
    return [pp_last_id('whiteboard_notes'), ''];
}

/** Rewrite a note's text, link, type and status. A reader's name and contact are never editable. */
function pp_wb_edit(int $siteId, int $id, array $in): string
{
    [$f, $error] = pp_wb_fields($in);
    if ($error !== '') {
        return $error;
    }
    $stmt = db()->prepare('UPDATE whiteboard_notes SET body = ?, source_url = ?, note_type = ?, status = ?, updated_at = ? WHERE id = ? AND site_id = ?');
    $stmt->execute([$f['body'], $f['source_url'], $f['note_type'], $f['status'], now(), $id, $siteId]);
    return $stmt->rowCount() > 0 ? '' : 'That note is no longer on the board.';
}

function pp_wb_set_status(int $siteId, int $id, string $status): bool
{
    if (!isset(PP_WB_STATUSES[$status])) {
        return false;
    }
    $stmt = db()->prepare('UPDATE whiteboard_notes SET status = ?, updated_at = ? WHERE id = ? AND site_id = ?');
    $stmt->execute([$status, now(), $id, $siteId]);
    return $stmt->rowCount() > 0;
}

function pp_wb_delete(int $siteId, int $id): bool
{
    $stmt = db()->prepare('DELETE FROM whiteboard_notes WHERE id = ? AND site_id = ?');
    $stmt->execute([$id, $siteId]);
    return $stmt->rowCount() > 0;
}

/**
 * Turn a note into a draft on this site, once. Returns the draft's id: the
 * new one, or the one an earlier click (or a colleague's) already made.
 * The draft carries the note's text and source link and says where it came
 * from. A reader's name and contact are never copied into it.
 */
function pp_wb_draft(int $siteId, int $id, array $user): ?int
{
    $note = pp_wb_note($siteId, $id);
    if ($note === null) {
        return null;
    }
    $text = (string) $note['body'];
    $first = trim(strtok($text, "\n") ?: $text);
    $title = mb_strlen($first) > 120 ? rtrim(mb_substr($first, 0, 117)) . '…' : $first;
    $what = $note['origin'] === 'reader' ? 'a reader tip' : 'a ' . strtolower(PP_WB_TYPES[$note['note_type']] ?? 'note');
    $body = '<p>' . nl2br(e($text)) . '</p>';
    if ($note['source_url'] !== '') {
        $body .= '<p><em>Source: <a href="' . e($note['source_url']) . '">' . e($note['source_url']) . '</a></em></p>';
    }
    $body .= '<p><em>From the whiteboard: ' . e($what) . ', ' . e(strtolower(PP_WB_STATUSES[$note['status']] ?? $note['status']))
           . ' when it was drafted. Check it before it runs.</em></p>';

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO posts (title, slug, author_id, byline, lede, body, source_url, origin, status, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$title, unique_post_slug($title), (int) $user['id'], (string) $user['name'], '', sanitize_html($body),
                       (string) $note['source_url'], 'newsroom', 'draft', now(), now()]);
        $postId = pp_last_id('posts');
        // Only one draft per note, however the clicks arrive: the note takes
        // the first draft's id, and any later attempt (a double click, a
        // colleague, a reload) finds it set and undoes its own insert.
        $claim = $pdo->prepare('UPDATE whiteboard_notes SET post_id = ?, updated_at = ? WHERE id = ? AND site_id = ? AND post_id IS NULL');
        $claim->execute([$postId, now(), $id, $siteId]);
        if ($claim->rowCount() === 0) {
            $pdo->rollBack();
            $again = pp_wb_note($siteId, $id);
            return $again !== null && (int) $again['post_id'] > 0 ? (int) $again['post_id'] : null;
        }
        pp_post_snapshot($postId, 'create', (string) $user['name']);
        set_post_sites($postId, pp_is_hub() ? [] : [$siteId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return $postId;
}

/* --- The public tips form ------------------------------------------------- */

/** The install's tips-form key, minted by schema step 26. '' if it is missing. */
function pp_tips_key(): string
{
    return pp_setting_for_site(0, 'tips_form_key');
}

/** A token for one rendering of a paper's tips form: when it was drawn, signed. */
function pp_tips_token(int $siteId, ?int $now = null): string
{
    $key = pp_tips_key();
    if ($key === '') {
        return '';
    }
    $ts = (string) ($now ?? time());
    return $ts . '.' . substr(hash_hmac('sha256', "tips|$siteId|$ts", $key), 0, 32);
}

/** Is the token genuine for this paper, and neither too fresh nor too old? */
function pp_tips_token_ok(string $token, int $siteId, ?int $now = null): bool
{
    $key = pp_tips_key();
    if ($key === '' || preg_match('/^(\d{9,11})\.([0-9a-f]{32})$/', $token, $m) !== 1) {
        return false;
    }
    $age = ($now ?? time()) - (int) $m[1];
    return $age >= PP_TIPS_MIN_AGE && $age <= PP_TIPS_MAX_AGE
        && hash_equals(substr(hash_hmac('sha256', "tips|$siteId|{$m[1]}", $key), 0, 32), $m[2]);
}

/** Is the paper under its hourly and daily caps for reader tips? */
function pp_tips_accepting(int $siteId, ?int $now = null): bool
{
    $now ??= time();
    $count = db()->prepare("SELECT COUNT(*) FROM whiteboard_notes WHERE site_id = ? AND origin = 'reader' AND created_at > ?");
    foreach ([3600 => PP_TIPS_PER_HOUR, 86400 => PP_TIPS_PER_DAY] as $window => $cap) {
        $count->execute([$siteId, date('Y-m-d H:i:s', $now - $window)]);
        if ((int) $count->fetchColumn() >= $cap) {
            return false;
        }
    }
    return true;
}

/**
 * File a reader's tip on a paper's board.
 *
 * Returns [status, errors]. Status is 'sent' (stored, or a tripped honeypot
 * that is told the same and stores nothing), 'invalid' (errors keyed by
 * field), 'expired' (the token failed) or 'busy' (the paper is at its cap).
 */
function pp_tips_submit(int $siteId, array $in, ?int $now = null): array
{
    // The honeypot: readers never see the field; a form-filling bot does.
    if (trim((string) ($in['website'] ?? '')) !== '') {
        return ['sent', []];
    }
    if (!pp_tips_token_ok((string) ($in['t'] ?? ''), $siteId, $now)) {
        return ['expired', []];
    }
    $tip = pp_wb_text($in['tip'] ?? '');
    $url = pp_wb_url($in['link'] ?? '');
    $name = pp_wb_line($in['name'] ?? '');
    $contact = pp_wb_line($in['contact'] ?? '');
    $errors = [];
    if (mb_strlen($tip) < PP_TIPS_MIN_TEXT) {
        $errors['tip'] = 'Tell us what you know, in a sentence or more.';
    } elseif (mb_strlen($tip) > PP_WB_MAX_TEXT) {
        $errors['tip'] = 'That is more than 4,000 characters. Shorten it, or leave a way to reach you and we will ask for the rest.';
    }
    if ($url === null) {
        $errors['link'] = 'That link is not a web address. It should start with https://.';
    }
    if (mb_strlen($name) > 120) {
        $errors['name'] = 'Keep your name under 120 characters.';
    }
    if (mb_strlen($contact) > 255) {
        $errors['contact'] = 'Keep this under 255 characters.';
    }
    if ($errors) {
        return ['invalid', $errors];
    }
    if (!pp_tips_accepting($siteId, $now)) {
        return ['busy', []];
    }
    $at = date('Y-m-d H:i:s', $now ?? time());
    db()->prepare('INSERT INTO whiteboard_notes (site_id, body, source_url, note_type, status, origin, posted_by, contact, user_id, created_at, updated_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$siteId, $tip, (string) $url, 'tip', 'unverified', 'reader', $name, $contact, null, $at, $at]);
    return ['sent', []];
}
