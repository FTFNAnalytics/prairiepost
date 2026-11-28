<?php
/**
 * Per-site Editorial Standards & Ethics page (/standards).
 * Custom copy lives in settings (standards_heading, standards_body, funding_note).
 * Wired into NewsMediaOrganization JSON-LD (`publishingPrinciples`).
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

$siteTitle = setting('site_title', 'The Prairie Dispatch');
$isFr = pp_lang() === 'fr';
$defaultHeading = $isFr ? 'Normes éditoriales et déontologie' : 'Editorial standards & ethics';
$heading = trim((string) setting('standards_heading', '')) ?: $defaultHeading;
$body = trim((string) setting('standards_body', ''));
$funding = trim((string) setting('funding_note', ''));

if ($body === '') {
    if ($isFr) {
        $fundPara = $funding !== '' ? '<p>' . e($funding) . '</p>' : '';
        $body = '<p>' . e($siteTitle) . ' s’engage à publier une information locale rigoureuse, vérifiée et indépendante.</p>'
            . '<h2>Indépendance éditoriale</h2>'
            . '<p>Les décisions journalistiques sont prises indépendamment des annonceurs, des commanditaires et des intérêts politiques. Tout contenu publicitaire ou commandité est clairement identifié.</p>'
            . '<h2>Sources et vérification</h2>'
            . '<p>Les reportages s’appuient sur des documents publics, des comptes rendus officiels et des témoignages directs. Lorsque des systèmes automatisés ou d’assistance rédactionnelle contribuent à la veille ou à la préparation d’un texte, la rédaction en fait mention et assume la responsabilité éditoriale.</p>'
            . '<h2>Corrections</h2>'
            . '<p>Nous corrigeons promptement et ouvertement toute erreur factuelle. Les corrections apparaissent en tête de l’article concerné et dans notre <a href="' . e(url('corrections')) . '">registre public des corrections</a>.</p>'
            . ($fundPara !== '' ? '<h2>Financement et propriété</h2>' . $fundPara : '')
            . '<p>Consultez également notre page <a href="' . e(url('about')) . '">À propos</a>, notre <a href="' . e(url('privacy')) . '">politique de confidentialité</a> ou <a href="' . e(url('tips')) . '">envoyez une piste à la rédaction</a>.</p>';
    } else {
        $fundPara = $funding !== '' ? '<p>' . e($funding) . '</p>' : '';
        $body = '<p>' . e($siteTitle) . ' is committed to accurate, fair, and independent local journalism.</p>'
            . '<h2>Editorial independence</h2>'
            . '<p>Newsroom decisions are made independently of advertisers, sponsors, and political interests. Paid placements and advertisements are always clearly labelled.</p>'
            . '<h2>Sourcing, verification &amp; automation</h2>'
            . '<p>Our reporting relies on public records, official proceedings, and primary sources. Where automated workflows or AI-assisted tools support monitoring or drafting, stories are clearly disclosed and remain subject to editorial oversight.</p>'
            . '<h2>Corrections policy</h2>'
            . '<p>We correct factual errors promptly and transparently. Every correction is displayed at the top of the story and archived in our public <a href="' . e(url('corrections')) . '">corrections file</a>.</p>'
            . ($fundPara !== '' ? '<h2>Ownership &amp; funding</h2>' . $fundPara : '')
            . '<p>Read more <a href="' . e(url('about')) . '">about the paper</a>, review our <a href="' . e(url('privacy')) . '">privacy policy</a>, or <a href="' . e(url('tips')) . '">send a tip to the newsroom</a>.</p>';
    }
}

page_header([
    'title'       => $heading,
    'description' => $isFr
        ? "Normes journalistiques, indépendance et politique de corrections — $siteTitle."
        : "Editorial standards, sourcing, automation disclosure, and corrections policy for $siteTitle.",
    'canonical'   => site_url() . '/standards',
], 'standards');
?>

<div class="pagehead wrap">
  <span class="pp-meta"><?= e(pp_t('The paper')) ?></span>
  <h1><?= e($heading) ?></h1>
  <div class="pp-horizon"></div>
</div>
<div class="article wrap">
  <div class="bodycopy"><?= sanitize_html($body) ?></div>
</div>

<?php page_footer(); ?>
