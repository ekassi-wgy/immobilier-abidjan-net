<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Mise en forme d'une annonce pour le back-office : fiche récapitulative et différences d'une révision.
 */
final class PropertyPresenter
{
    public function __construct(
        private readonly Database $db,
        private readonly CatalogRepository $catalog,
    ) {
    }

    /**
     * Valeur lisible d'un critère.
     *
     * @param array<string, mixed> $attribute
     */
    public function attributeValue(array $attribute, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $formatted = match ((string) $attribute['input_type']) {
            'boolean' => __((int) $value === 1 ? 'cmsadmin.yes' : 'cmsadmin.no'),
            'select' => $attribute['options'][(int) $value] ?? $this->optionLabel((int) $value),
            'multiselect' => implode(', ', array_map(fn ($option): string => $attribute['options'][(int) $option] ?? $this->optionLabel((int) $option), (array) $value)),
            'decimal' => rtrim(rtrim(number_format((float) $value, 2, ',', "\u{00A0}"), '0'), ','),
            'integer', 'year' => (string) (int) $value,
            'date' => substr((string) $value, 0, 10),
            default => (string) $value,
        };

        return $attribute['unit'] && !in_array($attribute['input_type'], ['boolean', 'select', 'multiselect', 'date', 'year'], true)
            ? $formatted . "\u{00A0}" . $attribute['unit']
            : $formatted;
    }

    /**
     * Différences entre la version en ligne et une révision : liste de [libellé, avant, après].
     *
     * @param array<string, mixed>             $property
     * @param array<string, mixed>             $revisionData
     * @param array<int, array<string, mixed>> $liveAttributes   Schéma de la catégorie en ligne
     * @param array<int, mixed>                $liveValues
     * @param array<int, array<string, mixed>> $newAttributes    Schéma de la catégorie proposée
     * @param list<int>                        $liveFeatures
     * @param list<array<string, mixed>>       $liveImages
     * @return list<array{label: string, before: ?string, after: ?string}>
     */
    public function revisionDiff(array $property, array $revisionData, array $liveAttributes, array $liveValues, array $newAttributes, array $liveFeatures, array $liveImages): array
    {
        // `property_revisions.data` est du JSON libre : une charge utile incomplète (format plus
        // ancien, correction manuelle en base) ne doit pas faire tomber la fiche en erreur 500.
        $revisionData += ['fields' => [], 'attributes' => [], 'features' => [], 'images' => []];

        $fields = (array) $revisionData['fields'];
        $diff = [];
        $add = static function (string $label, mixed $before, mixed $after) use (&$diff): void {
            $before = $before === null || $before === '' ? null : (string) $before;
            $after = $after === null || $after === '' ? null : (string) $after;
            if ($before !== $after) {
                $diff[] = ['label' => $label, 'before' => $before, 'after' => $after];
            }
        };

        $add(__('properties.fields.transaction'), $this->name('transaction_types', $property['transaction_type_id']), $this->name('transaction_types', $fields['transaction_type_id'] ?? null));
        $add(__('properties.fields.category'), $this->name('property_categories', $property['category_id']), $this->name('property_categories', $fields['category_id'] ?? null));
        $add(__('properties.fields.title'), $property['title'], $fields['title'] ?? null);
        $add(__('properties.fields.description'), $property['description'], $fields['description'] ?? null);
        $add(__('properties.fields.price'), $this->price($property['price'], $property['price_period']), $this->price($fields['price'] ?? null, $fields['price_period'] ?? 'total'));
        $add(__('properties.fields.negotiable'), __((int) $property['is_negotiable'] === 1 ? 'cmsadmin.yes' : 'cmsadmin.no'), __((int) ($fields['is_negotiable'] ?? 0) === 1 ? 'cmsadmin.yes' : 'cmsadmin.no'));
        $add(__('properties.fields.charges'), $property['charges'] !== null ? format_price($property['charges']) : null, isset($fields['charges']) ? format_price($fields['charges']) : null);
        $add(__('properties.fields.agency_fee'), $property['agency_fee_percent'] !== null ? (float) $property['agency_fee_percent'] . ' %' : null, isset($fields['agency_fee_percent']) ? (float) $fields['agency_fee_percent'] . ' %' : null);
        $add(__('properties.fields.location'), $this->location($property), $this->location($fields));
        $add(__('properties.fields.address'), $property['address'], $fields['address'] ?? null);
        $add(__('properties.fields.coordinates'), $this->coordinates($property), $this->coordinates($fields));
        $add(__('properties.fields.availability'), __('properties.availability.' . $property['availability']), __('properties.availability.' . ($fields['availability'] ?? 'available')));
        $add(__('properties.fields.available_from'), $property['available_from'], $fields['available_from'] ?? null);
        $add(__('properties.fields.video_url'), $property['video_url'], $fields['video_url'] ?? null);
        $add(__('properties.fields.virtual_tour_url'), $property['virtual_tour_url'], $fields['virtual_tour_url'] ?? null);
        $add(__('properties.fields.document'), $property['document_path'] !== null ? basename((string) $property['document_path']) : null, !empty($fields['document_path']) ? basename((string) $fields['document_path']) : null);
        foreach (['contact_name', 'contact_phone', 'contact_whatsapp', 'contact_email'] as $contact) {
            $add(__('properties.fields.' . $contact), $property[$contact], $fields[$contact] ?? null);
        }

        // Critères (colonnes et EAV), sur l'union des deux schémas
        $newValues = (array) $revisionData['attributes'];
        foreach ($newAttributes as $id => $attribute) {
            if ($attribute['storage'] === 'column') {
                $newValues[$id] = $fields[$attribute['column_name']] ?? null;
            }
        }
        foreach ($liveAttributes + $newAttributes as $id => $attribute) {
            $add($attribute['name'], $this->attributeValue($liveAttributes[$id] ?? $attribute, $liveValues[$id] ?? null), $this->attributeValue($newAttributes[$id] ?? $attribute, $newValues[$id] ?? null));
        }

        // Équipements
        $features = [];
        foreach ($this->catalog->featureChoices() as $group) {
            $features += $group;
        }
        $label = static fn (array $ids): ?string => $ids === [] ? null : implode(', ', array_map(static fn (int $id): string => $features[$id] ?? '#' . $id, $ids));
        $newFeatures = array_map('intval', (array) $revisionData['features']);
        sort($liveFeatures);
        sort($newFeatures);
        $add(__('properties.sections.features'), $label($liveFeatures), $label($newFeatures));

        // Photos
        $liveIds = array_map('intval', array_column($liveImages, 'id'));
        $newIds = array_map('intval', array_column((array) $revisionData['images'], 'id'));
        $added = count(array_diff($newIds, $liveIds));
        $removed = count(array_diff($liveIds, $newIds));
        $reordered = $added === 0 && $removed === 0 && $liveIds !== $newIds;
        if ($added > 0 || $removed > 0 || $reordered) {
            $diff[] = [
                'label' => __('properties.sections.photos'),
                'before' => __('properties.photos_count', ['count' => count($liveIds)]),
                'after' => implode(' · ', array_filter([
                    __('properties.photos_count', ['count' => count($newIds)]),
                    $added > 0 ? __('properties.photos_added', ['count' => $added]) : null,
                    $removed > 0 ? __('properties.photos_removed', ['count' => $removed]) : null,
                    $reordered ? __('properties.photos_reordered') : null,
                ])),
            ];
        }

        return $diff;
    }

    public function price(mixed $price, ?string $period): string
    {
        if ($price === null || $price === '') {
            return __('common.price_on_request');
        }
        $label = price_period_label((string) $period);

        return format_price($price) . ($label !== '' ? ' ' . $label : '');
    }

    /** @param array<string, mixed> $row */
    public function location(array $row): ?string
    {
        $parts = array_filter([
            $this->name('districts', $row['district_id'] ?? null),
            $this->name('communes', $row['commune_id'] ?? null),
            $this->name('cities', $row['city_id'] ?? null),
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /** @param array<string, mixed> $row */
    private function coordinates(array $row): ?string
    {
        return !empty($row['latitude']) && !empty($row['longitude']) ? (float) $row['latitude'] . ', ' . (float) $row['longitude'] : null;
    }

    private function name(string $table, mixed $id): ?string
    {
        static $cache = [];
        if ($id === null || $id === '' || !in_array($table, ['transaction_types', 'property_categories', 'cities', 'communes', 'districts'], true)) {
            return null;
        }

        return $cache[$table][(int) $id] ??= (string) ($this->db->scalar("SELECT name FROM `{$table}` WHERE id = :id", ['id' => (int) $id]) ?? '#' . $id);
    }

    private function optionLabel(int $optionId): string
    {
        return (string) ($this->db->scalar('SELECT label FROM property_attribute_options WHERE id = :id', ['id' => $optionId]) ?? '#' . $optionId);
    }
}
