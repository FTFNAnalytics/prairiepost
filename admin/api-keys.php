<?php
/**
 * Agent API keys — the control room's counter for the ingest API.
 *
 * Mints, lists and revokes the bearer tokens that authenticate
 * POST /api/ingest (stories, filed as drafts) and POST /api/ingest-media
 * (featured images). Same table and hashing as tools/make-agent.php on
 * the server — the two stay interchangeable. The database stores only
 * the SHA-256; the raw key is shown exactly once, on the page that
 * created it, and never logged.
 *
 * Hub only, admins only. Everything an agent files with one of these
 * keys lands as a DRAFT behind each paper's publish gate (a desk listed
 * in a site's wire_desks setting is the one exception, and that is a
 * per-paper editorial setting, not a property of the key).
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
$user = require_admin();
if (!pp_is_hub()) {
    http_response_code(404);
    exit('Not found.');
}

$sites = db()->query('SELECT slug, name FROM sites ORDER BY name')->fetchAll();
$freshToken = null;   // set only on the request that created it
$freshName = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $name = slugify((string) ($_POST['name'] ?? ''));
        $picked = array_values(array_intersect(
            array_map('slugify', (array) ($_POST['sites'] ?? [])),
            array_column($sites, 'slug')
        ));
        // Trim-and-filter BEFORE slugify: slugify('') returns its 'story'
        // fallback, which would turn an empty field into a desk lookup.
        $desks = array_map('slugify', array_filter(array_map('trim', explode(',', (string) ($_POST['desks'] ?? '')))));
        $bad = null;
        if ($name === '') {
            $bad = 'The key needs a name (letters, digits, hyphens) — name it after the agent that will hold it.';
        } elseif (!$picked) {
            $bad = 'Pick at least one paper — a key only writes to the papers it is scoped to.';
        } else {
            $selD = db()->prepare('SELECT 1 FROM categories WHERE slug = ?');
            foreach ($desks as $d) {
                $selD->execute([$d]);
                if (!$selD->fetch()) {
                    $bad = "There is no desk '{$d}'. Desks are created at launch; leave the field empty for any desk.";
                    break;
                }
            }
            $dup = db()->prepare('SELECT 1 FROM ingest_agents WHERE name = ?');
            $dup->execute([$name]);
            if ($bad === null && $dup->fetch()) {
                $bad = "A key named '{$name}' already exists. Revoke it, or pick a new name.";
            }
        }
        if ($bad !== null) {
            flash_set($bad, true);
            redirect('api-keys.php');
        }
        $token = 'hermes_' . bin2hex(random_bytes(28));
        db()->prepare('INSERT INTO ingest_agents (name, token_hash, sites, desks, enabled, created_at)
            VALUES (?, ?, ?, ?, 1, ?)')
            ->execute([$name, hash('sha256', $token), implode(',', $picked), implode(',', $desks), now()]);
        pp_audit('apikey.created', $name, 'sites: ' . implode(', ', $picked) . ($desks ? ' · desks: ' . implode(', ', $desks) : ''));
        // No redirect on purpose: the raw key exists only in this response.
        $freshToken = $token;
        $freshName = $name;
    }

    if ($action === 'revoke' || $action === 'enable') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM ingest_agents WHERE id = ?');
        $stmt->execute([$id]);
        if ($row = $stmt->fetch()) {
            db()->prepare('UPDATE ingest_agents SET enabled = ? WHERE id = ?')
                ->execute([$action === 'enable' ? 1 : 0, $id]);
            pp_audit('apikey.' . ($action === 'enable' ? 'enabled' : 'revoked'), (string) $row['name']);
            flash_set($action === 'enable'
                ? "'{$row['name']}' is active again — its existing key works on the next request."
                : "'{$row['name']}' is revoked — the key answers 401 from the next request on.");
        }
        redirect('api-keys.php');
    }
}

$keys = db()->query('SELECT * FROM ingest_agents ORDER BY enabled DESC, name')->fetchAll();

admin_header('Agent API keys', 'apikeys');
flash_show();
?>

<h1 class="pagetitle">Agent API keys</h1>
<p class="pagesub">Each key lets one uploading agent file <strong>drafts</strong> to the papers it is scoped to — stories through <code>POST /api/ingest</code>, featured images through <code>POST /api/ingest-media</code>. The database keeps only a hash: a key is shown once, here, when it is minted. Revocation takes effect on the key's next request.</p>

<?php if ($freshToken !== null): ?>
<div class="panel" style="border-color:#0a7d33">
  <h2>The key for <?= e((string) $freshName) ?> — copy it now</h2>
  <p><code style="font-size:15px;word-break:break-all;user-select:all"><?= e($freshToken) ?></code></p>
  <p class="pagesub">This is the only time it will ever be shown. Hand it to exactly one agent; if it leaks, revoke it below and mint a fresh one.</p>
</div>
<?php endif; ?>

<div class="panel">
  <h2>Mint a key</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="formrow">
      <label>Key name <input type="text" name="name" placeholder="uploader-ottawa" required></label>
      <label>Desks (optional, comma-separated slugs; empty = any desk) <input type="text" name="desks" placeholder="local-news, culture"></label>
    </div>
    <fieldset style="border:0;padding:0;margin:12px 0">
      <legend style="font-weight:600;margin-bottom:6px">Papers this key may file to</legend>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:4px 16px">
        <?php foreach ($sites as $s): ?>
        <label style="font-weight:400"><input type="checkbox" name="sites[]" value="<?= e($s['slug']) ?>"> <?= e($s['name']) ?></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <button class="btn" type="submit">Mint the key</button>
  </form>
</div>

<div class="panel">
  <h2>Existing keys</h2>
  <?php if (!$keys): ?><p class="pagesub">No keys yet.</p><?php else: ?>
  <table class="tbl">
    <tr><th>Name</th><th>Papers</th><th>Desks</th><th>Status</th><th>Created</th><th>Last used</th><th></th></tr>
    <?php foreach ($keys as $k): ?>
    <tr>
      <td><?= e($k['name']) ?></td>
      <td><?= e(str_replace(',', ', ', (string) $k['sites'])) ?></td>
      <td><?= e($k['desks'] !== '' ? str_replace(',', ', ', (string) $k['desks']) : 'any') ?></td>
      <td><?= (int) $k['enabled'] === 1 ? 'active' : '<strong>revoked</strong>' ?></td>
      <td><?= e(substr((string) $k['created_at'], 0, 10)) ?></td>
      <td><?= e($k['last_used_at'] ? substr((string) $k['last_used_at'], 0, 16) : 'never') ?></td>
      <td>
        <form method="post" class="inline">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
          <input type="hidden" name="action" value="<?= (int) $k['enabled'] === 1 ? 'revoke' : 'enable' ?>">
          <button class="btn btn--ghost btn--small" type="submit"><?= (int) $k['enabled'] === 1 ? 'Revoke' : 'Re-enable' ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<div class="panel">
  <h2>How an agent uses a key</h2>
  <p class="pagesub">Upload the graphic first, then file the story with the returned path. Everything lands as a draft in that paper's newsroom. Full contract: <code>docs/api-ingest.md</code> in the repository.</p>
  <pre style="overflow-x:auto;font-size:13px">curl -X POST https://&lt;paper-domain&gt;/api/ingest-media \
  -H "Authorization: Bearer $KEY" \
  -H "Content-Type: image/png" --data-binary @featured.png
# → {"ok":true,"path":"/uploads/2026/09/featured-a1b2c3.png"}

curl -X POST https://&lt;paper-domain&gt;/api/ingest \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"site":"&lt;site-slug&gt;","desk":"local-news","title":"…","lede":"…",
       "body":"&lt;p&gt;…&lt;/p&gt;","image":"/uploads/2026/09/featured-a1b2c3.png",
       "image_caption":"…","image_credit":"…"}'
# → {"ok":true,"id":123,"slug":"…","status":"draft"}</pre>
</div>

<?php admin_footer(); ?>
