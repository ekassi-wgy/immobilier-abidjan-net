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

    /** Largeurs proposées à la galerie de la fiche (la plus grande sert à la visionneuse). */
    private const GALLERY_WIDTHS = [400, 800, 1600];

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
            // Weblogy est l'interlocuteur de toutes les annonces : coordonnées du site, jamais celles du partenaire.
            'phone' => site()?->contactPhone,
            'whatsapp' => site()?->contactWhatsapp,
            'description' => $this->excerpt($row['description'] ?? null),
        ];
    }

    /**
     * Points de la vue carte : coordonnées et carte annonce complète, affichée dans l'infobulle.
     *
     * @param list<array<string, mixed>> $rows Lignes de ListingRepository::mapPoints()
     * @return list<array{lat: float, lng: float, card: array<string, mixed>}>
     */
    public function points(array $rows): array
    {
        $points = [];
        foreach ($rows as $row) {
            if ($row['latitude'] === null || $row['longitude'] === null) {
                continue;
            }
            $points[] = [
                'lat' => (float) $row['latitude'],
                'lng' => (float) $row['longitude'],
                'card' => $this->card($row),
            ];
        }

        return $points;
    }

    /** Début de la description, affiché sur la carte annonce en vue liste. */
    private function excerpt(mixed $description, int $length = 180): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $description)) ?? '');
        if ($text === '') {
            return null;
        }

        return mb_strlen($text) <= $length ? $text : rtrim(mb_substr($text, 0, $length)) . "\u{2026}";
    }

    /**
     * Fiche annonce complète (lot 1.10) : galerie, prix, localisation, critères groupés,
     * équipements, médias et coordonnées de Weblogy, seul interlocuteur du prospect.
     *
     * @param array<string, mixed>       $row      Ligne de ListingRepository::findPublished()
     * @param list<array<string, mixed>> $images   ListingRepository::publicImages()
     * @param list<array<string, mixed>> $criteria ListingRepository::criteriaRows()
     * @param list<array<string, mixed>> $features ListingRepository::publicFeatures()
     * @return array<string, mixed>
     */
    public function detail(array $row, array $images, array $criteria, array $features): array
    {
        $groups = $this->criteriaGroups($criteria);

        return [
            'id' => (int) $row['id'],
            'reference' => (string) $row['reference'],
            'url' => $this->url($row),
            'title' => (string) $row['title'],
            'description' => (string) $row['description'],
            'category' => (string) $row['category_name'],
            'category_slug' => (string) $row['category_slug'],
            'family' => $row['family_name'] !== null ? (string) $row['family_name'] : null,
            'family_slug' => $row['family_slug'] !== null ? (string) $row['family_slug'] : null,
            'transaction' => (string) $row['transaction_name'],
            'transaction_slug' => (string) $row['transaction_slug'],
            'price' => $row['price'] !== null ? (float) $row['price'] : null,
            'price_label' => $row['price'] !== null
                ? format_price($row['price'], $this->currency($row['currency_code'] ?? null))
                : __('common.price_on_request'),
            'period' => (string) ($row['price_period'] ?? 'total'),
            'negotiable' => (int) ($row['is_negotiable'] ?? 0) === 1,
            'charges' => $row['charges'] !== null && (float) $row['charges'] > 0 ? (float) $row['charges'] : null,
            'fee_percent' => $row['agency_fee_percent'] !== null && (float) $row['agency_fee_percent'] > 0 ? (float) $row['agency_fee_percent'] : null,
            'location' => $this->location($row),
            'city' => (string) $row['city_name'],
            'commune' => $row['commune_name'] !== null ? (string) $row['commune_name'] : null,
            'district' => $row['district_name'] !== null ? (string) $row['district_name'] : null,
            // L'adresse exacte n'est publiée que si l'annonce l'autorise.
            'address' => (int) ($row['show_exact_location'] ?? 0) === 1 && !empty($row['address']) ? (string) $row['address'] : null,
            'map' => $this->map($row),
            'directions' => $this->directions($row),
            'gallery' => $this->gallery($images, (string) $row['title']),
            'badges' => $this->badges($row),
            'specs' => $this->specs($row),
            'legal' => $groups['legal'] ?? null,
            'criteria' => array_diff_key($groups, ['legal' => null]),
            'features' => $this->featureGroups($features),
            'availability' => (string) ($row['availability'] ?? 'available'),
            'available_from' => $row['available_from'] !== null ? (string) $row['available_from'] : null,
            'video' => $this->link($row['video_url'] ?? null),
            'tour' => $this->link($row['virtual_tour_url'] ?? null),
            'document' => !empty($row['document_path']) ? url((string) $row['document_path']) : null,
            // Interlocuteur unique : Weblogy (coordonnées du site). Le partenaire reste une information interne.
            'phone' => site()?->contactPhone,
            'whatsapp' => site()?->contactWhatsapp,
            'email' => site()?->contactEmail,
            'published_at' => $row['published_at'] !== null ? (string) $row['published_at'] : null,
            'updated_at' => $row['updated_at'] !== null ? (string) $row['updated_at'] : null,
        ];
    }

    /**
     * Galerie : chaque photo en trois largeurs WebP. `property_images.path` ne porte pas le
     * suffixe de taille, il est ajouté ici.
     *
     * @param list<array<string, mixed>> $images
     * @return list<array{src: string, srcset: string, full: string, alt: string}>
     */
    private function gallery(array $images, string $title): array
    {
        $gallery = [];
        foreach ($images as $index => $image) {
            $base = (string) $image['path'];
            $srcset = [];
            foreach (self::GALLERY_WIDTHS as $width) {
                $srcset[] = url("{$base}-{$width}.webp") . " {$width}w";
            }
            $alt = trim((string) ($image['alt_text'] ?? ''));
            $gallery[] = [
                'src' => url("{$base}-800.webp"),
                'srcset' => implode(', ', $srcset),
                'full' => url("{$base}-1600.webp"),
                'alt' => $alt !== '' ? $alt : __('front.property.photo_alt', ['title' => $title, 'index' => $index + 1]),
            ];
        }

        return $gallery;
    }

    /**
     * Point de la carte. Quand la localisation exacte n'est pas publique, les coordonnées sont
     * arrondies au centième de degré (environ 1 km) : le quartier reste lisible, pas l'adresse.
     *
     * @return array{lat: float, lng: float, exact: bool}|null
     */
    private function map(array $row): ?array
    {
        if ($row['latitude'] === null || $row['longitude'] === null) {
            return null;
        }
        $exact = (int) ($row['show_exact_location'] ?? 0) === 1;

        return [
            'lat' => $exact ? (float) $row['latitude'] : round((float) $row['latitude'], 2),
            'lng' => $exact ? (float) $row['longitude'] : round((float) $row['longitude'], 2),
            'exact' => $exact,
        ];
    }

    /**
     * Lien d'itinéraire vers Google Maps. C'est une simple URL : ni clé d'API, ni cookie déposé,
     * ni quota consommé — le visiteur bascule dans l'application qu'il utilise déjà, avec la
     * navigation. Rien à voir avec la carte affichée sur la page, qui reste Leaflet + OSM.
     *
     * **Uniquement quand l'adresse exacte est publique.** Sinon un itinéraire révélerait ce que
     * l'arrondi des coordonnées masque volontairement (voir map()).
     */
    private function directions(array $row): ?string
    {
        if ((int) ($row['show_exact_location'] ?? 0) !== 1) {
            return null;
        }
        if ($row['latitude'] !== null && $row['longitude'] !== null) {
            $destination = $row['latitude'] . ',' . $row['longitude'];
        } elseif (!empty($row['address'])) {
            $destination = (string) $row['address'];
        } else {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($destination);
    }

    /**
     * Critères regroupés par section, dans l'ordre du catalogue. Les valeurs multi-choix d'un
     * même critère sont réunies sur une seule ligne.
     *
     * @param list<array<string, mixed>> $rows
     * @return array<string, array{label: string, items: list<array{label: string, value: string}>}>
     */
    private function criteriaGroups(array $rows): array
    {
        $byAttribute = [];
        foreach ($rows as $row) {
            $value = $this->criterionValue($row);
            if ($value === null) {
                continue;
            }
            $key = (string) $row['group_code'] . '.' . (string) $row['code'];
            if (isset($byAttribute[$key])) {
                $byAttribute[$key]['values'][] = $value;
                continue;
            }
            $byAttribute[$key] = [
                'group' => (string) $row['group_code'],
                'group_label' => self::localized($row['group_name'], $row['group_translations']),
                'label' => self::localized($row['name'], $row['name_translations']),
                'values' => [$value],
            ];
        }

        $groups = [];
        foreach ($byAttribute as $attribute) {
            $groups[$attribute['group']]['label'] = $attribute['group_label'];
            $groups[$attribute['group']]['items'][] = [
                'label' => $attribute['label'],
                'value' => implode(', ', $attribute['values']),
            ];
        }

        return $groups;
    }

    /** Valeur lisible d'un critère, selon son type de saisie. */
    private function criterionValue(array $row): ?string
    {
        if ($row['option_label'] !== null) {
            return self::localized($row['option_label'], $row['option_translations']);
        }

        $unit = !empty($row['unit']) ? "\u{00A0}" . (string) $row['unit'] : '';

        return match ((string) $row['input_type']) {
            'boolean' => isset($row['value_boolean']) && $row['value_boolean'] !== null
                ? __((int) $row['value_boolean'] === 1 ? 'common.yes' : 'common.no')
                : null,
            'integer' => $this->number($row['value'] ?? $row['value_integer'], $unit),
            'year' => isset($row['value_integer']) ? (string) (int) $row['value_integer'] : null,
            'decimal' => $this->number($row['value'] ?? $row['value_decimal'], $unit),
            'date' => !empty($row['value_date']) ? format_date((string) $row['value_date']) : null,
            'select', 'multiselect' => null, // sans option rattachée, il n'y a rien à afficher
            default => !empty($row['value_text']) ? (string) $row['value_text'] : null,
        };
    }

    private function number(mixed $value, string $unit): ?string
    {
        if ($value === null || $value === '' || (float) $value <= 0) {
            return null;
        }

        return format_decimal((float) $value) . $unit;
    }

    /**
     * Équipements groupés par famille (confort, sécurité, extérieurs, réseaux, connectivité).
     *
     * @param list<array<string, mixed>> $features
     * @return array<string, list<array{label: string, icon: ?string}>>
     */
    private function featureGroups(array $features): array
    {
        $groups = [];
        foreach ($features as $feature) {
            $groups[(string) $feature['feature_group']][] = [
                'label' => self::localized($feature['name'], $feature['name_translations']),
                'icon' => !empty($feature['icon']) ? (string) $feature['icon'] : null,
            ];
        }

        return $groups;
    }

    /** Lien externe (vidéo, visite 360°) : seuls http(s) sont acceptés. */
    private function link(mixed $url): ?string
    {
        $url = trim((string) ($url ?? ''));

        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }

    /** Libellé dans la langue du site, avec repli sur le libellé français du référentiel. */
    private static function localized(mixed $name, mixed $translations): string
    {
        $locale = locale();
        if ($locale !== 'fr' && is_string($translations) && $translations !== '') {
            $decoded = json_decode($translations, true);
            if (is_array($decoded) && !empty($decoded[$locale])) {
                return (string) $decoded[$locale];
            }
        }

        return (string) $name;
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
