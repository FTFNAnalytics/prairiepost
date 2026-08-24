





<footer class="pf-foot">
  <div class="cols">
    <div>
      <a class="brand" href="/">
        <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
        <span class="t"><?= e($siteTitle) ?></span>
      </a>
      <p class="ack"><?= e(setting('footer_line')) ?></p>
    </div>
    <div>
      <div class="fh">Sections</div>
      <div class="lnks">
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <div class="fh">The Post</div>
      <div class="lnks">
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="/admin/">Newsroom sign-in</a>
        <?php if (setting('contact_email') !== ''): ?>
        <a href="mailto:<?= e(setting('contact_email')) ?>">Contact</a>
        <?php endif; ?>
      </div>
    </div>
    <div>
      <div class="fh">Follow</div>
      <div class="lnks">
        <a href="<?= e(url('subscribe')) ?>">Subscribe</a>
        <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
        <a href="<?= e(url('feed/')) ?>">RSS</a>
      </div>
    </div>
  </div>
  <div class="legal">
    <div class="wrap">
      <span>© <?= e(date('Y')) ?> <?= e($siteTitle) ?> · <?= e(setting('tagline')) ?></span>
      <span>Set in Source Serif 4</span>
    </div>
  </div>
</footer>
</body>
</html>
