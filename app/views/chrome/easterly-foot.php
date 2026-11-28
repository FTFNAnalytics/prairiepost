<?php
/* The Easterly — footer chrome. Toronto Telegraph's footer band in the
   owner's brand package (28 Nov 2026): North Atlantic, with the lockup
   reversed (Sea Fog E. and logotype, the full stop stays Lighthouse).
   Closes <main> and the document. */
?>
</main>
<footer class="ea-foot">
  <div class="cols">
    <div>
      <a class="ea-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
        <img class="mk" src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="" width="72" height="61">
        <span class="ea-name"><img class="wm" src="<?= e(site_asset('wordmark-reversed.svg')) ?>" alt="" width="271" height="47"><span class="sub"><?= e(setting('tagline')) ?></span></span>
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
      <h4>The Easterly</h4>
      <a href="<?= e(url('tips')) ?>">News tips</a>
      <a href="<?= e(url('corrections')) ?>"><?= e(pp_t('Corrections')) ?></a>
      <a href="<?= e(url('search')) ?>"><?= e(pp_t('Search the archive')) ?></a>
      <a href="/admin/"><?= e(pp_t('Newsroom sign-in')) ?></a>
    </div>
    <div>
      <h4><?= e(pp_t('Follow')) ?></h4>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Morning Tide')) ?></a>
      <a href="<?= e(url('feed/')) ?>"><?= e(pp_t('RSS')) ?></a>
    </div>
  </div>
  <div class="legal">
    <span>&copy; <?= e(pp_date('Y')) ?> <?= e($siteTitle) ?> &middot; Atlantic Canada</span>
    <span class="strong">Corrections run at the top of the story, dated.</span>
  </div>
</footer>
</body>
</html>
