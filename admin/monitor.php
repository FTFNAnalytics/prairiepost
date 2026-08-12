<?php
/**
 * The media monitoring desk — one screen where journalists watch what's
 * surfacing across regions and jurisdictions: government publications from
 * the external scraping agent (via /api/monitor), press releases from the
 * desk's own feeds or captured by hand, and the story ideas they generate.
 *
 * Hub only; every newsroom role triages. Feeds are the editors' to manage.
 * Ideas are suggestions, never auto-filed stories.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/fetch.php';
require __DIR__ . '/_layout.php';
$user = require_login();
if (!pp_is_hub()) {
    http_response_code(404);
    exit('Not found.');
}

$levels   = pp_monitor_levels();
$doctypes = pp_monitor_doctypes();
$regionLabels = pp_region_labels();
foreach (db()->query('SELECT DISTINCT region FROM monitor_items ORDER BY region') as $r) {
    $k = (string) $r['region'];
    if ($k !== '' && !isset($regionLabels[$k])) {
        $regionLabels[$k] = ucwords(str_replace('-', ' ', $k));
    }
}

$backTo = function () {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    return 'monitor.php' . ($qs !== '' ? '?' . $qs : '');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $item = null;
    if ($itemId) {
        $stmt = db()->prepare('SELECT * FROM monitor_items WHERE id = ?');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch() ?: null;
    }

    /* --- Triage: the board's four verbs ---------------------------------- */
    $triage = [
        'flag'    => ['flagged',   'flagged_by'],
        'claim'   => ['claimed',   'claimed_by'],
        'dismiss' => ['dismissed', null],
        'restore' => ['new',       null],
    ];
    if (isset($triage[$action]) && $item) {
        [$to, $byCol] = $triage[$action];
        if ($byCol) {
            db()->prepare("UPDATE monitor_items SET status = ?, $byCol = ? WHERE id = ?")
                ->execute([$to, $user['name'], $itemId]);
        } else {
            db()->prepare('UPDATE monitor_items SET status = ? WHERE id = ?')->execute([$to, $itemId]);
        }
        redirect($backTo());
    }

    /* --- Start a draft from an item — exactly like the wire --------------- */
    if ($action === 'start_draft' && $item) {
        $title = $item['title'];
        $body = '<p><em>Draft started from the monitoring desk. '
              . e($doctypes[$item['doc_type']] ?? $item['doc_type']) . ' · '
              . e($levels[$item['level']] ?? $item['level'])
              . ($item['region'] !== '' ? ' · ' . e($regionLabels[$item['region']] ?? $item['region']) : '')
              . '. Source: ' . e($item['source_name']) . ' — <a href="' . e($item['url']) . '">' . e($item['url']) . '</a></em></p>'
              . ($item['summary'] ? '<p>' . e($item['summary']) . '</p>' : '');
        db()->prepare('INSERT INTO posts (title, slug, author_id, byline, lede, body, source_url, origin, status, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$title, unique_post_slug($title), (int) $user['id'], $user['name'], '', $body,
                       $item['url'], 'wire', 'draft', now(), now()]);
        $postId = pp_last_id('posts');
        db()->prepare("UPDATE monitor_items SET status = 'used', claimed_by = ? WHERE id = ?")
            ->execute([$user['name'], $itemId]);
        redirect('post-edit.php?id=' . $postId);
    }

    /* --- Story ideas from an item (AI) — pitches, never stories ----------- */
    if ($action === 'ideas' && $item) {
        set_time_limit(300);
        $papers = pp_paper_sites();
        $paperLines = implode("\n", array_map(
            fn ($s) => "- {$s['slug']}: {$s['name']}", $papers));
        $regionLines = implode("\n", array_map(
            fn ($k, $v) => "- $k: $v", array_keys($regionLabels), $regionLabels));
        [$pageText] = pp_ai_readable($item['url']);
        $system = "You are the story-ideas desk of a Canadian community-news network. From one monitoring item, propose three to five distinct story pitches a local journalist could pursue. Use ONLY the provided material — never invent facts. Each pitch: a working title, the angle in one or two sentences, and why it matters now (rationale). Choose region from the provided region keys (the item's own region when in doubt) and site_slug from the provided papers (empty string if none clearly fits). Pitches are suggestions for a journalist to claim — not stories.";
        $userMsg = "MONITORING ITEM\nTitle: {$item['title']}\nSource: {$item['source_name']}\n"
                 . "Jurisdiction: {$item['level']} · Region: {$item['region']} · Type: {$item['doc_type']}\nURL: {$item['url']}\n"
                 . ($item['summary'] ? "Summary: {$item['summary']}\n" : '')
                 . ($item['body_excerpt'] ? "Excerpt: {$item['body_excerpt']}\n" : '')
                 . ($pageText ? "\nPAGE TEXT\n$pageText\n" : '')
                 . "\nPAPERS\n$paperLines\n\nREGION KEYS\n$regionLines";
        $res = pp_ai_message($system, [['role' => 'user', 'content' => $userMsg]],
                             ['schema' => pp_ai_ideas_schema(), 'max_tokens' => 8000]);
        if (!$res['ok']) {
            flash_set($res['error'], true);
            redirect($backTo());
        }
        $decoded = json_decode($res['text'], true);
        $ideas = is_array($decoded) ? array_slice((array) ($decoded['ideas'] ?? []), 0, 5) : [];
        $siteBySlug = [];
        foreach ($papers as $s) {
            $siteBySlug[$s['slug']] = (int) $s['id'];
        }
        $n = 0;
        foreach ($ideas as $idea) {
            $title = trim((string) ($idea['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            db()->prepare('INSERT INTO story_ideas (monitor_item_id, site_id, title, angle, rationale, region, origin, status, created_by, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$itemId, $siteBySlug[trim((string) ($idea['site_slug'] ?? ''))] ?? null,
                           mb_substr($title, 0, 255), trim((string) ($idea['angle'] ?? '')),
                           trim((string) ($idea['rationale'] ?? '')),
                           mb_substr(slugify((string) ($idea['region'] ?? $item['region'])), 0, 40),
                           'ai', 'open', $user['name'], now()]);
            $n++;
        }
        flash_set($n ? "$n story idea(s) filed below — claim one to start a draft." : 'The model returned no usable pitches.', !$n);
        redirect($backTo() . '#ideas');
    }

    /* --- Capture a release by URL ----------------------------------------- */
    if ($action === 'capture') {
        $url = trim((string) ($_POST['url'] ?? ''));
        if (!preg_match('#^https?://#i', $url)) {
            flash_set('A release needs its URL, starting with http:// or https://.', true);
            redirect($backTo());
        }
        $meta = pp_fetch_link_meta($url);
        $title = trim((string) ($_POST['title'] ?? '')) ?: (string) ($meta['title'] ?? '');
        if ($title === '') {
            flash_set("That page offered no title — paste one in the capture form's title box.", true);
            redirect($backTo());
        }
        $level = isset($levels[$_POST['level'] ?? '']) ? $_POST['level'] : 'agency';
        $doc   = isset($doctypes[$_POST['doc_type'] ?? '']) ? $_POST['doc_type'] : 'release';
        $result = pp_monitor_add_item([
            'source'   => trim((string) ($_POST['source'] ?? '')) ?: (string) ($meta['site_name'] ?? parse_url($url, PHP_URL_HOST)),
            'level'    => $level,
            'region'   => (string) ($_POST['region'] ?? ''),
            'doc_type' => $doc,
            'title'    => $title,
            'url'      => $url,
            'summary'  => (string) ($meta['description'] ?? ''),
        ]);
        flash_set($result === 'added' ? 'Captured — it\'s on the board as new.' : 'Already on the board — the URL matched an existing item.', $result !== 'added');
        redirect($backTo());
    }

    /* --- Ideas: file by hand, claim into a draft, dismiss ------------------ */
    if ($action === 'idea_new') {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash_set('An idea starts with a working title.', true);
            redirect($backTo() . '#ideas');
        }
        db()->prepare('INSERT INTO story_ideas (site_id, title, angle, rationale, region, origin, status, created_by, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([(int) ($_POST['site_id'] ?? 0) ?: null, mb_substr($title, 0, 255),
                       trim((string) ($_POST['angle'] ?? '')), '',
                       mb_substr(slugify((string) ($_POST['region'] ?? '')), 0, 40),
                       'newsroom', 'open', $user['name'], now()]);
        flash_set('Idea filed.');
        redirect($backTo() . '#ideas');
    }
    $ideaId = (int) ($_POST['idea_id'] ?? 0);
    if (in_array($action, ['idea_claim', 'idea_dismiss'], true) && $ideaId) {
        $stmt = db()->prepare('SELECT i.*, m.url AS monitor_url, m.source_name AS monitor_source
                               FROM story_ideas i LEFT JOIN monitor_items m ON m.id = i.monitor_item_id WHERE i.id = ?');
        $stmt->execute([$ideaId]);
        if ($idea = $stmt->fetch()) {
            if ($action === 'idea_dismiss') {
                db()->prepare("UPDATE story_ideas SET status = 'dismissed' WHERE id = ?")->execute([$ideaId]);
                redirect($backTo() . '#ideas');
            }
            // Claiming an idea starts the draft — the journalist writes it.
            $body = '<p><em>Started from a story idea'
                  . ($idea['origin'] === 'ai' ? ' (AI-suggested pitch — the story is yours to report)' : '')
                  . '. Angle: ' . e((string) $idea['angle'])
                  . ($idea['rationale'] ? ' Why now: ' . e((string) $idea['rationale']) : '')
                  . ($idea['monitor_url'] ? ' Source item: <a href="' . e($idea['monitor_url']) . '">' . e($idea['monitor_source'] ?: $idea['monitor_url']) . '</a>' : '')
                  . '</em></p>';
            db()->prepare('INSERT INTO posts (title, slug, author_id, byline, lede, body, source_url, origin, status, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$idea['title'], unique_post_slug($idea['title']), (int) $user['id'], $user['name'], '', $body,
                           (string) ($idea['monitor_url'] ?? ''), '', 'draft', now(), now()]);
            $postId = pp_last_id('posts');
            db()->prepare("UPDATE story_ideas SET status = 'claimed', claimed_by = ? WHERE id = ?")
                ->execute([$user['name'], $ideaId]);
            redirect('post-edit.php?id=' . $postId);
        }
        redirect($backTo() . '#ideas');
    }

    /* --- Feeds: the desk's own list (editors) ------------------------------ */
    if (str_starts_with($action, 'feed_') && is_editor($user)) {
        $feedId = (int) ($_POST['feed_id'] ?? 0);
        if ($action === 'feed_add') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $url  = trim((string) ($_POST['url'] ?? ''));
            if ($name === '' || !preg_match('#^https?://#i', $url)) {
                flash_set('A feed needs a name and an http(s) URL.', true);
            } else {
                db()->prepare('INSERT INTO monitor_feeds (name, url, level, region, doc_type, enabled) VALUES (?, ?, ?, ?, ?, 1)')
                    ->execute([mb_substr($name, 0, 160), mb_substr($url, 0, 600),
                               isset($levels[$_POST['level'] ?? '']) ? $_POST['level'] : 'agency',
                               mb_substr(slugify((string) ($_POST['region'] ?? '')), 0, 40),
                               isset($doctypes[$_POST['doc_type'] ?? '']) ? $_POST['doc_type'] : 'release']);
                flash_set('Feed added. Fetch it now, or let the hourly pull take it.');
            }
        }
        if ($action === 'feed_toggle' && $feedId) {
            db()->prepare('UPDATE monitor_feeds SET enabled = 1 - enabled WHERE id = ?')->execute([$feedId]);
        }
        if ($action === 'feed_delete' && $feedId) {
            db()->prepare('DELETE FROM monitor_feeds WHERE id = ?')->execute([$feedId]);
            flash_set('Feed removed. Its items stay on the board.');
        }
        if ($action === 'feed_fetch' && $feedId) {
            $stmt = db()->prepare('SELECT * FROM monitor_feeds WHERE id = ?');
            $stmt->execute([$feedId]);
            if ($feed = $stmt->fetch()) {
                set_time_limit(120);
                [$new, $err] = pp_monitor_poll_feed($feed);
                flash_set($err ? "The fetch failed: $err" : "Fetched — $new new item(s).", (bool) $err);
            }
        }
        redirect($backTo() . '#feeds');
    }
    redirect($backTo());
}

/* --- The board query ----------------------------------------------------- */
$fStatus = (string) ($_GET['status'] ?? 'active');
$fLevel  = (string) ($_GET['level'] ?? '');
$fRegion = (string) ($_GET['region'] ?? '');
$fDoc    = (string) ($_GET['doc'] ?? '');
$fQ      = trim((string) ($_GET['q'] ?? ''));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$per     = 60;

$where = [];
$params = [];
if ($fStatus === 'active') {
    $where[] = "m.status IN ('new', 'flagged', 'claimed')";
} elseif (in_array($fStatus, ['new', 'flagged', 'claimed', 'used', 'dismissed'], true)) {
    $where[] = 'm.status = ?';
    $params[] = $fStatus;
}
if (isset($levels[$fLevel])) {
    $where[] = 'm.level = ?';
    $params[] = $fLevel;
}
if ($fRegion !== '' && isset($regionLabels[$fRegion])) {
    $where[] = 'm.region = ?';
    $params[] = $fRegion;
}
if (isset($doctypes[$fDoc])) {
    $where[] = 'm.doc_type = ?';
    $params[] = $fDoc;
}
if ($fQ !== '') {
    $op = pp_like();
    $where[] = "(m.title $op ? OR m.source_name $op ?)";
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $fQ) . '%';
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = db()->prepare("SELECT COUNT(*) AS n FROM monitor_items m $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetch()['n'];
$stmt = db()->prepare("SELECT m.* FROM monitor_items m $whereSql
                       ORDER BY COALESCE(m.published_at, m.fetched_at) DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$stmt->execute($params);
$items = $stmt->fetchAll();

$openIdeas = db()->query("SELECT i.*, s.name AS site_name FROM story_ideas i
                          LEFT JOIN sites s ON s.id = i.site_id
                          WHERE i.status = 'open' ORDER BY i.created_at DESC LIMIT 40")->fetchAll();
$feeds = db()->query('SELECT * FROM monitor_feeds ORDER BY name')->fetchAll();
$papers = pp_paper_sites();

$filterQs = fn (array $over) => 'monitor.php?' . http_build_query(array_filter([
    'status' => $over['status'] ?? $fStatus,
    'level' => $over['level'] ?? $fLevel,
    'region' => $over['region'] ?? $fRegion,
    'doc' => $over['doc'] ?? $fDoc,
    'q' => $fQ,
    'page' => $over['page'] ?? null,
], fn ($v) => $v !== '' && $v !== null));

admin_header('Monitoring', 'monitor');
flash_show();
?>

<h1 class="pagetitle">The monitoring desk</h1>
<p class="pagesub">Government publications and press releases across every region and jurisdiction — streamed in by the scraping agent, pulled from the desk's feeds, or captured by hand. Triage with <em>Flag</em>, <em>Claim</em> and <em>Dismiss</em>; act with <em>Start draft</em>, <em>Research</em> and <em>Ideas</em>. Ideas are pitches — a journalist claims one and writes the story.</p>

<div class="panel">
  <form method="get" class="formrow">
    <select name="status" aria-label="Status">
      <option value="active"<?= $fStatus === 'active' ? ' selected' : '' ?>>Active (new · flagged · claimed)</option>
      <?php foreach (['new' => 'New', 'flagged' => 'Flagged', 'claimed' => 'Claimed', 'used' => 'Used', 'dismissed' => 'Dismissed'] as $k => $v): ?>
      <option value="<?= $k ?>"<?= $fStatus === $k ? ' selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <select name="level" aria-label="Jurisdiction">
      <option value="">Any jurisdiction</option>
      <?php foreach ($levels as $k => $v): ?>
      <option value="<?= $k ?>"<?= $fLevel === $k ? ' selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="region" aria-label="Region">
      <option value="">Any region</option>
      <?php foreach ($regionLabels as $k => $v): ?>
      <option value="<?= e($k) ?>"<?= $fRegion === $k ? ' selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="doc" aria-label="Document type">
      <option value="">Any type</option>
      <?php foreach ($doctypes as $k => $v): ?>
      <option value="<?= e($k) ?>"<?= $fDoc === $k ? ' selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="q" value="<?= e($fQ) ?>" placeholder="Title or source…" aria-label="Search">
    <button class="btn btn--ghost" type="submit">Filter</button>
  </form>
</div>

<div class="panel">
  <h2><?= $total ?> item<?= $total === 1 ? '' : 's' ?></h2>
  <?php if (!$items): ?>
  <p>Nothing matches. New items land here from the scraping agent (<span class="mono">/api/monitor</span>), the feeds below, or the capture form.</p>
  <?php endif; ?>
  <?php foreach ($items as $m): ?>
  <div class="newsitem<?= in_array($m['status'], ['used', 'dismissed'], true) ? ' is-used' : '' ?>">
    <div class="t">
      <a href="<?= e($m['url']) ?>" target="_blank" rel="noopener"><?= e($m['title']) ?></a>
      <span class="src"><?= e($m['source_name']) ?> · <?= e($levels[$m['level']] ?? $m['level']) ?><?= $m['region'] !== '' ? ' · ' . e($regionLabels[$m['region']] ?? $m['region']) : '' ?> · <?= e($doctypes[$m['doc_type']] ?? $m['doc_type']) ?> · <?= e(fmt_date($m['published_at'] ?: $m['fetched_at'], 'M j, g:i a')) ?></span>
      <?php if ($m['summary']): ?><span class="src"><?= e(excerpt((string) $m['summary'], 180)) ?></span><?php endif; ?>
    </div>
    <div class="acts">
      <?php if ($m['status'] === 'flagged'): ?><span class="chip chip--in_review" title="Flagged by <?= e($m['flagged_by']) ?>">Flagged</span><?php endif; ?>
      <?php if ($m['status'] === 'claimed'): ?><span class="chip chip--scheduled" title="Claimed by <?= e($m['claimed_by']) ?>">Claimed · <?= e($m['claimed_by']) ?></span><?php endif; ?>
      <?php if ($m['status'] === 'used'): ?><span class="chip chip--used">Used</span><?php endif; ?>
      <?php if ($m['status'] === 'dismissed'): ?><span class="chip chip--used">Dismissed</span><?php endif; ?>
      <?php $act = function (string $a, string $label, string $cls = 'btn--ghost') use ($m) { ?>
        <?php echo '<form method="post" class="inline">' . csrf_field()
            . '<input type="hidden" name="action" value="' . $a . '">'
            . '<input type="hidden" name="item_id" value="' . (int) $m['id'] . '">'
            . '<button class="btn ' . $cls . ' btn--small" type="submit">' . $label . '</button></form>'; ?>
      <?php }; ?>
      <?php if (in_array($m['status'], ['new', 'flagged', 'claimed'], true)): ?>
        <?php $act('start_draft', 'Start draft', 'btn--sky'); ?>
        <a class="btn btn--ghost btn--small" href="ai-draft.php?monitor=<?= (int) $m['id'] ?>">Research</a>
        <?php if (pp_ai_enabled()) { $act('ideas', 'Ideas'); } ?>
        <?php $m['status'] === 'flagged' ? $act('restore', 'Unflag') : $act('flag', 'Flag'); ?>
        <?php if ($m['status'] !== 'claimed') { $act('claim', 'Claim'); } ?>
        <?php $act('dismiss', 'Dismiss', 'btn--danger'); ?>
      <?php else: ?>
        <?php $act('restore', 'Restore'); ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if ($total > $per): ?>
  <p style="margin-top:12px">
    <?php for ($i = 1; $i <= min(20, (int) ceil($total / $per)); $i++): ?>
      <?php if ($i === $page): ?><span class="chip chip--used"><?= $i ?></span>
      <?php else: ?><a class="btn btn--ghost btn--small" href="<?= e($filterQs(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </p>
  <?php endif; ?>
</div>

<div class="panel" id="ideas">
  <h2>Story ideas · open docket</h2>
  <p class="pagesub" style="margin-bottom:12px">Pitches from the desk and from the newsroom. Claiming one starts your draft — the story is yours from there.</p>
  <?php if (!$openIdeas): ?><p>No open ideas. Generate some from a board item, or file your own below.</p><?php endif; ?>
  <?php foreach ($openIdeas as $idea): ?>
  <div class="newsitem">
    <div class="t">
      <strong><?= e($idea['title']) ?></strong>
      <?php if ($idea['angle']): ?><span class="src"><?= e($idea['angle']) ?><?= $idea['rationale'] ? ' — ' . e($idea['rationale']) : '' ?></span><?php endif; ?>
      <span class="src"><?= $idea['origin'] === 'ai' ? 'AI-suggested' : 'Filed by ' . e($idea['created_by']) ?><?= $idea['region'] !== '' ? ' · ' . e($regionLabels[$idea['region']] ?? $idea['region']) : '' ?><?= $idea['site_name'] ? ' · for ' . e($idea['site_name']) : '' ?> · <?= e(fmt_date($idea['created_at'], 'M j')) ?></span>
    </div>
    <div class="acts">
      <?php if ($idea['origin'] === 'ai'): ?><span class="chip chip--scheduled">AI-suggested</span><?php endif; ?>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="idea_claim"><input type="hidden" name="idea_id" value="<?= (int) $idea['id'] ?>"><button class="btn btn--sky btn--small" type="submit">Claim &amp; draft</button></form>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="idea_dismiss"><input type="hidden" name="idea_id" value="<?= (int) $idea['id'] ?>"><button class="btn btn--ghost btn--small" type="submit">Dismiss</button></form>
    </div>
  </div>
  <?php endforeach; ?>
  <form method="post" style="margin-top:16px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="idea_new">
    <div class="formrow">
      <input type="text" name="title" placeholder="Working title" aria-label="Working title" style="min-width:260px">
      <input type="text" name="angle" placeholder="The angle, in a sentence" aria-label="Angle" style="min-width:260px">
      <select name="region" aria-label="Region">
        <option value="">Region…</option>
        <?php foreach ($regionLabels as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <select name="site_id" aria-label="Paper">
        <option value="0">Any paper</option>
        <?php foreach ($papers as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn--outline" type="submit">File the idea</button>
    </div>
  </form>
</div>

<div class="panel">
  <h2>Capture a release</h2>
  <p class="pagesub" style="margin-bottom:12px">For anything that arrives outside a feed — paste the URL; the page's own title and summary prefill what you leave blank.</p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="capture">
    <div class="formrow">
      <input type="url" name="url" placeholder="https://…" aria-label="Release URL" required style="min-width:280px">
      <input type="text" name="title" placeholder="Title · optional, scraped if blank" aria-label="Title" style="min-width:220px">
      <input type="text" name="source" placeholder="Source · optional" aria-label="Source">
      <select name="level" aria-label="Jurisdiction">
        <?php foreach ($levels as $k => $v): ?><option value="<?= $k ?>"<?= $k === 'agency' ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <select name="region" aria-label="Region">
        <option value="">Region…</option>
        <?php foreach ($regionLabels as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <select name="doc_type" aria-label="Type">
        <?php foreach ($doctypes as $k => $v): ?><option value="<?= e($k) ?>"<?= $k === 'release' ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Capture</button>
    </div>
  </form>
</div>

<?php if (is_editor($user)): ?>
<div class="panel" id="feeds">
  <h2>The desk's feeds</h2>
  <p class="pagesub" style="margin-bottom:12px">Government newsrooms and wire services mostly publish RSS — polled hourly, kept apart from the newspaper wire. The scraping agent doesn't need a row here; it delivers through <span class="mono">/api/monitor</span> with the token in Settings.</p>
  <table class="tbl">
    <tr><th>Feed</th><th>Jurisdiction</th><th>Region</th><th>Type</th><th>Last fetch</th><th>Status</th><th></th></tr>
    <?php foreach ($feeds as $f): ?>
    <tr style="<?= $f['enabled'] ? '' : 'opacity:.55' ?>">
      <td><strong><?= e($f['name']) ?></strong><div class="mono" style="font-size:12px;word-break:break-all"><?= e($f['url']) ?></div></td>
      <td class="mono"><?= e($levels[$f['level']] ?? $f['level']) ?></td>
      <td class="mono"><?= e($regionLabels[$f['region']] ?? $f['region']) ?></td>
      <td class="mono"><?= e($doctypes[$f['doc_type']] ?? $f['doc_type']) ?></td>
      <td class="mono"><?= $f['last_fetched_at'] ? e(fmt_date($f['last_fetched_at'], 'M j, g:i a')) : '—' ?></td>
      <td><span class="chip <?= str_starts_with((string) $f['last_status'], 'error') ? 'chip--error' : 'chip--ok' ?>"><?= e($f['last_status'] ?: 'never fetched') ?></span></td>
      <td style="white-space:nowrap">
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="feed_fetch"><input type="hidden" name="feed_id" value="<?= (int) $f['id'] ?>"><button class="btn btn--ghost btn--small" type="submit">Fetch now</button></form>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="feed_toggle"><input type="hidden" name="feed_id" value="<?= (int) $f['id'] ?>"><button class="btn btn--ghost btn--small" type="submit"><?= $f['enabled'] ? 'Pause' : 'Resume' ?></button></form>
        <form method="post" class="inline" onsubmit="return confirm('Remove this feed? Its items stay on the board.')"><?= csrf_field() ?><input type="hidden" name="action" value="feed_delete"><input type="hidden" name="feed_id" value="<?= (int) $f['id'] ?>"><button class="btn btn--danger btn--small" type="submit">Delete</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (!$feeds): ?><p>No feeds yet — add the first below.</p><?php endif; ?>
  <form method="post" style="margin-top:16px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="feed_add">
    <div class="formrow">
      <input type="text" name="name" placeholder="Name — e.g. BC Gov News" aria-label="Feed name" style="min-width:200px">
      <input type="url" name="url" placeholder="https://…/feed" aria-label="Feed URL" style="min-width:260px">
      <select name="level" aria-label="Jurisdiction">
        <?php foreach ($levels as $k => $v): ?><option value="<?= $k ?>"<?= $k === 'provincial' ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <select name="region" aria-label="Region">
        <option value="">Region…</option>
        <?php foreach ($regionLabels as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <select name="doc_type" aria-label="Type">
        <?php foreach ($doctypes as $k => $v): ?><option value="<?= e($k) ?>"<?= $k === 'release' ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn--outline" type="submit">Add the feed</button>
    </div>
  </form>
</div>
<?php endif; ?>

<?php admin_footer(); ?>
