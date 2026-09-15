<?php

declare(strict_types=1);

/**
 * Envoi des emails (PHPMailer).
 *  - log  : aucun envoi, chaque message est enregistré dans storage/mail/*.eml (développement, SMTP non fourni) ;
 *  - smtp : envoi réel via le serveur SMTP.
 */

return [
    'mailer' => (string) env('MAIL_MAILER', 'log'),
    'path' => APP_ROOT . '/storage/mail',
    'smtp' => [
        'host' => (string) env('SMTP_HOST', ''),
        'port' => (int) env('SMTP_PORT', 587),
        'username' => (string) env('SMTP_USERNAME', ''),
        'password' => (string) env('SMTP_PASSWORD', ''),
        // tls (STARTTLS) | ssl | vide
        'encryption' => (string) env('SMTP_ENCRYPTION', 'tls'),
        'timeout' => 15,
    ],
    'from' => [
        'address' => (string) env('MAIL_FROM_ADDRESS', 'no-reply@immobilier.abidjan.net'),
        'name' => (string) env('MAIL_FROM_NAME', 'immobilier.abidjan.net'),
    ],
];
