<?php
/* Les Chutes — front page. Toronto Telegraph's front in the owner's
   brand package (28 Nov 2026). $hero resolved by index.php: the photo
   hero under the Bleu profond scrim (the Bleu profond panel with the
   reversed waterfall on its bottom edge when the lead has no art),
   Featured Stories cards beside the rail — weather, the latest five, the
   subscribe box. Renders the empty state cleanly: the paper carries no
   stories before its inaugural edition. */
// Bylines in French: the shared dateline() prints "By" and English times.
$chSig = static function (array $p): string {
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
$chSeen = $hero ? [(int) $hero['id']] : [];
$chGrid = latest_posts(3, $chSeen);
$chSeen = array_merge($chSeen, array_column($chGrid, 'id'));
$chMore = latest_posts(6, $chSeen);
$chLatest = latest_posts(5, []);
$chW = array_pad(explode('|', pp_weather_line()), 2, '');
?>
<?php if (!$hero): ?>
<div class="ch-wrap" style="padding-top:24px">
  <div class="ch-empty">La première édition des Chutes est sous presse. La rédaction se connecte à <a href="/admin/">/admin/</a> et publie le premier texte.</div>
</div>
<?php elseif ($hero['image']): ?>
<div class="ch-hero">
  <img class="bg" src="<?= e($hero['image']) ?>" alt="">
  <div class="scrim"></div>
  <div class="pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'Actualités'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e(excerpt($hero['lede'], 150)) ?></span><?php endif; ?>
    <a class="ch-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Lire la suite &rarr;</a>
  </div>
</div>
<?php else: ?>
<div class="ch-hero ch-hero--noart">
  <img class="wm" src="<?= e(site_asset('mark-reversed.svg')) ?>" alt="">
  <div class="pad">
    <span class="kick"><?= e(pp_desk_label((string) $hero['category_slug'], (string) ($hero['category_name'] ?: 'Actualités'))) ?></span>
    <h1><a href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e($hero['title']) ?></a></h1>
    <?php if ($hero['lede']): ?><span class="meta"><?= e($hero['lede']) ?></span><?php endif; ?>
    <a class="ch-btn" href="<?= e(url('story/' . $hero['slug'])) ?>">Lire la suite &rarr;</a>
  </div>
</div>
<?php endif; ?>

<?php if ($hero): ?>
<div class="ch-wrap">
  <div class="ch-home">
    <div>
      <div class="ch-ttl"><h2>À la une</h2></div>
      <?php if ($chGrid): ?>
      <div class="ch-grid">
        <?php foreach ($chGrid as $p): ?>
        <article class="ch-card<?= $p['image'] ? '' : ' noart' ?>">
          <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
          <div class="txt">
            <span class="ch-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) ($p['category_name'] ?: 'Actualités'))) ?></span>
            <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
            <?php if ($p['lede']): ?><span class="meta"><?= e(excerpt($p['lede'], 95)) ?></span><?php endif; ?>
            <span class="meta"><?= $chSig($p) ?></span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="ch-empty">D’autres textes s’en viennent — la rédaction y travaille.</div>
      <?php endif; ?>

      <?php if ($chMore): ?>
      <div class="ch-ttl"><h2>Plus d’actualités</h2></div>
      <?php foreach ($chMore as $p): ?>
      <article class="ch-row<?= $p['image'] ? '' : ' noart' ?>">
        <div>
          <span class="ch-chip"><?= e(pp_desk_label((string) $p['category_slug'], (string) $p['category_name'])) ?></span>
          <h3><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
          <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 130)) ?></p><?php endif; ?>
          <span class="meta"><?= $chSig($p) ?></span>
        </div>
        <?php if ($p['image']): ?><a href="<?= e(url('story/' . $p['slug'])) ?>"><img src="<?= e($p['image']) ?>" alt=""></a><?php endif; ?>
      </article>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <aside class="ch-rail">
      <?php if (pp_weather_line() !== ''): ?>
      <div class="ch-box ch-weather">
        <div class="hd"><h3>Météo</h3></div>
        <div class="now">
          <span class="temp"><?= e(trim($chW[0])) ?></span>
          <span class="cond"><?= e(trim($chW[1])) ?></span>
        </div>
        <div class="src"><?= pp_weather_source_html() ?></div>
      </div>
      <?php endif; ?>
      <?php if ($chLatest): ?>
      <div class="ch-box ch-trend">
        <div class="hd"><h3>Dernières</h3></div>
        <ol>
          <?php $chN = 0; foreach ($chLatest as $p): $chN++; ?>
          <li><span class="n"><?= $chN ?></span><a href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php endif; ?>
      <div class="ch-subbox">
        <h3><?= e(setting('newsletter_heading', 'L’infolettre des Chutes')) ?></h3>
        <p><?= e(setting('newsletter_copy', 'La une des Chutes dans votre boîte courriel chaque matin de semaine : ce que la capitale a décidé, construit et débattu.')) ?></p>
        <a class="ch-btn" href="<?= e(url('newsletter/')) ?>"><?= e(pp_t('Subscribe')) ?></a>
      </div>
    </aside>
  </div>
</div>
<?php endif; ?>
