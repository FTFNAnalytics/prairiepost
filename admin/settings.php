<?php
/** Site settings: identity, rail data, newsletter copy, ads, cron. Admin only. */
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/indexing.php';
require __DIR__ . '/_layout.php';
require_admin();

$textKeys = [
    'site_title', 'tagline', 'meta_description', 'footer_line',
    'newsletter_heading', 'newsletter_copy', 'markets_note', 'analytics_code',
    'breaking_label', 'breaking_url', 'contact_email',
    'field_notes_text', 'field_notes_url',
    'ad_top', 'ad_rail', 'ad_article', 'ads_txt',
    'about_heading', 'about_standfirst', 'about_body',
    'privacy_heading', 'privacy_body', 'privacy_officer',
    'standards_heading', 'standards_body', 'funding_note',
    'ai_model', 'ai_daily_limit', 'ai_disclosure', 'monitor_retention_days',
    'ga4_property_id', 'gsc_site_url',
];
$jsonKeys = ['regions', 'markets', 'traffic_items', 'events_items'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'new_cron_secret') {
        set_setting('cron_secret', bin2hex(random_bytes(16)));
        pp_audit('cron_secret.rotated', 'settings');
        flash_set('New cron secret generated. Update the cron job URL to match.');
        redirect('settings.php');
    }
    if (($_POST['action'] ?? '') === 'new_indexnow_key') {
        $k = pp_indexnow_generate_key();
        pp_audit('indexnow_key.rotated', 'settings', substr($k, 0, 8) . '…');
        flash_set('IndexNow key generated. Verification file is live at /' . $k . '.txt.');
        redirect('settings.php');
    }
    if (($_POST['action'] ?? '') === 'new_monitor_token' && pp_is_hub()) {
        set_setting('monitor_token', bin2hex(random_bytes(24)));
        pp_audit('monitor_token.rotated', 'settings');
        flash_set('New ingest token generated. Give it to the scraping agent — the old one stops working now.');
        redirect('settings.php');
    }
    // Search engines: the one switch, this paper only (app/indexing.php).
    // Opening re-checks what stands in the way here, whatever the page
    // showed; hiding is never refused.
    if (($_POST['action'] ?? '') === 'indexing_on') {
        $ready = pp_indexing_readiness();
        if ($ready['blockers']) {
            flash_set('Not opened to search engines. ' . $ready['blockers'][0], true);
        } elseif (!pp_indexing_enabled()) {
            set_setting('indexing_enabled', '1');
            pp_audit('indexing.on', 'settings', $ready['address']);
            flash_set(setting('site_title') . ' is open to search engines. Its pages no longer ask them to stay away.');
        }
        redirect('settings.php');
    }
    if (($_POST['action'] ?? '') === 'indexing_off') {
        if (pp_indexing_enabled()) {
            set_setting('indexing_enabled', '0');
            pp_audit('indexing.off', 'settings', site_url());
            flash_set(setting('site_title') . ' is hidden from search engines again. Every page asks them to stay away.');
        }
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
    // Search-engine indexing is not on this form: its own panel above it
    // opens and hides the paper with one button each (indexing_on/_off).

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
        // Session limits: whole numbers, blank = the default, 0 = off.
        foreach (['session_idle_hours' => 720, 'session_max_days' => 365] as $key => $cap) {
            if (isset($_POST[$key])) {
                $raw = trim((string) $_POST[$key]);
                $new = $raw === '' ? '' : (string) min($cap, max(0, (int) $raw));
                if ($new !== setting($key)) {
                    $changed[] = $key;
                }
                set_setting($key, $new);
            }
        }
        if (isset($_POST['ops_alert_email'])) {
            $new = trim((string) $_POST['ops_alert_email']);
            if ($new !== '' && !filter_var($new, FILTER_VALIDATE_EMAIL)) {
                flash_set('The alert address didn\'t save — that isn\'t a working email address.', true);
                $saveError = true;
            } else {
                if ($new !== setting('ops_alert_email')) {
                    $changed[] = 'ops_alert_email';
                }
                set_setting('ops_alert_email', $new);
            }
        }

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
                // Traffic and prices run on a clock from their last change
                // (app/freshness.php); saving Settings for another reason
                // must not make yesterday's figures look current again.
                if (isset(PP_FRESH_FOR[$key])) {
                    set_setting($key . '_saved', $new === '' ? '' : (string) time());
                }
            }
            set_setting($key, $new);
        }
    }
    // The breaking banner's expiry. A banner comes down by itself, at the
    // time the editor picks or six hours after a new one is saved. Saving
    // Settings for another reason changes nothing: a banner whose time has
    // passed, or a launch-day banner that never had one, stays down until
    // an editor picks a time ahead or writes a new banner.
    if (isset($_POST['breaking_until'])) {
        $picked = trim((string) $_POST['breaking_until']);
        $until = '';
        if (setting('breaking_label') === '') {
            $until = '';
        } elseif ($picked !== '') {
            $ts = strtotime($picked);
            if ($ts !== false && $ts > time()) {
                $until = (string) $ts;
            } else {
                flash_set('The banner\'s "show until" time has passed, so the banner is down. Pick a time ahead to put it up.', true);
                $saveError = true;
            }
        } elseif (in_array('breaking_label', $changed, true)) {
            $until = (string) (time() + PP_BREAKING_DEFAULT);
        } else {
            $until = setting('breaking_until');
        }
        if ($until !== setting('breaking_until')) {
            $changed[] = 'breaking_until';
        }
        set_setting('breaking_until', $until);
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

<?php
$ixOn = pp_indexing_enabled();
$ix = pp_indexing_readiness();
$ixLast = pp_indexing_last_change();
$ixName = (string) setting('site_title');
?>
<div class="panel" id="search-engines">
  <h2>Search engines</h2>
  <?php if ($ixOn): ?>
  <p style="margin:0 0 10px"><span class="chip chip--ok">Open</span>
    Search engines may index <?= e($ixName) ?>: its pages no longer ask them to stay away.
    <?php if ($ixLast && $ixLast['action'] === 'indexing.on'): ?>Opened <?= e(fmt_date((string) $ixLast['created_at'], 'M j, Y')) ?> by <?= e((string) $ixLast['user_name']) ?>.<?php endif; ?></p>
  <?php else: ?>
  <p style="margin:0 0 10px"><span class="chip chip--draft">Hidden</span>
    Every page of <?= e($ixName) ?> asks search engines to stay away (<span class="mono">noindex</span>).
    This switch is this paper's alone.
    <?php if ($ixLast && $ixLast['action'] === 'indexing.off'): ?>Hidden <?= e(fmt_date((string) $ixLast['created_at'], 'M j, Y')) ?> by <?= e((string) $ixLast['user_name']) ?>.<?php endif; ?></p>
  <?php endif; ?>
  <?php /* What stands in the way: it refuses Open while hidden, and still shows once open (a story put back up afterwards). */ ?>
  <?php if ($ix['blockers']): ?>
  <p style="margin:0 0 6px"><strong><?= $ixOn ? 'Search engines can see this now:' : 'Not ready yet:' ?></strong></p>
  <ul style="margin:0 0 10px;padding-left:20px">
    <?php foreach ($ix['blockers'] as $why): ?><li><?= e($why) ?></li><?php endforeach; ?>
  </ul>
  <?php if ($ix['live']): ?>
  <details style="margin:0 0 10px"><summary>The demonstration stories still live (<?= count($ix['live']) ?>)</summary>
    <ul style="margin:6px 0 0;padding-left:20px">
      <?php foreach ($ix['live'] as $row): ?><li><a href="post-edit.php?id=<?= (int) $row['id'] ?>"><?= e((string) $row['title']) ?></a> <span class="chip chip--<?= e((string) $row['status']) ?>"><?= e((string) $row['status']) ?></span></li><?php endforeach; ?>
    </ul></details>
  <?php endif; ?>
  <?php elseif (!$ixOn): ?>
  <p style="margin:0 0 10px">Its pages name <span class="mono"><?= e($ix['address']) ?></span> as their address, and none of its demonstration launch stories is live.</p>
  <?php endif; ?>
  <?php if ($ixOn): ?>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="indexing_off">
    <button class="btn btn--ghost" type="submit" onclick="return confirm(<?= e(json_encode('Hide ' . $ixName . ' from search engines? Every page asks them to stay away again, and they drop its pages over the following weeks.')) ?>)">Hide from search engines</button></form>
  <?php else: ?>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="indexing_on">
    <button class="btn" type="submit"<?= $ix['blockers'] ? ' disabled' : '' ?> onclick="return confirm(<?= e(json_encode('Open ' . $ixName . ' to search engines? Its pages stop asking them to stay away, and they start listing them over the following days.')) ?>)">Open to search engines</button></form>
  <?php endif; ?>
  <?php if ($ix['notes']): ?>
  <p class="help" style="margin-top:10px"><?= e(implode(' ', $ix['notes'])) ?></p>
  <?php endif; ?>
  <?php $inKey = pp_indexnow_key(); ?>
  <hr style="border:0;border-top:1px solid var(--pp-board);margin:14px 0">
  <p style="margin:0 0 8px"><strong>IndexNow instant indexing</strong> (Bing, DuckDuckGo, Copilot):
    <?php if ($inKey !== ''): ?>
      <span class="chip chip--ok">Key ready</span>
      <span class="mono"><a href="/<?= e($inKey) ?>.txt" target="_blank">/<?= e($inKey) ?>.txt</a></span>
      <?= $ixOn ? ' — pings automatically on publish.' : ' — waiting until this paper is opened to search engines.' ?>
    <?php else: ?>
      <span class="chip chip--draft">Off</span> Generate a key to ping IndexNow automatically when stories publish.
    <?php endif; ?>
  </p>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="new_indexnow_key">
    <button class="btn btn--ghost btn--small" type="submit"><?= $inKey !== '' ? 'Rotate IndexNow key' : 'Generate IndexNow key' ?></button></form>
</div>

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
        <?php $wx = pp_weather(); $wxCfg = pp_weather_config_from(pp_brand_file()); ?>
        <label>Weather · live, from Environment and Climate Change Canada</label>
        <p class="help" style="margin-top:0"><?php if ($wx): ?>Readers see <strong><?= e($wx['place'] . ' · ' . $wx['temp'] . ($wx['cond'] !== '' ? ' · ' . $wx['cond'] : '')) ?></strong>, <?= e(lcfirst($wx['observed'])) ?>. It refreshes every half hour.
        <?php elseif ($wxCfg): ?>No current reading for <?= e($wxCfg['place']) ?>, so readers see no weather. Readings appear within half an hour of the weather job running, and hide themselves once they are three hours old.
        <?php else: ?>This paper has no weather city, so readers see no weather. One is set in the paper's <span class="mono">palette.json</span>.<?php endif; ?></p>
        <label for="breaking_label">Breaking banner · label (blank = no banner)</label>
        <input type="text" id="breaking_label" name="breaking_label" value="<?= e(setting('breaking_label')) ?>" placeholder="BREAKING">
        <label for="breaking_url">Breaking banner · link</label>
        <input type="text" id="breaking_url" name="breaking_url" value="<?= e(setting('breaking_url')) ?>" placeholder="/story/…">
        <?php $bUntil = setting('breaking_until'); ?>
        <label for="breaking_until">Breaking banner · show until</label>
        <input type="datetime-local" id="breaking_until" name="breaking_until" value="<?= ctype_digit($bUntil) && (int) $bUntil > time() ? e(date('Y-m-d\TH:i', (int) $bUntil)) : '' ?>">
        <p class="help"><?php if (pp_breaking_live()): ?>Up until <?= e(date('M j, g:i a', (int) $bUntil)) ?>.
          <?php elseif (setting('breaking_label') !== ''): ?>Down: it has no time ahead. Pick one to put it back up.
          <?php else: ?>No banner.<?php endif; ?> A banner comes down by itself: at the time you pick here, or six hours after you save a new one with this left blank.</p>
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
    <h2>The rail — markets, traffic &amp; events</h2>
    <div class="formgrid">
      <div>
        <label for="markets">Closing prices · JSON rows of [name, price, change] · shown for 30 hours after you change them</label>
        <textarea id="markets" name="markets" class="mono"><?= e(setting('markets')) ?></textarea>
        <label for="markets_note">Prices note</label>
        <input type="text" id="markets_note" name="markets_note" value="<?= e(setting('markets_note')) ?>">
      </div>
      <div>
        <label for="traffic_items">Traffic box · JSON rows of [label, link] · shown for 12 hours after you change it</label>
        <textarea id="traffic_items" name="traffic_items" class="mono"><?= e(setting('traffic_items')) ?></textarea>
        <label for="events_items">Events box · JSON rows of [label, link, date, venue] · each event shows through its date, so give every one a date like "Dec 4"; venue optional</label>
        <textarea id="events_items" name="events_items" class="mono"><?= e(setting('events_items')) ?></textarea>
        <label for="contact_email">Contact email · for the newsroom's own use; readers' tips go to the <a href="whiteboard.php">whiteboard</a></label>
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
    <p class="help">Two-step sign-in was removed while the network is private. A lost passphrase is recovered on the server with <span class="mono">tools/reset-password.php</span>.</p>
    <label for="admin_ip_allowlist" style="margin-top:12px">Control-room address allowlist · blank = open from anywhere (the default)</label>
    <textarea id="admin_ip_allowlist" name="admin_ip_allowlist" class="mono" style="min-height:64px" placeholder="203.0.113.7&#10;198.51.100.0/24"><?= e(setting('admin_ip_allowlist')) ?></textarea>
    <p class="help">One IP or CIDR range per line. Applies to signed-in <em>hub</em> pages only — the papers' newsrooms never check it. The form refuses a list that doesn't cover your own current address (<span class="mono"><?= e(pp_client_ip()) ?></span>), so you can't lock yourself out from here. Careful with home connections — most change address without notice.</p>
    <div class="formgrid" style="margin-top:12px">
      <div>
        <label for="session_idle_hours">Sign out after · hours idle (blank = 12, 0 = never)</label>
        <input type="text" id="session_idle_hours" name="session_idle_hours" class="mono" value="<?= e(setting('session_idle_hours')) ?>" placeholder="12" style="max-width:120px">
        <label for="session_max_days">Sign out after · days since sign-in (blank = 14, 0 = never)</label>
        <input type="text" id="session_max_days" name="session_max_days" class="mono" value="<?= e(setting('session_max_days')) ?>" placeholder="14" style="max-width:120px">
        <p class="help">Limits for this site's sessions. Everyone can also end their own sessions everywhere from their profile; a passphrase change does it automatically.</p>
      </div>
      <div>
        <label for="ops_alert_email">Watch alerts · email address (blank = dashboard only)</label>
        <input type="text" id="ops_alert_email" name="ops_alert_email" value="<?= e(setting('ops_alert_email')) ?>" placeholder="you@example.com">
        <p class="help">When the five-minute watch finds trouble — a masthead down, a certificate near expiry, a cron gone quiet, a failed backup — it emails this address, at most once every six hours. Needs the SMTP details on the Newsletter page. The <a href="ops.php">Ops page</a> always shows the full picture.</p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="panel">
    <h2>Trust &amp; policy pages</h2>
    <p class="pagesub" style="margin-bottom:12px">Built-in publisher transparency routes (<a href="/about" target="_blank">/about</a>, <a href="/standards" target="_blank">/standards</a>, <a href="/privacy" target="_blank">/privacy</a>) wired into every article's <span class="mono">NewsMediaOrganization</span> JSON-LD. Leave body fields blank to use the bilingual defaults.</p>
    <div class="formgrid">
      <div>
        <label for="about_body">About page body · HTML allowed (<a href="/about" target="_blank">/about</a>)</label>
        <textarea id="about_body" name="about_body" class="prose" style="min-height:80px"><?= e(setting('about_body')) ?></textarea>
        <label for="funding_note">Ownership &amp; funding note · shown on /standards and /about</label>
        <input type="text" id="funding_note" name="funding_note" value="<?= e(setting('funding_note')) ?>" placeholder="Independently published by Civis Media Inc.">
        <label for="privacy_officer">Privacy officer contact · email or name for PIPEDA / Quebec Law 25</label>
        <input type="text" id="privacy_officer" name="privacy_officer" value="<?= e(setting('privacy_officer')) ?>" placeholder="privacy@example.ca">
      </div>
      <div>
        <label for="standards_body">Editorial standards body · HTML allowed (<a href="/standards" target="_blank">/standards</a>)</label>
        <textarea id="standards_body" name="standards_body" class="prose" style="min-height:80px"><?= e(setting('standards_body')) ?></textarea>
        <label for="privacy_body">Privacy policy body · HTML allowed (<a href="/privacy" target="_blank">/privacy</a>)</label>
        <textarea id="privacy_body" name="privacy_body" class="prose" style="min-height:80px"><?= e(setting('privacy_body')) ?></textarea>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>The research desk</h2>
    <?php if (pp_is_hub()): ?>
    <div class="formgrid">
      <div>
        <label for="ai_model">Model id · blank uses <span class="mono">claude-sonnet-4-6</span></label>
        <input type="text" id="ai_model" name="ai_model" class="mono" value="<?= e(setting('ai_model')) ?>" placeholder="claude-sonnet-4-6">
        <p class="help">The API key is not a setting — it lives in <code>config.php</code> on the server (<code>anthropic_api_key</code>), never in the database.</p>
      </div>
      <div>
        <label for="ai_daily_limit">Daily AI call limit · blank = 50/day, 0 = unlimited</label>
        <input type="text" id="ai_daily_limit" name="ai_daily_limit" class="mono" value="<?= e(setting('ai_daily_limit')) ?>" placeholder="50" style="max-width:120px">
        <p class="help">Calls today: <span class="mono"><?= (int) pp_ai_calls_today() ?></span><?= pp_ai_daily_limit() > 0 ? ' / ' . (int) pp_ai_daily_limit() : ' (unlimited)' ?>.</p>
      </div>
    </div>
    <?php else: ?>
    <label for="ai_disclosure">AI disclosure · blank = OFF (default until explicitly enabled)</label>
    <input type="text" id="ai_disclosure" name="ai_disclosure" value="<?= e(setting('ai_disclosure')) ?>" placeholder="leave blank to keep off">
    <p class="help">Off by default. When explicitly enabled (<span class="mono">1</span> for standard wording, or custom text), prints across every paper template.</p>
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
        <label for="ads_txt">ads.txt · served dynamically at <a href="/ads.txt" target="_blank">/ads.txt</a> (blank = 404)</label>
        <textarea id="ads_txt" name="ads_txt" class="mono" style="min-height:84px" placeholder="google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0"><?= e(setting('ads_txt')) ?></textarea>
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
