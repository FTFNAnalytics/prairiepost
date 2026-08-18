<?php
/**
 * The About page — a per-site standing page whose copy lives in settings
 * (about_heading, about_standfirst, about_body), so a paper writes its own
 * without a deploy. Any site may link it; the Standard carries it in the nav
 * because its brand package names it as a section.
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

$heading    = setting('about_heading', 'About ' . setting('site_title'));
$standfirst = setting('about_standfirst');
$body       = setting('about_body');

if (trim($body) === '') {
    $body = '<p>' . e(setting('meta_description')) . '</p>';
}

page_header([
    'title'       => $heading,
    'description' => $standfirst !== '' ? excerpt($standfirst, 155) : setting('meta_description'),
    'canonical'   => site_url() . '/about',
], 'about');
?>

<?php if (pp_chrome('template') === 'standard'): ?>
<div class="sd-band">
  <div class="in">
    <p class="eyebrow">The paper</p>
    <h1><?= e($heading) ?></h1>
    <?php if ($standfirst !== ''): ?><p><?= e($standfirst) ?></p><?php endif; ?>
  </div>
</div>
<div class="sd-main">
  <div class="sd-wrap">
    <div class="sd-page">
      <div class="sd-measure">
        <div class="bodycopy"><?= sanitize_html($body) ?></div>
        <p class="sd-fundnote"><?= e(setting('funding_note', '')) ?> <a href="<?= e(url('corrections')) ?>">Corrections policy</a>.</p>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="pagehead wrap">
  <span class="pp-meta">The paper</span>
  <h1><?= e($heading) ?></h1>
  <?php if ($standfirst !== ''): ?><p class="desc"><?= e($standfirst) ?></p><?php endif; ?>
  <div class="pp-horizon"></div>
</div>
<div class="article wrap">
  <div class="bodycopy"><?= sanitize_html($body) ?></div>
</div>
<?php endif; ?>

<?php page_footer(); ?>
