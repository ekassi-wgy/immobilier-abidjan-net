<?php

declare(strict_types=1);

/**
 * Authentification du back-office /cmsadmin.
 * Les seuils de blocage des connexions sont des paramètres métier : table settings (security.*).
 */

return [
    // Déconnexion après inactivité (minutes) ; « Rester connecté » prend alors le relais s'il a été coché
    'idle_timeout' => (int) env('AUTH_IDLE_TIMEOUT', 120),
    'remember_days' => (int) env('AUTH_REMEMBER_DAYS', 30),
    'remember_cookie' => 'ian_remember',
    // Validité du lien « mot de passe oublié » (minutes)
    'reset_expires' => 60,
    // Demandes de lien autorisées par adresse IP sur 15 minutes (anti-abus d'envoi d'emails)
    'reset_ip_limit' => 5,
    // Échecs de connexion tolérés par adresse IP sur la fenêtre de blocage (toutes adresses email confondues)
    'ip_failure_limit' => 30,
    'password_min_length' => 12,
];
