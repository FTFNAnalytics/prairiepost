




<footer class="cu-foot">
  <div class="grid">
    <div class="brand">
      <h2><?= e($siteTitle) ?></h2>
      <p><?= e(setting('footer_line')) ?></p>
    </div>
    <div class="col">
      <h3>Read</h3>
      <a href="/">Latest</a>
      <?php foreach (array_slice(pp_nav_categories(), 0, 4) as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="col">
      <h3>About</h3>
      <a href="<?= e(url('search')) ?>">Search the archive</a>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="/admin/">Newsroom sign-in</a>
      <?php if (setting('contact_email') !== ''): ?>
      <a href="mailto:<?= e(setting('contact_email')) ?>">Contact</a>
      <?php endif; ?>
    </div>
    <div class="col">
      <h3>Connect</h3>
      <a href="<?= e(url('newsletter/')) ?>">Newsletters</a>
      <a href="<?= e(url('subscribe')) ?>">Membership</a>
      <a href="<?= e(url('feed/')) ?>">RSS</a>
      <?php if (setting('contact_email') !== ''): ?>
      <a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="bottom">
    <span>© <?= e(date('Y')) ?> <?= e($siteTitle) ?></span>
    <span><?= e(setting('tagline')) ?></span>
  </div>
</footer>
</body>
</html>
