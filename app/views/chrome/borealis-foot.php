<?php
/* Borealis Bulletin — footer chrome. Toronto Telegraph's footer band in
   the owner's brand package (28 Nov 2026), which shipped this partial:
   Polar Midnight under an Aurora Emerald rule, the reversed roundel
   beside the stacked wordmark (Bulletin in Aurora Luminous), four
   columns, and the northern closing lines. Closes <main> and the
   document. */
?>
</main>
<footer class="bo-foot">
  <div class="cols">
    <div>
      <a class="bo-lock" href="/" aria-label="<?= e($siteTitle) ?> — front page">
        <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="" width="54" height="54">
        <span class="bo-name"><span class="top">Borealis</span><span class="mid">Bulletin</span><span class="sub"><?= e(setting('tagline')) ?></span></span>
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
      <h4>The Bulletin</h4>
      <a href="<?= e(url('tips')) ?>">News tips</a>
      <a href="<?= e(url('corrections')) ?>"><?= e(pp_t('Corrections')) ?></a>
      <a href="<?= e(url('standards')) ?>">Editorial standards</a>
      <a href="<?= e(url('search')) ?>"><?= e(pp_t('Search the archive')) ?></a>
      <a href="/admin/"><?= e(pp_t('Newsroom sign-in')) ?></a>
    </div>
    <div>
      <h4><?= e(pp_t('Follow')) ?></h4>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The 60th Parallel Brief')) ?></a>
      <a href="<?= e(url('feed/')) ?>"><?= e(pp_t('RSS')) ?></a>
      <a href="<?= e(url('privacy')) ?>">Privacy</a>
    </div>
  </div>
  <div class="legal">
    <span>&copy; <?= e(pp_date('Y')) ?> <?= e($siteTitle) ?> &middot; Yukon &middot; Northwest Territories &middot; Nunavut &middot; Circumpolar North</span>
    <span class="strong">North of 60&deg; &middot; Independent &middot; On the Record</span>
  </div>
</footer>
</body>
</html>
