


<footer class="ww-foot">
  <div class="cols">
    <div>
      <a class="brand" href="/">
        <img src="<?= e(site_asset('mark.svg')) ?>" alt="">
        <span class="t"><?= e($siteTitle) ?></span>
      </a>
      <p class="about"><?= e(setting('footer_line')) ?></p>
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
      <div class="fh">By province</div>
      <div class="lnks">
        <?php foreach (setting_json('regions') as $rk => $rl): ?>
        <a href="<?= e(url('region/' . $rk)) ?>"><?= e($rl) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <div class="fh">The wire</div>
      <div class="lnks">
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="<?= e(url('feed/')) ?>">RSS feed</a>
        <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="<?= e(url('subscribe')) ?>">Subscribe</a>
        <?php if (setting('contact_email') !== ''): ?>
        <a href="mailto:<?= e(setting('contact_email')) ?>">Submit a tip</a>
        <?php endif; ?>
        <a href="/admin/">Desk sign-in</a>
      </div>
    </div>
  </div>
  <div class="legal">
    <div class="wrap">
      <span>© <?= e(date('Y')) ?> <?= e($siteTitle) ?> · <?= e(setting('tagline')) ?></span>
      <span>Every headline links to the outlet that reported it</span>
    </div>
  </div>
</footer>
</body>
</html>
