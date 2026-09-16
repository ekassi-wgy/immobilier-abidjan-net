<?php

declare(strict_types=1);

/**
 * Front controller : toutes les URL non statiques arrivent ici (public/.htaccess).
 */

/** @var App\Core\App $app */
$app = require __DIR__ . '/../app/bootstrap.php';

// Le back-office d'abord : les routes de recherche du site public se terminent par des
// segments génériques (/{transaction}/{type}/…) et le routeur retient la première route
// qui correspond. Voir l'avertissement en bas de routes/web.php.
$app->loadRoutes(APP_ROOT . '/routes/cmsadmin.php');
$app->loadRoutes(APP_ROOT . '/routes/web.php');

$app->run();
