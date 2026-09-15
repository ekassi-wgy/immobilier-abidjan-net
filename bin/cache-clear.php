<?php

declare(strict_types=1);

/**
 * Vide le cache applicatif (storage/cache) : à lancer après un déploiement
 * ou une modification directe en base des sites, domaines, pays ou paramètres.
 *
 * Usage : php bin/cache-clear.php
 */

/** @var App\Core\App $app */
$app = require __DIR__ . '/../app/bootstrap.php';

printf("Cache vidé : %d fichier(s) supprimé(s).\n", $app->cache()->clear());
