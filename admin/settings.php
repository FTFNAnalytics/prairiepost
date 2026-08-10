<?php
/** Site settings: identity, rail data, newsletter copy, ads, cron. Admin only. */
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

$textKeys = [
    'site_title', 'tagline', 'meta_description', 'footer_line', 'weather_line',
    'newsletter_heading', 'newsletter_copy', 'markets_note', 'analytics_code',
    'breaking_label', 'breaking_url', 'contact_email',
    'ad_top', 'ad_rail', 'ad_article',
];
$jsonKeys = ['regions', 'markets', 'weather_today', 'traffic_items', 'events_items'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'new_cron_secret') {
        set_setting('cron_secret', bin2hex(random_bytes(16)));
        flash_set('New cron secret generated. Update the cron job URL to match.');
        redirect('settings.php');
    }

    foreach ($textKeys as $key) {
        if (isset($_POST[$key])) {
            set_setting($key, trim((string) $_POST[$key]));
        }
    }
    $jsonError = false;
    foreach ($jsonKeys as $key) {
        if (isset($_POST[$key])) {
            $raw = trim((string) $_POST[$key]);
            $decoded = json_decode($raw, true);
            if ($raw !== '' && !is_array($decoded)) {
                flash_set(strtoupper($key) . " didn't save — that isn't valid JSON. Check the quotes and brackets and try again.", true);
                $jsonError = true;
                continue;
            }
            set_setting($key, $raw === '' ? '' : json_encode($decoded));
        }
    }
    if (!$jsonError) {
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
