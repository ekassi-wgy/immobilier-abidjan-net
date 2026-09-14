<?php

declare(strict_types=1);

/**
 * PRÉVISUALISATION du back-office cmsadmin — développement local uniquement.
 * Aucune base de données : les pages sont rendues avec les données fictives de bin/preview/fixtures.php.
 * Inclus par public/index.php (MAMP) et bin/dev-server.php (serveur PHP intégré).
 * Sera remplacé par le vrai routeur au lot 1.1.
 *
 * Le rôle simulé se choisit avec ?role=super_admin|country_admin|agency et reste mémorisé (cookie).
 */

require_once __DIR__ . '/../../app/Support/helpers.php';
$fixtures = require __DIR__ . '/fixtures.php';

$roles = ['super_admin', 'country_admin', 'agency'];
if (in_array($_GET['role'] ?? '', $roles, true)) {
    $role = $_GET['role'];
    setcookie('preview_role', $role, ['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
} else {
    $role = in_array($_COOKIE['preview_role'] ?? '', $roles, true) ? $_COOKIE['preview_role'] : 'super_admin';
}
$shared = $fixtures['shared']($role);

$page = static function (string $view, array $data, array $options = []) use ($shared): void {
    $data += $shared;
    echo render_view('cmsadmin/layouts/app', $shared + $options + [
        'content' => render_view('cmsadmin/pages/' . $view, $data),
    ]);
};

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$route = rtrim($path, '/');

switch (true) {
    case $route === '/cmsadmin':
        $page('dashboard/index', $fixtures['dashboard']($role), [
            'title' => 'Tableau de bord',
            'activeMenu' => 'dashboard',
            'plugins' => ['chart'],
            'pageScripts' => ['js/dashboard.js'],
        ]);
        break;

    case $route === '/cmsadmin/annonces':
        $page('properties/index', $fixtures['properties']($role, $_GET), [
            'title' => 'Annonces',
            'activeMenu' => ($_GET['statut'] ?? '') === 'en-attente' ? 'properties.pending' : 'properties.all',
            'plugins' => ['select2'],
            'flash' => isset($_GET['flash']) ? [['type' => 'success', 'message' => 'L’annonce IAN-24518 a été publiée.']] : [],
        ]);
        break;

    case $route === '/cmsadmin/annonces/nouvelle':
    case preg_match('#^/cmsadmin/annonces/[A-Z0-9-]+/modifier$#', $route) === 1:
        $isEdit = str_ends_with($route, '/modifier');
        $page('properties/form', $fixtures['form']($role, $isEdit, isset($_GET['erreurs'])), [
            'title' => $isEdit ? 'Modifier l’annonce' : 'Nouvelle annonce',
            'activeMenu' => $isEdit ? 'properties.all' : 'properties.create',
            'plugins' => ['select2'],
        ]);
        break;

    case $route === '/cmsadmin/connexion':
        echo render_view('cmsadmin/layouts/auth', [
            'title' => 'Connexion',
            'variant' => 'split',
            'content' => render_view('cmsadmin/pages/auth/login', [
                'csrfToken' => 'dev',
                'errorMessage' => isset($_GET['erreur']) ? 'Identifiants incorrects.' : null,
                'email' => isset($_GET['erreur']) ? 'agence@exemple.ci' : '',
            ]),
        ]);
        break;

    default:
        $code = $route === '/cmsadmin/erreur-500' ? 500 : 404;
        http_response_code($code);
        echo render_view('cmsadmin/layouts/auth', [
            'title' => $code === 404 ? 'Page introuvable' : 'Erreur',
            'variant' => 'center',
            'content' => render_view('cmsadmin/pages/errors/error', ['code' => $code]),
        ]);
}
