<?php

declare(strict_types=1);

/**
 * Routeur pour le serveur PHP intégré (alternative à MAMP).
 *
 * Lancement : PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8765 -t public bin/dev-server.php
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

require __DIR__ . '/../public/index.php';
