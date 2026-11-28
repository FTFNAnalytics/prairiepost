<?php
/* Le Memphré — section front. FOUNDATION build (Nov 2026): Toronto
   Telegraph's desk page, unbranded ($cat, $posts, $page, $pages
   resolved by section.php). The ruled desk title over hairlined rows;
   desk descriptions are shared network-wide, so the name is the page. */
// Bylines in French: the shared dateline() prints "By" and English times.
$meSig = static function (array $p): string {
    $bits = [];
    if (($p['post_type'] ?? '') === 'link' && !empty($p['source_url'])) {
        $src = (string) ($p['source_name'] ?? '');
        if ($src === '') {
            $src = (string) preg_replace('/^www\./', '', (string) parse_url((string) $p['source_url'], PHP_URL_HOST));
        }
        if ($src !== '') {
            $bits[] = $src;
        }
    } else {
        if (!empty($p['dateline'])) {
            $bits[] = mb_strtoupper((string) $p['dateline']);
        }
        if (!empty($p['byline'])) {
            $bits[] = pp_t('By') . ' ' . $p['byline'];
        }
    }
    $ts = !empty($p['published_at']) ? strtotime((string) $p['published_at']) : false;
    if ($ts) {
        $bits[] = pp_date('Y-m-d', $ts) === pp_date('Y-m-d') ? pp_clock($ts) : pp_date_long($ts);
    }
    return implode(' · ', array_map('e', $bits));
};
?>
<div class="me-wrap me-section">
  <div class="me-ttl" style="margin-top:26px"><h2><?= e(pp_desk_label($cat['slug'], $cat['name'])) ?></h2></div>
  <?php foreach ($posts as $p): ?>
  <article class="me-row<?= $p['image'] ? '' : ' noart' ?>">
    <div>
      <span class="me-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
      <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
      <?php if ($p['lede']): ?><p><?= e($p['lede']) ?></p><?php endif; ?>
      <span class="meta"><?= $meSig($p) ?></span>
    </div>
    <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
  </article>
  <?php endforeach; ?>
  <?php if (!$posts): ?>
  <div class="me-empty" style="margin-top:8px">Rien dans cette rubrique pour l’instant — la rédaction y travaille.</div>
  <?php endif; ?>
  <?php if (($pages ?? 1) > 1 && ($page ?? 1) < $pages): ?>
  <div class="me-more"><a class="me-btn" href="<?= e(url('desk/' . $cat['slug'])) ?>?page=<?= (int) $page + 1 ?>">Textes précédents</a></div>
  <?php endif; ?>
</div>
