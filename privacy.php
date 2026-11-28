<?php
/**
 * Per-site Privacy Policy (/privacy).
 * Custom copy lives in settings (privacy_heading, privacy_body, privacy_officer).
 * Ships a complete bilingual (en/fr) default covering PIPEDA and Quebec Law 25
 * whenever a paper has not written bespoke copy yet.
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/views/ui.php';

$siteTitle = setting('site_title', 'The Prairie Dispatch');
$isFr = pp_lang() === 'fr';
$defaultHeading = $isFr ? 'Politique de confidentialité' : 'Privacy policy';
$heading = trim((string) setting('privacy_heading', '')) ?: $defaultHeading;
$body = trim((string) setting('privacy_body', ''));
$officer = trim((string) setting('privacy_officer', '')) ?: trim((string) setting('contact_email', ''));
$hasAnalytics = trim((string) setting('analytics_code', '')) !== '';

if ($body === '') {
    if ($isFr) {
        $contactLine = $officer !== ''
            ? '<p>Pour toute question relative à la protection des renseignements personnels (Loi 25 du Québec et LPRPDE), communiquez avec le responsable de la protection des renseignements personnels : ' . e($officer) . '.</p>'
            : '<p>Pour toute question relative à la protection des renseignements personnels (Loi 25 du Québec et LPRPDE), communiquez avec la rédaction par notre <a href="' . e(url('contact')) . '">page de contact</a>.</p>';
        $analyticsLine = $hasAnalytics
            ? '<p><strong>Mesure d’audience :</strong> ce journal utilise un outil de mesure d’audience afin de comprendre les sujets lus et d’améliorer sa couverture. Vous pouvez bloquer les témoins dans les paramètres de votre navigateur.</p>'
            : '<p><strong>Mesure d’audience :</strong> aucune trousse de suivi publicitaire ou analytique tierce n’est active sur ce site à l’heure actuelle.</p>';
        $body = '<p>' . e($siteTitle) . ' respecte la vie privée de ses lecteurs et limite la collecte de renseignements personnels au strict nécessaire.</p>'
            . '<h2>Infolettre</h2>'
            . '<p>Lorsque vous vous abonnez à notre infolettre, nous conservons uniquement votre adresse courriel pour vous envoyer nos éditions. Chaque envoi contient un lien de désabonnement en un clic.</p>'
            . '<h2>Pistes et messages à la rédaction</h2>'
            . '<p>Le formulaire <a href="' . e(url('tips')) . '">Nous écrire</a> n’enregistre ni votre adresse IP ni l’empreinte de votre navigateur, et son accès est exclu des journaux du serveur.</p>'
            . '<h2>Journaux serveur et témoins</h2>'
            . '<p>Nos serveurs enregistrent les journaux techniques standards (requête, code de réponse, horodatage) à des fins de sécurité et de fiabilité. Les pages publiques ne déposent aucun témoin de session.</p>'
            . $analyticsLine
            . '<h2>Responsable et droits d’accès</h2>'
            . $contactLine;
    } else {
        $contactLine = $officer !== ''
            ? '<p>To request access to, correction of, or deletion of personal information held by ' . e($siteTitle) . ', contact our privacy officer at ' . e($officer) . '.</p>'
            : '<p>To request access to, correction of, or deletion of personal information held by ' . e($siteTitle) . ', reach the newsroom through our <a href="' . e(url('contact')) . '">contact page</a>.</p>';
        $analyticsLine = $hasAnalytics
            ? '<p><strong>Analytics:</strong> This paper uses third-party audience analytics to understand readership and improve local coverage. You can restrict cookies or tracking in your browser settings.</p>'
            : '<p><strong>Analytics:</strong> This paper does not currently run third-party audience tracking scripts on public pages.</p>';
        $body = '<p>' . e($siteTitle) . ' respects reader privacy and collects only the personal information needed to publish and deliver our journalism.</p>'
            . '<h2>Newsletter subscriptions</h2>'
            . '<p>When you subscribe to our newsletter, we store your email address solely to deliver editions. Every email includes a one-click unsubscribe link.</p>'
            . '<h2>News tips</h2>'
            . '<p>Submissions through our <a href="' . e(url('tips')) . '">Send a tip</a> page are stored without your IP address or browser fingerprint, and requests to that route are excluded from web server access logs.</p>'
            . '<h2>Server logs &amp; cookies</h2>'
            . '<p>Standard web server logs are retained briefly for security and reliability. Public reading pages do not set session cookies.</p>'
            . $analyticsLine
            . '<h2>Privacy contact &amp; rights</h2>'
            . $contactLine;
    }
}

page_header([
    'title'       => $heading,
    'description' => $isFr
        ? "Politique de confidentialité et protection des renseignements personnels — $siteTitle."
        : "Privacy policy and data practices for $siteTitle.",
    'canonical'   => site_url() . '/privacy',
], 'privacy');
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
