<?php
/**
 * Le français du site — chrome only.
 *
 * Keys are the English strings the templates already contain, so anything not
 * listed here renders in English rather than breaking. Editorial copy is never
 * routed through this table; the newsroom writes in its own language.
 *
 * Register: québécois. Courriel rather than e-mail, fin de semaine rather than
 * week-end, clavardage rather than chat — the package is explicit about it
 * ("Un français tenu. Pas d'anglicismes de commodité").
 */

return [
    // --- navigation and chrome ------------------------------------------
    'Home'                => 'Accueil',
    'About'               => 'À propos',
    'Contact'             => 'Nous joindre',
    'Search'              => 'Chercher',
    'Search the archive'  => 'Chercher dans les archives',
    'Sign in'             => 'Se connecter',
    'Subscribe'           => 'S’abonner',
    'Newsletter'          => 'Infolettre',
    'Newsletters'         => 'Infolettres',
    'Sections'            => 'Rubriques',
    'Follow'              => 'Suivre',
    'The paper'           => 'Le journal',
    'Corrections'         => 'Corrections',
    'Newsroom sign-in'    => 'Accès rédaction',
    'Send a tip'          => 'Nous écrire',
    'RSS'                 => 'RSS',

    // --- front page and lists -------------------------------------------
    'Latest'              => 'Dernières',
    'Featured'            => 'À la une',
    'Read more'           => 'Lire la suite',
    'More top stories'    => 'Plus d’actualités',
    'The archive'         => 'Les archives',
    'Trending now'        => 'En vogue',
    'Lead story'          => 'Manchette',

    // --- article --------------------------------------------------------
    'By'                  => 'Par',
    'min read'            => 'min de lecture',
    'Share'               => 'Partager',
    'Correction'          => 'Correction',
    'Source material'     => 'Source',
    'Email'               => 'Courriel',
    'Copy the link'       => 'Copier le lien',
    'Copied'              => 'Copié',

    // --- newsletter -----------------------------------------------------
    'Sign up'             => 'S’inscrire',
    'Join'                => 'S’inscrire',
    'You’re on the list.' => 'C’est fait. Vous êtes inscrit.',
    'Email address'       => 'Adresse courriel',

    // --- empty states ---------------------------------------------------
    'Nothing filed yet.'  => 'Rien de publié pour l’instant.',

    // --- provenance (agent-filed stories) --------------------------------
    'Automated report' => 'Rapport automatisé',
    'This story was compiled by a monitoring system from the public records below and reviewed under this newsroom\'s editorial policy.'
        => 'Ce texte a été composé par un système de veille à partir des documents publics ci-dessous, sous la politique éditoriale de la rédaction.',
    'retrieved' => 'consulté le',
    // --- the newsroom sign-in (admin/login.php) -------------------------
    'Passphrase'          => 'Phrase de passe',
    'That session ended — sign in again to continue.'
        => 'Votre session a pris fin. Connectez-vous de nouveau pour continuer.',
    'Too many attempts. Wait fifteen minutes, then try again.'
        => 'Trop de tentatives. Attendez quinze minutes, puis réessayez.',
    'That email and passphrase don\'t match an account. Check both and try again.'
        => 'Ce courriel et cette phrase de passe ne correspondent à aucun compte. Vérifiez-les, puis réessayez.',
    'No accounts exist yet. The founding administrator is created on the server console, never from this page:'
        => 'Aucun compte n’existe encore. L’administrateur fondateur est créé sur la console du serveur, jamais depuis cette page :',
    'No accounts exist yet. The founding administrator is created on the server: php tools/setup-admin.php'
        => 'Aucun compte n’existe encore. L’administrateur fondateur est créé sur le serveur : php tools/setup-admin.php',
];
