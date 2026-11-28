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
    'Breaking'            => 'Dernière heure',
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

    // --- newsroom defaults & optional disclosure -------------------------
    'Newsroom Staff' => 'La rédaction',
    'Prepared with AI assistance and reviewed by an editor.'
        => 'Préparé avec l’assistance de l’intelligence artificielle et révisé par la rédaction.',
    'Privacy policy'      => 'Politique de confidentialité',
    'Editorial standards' => 'Normes éditoriales',
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

    // --- the tips form (tips.php) ---------------------------------------
    'The newsroom'        => 'La rédaction',
    'Tell the editors of %s what you know. Tips go straight to the newsroom.'
        => 'Dites à la rédaction de %s ce que vous savez. Les messages vont directement à la rédaction.',
    'Seen something %s should look into? Tell the editors here. Your tip goes straight to the newsroom, and nothing you send is published as it stands.'
        => 'Vous avez vu quelque chose sur lequel %s devrait se pencher? Écrivez-le ici. Votre message va directement à la rédaction, et rien de ce que vous envoyez n’est publié tel quel.',
    'Thank you. Your tip is with the editors.'
        => 'Merci. Votre message est entre les mains de la rédaction.',
    "If you left a way to reach you, someone may be in touch. If you didn't, we have no way to reply, and that is fine."
        => 'Si vous avez laissé un moyen de vous joindre, quelqu’un pourrait communiquer avec vous. Sinon, nous n’avons aucun moyen de vous répondre, et c’est très bien ainsi.',
    'Send another tip'    => 'Envoyer un autre message',
    'Back to the front page' => 'Retour à la une',
    'The form expired, or was sent too quickly. Check your tip below and send it again.'
        => 'Le formulaire a expiré ou a été envoyé trop vite. Vérifiez votre message ci-dessous, puis envoyez-le de nouveau.',
    'The tips line is busy right now. Please try again in an hour.'
        => 'La ligne est très sollicitée en ce moment. Veuillez réessayer dans une heure.',
    'Some of the form needs another look.' => 'Certains champs sont à revoir.',
    'What should we know?' => 'Que devrions-nous savoir?',
    'What happened, where and when, and how you know. Up to 4,000 characters.'
        => 'Ce qui s’est passé, où et quand, et comment vous le savez. Jusqu’à 4 000 caractères.',
    'A link, if there is one' => 'Un lien, s’il y en a un',
    'Your name'           => 'Votre nom',
    'Optional.'           => 'Facultatif.',
    'How can we reach you?' => 'Comment vous joindre?',
    "An email address or a phone number. Leave your name and this blank to stay anonymous, but then we can't ask you anything."
        => 'Une adresse courriel ou un numéro de téléphone. Laissez votre nom et ce champ vides pour rester anonyme, mais nous ne pourrons alors rien vous demander.',
    'Send the tip'        => 'Envoyer',
    'Who reads it'        => 'Qui le lit',
    'Only the editors of %s. Your tip is saved without your IP address or any details of your device. We never publish your name or your contact details without your permission.'
        => 'Seule la rédaction de %s. Votre message est conservé sans votre adresse IP ni aucun détail sur votre appareil. Nous ne publions jamais votre nom ni vos coordonnées sans votre permission.',
    "Please don't send documents through this form. If you have them, leave a way to reach you and we'll arrange a safer way to receive them."
        => 'N’envoyez pas de documents par ce formulaire. Si vous en avez, laissez un moyen de vous joindre et nous conviendrons d’une façon plus sûre de les recevoir.',
    'Tell us what you know, in a sentence or more.'
        => 'Dites-nous ce que vous savez, en une phrase ou plus.',
    'That is more than 4,000 characters. Shorten it, or leave a way to reach you and we will ask for the rest.'
        => 'C’est plus de 4 000 caractères. Raccourcissez votre message, ou laissez un moyen de vous joindre et nous vous demanderons la suite.',
    'That link is not a web address. It should start with https://.'
        => 'Ce lien n’est pas une adresse Web. Il devrait commencer par https://.',
    'Keep your name under 120 characters.' => 'Votre nom doit compter moins de 120 caractères.',
    'Keep this under 255 characters.' => 'Ce champ doit compter moins de 255 caractères.',
];
