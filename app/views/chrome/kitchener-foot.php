</main>
<footer class="kc-foot">
  <div class="in">
    <div>
      <div class="name"><?= e($siteTitle) ?></div>
      <p class="tag"><?= e(setting('footer_line', setting('tagline'))) ?></p>
    </div>
    <div class="colset">
      <div class="col">
        <h4>Sections</h4>
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="col">
        <h4>The paper</h4>
        <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Contact us</a><?php endif; ?>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="/admin/">Newsroom sign-in</a>
      </div>
      <div class="col">
        <h4>Newsletters</h4>
        <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Morning Chronicle')) ?></a>
        <a href="<?= e(url('feed/')) ?>">RSS</a>
        <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="base">&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?> &middot; Kitchener &middot; Waterloo &middot; Cambridge</div>
</footer>
</body>
</html>
