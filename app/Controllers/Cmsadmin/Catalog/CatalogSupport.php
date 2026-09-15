<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Catalog;

/**
 * Outils partagés des écrans du catalogue (traductions JSON, icônes, textes d'utilisation).
 */
trait CatalogSupport
{
    /** Valeur d'une colonne *_translations ({"en": "…"}). */
    private function translation(mixed $json, string $locale = 'en'): string
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return is_array($decoded) && is_string($decoded[$locale] ?? null) ? $decoded[$locale] : '';
    }

    private function encodeTranslation(?string $english): ?string
    {
        return $english !== null ? json_encode(['en' => $english], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null;
    }

    /** @param array<string, int> $usage [clé de traduction => nombre] */
    private function usageText(array $usage): string
    {
        return implode(', ', array_map(static fn (string $key, int $count): string => __($key, ['count' => $count]), array_keys($usage), $usage));
    }

    /**
     * Identifiants du sprite d'icônes du site public (public/assets/img/icons.svg).
     *
     * @return list<string>
     */
    private function icons(): array
    {
        static $icons = null;
        if ($icons === null) {
            preg_match_all('/<symbol id="i-([a-z0-9-]+)"/', (string) @file_get_contents(APP_ROOT . '/public/assets/img/icons.svg'), $matches);
            $icons = $matches[1];
            sort($icons);
        }

        return $icons;
    }

    /** Icône du sprite, vide, ou valeur déjà enregistrée (icône historique absente du sprite, conservée). */
    private function validIcon(?string $icon, ?string $current): bool
    {
        return $icon === null || $icon === $current || in_array($icon, $this->icons(), true);
    }
}
