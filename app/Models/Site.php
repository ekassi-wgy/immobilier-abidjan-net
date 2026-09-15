<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Site courant, résolu depuis le nom d'hôte (middleware SiteResolver).
 * Toute requête métier du site public filtre sur $site->country->id.
 */
final class Site
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_DISABLED = 'disabled';

    /**
     * @param list<string> $supportedLocales
     * @param string       $host             Nom d'hôte de la requête (validé : présent dans site_domains)
     * @param string       $environment      Environnement du domaine : production | staging | local
     * @param string|null  $primaryHost      Domaine principal de production
     */
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly string $theme,
        public readonly string $defaultLocale,
        public readonly array $supportedLocales,
        public readonly ?string $contactEmail,
        public readonly ?string $contactPhone,
        public readonly ?string $contactWhatsapp,
        public readonly ?string $address,
        public readonly string $status,
        public readonly Country $country,
        public readonly string $host,
        public readonly string $environment,
        public readonly ?string $primaryHost,
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInMaintenance(): bool
    {
        return $this->status === self::STATUS_MAINTENANCE;
    }

    public function isProductionHost(): bool
    {
        return $this->environment === 'production';
    }

    public function supportsLocale(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales, true);
    }
}
