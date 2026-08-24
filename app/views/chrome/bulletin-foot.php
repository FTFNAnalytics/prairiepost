



<footer class="bb-foot">
  <div class="cols">
    <div>
      <a class="brand" href="/">
        <img src="<?= e(site_asset('mark.svg')) ?>" alt="">
        <span class="t"><?= e($siteTitle) ?></span>
      </a>
      <p class="about"><?= e(setting('footer_line')) ?></p>
    </div>
    <div>
      <div class="fh">Beats</div>
      <div class="lnks">
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <div class="fh">The Bulletin</div>
      <div class="lnks">
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="/admin/">Newsroom sign-in</a>
        <a href="<?= e(url('feed/')) ?>">RSS</a>
      </div>
    </div>
    <div>
      <div class="fh">Support us</div>
      <div class="lnks">
        <a href="<?= e(url('subscribe')) ?>">Subscribe</a>
        <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
        <?php if (setting('contact_email') !== ''): ?>
        <a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="legal">
    <div class="wrap">
      <span>© <?= e(date('Y')) ?> <?= e($siteTitle) ?> · <?= e(setting('tagline')) ?></span>
      <span>Set in Source Serif 4 on newsprint</span>
    </div>
  </div>
</footer>
</body>
</html>
