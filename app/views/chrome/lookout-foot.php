</main>
<footer class="ll-foot">
  <div class="in">
    <div>
      <?php
      $llW = preg_split('/\s+/', trim($siteTitle)) ?: [];
      $llN = array_pop($llW) ?: $siteTitle;
      $llT = implode(' ', $llW);
      ?>
      <?php if ($llT !== ''): ?><div class="the"><?= e($llT) ?></div><?php endif; ?>
      <div class="name"><?= e($llN) ?></div>
      <p><?= e(setting('footer_line', setting('tagline'))) ?></p>
    </div>
    <div class="cols">
      <div class="col">
        <h4>Sections</h4>
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="col">
        <h4>The newsroom</h4>
        <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a><?php endif; ?>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="/admin/">Newsroom sign-in</a>
      </div>
      <div class="col">
        <h4>Follow</h4>
        <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Daily Lookout')) ?></a>
        <a href="<?= e(url('feed/')) ?>">RSS</a>
        <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Membership</a><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="base">
    <span>&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?> &middot; London, Ontario</span>
    <span>Errors are corrected at the top of the story, dated.</span>
  </div>
</footer>
</body>
</html>
