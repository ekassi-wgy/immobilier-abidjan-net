<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Hachage et règles des mots de passe du back-office.
 * Argon2id si PHP le propose, bcrypt sinon ; password_verify() reconnaît les deux.
 */
final class PasswordHasher
{
    private const MAX_LENGTH = 128;

    /** Mots de passe trop courants refusés même s'ils respectent la longueur minimale. */
    private const COMMON = [
        'motdepasse123', 'motdepasse1234', 'password1234', 'password12345', '123456789012', 'azertyuiop123',
        'qwertyuiop123', 'immobilier123', 'abidjan123456', 'administrateur', 'adminadmin123', 'bienvenue1234',
    ];

    public function __construct(private readonly int $minLength = 12)
    {
    }

    public function hash(string $password): string
    {
        return password_hash($password, $this->algorithm());
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm());
    }

    /**
     * Erreurs de validation d'un nouveau mot de passe (clés de traduction), liste vide si valide.
     *
     * @return list<string>
     */
    public function validate(string $password, string $confirmation, string $email = ''): array
    {
        $errors = [];
        $length = mb_strlen($password);

        if ($length < $this->minLength) {
            $errors[] = 'auth.password.too_short';
        } elseif ($length > self::MAX_LENGTH) {
            $errors[] = 'auth.password.too_long';
        } elseif ($this->isWeak($password, $email)) {
            $errors[] = 'auth.password.too_weak';
        }

        if (!hash_equals($password, $confirmation)) {
            $errors[] = 'auth.password.mismatch';
        }

        return $errors;
    }

    public function minLength(): int
    {
        return $this->minLength;
    }

    /** Mot de passe aléatoire lisible (comptes créés par un administrateur, à changer à la première connexion). */
    public static function generate(int $length = 16): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    /** Mot de passe courant, contenant l'identifiant de l'adresse email, ou trop répétitif (« aaaaaaaaaaaa »). */
    private function isWeak(string $password, string $email): bool
    {
        $lower = mb_strtolower($password);
        if (in_array($lower, self::COMMON, true) || count(array_unique(mb_str_split($password))) < 5) {
            return true;
        }

        $localPart = mb_strtolower((string) strstr($email, '@', true));

        return mb_strlen($localPart) >= 4 && str_contains($lower, $localPart);
    }

    private function algorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }
}
