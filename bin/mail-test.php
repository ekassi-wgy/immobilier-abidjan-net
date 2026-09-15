<?php

declare(strict_types=1);

/**
 * Envoie un email de test avec la configuration courante (MAIL_MAILER, SMTP_*).
 *
 * Usage : php bin/mail-test.php --to=destinataire@exemple.ci
 */

/** @var App\Core\App $app */
$app = require __DIR__ . '/../app/bootstrap.php';

$to = (string) (getopt('', ['to:'])['to'] ?? '');
if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Usage : php bin/mail-test.php --to=destinataire@exemple.ci\n");
    exit(1);
}

$sentAt = gmdate('d/m/Y H:i') . ' UTC';
$file = $app->mailer()->send(
    $to,
    'Test d’envoi · ' . config('app.name'),
    '<p>Email de test envoyé le ' . e($sentAt) . ' (pilote « ' . e(config('mail.mailer')) . ' »).</p>',
    "Email de test envoyé le {$sentAt} (pilote « " . config('mail.mailer') . " »)."
);

echo $file === null ? "Email envoyé à {$to} via " . config('mail.smtp.host') . ".\n" : "Pilote log : message enregistré dans {$file}\n";
