<?php
/* Surrey Standard — footer chrome (foundation build). Closes <main> and
   the document. */
?>
</main>
<footer class="ss-foot">
  <div class="in">
    <div>
      <div class="name">Surrey Standard</div>
      <p style="font-size:13px;line-height:1.6"><?= e(setting('footer_line', setting('tagline'))) ?></p>
    </div>
    <div>
      <h4>Sections</h4>
      <?php foreach (pp_nav_categories() as $cat): ?>
      <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>The newsroom</h4>
      <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a><?php endif; ?>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="<?= e(url('search')) ?>">Search the archive</a>
      <a href="/admin/">Newsroom sign-in</a>
    </div>
    <div>
      <h4>Follow</h4>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The Morning Standard')) ?></a>
      <a href="<?= e(url('feed/')) ?>">RSS</a>
    </div>
  </div>
  <div class="base">
    <div class="in">
      <span>&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?> &middot; Surrey, British Columbia</span>
      <span>Corrections run at the top of the story, dated.</span>
    </div>
  </div>
</footer>
</body>
</html>
