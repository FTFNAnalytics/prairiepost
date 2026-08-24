







<footer class="ga-foot">
  <div class="cols">
    <div>
      <a class="logo" href="/"><img src="<?= e(site_asset('logo-header.png')) ?>" alt="<?= e($siteTitle) ?>"></a>
      <p class="about"><?= e(setting('footer_line')) ?></p>
    </div>
    <div>
      <div class="fh">News</div>
      <div class="lnks">
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <div class="fh">The paper</div>
      <div class="lnks">
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="<?= e(url('feed/')) ?>">RSS feed</a>
        <a href="<?= e(url('newsletter/')) ?>">Newsletter archive</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="/admin/">Newsroom sign-in</a>
      </div>
    </div>
    <div>
      <div class="fh">Contact</div>
      <div class="lnks">
        <?php if (setting('contact_email') !== ''): ?>
        <a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a>
        <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a>
        <?php endif; ?>
        <a href="<?= e(url('subscribe')) ?>">Get the newsletter</a>
      </div>
      <?php if (setting('paper_address') !== ''): ?>
      <p class="about" style="margin-top:14px"><?= nl2br(e(setting('paper_address'))) ?></p>
      <?php endif; ?>
    </div>
  </div>
  <div class="legal">
    <div class="wrap">
      <span>© <?= e(date('Y')) ?> <?= e($siteTitle) ?> · <?= e(setting('tagline')) ?></span>
      <span>Source Serif 4 · Archivo</span>
    </div>
  </div>
</footer>
</body>
</html>
