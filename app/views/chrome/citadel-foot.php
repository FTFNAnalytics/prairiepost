<?php
/* Citadel Courier — footer chrome. Toronto Telegraph's footer band in the
   owner's brand package (28 Nov 2026): Deep Navy, with the fort reversed
   in Light Sea Blue and the wordmark in white. Closes <main> and the
   document. */
?>
</main>
<footer class="cc-foot">
  <div class="cols">
    <div>
      <a class="cc-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
        <img class="mk" src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="" width="93" height="55">
        <span class="cc-name"><span class="wm">Citadel Courier</span><span class="sub"><?= e(setting('tagline')) ?></span></span>
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
      <h4>The Courier</h4>
      <a href="<?= e(url('tips')) ?>">News tips</a>
      <a href="<?= e(url('corrections')) ?>"><?= e(pp_t('Corrections')) ?></a>
      <a href="<?= e(url('search')) ?>"><?= e(pp_t('Search the archive')) ?></a>
      <a href="/admin/"><?= e(pp_t('Newsroom sign-in')) ?></a>
    </div>
    <div>
      <h4><?= e(pp_t('Follow')) ?></h4>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Noon Gun')) ?></a>
      <a href="<?= e(url('feed/')) ?>"><?= e(pp_t('RSS')) ?></a>
    </div>
  </div>
  <div class="legal">
    <span>&copy; <?= e(pp_date('Y')) ?> <?= e($siteTitle) ?> &middot; Halifax, Nova Scotia</span>
    <span class="strong">Corrections run at the top of the story, dated.</span>
  </div>
</footer>
</body>
</html>
