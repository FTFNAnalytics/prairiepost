<?php
/**
 * Le Bleuet Blanc — trousse de lancement.
 * Chargée une fois par `PP_SITE=bleuet-blanc php tools/seed-launch.php`.
 *
 * CONTENU DE DÉMONSTRATION. Ces vingt-cinq textes servent à montrer la
 * maquette — la manchette pleine largeur, les trois colonnes, la citation en
 * italique, les unes de rubrique. Ils sont illustratifs et non journalistiques,
 * et la rédaction les remplacera par son propre travail.
 *
 * Chaque texte porte un slug explicite préfixé « bb- ». `posts.slug` est
 * UNIQUE sur toute la base partagée et le semoir saute silencieusement un
 * texte dont le slug est déjà pris ; rien n'est laissé au hasard.
 *
 * Le français est celui de la trousse : courriel, fin de semaine, clavardage.
 * Pas d'anglicismes de commodité.
 */

$ilya = fn (string $decalage) => date('Y-m-d H:i:s', strtotime($decalage));
$p = fn (string ...$paras) => '<p>' . implode('</p><p>', $paras) . '</p>';
$q = fn (string $cit, string $qui) => '<blockquote><p>' . $cit . '</p><cite>' . $qui . '</cite></blockquote>';
$h = fn (string $titre) => '<h2>' . $titre . '</h2>';
$img = fn (string $f) => '/assets/sites/bleuet-blanc/img/' . $f;

return [

    /* Neuf rubriques, plus le fil. La barre en porte six ; les autres vivent
       dans le pied de page. Les slugs évitent les collisions avec les
       rubriques anglaises déjà semées par les journaux frères. */
    'desks' => [
        ['name' => 'Actualités',    'slug' => 'actualites',    'color' => '#095797', 'description' => 'Le jour même, du national au local.'],
        ['name' => 'Politique',     'slug' => 'politique',     'color' => '#095797', 'description' => 'Québec, Ottawa, hôtels de ville.'],
        ['name' => 'Économie',      'slug' => 'economie',      'color' => '#095797', 'description' => 'Emploi, ressources, PME régionales.'],
        ['name' => 'Régions',       'slug' => 'regions',       'color' => '#095797', 'description' => 'Dix-sept régions, une par une.'],
        ['name' => 'Culture',       'slug' => 'culture-qc',    'color' => '#095797', 'description' => 'Musique, livres, écrans d’ici.'],
        ['name' => 'Société',       'slug' => 'societe',       'color' => '#095797', 'description' => 'Santé, éducation, langue.'],
        ['name' => 'Environnement', 'slug' => 'environnement', 'color' => '#095797', 'description' => 'Forêt, eau, énergie, climat.'],
        ['name' => 'Sports',        'slug' => 'sports-qc',     'color' => '#095797', 'description' => 'Du junior majeur au professionnel.'],
        ['name' => 'Idées',         'slug' => 'idees',         'color' => '#095797', 'description' => 'Chroniques et lettres, toujours signées.'],
        ['name' => 'Le fil',        'slug' => 'le-fil',        'color' => '#D6006C', 'description' => 'En direct. Le seul endroit où le magenta paraît.'],
    ],

    'settings' => [
        'site_title'         => 'Le Bleuet Blanc',
        'tagline'            => 'Le Québec, de la région vers le monde',
        'meta_description'   => 'Quotidien numérique québécois : actualités, politique, économie, régions, culture, société, environnement, sports et idées.',
        'footer_line'        => 'Le Québec, de la région vers le monde',
        'contact_email'      => 'redaction@bleuetblanc.ca',
        'newsletter_heading' => 'L’infolettre du matin',
        'newsletter_copy'    => 'Six textes par courriel, du lundi au vendredi, avant sept heures. Gratuite.',
        'breaking_label'     => '',
        'breaking_url'       => '',
        'regions'            => json_encode([
            'saguenay' => 'Saguenay–Lac-Saint-Jean',
            'quebec'   => 'Québec',
            'national' => 'National',
        ]),
    ],

    /* Fils de veille pour la revue de presse du matin. Ils alimentent le
       tableau de bord ; ils ne publient rien d'eux-mêmes. */
    'sources' => [
        ['Radio-Canada · Québec',  'https://ici.radio-canada.ca/rss/4159',  'quebec'],
        ['Radio-Canada · Économie', 'https://ici.radio-canada.ca/rss/5877', 'national'],
    ],

    'stories' => [

        /* ------------------------------------------------------ la manchette --- */
        [
            'title' => 'La bleuetière comme modèle d’exportation',
            'slug' => 'bb-la-bleuetiere-comme-modele-d-exportation',
            'desk' => 'economie', 'byline' => 'Camille Tremblay', 'dateline' => 'Saguenay–Lac-Saint-Jean',
            'lede' => 'Les producteurs du Lac-Saint-Jean vendent désormais plus de la moitié de leur récolte hors du Canada. Le virage a pris dix ans et deux mauvaises saisons.',
            'image' => $img('bleuetiere.svg'),
            'image_caption' => 'La récolte, un mardi matin de la fin d’août.',
            'image_credit' => 'Le Bleuet Blanc',
            'featured' => 1, 'placement' => 'hero', 'views' => 4120, 'published' => $ilya('-2 hours'),
            'tags' => 'bleuet, exportation, agroalimentaire',
            'body' => $p(
                'La première caisse est partie en 2016, presque par accident : un acheteur japonais de passage à Alma avait goûté au produit congelé et demandé un prix. Personne, ce jour-là, n’avait de prix à lui donner.',
                'Dix ans plus tard, cinquante-quatre pour cent de la récolte régionale quitte le pays. La proportion était de neuf pour cent avant la première commande.'
            )
            . $h('Ce que le virage a coûté')
            . $p(
                'Deux saisons, d’abord. Celle de 2019, où le gel de la mi-juin a emporté le tiers des champs, et celle de 2021, où le conteneur est resté six semaines au port sans que personne ne sache à qui téléphoner.',
                'Puis une manière de travailler. Exporter suppose un calibre constant, une traçabilité par parcelle et des analyses que le marché intérieur n’exigeait pas. Les petits producteurs ont dû se regrouper ou renoncer ; onze l’ont fait, quatre ont renoncé.'
            )
            . $q('On a arrêté de vendre du bleuet. On vend une garantie, et le bleuet vient avec.', 'Un producteur de Saint-Félicien')
            . $p(
                'Le modèle intéresse maintenant d’autres filières. La chambre de commerce régionale a reçu quatre délégations cette année — canneberge, sirop, petits fruits nordiques — venues poser les mêmes questions.',
                'La réponse qu’on leur donne est toujours la même, et elle n’est pas commode : le premier contrat ne rapporte rien, le deuxième non plus, et c’est au troisième que l’on sait si l’on a bien fait.',
                'La récolte de cette année s’annonce forte. Les contrats, eux, sont signés depuis février.'
            ),
        ],

        /* -------------------------------------------------------- politique --- */
        [
            'title' => 'Québec dépose son projet de loi sur l’aménagement',
            'slug' => 'bb-quebec-depose-son-projet-de-loi-sur-l-amenagement',
            'desk' => 'politique', 'byline' => 'Jean-Philippe Côté', 'dateline' => 'Assemblée nationale',
            'lede' => 'La réforme touche les 1 100 municipalités et impose un délai de deux ans pour réviser les plans d’urbanisme.',
            'views' => 2870, 'published' => $ilya('-5 hours'),
            'tags' => 'urbanisme, municipalités, législation',
            'body' => $p(
                'Le projet de loi tient en quatre-vingt-onze articles et son cœur en tient trois : le délai, le seuil de densité, et qui tranche en cas de désaccord.',
                'Le délai est de deux ans. Les municipalités de moins de dix mille habitants disent qu’il leur en faudrait quatre, et qu’elles n’ont pas d’urbaniste à temps plein pour s’y mettre.',
                'Le ministère répond que l’accompagnement est prévu. Le budget de cet accompagnement n’est pas encore chiffré, ce que l’opposition a relevé dès la première heure de débat.'
            )
            . $p(
                'Sur le fond, peu de monde conteste la nécessité. Les plans d’urbanisme de trois cents municipalités datent d’avant 2010 et plusieurs d’avant 2002.',
                'Ce qui se joue est le rythme, et derrière le rythme, la question habituelle : une réforme conçue pour les grandes villes s’applique-t-elle telle quelle à une municipalité de huit cents personnes.'
            ),
        ],
        [
            'title' => 'Le financement des partis municipaux revient au feuilleton',
            'slug' => 'bb-financement-des-partis-municipaux',
            'desk' => 'politique', 'byline' => 'Jean-Philippe Côté', 'dateline' => '',
            'lede' => 'Trois formations demandent le relèvement du plafond des dons ; le directeur général des élections s’y oppose.',
            'views' => 940, 'published' => $ilya('-1 day 4 hours'),
            'tags' => 'financement, élections',
            'body' => $p(
                'Le plafond est à cent dollars depuis 2013. Les partis municipaux plaident qu’il rend la campagne impossible ailleurs que dans les grandes villes.',
                'Le directeur général des élections rappelle pourquoi il a été fixé là, et le rappelle dans les mêmes termes qu’en 2013.',
                'Le débat reviendra à l’automne. Les élections municipales, elles, sont en novembre.'
            ),
        ],

        /* ---------------------------------------------------------- régions --- */
        [
            'title' => 'Rimouski rouvre son traversier après trois semaines',
            'slug' => 'bb-rimouski-rouvre-son-traversier',
            'desk' => 'regions', 'byline' => 'Noémie Lapointe', 'dateline' => 'Rimouski',
            'lede' => 'Le retour du service rétablit le lien avec la Côte-Nord au plus fort de la saison touristique.',
            'image' => $img('traversier.svg'),
            'image_caption' => 'Le quai, à la reprise du service.',
            'image_credit' => 'Le Bleuet Blanc',
            'views' => 1980, 'published' => $ilya('-7 hours'),
            'tags' => 'traversier, côte-nord, transport',
            'body' => $p(
                'La panne était mécanique et la pièce venait d’Europe. Trois semaines, dont onze jours d’attente au dédouanement.',
                'Le service reprend à l’horaire d’été, quatre traversées par jour, jusqu’à la fin septembre.',
                'La chambre de commerce évalue la perte à un peu plus de deux millions pour les commerces des deux rives. La société des traversiers n’a pas commenté ce chiffre.'
            ),
        ],
        [
            'title' => 'La relève agricole achète, mais plus petit',
            'slug' => 'bb-la-releve-agricole-achete-mais-plus-petit',
            'desk' => 'regions', 'byline' => 'Noémie Lapointe', 'dateline' => 'Centre-du-Québec',
            'lede' => 'Le nombre de transferts a monté de dix-huit pour cent en cinq ans. La superficie moyenne, elle, a baissé du tiers.',
            'views' => 760, 'published' => $ilya('-2 days'),
            'tags' => 'agriculture, relève, terres',
            'body' => $p(
                'Les deux chiffres racontent la même chose : la relève existe et elle n’a pas les moyens des fermes qu’elle remplace.',
                'Ce qui s’achète maintenant, ce sont des unités de cinquante à quatre-vingts hectares, souvent en maraîcher ou en petits fruits, souvent avec une vente directe attachée.',
                'Les terres des grandes fermes, elles, continuent d’aller à d’autres grandes fermes.'
            ),
        ],

        /* --------------------------------------------------------- société --- */
        [
            'title' => 'Les cégeps attendent 4 000 inscriptions de plus',
            'slug' => 'bb-les-cegeps-attendent-4000-inscriptions-de-plus',
            'desk' => 'societe', 'byline' => 'Marie-Soleil Gagnon', 'dateline' => '',
            'lede' => 'La hausse se concentre en Estrie et dans la Capitale-Nationale, où les résidences sont déjà pleines.',
            'views' => 1640, 'published' => $ilya('-9 hours'),
            'tags' => 'éducation, cégeps, logement',
            'body' => $p(
                'Quatre mille inscriptions de plus, et à peu près autant de lits qui n’existent pas.',
                'Deux cégeps ont loué des chambres d’hôtel pour la session d’automne. Un troisième a écrit aux familles de la région pour demander qui pouvait héberger.',
                'Le ministère parle d’une pointe démographique connue depuis 2019. Les cégeps répondent que la connaître et l’avoir financée sont deux choses différentes.'
            ),
        ],
        [
            'title' => 'Ce que le nouveau règlement change pour les locataires',
            'slug' => 'bb-ce-que-le-nouveau-reglement-change-pour-les-locataires',
            'desk' => 'societe', 'byline' => 'Marie-Soleil Gagnon', 'dateline' => '',
            'lede' => 'Cinq changements entrent en vigueur le premier octobre. Trois touchent la reprise de logement.',
            'views' => 3210, 'published' => $ilya('-1 day 2 hours'),
            'tags' => 'logement, droit, locataires',
            'body' => $p(
                'Le premier changement est le préavis : six mois au lieu de quatre pour une reprise, et la preuve du lien de parenté doit être jointe à l’avis plutôt que fournie en cas de contestation.',
                'Le deuxième est l’indemnité, portée à trois mois de loyer. Le troisième oblige le propriétaire à occuper le logement pendant au moins un an.',
                'Les deux autres concernent la cession de bail et les frais exigibles à la signature.',
                'Les baux signés avant le premier octobre restent soumis aux anciennes règles jusqu’à leur renouvellement.'
            ),
        ],

        /* --------------------------------------------------------- culture --- */
        [
            'title' => 'Une saison de théâtre entièrement en tournée',
            'slug' => 'bb-une-saison-de-theatre-entierement-en-tournee',
            'desk' => 'culture-qc', 'byline' => 'Marie-Soleil Gagnon', 'dateline' => 'Trois-Rivières',
            'lede' => 'Onze villes, aucune salle fixe : le pari d’une compagnie de Trois-Rivières pour rejoindre son public.',
            'image' => $img('theatre.svg'),
            'image_caption' => 'Le montage, deux heures avant la première.',
            'image_credit' => 'Le Bleuet Blanc',
            'views' => 1220, 'published' => $ilya('-12 hours'),
            'tags' => 'théâtre, tournée, régions',
            'body' => $p(
                'La compagnie a rendu sa salle en juin. Le loyer représentait quarante et un pour cent de son budget et remplissait, les bonnes semaines, la moitié des sièges.',
                'La saison se joue donc dans onze villes, dans des salles municipales, un sous-sol d’église et un aréna.'
            )
            . $q('On a passé quinze ans à demander au public de venir à nous. On avait la réponse depuis quinze ans.', 'La directrice artistique')
            . $p(
                'Le modèle a ses coûts : le décor tient dans un camion et se monte en deux heures, ce qui a supposé de le redessiner en entier.',
                'Les six premières dates sont complètes.'
            ),
        ],
        [
            'title' => 'Entretien : diriger un festival sans commanditaire',
            'slug' => 'bb-entretien-diriger-un-festival-sans-commanditaire',
            'desk' => 'culture-qc', 'byline' => 'Marie-Soleil Gagnon', 'dateline' => '',
            'lede' => 'Après le retrait de son partenaire principal, un festival de l’Outaouais a bouclé son budget autrement. Conversation.',
            'views' => 690, 'published' => $ilya('-3 days'),
            'tags' => 'festival, financement, culture',
            'body' => $p(
                'Le retrait a été annoncé en mars, pour une édition qui commençait en juillet.',
                'Ce qui a remplacé la commandite : la billetterie à prix variable, une campagne de dons de proximité, et la décision de réduire la programmation d’un jour plutôt que d’en diluer la qualité.',
                'Le festival a terminé à l’équilibre, pour la première fois en six ans.'
            ),
        ],

        /* --------------------------------------------------- environnement --- */
        [
            'title' => 'Cinq cartes pour comprendre la crise de l’eau',
            'slug' => 'bb-cinq-cartes-pour-comprendre-la-crise-de-l-eau',
            'desk' => 'environnement', 'byline' => 'Alexis Boivin', 'dateline' => '',
            'lede' => 'Les prélèvements, les nappes, les avis d’ébullition, les usages industriels et ce que le ministère publie. Une carte par question.',
            'image' => $img('cartes-eau.svg'),
            'image_caption' => 'Les avis d’ébullition en vigueur, par municipalité.',
            'image_credit' => 'Le Bleuet Blanc',
            'views' => 2540, 'published' => $ilya('-1 day 6 hours'),
            'tags' => 'eau, données, environnement',
            'body' => $p(
                'Les données existent toutes. Elles vivent dans cinq systèmes différents, dans quatre formats, et deux d’entre elles ne sont pas téléchargeables.',
                'Ces cartes les mettent côte à côte. C’est tout ce qu’elles font, et c’est la première fois que ce soit fait.',
                'Les corrections sont bienvenues et seront appliquées le jour même.'
            ),
        ],
        [
            'title' => 'Pourquoi la récolte a commencé douze jours plus tôt',
            'slug' => 'bb-pourquoi-la-recolte-a-commence-douze-jours-plus-tot',
            'desk' => 'environnement', 'byline' => 'Alexis Boivin', 'dateline' => '',
            'lede' => 'Un printemps chaud, une nuit de gel évitée de justesse, et une courbe qui monte depuis vingt ans.',
            'views' => 1080, 'published' => $ilya('-2 days 5 hours'),
            'tags' => 'climat, agriculture, saison',
            'body' => $p(
                'Douze jours, ce n’est pas une anomalie isolée : la date moyenne du début de la récolte avance d’un peu plus d’une journée par tranche de deux ans depuis 2004.',
                'Ce que cela change, sur le terrain, tient surtout à la main-d’œuvre. Les équipes saisonnières arrivent selon des permis datés, et les dates n’avancent pas.'
            ),
        ],

        /* ------------------------------------------------------------ idées --- */
        [
            'title' => 'Le train de nuit vers Jonquière, cinquante ans plus tard',
            'slug' => 'bb-le-train-de-nuit-vers-jonquiere',
            'desk' => 'idees', 'byline' => 'Étienne Fortin', 'dateline' => '',
            'lede' => 'Le service existe encore, trois fois par semaine, et presque personne ne le sait. C’est le problème, pas l’achalandage.',
            'views' => 1470, 'published' => $ilya('-1 day 9 hours'),
            'tags' => 'transport, régions, chronique',
            'body' => $p(
                'On dit que le train n’est pas rentable. Il ne l’est pas. On dit ensuite que c’est faute de passagers, et c’est là que le raisonnement se retourne.',
                'Il n’y a pas d’horaire lisible, pas de correspondance annoncée, et l’achat du billet suppose de savoir que le service existe.'
            )
            . $q('Un service que l’on n’annonce pas n’est pas un service. C’est une ligne dans un budget.', 'L’auteur')
            . $p(
                'La question n’est pas de savoir si l’on subventionne. On subventionne déjà. Elle est de savoir si l’on subventionne quelque chose que les gens peuvent utiliser.'
            ),
        ],

        /* ------------------------------------------------------------ le fil --- */
        [
            'title' => 'Le fil : la journée de mardi, heure par heure',
            'slug' => 'bb-le-fil-la-journee-de-mardi',
            'desk' => 'le-fil', 'byline' => 'La rédaction', 'dateline' => '',
            'lede' => 'Le dépôt du projet de loi, la reprise du traversier et les inscriptions collégiales, suivis en direct.',
            'views' => 5600, 'published' => $ilya('-3 hours'),
            'tags' => 'direct, politique',
            'body' => $p(
                'Le fil est ouvert. Il ferme à dix-neuf heures.',
                'Les entrées les plus récentes apparaissent en premier ; les heures sont celles de l’Est.'
            ),
        ],

        /* ------------------------------------------------------------ sports --- */
        [
            'title' => 'Le junior majeur revient avec un calendrier resserré',
            'slug' => 'bb-le-junior-majeur-revient-avec-un-calendrier-resserre',
            'desk' => 'sports-qc', 'byline' => 'Étienne Fortin', 'dateline' => '',
            'lede' => 'Quatre matchs de moins, deux congés de plus, et une saison qui finit dix jours plus tôt.',
            'views' => 830, 'published' => $ilya('-4 days'),
            'tags' => 'hockey, junior majeur',
            'body' => $p(
                'La ligue coupe quatre matchs par équipe et ajoute deux pauses. Les entraîneurs demandaient les pauses depuis trois ans ; les propriétaires refusaient les coupes.',
                'Ce qui a changé, c’est le transport : les deux dernières saisons ont vu les coûts d’autobus monter au point où quatre matchs de moins se paient d’eux-mêmes.'
            ),
        ],

        /* ------------------------------------------------------- actualités --- */
        [
            'title' => 'Un train de marchandises déraille près de Drummondville',
            'slug' => 'bb-deraillement-pres-de-drummondville',
            'desk' => 'actualites', 'byline' => 'Camille Tremblay', 'dateline' => 'Drummondville',
            'lede' => 'Onze wagons hors des rails, aucun blessé, et une route régionale fermée pour la journée.',
            'views' => 4120, 'published' => $ilya('-2 hours'),
            'tags' => 'transport, sécurité, centre-du-québec',
            'body' => $p(
                'Le déraillement s’est produit peu avant six heures, à trois kilomètres à l’ouest de la gare de triage. Onze wagons sont sortis des rails ; deux se sont couchés dans le fossé.',
                'Aucun ne transportait de matière dangereuse, a confirmé le transporteur en milieu d’avant-midi. La route régionale qui longe la voie restera fermée jusqu’à ce que les wagons soient relevés.',
                'Le Bureau de la sécurité des transports a dépêché deux enquêteurs. Ils s’intéressent d’abord à l’état de la voie, réparée en juin après une inspection qui avait relevé un défaut d’alignement.'
            )
            . $p(
                'La municipalité a ouvert un centre pour la quinzaine de familles dont l’accès à la maison est coupé. Personne n’y avait passé la nuit au moment d’écrire ces lignes.'
            ),
        ],
        [
            'title' => 'Hydro-Québec rétablit le courant dans Charlevoix',
            'slug' => 'bb-hydro-retablit-le-courant-dans-charlevoix',
            'desk' => 'actualites', 'byline' => 'Camille Tremblay', 'dateline' => 'La Malbaie',
            'lede' => 'Quarante heures sans électricité pour six mille abonnés. La société d’État parle d’un orage « hors des normes de conception ».',
            'views' => 2740, 'published' => $ilya('-14 hours'),
            'tags' => 'hydro-québec, panne, charlevoix',
            'body' => $p(
                'Le dernier abonné a été rebranché dimanche soir. Le plus long des rétablissements aura pris quarante heures — dans un secteur où la ligne traverse huit kilomètres de forêt.',
                'Hydro-Québec compte quatre-vingt-onze poteaux à remplacer. La cause est banale et connue : des arbres tombés sur la ligne, plus vite que les équipes ne pouvaient les dégager.',
                'La question qui reste est celle du déboisement des emprises, réduit de moitié depuis 2018 pour des motifs budgétaires. La société d’État dit réexaminer la décision chaque année.'
            ),
        ],
        [
            'title' => 'La ministre annonce 300 places de garderie de plus en Estrie',
            'slug' => 'bb-300-places-de-garderie-de-plus-en-estrie',
            'desk' => 'actualites', 'byline' => 'Marie-Soleil Gagnon', 'dateline' => 'Sherbrooke',
            'lede' => 'Les places s’ajoutent aux 1 200 promises en 2023, dont un peu plus de la moitié a ouvert.',
            'views' => 1510, 'published' => $ilya('-1 day 2 hours'),
            'tags' => 'famille, garderies, estrie',
            'body' => $p(
                'Trois cents places, réparties sur onze installations, dont sept qui existent déjà et agrandissent.',
                'C’est la partie facile. Les quatre autres sont des constructions neuves, et c’est là que le calendrier a glissé les trois dernières fois : le terrain, le permis, puis l’éducatrice qu’on ne trouve pas.',
                'La ministre a été franche là-dessus. « Les places, je peux les annoncer. Le personnel, je ne peux pas l’annoncer », a-t-elle dit, ce qui aura été la phrase la plus citée de la journée.'
            ),
        ],
        [
            'title' => 'Le fédéral et Québec s’entendent sur le pont de l’Île-aux-Tourtes',
            'slug' => 'bb-entente-sur-le-pont-de-l-ile-aux-tourtes',
            'desk' => 'actualites', 'byline' => 'Jean-Philippe Côté', 'dateline' => 'Ottawa',
            'lede' => 'Le partage des coûts était bloqué depuis dix-huit mois. Il l’est moins depuis vendredi.',
            'views' => 1180, 'published' => $ilya('-3 days'),
            'tags' => 'infrastructures, fédéral-provincial',
            'body' => $p(
                'L’entente porte sur le partage, pas sur le montant : Ottawa paiera quarante pour cent d’une facture que personne n’a encore arrêtée.',
                'Les deux gouvernements s’en félicitent dans les mêmes termes, ce qui arrive rarement et signale surtout que le dossier était devenu embarrassant pour les deux.',
                'Les travaux de la structure provisoire, eux, continuent comme avant, et se terminent en 2028 selon le dernier échéancier public.'
            ),
        ],

        /* -------------------------------------------------------- économie --- */
        [
            'title' => 'Le prix du bois d’œuvre remonte, les scieries ne rappellent pas',
            'slug' => 'bb-le-prix-du-bois-remonte-les-scieries-ne-rappellent-pas',
            'desk' => 'economie', 'byline' => 'Camille Tremblay', 'dateline' => 'Saguenay',
            'lede' => 'Trois hausses en six semaines, et aucun des quatre cents travailleurs mis à pied cet hiver n’est retourné à l’usine.',
            'views' => 2210, 'published' => $ilya('-11 hours'),
            'tags' => 'forêt, emploi, saguenay',
            'body' => $p(
                'Le prix a repris trente pour cent depuis juin. Les mises à pied datent de février et tiennent toujours.',
                'Les entreprises expliquent qu’une remontée de six semaines ne justifie pas de rouvrir un quart, et qu’elles attendent de voir passer l’automne.',
                'Le syndicat répond que l’argument a servi en 2019 et en 2022, et que dans les deux cas le quart n’est jamais revenu.'
            )
            . $p(
                'Entre les deux, il y a une donnée que personne ne conteste : la région a perdu neuf cents emplois en scierie depuis dix ans, et les hausses de prix successives n’en ont ramené aucun durablement.'
            ),
        ],
        [
            'title' => 'Une coopérative rachète la dernière quincaillerie du village',
            'slug' => 'bb-une-cooperative-rachete-la-derniere-quincaillerie',
            'desk' => 'economie', 'byline' => 'Noémie Lapointe', 'dateline' => 'Saint-Fabien',
            'lede' => 'Cent quatre-vingts parts vendues en trois semaines dans une municipalité de neuf cents personnes.',
            'views' => 1340, 'published' => $ilya('-2 days 6 hours'),
            'tags' => 'coopératives, commerce, bas-saint-laurent',
            'body' => $p(
                'Le propriétaire partait à la retraite sans acheteur. La formule coopérative est venue d’un conseiller municipal qui l’avait vue fonctionner deux villages plus loin.',
                'Cent quatre-vingts parts à deux cents dollars : de quoi couvrir l’inventaire et deux mois de fonds de roulement, pas de quoi rénover.',
                'Le magasin rouvre en septembre avec les mêmes heures et un employé de moins. Ce qui change, c’est que la décision de fermer, si elle vient, se prendra en assemblée.'
            ),
        ],

        /* ------------------------------------------------------- politique --- */
        [
            'title' => 'L’opposition réclame une commission sur les contrats informatiques',
            'slug' => 'bb-commission-sur-les-contrats-informatiques',
            'desk' => 'politique', 'byline' => 'Jean-Philippe Côté', 'dateline' => 'Québec',
            'lede' => 'Quatre systèmes, quatre dépassements, et un total qui a triplé depuis les appels d’offres.',
            'views' => 1620, 'published' => $ilya('-1 day 8 hours'),
            'tags' => 'informatique, contrats, assemblée nationale',
            'body' => $p(
                'Les quatre projets ont en commun d’avoir été estimés avant que le devis soit écrit, et d’avoir changé de devis en cours de route.',
                'Le gouvernement plaide que le mode d’attribution a changé en 2024 et que les contrats visés sont antérieurs. C’est exact pour trois d’entre eux.',
                'Le quatrième a été attribué en janvier dernier, et c’est celui-là que l’opposition ramène chaque fois qu’on lui oppose la réforme.'
            ),
        ],

        /* --------------------------------------------------------- régions --- */
        [
            'title' => 'Sept municipalités de l’Abitibi partagent un même urbaniste',
            'slug' => 'bb-sept-municipalites-partagent-un-urbaniste',
            'desk' => 'regions', 'byline' => 'Noémie Lapointe', 'dateline' => 'Abitibi-Ouest',
            'lede' => 'Aucune n’avait les moyens d’en embaucher un. Ensemble, elles paient un salaire et demi.',
            'views' => 690, 'published' => $ilya('-3 days 5 hours'),
            'tags' => 'municipalités, urbanisme, abitibi',
            'body' => $p(
                'L’entente tient sur quatre pages et répartit les jours au prorata de la population. La plus petite des sept a droit à onze jours par année.',
                'Onze jours, c’est peu. C’est aussi onze de plus qu’avant, quand les demandes de permis complexes attendaient qu’un consultant de Rouyn ait une disponibilité.',
                'Trois autres municipalités ont demandé à se joindre. La réponse dépend de la réforme de l’aménagement déposée cette semaine, qui pourrait rendre le poste obligatoire — et le partage impossible.'
            ),
        ],

        /* --------------------------------------------------------- culture --- */
        [
            'title' => 'Le disque québécois vend moins et remplit plus de salles',
            'slug' => 'bb-le-disque-vend-moins-et-remplit-plus-de-salles',
            'desk' => 'culture-qc', 'byline' => 'Alexis Boivin', 'dateline' => '',
            'lede' => 'Les ventes ont fondu de moitié en huit ans. La billetterie des salles de moins de mille places a doublé.',
            'views' => 1290, 'published' => $ilya('-2 days 10 hours'),
            'tags' => 'musique, spectacles, industrie',
            'body' => $p(
                'Les deux courbes sont connues séparément. Mises côte à côte, elles disent qu’un disque n’est plus un produit mais une raison de partir en tournée.',
                'Les maisons de disques le savent et signent en conséquence : le contrat type inclut maintenant une part du spectacle, ce qui n’existait à peu près pas en 2016.',
                'Pour l’artiste, cela veut dire jouer davantage. Pour la salle de trois cents places en région, cela veut dire recevoir des noms qui, il y a dix ans, ne s’arrêtaient pas.'
            ),
        ],

        /* --------------------------------------------------------- société --- */
        [
            'title' => 'Les urgences de nuit de deux hôpitaux régionaux ferment jusqu’en octobre',
            'slug' => 'bb-urgences-de-nuit-fermees-jusqu-en-octobre',
            'desk' => 'societe', 'byline' => 'Marie-Soleil Gagnon', 'dateline' => 'Gaspé',
            'lede' => 'Le corridor de service renvoie les patients à soixante-dix kilomètres. Les ambulanciers disent que le calcul ne tient pas la route.',
            'views' => 3080, 'published' => $ilya('-16 hours'),
            'tags' => 'santé, urgences, gaspésie',
            'body' => $p(
                'De minuit à huit heures, les deux urgences sont fermées et les patients dirigés vers l’hôpital voisin, à soixante-dix kilomètres par une route qui en prend cinquante minutes l’été.',
                'L’hiver, la même route en prend quatre-vingt-dix, et c’est le chiffre que les ambulanciers mettent sur la table.',
                'L’établissement répond que la fermeture vaut jusqu’en octobre et qu’il manque quatre médecins pour couvrir les nuits. Il en manquait trois l’an dernier, à la même annonce.'
            ),
        ],

        /* ---------------------------------------------------------- le fil --- */
        [
            'title' => 'Le fil : le déraillement de Drummondville, minute par minute',
            'slug' => 'bb-le-fil-le-deraillement-de-drummondville',
            'desk' => 'le-fil', 'byline' => 'La rédaction', 'dateline' => 'Drummondville',
            'lede' => 'Les relevages, la réouverture de la route et le point de presse du Bureau de la sécurité des transports, en direct.',
            'views' => 7200, 'published' => $ilya('-1 hour'),
            'tags' => 'direct, transport',
            'body' => $p(
                'Le fil est ouvert depuis six heures quarante. Il ferme quand la route rouvre.',
                'Les entrées les plus récentes apparaissent en premier ; les heures sont celles de l’Est.'
            ),
        ],
    ],
];
