<?php
/* The Cariboo Compass — footer chrome (brand build). Closes <main> and
   the document. Deep green, the gold rose, the brand sheet's base line. */
?>
</main>
<footer class="cc-foot">
  <div class="in">
    <div>
      <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="" width="54" height="54" style="margin-bottom:8px">
      <div class="name">The Cariboo <span class="gold">Compass</span></div>
      <p style="font-size:13px;line-height:1.6"><?= e(setting('footer_line', setting('tagline'))) ?></p>
    </div>
    <div>
      <h4>Sections</h4>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>The newsroom</h4>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">News tips</a><?php endif; ?>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="<?= e(url('search')) ?>">Search the archive</a>
      <a href="/admin/">Newsroom sign-in</a>
    </div>
    <div>
      <h4>Follow</h4>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Morning Bearing')) ?></a>
      <a href="<?= e(url('feed/')) ?>">RSS</a>
    </div>
  </div>
  <div class="base">
    <div class="in2">
      <span>&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?> &middot; Local news. Real impact.</span>
      <span>Corrections run at the top of the story, dated.</span>
    </div>
  </div>
</footer>
</body>
</html>
