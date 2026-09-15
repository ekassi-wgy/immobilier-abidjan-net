<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Paramètres effectifs du site courant (table settings, valeurs JSON décodées).
 *
 *   settings('listing.lifetime_days', 90)
 *
 * Une valeur NULL en base signifie « décision métier en attente » : la valeur par défaut est alors retournée.
 */
final class Settings
{
    /** @param array<string, mixed> $values */
    public function __construct(private readonly array $values)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->values[$key]);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }
}
