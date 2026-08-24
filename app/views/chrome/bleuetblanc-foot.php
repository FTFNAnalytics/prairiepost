  <footer class="bb-pied">
    <div class="haut">
      <div>
        <span class="sym" aria-hidden="true"><img src="<?= e(site_asset('mark.svg')) ?>" alt="" width="54" height="54"></span>
        <div class="nom"><?= e($siteTitle) ?></div>
        <p class="devise"><?= e(setting('footer_line')) ?></p>
      </div>
      <div class="cols">
        <div>
          <h4><?= e(pp_t('Sections')) ?></h4>
          <?php foreach (pp_nav_categories() as $cat): ?>
          <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
          <?php endforeach; ?>
        </div>
        <div>
          <h4><?= e(pp_t('The paper')) ?></h4>
          <a href="<?= e(url('contact')) ?>"><?= e(pp_t('Contact')) ?></a>
          <a href="<?= e(url('corrections')) ?>"><?= e(pp_t('Corrections')) ?></a>
          <a href="<?= e(url('search')) ?>"><?= e(pp_t('Search the archive')) ?></a>
          <a href="/admin/"><?= e(pp_t('Newsroom sign-in')) ?></a>
        </div>
        <div>
          <h4><?= e(pp_t('Follow')) ?></h4>
          <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', pp_t('Newsletter'))) ?></a>
          <a href="<?= e(url('feed/')) ?>">RSS</a>
          <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(pp_t('Send a tip')) ?></a><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="legal">
      <span>&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?></span>
      <span><?= e(setting('footer_line')) ?></span>
    </div>
  </footer>
</div>
</body>
</html>
