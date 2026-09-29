<?php
/* Toronto Telegraph — footer chrome (brand build). Closes <main> and
   the document. The package's Leafs-navy band: reversed roundel and
   stacked wordmark, section columns, and the brand's closing lines. */
?>
</main>
<footer class="tg-foot">
  <div class="cols">
    <div>
      <a class="tg-lock" href="/">
        <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
        <span class="tg-name"><span class="top">Toronto</span><span class="mid">Telegraph</span><span class="sub"><?= e(setting('tagline')) ?></span></span>
      </a>
      <p style="font-size:14px;line-height:1.6;margin-top:10px"><?= e(setting('footer_line', setting('tagline'))) ?></p>
    </div>
    <div>
      <h4>Sections</h4>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>The Telegraph</h4>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">News tips</a><?php endif; ?>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="<?= e(url('search')) ?>">Search the archive</a>
      <a href="/admin/">Newsroom sign-in</a>
    </div>
    <div>
      <h4>Follow</h4>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Morning Wire')) ?></a>
      <a href="<?= e(url('feed/')) ?>">RSS</a>
    </div>
  </div>
  <div class="legal">
    <span>&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?> &middot; Toronto, Ontario &middot; Local stories. Global city.</span>
    <span class="strong">Toronto Focused &middot; Community Connected &middot; Truth First</span>
  </div>
</footer>
</body>
</html>
