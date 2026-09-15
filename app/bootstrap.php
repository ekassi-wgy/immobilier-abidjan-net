<?php

declare(strict_types=1);

/**
 * Amorçage commun (site web, scripts bin/, futures tâches CRON) : autoload, .env, erreurs, fuseau.
 * Retourne l'application prête à traiter une requête.
 */

use App\Core\App;
use App\Core\Env;

define('APP_ROOT', dirname(__DIR__));

$autoload = APP_ROOT . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(503);
    exit("Dépendances absentes : lancer « composer install ».\n");
}
require $autoload;

Env::load(APP_ROOT . '/.env');

$app = new App(APP_ROOT);
$app->errors->register();

date_default_timezone_set((string) $app->config->get('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');

return $app;
