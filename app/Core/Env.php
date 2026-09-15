<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Lecture du fichier .env (format KEY=VALUE), sans dépendance externe.
 *
 * - lignes vides et commentaires (#) ignorés, préfixe « export » toléré ;
 * - valeurs entre guillemets doubles (échappements \n, \", \\) ou simples (littérales) ;
 * - une variable déjà définie par le serveur (Plesk, Apache SetEnv) n'est pas écrasée.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException("Lecture impossible : {$file}");
        }

        foreach ($lines as $number => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
                throw new RuntimeException(sprintf('.env ligne %d : syntaxe invalide', $number + 1));
            }

            [, $key, $raw] = $matches;
            $existing = getenv($key);
            self::$values[$key] = $existing !== false ? $existing : self::parseValue($raw);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$values[$key] ?? getenv($key);
        if ($value === false) {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }

    private static function parseValue(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        $quote = $raw[0];
        if ($quote === '"' || $quote === "'") {
            $end = strrpos($raw, $quote);
            if ($end === 0) {
                throw new RuntimeException('.env : guillemet non fermé');
            }
            $value = substr($raw, 1, $end - 1);

            return $quote === '"'
                ? strtr($value, ['\\n' => "\n", '\\"' => '"', '\\\\' => '\\'])
                : $value;
        }

        // Valeur sans guillemets : un commentaire en fin de ligne est retiré
        return trim((string) preg_replace('/\s+#.*$/', '', $raw));
    }
}
