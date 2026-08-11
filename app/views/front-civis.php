<?php
/**
 * Civis Media — the brochure front (template: civis).
 * The hub's public face: a communications-and-advertising practice, rendered
 * whole with none of the newsroom chrome. Copy lives in settings (see the
 * launch package); the contact form posts to /contact and lands in the
 * control room's Inquiries page.
 */

$siteTitle = setting('site_title', 'Civis Media');
$tagline   = setting('tagline', 'Communications & advertising');
$headline  = setting('civis_headline', 'Clear communications, placed well.');
$sub       = setting('civis_sub', '');
$services  = setting_json('civis_services');
$approach  = setting_json('civis_approach');
$email     = setting('contact_email');
$address   = setting('paper_address');
$sent      = isset($_GET['sent']);
$err       = (string) ($_GET['err'] ?? '');
$errors    = [
    'missing' => 'A name, an email address and a few words about the project are all we need — one of them was missing.',
    'email'   => 'That email address doesn\'t look complete — check it and send again.',
    'rate'    => 'That\'s a few messages in a row from this connection. Give it an hour, or email us directly.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($siteTitle) ?> — <?= e($tagline) ?></title>
<meta name="description" content="<?= e(setting('meta_description')) ?>">
<link rel="canonical" href="<?= e(site_url()) ?>/">
<link rel="icon" type="image/svg+xml" href="<?= e(site_asset('favicon.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="/assets/css/civis.css">
<meta property="og:site_name" content="<?= e($siteTitle) ?>">
<meta property="og:title" content="<?= e($siteTitle) ?> — <?= e($tagline) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= e(site_url()) ?>/">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => $siteTitle,
    'url'      => site_url(),
    'description' => setting('meta_description'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php $analytics = setting('analytics_code'); if ($analytics !== '') { echo $analytics . "\n"; } ?>
</head>
<body class="t-civis">

<header class="cv-top">
  <div class="cv-wrap">
    <a class="logo" href="/" aria-label="<?= e($siteTitle) ?>">
      <img src="<?= e(site_asset('logo-primary.svg')) ?>" alt="<?= e($siteTitle) ?>">
    </a>
    <nav aria-label="Sections">
      <a href="#services">Services</a>
      <a href="#approach">Approach</a>
      <a href="#contact">Contact</a>
    </nav>
  </div>
</header>
<div class="cv-horizon"></div>

<main>
<section class="cv-hero">
  <div class="cv-wrap">
    <p class="kicker cv-mono"><?= e($tagline) ?></p>
    <h1><?= e($headline) ?></h1>
    <?php if ($sub !== ''): ?><p><?= e($sub) ?></p><?php endif; ?>
    <div class="act">
      <a class="cv-btn cv-btn--copper" href="#contact">Start a conversation</a>
      <a class="cv-btn cv-btn--ghost" href="#services">What we do</a>
    </div>
  </div>
</section>

<section class="cv-section" id="services">
  <div class="cv-wrap">
    <p class="kicker cv-mono">Services</p>
    <h2>Four things, done properly.</h2>
    <div class="cv-services">
      <?php foreach ($services as $s): ?>
      <div class="cv-service">
        <h3><?= e((string) ($s[0] ?? '')) ?></h3>
        <p><?= e((string) ($s[1] ?? '')) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($approach): ?>
<section class="cv-section cv-band" id="approach">
  <div class="cv-wrap">
    <p class="kicker cv-mono">Approach</p>
    <h2>How an engagement runs.</h2>
    <div class="cv-steps">
      <?php foreach ($approach as $i => $step): ?>
      <div class="cv-step">
        <div class="n"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></div>
        <h3><?= e((string) ($step[0] ?? '')) ?></h3>
        <p><?= e((string) ($step[1] ?? '')) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="cv-section cv-contact" id="contact">
  <div class="cv-wrap">
    <div>
      <p class="kicker cv-mono">Contact</p>
      <h2>Tell us what needs saying.</h2>
      <p class="lede">A few sentences about the organization and the problem is plenty — we'll come back within two business days.</p>
      <?php if ($email !== ''): ?>
      <p class="direct">Prefer email? <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>
      <?php endif; ?>
    </div>
    <div>
      <?php if ($sent): ?>
      <div class="cv-note cv-note--ok">Received, thank you — we'll come back to you within two business days.</div>
      <?php elseif (isset($errors[$err])): ?>
      <div class="cv-note"><?= e($errors[$err]) ?></div>
      <?php endif; ?>
      <form class="cv-form" method="post" action="/contact">
        <label for="cv-name">Name</label>
        <input type="text" id="cv-name" name="name" required maxlength="120">
        <label for="cv-email">Email</label>
        <input type="email" id="cv-email" name="email" required maxlength="191">
        <label for="cv-org">Organization <span style="font-weight:400;color:var(--cv-muted)">· optional</span></label>
        <input type="text" id="cv-org" name="organization" maxlength="160">
        <label for="cv-msg">The project</label>
        <textarea id="cv-msg" name="message" required maxlength="4000"></textarea>
        <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="send"><button class="cv-btn cv-btn--copper" type="submit">Send it over</button></div>
      </form>
    </div>
  </div>
</section>
</main>

<div class="cv-horizon"></div>
<footer class="cv-foot">
  <div class="cv-wrap">
    <a href="/" aria-label="<?= e($siteTitle) ?>"><img src="<?= e(site_asset('logo-reversed.svg')) ?>" alt="<?= e($siteTitle) ?>"></a>
    <span>© <?= e(date('Y')) ?> <?= e($siteTitle) ?> · <?= e($tagline) ?><?= $address !== '' ? ' · ' . e($address) : '' ?></span>
    <?php if ($email !== ''): ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php endif; ?>
  </div>
</footer>
</body>
</html>
