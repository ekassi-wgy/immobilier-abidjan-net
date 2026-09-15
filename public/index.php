<?php

declare(strict_types=1);

/**
 * Front controller : toutes les URL non statiques arrivent ici (public/.htaccess).
 */

/** @var App\Core\App $app */
$app = require __DIR__ . '/../app/bootstrap.php';

$app->loadRoutes(APP_ROOT . '/routes/web.php');
$app->loadRoutes(APP_ROOT . '/routes/cmsadmin.php');

$app->run();
