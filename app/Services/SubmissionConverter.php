<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Site;
use App\Models\User;
use App\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Transforme un bien confié par un particulier en annonce **brouillon** de Weblogy.
 *
 * L'annonce reprend ce que le particulier a décrit (type, transaction, localisation, surfaces,
 * prix, titre de propriété) et ses photos, copiées dans les trois largeurs publiques. Elle reste un
 * brouillon : l'équipe rédige le titre et la description, complète les critères, puis publie.
 * Les coordonnées du particulier vont dans `property_private_details`, jamais sur le site.
 */
final class SubmissionConverter
{
    public function __construct(
        private readonly Database $db,
        private readonly PropertyRepository $properties,
        private readonly SubmissionRepository $submissions,
        private readonly PrivateFiles $files,
        private readonly ImageUploader $images,
        private readonly Settings $settings,
    ) {
    }

    /**
     * @param array<string, mixed> $submission SubmissionRepository::find()
     * @return array{id: int, reference: string}
     */
    public function convert(array $submission, User $staff, Site $site): array
    {
        if ($submission['property_id'] !== null) {
            throw new RuntimeException('Ce dossier a déjà une annonce.');
        }
        if (in_array($submission['status'], ['rejected', 'withdrawn'], true)) {
            throw new RuntimeException('Dossier refusé ou retiré : pas d’annonce.');
        }

        $place = $submission['commune_name'] ?? $submission['city_name'];
        $title = mb_substr(__('submissions.convert.title', ['type' => $submission['category_name'], 'place' => $place]), 0, 160);
        $written = [];

        try {
            $result = $this->db->transaction(function () use ($submission, $staff, $site, $title, &$written): array {
                $id = $this->properties->create([
                    'country_id' => $site->country->id,
                    'source' => 'private_owner',
                    'transaction_type_id' => (int) $submission['transaction_type_id'],
                    'category_id' => (int) $submission['category_id'],
                    'title' => $title,
                    'slug' => Str::slug($title, 190),
                    'description' => (string) $submission['description'],
                    'price' => $submission['price'],
                    'price_period' => (string) $submission['price_period'],
                    'is_negotiable' => (int) $submission['is_negotiable'],
                    'currency_code' => $site->country->currencyCode,
                    'city_id' => (int) $submission['city_id'],
                    'commune_id' => $submission['commune_id'],
                    'district_id' => $submission['district_id'],
                    'address' => $submission['address'],
                    'show_exact_location' => 0,
                    'living_area' => $submission['living_area'],
                    'land_area' => $submission['land_area'],
                    'rooms' => $submission['rooms'],
                    'bedrooms' => $submission['bedrooms'],
                    'bathrooms' => $submission['bathrooms'],
                    'status' => 'draft',
                    'created_by_user_id' => $staff->id,
                    'updated_by_user_id' => $staff->id,
                ], (string) $this->settings->get('listing.reference_prefix', 'IAN'));

                $this->properties->savePrivateDetails($id, [
                    'owner_name' => trim($submission['owner_first_name'] . ' ' . $submission['owner_last_name']),
                    'owner_phone' => $submission['owner_phone'],
                    'owner_email' => $submission['owner_email'],
                    'internal_notes' => __('submissions.convert.note', ['id' => $submission['id']]),
                ]);

                // Titre de propriété déclaré : option du critère `title_type`, s'il existe toujours.
                if (!empty($submission['title_type'])) {
                    $option = $this->db->selectOne(
                        "SELECT o.id, o.attribute_id FROM property_attribute_options o JOIN property_attributes a ON a.id = o.attribute_id
                         WHERE a.code = 'title_type' AND o.code = :code AND o.is_active = 1",
                        ['code' => $submission['title_type']]
                    );
                    if ($option !== null) {
                        $this->db->insert('property_attribute_values', ['property_id' => $id, 'attribute_id' => (int) $option['attribute_id'], 'value_option_id' => (int) $option['id']]);
                    }
                }

                // Photos : copiées depuis le stockage privé vers les trois largeurs publiques de l'annonce.
                $directory = strtolower($site->country->iso2) . '/annonces/' . $id;
                foreach ($this->submissions->files((int) $submission['id'], 'photo') as $position => $photo) {
                    $source = $this->files->absolutePath((string) $photo['path']);
                    if ($source === null) {
                        continue;
                    }
                    $stored = $this->images->storeVariants(['tmp_name' => $source], $directory);
                    $written[] = $stored['path'];
                    $this->properties->insertImage([
                        'property_id' => $id,
                        'path' => $stored['path'],
                        'original_name' => mb_substr((string) $photo['original_name'], 0, 255),
                        'mime_type' => 'image/webp',
                        'width' => $stored['width'],
                        'height' => $stored['height'],
                        'size_bytes' => $stored['size'],
                        'sort_order' => $position,
                    ]);
                }

                $this->properties->recordStatus($id, null, 'draft', null, $staff->id);
                $this->submissions->update((int) $submission['id'], ['property_id' => $id, 'status' => 'in_review'], $staff->id);

                return ['id' => $id, 'reference' => (string) $this->db->scalar('SELECT reference FROM properties WHERE id = :id', ['id' => $id])];
            });
        } catch (Throwable $exception) {
            foreach ($written as $base) {
                $this->images->deleteVariants($base);
            }
            throw $exception;
        }

        return $result;
    }
}
