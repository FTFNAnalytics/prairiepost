    </div><?php // closes .ti-pad, opened in the header ?>
    <footer class="ti-foot">
      <div class="cols">
        <div>
          <h4>The paper</h4>
          <a href="<?= e(url('about')) ?>">About &amp; contact</a>
          <a href="<?= e(url('corrections')) ?>">Corrections</a>
          <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a><?php endif; ?>
          <a href="/admin/">Newsroom sign-in</a>
        </div>
        <div>
          <h4>Sections</h4>
          <?php foreach (pp_nav_categories() as $cat): ?>
          <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
          <?php endforeach; ?>
        </div>
        <div>
          <h4>Follow</h4>
          <a href="<?= e(url('search')) ?>">Search the archive</a>
          <a href="<?= e(url('newsletter/')) ?>">The morning brief</a>
          <a href="<?= e(url('feed/')) ?>">RSS</a>
        </div>
      </div>
      <div class="base">
        <?= e($siteTitle) ?> &middot; <?= e(setting('footer_line')) ?> &middot; &copy; <?= e(date('Y')) ?>
      </div>
    </footer>
  </div>
</div>
</body>
</html>
