<?php
/* Le Memphré — footer chrome. FOUNDATION build (Nov 2026): Toronto
   Telegraph's footer band, unbranded. Closes <main> and the document. */
?>
</main>
<footer class="me-foot">
  <div class="cols">
    <div>
      <a class="me-lock" href="/">
        <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
        <span class="me-name"><span class="top">Le</span><span class="mid">Memphré</span><span class="sub"><?= e(setting('tagline')) ?></span></span>
      </a>
      <p style="font-size:14px;line-height:1.6;margin-top:10px"><?= e(setting('footer_line', setting('tagline'))) ?></p>
    </div>
    <div>
      <h4><?= e(pp_t('Sections')) ?></h4>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>Le Memphré</h4>
      <a href="<?= e(url('tips')) ?>"><?= e(pp_t('Send a tip')) ?></a>
      <a href="<?= e(url('corrections')) ?>"><?= e(pp_t('Corrections')) ?></a>
      <a href="<?= e(url('search')) ?>"><?= e(pp_t('Search the archive')) ?></a>
      <a href="/admin/"><?= e(pp_t('Newsroom sign-in')) ?></a>
    </div>
    <div>
      <h4><?= e(pp_t('Follow')) ?></h4>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'L’infolettre du Memphré')) ?></a>
      <a href="<?= e(url('feed/')) ?>"><?= e(pp_t('RSS')) ?></a>
    </div>
  </div>
  <div class="legal">
    <span>&copy; <?= e(pp_date('Y')) ?> <?= e($siteTitle) ?> &middot; Magog, Québec</span>
    <span class="strong">Les corrections paraissent en tête de texte, datées.</span>
  </div>
</footer>
</body>
</html>
