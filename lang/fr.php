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

    'errors' => [
        'eyebrow' => 'Erreur :code',
        'back_dashboard' => 'Retour au tableau de bord',
        'reload' => 'Recharger la page',
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
