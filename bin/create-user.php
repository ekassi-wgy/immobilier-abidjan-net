<?php

declare(strict_types=1);

/**
 * Crée un compte interne du back-office (premier Super Admin, Admin Pays).
 * Les comptes d'agence seront créés depuis le back-office (lot 1.5).
 *
 * Usage :
 *   php bin/create-user.php --role=super_admin --email=admin@exemple.ci --first-name=Awa --last-name=Koné
 *   php bin/create-user.php --role=country_admin --country=CI --email=… --first-name=… --last-name=…
 *
 * Un mot de passe provisoire est généré et affiché une seule fois : il devra être changé à la première connexion.
 * Aucun mot de passe n'est accepté en argument (il resterait dans l'historique du terminal).
 */

use App\Models\User;
use App\Services\PasswordHasher;
use App\Services\UserRepository;

/** @var App\Core\App $app */
$app = require __DIR__ . '/../app/bootstrap.php';

$options = getopt('', ['role:', 'email:', 'first-name:', 'last-name:', 'country:', 'help']);
$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL . 'Aide : php bin/create-user.php --help' . PHP_EOL);
    exit(1);
};

if (isset($options['help']) || $options === []) {
    echo "php bin/create-user.php --role=super_admin|country_admin --email=… --first-name=… --last-name=… [--country=CI]\n";
    exit(0);
}

$role = (string) ($options['role'] ?? '');
$email = UserRepository::normalizeEmail((string) ($options['email'] ?? ''));
$firstName = trim((string) ($options['first-name'] ?? ''));
$lastName = trim((string) ($options['last-name'] ?? ''));
$countryIso = strtoupper(trim((string) ($options['country'] ?? '')));

if (!in_array($role, [User::SUPER_ADMIN, User::COUNTRY_ADMIN], true)) {
    $fail('Rôle invalide : super_admin ou country_admin (les comptes agence se créent depuis le back-office).');
}
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $fail('Adresse email invalide.');
}
if ($firstName === '' || $lastName === '') {
    $fail('Prénom et nom obligatoires.');
}

$db = $app->db();
$countryId = null;
if ($role === User::COUNTRY_ADMIN || $countryIso !== '') {
    $countryId = $db->scalar('SELECT id FROM countries WHERE iso2 = :iso', ['iso' => $countryIso]);
    if ($countryId === null) {
        $fail('Pays introuvable (code ISO à 2 lettres, ex. --country=CI).');
    }
}
if ($app->users()->findByEmail($email) !== null || $db->scalar('SELECT id FROM users WHERE email = :email', ['email' => $email]) !== null) {
    $fail("Un compte existe déjà avec l'adresse {$email}.");
}

$password = PasswordHasher::generate();
$userId = $db->insert('users', [
    'role' => $role,
    'country_id' => $countryId !== null ? (int) $countryId : null,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $email,
    'password_hash' => $app->hasher()->hash($password),
    'is_active' => 1,
    'must_change_password' => 1,
]);
$app->activity()->log('user.created', null, $countryId !== null ? (int) $countryId : null, 'user', $userId, 'Compte créé en ligne de commande (' . $role . ')');

echo "Compte créé (#{$userId}) : {$firstName} {$lastName} <{$email}>, rôle {$role}.\n";
echo "Mot de passe provisoire (à changer à la première connexion) : {$password}\n";
