
<?php
$sdWords = preg_split('/\s+/', trim($siteTitle));
$sdLast  = count($sdWords) > 1 ? array_pop($sdWords) : '';
$sdMono  = mb_substr(preg_replace('/^The\s+/i', '', $siteTitle), 0, 1);
?>
<footer class="sd-foot">
  <div class="in">
    <a class="lock" href="/">
      <span class="sx" aria-hidden="true"><?= e($sdMono) ?></span>
      <span class="nm"><?= e(mb_strtoupper($sdLast ?: $siteTitle)) ?></span>
    </a>
    <span class="meta">&copy; <?= e(date('Y')) ?> <?= e($siteTitle) ?> &middot; <?= e(setting('footer_line', 'Independent, reader-funded')) ?></span>
    <span class="lnks">
      <a href="<?= e(url('about')) ?>">About</a>
      <a href="<?= e(url('corrections')) ?>">Corrections</a>
      <a href="<?= e(url('search')) ?>">Search</a>
      <a href="<?= e(url('feed/')) ?>">RSS</a>
    </span>
  </div>
</footer>
</body>
</html>
