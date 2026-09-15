<?php

declare(strict_types=1);

/**
 * Configuration générale de l'application (valeurs issues de .env).
 */

$env = (string) env('APP_ENV', 'production');

return [
    'name' => 'immobilier.abidjan.net',
    'env' => $env,
    // Le détail des erreurs n'est jamais affiché en production, même si APP_DEBUG=true
    'debug' => $env !== 'production' && (bool) env('APP_DEBUG', false),
    'url' => rtrim((string) env('APP_URL', ''), '/'),
    'base_path' => rtrim((string) env('APP_BASE_PATH', ''), '/'),
    'locale' => (string) env('APP_LOCALE', 'fr'),
    'fallback_locale' => 'fr',
    'locales' => ['fr', 'en'],
    // Toutes les dates sont manipulées et stockées en UTC
    'timezone' => 'UTC',
    'preview' => $env === 'local' && (bool) env('APP_PREVIEW', false),

    'session' => [
        'name' => 'ian_session',
        'lifetime' => (int) env('SESSION_LIFETIME', 120), // minutes
        'secure' => env('SESSION_SECURE', 'auto'),
    ],

    'log' => [
        'path' => APP_ROOT . '/storage/logs',
    ],
];
