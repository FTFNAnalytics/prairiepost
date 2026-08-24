<?php
$pkWords = preg_split('/\s+/', trim($siteTitle));
if (strcasecmp($pkWords[0] ?? '', 'The') === 0) { array_shift($pkWords); }
$pkName = implode(' ', $pkWords);
$pkInit = mb_strtoupper(mb_substr($pkName !== '' ? $pkName : $siteTitle, 0, 1));
?>
  <footer class="pk-foot">
    <div class="cols">
      <div>
        <span class="lock">
          <span class="pk-tile" aria-hidden="true"><?= e($pkInit) ?></span>
          <span class="nm"><?= e($pkName !== '' ? $pkName : $siteTitle) ?></span>
        </span>
        <p style="font-size:15px;margin:0;color:color-mix(in srgb,var(--paper) 82%,transparent)"><?= e(setting('footer_line')) ?></p>
      </div>
      <div>
        <h4>Sections</h4>
        <?php foreach (pp_nav_categories() as $cat): ?>
        <a href="<?= e(url('desk/' . $cat['slug'])) ?>"><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></a>
        <?php endforeach; ?>
      </div>
      <div>
        <h4>The paper</h4>
        <a href="<?= e(url('contact')) ?>">Contact</a>
        <a href="<?= e(url('corrections')) ?>">Corrections</a>
        <a href="<?= e(url('search')) ?>">Search the archive</a>
        <a href="/admin/">Newsroom sign-in</a>
      </div>
      <div>
        <h4>Follow</h4>
        <a href="<?= e(url('newsletter/')) ?>">The morning email</a>
        <a href="<?= e(url('feed/')) ?>">RSS</a>
        <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Send a tip</a><?php endif; ?>
      </div>
    </div>
    <div class="base">
      &copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?> &middot; <?= e(setting('footer_line')) ?>
    </div>
  </footer>
</div>
</body>
</html>
