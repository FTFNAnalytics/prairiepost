<?php
$ttWords = preg_split('/\s+/', trim($siteTitle));
$ttLast  = count($ttWords) > 1 ? array_pop($ttWords) : '';
$ttFirst = $ttLast !== '' ? implode(' ', $ttWords) : $siteTitle;
?>
<div class="tt-footindex">
  <div class="in">
    <div>
      <a class="lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
        <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
        <span><b><?= e($ttFirst) ?></b><span><?= e(mb_strtoupper($ttLast)) ?></span></span>
      </a>
      <p class="blurb"><?= e(setting('footer_line')) ?></p>
    </div>
    <div>
      <p class="fh">Sections</p>
      <div class="lnks">
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <p class="fh">The Torch</p>
      <div class="lnks">
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="<?= e(url('feed/')) ?>">RSS</a>
        <?php if (setting('contact_email') !== ''): ?>
        <a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a>
        <?php endif; ?>
        <a href="/admin/">Newsroom sign-in</a>
      </div>
    </div>
  </div>
</div>
<footer class="tt-foot">
  <div class="in">
    <p>© <?= e(date('Y')) ?> <?= e($siteTitle) ?> · Coquitlam, BC</p>
    <div class="soc">
      <a href="<?= e(url('feed/')) ?>">RSS</a>
      <a href="<?= e(url('subscribe')) ?>">Newsletter</a>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
    </div>
  </div>
</footer>
</body>
</html>
