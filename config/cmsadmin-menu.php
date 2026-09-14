<?php

declare(strict_types=1);

/**
 * Menu latéral du back-office cmsadmin.
 *
 * - "roles"  : rôles autorisés à VOIR l'entrée (le contrôle d'accès réel reste fait côté serveur).
 * - "key"    : identifiant utilisé pour l'état actif ("properties.pending" active aussi "properties").
 * - "badge"  : clé d'un compteur fourni par le contrôleur (ex. annonces en attente).
 * - "label"  : peut être un tableau [rôle => libellé] quand le libellé dépend du rôle.
 *
 * Rôles : super_admin, country_admin, agency.
 */

$allRoles = ['super_admin', 'country_admin', 'agency'];
$staff = ['super_admin', 'country_admin'];

return [
    ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'mdi-view-grid-outline', 'url' => '', 'roles' => $allRoles],

    ['category' => 'Annonces', 'roles' => $allRoles],
    [
        'key' => 'properties',
        'label' => ['agency' => 'Mes annonces', 'default' => 'Annonces'],
        'icon' => 'mdi-home-city-outline',
        'roles' => $allRoles,
        'badge' => 'pending_properties',
        'children' => [
            ['key' => 'properties.all', 'label' => 'Toutes les annonces', 'url' => 'annonces', 'roles' => $allRoles],
            ['key' => 'properties.pending', 'label' => 'À valider', 'url' => 'annonces?statut=en-attente', 'roles' => $staff, 'badge' => 'pending_properties'],
            ['key' => 'properties.create', 'label' => 'Ajouter une annonce', 'url' => 'annonces/nouvelle', 'roles' => $allRoles],
        ],
    ],
    [
        'key' => 'catalog',
        'label' => 'Catégories & critères',
        'icon' => 'mdi-shape-outline',
        'roles' => ['super_admin'],
        'children' => [
            ['key' => 'catalog.categories', 'label' => 'Catégories', 'url' => 'categories', 'roles' => ['super_admin']],
            ['key' => 'catalog.attributes', 'label' => 'Critères dynamiques', 'url' => 'criteres', 'roles' => ['super_admin']],
            ['key' => 'catalog.features', 'label' => 'Équipements', 'url' => 'equipements', 'roles' => ['super_admin']],
        ],
    ],

    ['category' => 'Relation client', 'roles' => $allRoles],
    ['key' => 'leads', 'label' => 'Demandes de contact', 'icon' => 'mdi-email-outline', 'url' => 'contacts', 'roles' => $allRoles, 'badge' => 'new_leads'],
    [
        'key' => 'agencies',
        'label' => 'Agences partenaires',
        'icon' => 'mdi-office-building-outline',
        'roles' => $staff,
        'children' => [
            ['key' => 'agencies.all', 'label' => 'Toutes les agences', 'url' => 'agences', 'roles' => $staff],
            ['key' => 'agencies.requests', 'label' => 'Demandes de partenariat', 'url' => 'demandes-partenariat', 'roles' => $staff, 'badge' => 'partner_requests'],
        ],
    ],
    ['key' => 'agency_profile', 'label' => 'Profil de l\'agence', 'icon' => 'mdi-card-account-details-outline', 'url' => 'profil-agence', 'roles' => ['agency']],

    ['category' => 'Contenu & SEO', 'roles' => ['super_admin']],
    [
        'key' => 'content',
        'label' => 'Contenu',
        'icon' => 'mdi-text-box-outline',
        'roles' => ['super_admin'],
        'children' => [
            ['key' => 'content.pages', 'label' => 'Pages', 'url' => 'pages', 'roles' => ['super_admin']],
            ['key' => 'content.posts', 'label' => 'Actualités', 'url' => 'actualites', 'roles' => ['super_admin']],
            ['key' => 'content.banners', 'label' => 'Bannières', 'url' => 'bannieres', 'roles' => ['super_admin']],
        ],
    ],
    [
        'key' => 'seo',
        'label' => 'Référencement',
        'icon' => 'mdi-chart-timeline-variant',
        'roles' => ['super_admin'],
        'children' => [
            ['key' => 'seo.meta', 'label' => 'Balises méta', 'url' => 'seo', 'roles' => ['super_admin']],
            ['key' => 'seo.redirects', 'label' => 'Redirections', 'url' => 'seo/redirections', 'roles' => ['super_admin']],
        ],
    ],

    ['category' => 'Administration', 'roles' => $staff],
    [
        'key' => 'geo',
        'label' => 'Référentiel géographique',
        'icon' => 'mdi-map-marker-radius-outline',
        'roles' => $staff,
        'children' => [
            ['key' => 'geo.cities', 'label' => 'Villes', 'url' => 'geo/villes', 'roles' => $staff],
            ['key' => 'geo.communes', 'label' => 'Communes', 'url' => 'geo/communes', 'roles' => $staff],
            ['key' => 'geo.districts', 'label' => 'Quartiers', 'url' => 'geo/quartiers', 'roles' => $staff],
        ],
    ],
    ['key' => 'sites', 'label' => 'Pays & sites', 'icon' => 'mdi-earth', 'url' => 'pays-sites', 'roles' => ['super_admin']],
    ['key' => 'users', 'label' => 'Utilisateurs internes', 'icon' => 'mdi-account-multiple-outline', 'url' => 'utilisateurs', 'roles' => ['super_admin']],
    ['key' => 'logs', 'label' => 'Journal d\'activité', 'icon' => 'mdi-history', 'url' => 'journal', 'roles' => $staff],
    ['key' => 'exports', 'label' => 'Exports', 'icon' => 'mdi-tray-arrow-down', 'url' => 'exports', 'roles' => $staff],
    ['key' => 'settings', 'label' => 'Paramètres', 'icon' => 'mdi-tune-variant', 'url' => 'parametres', 'roles' => ['super_admin']],
];
