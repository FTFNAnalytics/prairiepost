<?php
/* The Burrard Brief — footer chrome (foundation build). A foot partial
   closes <main> and the document itself. */
?>
</main>
<footer class="bb-foot">
  <div class="in">
    <div>
      <div class="name">The Burrard Brief</div>
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
      <a href="<?= e(url('search')) ?>">Search</a>
      <a href="/admin/">Newsroom sign-in</a>
    </div>
    <div>
      <h4>Follow</h4>
      <a href="<?= e(url('newsletter/')) ?>"><?= e(setting('newsletter_heading', 'The First Brief')) ?></a>
      <a href="<?= e(url('feed/')) ?>">RSS</a>
    </div>
  </div>
  <div class="base">
    <div class="in">
      <span>&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?> &middot; Vancouver, British Columbia</span>
      <span>Corrections run at the top of the story, dated.</span>
    </div>
  </div>
</footer>
</body>
</html>
