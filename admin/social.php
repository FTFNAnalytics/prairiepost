<?php
/**
 * The social desk — prepare a published story for X, Threads, Instagram
 * and Facebook, then record what actually went out. Hub only, editors up.
 *
 * The desk deliberately does not hold platform credentials and never
 * posts on the network's behalf. It drafts the post (the research desk's
 * model suggests text when the hub has a key; a plain template stands in
 * when it doesn't), sizes an image, opens the platform's own composer,
 * and records the human's word that it was posted. The tracker view
 * reads those records back as a grid.
 *
 * Images are made in the browser: the story's cover cropped to each
 * platform's aspect, or a headline card drawn from the paper's palette —
 * both plain canvas, uploaded through the same endpoint as any admin
 * image. No image service, no external calls.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_editor();
if (!pp_is_hub()) {
    redirect('index.php');
}

const PP_SOCIAL_PROMPTS = [
    'x'         => 'X (Twitter): at most 280 characters. Punchy and attention-grabbing. One or two relevant hashtags. Do not include the article URL in the content; it is attached separately.',
    'threads'   => 'Threads: conversational and engaging, up to 500 characters. Natural, plain tone. No hashtags needed.',
    'instagram' => 'Instagram: an engaging caption with a strong first line. Three to five relevant hashtags at the end.',
    'facebook'  => 'Facebook: community-oriented, two or three sentences, ending with a question that invites discussion. Include the article link at the end.',
];

/** A serviceable post with no model: headline, trimmed lede, link where the platform wants one. */
function pp_social_fallback(string $platform, array $post, string $url, string $paper): array
{
    $lede = excerpt((string) ($post['lede'] ?? ''), 160);
    $tag = '#' . preg_replace('/[^A-Za-z0-9]/', '', (string) ($post['category_name'] ?? 'News'));
    $content = match ($platform) {
        'x'         => $post['title'] . ($lede !== '' ? " — {$lede}" : '') . " {$tag}",
        'threads'   => $post['title'] . ($lede !== '' ? "\n\n{$lede}" : ''),
        'instagram' => $post['title'] . ($lede !== '' ? "\n\n{$lede}" : '') . "\n\n{$tag} #{$paper}",
        default     => $post['title'] . ($lede !== '' ? "\n\n{$lede}" : '') . "\n\nWhat do you think? {$url}",
    };
    return ['title' => mb_substr((string) $post['title'], 0, 60), 'content' => $content];
}

/* --- POST: save one card, or suggest text for one card ------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action   = (string) ($_POST['action'] ?? '');
    $postId   = (int) ($_POST['post_id'] ?? 0);
    $platform = (string) ($_POST['platform'] ?? '');

    $stmt = db()->prepare('SELECT ' . PP_POST_COLS . ' FROM posts p' . PP_POST_JOINS . " WHERE p.id = ? AND p.status = 'published'");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    if ($action === 'suggest') {
        header('Content-Type: application/json');
        if (!$post || !isset(pp_social_platforms()[$platform])) {
            echo json_encode(['ok' => false, 'error' => 'That story or platform is not available.']);
            exit;
        }
        $url = pp_post_public_url($post);
        $paperStmt = db()->prepare('SELECT name FROM sites WHERE id = ?');
        $paperStmt->execute([(int) ($post['canonical_site_id'] ?? 0) ?: (int) (db()->query('SELECT site_id FROM post_sites WHERE post_id = ' . (int) $post['id'] . ' ORDER BY site_id LIMIT 1')->fetchColumn() ?: 0)]);
        $paper = (string) ($paperStmt->fetchColumn() ?: setting('site_title', 'the paper'));

        if (pp_ai_enabled()) {
            $system = 'You are the social media manager for ' . $paper . ', a Canadian news publication. '
                    . 'Write a social media post promoting one of its articles. Base it only on the headline and excerpt provided; add nothing they do not support.';
            $userMsg = "Article title: {$post['title']}\n"
                     . 'Article excerpt: ' . excerpt((string) ($post['lede'] ?? '') . ' ' . (string) ($post['body'] ?? ''), 400) . "\n"
                     . "Article URL: {$url}\n\n"
                     . 'Platform requirements: ' . PP_SOCIAL_PROMPTS[$platform] . "\n\n"
                     . 'Return JSON with "title" (a short hook, at most 60 characters) and "content" (the full post text).';
            $res = pp_ai_message($system, [['role' => 'user', 'content' => $userMsg]], [
                'schema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'content' => ['type' => 'string']], 'required' => ['title', 'content'], 'additionalProperties' => false],
                'timeout' => 60,
            ]);
            if ($res['ok']) {
                $j = json_decode((string) $res['text'], true);
                if (is_array($j) && isset($j['content'])) {
                    echo json_encode(['ok' => true, 'title' => (string) ($j['title'] ?? ''), 'content' => (string) $j['content'], 'via' => 'model']);
                    exit;
                }
            }
        }
        $fb = pp_social_fallback($platform, $post, $url, preg_replace('/[^A-Za-z0-9]/', '', $paper));
        echo json_encode(['ok' => true, 'title' => $fb['title'], 'content' => $fb['content'], 'via' => 'template']);
        exit;
    }

    if ($action === 'save' && $post && isset(pp_social_platforms()[$platform])) {
        pp_social_save((int) $post['id'], $platform, [
            'post_title'   => (string) ($_POST['post_title'] ?? ''),
            'post_content' => (string) ($_POST['post_content'] ?? ''),
            'image_url'    => (string) ($_POST['image_url'] ?? ''),
            'is_posted'    => !empty($_POST['is_posted']),
        ]);
        pp_audit('social.saved', mb_substr((string) $post['title'], 0, 120), $platform . (!empty($_POST['is_posted']) ? ' · confirmed posted' : ''));
        flash_set(pp_social_platforms()[$platform] . ' card saved.');
        redirect('social.php?post=' . (int) $post['id'] . '#card-' . $platform);
    }
    redirect('social.php');
}

/* --- GET: the desk, or the tracker --------------------------------------- */
$view = (string) ($_GET['view'] ?? 'share');

// Published stories network-wide, newest first, labelled with their paper.
$stories = db()->query(
    'SELECT p.id, p.title, p.published_at,
            COALESCE(cs.name, (SELECT s2.name FROM post_sites ps2 JOIN sites s2 ON s2.id = ps2.site_id
                               WHERE ps2.post_id = p.id ORDER BY ps2.site_id LIMIT 1)) AS paper
     FROM posts p
     LEFT JOIN sites cs ON cs.id = p.canonical_site_id
     WHERE p.status = \'published\' AND p.published_at <= \'' . now() . '\'
     ORDER BY p.published_at DESC LIMIT 100'
)->fetchAll();

$selected = null;
$shares = [];
$publicUrl = '';
$palette = ['ink' => '#1A1A1A', 'paper' => '#F5F4F0', 'accent' => '#8A3033'];
$paperName = '';
$selId = (int) ($_GET['post'] ?? 0);
if ($selId > 0) {
    $stmt = db()->prepare('SELECT ' . PP_POST_COLS . ' FROM posts p' . PP_POST_JOINS . " WHERE p.id = ? AND p.status = 'published'");
    $stmt->execute([$selId]);
    $selected = $stmt->fetch() ?: null;
}
if ($selected) {
    $shares = pp_social_shares((int) $selected['id']);
    $publicUrl = pp_post_public_url($selected);
    $siteId = (int) ($selected['canonical_site_id'] ?? 0);
    if ($siteId === 0) {
        $siteId = (int) (db()->query('SELECT site_id FROM post_sites WHERE post_id = ' . (int) $selected['id'] . ' ORDER BY site_id LIMIT 1')->fetchColumn() ?: 0);
    }
    $srow = db()->prepare('SELECT name, slug FROM sites WHERE id = ?');
    $srow->execute([$siteId]);
    $site = $srow->fetch() ?: ['name' => '', 'slug' => ''];
    $paperName = (string) $site['name'];
    // The headline card borrows the paper's own palette where one exists.
    $pfile = PP_ROOT . '/assets/sites/' . $site['slug'] . '/palette.json';
    if (is_file($pfile)) {
        $p = json_decode((string) file_get_contents($pfile), true)['palette'] ?? [];
        $palette['ink'] = (string) ($p['ink'] ?? $palette['ink']);
        $palette['paper'] = (string) ($p['paper'] ?? $palette['paper']);
        foreach ($p as $k => $v) {
            if (!in_array($k, ['ink', 'paper', 'muted'], true) && is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v)) {
                $palette['accent'] = $v;
                break;
            }
        }
    }
}

/* Tracker data: the latest published stories against every platform. */
$trackerRows = [];
if ($view === 'tracker') {
    $trackerRows = db()->query(
        'SELECT p.id, p.title, p.published_at,
                COALESCE(cs.name, (SELECT s2.name FROM post_sites ps2 JOIN sites s2 ON s2.id = ps2.site_id
                                   WHERE ps2.post_id = p.id ORDER BY ps2.site_id LIMIT 1)) AS paper
         FROM posts p
         LEFT JOIN sites cs ON cs.id = p.canonical_site_id
         WHERE p.status = \'published\' AND p.published_at <= \'' . now() . '\'
         ORDER BY p.published_at DESC LIMIT 60'
    )->fetchAll();
    $postedMap = [];
    foreach (db()->query('SELECT post_id, platform, is_posted FROM social_shares') as $r) {
        $postedMap[(int) $r['post_id']][$r['platform']] = (int) $r['is_posted'] === 1;
    }
}

admin_header('Social desk', 'social');
flash_show();
?>

<div class="headrow">
  <h1 class="pagetitle">Social desk</h1>
  <div class="tabs">
    <a href="social.php"<?= $view !== 'tracker' ? ' aria-current="page"' : '' ?>>Prepare &amp; share</a>
    <a href="social.php?view=tracker"<?= $view === 'tracker' ? ' aria-current="page"' : '' ?>>Tracker</a>
  </div>
</div>

<?php if ($view === 'tracker'): ?>
<p class="pagesub">The latest published stories across every paper, and where each has been posted. A green check means a person confirmed the post on that platform; a dash means it hasn't been.</p>
<div class="panel">
  <table class="tbl">
    <thead><tr><th>Story</th><th>Paper</th><th>Published</th>
      <?php foreach (pp_social_platforms() as $k => $label): ?><th style="text-align:center"><?= e($label) ?></th><?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($trackerRows as $r): ?>
      <tr>
        <td><a href="social.php?post=<?= (int) $r['id'] ?>"><?= e(mb_strimwidth((string) $r['title'], 0, 72, '…')) ?></a></td>
        <td><?= e((string) $r['paper']) ?></td>
        <td class="mono" style="white-space:nowrap"><?= e(fmt_date($r['published_at'])) ?></td>
        <?php foreach (pp_social_platforms() as $k => $label): ?>
        <td style="text-align:center"><?= !empty($postedMap[(int) $r['id']][$k])
            ? '<span class="chip chip--published">✓</span>'
            : '<span style="color:var(--color-neutral-600)">—</span>' ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php else: ?>
<p class="pagesub">Pick a published story, draft a post for each platform, size an image, then share through the platform's own composer. Nothing posts by itself — the checkbox records what you did, and the tracker reads it back.</p>

<form method="get" class="panel" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
  <div style="flex:1;min-width:320px">
    <label for="post">Published story</label>
    <select id="post" name="post" onchange="this.form.submit()">
      <option value="">Choose a story…</option>
      <?php foreach ($stories as $s): ?>
      <option value="<?= (int) $s['id'] ?>"<?= $selected && (int) $selected['id'] === (int) $s['id'] ? ' selected' : '' ?>>
        <?= e(mb_strimwidth((string) $s['title'], 0, 80, '…')) ?> — <?= e((string) $s['paper']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <noscript><button class="btn btn--ghost" type="submit">Open</button></noscript>
</form>

<?php if ($selected): ?>
<p class="help">Shares link to <span class="mono"><?= e($publicUrl) ?></span></p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:16px">
<?php foreach (pp_social_platforms() as $key => $label):
    $sh = $shares[$key] ?? [];
    $posted = !empty($sh['is_posted']); ?>
  <div class="panel" id="card-<?= e($key) ?>" data-platform="<?= e($key) ?>" style="<?= $posted ? 'border-color:#2E7D46;' : '' ?>">
    <div class="headrow" style="margin-bottom:8px">
      <h2 style="margin:0"><?= e($label) ?></h2>
      <?php if ($posted): ?><span class="chip chip--published">Posted<?= !empty($sh['posted_at']) ? ' · ' . e(fmt_date($sh['posted_at'], 'M j')) : '' ?></span><?php endif; ?>
    </div>
    <form method="post" class="social-card">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="post_id" value="<?= (int) $selected['id'] ?>">
      <input type="hidden" name="platform" value="<?= e($key) ?>">
      <input type="hidden" name="image_url" class="js-image-url" value="<?= e((string) ($sh['image_url'] ?? '')) ?>">

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
        <button type="button" class="btn btn--ghost btn--small js-suggest"><?= empty($sh['post_content']) ? 'Suggest post' : 'Suggest again' ?></button>
        <?php if (!empty($selected['image'])): ?>
        <button type="button" class="btn btn--ghost btn--small js-fit-cover">Fit cover image</button>
        <?php endif; ?>
        <button type="button" class="btn btn--ghost btn--small js-headline-card">Headline card</button>
      </div>
      <p class="help js-spec-note" style="margin:0 0 8px"></p>

      <label>Post title / hook</label>
      <input type="text" name="post_title" value="<?= e((string) ($sh['post_title'] ?? '')) ?>" maxlength="255">
      <label style="margin-top:8px">Post content</label>
      <textarea name="post_content" rows="4" class="js-content"><?= e((string) ($sh['post_content'] ?? '')) ?></textarea>

      <div class="js-preview" style="margin-top:8px">
        <?php if (!empty($sh['image_url'])): ?><img src="<?= e((string) $sh['image_url']) ?>" alt="" style="max-width:100%;border-radius:3px"><?php endif; ?>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <button type="button" class="btn btn--small js-share">Share to <?= e($label) ?></button>
        <a class="btn btn--ghost btn--small js-download" download="<?= e($key) ?>-<?= (int) $selected['id'] ?>.png"
           style="<?= empty($sh['image_url']) ? 'display:none' : '' ?>" href="<?= e((string) ($sh['image_url'] ?? '#')) ?>">Download image</a>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;padding-top:10px;border-top:1px solid var(--color-divider)">
        <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;margin:0">
          <input type="checkbox" name="is_posted" value="1" style="width:auto"<?= $posted ? ' checked' : '' ?>>
          Confirmed posted
        </label>
        <button class="btn btn--small" type="submit">Save</button>
      </div>
    </form>
  </div>
<?php endforeach; ?>
</div>

<script>
(function () {
  'use strict';
  var CSRF = <?= json_encode(csrf_token()) ?>;
  var POST_ID = <?= (int) $selected['id'] ?>;
  var ARTICLE_URL = <?= json_encode($publicUrl) ?>;
  var COVER = <?= json_encode((string) ($selected['image'] ?? '')) ?>;
  var HEADLINE = <?= json_encode((string) $selected['title']) ?>;
  var PAPER = <?= json_encode($paperName) ?>;
  var PALETTE = <?= json_encode($palette) ?>;
  // The same platform dimensions the CivicWest desk uses.
  var SPECS = {
    x:         { w: 1200, h: 675,  label: '1200×675 · 16:9' },
    threads:   { w: 1080, h: 1080, label: '1080×1080 · square' },
    instagram: { w: 1080, h: 1080, label: '1080×1080 · square' },
    facebook:  { w: 1200, h: 630,  label: '1200×630 · 1.91:1' }
  };

  function uploadCanvas(canvas, platform, card) {
    return new Promise(function (resolve, reject) {
      canvas.toBlob(function (blob) {
        var fd = new FormData();
        fd.append('csrf', CSRF);
        fd.append('file', new File([blob], platform + '-' + POST_ID + '.png', { type: 'image/png' }));
        fetch('upload.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (j) { j.url ? resolve(j.url) : reject(new Error(j.error || 'upload failed')); })
          .catch(reject);
      }, 'image/png');
    });
  }

  function setImage(card, url) {
    card.querySelector('.js-image-url').value = url;
    card.querySelector('.js-preview').innerHTML =
      '<img src="' + url + '" alt="" style="max-width:100%;border-radius:3px">';
    var dl = card.querySelector('.js-download');
    dl.href = url; dl.style.display = '';
    note(card, 'Image ready — Save stores it on this card.');
  }

  function note(card, text) { card.querySelector('.js-spec-note').textContent = text; }

  function wrapText(ctx, text, maxWidth) {
    var words = text.split(/\s+/), lines = [], line = '';
    words.forEach(function (w) {
      var probe = line === '' ? w : line + ' ' + w;
      if (ctx.measureText(probe).width > maxWidth && line !== '') { lines.push(line); line = w; }
      else { line = probe; }
    });
    if (line !== '') lines.push(line);
    return lines;
  }

  document.querySelectorAll('[data-platform]').forEach(function (card) {
    var platform = card.dataset.platform;
    var spec = SPECS[platform];
    note(card, 'Optimal image: ' + spec.label);

    card.querySelector('.js-suggest').addEventListener('click', function () {
      var btn = this; btn.disabled = true; btn.textContent = 'Thinking…';
      var fd = new FormData();
      fd.append('csrf', CSRF); fd.append('action', 'suggest');
      fd.append('post_id', POST_ID); fd.append('platform', platform);
      fetch('social.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok) { note(card, j.error || 'Suggestion failed.'); return; }
          card.querySelector('[name=post_title]').value = j.title || '';
          card.querySelector('.js-content').value = j.content || '';
          note(card, j.via === 'model' ? 'Drafted by the research desk — edit freely, then Save.' : 'Drafted from the headline and lede (no model key configured) — edit freely, then Save.');
        })
        .catch(function () { note(card, 'Suggestion failed — try again.'); })
        .finally(function () { btn.disabled = false; btn.textContent = 'Suggest again'; });
    });

    var fit = card.querySelector('.js-fit-cover');
    if (fit) fit.addEventListener('click', function () {
      var btn = this; btn.disabled = true;
      var img = new Image();
      img.onload = function () {
        var c = document.createElement('canvas'); c.width = spec.w; c.height = spec.h;
        var ctx = c.getContext('2d');
        var sa = img.width / img.height, ta = spec.w / spec.h, sx, sy, sw, sh;
        if (sa > ta) { sh = img.height; sw = sh * ta; sx = (img.width - sw) / 2; sy = 0; }
        else { sw = img.width; sh = sw / ta; sx = 0; sy = (img.height - sh) / 2; }
        ctx.drawImage(img, sx, sy, sw, sh, 0, 0, spec.w, spec.h);
        uploadCanvas(c, platform, card).then(function (url) { setImage(card, url); })
          .catch(function (e) { note(card, 'Cover fit failed: ' + e.message); })
          .finally(function () { btn.disabled = false; });
      };
      img.onerror = function () { note(card, 'The cover image could not be loaded.'); btn.disabled = false; };
      img.src = COVER;
    });

    card.querySelector('.js-headline-card').addEventListener('click', function () {
      var btn = this; btn.disabled = true;
      var c = document.createElement('canvas'); c.width = spec.w; c.height = spec.h;
      var ctx = c.getContext('2d');
      var pad = Math.round(spec.w * 0.07);
      ctx.fillStyle = PALETTE.paper; ctx.fillRect(0, 0, spec.w, spec.h);
      ctx.fillStyle = PALETTE.accent; ctx.fillRect(0, 0, spec.w, Math.round(spec.h * 0.018));
      ctx.fillStyle = PALETTE.ink;
      ctx.font = '600 ' + Math.round(spec.h * 0.045) + 'px Georgia, serif';
      ctx.fillText((PAPER || '').toUpperCase(), pad, pad + Math.round(spec.h * 0.05));
      var size = Math.round(spec.h * 0.105);
      ctx.font = '700 ' + size + 'px Georgia, serif';
      var lines = wrapText(ctx, HEADLINE, spec.w - pad * 2);
      while (lines.length * size * 1.18 > spec.h * 0.62 && size > 24) {
        size -= 6; ctx.font = '700 ' + size + 'px Georgia, serif';
        lines = wrapText(ctx, HEADLINE, spec.w - pad * 2);
      }
      var y = Math.round(spec.h * 0.30);
      lines.forEach(function (l) { ctx.fillText(l, pad, y); y += Math.round(size * 1.18); });
      ctx.fillStyle = PALETTE.accent;
      ctx.font = '600 ' + Math.round(spec.h * 0.035) + 'px Georgia, serif';
      ctx.fillText(ARTICLE_URL.replace(/^https:\/\//, '').split('/')[0], pad, spec.h - pad);
      uploadCanvas(c, platform, card).then(function (url) { setImage(card, url); })
        .catch(function (e) { note(card, 'Headline card failed: ' + e.message); })
        .finally(function () { btn.disabled = false; });
    });

    card.querySelector('.js-share').addEventListener('click', function () {
      var text = card.querySelector('.js-content').value || HEADLINE;
      if (platform === 'x') {
        window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent(text) + '&url=' + encodeURIComponent(ARTICLE_URL), '_blank', 'width=600,height=420');
      } else if (platform === 'threads') {
        window.open('https://threads.net/intent/post?text=' + encodeURIComponent(text + ' ' + ARTICLE_URL), '_blank', 'width=600,height=420');
      } else if (platform === 'facebook') {
        window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(ARTICLE_URL) + '&quote=' + encodeURIComponent(text), '_blank', 'width=600,height=420');
      } else {
        navigator.clipboard.writeText(text + '\n\n' + ARTICLE_URL).then(function () {
          note(card, 'Caption copied — paste it into Instagram with the downloaded image.');
        });
      }
    });
  });
})();
</script>
<?php endif; ?>
<?php endif; ?>

<?php admin_footer(); ?>
