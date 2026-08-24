








<footer class="v3-foot">
  <div class="cols">
    <div>
      <div class="fh">Contact <?= e(preg_replace('/^The\s+/i', '', $siteTitle)) ?></div>
      <p><?= nl2br(e(setting('paper_address'))) ?><?= setting('contact_email') !== '' ? '<br>' . e(setting('contact_email')) : '' ?></p>
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
      <div class="fh">The paper</div>
      <div class="lnks">
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="<?= e(url('feed/')) ?>">RSS feed</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="/admin/">Newsroom sign-in</a>
      </div>
    </div>
    <div>
      <div class="fh">Newsletter</div>
      <p><?= e(setting('newsletter_copy')) ?></p>
      <form class="nl" method="post" action="<?= e(url('subscribe')) ?>">
        <input type="email" name="email" required placeholder="you@email.ca" aria-label="Email address">
        <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button type="submit">Sign up</button>
      </form>
    </div>
  </div>
  <div class="legal">© <?= e(date('Y')) ?> <?= e($siteTitle) ?> · <?= e(setting('tagline')) ?></div>
</footer>
</body>
</html>
