<?php

declare(strict_types=1);

/**
 * Routeur de PRÉVISUALISATION du back-office cmsadmin — développement local uniquement.
 * Aucune base de données : les pages sont rendues avec les données d'exemple de bin/fixtures/cmsadmin.php.
 * Sera remplacé par le front controller public/index.php au lot 1.1.
 *
 * Lancement :  php -S localhost:8000 -t public bin/dev-server.php
 * Puis :       http://localhost:8000/cmsadmin            (Super Admin)
 *              http://localhost:8000/cmsadmin?role=agency (vue Agence)
 */

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

// Fichiers statiques servis directement par le serveur intégré
if ($path !== '/' && is_file(__DIR__ . '/../public' . $path)) {
    return false;
}

require __DIR__ . '/../app/Support/helpers.php';
$fixtures = require __DIR__ . '/fixtures/cmsadmin.php';

$role = in_array($_GET['role'] ?? '', ['super_admin', 'country_admin', 'agency'], true) ? $_GET['role'] : 'super_admin';
$shared = $fixtures['shared']($role);

$page = static function (string $view, array $data, array $options = []) use ($shared): void {
    $data += $shared;
    echo render_view('cmsadmin/layouts/app', $shared + $options + [
        'content' => render_view('cmsadmin/pages/' . $view, $data),
    ]);
};

$route = rtrim($path, '/');

switch (true) {
    case $route === '' || $route === '/cmsadmin':
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
        $data = $fixtures['form']($role, str_ends_with($route, '/modifier'), isset($_GET['erreurs']));
        $page('properties/form', $data, [
            'title' => str_ends_with($route, '/modifier') ? 'Modifier l’annonce' : 'Nouvelle annonce',
            'activeMenu' => str_ends_with($route, '/modifier') ? 'properties.all' : 'properties.create',
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
