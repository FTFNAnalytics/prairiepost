</main>
<footer class="mm-foot">
  <div class="in">
    <div>
      <span class="m"><img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="" width="48" height="42"></span>
      <div class="name"><?= e($siteTitle) ?></div>
      <p class="tag"><?= e(setting('footer_line', setting('tagline'))) ?></p>
    </div>
    <div>
      <h4>Sections</h4>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>About us</h4>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Contact us</a><?php endif; ?>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="<?= e(url('search')) ?>">Search the archive</a>
      <a href="/admin/">Newsroom sign-in</a>
    </div>
    <div>
      <h4>Follow us</h4>
      <a href="<?= e(url('newsletter/')) ?>">Newsletter</a>
      <a href="<?= e(url('feed/')) ?>">RSS</a>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a><?php endif; ?>
    </div>
  </div>
  <div class="base">
    <span>&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?></span>
    <span><?= e(setting('tagline')) ?></span>
  </div>
</footer>
</body>
</html>
