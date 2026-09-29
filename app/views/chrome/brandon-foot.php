<?php
/* The Brandon Bulletin — footer chrome (brand build). Closes <main>
   and the document. The package's black band: reversed badge beside
   the stacked wordmark, then the section columns and the brand's
   closing line. */
?>
</main>
<footer class="bdn-foot">
  <div class="cols">
    <div>
      <a class="bdn-lock" href="/">
        <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
        <span class="bdn-name"><span class="top">BRANDON</span><span class="sub">Bulletin</span></span>
      </a>
      <p style="font-size:13px;line-height:1.6;margin-top:10px"><?= e(setting('footer_line', setting('tagline'))) ?></p>
    </div>
    <div>
      <h4>Sections</h4>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>The Bulletin</h4>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">News tips</a><?php endif; ?>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="<?= e(url('search')) ?>">Search the archive</a>
      <a href="/admin/">Newsroom sign-in</a>
    </div>
    <div>
      <h4>Follow</h4>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Wheat City Brief')) ?></a>
      <a href="<?= e(url('feed/')) ?>">RSS</a>
    </div>
  </div>
  <div class="legal">
    <span>&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?> &middot; Brandon &middot; Southwest Manitoba</span>
    <span>Prairie values. Local stories. Lasting impact.</span>
  </div>
</footer>
</body>
</html>
