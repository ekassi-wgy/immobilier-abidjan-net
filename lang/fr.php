<?php

declare(strict_types=1);

/**
 * Chaînes d'interface — français (langue de référence et de repli).
 * Toute nouvelle clé est ajoutée ici ET dans les autres langues (lang/en.php…).
 */

return [
    'common' => [
        'skip_to_content' => 'Aller au contenu',
        'back_home' => 'Retour à l’accueil',
        'price_on_request' => 'Prix sur demande',
        'price_period' => [
            'month' => '/ mois',
            'week' => '/ semaine',
            'night' => '/ nuit',
            'year' => '/ an',
        ],
    ],

    'auth' => [
        'aside' => [
            'eyebrow' => 'Espace de gestion',
            'headline' => 'Chaque annonce publiée est une annonce vérifiée.',
            'fact_1' => 'Validation de chaque annonce avant mise en ligne',
            'fact_2' => 'Agences partenaires sélectionnées',
            'fact_3' => 'Côte d’Ivoire · extension panafricaine',
        ],
        'roles' => [
            'super_admin' => 'Super administrateur',
            'country_admin' => 'Administrateur pays',
            'agency_owner' => 'Responsable d’agence',
            'agency_agent' => 'Agent d’agence',
        ],
        'email' => 'Adresse email',
        'password_label' => 'Mot de passe',
        'show_password' => 'Afficher le mot de passe',
        'back_to_login' => 'Retour à la connexion',
        'login' => [
            'title' => 'Connexion',
            'subtitle' => 'Accédez à votre espace de gestion des annonces.',
            'forgot' => 'Mot de passe oublié ?',
            'remember' => 'Rester connecté sur cet appareil',
            'submit' => 'Se connecter',
            'partner' => 'Agence immobilière ?',
            'partner_link' => 'Devenir partenaire',
            'invalid' => 'Adresse email ou mot de passe incorrect.',
            'required' => 'Saisissez votre adresse email et votre mot de passe.',
            'locked' => 'Trop de tentatives. Réessayez dans :minutes minutes ou réinitialisez votre mot de passe.',
            'wrong_site' => 'Ce compte n’a pas accès au site de ce pays.',
            'expired' => 'Votre session a expiré après une période d’inactivité. Reconnectez-vous.',
            'logged_out' => 'Vous êtes déconnecté.',
        ],
        'logout' => 'Se déconnecter',
        'forgot' => [
            'title' => 'Mot de passe oublié',
            'subtitle' => 'Indiquez l’adresse email de votre compte : nous vous enverrons un lien pour choisir un nouveau mot de passe.',
            'submit' => 'Recevoir le lien',
            'sent' => 'Si un compte correspond à cette adresse, un email vient d’être envoyé. Le lien est valable :minutes minutes.',
            'too_many' => 'Trop de demandes depuis votre connexion. Réessayez dans quelques minutes.',
            'invalid_email' => 'Saisissez une adresse email valide.',
        ],
        'reset' => [
            'title' => 'Nouveau mot de passe',
            'subtitle' => 'Choisissez le mot de passe de votre compte :email.',
            'submit' => 'Enregistrer le mot de passe',
            'invalid_title' => 'Lien expiré',
            'invalid_text' => 'Ce lien de réinitialisation n’est plus valable : il a déjà servi, a expiré ou a été remplacé par une demande plus récente.',
            'request_new' => 'Demander un nouveau lien',
            'done' => 'Votre mot de passe a été modifié. Connectez-vous avec le nouveau mot de passe.',
            'email_subject' => 'Réinitialisation de votre mot de passe · :site',
            'email_preheader' => 'Lien valable une heure, à usage unique.',
            'email_hello' => 'Bonjour :name,',
            'email_intro' => 'Une réinitialisation du mot de passe de votre compte :site a été demandée.',
            'email_button' => 'Choisir un nouveau mot de passe',
            'email_expiry' => 'Ce lien est valable :minutes minutes et ne peut servir qu’une fois.',
            'email_ignore' => 'Si vous n’êtes pas à l’origine de cette demande, ignorez ce message : votre mot de passe actuel reste valable.',
            'email_link_fallback' => 'Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :',
            'email_footer' => 'Message automatique envoyé par :site. Demande effectuée depuis l’adresse IP :ip.',
        ],
        'change' => [
            'title' => 'Choisissez votre mot de passe',
            'subtitle' => 'Votre compte utilise un mot de passe provisoire. Remplacez-le pour accéder au back-office.',
            'current' => 'Mot de passe actuel',
            'new' => 'Nouveau mot de passe',
            'confirm' => 'Confirmer le nouveau mot de passe',
            'hint' => 'Au moins :min caractères. Une phrase courte est plus sûre et plus facile à retenir qu’un mot compliqué.',
            'submit' => 'Enregistrer',
            'done' => 'Votre mot de passe a été modifié.',
            'current_invalid' => 'Le mot de passe actuel est incorrect.',
            'same_as_current' => 'Le nouveau mot de passe doit être différent de l’actuel.',
        ],
        'password' => [
            'too_short' => 'Le mot de passe doit contenir au moins :min caractères.',
            'too_long' => 'Le mot de passe ne peut pas dépasser 128 caractères.',
            'too_weak' => 'Ce mot de passe est trop facile à deviner. Choisissez-en un autre.',
            'mismatch' => 'Les deux mots de passe ne correspondent pas.',
        ],
        'account' => [
            'title' => 'Mon compte',
            'subtitle' => 'Vos informations de connexion.',
            'identity' => 'Identité',
            'name' => 'Nom',
            'role' => 'Rôle',
            'agency' => 'Agence',
            'last_login' => 'Dernière connexion',
            'never' => 'Première connexion',
            'security' => 'Mot de passe',
            'security_help' => 'Modifier votre mot de passe déconnecte vos autres appareils.',
            'profile_later' => 'La modification des coordonnées sera disponible avec la gestion des utilisateurs.',
        ],
        'dashboard' => 'Tableau de bord',
    ],

    'errors' => [
        'eyebrow' => 'Erreur :code',
        'back_dashboard' => 'Retour au tableau de bord',
        'reload' => 'Recharger la page',
        401 => [
            'title' => 'Connexion requise',
            'text' => 'Connectez-vous pour accéder à cette page.',
        ],
        403 => [
            'title' => 'Accès refusé',
            'text' => 'Vous n’avez pas les droits nécessaires pour consulter cette page.',
        ],
        404 => [
            'title' => 'Page introuvable',
            'text' => 'L’adresse demandée n’existe pas ou a été déplacée. L’annonce que vous cherchez a peut-être été vendue ou louée.',
        ],
        405 => [
            'title' => 'Action non autorisée',
            'text' => 'Cette adresse ne peut pas être utilisée de cette façon.',
        ],
        419 => [
            'title' => 'Session expirée',
            'text' => 'Le formulaire est resté ouvert trop longtemps. Rechargez la page, puis renvoyez-le.',
        ],
        429 => [
            'title' => 'Trop de tentatives',
            'text' => 'Merci de patienter quelques minutes avant de réessayer.',
        ],
        500 => [
            'title' => 'Une erreur est survenue',
            'text' => 'Le problème a été enregistré et sera corrigé. Réessayez dans un instant.',
        ],
        503 => [
            'title' => 'Site en préparation',
            'text' => 'immobilier.abidjan.net ouvre bientôt. Revenez dans quelques jours.',
        ],
    ],
];
