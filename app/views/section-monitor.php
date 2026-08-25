<?php
/**
 * The Mississauga Monitor — section front (design canvas §03/§04).
 * $cat and $posts are resolved by section.php. The `live` desk wears the
 * orange LIVE badge — the only place it appears — and its list leads
 * with times; every other desk gets the lead-story + rows treatment
 * with an agenda-style rail of the desk's recent dates.
 */
$mmIsLive = ($cat['slug'] ?? '') === 'live';
$lead = $posts[0] ?? null;
$rest = array_slice($posts, 1);
?>
<div class="mm-page mm-sec">
  <header class="mm-sec-head">
    <?php if ($mmIsLive): ?><span class="mm-live"><span class="dot" aria-hidden="true"></span>Live</span><?php endif; ?>
    <span class="mm-kicker mm-kicker--muted">Section</span>
    <h1><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h1>
    <?php if (!empty($cat['description'])): ?><p class="desc"><?= e($cat['description']) ?></p><?php endif; ?>
  </header>

  <div class="mm-sec-list">
    <?php if (!$posts): ?>
    <div class="mm-card"><p class="mm-meta" style="margin:0">Nothing on this desk yet.</p></div>
    <?php endif; ?>
    <?php if ($lead): ?>
    <article class="mm-card lead">
      <span class="mm-kicker"><?= e($lead['dateline'] ?: fmt_date($lead['published_at'])) ?></span>
      <h2><a href="<?= e(url('story/' . $lead['slug'])) ?>"><?= e($lead['title']) ?></a></h2>
      <?php if ($lead['lede']): ?><p><?= e(excerpt($lead['lede'], 190)) ?></p><?php endif; ?>
      <span class="mm-meta"><?php if ($lead['byline']): ?>By <?= e($lead['byline']) ?> &middot; <?php endif; ?><?= e(fmt_date($lead['published_at'], 'M j, g:i a')) ?></span>
    </article>
    <?php endif; ?>
    <?php foreach ($rest as $p): ?>
    <article class="mm-card row">
      <span class="mm-kicker mm-kicker--muted"><?= e($p['dateline'] ?: fmt_date($p['published_at'])) ?></span>
      <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 130)) ?></p><?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>

  <aside class="mm-rail">
    <div class="mm-card">
      <h3><?= $mmIsLive ? 'On this file' : 'Recently on this desk' ?></h3>
      <?php foreach (array_slice($posts, 0, 5) as $p): ?>
      <div class="item">
        <span class="d"><?= e(fmt_date($p['published_at'], $mmIsLive ? 'g:i a' : 'M j')) ?></span>
        <a style="color:inherit" href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a>
      </div>
      <?php endforeach; ?>
      <?php if (!$posts): ?><p class="mm-meta" style="margin:0">Check back soon.</p><?php endif; ?>
    </div>
    <div class="mm-card">
      <h3>Have a tip?</h3>
      <p class="mm-meta" style="margin:0 0 12px">We protect our sources. Reach the newsroom directly.</p>
      <?php $mmTip = setting('contact_email'); ?>
      <a class="mm-subscribe" style="margin-left:0" href="<?= $mmTip !== '' ? 'mailto:' . e($mmTip) : e(url('corrections')) ?>">Send a tip</a>
    </div>
  </aside>
</div>
