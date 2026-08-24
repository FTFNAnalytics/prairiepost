<?php
/**
 * Le Bleuet Blanc — la une (chrome.template = "bleuetblanc").
 * Incluse par index.php après page_header(); $hero est déjà résolu.
 *
 * La structure de la trousse, section 07 : bandeau de service et barre des
 * rubriques (le chrome, dans ui.php), puis la manchette pleine largeur en
 * bleu, trois colonnes — la salle de rédaction, les dernières, en vogue —
 * et le pied de page.
 *
 * Les aplats bleus portent la navigation et les listes ; le corps des textes
 * reste sur papier. Le magenta ne paraît que sur « le fil ».
 */

/** « Économie · Saguenay–Lac-Saint-Jean » : la rubrique, puis le lieu. */
$rubrique = function (array $p): string {
    $nom = $p['category_name'] ?? '';
    if ($nom === '') {
        return '';
    }
    $out = e(pp_desk_label($p['category_slug'] ?? null, $nom));
    if (!empty($p['dateline'])) {
        $out .= ' &middot; ' . e($p['dateline']);
    }
    return $out;
};

/** Les initiales d'une signature, pour la pastille de la rédaction. */
$initiales = function (string $nom): string {
    $mots = preg_split('/\s+/', trim($nom)) ?: [];
    $ini = '';
    foreach ($mots as $m) {
        if ($m !== '' && preg_match('/\p{L}/u', $m)) {
            $ini .= mb_strtoupper(mb_substr($m, 0, 1));
        }
        if (mb_strlen($ini) >= 2) {
            break;
        }
    }
    return $ini !== '' ? $ini : '·';
};

$vus     = $hero ? [(int) $hero['id']] : [];
$dernieres = latest_posts(4, $vus);
$vus     = array_merge($vus, array_map('intval', array_column($dernieres, 'id')));
$vogue   = latest_posts(6, $vus);

/* La salle de rédaction : les signatures qui ont publié, chacune une fois.
   La signature collective porte déjà le nom du bloc — l'y répéter n'apprend
   rien au lecteur, alors on la saute. */
$titreRedaction = 'La rédaction';
$signatures = [];
foreach (array_merge($hero ? [$hero] : [], $dernieres, $vogue) as $p) {
    $nom = trim((string) ($p['byline'] ?? ''));
    if ($nom === '' || $nom === $titreRedaction || isset($signatures[$nom])) {
        continue;
    }
    $signatures[$nom] = pp_desk_label($p['category_slug'] ?? null, $p['category_name'] ?? '');
    if (count($signatures) >= 6) {
        break;
    }
}
?>

<?php if ($hero): ?>
<div class="bb-manchette">
  <span class="filigrane" aria-hidden="true"><img src="<?= e(site_asset('mark-reversed.svg')) ?>" alt=""></span>
  <div class="dedans">
    <?php if ($r = $rubrique($hero)): ?><p class="rub"><?= $r ?></p><?php endif; ?>
    <h1><?= e($hero['title']) ?></h1>
    <?php if ($hero['lede']): ?><p class="chapeau"><?= e($hero['lede']) ?></p><?php endif; ?>
    <a class="lire" href="<?= e(url('story/' . $hero['slug'])) ?>"><?= e(pp_t('Read more')) ?></a>
  </div>
</div>
<?php endif; ?>

<div class="bb-colonnes">
  <?php if ($signatures): ?>
  <section class="bb-redaction" aria-label="<?= e($titreRedaction) ?>">
    <p class="bb-tetiere bb-tetiere--clair"><?= e($titreRedaction) ?></p>
    <?php foreach ($signatures as $nom => $poste): ?>
    <div class="sig">
      <span class="ini" aria-hidden="true"><?= e($initiales($nom)) ?></span>
      <span>
        <span class="qui"><?= e($nom) ?></span>
        <?php if ($poste !== ''): ?><span class="quoi" style="display:block"><?= e($poste) ?></span><?php endif; ?>
      </span>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <section class="bb-dernieres" aria-label="<?= e(pp_t('Latest')) ?>">
    <p class="bb-tetiere"><?= e(pp_t('Latest')) ?></p>
    <?php if (!$dernieres && !$hero): ?>
    <div class="bb-vide">
      <?= e(pp_t('Nothing filed yet.')) ?>
      La rédaction se connecte à <a href="/admin/">/admin/</a> pour publier le premier texte.
    </div>
    <?php endif; ?>
    <?php foreach ($dernieres as $p): ?>
    <article class="item">
      <span class="quand">
        <?= e(pp_desk_label($p['category_slug'] ?? null, $p['category_name'] ?? '')) ?>
        <span class="heure"><?= e(pp_clock(strtotime($p['published_at']))) ?></span>
      </span>
      <span>
        <h3><a style="color:inherit" href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <?php if ($p['lede']): ?><p><?= e(excerpt($p['lede'], 150)) ?></p><?php endif; ?>
      </span>
    </article>
    <?php endforeach; ?>
    <?php if ($dernieres): ?>
    <a class="bb-plus" href="<?= e(url('search')) ?>"><?= e(pp_t('More top stories')) ?></a>
    <?php endif; ?>
  </section>

  <?php if ($vogue): ?>
  <section class="bb-vogue" aria-label="<?= e(pp_t('Trending now')) ?>">
    <p class="bb-tetiere"><?= e(pp_t('Trending now')) ?></p>
    <?php foreach ($vogue as $i => $p): ?>
    <article class="item">
      <span class="n"><?= $i + 1 ?></span>
      <h3><a style="color:inherit" href="<?= e(url('story/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
    </article>
    <?php endforeach; ?>
    <a class="bb-plus" href="<?= e(url('search')) ?>"><?= e(pp_t('The archive')) ?></a>
  </section>
  <?php endif; ?>
</div>

<div class="bb-corps-page" style="padding-top:0">
  <?= ad_slot('top') ?>
  <section class="bb-infolettre">
    <h3><?= e(setting('newsletter_heading', 'L’infolettre du matin')) ?></h3>
    <?php if (isset($_GET['subscribed'])): ?>
    <p class="fait"><?= e(pp_t('You’re on the list.')) ?></p>
    <?php else: ?>
    <p><?= e(setting('newsletter_copy', 'Six textes par courriel, du lundi au vendredi, avant sept heures.')) ?></p>
    <form method="post" action="<?= e(url('subscribe')) ?>">
      <input type="email" name="email" required placeholder="vous@exemple.ca" aria-label="<?= e(pp_t('Email address')) ?>">
      <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
      <button type="submit"><?= e(pp_t('Sign up')) ?></button>
    </form>
    <?php endif; ?>
  </section>
</div>
