<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Pays d'un site : devise, indicatif, fuseau horaire, langue par défaut.
 */
final class Country
{
    /** @param array<string, string> $nameTranslations */
    public function __construct(
        public readonly int $id,
        public readonly string $iso2,
        public readonly string $name,
        public readonly array $nameTranslations,
        public readonly string $currencyCode,
        public readonly string $currencySymbol,
        public readonly int $currencyDecimals,
        public readonly string $phonePrefix,
        public readonly string $defaultLocale,
        public readonly string $timezone,
        public readonly bool $isActive,
    ) {
    }

    /** @param array<string, mixed> $row Ligne de la table countries */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['iso2'],
            (string) $row['name'],
            self::decodeJson($row['name_translations'] ?? null),
            (string) $row['currency_code'],
            (string) $row['currency_symbol'],
            (int) $row['currency_decimals'],
            (string) $row['phone_prefix'],
            (string) $row['default_locale'],
            (string) $row['timezone'],
            (bool) $row['is_active'],
        );
    }

    /** Nom dans la langue demandée, nom de référence (français) sinon. */
    public function localizedName(string $locale): string
    {
        return $this->nameTranslations[$locale] ?? $this->name;
    }

    /** @return array<string, string> */
    private static function decodeJson(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return is_array($decoded) ? array_filter($decoded, 'is_string') : [];
    }
}
