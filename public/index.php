<?php

declare(strict_types=1);

/**
 * Front controller — VERSION PROVISOIRE.
 *
 * Tant que le socle (lot 1.1 : routeur, .env, PDO) n'existe pas, ce point d'entrée ne sert que
 * la prévisualisation du back-office avec des données fictives, et UNIQUEMENT en local.
 */

$host = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
$isLocal = in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true) || str_ends_with($host, '.local');

if (!$isLocal) {
    http_response_code(503);
    header('Retry-After: 3600');
    echo 'Site en préparation.';
    exit;
}

require __DIR__ . '/../bin/preview/cmsadmin.php';
