<?php

declare(strict_types=1);

/**
 * Contenu du guide d'utilisation du back-office (`/cmsadmin/guide`).
 *
 * Écrit en français comme les libellés de `cmsadmin-menu.php` : c'est du contenu éditorial, pas de
 * l'interface, et la langue du back-office suit `sites.default_locale` (fr pour la Côte d'Ivoire).
 * Seule l'ossature de la page passe par `lang/*.php` (clés `guide.*`).
 *
 * - "roles"  : rôles qui voient la section (super_admin, country_admin, agency).
 * - "blocks" : text | list | steps | table | note — rendus par pages/guide/index.php.
 *
 * Toute évolution du back-office qui change une règle décrite ici doit mettre ce fichier à jour :
 * un guide faux coûte plus cher que pas de guide.
 */

$allRoles = ['super_admin', 'country_admin', 'agency'];
$staff = ['super_admin', 'country_admin'];
$admin = ['super_admin'];

return [
    // ---------------------------------------------------------------- Prise en main
    [
        'key' => 'principes',
        'title' => 'Principes à connaître',
        'icon' => 'mdi-lightbulb-on-outline',
        'roles' => $allRoles,
        'intro' => 'Trois règles structurent toute la plateforme. Les comprendre évite la plupart des erreurs de manipulation.',
        'blocks' => [
            [
                'type' => 'note',
                'tone' => 'primary',
                'title' => 'Weblogy est l’intermédiaire exclusif',
                'html' => '<p>Aucune page publique n’affiche l’identité ni les coordonnées d’un partenaire, d’un agent ou d’un propriétaire. Les visiteurs voient les coordonnées d’<strong>Abidjan.net Immobilier</strong>, et toutes les demandes arrivent à l’équipe Weblogy, qui met ensuite en relation.</p><p class="mb-0">Conséquence pratique : les champs « contact » d’une annonce ne sont pas publiés. Ce sont les coordonnées <em>internes</em> que Weblogy appelle lorsqu’un prospect se manifeste.</p>',
            ],
            [
                'type' => 'list',
                'title' => 'Rien n’est publié sans contrôle',
                'items' => [
                    'Une annonce déposée par un partenaire part en <strong>attente de validation</strong>. Elle n’est visible du public qu’après approbation par l’équipe.',
                    'Modifier une annonce <strong>déjà en ligne</strong> ne la retire pas du site : la modification devient une <strong>révision</strong>, et la version publiée reste visible jusqu’à la validation.',
                    'Un refus exige toujours un <strong>motif</strong>, qui est transmis au partenaire pour qu’il corrige.',
                ],
            ],
            [
                'type' => 'table',
                'title' => 'Deux façons d’arriver sur le site',
                'head' => ['Origine du bien', 'Qui saisit l’annonce', 'Mise en ligne'],
                'rows' => [
                    ['<strong>Partenaire professionnel</strong> (agence, promoteur, gestionnaire)', 'Le partenaire lui-même, depuis son accès au back-office', 'Après validation par Weblogy'],
                    ['<strong>Particulier</strong> qui confie son bien', 'L’équipe Weblogy, à partir du dossier déposé', 'Après validation par Weblogy'],
                ],
            ],
            [
                'type' => 'list',
                'title' => 'Chacun ne voit que son périmètre',
                'items' => [
                    '<strong>Partenaire</strong> : uniquement ses propres annonces et son profil.',
                    '<strong>Admin Pays</strong> : tout ce qui concerne son pays.',
                    '<strong>Super Admin</strong> : tous les pays, plus les réglages de la plateforme.',
                    'Une adresse forgée ne contourne rien : l’accès est vérifié à chaque requête, côté serveur.',
                ],
            ],
        ],
    ],

    [
        'key' => 'compte',
        'title' => 'Votre compte et votre sécurité',
        'icon' => 'mdi-shield-account-outline',
        'roles' => $allRoles,
        'blocks' => [
            [
                'type' => 'list',
                'items' => [
                    'Un compte se crée <strong>par invitation</strong> : le destinataire reçoit un lien valable <strong>72 heures</strong> pour choisir son mot de passe. Passé ce délai, demandez un nouvel envoi (« Renvoyer l’invitation »).',
                    'Le mot de passe fait <strong>12 caractères minimum</strong>. Un mot de passe provisoire doit être changé dès la première connexion.',
                    'Après <strong>6 tentatives</strong> infructueuses, la connexion est bloquée quelques minutes. Ce n’est pas une panne : patientez, ou utilisez « Mot de passe oublié ».',
                    'Changer son mot de passe <strong>déconnecte toutes les autres sessions</strong> et révoque les « Rester connecté ». C’est le bon réflexe si vous soupçonnez qu’un poste est resté ouvert.',
                    'La <strong>cloche</strong> de la barre supérieure regroupe les notifications : annonce à valider, annonce publiée ou rejetée, nouveau dossier, profil modifié.',
                ],
            ],
            [
                'type' => 'note',
                'tone' => 'warning',
                'title' => 'Poste partagé',
                'html' => '<p class="mb-0">N’utilisez pas « Rester connecté » sur un ordinateur partagé, et déconnectez-vous par le menu de votre nom plutôt qu’en fermant l’onglet.</p>',
            ],
        ],
    ],

    // ---------------------------------------------------------------- Annonces
    [
        'key' => 'annonces',
        'title' => 'Annonces',
        'icon' => 'mdi-home-city-outline',
        'roles' => $allRoles,
        'intro' => 'Le cœur du site. Une annonce se compose d’une catégorie, de critères qui dépendent de cette catégorie, de photos, d’un prix et d’une localisation.',
        'blocks' => [
            [
                'type' => 'table',
                'title' => 'Ce que vous faites vous-même, ce qui revient à Weblogy',
                'roles' => ['agency'],
                'head' => ['Action', 'Vous', 'Weblogy'],
                'rows' => [
                    ['Créer et modifier vos annonces', 'Oui', '—'],
                    ['Enregistrer un brouillon avant de finaliser', 'Oui', '—'],
                    ['Désactiver, puis réactiver ce que vous avez désactivé', 'Oui', '—'],
                    ['Prolonger, archiver (vendu ou loué)', 'Oui', '—'],
                    ['<strong>Mettre en ligne</strong>', 'Non', 'Valide chaque annonce avant publication'],
                    ['<strong>Réactiver une annonce dépubliée par Weblogy</strong>', 'Non', 'Elle repasse par la validation'],
                    ['<strong>Mettre un bien à la une</strong>', 'Non', 'Choix éditorial de l’équipe'],
                    ['<strong>Traiter les demandes des prospects</strong>', 'Non', 'Vous êtes contacté quand une demande concerne un de vos biens'],
                ],
            ],
            [
                'type' => 'note',
                'tone' => 'primary',
                'title' => 'Vous déposez vos annonces vous-même',
                'roles' => ['agency'],
                'html' => '<p class="mb-0">Votre compte donne accès au dépôt et à la gestion de vos biens : vous n’avez pas à les transmettre à Weblogy par email ou par téléphone. Weblogy intervient à la validation, puis comme intermédiaire auprès des prospects.</p>',
            ],
            [
                'type' => 'steps',
                'title' => 'Déposer une annonce',
                'items' => [
                    'Menu <strong>Annonces → Ajouter une annonce</strong>.',
                    'Choisissez la <strong>catégorie</strong> en premier : c’est elle qui détermine les critères proposés ensuite (un appartement et un terrain n’ont pas les mêmes). Changer de catégorie recharge ces critères.',
                    'Renseignez la <strong>transaction</strong> (vente, location…), le <strong>prix</strong> et, pour une location, la période (par mois, par nuit…).',
                    'Indiquez la <strong>localisation</strong> : ville, commune, quartier. L’adresse précise n’est publiée que si vous cochez « afficher l’adresse exacte » ; sinon la carte publique affiche un cercle approximatif, jamais un point.',
                    'Ajoutez les <strong>photos</strong> (au moins une). Elles sont converties automatiquement en trois tailles, la première sert de vignette. Glissez-les pour changer l’ordre.',
                    'Remplissez la <strong>situation juridique</strong> (titre foncier, ACD, ADU, lettre d’attribution, coutumier, litige) : c’est déterminant pour un acheteur ivoirien.',
                    'Les <strong>informations confidentielles</strong> (notaire, référence de dossier, propriétaire) ne sont jamais affichées en public : elles restent réservées à l’équipe.',
                    'Enregistrez. Un partenaire peut aussi <strong>enregistrer en brouillon</strong> pour compléter plus tard.',
                ],
            ],
            [
                'type' => 'table',
                'title' => 'Les statuts, et ce qu’ils veulent dire',
                'head' => ['Statut', 'Visible du public', 'Signification'],
                'rows' => [
                    ['Brouillon', 'Non', 'Travail en cours du partenaire. N’est pas dans la file de validation ; les champs obligatoires ne sont vérifiés qu’à l’envoi.'],
                    ['En attente', 'Non', 'Déposée, en attente de décision de l’équipe.'],
                    ['Publiée', 'Oui', 'En ligne sur le site.'],
                    ['Rejetée', 'Non', 'Refusée avec un motif. Le partenaire corrige puis renvoie.'],
                    ['Dépubliée', 'Non', 'Retirée du site. Par l’équipe (le partenaire ne peut pas la remettre) ou par le partenaire lui-même (il peut la réactiver).'],
                    ['Archivée', 'Non', 'Bien vendu ou loué. Conservée pour les statistiques.'],
                    ['Expirée', 'Non', 'Durée de vie dépassée (90 jours par défaut). Une relance est envoyée avant l’échéance.'],
                ],
            ],
            [
                'type' => 'list',
                'title' => 'Modifier une annonce déjà en ligne',
                'roles' => $allRoles,
                'items' => [
                    'La version publiée <strong>reste visible</strong> pendant l’examen : le bien ne disparaît pas du site.',
                    'Les nouvelles photos proposées ne sont pas publiques tant que la révision n’est pas acceptée.',
                    'Si l’équipe modifie l’annonce entre-temps, la révision devient caduque et doit être refaite.',
                ],
            ],
            [
                'type' => 'list',
                'title' => 'Prolonger, archiver, mettre en avant',
                'items' => [
                    '<strong>Prolonger</strong> repousse la date d’expiration sans repasser par la validation.',
                    '<strong>Archiver</strong> sert quand le bien est vendu ou loué — préférez-le à la suppression, les statistiques sont conservées.',
                    '<strong>Mettre en avant</strong> (« à la une » sur l’accueil) est réservé à l’équipe et aux annonces publiées.',
                    'La <strong>suppression</strong> n’efface rien définitivement : l’annonce est retirée mais reste en base. Un partenaire ne peut supprimer qu’une annonce jamais publiée.',
                ],
            ],
            [
                'type' => 'note',
                'tone' => 'info',
                'title' => 'Validation et refus',
                'roles' => $staff,
                'html' => '<p>Depuis <strong>Annonces → À valider</strong>, « Approuver » met l’annonce en ligne immédiatement. « Rejeter » <strong>exige un motif</strong> : il est envoyé au partenaire et s’affiche dans son espace.</p><p class="mb-0">Une annonce <strong>déjà publiée ne se rejette pas</strong> — c’est « Dépublier » qu’il faut employer. Le rejet ne s’applique qu’à une annonce en attente.</p>',
            ],
        ],
    ],

    // ---------------------------------------------------------------- Relation client (équipe)
    [
        'key' => 'contacts',
        'title' => 'Demandes de contact',
        'icon' => 'mdi-email-outline',
        'roles' => $staff,
        'intro' => 'Toutes les demandes des visiteurs arrivent ici : formulaire d’une annonce, page Contact, « Confiez-nous votre bien ». Le partenaire ne les voit pas — il ne connaît que leur nombre.',
        'blocks' => [
            [
                'type' => 'steps',
                'title' => 'Traiter une demande',
                'items' => [
                    'Ouvrez la demande : coordonnées du prospect, message, annonce concernée et page d’origine.',
                    'Le panneau de suivi donne le <strong>contact interne</strong> à solliciter : celui saisi sur l’annonce, et les coordonnées du partenaire.',
                    'Faites évoluer le statut : <strong>Nouvelle → Lue → En cours → Close</strong>, ou <strong>Indésirable</strong> pour un message publicitaire.',
                    'Affectez la demande à un membre de l’équipe pour éviter les doublons de traitement.',
                ],
            ],
            [
                'type' => 'note',
                'tone' => 'warning',
                'html' => '<p class="mb-0">La pastille du menu compte les demandes <strong>nouvelles</strong>. Elle ne retombe que lorsque les demandes changent de statut : c’est votre file d’attente, pas un simple indicateur.</p>',
            ],
        ],
    ],

    [
        'key' => 'biens-confies',
        'title' => 'Biens confiés par des particuliers',
        'icon' => 'mdi-key-outline',
        'roles' => $staff,
        'intro' => 'Un particulier qui confie son bien dépose un dossier depuis son espace sur le site public. L’équipe l’étudie, puis en fait une annonce.',
        'blocks' => [
            [
                'type' => 'steps',
                'title' => 'Ce que le propriétaire fait avant que le dossier vous parvienne',
                'items' => [
                    'Il ouvre un compte depuis « <strong>Confiez-nous votre bien</strong> » sur le site public. C’est le seul compte accessible aux particuliers : il n’a <strong>aucun accès au back-office</strong> et ne peut rien publier.',
                    'Il <strong>confirme son adresse email</strong> (lien valable 48 h). Tant que ce n’est pas fait, le formulaire du bien lui reste fermé.',
                    'Il décrit son bien et joint ses <strong>photos (1 à 15)</strong> et ses <strong>documents (jusqu’à 5)</strong>, titre de propriété compris.',
                    'Il reçoit un accusé de réception ; vous recevez une notification et le dossier apparaît ci-dessous.',
                    'Depuis son espace <strong>/mon-espace</strong>, il suit l’avancement de son dossier — et, une fois le bien en ligne, y trouve le lien de l’annonce.',
                ],
            ],
            [
                'type' => 'steps',
                'title' => 'Ce que vous faites ensuite',
                'items' => [
                    'Ouvrez le dossier : description du bien, photos et documents (titre de propriété…), coordonnées du propriétaire.',
                    'Passez-le <strong>« en cours d’étude »</strong> — le propriétaire en est informé par email.',
                    'Pour <strong>refuser</strong>, un motif est obligatoire ; il est envoyé au propriétaire.',
                    '« <strong>Créer l’annonce</strong> » génère une annonce en <strong>brouillon</strong>, pré-remplie : photos reprises, propriétaire enregistré en informations confidentielles, titre de propriété repris.',
                    'Complétez l’annonce puis publiez-la. Le dossier passe alors « publié » et le propriétaire reçoit le lien de son bien en ligne.',
                ],
            ],
            [
                'type' => 'note',
                'tone' => 'info',
                'html' => '<p class="mb-0">Les pièces jointes d’un dossier sont stockées <strong>hors du site public</strong> : elles ne sont accessibles qu’en étant connecté et habilité. Ne les recopiez jamais dans les photos d’une annonce.</p>',
            ],
        ],
    ],

    [
        'key' => 'partenaires',
        'title' => 'Partenaires et dossiers de partenariat',
        'icon' => 'mdi-office-building-outline',
        'roles' => $staff,
        'blocks' => [
            [
                'type' => 'steps',
                'title' => 'Ouvrir un compte partenaire',
                'items' => [
                    'Un professionnel dépose un dossier depuis <strong>« Devenir partenaire »</strong> : identité légale, coordonnées, responsable, RCCM et pièce d’identité.',
                    'Examinez le dossier dans <strong>Partenaires → Dossiers de partenariat</strong>. Les pièces justificatives se consultent depuis la fiche, jamais par une adresse publique.',
                    'Pour accepter, utilisez « <strong>Créer le partenaire depuis ce dossier</strong> » : la fiche est pré-remplie avec l’identité légale déjà vérifiée.',
                    'Créez ensuite le compte du responsable : il reçoit une <strong>invitation</strong> et choisit lui-même son mot de passe.',
                ],
            ],
            [
                'type' => 'list',
                'title' => 'Au quotidien',
                'items' => [
                    'Le <strong>nom, le RCCM, le statut et le badge « vérifié »</strong> restent modifiables par l’équipe seule : le partenaire ne peut pas se déclarer vérifié.',
                    'Suspendre ou fermer un partenaire retire l’accès à <strong>tous ses comptes</strong> dès la requête suivante.',
                    'Un partenaire qui a des annonces ne peut pas être supprimé : suspendez-le, ou traitez d’abord ses annonces.',
                ],
            ],
        ],
    ],

    [
        'key' => 'profil-partenaire',
        'title' => 'Votre profil partenaire',
        'icon' => 'mdi-card-account-details-outline',
        'roles' => ['agency'],
        'blocks' => [
            [
                'type' => 'list',
                'items' => [
                    'Le <strong>responsable</strong> du compte modifie la présentation, le logo, les coordonnées et les zones d’intervention. Un <strong>agent</strong> consulte ces informations sans pouvoir les enregistrer.',
                    'Le <strong>nom, la forme juridique, le RCCM et le badge « vérifié »</strong> sont gérés par Weblogy : signalez-nous toute correction.',
                    'Vos coordonnées ne sont <strong>pas publiées sur le site</strong> : elles servent à l’équipe Weblogy pour vous joindre quand un prospect s’intéresse à l’un de vos biens.',
                    'Un profil complet (présentation, logo, zones) facilite le traitement de vos annonces.',
                ],
            ],
        ],
    ],

    // ---------------------------------------------------------------- Contenu et SEO
    [
        'key' => 'contenu',
        'title' => 'Pages, actualités et bannières',
        'icon' => 'mdi-text-box-outline',
        'roles' => $admin,
        'blocks' => [
            [
                'type' => 'list',
                'title' => 'Pages',
                'items' => [
                    'Une page <strong>non publiée répond 404</strong> et son lien disparaît du pied de page : le site ne sert jamais de page vide.',
                    'Les pages <strong>système</strong> (mentions légales, CGU, confidentialité, cookies, FAQ, À propos, Comment ça marche) ne se suppriment pas — elles se dépublient.',
                    'Changer l’adresse (le « slug ») d’une page change son URL publique : créez une <strong>redirection</strong> depuis l’ancienne, sinon les liens existants tombent en 404.',
                ],
            ],
            [
                'type' => 'note',
                'tone' => 'info',
                'title' => 'La FAQ a un format particulier',
                'html' => '<p class="mb-0">Dans la page FAQ, un <strong>titre de niveau 2</strong> ouvre un thème, un <strong>titre de niveau 3</strong> pose une question, et le texte qui suit est la réponse. L’accordéon du site public et les données structurées Google sont générés à partir de cette structure. Sans titres de niveau 3, la page s’affiche comme une page ordinaire.</p>',
            ],
            [
                'type' => 'list',
                'title' => 'Actualités et bannières',
                'items' => [
                    'Un article n’est public que s’il est <strong>publié</strong> <em>et</em> que sa date de publication est passée : une date future prépare une parution.',
                    'Les <strong>bannières</strong> de placement « accueil » alimentent le diaporama de la page d’accueil. À défaut, des photos provisoires sont utilisées.',
                    'Soignez le format des images : elles sont converties automatiquement, mais une photo nette et large donne un bien meilleur résultat qu’une capture d’écran.',
                ],
            ],
        ],
    ],

    [
        'key' => 'referencement',
        'title' => 'Référencement',
        'icon' => 'mdi-chart-timeline-variant',
        'roles' => $admin,
        'blocks' => [
            [
                'type' => 'list',
                'items' => [
                    '<strong>Balises méta</strong> : surcharge du titre, de la description, de l’image de partage et du texte d’introduction, adresse par adresse. Un champ laissé vide conserve ce que le site calcule tout seul — c’est le bon réflexe par défaut.',
                    'Cocher <strong>« ne pas indexer »</strong> retire aussi la page du <em>sitemap</em> : les deux ne se contredisent jamais.',
                    '<strong>Redirections</strong> : indispensables après un changement d’adresse. Trois interdits, refusés à la saisie — rediriger une adresse déjà redirigée, rediriger une page vers elle-même, et enchaîner deux redirections.',
                    'Le <em>sitemap</em> et le fichier <em>robots</em> sont produits automatiquement : rien à téléverser.',
                ],
            ],
        ],
    ],

    // ---------------------------------------------------------------- Référentiels
    [
        'key' => 'referentiels',
        'title' => 'Référentiels : géographie, catégories, critères',
        'icon' => 'mdi-map-marker-radius-outline',
        'roles' => $staff,
        'intro' => 'Ce sont les listes qui alimentent les formulaires et les filtres du site. Elles se modifient rarement, mais une erreur ici se voit partout.',
        'blocks' => [
            [
                'type' => 'list',
                'title' => 'Règle générale',
                'items' => [
                    'Un élément <strong>utilisé</strong> par des annonces ne se supprime pas : il se <strong>désactive</strong>. Il disparaît des formulaires sans casser l’existant.',
                    'Les <strong>codes techniques</strong> sont figés à la création : ils sont employés par le code du site.',
                    'Le <strong>slug</strong> reste modifiable, avec prudence : il apparaît dans les adresses publiques.',
                ],
            ],
            [
                'type' => 'list',
                'title' => 'Géographie',
                'items' => [
                    'Villes, communes et quartiers alimentent la recherche et les adresses des annonces.',
                    'Un Admin Pays travaille sur son pays ; le Super Admin choisit le pays, qui reste mémorisé pendant sa session.',
                ],
            ],
            [
                'type' => 'list',
                'title' => 'Catégories et critères',
                'roles' => $admin,
                'items' => [
                    'Les <strong>catégories</strong> forment une arborescence : une famille (Résidentiel…) et ses sous-catégories (Appartement, Villa…).',
                    'Les <strong>transactions</strong> se déclarent sur la famille ; une sous-catégorie sans transaction hérite de celles de sa famille.',
                    'Les <strong>critères dynamiques</strong> évitent de coder un champ par type de bien. Le type de saisie d’un critère est <strong>verrouillé dès qu’une valeur existe</strong> : créez-en un nouveau plutôt que de forcer.',
                    'Les critères marqués <strong>« public »</strong> apparaissent sur la fiche du bien ; les autres restent internes.',
                ],
            ],
        ],
    ],

    // ---------------------------------------------------------------- Administration
    [
        'key' => 'sites',
        'title' => 'Pays & sites',
        'icon' => 'mdi-earth',
        'roles' => $admin,
        'blocks' => [
            [
                'type' => 'list',
                'items' => [
                    'C’est ici que vivent les <strong>coordonnées publiques</strong> du site : téléphone, WhatsApp, email, adresse, position sur la carte, réseaux sociaux. Elles alimentent le pied de page, la page Contact et le bloc « Votre interlocuteur » des annonces.',
                    'Une coordonnée <strong>laissée vide n’est pas affichée</strong> : jamais de lien mort ni de champ vide sur le site.',
                    'La carte de la page Contact n’apparaît que si la <strong>latitude et la longitude</strong> sont renseignées.',
                    'L’<strong>identifiant de mesure d’audience</strong> (Google) se saisit ici. Il n’est chargé qu’en production et <strong>seulement après acceptation</strong> des cookies par le visiteur.',
                    'On ne peut ni désactiver le site sur lequel on est connecté, ni supprimer le domaine en cours d’utilisation.',
                ],
            ],
            [
                'type' => 'note',
                'tone' => 'warning',
                'html' => '<p class="mb-0">Après une modification ici, le site public peut mettre <strong>jusqu’à dix minutes</strong> à la refléter : ces données sont mises en cache. Ce n’est pas une erreur de votre part.</p>',
            ],
        ],
    ],

    [
        'key' => 'utilisateurs',
        'title' => 'Utilisateurs internes',
        'icon' => 'mdi-account-multiple-outline',
        'roles' => $admin,
        'blocks' => [
            [
                'type' => 'list',
                'items' => [
                    'Créez les comptes <strong>Super Admin</strong> et <strong>Admin Pays</strong>. Les comptes de partenaires se créent depuis la fiche du partenaire.',
                    'Un <strong>Admin Pays</strong> ne peut se connecter que sur le site de son pays.',
                    'Garde-fous : on ne modifie pas son propre rôle, on ne se désactive pas soi-même, et il doit toujours rester un Super Admin actif.',
                    'Désactiver un compte ou changer son email <strong>révoque immédiatement</strong> ses sessions.',
                    'Une suppression libère l’adresse email pour un futur compte.',
                ],
            ],
        ],
    ],

    [
        'key' => 'journal',
        'title' => 'Journal d’activité',
        'icon' => 'mdi-history',
        'roles' => $staff,
        'blocks' => [
            [
                'type' => 'list',
                'items' => [
                    'Toutes les écritures du back-office y sont tracées : qui a validé, modifié, supprimé quoi, et quand.',
                    '« <strong>n champs modifiés</strong> » se déplie et montre les valeurs <strong>avant et après</strong>. C’est l’outil pour comprendre une modification contestée.',
                    'Écran en <strong>lecture seule</strong> : une entrée ne se modifie ni ne se supprime.',
                    'Un Admin Pays voit son pays ; le Super Admin voit tous les pays et les actions automatiques du système.',
                ],
            ],
        ],
    ],

    [
        'key' => 'exports',
        'title' => 'Exports',
        'icon' => 'mdi-tray-arrow-down',
        'roles' => $staff,
        'blocks' => [
            [
                'type' => 'list',
                'items' => [
                    'Exports des <strong>annonces</strong> et des <strong>demandes de contact</strong>, filtrables par période et par statut.',
                    'Le fichier s’ouvre directement dans Excel : accents corrects, séparateur point-virgule, statuts en clair.',
                    'Les <strong>informations confidentielles</strong> ne sont jamais exportées, et l’adresse d’un bien ne l’est que si elle est publique.',
                    'Chaque export est inscrit au journal d’activité.',
                ],
            ],
        ],
    ],

    [
        'key' => 'parametres',
        'title' => 'Paramètres',
        'icon' => 'mdi-tune-variant',
        'roles' => $admin,
        'blocks' => [
            [
                'type' => 'list',
                'items' => [
                    '<strong>Commission</strong> : mode, assiette, taux et plancher. Tant que le champ est vide, la valeur reste « à définir » et le site utilise sa valeur par défaut.',
                    '<strong>Durée de vie d’une annonce</strong> (90 jours par défaut) et délai de relance avant expiration.',
                    '<strong>Publication directe</strong> : par rôle. Activée pour les partenaires, leurs annonces paraissent sans validation — à n’activer qu’en confiance, un rejet ou une dépublication ramènent toujours à la validation.',
                    '<strong>Sécurité</strong> : nombre de tentatives de connexion avant blocage, et durée du blocage.',
                    'Les coordonnées publiques ne sont pas ici mais dans <strong>Pays &amp; sites</strong> : une seule source de vérité.',
                ],
            ],
        ],
    ],

    // ---------------------------------------------------------------- Dépannage
    [
        'key' => 'depannage',
        'title' => 'Questions fréquentes',
        'icon' => 'mdi-help-circle-outline',
        'roles' => $allRoles,
        'blocks' => [
            [
                'type' => 'table',
                'head' => ['Situation', 'Explication'],
                'rows' => [
                    ['Mon annonce n’apparaît pas sur le site', 'Vérifiez son statut : seule une annonce <strong>publiée</strong> est visible. Une annonce en attente, rejetée, expirée ou archivée ne l’est pas.'],
                    ['J’ai modifié une annonce en ligne, rien n’a changé', 'C’est le fonctionnement attendu : votre modification est une <strong>révision</strong> en attente de validation. La version publiée reste visible entre-temps.'],
                    ['Je ne peux pas rejeter une annonce', 'Le rejet ne s’applique qu’à une annonce <strong>en attente</strong>. Pour une annonce publiée, utilisez « Dépublier ».'],
                    ['Un changement fait dans « Pays &amp; sites » ne se voit pas', 'Ces données sont en cache : comptez jusqu’à dix minutes.'],
                    ['Je n’arrive plus à me connecter', 'Après 6 essais, l’accès est bloqué quelques minutes. Attendez, ou passez par « Mot de passe oublié ».'],
                    ['Le bandeau « Aperçu » s’affiche en bas des pages', 'Le site contient encore les <strong>données de démonstration</strong> et n’est pas indexé par Google. Elles seront supprimées à l’ouverture.'],
                    ['Une photo est refusée', 'Formats acceptés : JPEG, PNG et WebP. Une image trop lourde ou un fichier qui n’est pas vraiment une image est rejeté.'],
                ],
            ],
            [
                'type' => 'note',
                'tone' => 'primary',
                'title' => 'Une question que ce guide ne couvre pas ?',
                'html' => '<p class="mb-0">Contactez l’équipe Weblogy. Si le problème concerne une action précise, indiquez la date et l’heure : le <strong>journal d’activité</strong> permet de retrouver exactement ce qui s’est passé.</p>',
            ],
        ],
    ],
];
