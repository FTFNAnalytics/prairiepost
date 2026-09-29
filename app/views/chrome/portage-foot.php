<?php
/* Portage Press — footer chrome (brand build). Closes <main> and the
   document. The package's navy band: wordmark and tagline, Quick
   Links, Services, and the legal line. */
$ptgWords = preg_split('/\s+/', trim($siteTitle)) ?: [$siteTitle];
$ptgLast = array_pop($ptgWords);
$ptgFirst = implode(' ', $ptgWords);
?>
</main>
<footer class="ptg-foot">
  <div class="cols">
    <div>
      <a class="ptg-lock" href="/">
        <span class="ptg-name">
          <span class="top"><?= $ptgFirst !== '' ? e($ptgFirst) . ' ' : '' ?><span class="red"><?= e($ptgLast) ?></span> <img src="<?= e(site_asset('maple.svg')) ?>" alt="" style="width:14px;height:15px"></span>
        </span>
      </a>
      <p style="font-size:13px;line-height:1.6;margin-top:8px"><?= e(setting('footer_line', setting('tagline'))) ?></p>
    </div>
    <div>
      <h4>Quick Links</h4>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>Services</h4>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'Stay in the Know')) ?></a>
      <a href="<?= e(url('feed/')) ?>">RSS Feeds</a>
      <a href="<?= e(url('search')) ?>">Search the archive</a>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
    </div>
    <div>
      <h4>The Newsroom</h4>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">News tips</a><?php endif; ?>
      <a href="/admin/">Newsroom sign-in</a>
    </div>
  </div>
  <div class="legal">
    <span>&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?> &middot; Winnipeg, Manitoba</span>
    <span><?= e(setting('tagline')) ?></span>
  </div>
</footer>
</body>
</html>
