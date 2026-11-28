<?php
/* Le Memphré — front page. Toronto Telegraph's front in the owner's
   brand guidelines (Nov 2026). $hero resolved by index.php: the photo
   hero under the Dark Blue scrim (the Dark Blue panel with the reversed
   serpent on its bottom edge when the lead has no art), Featured Stories
   cards beside the rail — weather, the latest five, the subscribe box.
   Renders the empty state cleanly: the paper carries no stories before
   its inaugural edition. */
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
$meSeen = $hero ? [(int) $hero['id']] : [];
$meGrid = latest_posts(3, $meSeen);
$meSeen = array_merge($meSeen, array_column($meGrid, 'id'));
$meMore = latest_posts(6, $meSeen);
$meLatest = latest_posts(5, []);
$meW = array_pad(explode('|', pp_weather_line()), 2, '');
?>
<?php if (!$hero): ?>
<div class="me-wrap" style="padding-top:24px">
  <div class="me-empty">La première édition du Memphré est sous presse. La rédaction se connecte à <a href="/admin/">/admin/</a> et publie le premier texte.</div>
</div>
<?php elseif ($hero['image']): ?>
<div class="me-hero">
  <img class="bg" src="<?= e($hero['image']) ?>" alt="">
  <div class="scrim"></div>
  <div class="pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'Actualités'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e(excerpt($hero['lede'], 150)) ?></span><?php endif; ?>
    <a class="me-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Lire la suite &rarr;</a>
  </div>
</div>
<?php else: ?>
<div class="me-hero me-hero--noart">
  <img class="wm" src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
  <div class="pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'Actualités'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e($hero['lede']) ?></span><?php endif; ?>
    <a class="me-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Lire la suite &rarr;</a>
  </div>
</div>
<?php endif; ?>

<?php if ($hero): ?>
<div class="me-wrap">
  <div class="me-home">
    <div>
      <div class="me-ttl"><h2>À la une</h2></div>
      <?php if ($meGrid): ?>
      <div class="me-grid">
        <?php foreach ($meGrid as $p): ?>
        <article class="me-card<?= $p['image'] ? '' : ' noart' ?>">
          <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
          <div class="txt">
            <span class="me-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) ($p['category_name'] ?: 'Actualités'))) ?></span>
            <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
            <?php if ($p['lede']): ?><span class="meta"><?= e(excerpt($p['lede'], 95)) ?></span><?php endif; ?>
            <span class="meta"><?= $meSig($p) ?></span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="me-empty">D’autres textes s’en viennent — la rédaction y travaille.</div>
      <?php endif; ?>

      <?php if ($meMore): ?>
      <div class="me-ttl"><h2>Plus d’actualités</h2></div>
      <?php foreach ($meMore as $p): ?>
      <article class="me-row<?= $p['image'] ? '' : ' noart' ?>">
        <div>
          <span class="me-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
          <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
          <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 130)) ?></p><?php endif; ?>
          <span class="meta"><?= $meSig($p) ?></span>
        </div>
        <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
      </article>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <aside class="me-rail">
      <?php if (pp_weather_line() !== ''): ?>
      <div class="me-box me-weather">
        <div class="hd"><h3>Météo</h3></div>
        <div class="now">
          <span class="temp"><?= e(trim($meW[0])) ?></span>
          <span class="cond"><?= e(trim($meW[1])) ?></span>
        </div>
        <div class="src"><?= pp_weather_source_html() ?></div>
      </div>
      <?php endif; ?>
      <?php if ($meLatest): ?>
      <div class="me-box me-trend">
        <div class="hd"><h3>Dernières</h3></div>
        <ol>
          <?php $meN = 0; foreach ($meLatest as $p): $meN++; ?>
          <li><span class="n"><?= $meN ?></span><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php endif; ?>
      <div class="me-subbox">
        <h3><?= e(setting('newsletter_heading', 'L’infolettre du Memphré')) ?></h3>
        <p><?= e(setting('newsletter_copy', 'La une du Memphré dans votre boîte courriel chaque matin de semaine : ce que Sherbrooke et les Cantons-de-l’Est ont décidé, construit et débattu.')) ?></p>
        <a class="me-btn" href="<?= e(url('newsletter/')) ?>"><?= e(pp_t('Subscribe')) ?></a>
      </div>
    </aside>
  </div>
</div>
<?php endif; ?>
