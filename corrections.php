<?php
/**
 * The Prairie Post — the corrections file.
 * Every correction the paper has run, in public, newest first. Being seen
 * to fix mistakes is the cheapest credibility a paper can buy.
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

$posts = corrected_posts();

page_header([
    'title' => 'The corrections file',
    'description' => 'Every correction ' . setting('site_title', 'The Prairie Post') . ' has run, in public. When we get it wrong, this is where we say so.',
]);
?>
<div class="pagehead wrap" style="--desk:#9C3B22">
  <span class="pp-meta" style="color:#5A6A5C">The record</span>
  <h1>The corrections file</h1>
  <p class="desc">When the paper gets something wrong, the story is corrected where it stands and the correction is logged here. If you've caught an error, tell us — the newsroom reads its email.</p>
  <div class="pp-horizon"></div>
</div>

<div class="archive wrap">
  <?php if (!$posts): ?>
  <div class="empty">No corrections on file. That record lasts exactly until the next mistake — and then this page says so.</div>
  <?php else: ?>
  <?php foreach ($posts as $post): ?>
  <article class="correction" style="max-width:68ch;margin-bottom:22px">
    <span class="k">Correction · <?= e(fmt_date($post['corrected_at'], 'M j, Y')) ?></span>
    <p style="margin:8px 0 10px;font-size:16.5px"><?= e((string) $post['correction']) ?></p>
    <p class="pp-meta" style="margin:0"><a href="<?= e(url('story/' . $post['slug'])) ?>"><?= e($post['title']) ?></a> · first published <?= e(fmt_date($post['published_at'])) ?></p>
  </article>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
