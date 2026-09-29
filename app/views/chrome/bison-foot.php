<?php
/* The Bison Bulletin — footer chrome (brand build). Closes <main> and
   the document. Near-black ground per the package's reverse lockup:
   the white bison over the stacked name, then the section columns. */
?>
</main>
<footer class="bsn-foot">
  <div class="cols">
    <div>
      <a href="/"><img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt=""></a>
      <div class="name">The <span class="red">Bison</span> Bulletin</div>
      <p style="font-size:13px;line-height:1.6"><?= e(setting('footer_line', setting('tagline'))) ?></p>
    </div>
    <div>
      <h4>Sections</h4>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>The Bulletin</h4>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">News tips</a><?php endif; ?>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="<?= e(url('search')) ?>">Search the archive</a>
      <a href="/admin/">Newsroom sign-in</a>
    </div>
    <div>
      <h4>Follow</h4>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Morning Bulletin')) ?></a>
      <a href="<?= e(url('feed/')) ?>">RSS</a>
    </div>
  </div>
  <div class="legal">
    <span>&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?> &middot; Manitoba</span>
    <span><?= e(setting('tagline')) ?></span>
  </div>
</footer>
</body>
</html>
