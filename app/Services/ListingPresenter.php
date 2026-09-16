<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Mise en forme d'une annonce pour le site public : URL, image, prix, localisation, caractéristiques,
 * contacts affichés. Produit le tableau attendu par `front/partials/property-card`.
 *
 * Les coordonnées publiées sont celles de l'annonce, à défaut celles de l'agence
 * (jamais les informations de `property_private_details`).
 */
final class ListingPresenter
{
    /** Largeurs générées par ImageUploader pour les photos d'annonces. */
    private const IMAGE_WIDTHS = [400, 800];

    /** Une annonce publiée depuis moins de N jours porte le badge « Nouveau ». */
    private const NEW_DAYS = 7;

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function cards(array $rows): array
    {
        return array_map(fn (array $row): array => $this->card($row), $rows);
    }

    /**
     * @param array<string, mixed> $row Ligne issue de ListingRepository
     * @return array<string, mixed>
     */
    public function card(array $row): array
    {
        $phone = $row['contact_phone'] ?? $row['agency_phone'] ?? null;
        $whatsapp = $row['contact_whatsapp'] ?? $row['agency_whatsapp'] ?? null;

        return [
            'reference' => (string) $row['reference'],
            'url' => $this->url($row),
            'title' => (string) $row['title'],
            'category' => (string) $row['category_name'],
            'transaction' => (string) $row['transaction_name'],
            'price' => $row['price'] !== null ? (float) $row['price'] : null,
            'currency' => $this->currency($row['currency_code'] ?? null),
            'period' => (string) ($row['price_period'] ?? 'total'),
            'location' => $this->location($row),
            'image' => $this->image($row),
            'photos' => (int) ($row['photos_count'] ?? 0),
            'badges' => $this->badges($row),
            'specs' => $this->specs($row),
            'agency' => $row['agency_name'] !== null ? (string) $row['agency_name'] : (string) (site()?->name ?? ''),
            'agency_url' => $row['agency_slug'] !== null ? 'agences/' . $row['agency_slug'] : null,
            'verified' => (int) ($row['agency_verified'] ?? 0) === 1,
            'phone' => $phone !== null && $phone !== '' ? (string) $phone : null,
            'whatsapp' => $whatsapp !== null && $whatsapp !== '' ? (string) $whatsapp : null,
        ];
    }

    /** Symbole affiché (FCFA) plutôt que le code ISO, quand l'annonce est dans la devise du pays. */
    private function currency(?string $code): string
    {
        $country = site()?->country;
        if ($country === null) {
            return (string) $code;
        }

        return $code === null || $code === $country->currencyCode ? $country->currencySymbol : $code;
    }

    /** URL publique d'une annonce : /annonces/{slug}-ref{id}. */
    public function url(array $row): string
    {
        return 'annonces/' . $row['slug'] . '-ref' . (int) $row['id'];
    }

    /**
     * Image de couverture : URL de la photo réelle (WebP multi-tailles) ou visuel de remplacement.
     *
     * @return array{src: string, srcset: string, alt: string, placeholder: bool}
     */
    private function image(array $row): array
    {
        $alt = trim((string) ($row['cover_alt'] ?? '')) !== ''
            ? (string) $row['cover_alt']
            : (string) $row['title'];

        if (empty($row['cover_path'])) {
            return ['src' => asset('img/no-photo.svg'), 'srcset' => '', 'alt' => $alt, 'placeholder' => true];
        }

        $base = (string) $row['cover_path'];
        $srcset = [];
        foreach (self::IMAGE_WIDTHS as $width) {
            $srcset[] = url("{$base}-{$width}.webp") . " {$width}w";
        }

        return [
            'src' => url("{$base}-800.webp"),
            'srcset' => implode(', ', $srcset),
            'alt' => $alt,
            'placeholder' => false,
        ];
    }

    /** « Quartier, Commune » ou « Commune, Ville » selon ce qui est renseigné. */
    private function location(array $row): string
    {
        $parts = array_filter([
            $row['district_name'] ?? null,
            $row['commune_name'] ?? null,
            $row['commune_name'] === null ? ($row['city_name'] ?? null) : null,
        ]);
        if ($parts === []) {
            $parts = [$row['city_name'] ?? ''];
        }

        return implode(', ', array_slice(array_values(array_map('strval', $parts)), 0, 2));
    }

    /**
     * Badges de la carte : « Nouveau » (publication récente) et « À la une ».
     *
     * @return list<array{label: string, variant: string}>
     */
    private function badges(array $row): array
    {
        $badges = [];
        $publishedAt = $row['published_at'] ?? null;
        if ($publishedAt !== null && strtotime((string) $publishedAt) > time() - self::NEW_DAYS * 86400) {
            $badges[] = ['label' => __('front.card.new'), 'variant' => 'new'];
        }
        if ((int) ($row['is_featured'] ?? 0) === 1) {
            $badges[] = ['label' => __('front.card.featured'), 'variant' => 'featured'];
        }

        return $badges;
    }

    /**
     * Trois caractéristiques au maximum : surface, terrain, chambres, pièces, salles d'eau.
     *
     * @return list<array{icon: string, label: string}>
     */
    private function specs(array $row): array
    {
        $candidates = [
            ['icon' => 'area', 'value' => $row['living_area'] ?? null, 'label' => static fn (string $v): string => $v . "\u{00A0}m²"],
            ['icon' => 'land-area', 'value' => $row['land_area'] ?? null, 'label' => static fn (string $v): string => $v . "\u{00A0}m²"],
            ['icon' => 'bed', 'value' => $row['bedrooms'] ?? null, 'label' => static fn (string $v): string => __('front.card.bedrooms', ['count' => $v])],
            ['icon' => 'rooms', 'value' => $row['rooms'] ?? null, 'label' => static fn (string $v): string => __('front.card.rooms', ['count' => $v])],
            ['icon' => 'bath', 'value' => $row['bathrooms'] ?? null, 'label' => static fn (string $v): string => __('front.card.bathrooms', ['count' => $v])],
        ];

        $specs = [];
        foreach ($candidates as $candidate) {
            if ($candidate['value'] === null || (float) $candidate['value'] <= 0) {
                continue;
            }
            $value = format_number((float) $candidate['value']);
            $specs[] = ['icon' => $candidate['icon'], 'label' => ($candidate['label'])($value)];
            if (count($specs) === 3) {
                break;
            }
        }

        return $specs;
    }
}
