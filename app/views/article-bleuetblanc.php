<?php
/**
 * Le Bleuet Blanc — l'article (chrome.template = "bleuetblanc").
 * Incluse par article.php après page_header(); $post, $tags et $canonical
 * sont déjà résolus.
 *
 * Une colonne de 60 à 70 signes sur papier, l'interligne ample. Le chapeau
 * et la citation prennent l'italique du serif — jamais d'oblique simulée.
 * La signature suit la trousse : « Par Camille Tremblay · 23 août 2026, 6 h 15 ».
 */

$autres = related_posts($post['category_id'] ? (int) $post['category_id'] : null, (int) $post['id'], 3);
$rub = $post['category_name'] ?? '';
$ts = strtotime($post['published_at']);
?>

<div class="bb-corps-page">
  <article class="bb-article">
    <?php if ($rub !== ''): ?>
    <a class="bb-rub" href="<?= e(url('desk/' . $post['category_slug'])) ?>"><?= e(pp_desk_label($post['category_slug'] ?? null, $rub)) ?><?php if (!empty($post['dateline'])): ?> &middot; <?= e($post['dateline']) ?><?php endif; ?></a>
    <?php endif; ?>
    <h1><?= e($post['title']) ?></h1>
    <?php if ($post['lede']): ?><p class="chapeau"><?= e($post['lede']) ?></p><?php endif; ?>

    <div class="signature">
      <span><?= e(pp_t('By')) ?> <strong><?php if (!empty($post['author_slug'])): ?><a style="color:inherit" href="<?= e(url('author/' . $post['author_slug'])) ?>"><?= e($post['byline']) ?></a><?php else: ?><?= e($post['byline'] ?: setting('site_title')) ?><?php endif; ?></strong></span>
      <span><?= e(pp_date_long($ts)) ?>, <?= e(pp_clock($ts)) ?></span>
      <span><?= (int) read_minutes($post) ?> <?= e(pp_t('min read')) ?></span>
    </div>

    <?php if ($post['image']): ?>
    <figure>
      <img src="<?= e($post['image']) ?>" alt="<?= e($post['image_caption'] ?: $post['title']) ?>">
      <?php if ($post['image_caption'] !== '' || $post['image_credit'] !== ''): ?>
      <figcaption><?= e($post['image_caption']) ?><?php if ($post['image_credit'] !== ''): ?> <em><?= e($post['image_credit']) ?></em><?php endif; ?></figcaption>
      <?php endif; ?>
    </figure>
    <?php endif; ?>

    <?php if (!empty($post['correction'])): ?>
    <p class="bb-note"><strong><?= e(pp_t('Correction')) ?> &middot; <?= e(pp_date_long(strtotime($post['corrected_at']))) ?></strong><br><?= e((string) $post['correction']) ?></p>
    <?php endif; ?>

    <div class="bb-texte"><?= sanitize_html((string) $post['body']) ?></div>

    <?= ad_slot('article') ?>

    <?php if ($post['source_url']): ?>
    <p class="bb-note"><?= e(pp_t('Source material')) ?> : <a href="<?= e($post['source_url']) ?>" rel="nofollow noopener"><?= e(parse_url($post['source_url'], PHP_URL_HOST) ?: $post['source_url']) ?></a></p>
    <?php endif; ?>

    <p class="bb-note">Une erreur dans ce texte ? <a href="<?= e(url('corrections')) ?>">Écrivez-nous</a>.</p>

    <?php if ($tags): ?>
    <p class="bb-note">
      <?php foreach ($tags as $tag): ?>
      <a href="<?= e(url('search') . '?q=' . urlencode($tag['name'])) ?>"><?= e($tag['name']) ?></a>
      <?php endforeach; ?>
    </p>
    <?php endif; ?>

    <p class="bb-note">
      <?= e(pp_t('Share')) ?> :
      <a href="https://www.facebook.com/sharer/sharer.php?u=<?= e(urlencode($canonical)) ?>" target="_blank" rel="noopener">Facebook</a> &middot;
      <a href="https://bsky.app/intent/compose?text=<?= e(urlencode($post['title'] . ' ' . $canonical)) ?>" target="_blank" rel="noopener">Bluesky</a> &middot;
      <a href="mailto:?subject=<?= e(rawurlencode($post['title'])) ?>&amp;body=<?= e(rawurlencode($canonical)) ?>"><?= e(pp_t('Email')) ?></a>
    </p>
  </article>

  <?php if ($autres): ?>
  <section style="margin:52px 0 0" aria-label="À lire aussi">
    <p class="bb-tetiere">À lire aussi</p>
    <div class="bb-liste">
      <?php foreach ($autres as $p): ?>
      <article class="item">
        <?php if ($p['image']): ?>
        <a class="shot" href="<?= e(url('story/' . $p['slug'])) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e($p['image']) ?>" alt="" loading="lazy"></a>
        <?php endif; ?>
        <h3><a style="color:inherit" href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <p class="sig"><?= (int) read_minutes($p) ?> <?= e(pp_t('min read')) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>
