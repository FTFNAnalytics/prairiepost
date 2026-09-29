<?php
/* The Red River Register — footer chrome (brand build). Closes <main>
   and the document. Ink ground, white nameplate with the reversed
   ledger-and-meander mark. */
?>
</main>
<footer class="rg-foot">
  <div class="cols">
    <div>
      <a class="rg-nameplate" href="/"><img class="lock-mark" src="<?= e(site_asset('mark-reversed.svg')) ?>" alt=""> <?= e($siteTitle) ?></a>
      <p style="font-size:13px;line-height:1.6"><?= e(setting('footer_line', setting('tagline'))) ?></p>
    </div>
    <div>
      <h4>Sections</h4>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>The Register</h4>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">News tips</a><?php endif; ?>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="<?= e(url('search')) ?>">Search the archive</a>
      <a href="/admin/">Newsroom sign-in</a>
    </div>
    <div>
      <h4>Follow</h4>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Morning Record')) ?></a>
      <a href="<?= e(url('feed/')) ?>">RSS</a>
    </div>
  </div>
  <div class="legal">
    <span>&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?> &middot; The Red River Valley &middot; Manitoba</span>
    <span>The valley, on the record.</span>
  </div>
</footer>
</body>
</html>
