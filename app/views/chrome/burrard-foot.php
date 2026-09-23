<?php
/* The Burrard Brief — footer chrome (brand build). Closes <main> and
   the document. Navy ground, reverse lockup, the land acknowledgment
   from the brand package, and the reader-supported base line. */
?>
</main>
<footer class="bb-foot">
  <div class="in">
    <div>
      <img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="" width="66" height="42" style="margin-bottom:8px">
      <div class="the">The</div>
      <div class="name">Burrard Brief</div>
      <p style="font-size:14px;line-height:1.6;font-style:italic"><?= e(setting('footer_line', setting('tagline'))) ?></p>
    </div>
    <div>
      <h4>Sections</h4>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>The newsroom</h4>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">News tips</a><?php endif; ?>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="<?= e(url('search')) ?>">Search the archive</a>
      <a href="/admin/">Newsroom sign-in</a>
    </div>
    <div>
      <h4>The Morning Brief</h4>
      <a href="<?= e(url('newsletter/')) ?>">Subscribe free</a>
      <a href="<?= e(url('feed/')) ?>">RSS</a>
      <p style="font-size:13px;line-height:1.55;color:#9DB0C0;margin:6px 0 0">Lands at 7 a.m. every weekday. Free.</p>
    </div>
  </div>
  <div class="ack">
    <div class="in2">We report from the unceded territories of the xʷməθkʷəy̓əm (Musqueam), Sḵwx̱wú7mesh (Squamish) and səlilwətaɬ (Tsleil-Waututh) Nations.</div>
  </div>
  <div class="base">
    <div class="in2">
      <span>&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?>. Independent and reader supported.</span>
      <span>Corrections run at the top of the story, dated.</span>
    </div>
  </div>
</footer>
</body>
</html>
