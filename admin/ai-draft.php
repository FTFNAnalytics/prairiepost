<?php
/**
 * The research & drafting desk — AI as a research assistant and first-draft
 * aide, never the final author. Hub only; editors and authors alike.
 *
 * Two products: a research brief (raw material, no article copy) and working
 * copy (a draft the journalist rewrites). Drafts land status=draft,
 * origin='ai', under the requesting journalist's byline — then the normal
 * flow: rework, review, publish, Runs on. This path cannot publish.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_login();
if (!pp_is_hub()) {
    http_response_code(404);
    exit('Not found.');
}

/** The wire item this visit is grounded on, when there is one. */
$newsItem = null;
$newsId = (int) ($_GET['news'] ?? $_POST['news_id'] ?? 0);
if ($newsId) {
    $stmt = db()->prepare('SELECT n.*, s.name AS source_name FROM news_items n JOIN sources s ON s.id = n.source_id WHERE n.id = ?');
    $stmt->execute([$newsId]);
    $newsItem = $stmt->fetch() ?: null;
}

/** Assemble the grounding block shared by both modes. Fetches capped pages. */
function pp_desk_grounding(?array $newsItem, array $urls, string $brief): array
{
    $parts = [];
    $notes = [];
    if ($newsItem) {
        $parts[] = "WIRE ITEM\nTitle: {$newsItem['title']}\nSource: {$newsItem['source_name']}\nURL: {$newsItem['url']}"
                 . ($newsItem['published_at'] ? "\nPublished: {$newsItem['published_at']}" : '')
                 . ($newsItem['summary'] ? "\nSummary: {$newsItem['summary']}" : '');
        array_unshift($urls, $newsItem['url']);
    }
    $urls = array_values(array_unique(array_filter(array_map('trim', $urls))));
    foreach (array_slice($urls, 0, 4) as $i => $url) {
        [$text, $err] = pp_ai_readable($url);
        if ($text !== null) {
            $parts[] = 'SOURCE ' . ($i + 1) . ": $url\n$text";
        } else {
            $notes[] = "$url — couldn't be fetched ($err); only its listing above is available";
        }
    }
    if ($notes) {
        $parts[] = "FETCH NOTES\n" . implode("\n", $notes);
    }
    if ($brief !== '') {
        $parts[] = "RESEARCH BRIEF (prepared earlier on this desk)\n$brief";
    }
    return $parts;
}

$briefSystem = <<<'PROMPT'
You are the research desk of a Canadian community-news network. Produce a RESEARCH BRIEF — raw material for a journalist. No article copy of any kind.

Use ONLY the material provided. Never invent facts, quotes, names, numbers or dates; where the material is silent, say so. Mark anything uncertain plainly.

Plain text under exactly these headings:
WHAT HAPPENED — three to six sentences.
BACKGROUND & TIMELINE — dated entries where dates are known.
KEY FIGURES — the people and organizations involved, one line each on why they matter.
NUMBERS & QUOTES — each with the source URL it came from in parentheses.
WHAT'S MISSING — open questions, missing voices, records worth requesting, facts to confirm.
ANGLES — two to four possible angles, noting which regions or communities each would serve.
PROMPT;

$draftSystem = <<<'PROMPT'
You are the drafting desk of a Canadian community-news network. You prepare WORKING COPY for a journalist — never finished journalism. A journalist will verify every fact, rewrite the piece in their own voice, and sign it; nothing you write is published as-is.

Rules, in order:
1. Use ONLY the source material provided. Never invent facts, quotes, names, numbers, dates or titles. What the material doesn't establish stays out.
2. Mark anything a journalist must confirm before publication inline as [VERIFY: what to check] — inferred figures, inconsistent spellings, single-source claims.
3. Write original sentences from the facts. Do not copy or lightly reword the source's sentences.
4. Attribute: name the source outlet or issuing organization in the body on first reference ("as first reported by …", "according to a … release").
5. Canadian spelling; numbers under ten spelled out; metric first.
6. body_html uses only <p>, <h2>, <blockquote>, <ul>, <ol>, <li>, <a>, <strong> and <em>. Its first paragraph begins with the dateline followed by " — ".
7. If the material is too thin to carry an original story, say so in editor_note and recommend running it as a wire link instead — and still return your best minimal draft.

editor_note is your memo to the journalist: thin sourcing, conflicting facts, missing voices, calls worth making. Empty string if there is nothing to flag.
suggested_desk names one newsroom desk (e.g. City Hall, Business, Courts); suggested_tags gives three to six short topic tags.
PROMPT;

$brief = '';
$briefTopic = '';
$briefUrls = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    set_time_limit(300);
    $action = (string) ($_POST['action'] ?? '');
    $topic  = trim((string) ($_POST['topic'] ?? ''));
    $urls   = preg_split('/\R+/', trim((string) ($_POST['urls'] ?? ''))) ?: [];
    $carriedBrief = trim((string) ($_POST['brief'] ?? ''));

    if ($topic === '' && !$newsItem) {
        flash_set('Give the desk an assignment — a topic, an angle, or a wire item to work from.', true);
        redirect('ai-draft.php' . ($newsId ? '?news=' . $newsId : ''));
    }

    $grounding = pp_desk_grounding($newsItem, $urls, $action === 'draft' ? $carriedBrief : '');
    $assignment = "ASSIGNMENT\n" . ($topic !== '' ? $topic : 'Work from the wire item below.');
    $userMessage = $assignment . "\n\n" . implode("\n\n", $grounding);

    if ($action === 'brief') {
        $res = pp_ai_message($briefSystem, [['role' => 'user', 'content' => $userMessage]], ['max_tokens' => 8000]);
        if (!$res['ok']) {
            flash_set($res['error'], true);
            redirect('ai-draft.php' . ($newsId ? '?news=' . $newsId : ''));
        }
        // Render below with the inputs preserved, so drafting can follow.
        $brief = $res['text'];
        $briefTopic = $topic;
        $briefUrls = implode("\n", array_filter(array_map('trim', $urls)));
    }

    if ($action === 'draft') {
        $res = pp_ai_message($draftSystem, [['role' => 'user', 'content' => $userMessage]],
                             ['schema' => pp_ai_draft_schema()]);
        if (!$res['ok']) {
            flash_set($res['error'], true);
            redirect('ai-draft.php' . ($newsId ? '?news=' . $newsId : ''));
        }
        $draft = json_decode($res['text'], true);
        if (!is_array($draft) || trim((string) ($draft['title'] ?? '')) === '') {
            flash_set("The model's reply wasn't a usable draft. Try again.", true);
            redirect('ai-draft.php' . ($newsId ? '?news=' . $newsId : ''));
        }

        $sourceUrl = $newsItem['url'] ?? (array_values(array_filter(array_map('trim', $urls)))[0] ?? '');
        $sourceLabel = $newsItem
            ? e($newsItem['source_name']) . ' — <a href="' . e($newsItem['url']) . '">' . e($newsItem['url']) . '</a>'
            : ($sourceUrl !== '' ? '<a href="' . e($sourceUrl) . '">' . e($sourceUrl) . '</a>' : 'the assignment text only');

        // The provenance note leads the working copy; the journalist deletes
        // it as part of making the story their own.
        $body = '<p><em>AI-assisted working copy prepared for ' . e($user['name'])
              . '. Verify every fact and every [VERIFY] mark against the sources before this moves. Source: '
              . $sourceLabel . '</em></p>';
        $note = trim((string) ($draft['editor_note'] ?? ''));
        if ($note !== '') {
            $body .= '<p><em>Desk note: ' . e($note) . '</em></p>';
        }
        $body .= sanitize_html((string) ($draft['body_html'] ?? ''));

        $deskId = null;
        $deskName = trim((string) ($draft['suggested_desk'] ?? ''));
        if ($deskName !== '') {
            $stmt = db()->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(?) OR slug = ? LIMIT 1');
            $stmt->execute([$deskName, slugify($deskName)]);
            $deskId = ($row = $stmt->fetch()) ? (int) $row['id'] : null;
        }

        $title = trim((string) $draft['title']);
        db()->prepare('INSERT INTO posts (title, slug, author_id, byline, dateline, lede, body, meta_description, category_id, source_url, origin, status, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$title, unique_post_slug($title), (int) $user['id'], $user['name'],
                       trim((string) ($draft['dateline'] ?? '')), trim((string) ($draft['lede'] ?? '')), $body,
                       mb_substr(trim((string) ($draft['meta_description'] ?? '')), 0, 255),
                       $deskId, $sourceUrl, 'ai', 'draft', now(), now()]);
        $postId = pp_last_id('posts');
        // No site mapping yet — the journalist picks papers in Runs on.
        $tags = array_slice(array_filter(array_map('trim', (array) ($draft['suggested_tags'] ?? []))), 0, 6);
        if ($tags) {
            set_post_tags($postId, implode(', ', $tags));
        }
        if ($newsItem) {
            db()->prepare('UPDATE news_items SET used = 1 WHERE id = ?')->execute([(int) $newsItem['id']]);
        }
        flash_set('Working copy filed as a draft under your byline. Verify every fact, rework it in your voice — it moves only when you sign it.');
        redirect('post-edit.php?id=' . $postId);
    }
}

admin_header('Research desk', 'aidraft');
flash_show();
?>

<h1 class="pagetitle">The research &amp; drafting desk</h1>
<p class="pagesub">AI as a research assistant and first-draft aide — never the final author. A brief gathers raw material; working copy lands as a draft under <strong>your</strong> byline, marked AI-assisted, for you to verify, rewrite and sign. Nothing from this desk can publish.</p>

<?php if (!pp_ai_enabled()): ?>
<div class="panel">
  <h2>Not connected yet</h2>
  <p>The desk needs <code>anthropic_api_key</code> in the hub's <code>config.php</code> — the key lives on the server only, never in the database or the repository. Model: <span class="mono"><?= e(pp_ai_model()) ?></span> (change it under Settings once connected).</p>
</div>
<?php endif; ?>

<?php if ($newsItem): ?>
<div class="panel">
  <h2>Grounded on this wire item</h2>
  <div class="newsitem">
    <div class="t">
      <a href="<?= e($newsItem['url']) ?>" target="_blank" rel="noopener"><?= e($newsItem['title']) ?></a>
      <span class="src"><?= e($newsItem['source_name']) ?> · <?= e(fmt_date($newsItem['published_at'] ?: $newsItem['fetched_at'], 'M j, g:i a')) ?></span>
    </div>
    <div class="acts"><a class="btn btn--ghost btn--small" href="ai-draft.php">Clear</a></div>
  </div>
  <?php if ($newsItem['summary']): ?><p><?= e($newsItem['summary']) ?></p><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($brief !== ''): ?>
<div class="panel">
  <h2>Research brief</h2>
  <div class="prose" style="white-space:pre-wrap"><?= e($brief) ?></div>
  <form method="post" style="margin-top:16px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="draft">
    <input type="hidden" name="news_id" value="<?= (int) $newsId ?>">
    <input type="hidden" name="topic" value="<?= e($briefTopic) ?>">
    <input type="hidden" name="urls" value="<?= e($briefUrls) ?>">
    <input type="hidden" name="brief" value="<?= e($brief) ?>">
    <button class="btn" type="submit"<?= pp_ai_enabled() ? '' : ' disabled' ?>>Draft working copy from this brief</button>
    <span class="help">Files a draft under your byline — you rework and sign it.</span>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <h2><?= $newsItem ? 'The assignment' : 'A fresh assignment' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="news_id" value="<?= (int) $newsId ?>">
    <div class="formgrid">
      <div>
        <label for="topic">Topic &amp; angle<?= $newsItem ? ' · optional, steers the wire item' : '' ?></label>
        <textarea id="topic" name="topic" style="min-height:96px" placeholder="What's the story, and for whom? e.g. “Kelowna's transit expansion — what the new routes mean for commuters in Rutland.”"><?= e($briefTopic) ?></textarea>
      </div>
      <div>
        <label for="urls">Source URLs · one per line, up to four<?= $newsItem ? ' (the wire link is included automatically)' : '' ?></label>
        <textarea id="urls" name="urls" class="mono" style="min-height:96px" placeholder="https://…"><?= e($briefUrls) ?></textarea>
        <p class="help">Pages are fetched and given to the model as grounding — it may use nothing else, and must mark anything unconfirmed with [VERIFY].</p>
      </div>
    </div>
    <p style="margin-top:16px">
      <button class="btn btn--outline" type="submit" name="action" value="brief"<?= pp_ai_enabled() ? '' : ' disabled' ?>>Build a research brief</button>
      <button class="btn" type="submit" name="action" value="draft"<?= pp_ai_enabled() ? '' : ' disabled' ?>>Draft working copy</button>
      <span class="help">Model: <span class="mono"><?= e(pp_ai_model()) ?></span> · a draft can take a minute or two.</span>
    </p>
  </form>
</div>

<?php admin_footer(); ?>
