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
     * Réseaux sociaux reconnus, dans l'ordre d'affichage, avec les domaines acceptés pour chacun
     * (un lien vers un autre site est refusé à la saisie).
     */
    public const SOCIAL_NETWORKS = [
        'facebook' => ['facebook.com', 'fb.com', 'fb.me'],
        'instagram' => ['instagram.com'],
        'linkedin' => ['linkedin.com'],
        'x' => ['x.com', 'twitter.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'tiktok' => ['tiktok.com'],
    ];

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
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        /** @var array<string, string> Réseau => URL, uniquement les réseaux renseignés, dans l'ordre de SOCIAL_NETWORKS */
        public readonly array $socialLinks = [],
        /** Tag Google (« G-… » ou « UA-… ») ; null = aucune mesure d'audience */
        public readonly ?string $analyticsId = null,
    ) {
    }

    /**
     * Adresse sans la boîte postale (« 01 BP 12324 01 Abidjan »), pour les emplacements courts
     * comme le pied de page. L'adresse complète reste celle de la page Contact et des mentions légales.
     */
    public function shortAddress(): ?string
    {
        if ($this->address === null) {
            return null;
        }

        $parts = array_filter(
            array_map('trim', explode(',', $this->address)),
            static fn (string $part): bool => $part !== '' && preg_match('/\bB\.?\s?P\.?\b|bo[iî]te postale/iu', $part) !== 1
        );

        return $parts === [] ? $this->address : implode(', ', $parts);
    }

    /** Position de l'éditeur connue : la page Contact affiche une carte. */
    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInMaintenance(): bool
    {
        return $this->status === self::STATUS_MAINTENANCE;
    }

    /**
     * Tag de mesure d'audience à proposer au visiteur : jamais hors production, pour que la
     * pré-production et le développement ne faussent pas les statistiques.
     */
    public function analyticsTag(): ?string
    {
        return $this->isProductionHost() ? $this->analyticsId : null;
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
