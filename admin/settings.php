<?php
/** Site settings: identity, rail data, newsletter copy, ads, cron. Admin only. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

$textKeys = [
    'site_title', 'tagline', 'meta_description', 'footer_line', 'weather_line',
    'newsletter_heading', 'newsletter_copy', 'markets_note', 'analytics_code',
    'breaking_label', 'breaking_url', 'contact_email',
    'field_notes_text', 'field_notes_url',
    'ad_top', 'ad_rail', 'ad_article',
    'ai_model', 'ai_disclosure', 'monitor_retention_days',
    'ga4_property_id', 'gsc_site_url',
];
$jsonKeys = ['regions', 'markets', 'weather_today', 'traffic_items', 'events_items'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'new_cron_secret') {
        set_setting('cron_secret', bin2hex(random_bytes(16)));
        pp_audit('cron_secret.rotated', 'settings');
        flash_set('New cron secret generated. Update the cron job URL to match.');
        redirect('settings.php');
    }
    if (($_POST['action'] ?? '') === 'new_monitor_token' && pp_is_hub()) {
        set_setting('monitor_token', bin2hex(random_bytes(24)));
        pp_audit('monitor_token.rotated', 'settings');
        flash_set('New ingest token generated. Give it to the scraping agent — the old one stops working now.');
        redirect('settings.php');
    }

    $changed = [];
    foreach ($textKeys as $key) {
        if (isset($_POST[$key])) {
            $new = trim((string) $_POST[$key]);
            if ($new !== setting($key)) {
                $changed[] = $key;
            }
            set_setting($key, $new);
        }
    }
    // Agent auto-queue checkboxes (hub): unchecked boxes don't POST, so the
    // marker field tells us the panel was on the submitted form at all.
    if (pp_is_hub() && isset($_POST['agent_auto_marker'])) {
        foreach (['linkify', 'seo_meta', 'tagger'] as $k) {
            $new = isset($_POST["auto_agent_$k"]) ? '1' : '';
            if ($new !== setting("auto_agent_$k")) {
                $changed[] = "auto_agent_$k";
            }
            set_setting("auto_agent_$k", $new);
        }
    }
    $saveError = false;
    // The hub's security panel. The allowlist is validated hard, and a
    // non-empty list must cover the address making this request — the one
    // rule that makes locking yourself out impossible from this form.
    if (pp_is_hub() && isset($_POST['security_marker'])) {
        $new = isset($_POST['require_totp']) ? '1' : '0';
        if ($new !== setting('require_totp', '1')) {
            $changed[] = 'require_totp';
        }
        set_setting('require_totp', $new);

        $entries = preg_split('/[\s,]+/', trim((string) ($_POST['admin_ip_allowlist'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $bad = array_filter($entries, fn ($x) => !pp_cidr_valid($x));
        $coversMe = $entries === [];
        foreach ($entries as $entry) {
            if (pp_ip_in_cidr(pp_client_ip(), $entry)) {
                $coversMe = true;
            }
        }
        if ($bad) {
            flash_set('The allowlist didn\'t save — these entries aren\'t addresses or CIDR ranges: ' . implode(', ', array_slice($bad, 0, 5)), true);
            $saveError = true;
        } elseif (!$coversMe) {
            flash_set('The allowlist didn\'t save — it would lock YOU out. Your current address is ' . pp_client_ip() . '; add it (or a range covering it) and save again.', true);
            $saveError = true;
        } else {
            $newList = implode("\n", $entries);
            if ($newList !== setting('admin_ip_allowlist')) {
                $changed[] = 'admin_ip_allowlist';
            }
            set_setting('admin_ip_allowlist', $newList);
        }
    }
    foreach ($jsonKeys as $key) {
        if (isset($_POST[$key])) {
            $raw = trim((string) $_POST[$key]);
            $decoded = json_decode($raw, true);
            if ($raw !== '' && !is_array($decoded)) {
                flash_set(strtoupper($key) . " didn't save — that isn't valid JSON. Check the quotes and brackets and try again.", true);
                $saveError = true;
                continue;
            }
            $new = $raw === '' ? '' : json_encode($decoded);
            if ($new !== setting($key)) {
                $changed[] = $key;
            }
            set_setting($key, $new);
        }
    }
    if ($changed) {
        pp_audit('settings.saved', 'settings', implode(', ', $changed));
    }
    if (!$saveError) {
        flash_set('Settings saved. The site reflects them immediately.');
    }
    redirect('settings.php');
}

$cronUrl = site_url() . '/cron/fetch-news.php?key=' . setting('cron_secret');

admin_header('Settings', 'settings');
flash_show();
?>

<h1 class="pagetitle">Settings</h1>
<p class="pagesub">Everything here takes effect immediately. The design system itself — colours, type, the horizon rule — lives in the stylesheet and is deliberately not a setting.</p>

<form method="post">
  <?= csrf_field() ?>
  <!-- First submit in tree order = the Enter-key default. This silent one
       keeps Enter meaning "save" — not the token-rotate button below. -->
  <button type="submit" style="display:none" aria-hidden="true" tabindex="-1"></button>

  <div class="panel">
    <h2>The paper</h2>
    <div class="formgrid">
      <div>
        <label for="site_title">Name</label>
        <input type="text" id="site_title" name="site_title" value="<?= e(setting('site_title')) ?>">
        <label for="tagline">Tagline</label>
        <input type="text" id="tagline" name="tagline" value="<?= e(setting('tagline')) ?>">
        <label for="weather_line">Sky-bar weather line</label>
        <input type="text" id="weather_line" name="weather_line" value="<?= e(setting('weather_line')) ?>">
        <label for="breaking_label">Breaking banner · label (blank = no banner)</label>
        <input type="text" id="breaking_label" name="breaking_label" value="<?= e(setting('breaking_label')) ?>" placeholder="BREAKING">
        <label for="breaking_url">Breaking banner · link</label>
        <input type="text" id="breaking_url" name="breaking_url" value="<?= e(setting('breaking_url')) ?>" placeholder="/story/…">
      </div>
      <div>
        <label for="meta_description">Search description</label>
        <textarea id="meta_description" name="meta_description" style="min-height:64px"><?= e(setting('meta_description')) ?></textarea>
        <label for="footer_line">Footer line</label>
        <textarea id="footer_line" name="footer_line" style="min-height:64px"><?= e(setting('footer_line')) ?></textarea>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>The rail — markets &amp; forecast</h2>
    <div class="formgrid">
      <div>
        <label for="markets">Closing prices · JSON rows of [name, price, change]</label>
        <textarea id="markets" name="markets" class="mono"><?= e(setting('markets')) ?></textarea>
        <label for="markets_note">Prices note</label>
        <input type="text" id="markets_note" name="markets_note" value="<?= e(setting('markets_note')) ?>">
      </div>
      <div>
        <label for="weather_today">Forecast block · JSON with temp, hi, lo, line</label>
        <textarea id="weather_today" name="weather_today" class="mono"><?= e(setting('weather_today')) ?></textarea>
        <label for="traffic_items">Traffic box · JSON rows of [label, link]</label>
        <textarea id="traffic_items" name="traffic_items" class="mono"><?= e(setting('traffic_items')) ?></textarea>
        <label for="events_items">Events box · JSON rows of [label, link, date, venue] — date &amp; venue optional</label>
        <textarea id="events_items" name="events_items" class="mono"><?= e(setting('events_items')) ?></textarea>
        <label for="contact_email">Contact email · shown in the footer</label>
        <input type="text" id="contact_email" name="contact_email" value="<?= e(setting('contact_email')) ?>">
        <label for="field_notes_text">Field notes box · a standing note on the rail</label>
        <textarea id="field_notes_text" name="field_notes_text" style="min-height:64px"><?= e(setting('field_notes_text')) ?></textarea>
        <label for="field_notes_url">Field notes · link</label>
        <input type="text" id="field_notes_url" name="field_notes_url" value="<?= e(setting('field_notes_url')) ?>">
        <p class="help">Blank any field to hide its block.</p>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>The 6 a.m. newsletter</h2>
    <div class="formgrid">
      <div>
        <label for="newsletter_heading">Heading</label>
        <input type="text" id="newsletter_heading" name="newsletter_heading" value="<?= e(setting('newsletter_heading')) ?>">
      </div>
      <div>
        <label for="newsletter_copy">Pitch · one or two sentences</label>
        <textarea id="newsletter_copy" name="newsletter_copy" class="prose" style="min-height:64px"><?= e(setting('newsletter_copy')) ?></textarea>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>The wire</h2>
    <label for="regions">Region tabs · JSON of {"key": "Label"}</label>
    <textarea id="regions" name="regions" class="mono"><?= e(setting('regions')) ?></textarea>
    <p class="help">Region keys are stored on every fetched news item — changing a key later orphans that region's items. Add and label freely; rename with care.</p>
  </div>

  <?php if (pp_is_hub()): ?>
  <div class="panel">
    <h2>The monitoring desk</h2>
    <label>Ingest endpoint · the contract with the scraping agent</label>
    <p class="mono" style="font-size:12.5px;word-break:break-all;background:var(--pp-cloudbank);padding:10px 12px;border:1px solid var(--pp-board)">POST <?= e(site_url()) ?>/api/monitor<br>Authorization: Bearer <?= setting('monitor_token') ? e(setting('monitor_token')) : '&lt;generate a token below&gt;' ?></p>
    <!-- A named submit of the surrounding form, not a nested <form> — browsers
         drop a form-inside-a-form and everything after it stops submitting. -->
    <button class="btn btn--ghost btn--small" type="submit" name="action" value="new_monitor_token" onclick="return confirm('Generate a new token? The scraping agent must switch to it — the old one stops working immediately.')"><?= setting('monitor_token') ? 'Rotate the token' : 'Generate the token' ?></button>
    <p class="help">The agent POSTs a JSON array of items — {source, level, region, doc_type, title, url, summary?, body_excerpt?, published_at?}. Until a token exists the endpoint answers 503 and nothing can deliver.</p>
    <label for="monitor_retention_days" style="margin-top:12px">Retention · days before untouched and dismissed items prune (blank = 180)</label>
    <input type="text" id="monitor_retention_days" name="monitor_retention_days" class="mono" value="<?= e(setting('monitor_retention_days')) ?>" placeholder="180" style="max-width:120px">
    <p class="help">Flagged, claimed and used items never prune — they're the paper trail behind published stories.</p>
  </div>
  <?php endif; ?>

  <?php if (pp_is_hub()): ?>
  <div class="panel">
    <h2>The agent desk</h2>
    <input type="hidden" name="agent_auto_marker" value="1">
    <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:.04em;margin:6px 0">
      <input type="checkbox" name="auto_agent_linkify" value="1" style="width:auto"<?= setting('auto_agent_linkify') === '1' ? ' checked' : '' ?>>
      Queue the <strong>linkifier</strong> automatically when a story publishes
    </label>
    <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:.04em;margin:6px 0">
      <input type="checkbox" name="auto_agent_seo_meta" value="1" style="width:auto"<?= setting('auto_agent_seo_meta') === '1' ? ' checked' : '' ?>>
      Queue the <strong>SEO meta writer</strong> automatically when a story publishes
    </label>
    <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:.04em;margin:6px 0">
      <input type="checkbox" name="auto_agent_tagger" value="1" style="width:auto"<?= setting('auto_agent_tagger') === '1' ? ' checked' : '' ?>>
      Queue the <strong>tagger</strong> automatically when a story publishes
    </label>
    <p class="help">Auto-queue only files the task — every proposal still waits for an editor on the agent desk. Auto-<em>apply</em> deliberately doesn't exist.</p>
  </div>
  <?php endif; ?>

  <?php if (pp_is_hub()): ?>
  <div class="panel">
    <h2>Security</h2>
    <input type="hidden" name="security_marker" value="1">
    <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:.04em;margin:6px 0">
      <input type="checkbox" name="require_totp" value="1" style="width:auto"<?= setting('require_totp', '1') !== '0' ? ' checked' : '' ?>>
      Require <strong>two-step sign-in</strong> for control-room administrators
    </label>
    <p class="help">On by default. An administrator without two-step is funnelled to their profile page to set it up — nothing else opens until it's on. Editors and authors can enable it voluntarily from their profiles.</p>
    <label for="admin_ip_allowlist" style="margin-top:12px">Control-room address allowlist · blank = open from anywhere (the default)</label>
    <textarea id="admin_ip_allowlist" name="admin_ip_allowlist" class="mono" style="min-height:64px" placeholder="203.0.113.7&#10;198.51.100.0/24"><?= e(setting('admin_ip_allowlist')) ?></textarea>
    <p class="help">One IP or CIDR range per line. Applies to signed-in <em>hub</em> pages only — the papers' newsrooms never check it. The form refuses a list that doesn't cover your own current address (<span class="mono"><?= e(pp_client_ip()) ?></span>), so you can't lock yourself out from here. Careful with home connections — most change address without notice.</p>
  </div>
  <?php endif; ?>

  <div class="panel">
    <h2>The research desk</h2>
    <?php if (pp_is_hub()): ?>
    <label for="ai_model">Model id · blank uses <span class="mono">claude-opus-5</span></label>
    <input type="text" id="ai_model" name="ai_model" class="mono" value="<?= e(setting('ai_model')) ?>" placeholder="claude-opus-5">
    <p class="help">The API key is not a setting — it lives in <code>config.php</code> on the server (<code>anthropic_api_key</code>), never in the database.</p>
    <?php else: ?>
    <label for="ai_disclosure">AI-assistance disclosure · blank = off (the default)</label>
    <input type="text" id="ai_disclosure" name="ai_disclosure" value="<?= e(setting('ai_disclosure')) ?>" placeholder="1">
    <p class="help">Printed under the byline on stories drafted with the desk's help (<span class="mono">origin = ai</span>). Set to <span class="mono">1</span> for the standard line — “Prepared with AI assistance and reviewed by an editor.” — or write your own wording. Every AI-assisted story is verified, rewritten and signed by its journalist either way; this only chooses whether readers see a note.</p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Advertising &amp; analytics</h2>
    <div class="formgrid">
      <div>
        <label for="ad_top">Ad slot · top of front page</label>
        <textarea id="ad_top" name="ad_top" class="mono"><?= e(setting('ad_top')) ?></textarea>
        <label for="ad_rail">Ad slot · rail</label>
        <textarea id="ad_rail" name="ad_rail" class="mono"><?= e(setting('ad_rail')) ?></textarea>
      </div>
      <div>
        <label for="ad_article">Ad slot · after story text</label>
        <textarea id="ad_article" name="ad_article" class="mono"><?= e(setting('ad_article')) ?></textarea>
        <label for="analytics_code">Analytics snippet · pasted into &lt;head&gt;</label>
        <textarea id="analytics_code" name="analytics_code" class="mono"><?= e(setting('analytics_code')) ?></textarea>
        <label for="ga4_property_id">GA4 property id · numbers only, for the nightly pull</label>
        <input type="text" id="ga4_property_id" name="ga4_property_id" class="mono" value="<?= e(setting('ga4_property_id')) ?>" placeholder="123456789">
        <label for="gsc_site_url">Search Console property · e.g. sc-domain:example.ca</label>
        <input type="text" id="gsc_site_url" name="gsc_site_url" class="mono" value="<?= e(setting('gsc_site_url')) ?>" placeholder="sc-domain:example.ca">
        <p class="help">Both feed the control room's Analytics page. The network's service account must be Viewer on the GA4 property and Restricted on the Search Console property — its email shows on the hub's Analytics page.</p>
      </div>
    </div>
    <p class="help">Paste the embed code your ad or analytics provider gives you. Empty slots render nothing — no placeholder ever shows to readers.</p>
  </div>

  <p style="margin-top:20px"><button class="btn" type="submit">Save the settings</button></p>
</form>

<div class="panel">
  <h2>The cron job</h2>
  <p style="margin:0 0 8px">Fetches every enabled feed, prunes stale wire items, and publishes scheduled stories. Run it daily (or hourly) either way:</p>
  <p class="mono" style="font-size:12.5px;word-break:break-all;background:var(--pp-cloudbank);padding:10px 12px;border:1px solid var(--pp-board)">php <?= e(PP_ROOT) ?>/cron/fetch-news.php</p>
  <p class="mono" style="font-size:12.5px;word-break:break-all;background:var(--pp-cloudbank);padding:10px 12px;border:1px solid var(--pp-board)"><?= e($cronUrl) ?></p>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="new_cron_secret"><button class="btn btn--ghost btn--small" type="submit" onclick="return confirm('Generate a new secret? The old cron URL stops working.')">Generate a new secret</button></form>
</div>

<?php admin_footer(); ?>
