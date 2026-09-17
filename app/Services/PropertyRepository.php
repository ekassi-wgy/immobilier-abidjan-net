<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Annonces : lecture et écriture en base (champs, critères EAV / colonnes, équipements, photos, historique, révisions).
 * Les règles métier (qui publie quoi, révisions, notifications) sont dans PropertyWorkflow.
 *
 * Toutes les lectures sont limitées au pays, et à l'agence pour un compte agence.
 * property_private_details n'est lu qu'ici, pour le back-office : jamais dans une requête publique.
 */
final class PropertyRepository
{
    public const STATUSES = ['draft', 'pending', 'published', 'rejected', 'unpublished', 'archived', 'expired'];
    public const AVAILABILITIES = ['available', 'reserved', 'sold', 'rented'];
    public const PRICE_PERIODS = ['total', 'month', 'week', 'night', 'year'];
    public const SOURCES = ['agency', 'platform', 'private_owner'];
    public const COLUMN_ATTRIBUTES = ['living_area', 'land_area', 'rooms', 'bedrooms', 'bathrooms'];

    /** Champs de la table properties modifiables par le formulaire (et donc par une révision). */
    public const EDITABLE_FIELDS = [
        'source', 'agency_id', 'agent_user_id', 'transaction_type_id', 'category_id', 'title', 'slug', 'description',
        'internal_reference', 'price', 'price_period', 'is_negotiable', 'charges', 'agency_fee_percent', 'city_id',
        'commune_id', 'district_id', 'address', 'latitude', 'longitude', 'show_exact_location', 'living_area', 'land_area',
        'rooms', 'bedrooms', 'bathrooms', 'availability', 'available_from', 'video_url', 'virtual_tour_url',
        'document_path', 'contact_name', 'contact_phone', 'contact_whatsapp', 'contact_email',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    // Listes -----------------------------------------------------------------------------------------

    /**
     * @param array{q?: string, statut?: string, categorie?: int, commune?: int, agence?: int, une?: bool} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginate(int $countryId, ?int $agencyId, array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->listConditions($countryId, $agencyId, $filters);
        $from = "FROM properties p
                 JOIN property_categories c ON c.id = p.category_id
                 JOIN transaction_types t ON t.id = p.transaction_type_id
                 JOIN cities ci ON ci.id = p.city_id
                 LEFT JOIN communes m ON m.id = p.commune_id
                 LEFT JOIN districts d ON d.id = p.district_id
                 LEFT JOIN agencies a ON a.id = p.agency_id
                 WHERE {$where}";

        return [
            'total' => (int) $this->db->scalar("SELECT COUNT(*) {$from}", $params),
            'rows' => $this->db->select(
                "SELECT p.id, p.reference, p.title, p.status, p.source, p.price, p.currency_code, p.price_period, p.availability,
                        p.is_featured, p.featured_until, p.expires_at, p.published_at, p.submitted_at, p.updated_at, p.created_at,
                        p.views_count, p.leads_count, p.living_area, p.land_area, p.bedrooms,
                        c.name AS category_name, t.name AS transaction_name, ci.name AS city_name, m.name AS commune_name,
                        d.name AS district_name, a.name AS agency_name,
                        (SELECT i.path FROM property_images i WHERE i.property_id = p.id AND i.revision_id IS NULL ORDER BY i.sort_order, i.id LIMIT 1) AS cover_path,
                        (SELECT COUNT(*) FROM property_images i WHERE i.property_id = p.id AND i.revision_id IS NULL) AS photos_count,
                        (SELECT r.id FROM property_revisions r WHERE r.property_id = p.id AND r.status = 'pending' LIMIT 1) AS pending_revision_id
                 {$from}
                 ORDER BY FIELD(p.status, 'pending') DESC, COALESCE(p.updated_at, p.created_at) DESC
                 LIMIT :limit OFFSET :offset",
                $params + ['limit' => $limit, 'offset' => $offset]
            ),
        ];
    }

    /**
     * Compteurs des onglets : par statut, plus « revision » (modifications d'annonces publiées en attente).
     *
     * @return array<string, int>
     */
    public function countsByStatus(int $countryId, ?int $agencyId): array
    {
        $params = ['country' => $countryId];
        $scope = 'p.country_id = :country AND p.deleted_at IS NULL';
        if ($agencyId !== null) {
            $scope .= ' AND p.agency_id = :agency';
            $params['agency'] = $agencyId;
        }

        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($this->db->select("SELECT p.status, COUNT(*) AS n FROM properties p WHERE {$scope} GROUP BY p.status", $params) as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }
        $counts['revision'] = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM property_revisions r JOIN properties p ON p.id = r.property_id WHERE r.status = 'pending' AND {$scope}",
            $params
        );

        return $counts;
    }

    /** Annonces à traiter par l'équipe : en attente + révisions en attente (pastille du menu). */
    public function toReviewCount(int $countryId): int
    {
        $counts = $this->countsByStatus($countryId, null);

        return $counts['pending'] + $counts['revision'];
    }

    // Lecture d'une annonce ----------------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function findByReference(string $reference, int $countryId, ?int $agencyId = null): ?array
    {
        $params = ['reference' => $reference, 'country' => $countryId];
        $agencyCondition = '';
        if ($agencyId !== null) {
            $agencyCondition = ' AND p.agency_id = :agency';
            $params['agency'] = $agencyId;
        }

        return $this->db->selectOne(
            "SELECT p.*, c.name AS category_name, c.parent_id AS category_parent_id, t.name AS transaction_name, t.code AS transaction_code,
                    ci.name AS city_name, m.name AS commune_name, d.name AS district_name, a.name AS agency_name,
                    CONCAT(ag.first_name, ' ', ag.last_name) AS agent_name,
                    CONCAT(cr.first_name, ' ', cr.last_name) AS created_by_name,
                    CONCAT(rv.first_name, ' ', rv.last_name) AS reviewed_by_name
             FROM properties p
             JOIN property_categories c ON c.id = p.category_id
             JOIN transaction_types t ON t.id = p.transaction_type_id
             JOIN cities ci ON ci.id = p.city_id
             LEFT JOIN communes m ON m.id = p.commune_id
             LEFT JOIN districts d ON d.id = p.district_id
             LEFT JOIN agencies a ON a.id = p.agency_id
             LEFT JOIN users ag ON ag.id = p.agent_user_id
             LEFT JOIN users cr ON cr.id = p.created_by_user_id
             LEFT JOIN users rv ON rv.id = p.reviewed_by_user_id
             WHERE p.reference = :reference AND p.country_id = :country AND p.deleted_at IS NULL{$agencyCondition}",
            $params
        );
    }

    /**
     * Valeurs des critères (colonnes et EAV) : [attribute_id => valeur | list<option_id>].
     *
     * @param array<int, array<string, mixed>> $attributes Schéma (CatalogRepository::formSchema()['attributes'])
     * @param array<string, mixed>             $property
     * @return array<int, mixed>
     */
    public function attributeValues(int $propertyId, array $attributes, array $property): array
    {
        $values = [];
        foreach ($attributes as $id => $attribute) {
            if ($attribute['storage'] === 'column') {
                $values[$id] = $property[$attribute['column_name']] ?? null;
            }
        }

        foreach ($this->db->select('SELECT * FROM property_attribute_values WHERE property_id = :id', ['id' => $propertyId]) as $row) {
            $id = (int) $row['attribute_id'];
            $type = $attributes[$id]['input_type'] ?? null;
            if ($type === null) {
                continue;
            }
            $value = match ($type) {
                'integer', 'year' => $row['value_integer'],
                'decimal' => $row['value_decimal'],
                'boolean' => $row['value_boolean'],
                'date' => $row['value_date'],
                'select', 'multiselect' => $row['value_option_id'] !== null ? (int) $row['value_option_id'] : null,
                default => $row['value_text'],
            };
            if ($type === 'multiselect') {
                $values[$id][] = $value;
            } else {
                $values[$id] = $value;
            }
        }

        return $values;
    }

    /** @return list<int> */
    public function featureIds(int $propertyId): array
    {
        return array_map('intval', array_column($this->db->select('SELECT feature_id FROM property_features WHERE property_id = :id', ['id' => $propertyId]), 'feature_id'));
    }

    /**
     * Photos en ligne (revision_id NULL), ou celles d'une révision en plus.
     *
     * @return list<array<string, mixed>>
     */
    public function images(int $propertyId, ?int $revisionId = null): array
    {
        return $this->db->select(
            'SELECT * FROM property_images WHERE property_id = :id AND (revision_id IS NULL OR revision_id = :revision) ORDER BY sort_order, id',
            ['id' => $propertyId, 'revision' => $revisionId ?? 0]
        );
    }

    /** @return array<string, mixed> */
    public function privateDetails(int $propertyId): array
    {
        return $this->db->selectOne('SELECT * FROM property_private_details WHERE property_id = :id', ['id' => $propertyId]) ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function statusHistory(int $propertyId): array
    {
        return $this->db->select(
            "SELECT h.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name FROM property_status_history h
             LEFT JOIN users u ON u.id = h.user_id WHERE h.property_id = :id ORDER BY h.created_at DESC, h.id DESC",
            ['id' => $propertyId]
        );
    }

    // Écriture ------------------------------------------------------------------------------------------

    /**
     * Crée l'annonce et lui attribue sa référence publique (préfixe + numéro).
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data, string $referencePrefix): int
    {
        $data['reference'] = 'TMP-' . bin2hex(random_bytes(6));
        $id = $this->db->insert('properties', $data);
        $this->db->execute('UPDATE properties SET reference = :reference WHERE id = :id', ['reference' => $referencePrefix . '-' . (10000 + $id), 'id' => $id]);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $sets = implode(', ', array_map(static fn (string $column): string => "`{$column}` = :{$column}", array_keys($data)));
        $this->db->execute("UPDATE properties SET {$sets} WHERE id = :id", $data + ['id' => $id]);
    }

    /**
     * Enregistre les critères EAV (les critères « colonne » sont dans $data de update()).
     *
     * @param array<int, array<string, mixed>> $attributes Schéma de la catégorie
     * @param array<int, mixed>                $values     Valeurs validées
     */
    public function saveAttributeValues(int $propertyId, array $attributes, array $values): void
    {
        $this->db->execute('DELETE FROM property_attribute_values WHERE property_id = :id', ['id' => $propertyId]);

        foreach ($attributes as $id => $attribute) {
            if ($attribute['storage'] === 'column' || !array_key_exists($id, $values) || $values[$id] === null || $values[$id] === [] || $values[$id] === '') {
                continue;
            }

            $rows = $attribute['input_type'] === 'multiselect' ? array_map(static fn ($v): array => ['option' => (int) $v], (array) $values[$id]) : [['value' => $values[$id]]];
            foreach ($rows as $row) {
                $column = match ($attribute['input_type']) {
                    'integer', 'year' => ['value_integer' => (int) $row['value']],
                    'decimal' => ['value_decimal' => (string) $row['value']],
                    'boolean' => ['value_boolean' => (int) (bool) $row['value']],
                    'date' => ['value_date' => (string) $row['value']],
                    'select' => ['value_option_id' => (int) $row['value']],
                    'multiselect' => ['value_option_id' => $row['option']],
                    default => ['value_text' => (string) $row['value']],
                };
                $this->db->insert('property_attribute_values', ['property_id' => $propertyId, 'attribute_id' => $id] + $column);
            }
        }
    }

    /** @param list<int> $featureIds */
    public function saveFeatures(int $propertyId, array $featureIds): void
    {
        $this->db->execute('DELETE FROM property_features WHERE property_id = :id', ['id' => $propertyId]);
        foreach (array_unique($featureIds) as $featureId) {
            $this->db->execute('INSERT INTO property_features (property_id, feature_id) VALUES (:p, :f)', ['p' => $propertyId, 'f' => $featureId]);
        }
    }

    /** @param array<string, mixed> $data */
    public function savePrivateDetails(int $propertyId, array $data): void
    {
        $data = array_intersect_key($data, array_flip(['owner_name', 'owner_phone', 'owner_email', 'notary_name', 'notary_reference', 'internal_notes']));
        if (array_filter($data, static fn ($v): bool => $v !== null && $v !== '') === []) {
            $this->db->execute('DELETE FROM property_private_details WHERE property_id = :id', ['id' => $propertyId]);

            return;
        }
        $columns = array_keys($data);
        $this->db->execute(
            'INSERT INTO property_private_details (property_id, ' . implode(', ', $columns) . ') VALUES (:property_id, :' . implode(', :', $columns) . ')
             ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(static fn (string $c): string => "{$c} = VALUES({$c})", $columns)),
            ['property_id' => $propertyId] + $data
        );
    }

    /** @param array<string, mixed> $image */
    public function insertImage(array $image): int
    {
        return $this->db->insert('property_images', $image);
    }

    public function updateImage(int $imageId, int $propertyId, int $sortOrder, ?string $alt): void
    {
        $this->db->execute(
            'UPDATE property_images SET sort_order = :sort, alt_text = :alt WHERE id = :id AND property_id = :property',
            ['sort' => $sortOrder, 'alt' => $alt, 'id' => $imageId, 'property' => $propertyId]
        );
    }

    public function deleteImageRow(int $imageId): void
    {
        $this->db->execute('DELETE FROM property_images WHERE id = :id', ['id' => $imageId]);
    }

    public function recordStatus(int $propertyId, ?string $from, string $to, ?string $reason, ?int $userId): void
    {
        $this->db->insert('property_status_history', ['property_id' => $propertyId, 'from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'user_id' => $userId]);
    }

    public function softDelete(int $propertyId): void
    {
        $this->db->execute("UPDATE properties SET deleted_at = UTC_TIMESTAMP(), is_featured = 0 WHERE id = :id", ['id' => $propertyId]);
    }

    // Révisions -----------------------------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function pendingRevision(int $propertyId): ?array
    {
        $revision = $this->db->selectOne(
            "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) AS submitted_by_name FROM property_revisions r
             LEFT JOIN users u ON u.id = r.submitted_by_user_id WHERE r.property_id = :id AND r.status = 'pending' ORDER BY r.id DESC LIMIT 1",
            ['id' => $propertyId]
        );
        if ($revision !== null) {
            $revision['data'] = json_decode((string) $revision['data'], true) ?: [];
        }

        return $revision;
    }

    /** @return array<string, mixed>|null Dernière révision rejetée (motif à afficher à l'agence) */
    public function lastRejectedRevision(int $propertyId): ?array
    {
        return $this->db->selectOne(
            "SELECT id, rejection_reason, reviewed_at FROM property_revisions WHERE property_id = :id AND status = 'rejected'
               AND reviewed_at > COALESCE((SELECT MAX(submitted_at) FROM property_revisions WHERE property_id = :id2 AND status IN ('pending', 'approved')), '1970-01-01')
             ORDER BY id DESC LIMIT 1",
            ['id' => $propertyId, 'id2' => $propertyId]
        );
    }

    /** @param array<string, mixed> $data */
    public function createRevision(int $propertyId, array $data, int $userId): int
    {
        return $this->db->insert('property_revisions', [
            'property_id' => $propertyId,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'submitted_by_user_id' => $userId,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updateRevisionData(int $revisionId, array $data, int $userId): void
    {
        $this->db->execute(
            'UPDATE property_revisions SET data = :data, submitted_by_user_id = :user, submitted_at = UTC_TIMESTAMP() WHERE id = :id',
            ['data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'user' => $userId, 'id' => $revisionId]
        );
    }

    public function closeRevision(int $revisionId, string $status, ?string $reason, ?int $userId): void
    {
        $this->db->execute(
            'UPDATE property_revisions SET status = :status, rejection_reason = :reason, reviewed_by_user_id = :user, reviewed_at = UTC_TIMESTAMP() WHERE id = :id',
            ['status' => $status, 'reason' => $reason, 'user' => $userId, 'id' => $revisionId]
        );
    }

    /** Photos d'une révision rattachées à la version en ligne (après approbation). */
    public function promoteRevisionImages(int $revisionId): void
    {
        $this->db->execute('UPDATE property_images SET revision_id = NULL WHERE revision_id = :id', ['id' => $revisionId]);
    }

    /** @return list<array<string, mixed>> */
    public function revisionImages(int $revisionId): array
    {
        return $this->db->select('SELECT * FROM property_images WHERE revision_id = :id', ['id' => $revisionId]);
    }

    // Tâches planifiées ---------------------------------------------------------------------------------

    /** @return list<array<string, mixed>> Annonces publiées arrivées à expiration */
    public function dueForExpiry(): array
    {
        return $this->db->select(
            "SELECT id, reference, title, country_id, agency_id, created_by_user_id, agent_user_id FROM properties
             WHERE status = 'published' AND deleted_at IS NULL AND expires_at IS NOT NULL AND expires_at <= UTC_TIMESTAMP()"
        );
    }

    /** @return list<array<string, mixed>> Annonces publiées expirant dans moins de N jours, non encore relancées */
    public function dueForReminder(int $days): array
    {
        return $this->db->select(
            "SELECT id, reference, title, country_id, agency_id, created_by_user_id, agent_user_id, expires_at FROM properties
             WHERE status = 'published' AND deleted_at IS NULL AND expires_at IS NOT NULL AND expiry_reminder_sent_at IS NULL
               AND expires_at > UTC_TIMESTAMP() AND expires_at <= UTC_TIMESTAMP() + INTERVAL :days DAY",
            ['days' => $days]
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function listConditions(int $countryId, ?int $agencyId, array $filters): array
    {
        $where = 'p.country_id = :country AND p.deleted_at IS NULL';
        $params = ['country' => $countryId];

        if ($agencyId !== null) {
            $where .= ' AND p.agency_id = :agency';
            $params['agency'] = $agencyId;
        } elseif (!empty($filters['agence'])) {
            $where .= ' AND p.agency_id = :agency';
            $params['agency'] = (int) $filters['agence'];
        }

        $statut = (string) ($filters['statut'] ?? '');
        if ($statut === 'revision') {
            $where .= " AND EXISTS (SELECT 1 FROM property_revisions r WHERE r.property_id = p.id AND r.status = 'pending')";
        } elseif (in_array($statut, self::STATUSES, true)) {
            $where .= ' AND p.status = :status';
            $params['status'] = $statut;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (p.reference LIKE :q OR p.title LIKE :q2 OR p.internal_reference LIKE :q3 OR d.name LIKE :q4)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params += ['q' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        if (!empty($filters['categorie'])) {
            $where .= ' AND (p.category_id = :category OR c.parent_id = :category2)';
            $params['category'] = (int) $filters['categorie'];
            $params['category2'] = (int) $filters['categorie'];
        }
        if (!empty($filters['commune'])) {
            $where .= ' AND p.commune_id = :commune';
            $params['commune'] = (int) $filters['commune'];
        }
        if (!empty($filters['une'])) {
            $where .= ' AND p.is_featured = 1';
        }

        return [$where, $params];
    }
}
