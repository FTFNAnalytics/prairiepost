






<footer class="kc-foot">
  <div class="cols">
    <div class="brand">
      <a href="/"><img src="<?= e(site_asset('bear-crest.png')) ?>" alt=""></a>
      <div class="t"><?= e($siteTitle) ?></div>
    </div>
    <div>
      <div class="fh kc-caps">Sections</div>
      <div class="lnks">
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <div class="fh kc-caps">The paper</div>
      <div class="lnks">
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="<?= e(url('feed/')) ?>">RSS feed</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="/admin/">Newsroom sign-in</a>
      </div>
    </div>
    <div>
      <div class="fh kc-caps">Support</div>
      <div class="lnks">
        <a href="<?= e(url('subscribe')) ?>">Subscribe</a>
        <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
        <?php if (setting('contact_email') !== ''): ?>
        <a href="mailto:<?= e(setting('contact_email')) ?>">Tips</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="legal">
    <div class="wrap">
      <span><?= e(setting('footer_line')) ?> © <?= e(date('Y')) ?> <?= e($siteTitle) ?>.</span>
      <span><?= e(setting('tagline')) ?></span>
    </div>
  </div>
</footer>
</body>
</html>
